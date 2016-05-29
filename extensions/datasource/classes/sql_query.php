<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf\datasource;

use de\toxa\txf\string as string;
use de\toxa\txf\browseable as browseable;


/**
 * Conveniently features cumulative construction of an SQL query.
 *
 * This class is available to enable distributed creation and modification of
 * SQL-based queries to datasources.
 *
 * Since most methods are returning reference on current instance it is possible
 * to build a chain of calls to different methods.
 *
 * @example
 *
 *   $query = $datasource->createQuery( 'employees em' )
 *               ->addDataset( 'salary s', 's.employee=em.id AND em.department<>?', 'management' )
 *               ->addProperty( 'em.id' )
 *               ->addProperty( 'CONCAT(em.firstname," ",em.surname)', 'fullname' )
 *               ->addProperty( 'IF(s.monthly>?,"high","low to moderate")', 'salary_class', 6000 )
 *               ->addCondition( 'em.employed_since<?', true, date( 'Y-m-d', time()-86400*365*10 ) );
 *
 * resulting in a query to be compiled later similar to this
 *
 *   SELECT
 *    em.id,
 *    CONCAT(em.firstname," ",em.surname) AS fullname,
 *    IF(s.monthly>6000,"high","low to moderate") AS salary_class
 *   FROM
 *    employees em
 *   LEFT JOIN
 *    salary s ON ( s.employee=em.id AND em.department<>"management" )
 *   WHERE
 *    em.employed_since<"2001-02-03"
 *
 * (showing parameter values here instead of parameter markers actually used).
 *
 * The benefit of class query is in situations like this:
 *
 * Given $query above any code may decide to adjust the query without parsing
 * and analysing what has been added to query before. If listing employees must
 * not include managers at all and should include employees' ages in addition
 * that code might run
 *
 *   $query->addCondition( 'em.department<>?', true, 'management' )
 *	       ->addProperty( 'em.age' );
 *
 * Later it might add limit and sorting according to current script context:
 *
 *   $query->addOrder( 'em.fullname', false )
 *         ->limit( 10, 65 );
 *
 * This would finally result in an SQL-query like this:
 *
 *   SELECT
 *    em.id,
 *    CONCAT(em.firstname," ",em.surname) AS fullname,
 *    IF(s.monthly>6000,"high","low to moderate") AS salary_class,
 *    em.age
 *   FROM
 *    employees em
 *   LEFT JOIN
 *    salary s ON ( s.employee=em.id AND em.department<>"management" )
 *   WHERE
 *    em.employed_since<"2001-02-03"
 *    AND
 *    em.department<>"management"
 *   ORDER BY
 *    em.fullname ASC
 *   LIMIT
 *    10 OFFSET 65
 *
 */


class sql_query implements query, browseable
{

	/**
	 * connection query is related to
	 *
	 * @var pdo
	 */

	protected $connection = null;

	/**
	 * set of tables to join in query
	 *
	 * @var array
	 */

	protected $tables = array();

	/**
	 * columns to retrieve in query
	 *
	 * @var array
	 */

	protected $columns = array();

	/**
	 * parameters for parameter binding related to column definitions
	 *
	 * @var array
	 */

	protected $columnParameters = array();

	/**
	 * conditions selecting set of records
	 *
	 * @var array
	 */

	protected $conditions = array();

	/**
	 * parameters for parameter binding related to record-selecting conditions
	 *
	 * @var array
	 */

	protected $conditionParameters = array();

	/**
	 * group definitions
	 *
	 * @var array
	 */

	protected $groups = array();

	/**
	 * additional filters to be applied on matching records (having-clause)
	 *
	 * @var array
	 */

	protected $filters = array();

	/**
	 * parameters for parameter binding related to lately applied filter
	 *
	 * @var array
	 */

	protected $filterParameters = array();

	/**
	 * sorting order definitions
	 *
	 * @var array
	 */

	protected $orders = array();

	/**
	 * number of records to skip
	 *
	 * @var integer
	 */

	protected $offset = null;

	/**
	 * number of records to retrieve at most
	 *
	 * @var integer
	 */

	protected $size = null;


	/**
	 * @param connection $connection link to datasource
	 * @param string $table name of a table optionally including alias
	 */

	public function __construct( connection $connection, $table )
	{
		$this->reconnectDatasource( $connection );

		if ( !is_string( $table ) || trim( $table ) === '' )
			throw new \InvalidArgumentException( 'bad table name' );

		$this->tables[$connection->qualifyDatasetName( trim( $table ), true, true )] = false;
	}

	/**
	 * Reads parameters provided in variable number of additional arguments.
	 *
	 * This method is used by all public methods that support provision of
	 * parameters to be bound.
	 *
	 * Several methods supporting provision of an arbitrary number of additional
	 * parameters collected here for parameter binding in resulting query. You
	 * can either provide several scalar parameters or a single array with all
	 * parameters.
	 *
	 * @param array $parameters parameters provided in call to caller
	 * @param array $collector array to append collected parameters to
	 * @param integer number of arguments expected by caller
	 */

	protected function collectParameters( $parameters, &$collector, $shifting = 1 )
	{
		$parameters = array_values( array_slice( $parameters, $shifting ) );
		if ( count( $parameters ) == 1 && is_array( $parameters[0] ) )
			$parameters = $parameters[0];

		foreach ( $parameters as $parameter )
			$collector[] = is_scalar( $parameter ) ? $parameter : \de\toxa\txf\_S((string)$parameter)->asUtf8;
	}

	/**
	 * Implements common support for lists and arrays in parameter binding.
	 *
	 * This method is replacing (singly supported) occurrence of marker
	 *
	 * %array%
	 *
	 * by sequence of as many comma-separated question marks as parameters are
	 * given in $parameters.
	 *
	 * @param string $term condition used, may contain special marker %array%
	 * @param array $parameters parameters for binding given by grand-caller
	 * @return string probably adjusted term
	 */

	protected static function qualifyTerm( $term, $parameters )
	{
		$term   = \de\toxa\txf\_S($term,null,'')->trim();
		$marker = \de\toxa\txf\_S('%array%');

		if ( $term->indexOf( $marker ) !== false )
			$term = $term->replace( $marker, implode( ',', array_pad( array(), count( $parameters ), '?' ) ) );

		return $term;
	}

	/**
	 * Joins selected data set to include (some of) its columns.
	 *
	 * @example $query->addDataset( 'salary s', 's.employee=em.id AND tax=?', 'full' );
	 *
	 * On providing another instance of query in $dataset it's considered a
	 * subselect and gets instantly compiled prior to joining here.
	 *
	 * The number of parameters must match number of markers in term.
	 *
	 * @param string|query $dataset name and optionally appended alias of data set to join
	 * @param string $condition condition for selecting columns of joined table
	 * @param mixed $parameters array of parameters or first of additional arguments providing one parameter each
	 * @return $this
	 */

	public function addDataset( $dataset, $condition, $parameters = null )
	{
		// first get parameters provided here for joining table
		$joinParameters = array();
		$this->collectParameters( func_get_args(), $joinParameters, 2 );


		// next qualify condition term for properly supporting use of lists here
		$condition = static::qualifyTerm( $condition, $joinParameters );

		if ( $condition->isEmpty )
			throw new \InvalidArgumentException( 'missing/invalid join condition' );


		// finally inspect $table
		if ( $dataset instanceof self )
		{
			// got instance of class query
			// --> consider subselect
			//     --> derive alias from name of basetable in provided query
			$alias = null;

			foreach ( $dataset->tables as $subtable => $subjoin )
				if ( $subjoin === false )
				{
					$alias = array_shift( preg_split( '/\s+/', $subtable ) );
					break;
				}

			// prepend subselect's parameter to provided join parameters
			$joinParameters = array_merge( $dataset->compileParameters(), $joinParameters );

			// finally compile subselect
			$dataset = '(' . strval( $dataset ) . ') inner_' . $alias;
		}
		else
		{
			// got another table name and optionally appended alias
			$dataset = \de\toxa\txf\_S($dataset,null,'');

			if ( $dataset->isEmpty )
				throw new \InvalidArgumentException( 'bad table name' );

			$dataset = $this->connection->qualifyDatasetName( $dataset->asUtf8, true, true );

			if ( $this->tables[$dataset] === false )
				throw new \InvalidArgumentException( 'cannot rejoin base table' );
		}

		$this->tables[$dataset] = array( $condition->asUtf8, $joinParameters );


		return $this;
	}

	public function addProperty( $name, $alias = null, $parameters = null )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new \InvalidArgumentException( 'bad column name' );

		if ( is_null( $alias ) )
			$alias = $name;
		else if ( !is_string( $alias ) || !( $alias = trim( $alias ) ) )
			throw new \InvalidArgumentException( 'bad column alias' );

		$this->columns[$alias] = $name;

		$this->collectParameters( func_get_args(), $this->columnParameters, 2 );

		return $this;
	}

	public function dropProperties( $keepPropertyByName = null )
	{
		$keptProperties = func_get_args();

		foreach ( $this->columns as $key => $spec ) {
			if ( !in_array( $key, $keptProperties ) ) {
				unset( $this->columns[$key] );
			}
		}

		return $this;
	}

	public function addCondition( $term, $union = true, $parameters = null )
	{
		if ( !is_string( $term ) || !( $term = trim( $term ) ) )
			throw new \InvalidArgumentException( 'bad condition term' );

		$conditionParameters = array();

		$this->collectParameters( func_get_args(), $conditionParameters, 2 );

		if ( !count( $this->conditions ) )
			$union = true;

		$this->conditions[] = array( self::qualifyTerm( $term, $conditionParameters )->asUtf8, $union ? ' AND ' : ' OR ' );

		$this->conditionParameters = array_merge( $this->conditionParameters, $conditionParameters );

		return $this;
	}

	public function addFilter( $term, $union = true, $parameters = null )
	{
		if ( !is_string( $term ) || !( $term = trim( $term ) ) )
			throw new \InvalidArgumentException( 'bad filter term' );

		if ( !count( $this->filters ) )
			$union = true;

		$this->filters[] = array( $term, $union ? ' AND ' : ' OR ' );

		$this->collectParameters( func_get_args(), $this->filterParameters, 2 );

		return $this;
	}

	public function addGroup( $name )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new \InvalidArgumentException( 'bad group column' );

		$this->groups[] = $name;

		return $this;
	}

	public function addOrder( $name, $ascending = true )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new \InvalidArgumentException( 'bad order column' );

		$this->orders[] = $name . ( $ascending ? ' ASC' : ' DESC');

		return $this;
	}

	public function limit( $size = 20, $offset = 0 )
	{
		$size   = intval( $size );
		$offset = intval( $offset );

		if ( ( $size > 0 ) || ( $offset > 0 ) )
		{
			$this->size   = max( 1, $size );
			$this->offset = $offset;
		}

		return $this;
	}

	public function compile( $gettingMatchCount = false )
	{
		if ( count( $this->columns ) )
			$columns = implode( ',', array_map(
								function( $alias, $name ) { return ( $alias == $name ) ? $name : "$name AS $alias"; },
								array_keys( $this->columns ),
								array_values( $this->columns )
							) );
		else
			$columns = '*';

		$tables = implode( ' LEFT JOIN ', array_map(
							function( $table, $joinFilter ) {
								return is_array( $joinFilter ) ? "$table ON ( $joinFilter[0] )" : $table;
							},
							array_keys( $this->tables ),
							array_values( $this->tables )
						) );

		$conditions = count( $this->conditions ) ? ' WHERE 1' . implode( '', array_map( function( $c ) { return "$c[1] ( $c[0] )"; }, $this->conditions ) ) : '';

		$groups = count( $this->groups ) ? ' GROUP BY ' . implode( ',', $this->groups ) : '';

		$filters = count( $this->filters ) ? ' HAVING 1' . implode( '', array_map( function( $c ) { return "$c[1] ( $c[0] )"; }, $this->filters ) ) : '';

		if ( !$gettingMatchCount )
		{
			$orders = count( $this->orders ) ? ' ORDER BY ' . implode( ',', $this->orders ) : '';

			if ( $this->size > 0 )
				$limit = " LIMIT $this->size" . ( ( $this->offset > 0 ) ? " OFFSET $this->offset" : '' );
		}
		else
			$orders = $limit = '';


		$sql = "SELECT $columns FROM $tables$conditions$groups$filters$orders$limit";
		if ( $gettingMatchCount )
			$sql = "SELECT COUNT(*) FROM ($sql) ca";

		return $sql;
	}

	public function compileParameters( $gettingMatchCount = false )
	{
		$parameters = $this->columnParameters;

		foreach ( array_map(
							function( $data ) { return ( is_array( $data ) && is_array( $data[1] ) ) ? $data[1] : array(); },
							$this->tables
						) as $data )
			$parameters = array_merge( $parameters, $data );

		$parameters = array_merge( $parameters, $this->conditionParameters );
		$parameters = array_merge( $parameters, $this->filterParameters );

		return $parameters;
	}

	public function execute( $gettingMatchCount = false )
	{
		if ( !( $this->connection instanceof connection ) )
			throw new \RuntimeException( 'missing connection to datasource' );


		$query = $this->compile( $gettingMatchCount );

		$statement = $this->connection->compile( $query );

		$statement->execute( $this->compileParameters( $gettingMatchCount ) );
		if ( $statement->failed )
			throw new datasource_exception( $statement );

		return $statement;
	}

	public function __toString()
	{
		return $this->compile();
	}

	public function __sleep()
	{
		return array( 'tables', 'columns', 'columnParameters', 'conditions', 'conditionParameters', 'groups', 'filters', 'filterParameters', 'orders', 'offset', 'size' );
	}

	public function __wakeup()
	{
		$this->connection = null;
	}

	public function reconnectDatasource( connection $connection )
	{
		if ( !( $connection instanceof pdo ) )
			throw new \InvalidArgumentException( 'sql_query works on pdo datasources, only' );

		$this->connection = $connection;

		return $this;
	}

	public function datasource()
	{
		return $this->connection;
	}


	/*
	 * implementation of browseable interface
	 */

	public function sortBy( $property, $ascending = true )
	{
		return $this->addOrder( $property, $ascending );
	}

	public function offset( $count )
	{
		if ( ctype_digit( trim( $count ) ) )
		{
			if ( $this->size == null )
				$this->size = 10;

			$this->offset = intval( $count );

			return $this;
		}

		throw new \InvalidArgumentException( 'invalid skip' );
	}

	public function size( $count )
	{
		$count = intval( $count );
		if ( $count > 0 )
		{
			$this->size = $count;

			return $this;
		}

		throw new \InvalidArgumentException( 'invalid size' );
	}

	public function items()
	{
		return $this->execute()->all();
	}

	public function count()
	{
		return intval( $this->execute( true )->cell() );
	}

	public function name()
	{
		return implode( '-', array_keys( $this->tables ) );
	}
}

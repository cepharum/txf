<?php


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
 *   $query = query::create( 'employees em' )
 *               ->addJoin( 'salary s', 's.employee=em.id AND em.department<>?', 'management' )
 *               ->addColumn( 'em.id' )
 *               ->addColumn( 'CONCAT(em.firstname," ",em.surname)', 'fullname' )
 *               ->addColumn( 'IF(s.monthly>?,"high","low to moderate")', 'salary_class', 6000 )
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
 * Given this instance any code may decide to adjust the query without parsing
 * and analysing what has been added to query before. If listing employees must
 * not include managers at all and should include employees' ages in addition
 * that code might run
 *
 *   $providedQuery->addCondition( 'em.department<>?', true, 'management' )
 *	               ->addColumn( 'em.age' );
 *
 * Later it might add limit and sorting according to current script context:
 *
 *   $providedQuery->addOrder( 'em.fullname', false )
 *                ->limit( 10, 65 );
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


namespace de\toxa\txf\datasource;

use de\toxa\txf\string as string;
use de\toxa\txf\shortcuts as shortcuts;


class sql_query
{

	protected $name;

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
	 * @param string $table name of a table optionally including alias
	 */

	public function __construct( $connection, $table )
	{
		$this->connection = $connection;

		$table = \de\toxa\txf\_S($table,null,'')->trim();
		if ( $table->isEmpty )
			throw new InvalidArgumentException( 'bad table name' );

		$this->tables[$table->asUtf8] = false;
	}

	/**
	 * Starts construction of query basically addressing provided table.
	 *
	 * @example $query = query::create( 'employees em' );
	 *
	 * @param string $table name of table optionally including alias as suffix
	 * @return query
	 */

	public static function create( $table )
	{
		return new static( $table );
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
		if ( count( $parameters ) == 1 )
			if ( is_array( $parameters[0] ) )
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
	 * Joins selected table to include (some of) its columns.
	 *
	 * @example $query->addJoin( 'salary s', 's.employee=em.id AND tax=?', 'full' );
	 *
	 * On providing another instance of class query in $table it's considered
	 * subselect and
	 *
	 * @param string|query $dataset name and optionally appended alias of dataset to join
	 * @param string $condition condition for selecting columns of joined table
	 * @param mixed $parameters @see query::collectParameters()
	 * @return query reference to current instance for chaining calls
	 */

	public function addJoin( $dataset, $condition, $parameters = null )
	{
		// first get parameters provided here for joining table
		$joinParameters = array();
		$this->collectParameters( func_get_args(), $joinParameters, 2 );


		// next qualify condition term for properly supporting use of lists here
		$condition = static::qualifyTerm( $condition, $joinParameters );

		if ( $condition->isEmpty )
			throw new InvalidArgumentException( 'missing/invalid join condition' );


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
				throw new InvalidArgumentException( 'bad table name' );

			$dataset = $dataset->asUtf8;

			if ( $this->tables[$dataset] === false )
				throw new InvalidArgumentException( 'cannot rejoin base table' );
		}

		$this->tables[$dataset] = array( $condition->asUtf8, $joinParameters );


		return $this;
	}


	/**
	 * Adds column to retrieve from (set of joined) table(s).
	 *
	 * This column may be using alias. $name may contain full SQL term. In that
	 * case $parameters may include some parameters for binding if $name
	 * contains term including parameter markers.
	 *
	 * @param string $name column name or term
	 * @param string $alias alias to assign
	 * @param mixed $parameters @see query::collectParameters()
	 * @return query
	 */

	public function addColumn( $name, $alias = null, $parameters = null )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new InvalidArgumentException( 'bad column name' );

		if ( is_null( $alias ) )
			$alias = $name;
		else if ( !is_string( $alias ) || !( $alias = trim( $alias ) ) )
			throw new InvalidArgumentException( 'bad column alias' );

		$this->columns[$alias] = $name;

		$this->collectParameters( func_get_args(), $this->columnParameters, 2 );

		return $this;
	}

	public function addCondition( $term, $union = true, $parameters = null )
	{
		if ( !is_string( $term ) || !( $term = trim( $term ) ) )
			throw new InvalidArgumentException( 'bad condition term' );

		$conditionParameters = array();

		$this->collectParameters( func_get_args(), $conditionParameters, 2 );

		$this->conditions[] = array( self::qualifyTerm( $term, $conditionParameters )->asUtf8, $union ? ' AND ' : ' OR ' );

		$this->conditionParameters = array_merge( $this->conditionParameters, $conditionParameters );

		return $this;
	}

	public function addFilter( $term, $union = true, $parameters = null )
	{
		if ( !is_string( $term ) || !( $term = trim( $term ) ) )
			throw new InvalidArgumentException( 'bad filter term' );

		$this->filters[] = array( $term, $union ? ' AND ' : ' OR ' );

		$this->collectParameters( func_get_args(), $this->filterParameters, 2 );

		return $this;
	}

	public function addGroup( $name )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new InvalidArgumentException( 'bad group column' );

		$this->groups[] = $name;

		return $this;
	}

	public function addOrder( $name, $ascending = true )
	{
		if ( !is_string( $name ) || !( $name = trim( $name ) ) )
			throw new InvalidArgumentException( 'bad order column' );

		$this->orders[] = $name . ( $ascending ? ' ASC' : ' DESC');

		return $this;
	}

	/**
	 * Requests to limit result set.
	 *
	 * @param integer $size maximum number of records to include in result set
	 * @param integer $offset number of matches to skip
	 * @return sql_query current instance for chaining calls
	 */

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

	/**
	 * Compiles previously described query.
	 *
	 * @param boolean $gettingMatchCount true on requesting count of matches
	 * @return string statement to query actually
	 */

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
							function( $table, $data ) { return is_array( $data ) ? "$table ON ( $data[0] )" : $table; },
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

	/**
	 * Compiles set of parameters to pass for binding in proper order.
	 *
	 * @param boolean $gettingMatchCount true on requesting count of matches
	 * @return array set of parameters to bind
	 */

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

	/**
	 * Executes query providing access on result set.
	 *
	 * @param boolean $gettingMatchCount true to request query for count of matches
	 * @return statement executed statement providing result set
	 */

	public function execute( $gettingMatchCount = false )
	{
		$query = $this->compile( $gettingMatchCount );
		$statement = call_user_func( array( $this->connection, 'compile' ), $query );
		return call_user_func_array( array( $statement, 'execute' ), $this->compileParameters( $gettingMatchCount ) );
	}

	public function __toString()
	{
		return $this->compile();
	}
}


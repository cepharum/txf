<?php


/**
 * Copyright 2012 Thomas Urban, toxA IT-Dienstleistungen
 *
 * This file is part of TXF, toxA's web application framework.
 *
 * TXF is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * TXF is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * TXF. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright 2012, Thomas Urban, toxA IT-Dienstleistungen, www.toxa.de
 * @license GNU GPLv3+
 * @version: $Id$
 *
 */


namespace de\toxa\txf\datasource;


/**
 * Query interface
 *
 * Conveniently features cumulative and distributed construction of a query to
 * fetch records from a (set of) dataset(s).
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
 * @author <thomas.urban@toxa.de>
 *
 */

interface query
{
	/**
	 * @param connection $connection link to a datasource
	 * @param string $table name of a table optionally including alias
	 */

	public function __construct( connection $connection, $table );

	/**
	 * Joins selected dataset to include (some of) its columns.
	 *
	 * @example $query->addDataset( 'salary s', 's.employee=em.id AND tax=?', 'full' );
	 *
	 * On providing another instance of query in $dataset it's considered a
	 * subselect and gets instantly compiled prior to joining here.
	 *
	 * The number of parameters must match number of markers in term.
	 *
	 * @param string|query $dataset name and optionally appended alias of dataset to join
	 * @param string $condition condition for selecting columns of joined table
	 * @param mixed $parameters array of parameters or first of additional arguments providing one parameter each
	 * @return query reference to current instance for chaining calls
	 */

	public function addDataset( $dataset, $condition, $parameters = null );

	/**
	 * Adds property to fetch of all matching records in (one of the joined)
	 * dataset(s).
	 *
	 * This property may be using alias. $name may contain full term. In that
	 * case $parameters may include some parameters replacing markers in term.
	 *
	 * The number of parameters must match number of markers in term.
	 *
	 * @param string $name property to fetch or term to evaluate on all matches
	 * @param string $alias alias to assign for addressing fetched value in filter
	 * @param mixed $parameters array of parameters or first of additional arguments providing one parameter each
	 * @return query current instance for chaining calls
	 */

	public function addProperty( $name, $alias = null, $parameters = null );

	/**
	 * Requests to fetch records matching provided conditional term, only.
	 *
	 * Conditions provided here are selecting records of dataset(s) included in
	 * query and thus apply prior to any optional grouping or late filtering.
	 *
	 * Parameter $union selects how to combine matches of currently added filter
	 * with matches of all previously added filters. If true, union of matches
	 * are returned. If false, merge of all matches is returned. First condition
	 * of query is ignoring this selector.
	 *
	 * If term requires some parameters they are given in additional arguments
	 * replacing existing parameter markers.
	 *
	 * The number of parameters must match number of markers in term.
	 *
	 * @param string $term term evaluating true on records/matches to keep
	 * @param boolean $union true to select union of matches, false for merge
	 * @param mixed $parameters array of parameters or first of additional arguments providing one parameter each
	 * @return query current instance for chaining calls
	 */

	public function addCondition( $term, $union = true, $parameters = null );

	/**
	 * Requests to remove matches failing on provided conditional term.
	 *
	 * Filters are applied after selecting matches depending on given conditions
	 * and their optional grouping.
	 *
	 * Parameter $union selects how to combine matches of currently added filter
	 * with matches of all previously added filters. If true, union of matches
	 * are returned. If false, merge of all matches is returned. First condition
	 * of query is ignoring this selector.
	 *
	 * If term requires some parameters they are given in additional arguments
	 * replacing existing parameter markers.
	 *
	 * The number of parameters must match number of markers in term.
	 *
	 * @param string $term term evaluating true on records/matches to keep
	 * @param boolean $union true to select union of matches, false for merge
	 * @param mixed $parameters array of parameters or first of additional arguments providing one parameter each
	 * @return query current instance for chaining calls
	 */

	public function addFilter( $term, $union = true, $parameters = null );

	/**
	 * Requests to group resulting records by a single property.
	 *
	 * @param string $name name of property in dataset of query
	 * @return query current instance for chaining calls
	 */

	public function addGroup( $name );

	/**
	 * Selects another rule on sorting result to be applied.
	 *
	 * Any succeeding rule on sorting is used if all previously added rules
	 * have equal result entries.
	 *
	 * @param string $name name of property in dataset of query
	 * @param boolean $ascending if true sorting rule requests ascending order
	 * @return query current instance for chaining calls
	 */

	public function addOrder( $name, $ascending = true );

	/**
	 * Requests to limit result set.
	 *
	 * @param integer $size maximum number of records to include in result set
	 * @param integer $offset number of matches to skip
	 * @return query current instance for chaining calls
	 */

	public function limit( $size = 20, $offset = 0 );

	/**
	 * Compiles previously described query.
	 *
	 * @param boolean $gettingMatchCount true on requesting count of matches
	 * @return string statement to query actually
	 */

	public function compile( $gettingMatchCount = false );

	/**
	 * Compiles set of parameters to pass for binding in proper order.
	 *
	 * @param boolean $gettingMatchCount true on requesting count of matches
	 * @return array set of parameters to bind
	 */

	public function compileParameters( $gettingMatchCount = false );

	/**
	 * Executes query providing access on result set.
	 *
	 * @param boolean $gettingMatchCount true to request query for count of matches
	 * @return statement executed statement providing result set
	 */

	public function execute( $gettingMatchCount = false );

	/**
	 * Switches datasource this query is operating on when executing.
	 *
	 * This method may require special implementation of connection due to
	 * semantical dependencies.
	 *
	 * Reconnecting datasource may be required after restoring a serialized
	 * query, e.g. on having stored in session.
	 *
	 * @param connection $connection datasource to use furtheron
	 */

	public function reconnectDatasource( connection $connection );

	/**
	 * Fetches datasource current query is operating on.
	 *
	 * @return \de\toxa\txf\datasource\connection
	 */

	public function datasource();
}


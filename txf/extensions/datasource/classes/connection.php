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


interface connection
{
	public function __construct( $dsn = null, $username = null, $password = null );

	/**
	 * Retrieves transaction manager working on current datasource.
	 *
	 * @return transaction
	 */

	public function transaction();

	/**
	 * Compiles query returning statement instance to be executed later
	 * (multiple times).
	 *
	 * @string $query query to be compiled
	 * @return statement compiled statement
	 */

	public function compile( $query );

	/**
	 * Query datasource, but don't return any resultset, but indicator on
	 * whether query succeeded or not.
	 *
	 * This is provided for querying a datasource e.g. for modification not
	 * returning any resultset.
	 *
	 * return @boolean
	 */

	public function test( $query );

	/**
	 * Retrieves first column of first row matching query.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
	 * @return mixed value of first cell in first matching row
	 */

	public function cell( $query );

	/**
	 * Retrieves first row matching query using names of columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
	 * @return array set of columns in first matching row
	 */

	public function row( $query );

	/**
	 * Retrieves first row matching query using numeric indices on columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
	 * @return array set of columns in first matching row
	 */

	public function rowNumeric( $query );

	/**
	 * Retrieves all rows matching query using names of columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
	 * @return array-of-arrays matching rows
	 */

	public function all( $query );

	/**
	 * Retrieves all rows matching query using numeric indices on columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
	 * @return array-of-arrays matching rows
	 */

	public function allNumeric( $query );

	/**
	 * Tests whether provided dataset exists in connected datasource or not.
	 *
	 * @param string $dataset name of dataset (table) to check
	 * @return boolean true on dataset (table) exists
	 */

	public function exists( $dataset );

	/**
	 * Creates named dataset (table) using provided definition of properties
	 * (columns).
	 *
	 * This method isn't doing anything if selected dataset exists already.
	 *
	 * @param string $name name of dataset (table) to create
	 * @param array[string->string] $definition property names mapping into property type definitions
	 * @return boolean true on success, false on failure
	 */

	public function createDataset( $name, $definition );

	/**
	 * Retrieves new instance for programmatically compiling query for fetching
	 * data from datasource.
	 *
	 * @param string $dataset name of main dataset (table) query is associated with
	 * @return query
	 */

	public function createQuery( $dataset );

	/**
	 * Retrieves next available primary key for selected dataset (table).
	 *
	 * This method is provided for cross-datasource provider support. Some
	 * datasources feature auto increment option on integer properties, but
	 * some others don't. To have code working on all datasources this method
	 * is provided for simulating auto-increment behaviour by managing separate
	 * dataset (table) "__keys" in that datasource.
	 *
	 * This method is requiring to be wrapped in a transaction. You may use
	 * datasource::transaction()->wrap() to achieve this in a convenient way.
	 *
	 * @param string $dataset name of dataset (table) ID is to be used in
	 * @return integer next available integer ID to use as primary key in selected dataset
	 */

	public function nextID( $dataset );

	/**
	 * Fetches human-readable error occurred previously in current connection.
	 *
	 * @return string
	 */

	public function errorText();

	/**
	 * Fetches datasource-specific code indicating previously occurred error in
	 * current connection.
	 *
	 * @return string
	 */

	public function errorCode();

	/**
	 * Wraps a provided property or dataset name to be used literally in a
	 * query command. Actual wrapping style depends on current datasource.
	 *
	 * @note Don't use this method for "neutralizing" values to be embedded in a
	 *       query while datasource is featuring parameter binding as this is
	 *       bad-practice and subject to frequent security vulnerabilities.
	 *       This whole API is designed to advise use of parameter binding.
	 *
	 * @return string
	 */

	public function quoteName( $name );

	/**
	 * Retrieves new exception instance linked with current connection,
	 * including optionally provided custom message.
	 *
	 * @param string $message custom message to include in exception to clarify
	 *                        context containing exception
	 * @return datasource_exception
	 */

	public function exception( $message = null );
}

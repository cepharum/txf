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
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return bool
	 */

	public function test( $query );

	/**
	 * Retrieves first column of first row matching query.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return mixed value of first cell in first matching row
	 */

	public function cell( $query );

	/**
	 * Retrieves first row matching query using names of columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array set of columns in first matching row
	 */

	public function row( $query );

	/**
	 * Retrieves first row matching query using numeric indices on columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array set of columns in first matching row
	 */

	public function rowNumeric( $query );

	/**
	 * Retrieves all rows matching query using names of columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array-of-arrays matching rows
	 */

	public function all( $query );

	/**
	 * Retrieves all rows matching query using numeric indices on columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array-of-arrays matching rows
	 */

	public function allNumeric( $query );

	/**
	 * Tests whether provided dataset exists in connected datasource or not.
	 *
	 * @param string $dataset name of dataset (table) to check
	 * @param boolean $noCache true to force actually checking datasource
	 * @return boolean true on dataset (table) exists
	 */

	public function exists( $dataset, $noCache = false );

	/**
	 * Creates named dataset (table) using provided definition of properties
	 * (columns).
	 *
	 * This method isn't doing anything if selected dataset exists already.
	 *
	 * @param string $name name of dataset (table) to create
	 * @param array[string->string] $definition property names mapping into property type definitions
	 * @param array $primaries names of properties used to build primary key, omit for "id"
	 * @return boolean true on success, false on failure
	 */

	public function createDataset( $name, $definition, $primaries = null );

	/**
	 * Retrieves new instance for programmatically compiling query for fetching
	 * data from datasource.
	 *
	 * @note Provided dataset name must not be qualified and/or quoted!
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
	 *       **This whole API is designed to advise use of parameter binding.**
	 *
	 * @param string $name name to quote for using it literally in queries on datasource
	 * @return string
	 */

	public function quoteName( $name );

	/**
	 * Qualifies provided name of a data set.
	 *
	 * @param string $name name of data set to be qualified
	 * @param bool $quoted true to additionally quote qualified data set name
	 * @param bool $splitWords if true name is split on space boundaries into
	 *        multiple words, each word is qualified and quoted separately then
	 * @return string
	 */

	public function qualifyDatasetName( $name, $quoted = true, $splitWords = true );

	/**
	 * Qualifies one or more property names of an additionally named data set
	 * for using it literally in queries on datasource.
	 *
	 * Qualification includes quoting provided name or alias of data set as well
	 * as any provided property's name before concatenating both.
	 *
	 * On returning array this list is mapping provided property names into
	 * their qualified counterparts.
	 *
	 * @note Don't use this method for "neutralizing" values to be embedded in a
	 *       query while datasource is featuring parameter binding as this is
	 *       bad-practice and subject to frequent security vulnerabilities.
	 *       **This whole API is designed to advise use of parameter binding.**
	 *
	 * @param string $qualifiedSetOrAlias qualified name of set or its alias
	 * @param string|array $property first out of several names of properties
	 *        contained in set or all names in single array
	 * @return string|array single qualified and quoted property name on
	 *         providing single string in $property, set of qualified and quoted
	 *         property names otherwise
	 */

	public function qualifyPropertyNames( $qualifiedSetOrAlias, $property, $quoted = true );

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

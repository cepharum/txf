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

use de\toxa\txf as txf;
use de\toxa\txf\config as config;
use de\toxa\txf\data as data;
use de\toxa\txf\singleton as singleton;


/**
 * PDO-driven datasource manager
 *
 * @property-read PDO $link
 * @property-read string $command
 */

class pdo extends singleton implements connection
{
	/**
	 * connection to datasource
	 *
	 * @var PDO
	 */

	protected $link;

	/**
	 * transaction controller for managing nested transactions
	 *
	 * @var transaction
	 */

	protected $transaction;

	/**
	 * name of driver used to operate current connection to datasource
	 *
	 * @var string
	 */

	protected $driver;

	/**
	 * recent-most command
	 *
	 * @var string
	 */

	protected $command;

	/**
	 * Caches text and code of most-recently queried statement's error.
	 *
	 * @var array|null
	 */

	protected $_recentStmtError;

	/**
	 * Stores prefix to use implicitly on every provided name of a dataset.
	 *
	 * @var string
	 */

	protected $prefix = '';



	/**
	 *
	 * @param string $dsn name of datasource to connect to
	 * @param string $username optional username for authenticating
	 * @param string $password optional password to use for authentication
	 */

	public function __construct( $dsn = null, $username = null, $password = null )
	{
		// got DSN in parameters?
		if ( $dsn === null )
		{
			// no -> use simple definition in runtime configuration

			/**
			 * @note This case isn't preferred one.
			 * @see datasource::selectConfigured()
			 */

			$setup = config::get( 'datasource' );

			$dsn = data::qualifyString( @$setup['dsn'] );
			$username = @$setup['user'];
			$password = @$setup['password'];
		}

		// store normalized name of driver used to connect with datasource
		$this->driver = strtolower( trim( strtok( $dsn, ':' ) ) );

		// establish connection to datasource
		$this->link = new \PDO( $dsn, $username, $password );

		/**
		 * ensure connection is using utf8 encoding unless disabled in configuration
		 */

		if ( config::get( 'datasource.set-utf8-encoding', true ) ) {
			switch ( $this->driver ) {
				case 'mysql' :
					$this->link->exec( 'SET NAMES utf8' );
					break;

				case 'mssql' :
					$this->link->setAttribute( PDO::SQLSRV_ENCODING_UTF8, 1 );
					break;
			}
		}

		$this->transaction = new transaction(
									$this,
									function( connection $c ) { txf\log::debug( "starting transaction" );  return $c->link->beginTransaction(); },
									function( connection $c ) { txf\log::debug( "committing transaction" ); return $c->link->commit(); },
									function( connection $c ) { txf\log::debug( "reverting transaction" ); return $c->link->rollBack(); }
								);
	}

	/**
	 * Adjusts prefix to use on currently connected datasource.
	 *
	 * This prefix is used implicitly on naming data sets in data source unless
	 * querying individual statements.
	 *
	 * @param string $prefix prefix to use, provide '' to disable prefixing
	 * @return $this
	 */

	public function setPrefix( $prefix )
	{
		if ( !is_string( $prefix ) ) {
			throw new \InvalidArgumentException( 'invalid type of prefix' );
		}

		if ( preg_match( '/\s/', trim( $prefix ) ) ) {
			throw new \InvalidArgumentException( 'invalid use of whitespace in prefix' );
		}

		$this->prefix = trim( $prefix );

		return $this;
	}

	/**
	 * Compiles provided query on datasource.
	 *
	 * @param string $query query on datasource
	 * @return pdo_statement compiled query wrapped in a statement
	 */

	public function compile( $query )
	{
		return new pdo_statement( $this, $this->command = $query );
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'link' :
				return $this->link;
			case 'command' :
				return trim( $this->command ) ? trim( $this->command ) : null;
		}
	}

	/**
	 * Retrieves transaction manager of connection.
	 *
	 * @return transaction
	 */

	public function transaction()
	{
		return $this->transaction;
	}

	protected $_existsCache = array();

	/**
	 * Detects if a selected dataset ("table") exists or not.
	 *
	 * The provided dataset name is qualified internally by prepending any
	 * configured prefix.
	 *
	 * @param string $dataset name of dataset to test
	 * @param boolean $noCache true to force actually checking datasource
	 * @return boolean|null true if dataset exists, false if not, null if unknown
	 */

	public function exists( $dataset, $noCache = false )
	{
		$this->command = null;

		$dataset = $this->qualifyDatasetName( $dataset, false );

		if ( !$noCache && $this->_existsCache[$dataset] )
			return true;

		$method = array( $this, 'exists_' . $this->driver );
		if ( is_callable( $method ) )
			$result = call_user_func( $method, $dataset );
		else
			$result = null;

		$this->_existsCache[$dataset] = $result;

		return $result;
	}
	protected function exists_sqlite( $dataset )
	{
		try
		{
			return !!$this->cell( 'SELECT name FROM sqlite_master WHERE type=? AND name=?', 'table', $dataset );
		}
		catch ( datasource_exception $e )
		{
			return null;
		}
	}
	protected function exists_mysql( $dataset )
	{
		try
		{
			return !!$this->cell( 'SHOW TABLES LIKE ?', $dataset );
		}
		catch ( datasource_exception $e )
		{
			return null;
		}
	}

	public function errorText()
	{
		$info = $this->link->errorInfo();

		return $info[2] ? $info[2] : $this->_recentStmtError ? $this->_recentStmtError['text'] : '';
	}

	public function errorCode()
	{
		$code = $this->link->errorCode();

		return $code ? $code : $this->_recentStmtError ? $this->_recentStmtError['code'] : 0;
	}

	protected function __fastQuery( $retrievor, $arguments )
	{
		$this->_recentStmtError = null;

		$query = array_shift( $arguments );

		// qualify special markers in query
		$query = $this->qualifyBindingsInQuery( $query, $arguments );

		// compile statement
		$stmt = $this->compile( $query );

		// execute compiled statement
		if ( !call_user_func_array( array( $stmt, 'execute' ), $arguments ) || $stmt->failed ) {
			$this->_recentStmtError = array( 'text' => $stmt->errorText(), 'code' => $stmt->errorCode() );
			throw new datasource_exception( $stmt );
		}

		// read result using optionally named method of compiled statement
		$match = $retrievor ? call_user_func( array( $stmt, $retrievor ) ) : true;

		// close statement
		$stmt->close();

		return $match;
	}

	protected function qualifyBindingsInQuery( $query, &$values ) {
		if ( count( $values ) === 1 && is_array( $values[0] ) ) {
			$values = $values[0];
		}

		$pos = 0;
		$valueIndex = 0;
		$out = '';
		$outValues = array();
		$changed = false;

		while ( preg_match( '/(\?|%array%)/', $query, $matches, PREG_OFFSET_CAPTURE, $pos ) ) {
			$match = $matches[1];

			$out .= substr( $query, $pos, $match[1] - $pos );

			switch ( $match[0] ) {
				case '%array%' :
					if ( is_array( $values[$valueIndex] ) ) {
						$out .= implode( ',', array_pad( array(), count( $values[$valueIndex] ), '?' ) );
						$outValues = array_merge( $outValues, $values[$valueIndex] );
						$changed = true;
						break;
					}

				case '?' :
				default :
					$out .= '?';
					$outValues[] = $values[$valueIndex];
					break;
			}

			$valueIndex++;
			$pos = $match[1] + strlen( $match[0] );
		}

		if ( $changed ) {
			$values = $outValues;
		}

		return $out . substr( $query, $pos );
	}

	/**
	 * Creates new dataset unless dataset exists already.
	 *
	 * @param string $name name of dataset to create
	 * @param array $definition hash of element names into element types
	 * @param array $primaries names of columns in $definition to be part of primary key
	 * @return boolean true on success, false on failure
	 */

	public function createDataset( $name, $definition, $primaries = null )
	{
		$this->command = null;

		if ( !is_array( $definition ) || empty( $definition ) )
			throw new \InvalidArgumentException( 'missing definition of elements in data set' );

		if ( $this->exists( $name ) )
			return true;


		$rows = array();

		if ( !is_array( $primaries ) || !count( $primaries ) ) {
			// implicitly add column "id" serving as primary key unless caller
			// has named columns of primary key
			$rows['id'] = $this->quoteName( 'id' ) . ' INTEGER NOT NULL';
			$primaries  = array( 'id' );
		}

		foreach ( $definition as $key => $type )
			$rows[$key] = $type ? $this->quoteName( $key ) . ' ' . $type : null;


		foreach ( $primaries as $key => $columnName )
			if ( !array_key_exists( $columnName, $rows ) )
				throw new \InvalidArgumentException( 'missing definition of primary key column ' . $columnName );
			else
				$primaries[$key] = $this->quoteName( $columnName );


		$rows[] = 'PRIMARY KEY (' . implode( ',', $primaries ) . ')';


		$query = 'CREATE TABLE ' . $this->qualifyDatasetName( $name ) .
		         "\n(\n\t" . implode( ",\n\t", array_filter( $rows ) ) . "\n)";

		return $this->test( $query );
	}

	/**
	 * Retrieves new query instance on selected dataset.
	 *
	 * @note Provided dataset name must not be qualified and/or quoted!
	 *
	 * @param string $onDataset name of a dataset query is operating on
	 * @return sql_query instance providing complex query description
	 */

	public function createQuery( $onDataset )
	{
		$this->command = null;

		return new sql_query( $this, $onDataset );
	}

	/**
	 * Detects if provided string is quoted for literal use in an SQL statement.
	 *
	 * @param string $string
	 * @return bool true if string is quoted, false otherwise
	 */

	protected function isQuoted( $string ) {
		switch ( $this->driver ) {
			case 'mysql' :
			case 'sqlite' :
				return !!preg_match( '/^`.*`$/', trim( $string ) );

			case 'sqlsrv' :
				return !!preg_match( '/^\[.*\]$/', trim( $string ) );

			default :
				return false;
		}
	}

	/**
	 * Quotes provided name of a data set or one of its properties for literal
	 * use in a statement to be queried.
	 *
	 * This method isn't qualifying any provided name.
	 *
	 * @param string $name name of data set or property
	 * @return string quoted name
	 */

	public function quoteName( $name )
	{
		if ( strpos( $name, '(' ) !== false ) {
			// name looks like a term -> don't quote
			return $name;
		}

		if ( $this->isQuoted( $name ) ) {
			return $name;
		}

		switch ( $this->driver )
		{
			case 'mysql' :
			case 'sqlite' :
				if ( strpos( $name, '`' ) !== false )
					throw new \InvalidArgumentException( 'unquotable name' );

				return '`' . $name . '`';

			case 'sqlsrv' :
				if ( strpos( $name, ']' ) !== false )
					throw new \InvalidArgumentException( 'unquotable name' );

				return '[' . $name . ']';

			default :
				if ( preg_match( '/\w/', $name ) )
					throw new \InvalidArgumentException( 'unquotable name' );

				return $name;
		}
	}

	/**
	 * Qualifies provided name of data set by prepending internally set prefix.
	 *
	 * In addition, dataset name might be quoted to be embeddable in an SQL
	 * statement as a literal name.
	 *
	 * @param string $datasetName name of data set to qualify
	 * @param bool $quoted true for quoting qualified name in addition
	 * @param bool $splitWords if true name is split on space boundaries into
	 *        multiple words, each word is qualified and quoted separately then
	 * @return string
	 */

	public function qualifyDatasetName( $datasetName, $quoted = true, $splitWords = false )
	{
		$qualified = $this->prefix . $datasetName;

		if ( $splitWords ) {
			$qualified = preg_split( '/\s+/', trim( $qualified ) );

			if ( $quoted ) {
				foreach ( $qualified as &$part ) {
					$part = $this->quoteName( $part );
				}
			}

			return implode( ' ', $qualified );
		}

		return $quoted ? $this->quoteName( $qualified ) : $qualified;
	}

	/**
	 * Qualifies one or more property names of an additionally named data set
	 * for using it literally in queries on datasource.
	 *
	 * Qualification includes quoting provided name or alias of data set as well
	 * as any provided property's name before concatenating both. Concatenation
	 * is omitted by providing null in $setOrAlias.
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
	 * @param string|array $property first name of several property names to
	 *        wrap, or all names in single array
	 * @return string|array single quoted property name on providing single string
	 *         in $property, set of quoted property names otherwise
	 */

	public function qualifyPropertyNames( $qualifiedSetOrAlias = null, $property )
	{
		$qualify = !is_null( $qualifiedSetOrAlias );

		// validate optionally provided name or alias of data set
		if ( $qualify ) {
			if ( !is_string( $qualifiedSetOrAlias ) )
				throw new \InvalidArgumentException( 'invalid name or alias of data set for property name qualification' );

			$qualifiedSetOrAlias = trim( $qualifiedSetOrAlias );
			if ( $qualifiedSetOrAlias === '' )
				throw new \InvalidArgumentException( 'empty name or alias of data set for property name qualification' );

			$qualifiedSetOrAlias = $this->quoteName( $qualifiedSetOrAlias );
		}

		// normalize properties to qualify
		$properties = func_get_args();
		array_shift( $properties );

		$haveSingle = count( $properties ) == 1 && is_string( $properties[0] );

		if ( !$haveSingle )
			if ( count( $properties ) == 1 && is_array( $properties[0] ) )
				$properties = array_shift( $properties );

		if ( !is_array( $properties ) )
			throw new \InvalidArgumentException( 'invalid set of property names' );


		// qualify all given names of properties
		$qualified = array();

		foreach ( $properties as $name )
		{
			$quotedName       = $name === '*' ? $name : $this->quoteName( $name );
			$qualified[$name] = $qualify ? $qualifiedSetOrAlias . '.' . $quotedName : $quotedName;
		}


		return $haveSingle ? array_shift( $qualified ) : $qualified;
	}


	/**
	 * Tries to query database.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * "Trying" and "test" don't indicate that datasource isn't actually queried
	 * or modified by some proper query. This method is available in addition to
	 * cell() etc. to return true or false instead of a resultset. It's also
	 * different from other methods in that's capturing any datasource-related
	 * exceptions returning actually false on a failed query.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return boolean true on successfully querying, false on failure
	 */

	public function test( $query )
	{
		$arguments = func_get_args();

		try
		{
			return $this->__fastQuery( null, $arguments );
		}
		catch ( datasource_exception $e )
		{
			txf\log::debug( sprintf( 'PDO exception on testing: %s', $e->getMessage() ) );

			return false;
		}
	}

	/**
	 * Retrieves first column of first row matching query.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return mixed value of first cell in first matching row
	 */

	public function cell( $query )
	{
		$arguments = func_get_args();
		return $this->__fastQuery( 'cell', $arguments );
	}

	/**
	 * Retrieves first row matching query using names of columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array set of columns in first matching row
	 */

	public function row( $query )
	{
		$arguments = func_get_args();
		return $this->__fastQuery( 'row', $arguments );
	}

	/**
	 * Retrieves first row matching query using numeric indices on columns.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array set of columns in first matching row
	 */

	public function rowNumeric( $query )
	{
		$arguments = func_get_args();
		return $this->__fastQuery( 'rowNumeric', $arguments );
	}

	/**
	 * Retrieves all rows matching query using names of columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array-of-arrays matching rows
	 */

	public function all( $query )
	{
		$arguments = func_get_args();
		return $this->__fastQuery( 'all', $arguments );
	}

	/**
	 * Retrieves all rows matching query using numeric indices on columns per row.
	 *
	 * Provide bound parameters in additional arguments or as single array of
	 * parameters.
	 *
	 * @throws \BadMethodCallException
	 * @param string,... $query query on datasource, followed by one or more parameters to bind on query
	 * @return array-of-arrays matching rows
	 */

	public function allNumeric( $query )
	{
		$arguments = func_get_args();
		return $this->__fastQuery( 'allNumeric', $arguments );
	}

	/**
	 * Fetches next unique ID to use on adding record to selected dataset.
	 *
	 * This method is provided to establish common pattern for adding records to
	 * a dataset (table). While some datasources support auto-incrementing values
	 * others don't support such a feature. This method is designed to be simple
	 * in use and provide a convenient way of managing record IDs.
	 *
	 * The method must be called while transaction is in progress.
	 *
	 * @throws \LogicException on invoking without transaction in progress
	 * @throws datasource_exception if managing keys failed for some reason
	 * @param string $dataset name of dataset a record is going to be added to
	 * @return integer ID to use on adding a nother record to selected dataset
	 */

	public function nextID( $dataset )
	{
		$this->command = null;

		if ( !$this->transaction->inProgress() )
			throw new \LogicException( 'fetching next ID must be wrapped in transaction' );

		$qName = $this->qualifyDatasetName( '__keys', true );

		if ( $this->exists( '__keys' ) === false )
			$this->cell( 'CREATE TABLE ' . $qName . ' ( dataset CHAR(128) PRIMARY KEY, previousId INT UNSIGNED NOT NULL )' );

		$previousId = $this->cell( 'SELECT previousId FROM ' . $qName . ' WHERE dataset=?', $dataset );
		if ( $previousId === false )
			// there is no track of previously fetching ID for this dataset -> add now
			$this->cell( 'INSERT INTO ' . $qName . ' (dataset,previousId) VALUES (?,?)', $dataset, $previousId = 1 );
		else
			$this->cell( 'UPDATE ' . $qName . ' SET previousId=? WHERE dataset=?', ( $previousId += 1 ), $dataset );


		return $previousId;
	}

	/**
	 * Retrieves exception instance bound to current datasource connection for
	 * describing an occurred error.
	 *
	 * @param string $message optional custom message describing context of error
	 * @return datasource_exception
	 */

	public function exception( $message = null )
	{
		return new datasource_exception( $this, $message );
	}
}

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

use de\toxa\txf\config as config;
use de\toxa\txf\data as data;
use de\toxa\txf\singleton as singleton;


/**
 * PDO-driven datasource manager
 *
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
	 *
	 * @param string $dsn name of datasource to connect to
	 * @param string $username optional username for authenticating
	 * @param string $password optional password to use for authentication
	 */

	public function __construct( $dsn = null, $username = null, $password = null )
	{
		if ( $dsn === null )
		{
			$setup = config::get( 'datasource' );

			$dsn = data::qualifyString( @$setup['dsn'] );
			$username = @$setup['username'];
			$password = @$setup['password'];
		}

		$this->driver = strtolower( trim( strtok( $dsn, ':' ) ) );

		$this->link = new \PDO( $dsn, $username, $password );

		$this->transaction = new transaction(
									$this,
									function( connection $c ) { return $c->link->beginTransaction(); },
									function( connection $c ) { return $c->link->commit(); },
									function( connection $c ) { return $c->link->rollback(); }
								);
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

	/**
	 * Detects if a selected dataset ("table") exists or not.
	 *
	 * @param string $dataset name of dataset to test
	 * @return boolean|null true if dataset exists, false if not, null if unknown
	 */

	public function exists( $dataset )
	{
		$this->command = null;

		$method = array( $this, 'exists_' . $this->driver );
		if ( is_callable( $method ) )
			return call_user_func( $method, $dataset );

		return null;
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
		return $info[2];
	}

	public function errorCode()
	{
		return $this->link->errorCode();
	}

	protected function __fastQuery( $retrievor, $arguments )
	{
		$stmt = $this->compile( array_shift( $arguments ) );

		if ( !call_user_func_array( array( $stmt, 'execute' ), $arguments ) )
			throw new datasource_exception( $stmt );

		$match = $retrievor ? call_user_func( array( $stmt, $retrievor ) ) : true;

		$stmt->close();

		return $match;
	}

	/**
	 * Creates new dataset unless dataset exists already.
	 *
	 * @param string $name name of dataset to create
	 * @param array $definition hash of element names into element types
	 * @return boolean true on success, false on failure
	 */

	public function createDataset( $name, $definition )
	{
		$this->command = null;

		if ( !is_array( $definition ) || empty( $definition ) )
			throw new \InvalidArgumentException( 'missing dataset element definition' );

		if ( $this->exists( $name ) )
			return true;

		$query = $this->quoteName( 'id' ) . ' INTEGER PRIMARY KEY';
		foreach ( $definition as $key => $type )
			$query .= ",\n\t" . $this->quoteName( $key ) . ' ' . $type;

		$query = 'CREATE TABLE ' . $this->quoteName( $name ) . "\n(\n\t$query\n)";

		return $this->test( $query );
	}

	/**
	 * Retrieves new query instance on selected dataset.
	 *
	 * @param string $onDataset name of a dataset query is operating on
	 * @return sql_query instance providing complex query description
	 */

	public function createQuery( $onDataset )
	{
		$this->command = null;

		return new sql_query( $this, $onDataset );
	}

	public function quoteName( $name )
	{
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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
	 * @param string $query query on datasource
	 * @param array $parameters single array of parameters to bind
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

		if ( $this->exists( '__keys' ) === false )
			$this->cell( 'CREATE TABLE __keys ( dataset CHAR(128) PRIMARY KEY, previousId INT UNSIGNED NOT NULL )' );

		$previousId = $this->cell( 'SELECT previousId FROM __keys WHERE dataset=?', $dataset );
		if ( $previousId === false )
			// there is no track of previously fetching ID for this dataset -> add now
			$this->cell( 'INSERT INTO __keys (dataset,previousId) VALUES (?,?)', $dataset, $previousId = 1 );
		else
			$this->cell( 'UPDATE __keys SET previousId=? WHERE dataset=?', ++$previousId, $dataset );


		return $previousId;
	}
}

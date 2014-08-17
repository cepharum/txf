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


class pdo_statement implements statement
{
	/**
	 * mark on whether statement has been executed successfully before or not
	 *
	 * @var boolean
	 */

	protected $executed = null;

	/**
	 * wrapped statement resource
	 *
	 * @var \PDOStatement
	 */

	protected $statement;

	/**
	 * command wrapped in current instance
	 *
	 * @var string
	 */

	protected $command;



	public function __construct( pdo $connection, $query )
	{
		$this->command = $query;
		$this->statement = $connection->link->prepare( $query );
		if ( !$this->statement )
			throw new datasource_exception( $connection );
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'command' :
				return trim( $this->command ) ? trim( $this->command ) : null;
			case 'failed' :
				$this->hasExecuted( true );
				return ( $this->executed === false );
		}
	}

	public function execute()
	{
		if ( $this->executed === null )
		{
			$arguments = func_get_args();

			if ( count( $arguments ) === 1 )
			{
				if ( is_array( $arguments[0] ) )
					$arguments = array_shift( $arguments );
				else if ( is_object( $arguments ) )
					$arguments = get_object_vars( $arguments );
			}

			$this->executed = $this->statement->execute( $arguments );
		}

		return $this;
	}

	/**
	 * Closes current result set.
	 *
	 * Closing a result set might be required prior to querying datasource for
	 * another set. Fetching last row of a set used to close it implicitly.
	 */

	public function close()
	{
		if ( $this->executed )
		{
			$this->statement->closeCursor();

			$this->executed = false;
		}
	}

	/**
	 * Retrieves recent-most error message.
	 *
	 * @return string message describing recent-most error on statement
	 */

	public function errorText()
	{
		$this->hasExecuted( true );

		$info = $this->statement->errorInfo();
		return $info[2];
	}

	/**
	 * Retrieves recent-most error code.
	 *
	 * @return string code describing recent-most error on statement
	 */

	public function errorCode()
	{
		$this->hasExecuted( true );

		return $this->statement->errorCode();
	}

	protected function hasExecuted( $mayHaveFailed = false )
	{
		if ( $this->executed === null || ( !$mayHaveFailed && !$this->executed ) )
			throw new \BadMethodCallException( _Ltxl('statement execution missing or failed before') );

		return $this->executed;
	}

	public function cell()
	{
		$this->hasExecuted();

		return $this->statement->fetchColumn();
	}

	public function row()
	{
		$this->hasExecuted();

		return $this->statement->fetch( \PDO::FETCH_ASSOC );
	}

	public function rowNumeric()
	{
		$this->hasExecuted();

		return $this->statement->fetch( \PDO::FETCH_NUM );
	}

	public function all()
	{
		$this->hasExecuted();

		return $this->statement->fetchAll( \PDO::FETCH_ASSOC );
	}

	public function allNumeric()
	{
		$this->hasExecuted();

		return $this->statement->fetchAll( \PDO::FETCH_NUM );
	}

	public function count()
	{
		$this->hasExecuted();

		return $this->statement->rowCount();
	}
}

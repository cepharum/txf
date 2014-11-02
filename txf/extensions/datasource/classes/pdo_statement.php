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


/**
 * Implements statement interface in context of PDO-connected data sources for
 * working with a single statement.
 *
 * @property-read string $command provided text of original command
 * @property-read bool $failed true if executing command has failed, false otherwise
 *
 * @package de\toxa\txf\datasource
 */

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
			default :
				return null;
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
			throw new \BadMethodCallException( \de\toxa\txf\_L('statement execution missing or failed before') );

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

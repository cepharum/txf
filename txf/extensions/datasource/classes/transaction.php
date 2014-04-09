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
 * transaction manager supporting nested transactions
 *
 * This manager is bound to some actual transaction system (e.g. as provided by
 * a datasource) using callback methods. On nesting transactions all but
 * outer-most one are simulated internally.
 *
 */

class transaction
{
	/**
	 * datasource connection manager this transaction manager is operating on
	 *
	 * @var connection
	 */

	protected $connection;

	/**
	 * method to call for actually starting a transaction
	 *
	 * @var function
	 */

	protected $starter;

	/**
	 * method to call for actually committing a transaction
	 *
	 * @var function
	 */

	protected $committer;

	/**
	 * method to call for actually rolling back a transaction
	 *
	 * @var function
	 */

	protected $reverter;

	/**
	 * mark on whether current transaction is succeeding or not
	 *
	 * @var boolean
	 */

	protected $isSucceeding = true;

	/**
	 * LIFO queue of transactions called nestedly
	 *
	 * @var array
	 */

	protected $stack = array();



	/**
	 * @throws \InvalidArgumentException on missing proper set of callbacks
	 * @param function $starter callback to invoke on actually starting transaction
	 * @param function $committer callback to invoke on actually committing transaction
	 * @param function $reverter callback to invoke on actually rolling back transaction
	 */

	public function __construct( connection $connection, $starter, $committer, $reverter )
	{
		if ( !is_callable( $starter ) || !is_callable( $committer ) || !is_callable( $reverter ) )
			throw new \InvalidArgumentException( 'missing callback for performing transaction-related actions' );

		$this->connection = $connection;

		$this->starter = $starter;
		$this->committer = $committer;
		$this->reverter = $reverter;
	}

	/**
	 * Detects if there is a running transaction on datasource.
	 *
	 * This is covering transactions started by current connection to datasource
	 * only. You can't check for transactions in progress by other users of
	 * datasource.
	 *
	 * @return boolean true on active transaction, false otherwise
	 */

	public function inProgress()
	{
		return !empty( $this->stack );
	}

	/**
	 * Starts a (nested) transaction.
	 *
	 * Nested transactions are simulated here by managing local stack of requested
	 * transactions. The name is used to detect regressions: starting transaction
	 * with same name as a running transaction is rejected.
	 *
	 * @throws \InvalidArgumentException on invalid transaction name
	 * @throws \RuntimeException on improper nesting and regression
	 * @param string $name name of transaction to start
	 * @return boolean true on success, false on failure
	 */

	public function start( $name )
	{
		if ( !is_string( $name ) || !$name )
			throw new \InvalidArgumentException( 'missing or invalid transaction name' );

		if ( empty( $this->stack ) )
			call_user_func( $this->starter, $this->connection );
		else if ( in_array( $name, $this->stack ) )
			throw new \RuntimeException( 'nesting same transaction' );

		array_push( $this->stack, $name );

		return true;
	}

	/**
	 * Ends a running transaction either by committing or by rolling back all
	 * modifications on datasource in transaction.
	 *
	 * @throws \InvalidArgumentException on providing invalid name
	 * @throws \UnderflowException when no transaction is running
	 * @throws \LogicException improperly nesting or error handling
	 * @param string $name name of transaction to end
	 * @param boolean $committing true to commit, false to roll back
	 * @return boolean true on processing request succeeded, false on failure
	 */

	public function end( $name, $committing = true )
	{
		if ( !is_string( $name ) || !$name )
			throw new \InvalidArgumentException( 'missing or invalid transaction name' );

		if ( empty( $this->stack ) )
			throw new \UnderflowException( 'transaction stack underrun' );

		if ( $committing && !$this->isSucceeding )
			throw new \LogicException( 'won\'t commit outer transaction while rolling back inner one' );

		if ( array_pop( $this->stack ) !== $name )
			throw new \LogicException( 'improper nesting of transactions' );


		if ( !count( $this->stack ) )
		{
			// ending outermost transaction requires some actual action

			// on ending outermost transaction reset track of failed inner transactions
			$this->isSucceeding = true;

			if ( !$committing )
				return !!call_user_func( $this->reverter, $this->connection );

			if ( call_user_func( $this->committer, $this->connection ) )
				return true;

			// failed to commit transaction as requested
			// --> try to roll back instead
			call_user_func( $this->reverter, $this->connection );

			// request for commit failed nevertheless
			return false;
		}

		// keep track of any failed transaction in sequence of nesting transactions
		$this->isSucceeding &= !!$committing;


		return true;
	}

	/**
	 * Processes some code in scope of a transaction with given or random name.
	 *
	 * Transaction's connection is provided as first argument in call to given
	 * processor function.
	 *
	 * @throws \InvalidArgumentException on invalid processor
	 * @param callable $processor code to process in transaction
	 * @param string $name explicit name of transaction
	 * @return boolean true on success, false on error
	 */

	public function wrap( $processor, $name = null )
	{
		if ( !is_callable( $processor ) )
			throw new \InvalidArgumentException( 'missing processor to monitor' );

		if ( $name === null )
			$name = uniqid();


		if ( !$this->start( $name ) )
			return false;

		try
		{
			$result  = !!call_user_func( $processor, $this->connection );
			$result &= $this->end( $name, $result );

			return $result;
		}
		catch ( \Exception $e )
		{
			$this->end( $name, false );

			throw $e;
		}
	}
}

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


namespace de\toxa\txf;


/**
 * Implements session manager supporting delayed load of session data.
 *
 * Delayed load is required to support embedding TXF-based applications in
 * a third-party software.
 *
 * Instead of accessing $_SESSION this manager is available by accessing
 * array returned from call to session::current()->access().
 *
 * The class is designed to work on demand, thus won't require any extra
 * initialization.
 *
 * IMPORTANT! Do not use any TXF code inside this class unless you're absolutely
 *            sure about what you do!
 */


class session	// don't derive from anything external here!!! That's breaking major intention of this class!
{

	/**
	 * element in $_SESSION containing all TXF-related session data
	 */

	const stubName = 'TXF_DATA';

	/**
	 * Addresses session space related to current script.
	 */

	const SCOPE_SCRIPT = 0;

	/**
	 * Addresses separate space for classes.
	 *
	 * Can be combined with SCOPE_APPLICATION or SCOPE_GLOBAL. Addressing this
	 * way requires a class name as parameter.
	 */

	const SCOPE_CLASS = 1;

	/**
	 * Addresses session space related to current application.
	 */

	const SCOPE_APPLICATION = 2;

	/**
	 * Addresses session space shared by all txf-based applications accessing same PHP session.
	 */

	const SCOPE_GLOBAL = 4;



	/**
	 * reference to current session manager
	 *
	 * @see session::get()
	 * @var session
	 */

	protected static $current;



	/**
	 * locally managed variable space
	 *
	 * @see session::access()
	 * @var array
	 */

	protected $usable = array();

	/**
	 * serialized copy of $usable
	 *
	 * @var string
	 */

	private $storable;



	/**
	 * Instantiates new session manager and links it into current session space.
	 *
	 * Never create a manager yourself but call session::get() instead.
	 */

	protected function __construct()
	{
		assert( '!( self::$current instanceof self )' );

		// register manager in session
		$_SESSION[self::stubName] = $this;
	}

	/**
	 * Retrieves current singleton session manager.
	 *
	 * This method creates new session manager or restores one from available
	 * record in session space on demand.
	 *
	 * @return session
	 */

	final public static function current()
	{
		if ( !( self::$current instanceof self ) )
		{
			// process focus selection
			$focus = config::get( 'session.focus', 'application' );

			switch ( $focus )
			{
				case 'domain' :
					// valid for whole current domain
					$domain = $_SERVER['HTTP_HOST'];
					$path   = '/';
					break;

				case 'txf' :
					// valid for all applications in current installation, only
					$domain = $_SERVER['HTTP_HOST'];
					$path   = '/' . txf::getContext()->prefixPathname;
					break;

				case 'application' :
					// valid for current application, only
					$domain = $_SERVER['HTTP_HOST'];
					$path   = '/' . path::glue( txf::getContext()->prefixPathname, txf::getContext()->application->name );
					break;

				default :
					// option is explicitly providing domain and path to focus
					if ( strpos( $focus, '%' ) !== false )
						$focus  = strtr( $focus, array(
											'%H' => $_SERVER['HTTP_HOST'],
											'%T' => '/' . txf::getContext()->prefixPathname,
											'%A' => '/' . path::glue( txf::getContext()->prefixPathname, txf::getContext()->application->name ),
											) );

					$temp   = explode( '/', $focus );
					$domain = array_shift( $temp );
					$path   = '/' . implode( '/', $temp );
			}

			\session_set_cookie_params( 0, path::addTrailingSlash( $path ), $domain );


			// without existing link in current runtime check for snapshot
			// stored in session
			@session_start();


			if ( $_SESSION[self::stubName] instanceof self )
				// restore found snapshot
				self::$current = $_SESSION[self::stubName];
			else
				// not in session -> start new session manager
				self::$current = new static();
		}

		// (re-)retrieve current session manager instance
		return self::$current;
	}

	/**
	 * Takes a snapshot of current variable space to be storable in session.
	 */

	final protected function makeStorable()
	{
		assert( 'is_array( $this->usable )' );

		$this->storable = crypt::encrypt( serialize( $this->usable ) );
	}

	/**
	 * Restores variable space from current snapshot made to be storable in
	 * session.
	 */

	final protected function makeUsable()
	{
		if ( trim( $this->storable ) )
		{
			$space = unserialize( crypt::decrypt( $this->storable ) );
			if ( is_array( $space ) )
				$this->usable = $space;

			$this->storable = null;
		}

		if ( !is_array( $this->usable ) )
			$this->usable = array();
	}

	/**
	 * Processes
	 */

	final public function __sleep()
	{
		$this->makeStorable();

		// request to have $this->storable serialized into session, only
		return array( 'storable' );
	}

	private static function makeArray( &$ref )
	{
		if ( !is_array( $ref ) ) $ref = array();
	}

	/**
	 * Retrieves reference on usable variable space.
	 *
	 * @note Using global session space shared by several applications requires
	 *       external setup to provide access on same PHP session.
	 *
	 * @param enum $scope one of the SCOPE_* constants
	 * @param string|bool $parameter additional selector used according to $scope
	 * @return array-ref
	 */

	final public function &access( $scope, $parameter = null )
	{
		if ( !class_exists( '\de\toxa\txf\txf', false ) )
			throw new \RuntimeException( 'missing TXF context for accessing managed data' );

		// ensure session has been restored from serialization
		$this->makeUsable();

		/*
		 * process selected scope
		 */

		// validate provided parameter
		if ( $scope & SCOPE_CLASS )
			if ( !( $parameter = data::isNonEmptyString( $parameter ) ) )
				throw new \InvalidArgumentException( 'invalid/missing class selector' );

		// prepare subset of session data according to scope and parameter and
		// return reference on it for read/write access
		switch ( $scope )
		{
			case self::SCOPE_SCRIPT :
				self::makeArray( $this->usable['applications'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH] );
				return $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH];

			case self::SCOPE_CLASS + self::SCOPE_SCRIPT :
				self::makeArray( $this->usable['applications'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH]['classes'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH]['classes'][$parameter] );
				return $this->usable['applications'][TXF_APPLICATION]['scripts'][TXF_SCRIPT_PATH]['classes'][$parameter];

			case self::SCOPE_CLASS + self::SCOPE_APPLICATION :
				self::makeArray( $this->usable['applications'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['classes'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['classes'][$parameter] );
				return $this->usable['applications'][TXF_APPLICATION]['classes'][$parameter];

			case self::SCOPE_CLASS + self::SCOPE_GLOBAL :
				self::makeArray( $this->usable['classes'] );
				self::makeArray( $this->usable['classes'][$parameter] );
				return $this->usable['classes'][$parameter];

			case self::SCOPE_APPLICATION :
				self::makeArray( $this->usable['applications'] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION] );
				self::makeArray( $this->usable['applications'][TXF_APPLICATION]['shared'] );
				return $this->usable['applications'][TXF_APPLICATION]['shared'];

			case self::SCOPE_GLOBAL :
				self::makeArray( $this->usable['shared'] );
				return $this->usable['shared'];

			default :
				throw new \InvalidArgumentException( 'invalid session scope' );
		}
	}
}
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

	final public static function getScopeParameter( &$domain, &$path ) {
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
			self::getScopeParameter( $domain, $path );

			\session_set_cookie_params( 0, path::addTrailingSlash( $path ), $domain );


			// trigger import of class crypt so it may set required cookies
			crypt::init();


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

		if ( data::autoType( config::get( 'session.encrypt', false ), 'boolean' ) )
			$this->storable = crypt::create()->encrypt( serialize( $this->usable ) );
		else
			$this->storable = serialize( $this->usable );
	}

	/**
	 * Restores variable space from current snapshot made to be storable in
	 * session.
	 */

	final protected function makeUsable()
	{
		if ( trim( $this->storable ) )
		{
			if ( data::autoType( config::get( 'session.encrypt', false ), 'boolean' ) )
			{
				try
				{
					$space = unserialize( crypt::create()->decrypt( $this->storable ) );
				}
				catch ( \InvalidArgumentException $e )
				{
					log::warning( 'session lost due to failed decryption, might be okay if browser lost cookie in between' );
					$space = array();
				}
			}
			else
				$space = unserialize( $this->storable );

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
	 * @param int $scope one of the SCOPE_* constants
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
		if ( $scope & self::SCOPE_CLASS )
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

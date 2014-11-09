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
 * Manages application.
 *
 * @package de\toxa\txf
 *
 * @property-read string $name
 * @property-read string $pathname
 * @property-read string $script
 * @property-read array $selectors
 * @property-read string $url
 * @property-read string|boolean $usedProxy
 * @property-read context $context
 */

class application
{
	/**
	 * Provides name of application.
	 *
	 * @var string
	 */

	protected $name;

	/**
	 * Provides pathname of application's folder.
	 *
	 * @var string
	 */

	protected $pathname;

	/**
	 * Provides pathname of current script, set on managing current application,
	 * only.
	 *
	 * @var string
	 */

	protected $script;

	/**
	 * Provides additional resource selectors provided in request.
	 *
	 * @var array
	 */

	protected $selectors;

	/**
	 * Provides public URL of application, e.g. for addressing related images
	 * and files.
	 *
	 * @var string
	 */

	protected $url;

	/**
	 * Provides used proxy script or true on detecting use of rewrite engine.
	 *
	 * @var string|boolean
	 */

	protected $usedProxy;

	/**
	 * Provides context of current application.
	 *
	 * @var context
	 */

	protected $context;



	protected function __construct() {}

	/**
	 * Detects if current application instance is valid.
	 */

	public function isValid()
	{
		return ( ( $this->name != false ) && is_dir( $this->pathname ) );
	}

	/**
	 * Retrieves current application detected by utilizing provided context.
	 *
	 * If context is omitted one is created internally.
	 *
	 * @param context $context context to utilize while detecting application
	 * @return application current application instance
	 * @throws http_exception
	 */

	public static function current( context $context = null )
	{
		if ( is_null( $context ) )
			$context = new context();


		$application = new static;

		$application->context = $context;


		/*
		 * extract selected application and source
		 */

		// choose source of application/script selector
		$frames = $context->getRequestedScriptUri( $application->usedProxy );
		if ( empty( $frames ) )
			throw new http_exception( 400, 'Request missing application. Check your setup!' );

		// extract information on application folder and name
		$application->name = array_shift( $frames );
		if ( $application->name == 'txf' )
			throw new http_exception( 404, 'Requested application doesn\'t exist.' );

		// add some derived properties for conveniently addressing application
		$application->pathname = path::glue(
										$context->installationPathname,
										$application->name
									);

		// find selected script's pathname and name
		if ( empty( $frames ) )
			$application->script = 'index.php';
		else
		{
			$script = array();

			while ( count( $frames ) )
			{
				$script[] = array_shift( $frames );
				$pathname = path::glue( $application->pathname, implode( '/', $script ) );
				if ( is_file( $pathname ) )
					break;

				if ( is_file( "$pathname.php" ) )
				{
					$script[] = array_pop( $script ) . '.php';
					break;
				}
			}

			$application->script = implode( '/', $script );
		}


		// extract additional selectors to be available in script
		if ( txf::getContextMode() == txf::CTXMODE_REWRITTEN )
			$application->selectors = $frames;
		else
			$application->selectors = explode( '/', $_SERVER['PATH_INFO'] );

		$application->selectors = array_map( function ( $a ) { return data::autoType( trim( $a ) ); }, $application->selectors );


		// prepare application's base URL
		$application->url = path::glue( $context->url, $application->name );


		return $application;
	}

	/**
	 * Conveniently provides read-only access on protected/private properties.
	 *
	 * @param string $name name of property to read
	 * @return mixed value of property, null on addressing unknown property
	 */

	public function __get( $name )
	{
		if ( property_exists( $this, $name ) )
			return $this->$name;
	}

	/**
	 * Retrieves prefix for relative URLs pointing from current script to public
	 * root folder of current application.
	 *
	 * This prefix is required to use selectors affecting browsers in resolving
	 * relative URLs differently.
	 *
	 * @param string $rootRelativePathname pathname of resource to be prefixed
	 * @return string
	 */

	public function relativePrefix( $rootRelativePathname = null )
	{
		return implode( '', array_pad( array(), count( $this->selectors ), '../' ) ) . trim( $rootRelativePathname );
	}

	/**
	 * Compiles URL addressing selected script of current application.
	 *
	 * @param string $scriptName pathname of script relative to application folder
	 * @param array $parameters set of parameters to pass in query
	 * @param mixed $selector first of multiple optional selectors to include
	 * @return string URL of addressed script including optional parameters
	 */

	public function scriptURL( $scriptName, $parameters = array(), $selector = null )
	{
		if ( !is_array( $parameters ) )
			throw new \InvalidArgumentException( 'parameters must be array' );


		if ( substr( $scriptName, -4 ) == '.php' )
			$scriptName = substr( $scriptName, 0, -4 );


		$selectors = func_get_args();
		$selectors = array_slice( $selectors, 2 );

		if ( ( count( $selectors ) == 1 ) && is_array( $selectors[0] ) )
			$selectors = array_shift( $selectors );

		$selectors = implode( '/', $selectors );


		switch ( txf::getContextMode() )
		{
			case txf::CTXMODE_NORMAL :
			default :
				$url = path::glue( $this->url, $scriptName, $selectors );
				break;

			case txf::CTXMODE_REWRITTEN :
				$proxy = ( $this->usedProxy === true ) ? '' : $this->usedProxy;
				$url   = path::glue( $this->context->url, $proxy, $this->name, $scriptName, $selectors );
				break;
		}


		return $url . ( count( $parameters ) ? '?' . http_build_query( $parameters ) : '' );
	}

	/**
	 * Compiles URL addressing current script of current application.
	 *
	 * @param array|false $parameters set of parameters to pass in query, false to drop all current parameters (e.g. on creating GET form)
	 * @param mixed $selector first of multiple optional selectors to include
	 * @return string URL of addressed script including optional parameters
	 */

	public function selfURL( $parameters = array(), $selector = null )
	{
		$selectors = func_get_args();
		$selectors = array_slice( $selectors, 1 );

		if ( !count( $selectors ) )
			$selectors = application::current()->selectors;


		/*
		 * merge current script's input parameters with provided one
		 */

		if ( $parameters === false )
			$parameters = array();
		else
		{
			// find all non-persistent input parameters of current script
			$currentVolatileParameters = array();

			$get = input::source( input::SOURCE_ACTUAL_GET );
			foreach ( $get->listNames() as $name )
				if ( data::isKeyword( $name ) && !input::isPersistent( $name ) )
					$currentVolatileParameters[$name] = $get->getValue( $name );

			// merge volatile input with given parameters, but drop all those
			// finally set NULL (to support removal per $parameters)
			$parameters = array_filter( array_merge( $currentVolatileParameters, $parameters ), function( $item ) { return $item !== null; } );
		}


		/*
		 * utilize scriptURL() for referring to current script
		 */

		array_unshift( $selectors, $parameters );
		array_unshift( $selectors, $this->script );

		return call_user_func_array( array( &$this, 'scriptURL' ), $selectors );
	}
}

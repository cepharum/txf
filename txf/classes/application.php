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
 * Describes basic information on application current script belongs to.
 *
 * @property-read string $name
 * @property-read string $pathname
 * @property-read string $script
 * @property-read array $selectors
 * @property-read string $url
 * @property-read string|boolean $usedProxy
 * @property-read context $context
 * @property-read boolean $gotNameFromEnvironment
 *
 * @package de\toxa\txf
 */

class application
{
	/**
	 * name of application
	 *
	 * @var string
	 */

	protected $name;

	/**
	 * pathname of application's folder
	 *
	 * @var string
	 */

	protected $pathname;

	/**
	 * pathname of current script, set on managing current application, only
	 *
	 * @var string
	 */

	protected $script;

	/**
	 * additional resource selectors provided in request
	 *
	 * @var array
	 */

	protected $selectors;

	/**
	 * public URL of application, e.g. for addressing related images and files
	 *
	 * @var string
	 */

	protected $url;

	/**
	 * used proxy script or true on detecting use of rewrite engine
	 *
	 * @var string|boolean
	 */

	protected $usedProxy;

	/**
	 * Context of current application.
	 *
	 * @var context
	 */

	protected $context;

	/**
	 * Marks if application's name was selected by environment variable.
	 *
	 * @var boolean
	 */

	protected $gotNameFromEnvironment;



	protected function __construct()
	{
	}

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

		$envApplicationName = getenv( 'TXF_APPLICATION' );

		$application->gotNameFromEnvironment = !!$envApplicationName;


		/*
		 * extract selected application and source
		 */

		// choose source of application/script selector
		$frames = $context->getRequestedScriptUri( $application->usedProxy );
		if ( empty( $frames ) )
			throw new http_exception( 400, 'Request missing application. Check your setup!' );

		// extract information on application folder and name
		if ( $application->gotNameFromEnvironment ) {
			$application->name = $envApplicationName;
		} else {
			$application->name = array_shift( $frames );
		}

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
		if ( $application->gotNameFromEnvironment ) {
			$application->url = $context->url;
		} else {
			$application->url = path::glue( $context->url, $application->name );
		}


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
		switch ( $name ) {
			case 'prefixPathname' :
				return $this->gotNameFromEnvironment ? $this->context->prefixPathname : path::glue( $this->context->prefixPathname, $this->name );

			default :
				return property_exists( $this, $name ) ? $this->$name : null;
		}
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
				$url = path::glue( $this->url, $scriptName, $selectors );
				break;

			case txf::CTXMODE_REWRITTEN :
				$proxy = ( $this->usedProxy === true ) ? '' : $this->usedProxy;
				if ( $this->gotNameFromEnvironment ) {
					$url = path::glue( $this->context->url, $proxy, $scriptName, $selectors );
				} else {
					$url = path::glue( $this->context->url, $proxy, $this->name, $scriptName, $selectors );
				}
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
			$currentParameters = input::source( input::SOURCE_ACTUAL_GET )->getAllValues();
			foreach ( $currentParameters as $name => $value )
				if ( !data::isKeyword( $name ) || input::isPersistent( $name ) )
					unset( $currentParameters[$name] );

			// merge volatile input with given parameters, but drop all those
			// finally set NULL (to support removal per $parameters)
			$parameters = array_filter( array_merge( $currentParameters, $parameters ), function( $item ) { return $item !== null; } );
		}


		/*
		 * utilize scriptURL() for referring to current script
		 */

		array_unshift( $selectors, $parameters );
		array_unshift( $selectors, $this->script );

		return call_user_func_array( array( &$this, 'scriptURL' ), $selectors );
	}
}

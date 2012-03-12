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


class context
{

	/**
	 * marks if current request was sent over HTTPS
	 *
	 * @var boolean
	 */

	private $isHTTPS;

	/**
	 * absolute pathname of installation (containing framework and all apps)
	 *
	 * @var string
	 */

	private $installationPathname;

	/**
	 * absolute pathname of framework folder (txf)
	 *
	 * @var string
	 */

	private $frameworkPathname;

	/**
	 * pathname of installation relative to document root folder
	 * (e.g. for use in URL)
	 * 
	 * @example http://www.domain.com/SOME/PREFIX/application/action
	 *
	 * @var string
	 */

	private $prefixPathname;

	/**
	 * pathname of requested script relative to installation folder
	 *
	 * @var string
	 */

	private $scriptPathname;

	/**
	 * pathname of requested script's application relative to installation folder
	 *
	 * @var string
	 */

	private $applicationPathname;

	/**
	 * pathname of requested script relative to its application's folder
	 *
	 * @var string
	 */

	private $applicationScriptPathname;

	/**
	 * URL of current installation
	 *
	 * @var string
	 */

	private $url;

	/**
	 * detected name of current application
	 *
	 * @var string
	 */

	private $application;



	public function __construct()
	{

		// include some initial assertions on current context providing minimum
		// set of expected information
		assert( '$_SERVER["HTTP_HOST"]' );
		assert( '$_SERVER["DOCUMENT_ROOT"]' );
		assert( '$_SERVER["SCRIPT_FILENAME"]' );



		/*
		 * PHASE 1: Detect basic pathnames and URL components
		 */

		$this->frameworkPathname    = dirname( dirname( __FILE__ ) );
		$this->installationPathname = dirname( $this->frameworkPathname );

		$this->isHTTPS = ( $_SERVER['HTTPS'] != false );




		/*
		 * PHASE 2: Validate and detect current application
		 */

		// validate location of processing script ...
		// ... must be inside document root
		if ( path::isInWebfolder( $_SERVER['SCRIPT_FILENAME'] ) === false )
			throw new \InvalidArgumentException( 'script is not part of webspace' );

		// ... must be inside installation folder of TXF
		$this->scriptPathname = path::relativeToAnother( $this->installationPathname, realpath( $_SERVER['SCRIPT_FILENAME'] ) );
		if ( $this->scriptPathname === false )
			throw new \InvalidArgumentException( 'script is not part of TXF installation' );

		// derive URL's path prefix to select folder containing TXF installation
		$this->prefixPathname = path::relativeToAnother( realpath( $_SERVER['DOCUMENT_ROOT'] ), $this->installationPathname );
		if ( $this->prefixPathname === false )
		{
			// installation's folder might be linked into document root using symlink
			// --> comparing pathname of current script with document root will fail then
			//     --> try alternative method to find prefix pathname 
			$this->prefixPathname = path::relativeToAnother( $_SERVER['DOCUMENT_ROOT'], dirname( $_SERVER['SCRIPT_FILENAME'] ) );
		}


		// cache some derivable names
		list( $this->applicationPathname, $this->applicationScriptPathname ) = path::stripCommonPrefix( $this->scriptPathname, $this->prefixPathname, null );

		// compile base URL of current installation
		$this->url = path::glue(
							( $this->isHTTPS ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'],
							$this->prefixPathname
							);

		// detect current application
		$this->application = application::current( $this );
	}

	public static function getDocumentRoot()
	{
		if ( array_key_exists( 'DOCUMENT_ROOT', $_ENV ) )
			return $_ENV['DOCUMENT_ROOT'];

		return $_SERVER['DOCUMENT_ROOT'];
	}

	/**
	 * Simplifies retrieval of compiled URLs to a current script.
	 * 
	 * @param array $parameters set of parameters to include in reference
	 * @param string $selector one of several selectors to include in reference
	 * @return string URL referring to currently running script 
	 */

	public static function selfURL( $parameters = array(), $selectors = null )
	{
		$args = func_get_args();

		return call_user_func_array( array( application::current(), 'selfURL' ), $args );
	}

	/**
	 * Simplifies retrieval of compiled URLs to a selected script of current 
	 * application.
	 * 
	 * @param string $scriptName name of script to refer to
	 * @param array $parameters set of parameters to include in reference
	 * @param string $selector one of several selectors to include in reference
	 * @return string URL referring to selected script 
	 */

	public static function scriptURL( $scriptName, $parameters = array(), $selector = null )
	{
		$args = func_get_args();

		return call_user_func_array( array( application::current(), 'scriptURL' ), $args );
	}

	public function __get( $name )
	{
		if ( property_exists( $this, $name ) )
			return $this->$name;

		return null;
	}
}


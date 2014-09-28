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
	 * hostname used in current request
	 *
	 * @var string
	 */

	private $hostname;

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

		$this->isHTTPS = ( $_SERVER['HTTPS'] != false || $_SERVER['HTTP_X_HTTPS'] != false );

		// analyse special case of working behind reverse proxy
		if ( array_key_exists( 'HTTP_X_ORIGINAL_URL', $_SERVER ) ) {
			$url = parse_url( $_SERVER['HTTP_X_ORIGINAL_URL'] );

			$this->hostname = $url['host'];
			$proxyPrefix    = $url['path'];
		} else {
			$proxyPrefix = false;
		}

		if ( trim( $this->hostname ) === '' )
			$this->hostname = $_SERVER['HTTP_HOST'];



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
		$this->prefixPathname = path::relativeToAnother( realpath( static::getDocumentRoot() ), $this->installationPathname );
		if ( $this->prefixPathname === false )
		{
			// installation's folder might be linked into document root using symlink
			// --> comparing pathname of current script with document root will fail then
			//     --> try alternative method to find prefix pathname
			$this->prefixPathname = path::relativeToAnother( static::getDocumentRoot(), dirname( $_SERVER['SCRIPT_FILENAME'] ) );
		}


		// running behind reverse proxy?
		if ( $proxyPrefix ) {
			// detect prefix used to control that reverse proxy
			$request = implode( '/', $this->getRequestedScriptUri( $dummy ) );
			$split   = strpos( $proxyPrefix, $request );

			if ( $split >= 0 ) {
				// extract prefix required by reverse proxy
				$proxyPrefix = substr( $proxyPrefix, 0, $split );

				// prepend extracted prefix of reverse proxy to previously
				// detected prefix used to address installation of TXF locally
				$this->prefixPathname = path::glue( $proxyPrefix, $this->prefixPathname );
			}
		}


		// cache some derivable names
		list( $this->applicationPathname, $this->applicationScriptPathname ) = path::stripCommonPrefix( $this->scriptPathname, $this->prefixPathname, null );

		// compile base URL of current installation
		$this->url = path::glue(
							( $this->isHTTPS ? 'https://' : 'http://' ) . $this->hostname,
							$this->prefixPathname
							);

		// detect current application
		$this->application = application::current( $this );
	}

	/**
	 * Retrieves part of request URI selecting current application and script.
	 *
	 * The fetched request URI is provided as array of pathname elements, that is
	 * pathname might be derived by joining returned elements with '/'.
	 *
	 * @note Fetched request URI might include further selectors to be processed
	 *       by requested script, e.g.
	 *
	 *       myapp/myscript/par1-of-script/par2-of-script
	 *
	 * @param $detectedProxy name of detected proxy script
	 * @return array sequence of filenames contained in requested URI for selecting script
	 */

	public function getRequestedScriptUri( &$detectedProxy )
	{
		switch ( txf::getContextMode() ) {
			case txf::CTXMODE_REWRITTEN :
				assert( '$_SERVER[REDIRECT_URL] || $_SERVER[PATH_INFO] || $_SERVER[REQUEST_URI]' );

				// get originally requested script (e.g. prior to rewrite)
				if ( trim( $_SERVER['REDIRECT_URL'] ) !== '' )
				{
					// use of mod_rewrite detected
					$query = $_SERVER['REDIRECT_URL'];

					// mark rewrite mode by not selecting any valid used proxy
					$detectedProxy = true;
				}
				else if ( trim( $_SERVER['REQUEST_URI'] ) !== '' )
				{
					// use of lighttpd's rewriting detected
					$query = strtok( $_SERVER['REQUEST_URI'], '?' );

					// mark rewrite mode by not selecting any valid used proxy
					$detectedProxy = true;
				}
				else
				{
					// request for proxy script (run.php) detected
					$query = $_SERVER['PATH_INFO'];

					// remember proxy script used this time, if any
					$detectedProxy = $this->scriptPathname;
				}

				// derive list of application and script selectors
				return path::stripCommonPrefix( explode( '/', $query ), $this->prefixPathname );

			// txf::CTXMODE_NORMAL
			default :
				// expect application and script selectors being part of requested
				// script pathname
				return preg_split( '#/+#', $this->applicationScriptPathname );
		}
	}

	public static function getDocumentRoot()
	{
		if ( array_key_exists( 'TXF_DOCUMENT_ROOT', $_ENV ) )
			return $_ENV['TXF_DOCUMENT_ROOT'];

		if ( array_key_exists( 'TXF_DOCUMENT_ROOT', $_SERVER ) )
			return $_SERVER['TXF_DOCUMENT_ROOT'];

		if ( array_key_exists( 'DOCUMENT_ROOT', $_ENV ) )
			return $_ENV['DOCUMENT_ROOT'];

		return $_SERVER['DOCUMENT_ROOT'];
	}

	/**
	 * Simplifies retrieval of compiled URLs to a current script.
	 *
	 * @param array|false $parameters set of parameters to include in reference
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


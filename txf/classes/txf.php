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


include_once( 'singleton.php' );
include_once( 'shortcuts.php' );
include_once( 'extension.php' );


class txf extends singleton
{

	/**
	 * instance of class context describing current context
	 *
	 * @var \de\toxa\txf\context
	 */

	private $context;


	private $session;


	protected $classRedirectionMap = array();


	/**
	 * One of the txf::CTXMODE_* constants' value selecting one of several
	 * modes current request might be processed in.
	 *
	 * The context mode is used to separate different ways of executing a
	 * script in TXF from each other resulting in different behaviours each.
	 *
	 * @var enum
	 */

	private static $contextMode = null;



	/**
	 * Selects normal processing mode used on addressing script directly.
	 */

	const CTXMODE_NORMAL = 'normal';

	/**
	 * Selects embedded processing mode for running a script from another PHP
	 * based app such as a CMS.
	 */

	const CTXMODE_EMBEDDED = 'embedded';

	/**
	 * Selects rewritten processing mode used on addressing a script over
	 * rewrite engine through run.php.
	 */

	const CTXMODE_REWRITTEN = 'rewritten';



	/**
	 * Creates new context for runtime.
	 *
	 */

	public function __construct()
	{
		$this->classRedirectionMap = array();
	}

	/**
	 * Indicates whether switching currently selected instance of txf is enabled
	 * or not.
	 *
	 * @return boolean true on disabling switch to different txf instance
	 */

	final public static function singleSelectOnly()
	{
		return true;
	}


	/**
	 * Gains read/write access on subset of TXF-managed session space.
	 *
	 * @note You need to assign-by-reference the result of this method, e.g.
	 *
	 *        $scriptSession =& txf::session()
	 *
	 * @see session::access()
	 * @param enum $scope valid combination of session::SCOPE_* constants
	 * @param string $parameter additional parameter used depending on $scope
	 * @return array-ref reference on session-based space
	 */

	final public static function &session( $scope = session::SCOPE_SCRIPT, $parameter = null )
	{
		return static::current()->session->access( $scope, $parameter );
	}


	/**
	 * Gets called on single instance being selected initially.
	 *
	 */

	public function onLoad()
	{
		// install class autoloader
		spl_autoload_register( array( self, 'classAutoloader' ) );

		// analyse pathnames and prepare context for addressing related resources
		$this->context = new context;

		if ( !is_dir( $this->context->application->pathname ) )
			throw new \InvalidArgumentException( 'no such application' );

		// declare some constants for improving performance on accessing TXF context
		define( 'TXF_INSTALL_PATH', $this->context->installationPathname );
		define( 'TXF_FRAMEWORK_PATH', $this->context->frameworkPathname );
		define( 'TXF_URL', $this->context->url );
		define( 'TXF_APPLICATION', $this->context->application->name );
		define( 'TXF_APPLICATION_PATH', $this->context->application->pathname );
		define( 'TXF_APPLICATION_URL', $this->context->application->url );
		define( 'TXF_SCRIPT_PATH', $this->context->application->script );
		define( 'TXF_SCRIPT_NAME', pathinfo( TXF_SCRIPT_PATH, PATHINFO_FILENAME ) );

		// prepare URL prefix to use on compiling relative URLs pointing to
		// current application's public root
		define( 'TXF_RELATIVE_PREFIX', $this->context->application->relativePrefix() );

		// start runtime configuration support
		config::init();

		// support configuration option to enable/disable errors displayed in output
		ini_set( 'display_errors', config::get( 'php.display_errors', false ) );

		// enable internal class redirection support
		$this->initializeClassRedirections();

		// open managed session space
		$this->session = session::current();


		/*
		 * basically put further initialization below this comment
		 */

	}

	/**
	 * Automatically tries to load class from related file.
	 *
	 * @param string $className name of class to load
	 * @return true|null true on successfully loading class, null otherwise
	 */

	public static function classAutoloader( $className )
	{
		/*
		 * check for matching namespace
		 */

		$namespace = __NAMESPACE__ . '\\';
		$nsLength  = strlen( $namespace );

		if ( !strncmp( $className, $namespace, $nsLength ) )
		{
			// extract namespace-local name of class
			$className = substr( $className, $nsLength );


			// optionally support class redirection
			if ( self::hasCurrent() )
			{
				$map = self::current()->getClassRedirectionMap();

				if ( \array_key_exists( $className, $map ) )
					$className = $map[$className];

				if ( !$className )
					// auto-loader disabled by class redirection
					return null;
			}


			// detect subordinated namespace selecting class of extension
			if ( strpos( $className, '\\' ) )
			{
				$temp      = strtr( $className, '\\', '/' );
				$subNS     = dirname( $temp );
				$className = basename( $temp );

				$pathname  = '/extensions/' . $subNS . '/classes/';
			}
			else
				$pathname  = '/classes/';


			// select repositories to check for class files
			// - always try TXF core
			$locations = array( dirname( dirname( __FILE__ ) ) . $pathname );

			// - prefer current application's repository if available
			if ( defined( 'TXF_APPLICATION_PATH' ) )
			{
				array_unshift( $locations, TXF_APPLICATION_PATH . $pathname );
			}


			// derive set of relative path names to test for matching class file
			$names = array(
				strtr( $className, '_', '/' )       // try deeply-structured files first
			);

			if ( $names[0] !== $className ) {
				array_push( $names, $className );   // try surface-structured files then
			}


			/*
			 * iterate over all prepared repositories in proper fall-back order
			 * for detecting file implementing selected class
			 */

			foreach ( $locations as $location ) {
				foreach ( $names as $name ) {
					$classFilePrefix = $location . $name;

					/*
					 * Always try file used in current installation for
					 * overloading some externally managed file, e.g. core file
					 * of TXF to be replaced probably on updating TXF.
					 */

					$fileName = $classFilePrefix . '.overload.php';
					if ( is_file( $fileName ) ) {
						include_once( $fileName );
						return true;
					}

					// try non-overloading file next
					$fileName = $classFilePrefix . '.php';
					if ( is_file( $fileName ) ) {
						include_once( $fileName );
						return true;
					}
				}
			}
		}
	}

	/**
	 * Tries to import selected class.
	 *
	 * @param string $className name of class to import
	 * @return boolean true if class is available, false otherwise
	 */

	public static function import( &$className )
	{
		// qualify provided class name
		$className = ( $className[0] === '\\' ) ? substr( $className, 1 ) : __NAMESPACE__ . "\\$className";

		// check if selected class exists
		return $className && class_exists( $className, true );
	}


	/**
	 * Looks for single extension installed either in context of current
	 * application or in context of shared code base (framework):
	 *
	 * @throws \InvalidArgumentException on providing empty or non-string extension name
	 * @param string $extensionName extension to look for
	 * @return string|null absolute pathname of found extension, null on missing it
	 */

	public function findExtension( $extensionName )
	{
		if ( !is_string( $extensionName ) || ( ( $extensionName = trim( $extensionName ) ) === '' ) )
			throw new \InvalidArgumentException( 'invalid or missing extension name' );

		return $this->findResource( path::glue( 'extensions', $extensionName ) );
	}

	/**
	 * Looks for resource file selected by its relative pathname.
	 *
	 * This method is used to overload resources in framework by resources in
	 * current application.
	 *
	 * @throws \InvalidArgumentException on providing empty or non-string pathname
	 * @param string $resourcePathname relative pathname of resource to look for
	 * @return string|null absolute pathname of found resource, null on mismatch
	 */

	public function findResource( $resourcePathname )
	{
		if ( !is_string( $resourcePathname ) || ( ( $resourcePathname = trim( $resourcePathname ) ) === '' ) )
			throw new \InvalidArgumentException( 'invalid or missing resource pathname' );

		if ( $this->context )
		{
			if ( $this->context->application() )
			{
				$pathname = path::glue( $this->context->applicationPathname(), $resourcePathname );
				if ( is_dir( $pathname ) )
					return $pathname;
			}

			$pathname = path::glue( $this->context->frameworkPathname(), $resourcePathname );
			if ( is_dir( $pathname ) )
				return $pathname;
		}

		// no such extension
		return null;
	}


	/**
	 * Enables read-only access on non-public properties.
	 *
	 * @param string $property name of property to read
	 * @return mixed value of access property
	 */

	public function __get( $property )
	{
		if ( property_exists( $this, $property ) )
			return $this->$property;

		return null;
	}


	/**
	 * Filters adjustment of non-public properties.
	 *
	 * @param string $property name of property to adjust
	 * @param mixed $value value to assign
	 */

	public function __set( $property, $value )
	{
	}


	/**
	 * Sets context mode to use in processing current request.
	 *
	 * @param enum $contextMode on of txf::CTXMODE_* constants
	 */

	public static function setContextMode( $contextMode )
	{
		if ( !is_null( self::$contextMode ) )
			throw new \BadMethodCallException( 'invalid call for adjusting context mode' );

		if ( !in_array( $contextMode, array(
											self::CTXMODE_NORMAL,
											self::CTXMODE_EMBEDDED,
											self::CTXMODE_REWRITTEN,
										) ) )
			throw new \InvalidArgumentException( 'invalid context mode' );

		self::$contextMode = $contextMode;
	}

	/**
	 * Retrieves current context mode.
	 *
	 * @return enum one of the txf::CTXMODE_* constants' value
	 */

	public static function getContextMode()
	{
		return self::$contextMode ? self::$contextMode : self::CTXMODE_NORMAL;
	}

	/**
	 * Initializes support for class redirections by reading initial map from
	 * runtime configuration.
	 */

	protected function initializeClassRedirections()
	{
		$initialRedirections = config::getList( 'txf.autoloader.redirect' );

		if ( is_array( $initialRedirections ) )
			foreach ( $initialRedirections as $redirection )
				try
				{
					$this->redirectClass( $redirection['source'], $redirection['target'] );
				}
				catch ( \InvalidArgumentException $e )
				{
					trigger_error( sprintf( 'invalid class redirection %s -> %s ignored', $redirection['source'], $redirection['target'] ), E_USER_WARNING );
				}
	}

	/**
	 * Requests to redirect selected class.
	 *
	 * All classnames are relative to namespace de\toxa\txf and thus may include
	 * selection of a subnamespace resulting in file being loaded from an
	 * extension's folder.
	 *
	 * Class redirection occurs on autoloading a class on first request, so this
	 * method is senseless after class in $source has been loaded.
	 *
	 * Redirection is actually used to affect derivation of pathname of file
	 * expected to implement requested class. It is not supporting class aliases
	 * and cannot be used to rename a class.
	 *
	 * The file of redirected classname must implement the requested class using
	 * that one's original, non-redirected name. This constraint implies using
	 * same namespace.
	 *
	 * @param string $source name of original class
	 * @param string $target name of class to actually lookup on selecting file
	 */

	public function redirectClass( $source, $target )
	{
		if ( !self::isValidClassName( $source ) ||
			 !self::isValidClassName( $target ) )
			 throw new \InvalidArgumentException( 'invalid class name' );

		$this->classRedirectionMap[$source] = $target;
	}

	/**
	 * Detects if provided string contains valid class name.
	 *
	 * This check is testing syntactical validity, only. It doesn't check
	 * whether related class actually exists or not.
	 *
	 * @param string $name class name to check
	 * @return boolean true on a valid class name, false otherwise
	 */

	public static function isValidClassName( &$name )
	{
		if ( string::isString( $name ) )
		{
			$name = trim( $name );

			return preg_match( '/^([a-z_][a-z0-9_]*\\\\)*[a-z_][a-z0-9_]*$/i', $name );
		}

		return false;
	}


	public static function redirectTo( $url )
	{
		if ( func_num_args() > 1 )
			$url = call_user_func_array( array( __NAMESPACE__ . '\context', 'scriptURL' ), func_get_args() );

		if ( url::isRelative( $url ) )
			$url = application::current()->relativePrefix( $url );

		header( 'Location: ' . $url );

		view::callDisableOutput();

		exit;
	}
}


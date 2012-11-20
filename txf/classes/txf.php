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


	protected $classRedirectionMap;


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

		// start runtime configuration support
		config::init();

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

		$nsLength = strlen( __NAMESPACE__ );

		if ( !strncmp( $className, __NAMESPACE__ . '\\', $nsLength + 1 ) )
		{

			// extract qualified class name relative to current namespace
			$relativeClassName = substr( $className, $nsLength + 1 );


			// optionally support class redirection
			if ( self::hasCurrent() )
			{
				if ( \array_key_exists( $relativeClassName, self::current()->getClassRedirectionMap() ) )
					$relativeClassName = self::current()->$classRedirectionMap[$relativeClassName];

				if ( !$relativeClassName )
					// loader disabled by class redirection
					return null;
			}


			// detect subnamespaces selecting class of extension
			if ( strpos( $relativeClassName, '\\' ) )
			{
				$relativeClassName = strtr( $relativeClassName, '\\', '/' );
				$classPathname = '/extensions/' . dirname( $relativeClassName ) . '/classes/' . basename( $relativeClassName );
			}
			else
				$classPathname = '/classes/' . $relativeClassName;


			// prefer to load class file shipped with current application
			if ( defined( 'TXF_APPLICATION_PATH' ) )
			{
				$classFile = TXF_APPLICATION_PATH . $classPathname . '.php';
				if ( is_file( $classFile ) /*&& path::isInWebfolder( $classFile )*/ )
				{
					include_once( $classFile );
					return true;
				}
			}


			// look for selected class in context of framework
			$classFile = dirname( dirname( __FILE__ ) ) . $classPathname;

			if ( is_file( $classFile . '.overload.php' ) )
			{
				// Overload-class files are considered to replace existing
				// txf-internal classes without utilizing complex extension.
				// Overloads are good for collections of applications that
				// share special behaviour not found in distributed TXF core.
				// They are never upgraded and thus survive upgrading core
				// though they might break upgraded API ...
				include_once( $classFile . '.overload.php' );
				return true;
			}

			if ( is_file( $classFile . '.php' ) )
			{
				include_once( $classFile . '.php' );
				return true;
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
		$initialRedirections = config::get( 'txf.autoloader.redirect' );

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
			$name = trim( $name->string );

			return preg_match( '/^([a-z_][a-z0-9_]*\\)*[a-z_][a-z0-9_]*$/i', $name );
		}

		return false;
	}


	public static function redirectTo( $url )
	{
		header( 'Location: ' . $url );

		view::callDisableOutput();

		exit;
	}
}


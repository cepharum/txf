<?php


/**
 * Extension Management
 *
 * This class is designed to serve basic functionality for extension integration
 * and features
 *  - detecting extensions
 *  - accessing extensions
 *  - managing single contexts per extension using internal registry
 *
 *
 * @author Thomas Urban <info@toxa.de>
 * @copyright 2011, toxA IT-Dienstleistungen, http://www.toxa.de
 * @package core
 * @license LGPL
 * @version %%version%%
 *
 */


namespace de\toxa\txf;


class extension
{
	protected static $registry = array();


	private $name;

	private $version;


	public function __construct( $name )
	{
		$name = trim( $name );

		if ( preg_match( '[.)(]', $name ) )
			throw new \InvalidArgumentException( 'invalid extension name' );

		$this->name = $name;
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'name' :
				return $this->name;

			case 'version' :
				if ( is_null( $this->version ) )
				{
					$versionFile = path::glue( $this->basePath(), 'VERSION' );
					if ( file_exists( $versionFile ) )
						$this->version = trim( file_get_contents( $versionFile ) );

					if ( !$this->version )
						throw new \RuntimeException( 'missing extension version' );
				}

				return $this->version;
		}
	}

	/**
	 * Add extension to current runtime-based registry.
	 *
	 * This is required to enable callbacks to extension. Also, dependency-based
	 * sorting of extension callbacks relies on prior registration.
	 *
	 * Basically, registering is required once per runtime. Calling this method
	 * multiple times isn't affecting registry. Actually, it might boost
	 * performance if extensions are registering on occasional demand, only.
	 *
	 */

	public function register()
	{
		if ( !array_key_exists( $this->name, self::$registry ) )
			if ( $this->exists() )
				self::$registry[$this->name] = $this;
	}

	/**
	 * Retrieves instance of extension selected by its name.
	 *
	 * @param string $name name of extension to load, e.g. "view/skinnable"
	 * @return extension
	 */

	public static function selectByName( $name )
	{
		if ( is_string( $name ) && array_key_exists( $name, self::$registry ) )
			return self::$registry[$name];

		return new static( $name );
	}

	/**
	 * Retrieves instance of extension containing class selected by its fully
	 * qualified name.
	 *
	 * @param string $relativeClassname class name
	 * @return extension
	 */

	public static function selectByClass( $relativeClassname )
	{
		if ( !strncmp( $relativeClassname, __NAMESPACE__ . '\\', strlen( __NAMESPACE__ ) + 1 ) )
			$relativeClassname = substr( $relativeClassname, strlen( __NAMESPACE__ ) + 1 );

		$pathname = strtr( $relativeClassname, '\\', '/' );
		if ( preg_match( '#^(.+)/([^/]+)$#', $pathname, $matches ) )
			return self::selectByName( $matches[1] );

		throw new \InvalidArgumentException( 'class is not part of extension' );
	}

	/**
	 * Retrieves base path of extension.
	 *
	 * @return string
	 */

	public function basePath()
	{
		$subfolder = '/extensions/' . $this->name;

		$pathname = TXF_APPLICATION_PATH . $subfolder;
		if ( !is_dir( $pathname ) )
			$pathname = TXF_FRAMEWORK_PATH . $subfolder;

		if ( !is_dir( $pathname ) )
			throw new \RuntimeException( 'extension not found' );

		return $pathname;
	}

	/**
	 * Detect if extension actually exists.
	 *
	 * @return boolean true if extension is available
	 */

	public function exists()
	{
		try
		{
			$this->basePath();

			return true;
		}
		catch ( \RuntimeException $e )
		{
			return false;
		}
	}

}
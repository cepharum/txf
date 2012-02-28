<?php


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
		$this->prefixPathname = path::relativeToAnother( $_SERVER['DOCUMENT_ROOT'], $this->installationPathname );
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
							( $context->isHTTPS ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'],
							$context->prefixPathname
							);

		// detect current application
		$this->application = application::current( $this );
	}


	public function __get( $name )
	{
		if ( property_exists( $this, $name ) )
			return $this->$name;

		return null;
	}
}


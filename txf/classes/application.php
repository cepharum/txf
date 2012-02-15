<?php


namespace de\toxa\txf;


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
	 */

	public static function current( context $context = null )
	{
		if ( is_null( $context ) )
			$context = new context();


		$application = new static;

		$application->context = $context;


		/*
		 * choose source of application/script selector
		 */

		if ( txf::getContextMode() == txf::CTXMODE_REWRITTEN )
		{
			assert( '$_SERVER[REDIRECT_URL] || $_SERVER[PATH_INFO]' );

			// get originally requested script (e.g. prior to rewrite)
			if ( trim( $_SERVER['REDIRECT_URL'] ) !== '' )
			{
				// use of mod_rewrite detected
				$query = $_SERVER['REDIRECT_URL'];

				// mark rewrite mode by not selecting any valid used proxy
				$application->usedProxy = true;
			}
			else
			{
				// request for proxy script (run.php) detected
				$query = $_SERVER['PATH_INFO'];

				// remember proxy script used this time, if any
				$application->usedProxy = $context->scriptPathname;
			}

			// derive list of application and script selectors
			$frames = explode( '/', trim( $query, '/' ) );

		}
		else // txf::CTXMODE_NORMAL
			// expect application and script selectors being part of requested
			// script pathname
			$frames = explode( '/', $context->scriptPathname );


		/*
		 * extract selected application and source
		 */

		if ( empty( $frames ) )
			// require both application and script selector
			throw new http_exception( 400 );

		// extract information on application folder and name
		$application->name = array_shift( $frames );

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
			$application->selectors = explode( '/', trim( $_SERVER['PATH_INFO'], '/' ) );

		$application->selectors = array_filter( $application->selectors, function( $a ) { return trim( $a ); } );
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
				$url   = path::glue( $this->context->url, $proxy, $this->name, $scriptName, $selectors );
				break;
		}

		return $url . ( count( $parameters ) ? '?' . http_build_query( $parameters ) : '' );
	}

	/**
	 * Compiles URL addressing current script of current application.
	 *
	 * @param array $parameters set of parameters to pass in query
	 * @param mixed $selector first of multiple optional selectors to include
	 * @return string URL of addressed script including optional parameters
	 */

	public function selfURL( $parameters = array(), $selector = null )
	{
		$selectors = func_get_args();
		$selectors = array_slice( $selectors, 1 );

		if ( empty( $selectors ) )
			$selectors = application::current()->selectors;

		if ( $parameters === null )
			$parameters = input::source( input::SOURCE_ACTUAL_GET )->getAllValues();

		array_unshift( $selectors, $parameters );
		array_unshift( $selectors, $this->script );

		return call_user_func_array( array( &$this, 'scriptURL' ), $selectors );
	}
}
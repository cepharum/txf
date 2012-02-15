<?php


/**
 * Implementation of gettext-based translations support
 *
 * This class is considered to be available by redirection, thus class is called
 * "de\toxa\txf\translation" here. See txf::redirectClass() for more.
 *
 */


namespace de\toxa\txf;


class translation extends singleton
{

	/**
	 * ID of currently selected locale
	 *
	 * @var string
	 */

	protected $language;

	/**
	 * gettext domain to use by current instance
	 *
	 * @var string
	 */
	
	protected $domain;

	

	public function onLoad()
	{
		$this->language = config::get( 'locale.language', 'en' );
		$this->domain   = config::get( 'locale.domain', TXF_APPLICATION );

		if ( \extension_loaded( 'gettext' ) )
		{
			$path = config::get( 'locale.path', path::glue( TXF_APPLICATION_PATH, 'locale' ) );

			bindtextdomain( $this->domain, $path );
		}

		setlocale( LC_ALL, $this->language );
	}

	public static function get( $singular, $plural, $count )
	{
		$count = abs( $count );

		if ( \extension_loaded( 'gettext' ) )
			return dngettext( $singular, $plural, $count );
		else
			return ( $count == 1 ) ? $singular : $plural;
	}
}


translation::init();

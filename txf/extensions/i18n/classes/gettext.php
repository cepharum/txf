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

use \de\toxa\txf\singleton;
use \de\toxa\txf\config;
use \de\toxa\txf\path;
use \de\toxa\txf\log;


/**
 * Implementation of gettext-based translations support
 *
 * This class is considered to be available by redirection, thus class is called
 * "de\toxa\txf\locale" here. See txf::redirectClass() for more.
 *
 * For using gettext configuration must be adopted to contain this XML-fragment:
 *
 * <txf>
 *  <autoloader>
 *   <redirect>
 *    <source>locale</source>
 *    <target>i18n\gettext</target>
 *   </redirect>
 *  </autoloader>
 * </txf>
 *
 * Create POT files using xgettext like this.
 *
 * cd <TXF app folder>
 * mkdir -p locale
 * find -name "*.php" | xgettext -f /dev/stdin -L PHP -k_L -k_L:1,2 -o locale/<TXF app>.pot
 *
 * Any translated file must be put into created folder locale using further
 * subfolders:
 *
 * # Create subfolder for translation's locale, e.g. "de_DE". Ensure to have a
 *   translation for default locale configured in locale.language. Use shorter
 *   locales for more common namings, such as "de" for any flavour of German.
 * # Create another subfolder inside that locale's folder called "LC_MESSAGES".
 * # Put translated file into that LC_MESSAGES folder. Rename it to <your app>.mo,
 *   default.mo or <whatever is configured in locale.domain>.mo.
 *
 * Notes on Ubuntu:
 *
 * # Install locale in system first
 *     sudo apt-get install language-pack-de-base
 * # get list of locales
 *     locale -a
 * # ensure to use locale listed there, e.g. de_DE.utf8, for naming folders and
 *   choosing locale in configuration as well declaring translation locale in PO
 *   file!
 *
 */


class locale extends singleton
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

	/**
	 * Cached copy of collection file.
	 *
	 * @var string
	 */

	protected static $collectionFileCache = null;

	/**
	 * Selects mode to use on collecting lookups in a catalog ("misses", "all", "").
	 *
	 * @var string
	 */

	protected static $collectionMode;

	/**
	 * Selects file to write collected lookups to.
	 *
	 * @var string
	 */

	protected static $collectionFile;



	public function onLoad()
	{
		// select language to use (this must be listed in server's locale -a)
		$this->language = config::get( 'locale.language', 'en_US.utf8' );
		$this->domain   = config::get( 'locale.domain', TXF_APPLICATION );

		putenv( 'LANGUAGE=' . $this->language );
		putenv( 'LANG=' . $this->language );
		putenv( 'LC_ALL=' . $this->language );

		if ( !setlocale( LC_ALL, $this->language ) )
			log::error( 'could not select locale %s', $this->language );

		if ( \extension_loaded( 'gettext' ) )
		{
			$path = config::get( 'locale.path', path::glue( TXF_APPLICATION_PATH, 'locale' ) );

			bindtextdomain( $this->domain, $path );
			textdomain( $this->domain );
			bind_textdomain_codeset( $this->domain, 'UTF-8' );
		}


		self::$collectionMode = config::get( 'locale.collect.mode', '' );
		self::$collectionFile = config::get( 'locale.collect.file' );
	}

	protected static function onMissingGettext( $singular, $plural, $count = 1, $fallbackSingular = null, $fallbackPlural = null ) {
		// missing i18n/l10n support
		// -> provide matching fallback or lookup preferring the former over the latter
		return ( $count == 1 ) ?
			( $fallbackSingular !== null ? $fallbackSingular : $singular )
			:
			( $fallbackPlural !== null ? $fallbackPlural : $plural );
	}

	protected static function processTranslated( $domain, $translated, $singular, $plural, $count = 1, $fallbackSingular = null, $fallbackPlural = null ) {
		switch ( self::$collectionMode )
		{
			case 'all' :
				$collect = 1;
				break;
			case 'misses' :
			default :
				$collect = ( $translated === ( $count == 1 ? $singular : $plural ) );
				break;
		}

		if ( $collect && self::$collectionFile && self::$collectionMode !== '' )
		{
			if ( !is_array( self::$collectionFileCache ) )
			{
				// first collected lookup in current runtime
				register_shutdown_function( array( __CLASS__, 'writeMissesCollection' ) );

				if ( file_exists( self::$collectionFile ) )
					self::$collectionFileCache = array_flip( array_filter( array_map( function( $line ) { return preg_match( '/^msgid "(.*)"\s*$/', $line, $matches ) ? $matches[1] : null; }, @file( self::$collectionFile ) ) ) );
				else
					self::$collectionFileCache = array();
			}

			self::$collectionFileCache[addcslashes( $singular, "\r\n\"" )] = true;
		}


		if ( $collect !== true )
			return $translated;

		// requested optional collection due to missing proper translation
		// -> process as if gettext isn't available at all
		return static::onMissingGettext( $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
	}

	public static function domainGet( $domain, $singular, $plural, $count = 1, $fallbackSingular = null, $fallbackPlural = null )
	{
		$count = abs( $count );

		if ( !\extension_loaded( 'gettext' ) ) {
			return static::onMissingGettext( $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
		}

		if ( !$domain && trim( $domain ) === '' ) {
			$domain = self::current()->getDomain();
		}

		$translated = dngettext( $domain, $singular, $plural, $count );

		return static::processTranslated( $domain, $translated, $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
	}

	public static function get( $singular, $plural, $count = 1, $fallbackSingular = null, $fallbackPlural = null )
	{
		$count = abs( $count );

		if ( !\extension_loaded( 'gettext' ) ) {
			return static::onMissingGettext( $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
		}

		$translated = ngettext( $singular, $plural, $count );

		return static::processTranslated( null, $translated, $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
	}

	public static function writeMissesCollection()
	{
		if ( self::$collectionFile && is_writable( @dirname( self::$collectionFile ) ) )
			if ( is_array( self::$collectionFileCache ) )
				@file_put_contents( self::$collectionFile, implode( "", array_map( function( $line ) { return "msgid \"$line\"\nmsgstr \"\"\n"; }, array_keys( self::$collectionFileCache ) ) ) );
	}
}


locale::init();

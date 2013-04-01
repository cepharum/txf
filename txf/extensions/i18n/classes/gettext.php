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
 * Implementation of gettext-based translations support
 *
 * This class is considered to be available by redirection, thus class is called
 * "de\toxa\txf\locale" here. See txf::redirectClass() for more.
 *
 * Create POT files using xgettext like this.
 *
 * cd <TXF app folder>
 * mkdir -p locale
 * find -name "*.php" | xgettext -f /dev/stdin -L PHP -k_L -k_L:1,2 -o locale/inpas.pot
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

		if ( \extension_loaded( 'gettext' ) )
		{
			$path = config::get( 'locale.path', path::glue( TXF_APPLICATION_PATH, 'locale' ) );

			bindtextdomain( $this->domain, $path );
			textdomain( $this->domain );
		}

		if ( !setlocale( LC_ALL, $this->language ) )
			log::error( 'could not select locale %s', $this->language );


		self::$collectionMode = config::get( 'locale.collect.mode', '' );
		self::$collectionFile = config::get( 'locale.collect.file' );
	}

	public static function get( $singular, $plural, $count )
	{
		$count = abs( $count );

		if ( \extension_loaded( 'gettext' ) )
		{
//			$translated = dngettext( self::current()->getDomain(), $singular, $plural, $count );
			$translated = ngettext( $singular, $plural, $count );

			switch ( self::$collectionMode )
			{
				case 'misses' :
					$collect = ( $translated === ( $count == 1 ? $singular : $plural ) );
					break;
				case 'all' :
					$collect = true;
					break;
				default :
					$collect = false;
			}

			if ( $collect && self::$collectionFile )
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


			return $translated;
		}
		else
			return ( $count == 1 ) ? $singular : $plural;
	}

	public static function writeMissesCollection()
	{
		if ( self::$collectionFile && is_writable( @dirname( self::$collectionFile ) ) )
			if ( is_array( self::$collectionFileCache ) )
				@file_put_contents( self::$collectionFile, implode( "", array_map( function( $line ) { return "msgid \"$line\"\nmsgstr \"\"\n"; }, array_keys( self::$collectionFileCache ) ) ) );
	}
}


locale::init();

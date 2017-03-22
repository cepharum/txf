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

use \de\toxa\txf\singleton;
use \de\toxa\txf\config;
use \de\toxa\txf\path;
use \de\toxa\txf\log;


/**
 * Implementation of gettext-based translations support
 *
 * # Enabling Extension
 *
 * This class is considered to be available by redirection, thus class is called
 * "de\toxa\txf\locale" here. See txf::redirectClass() for more. Redirection must
 * be configured in your application's configuration by properly inserting this
 * XML fragment:
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
 * # Creating Dictionary Templates
 *
 * ## Application-specific Translations
 *
 * POT files are required to contain all requests for localizing a string using
 * shortcut _L(). Those POT files are created using xgettext like this:
 *
 *     cd <your-TXF-app-folder>
 *     mkdir -p locale
 *     find -name "*.php" -o -name "*.phpt" | xgettext -f - -L PHP -k_L -k_L:1,2 --from-code=utf-8 -o locale/<TXF app>.pot
 *
 * If you didn't explicitly configure any l10n domain the following code is
 * ready to use:
 *
 *     find -name "*.php" -o -name "*.phpt" | xgettext -f - -L PHP -k_L -k_L:1,2 --from-code=utf-8 -o locale/"$(basename "$(pwd)")".pot
 *
 * Either command is parsing all script and templates files of your TXF-based
 * application for use of _L().
 *
 * The resulting <somename>>.pot is a template for creating <somename>>.mo to be
 * installed in your application's locale folder later.
 *
 * ## Generic Translations of TXF
 *
 * TXF is using second dictionary/domain for all uses of _L() in code of TXF
 * itself. THis second domain is integrated as a fallback to your application's
 * dictionary.
 *
 * Use the following code in case of extracting catalogue for TXL-generic code:
 *
 *     cd <parent-of-txf>
 *     find txf -name "*.php" -o -name "*.phpt"  | xgettext -f - -L PHP -k_L -k_L:1,2 --from-code=utf-8 -o txf/extensions/i18n/locale/txf.pot
 *
 * The resulting txf.pot is a template for creating txf.mo to be installed in
 * extension's locale folder later.
 *
 * # Translating
 *
 * Result POT-files are used to start translations for either locale to be
 * supported. Try using tools like poedit for this (http://poedit.net).
 * Translation results in PO- and MO-files. The former are sources of actual
 * translation. The latter are used by gettext for looking up translations. Thus
 * you must install the latter files at least.
 *
 * # Installing Translations
 *
 * Any translated file must be put into locale folder. This locale folder is
 * either subfolder of your application in case of application-specific
 * translations. In case of generic translations for TXF it's the subfolder
 * locale in this extension's folder.
 *
 * In either case you need to create special subfolders inside that locale
 * folder:
 *
 * 1. Create subfolder for translation's locale, e.g. "de_DE". Ensure to have a
 *    translation for default locale configured in locale.language. Use shorter
 *    locales for more common namings, such as "de" for any flavour of German.
 * 2. Create another subfolder inside that locale's folder called "LC_MESSAGES".
 * 3. Put translated file into that LC_MESSAGES folder. Rename it to <your app>.mo,
 *    default.mo or <whatever is configured in locale.domain>.mo.
 *
 * The resulting pathname would be
 *
 *     <web-root>/<your-app>/locale/de_DE/LC_MESSAGES/<your-app>.mo
 *
 * or
 *
 *     <web-root>/txf/extensions/i18n/locale/de_DE/LC_MESSAGES/txf.mo
 *
 * ## Notes on Ubuntu
 *
 * 1. Install locale in system first
 *        sudo apt-get install language-pack-de-base
 * 2. Get list of locales
 *        locale -a
 * 3. Ensure to use locale listed there, e.g. de_DE.utf8, for naming folders and
 *    choosing locale in configuration as well declaring translation locale in
 *    PO file!
 *
 */


class locale extends singleton
{

	/**
	 * ID of currently selected locale
	 *
	 * @var object
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
		$language     = config::get( 'locale.language', 'en_US.utf8' );
		$this->domain = config::get( 'locale.domain', TXF_APPLICATION );


		// choose locale according to request/context/available translations
		$projectPath = config::get( 'locale.path', path::glue( TXF_APPLICATION_PATH, 'locale' ) );
		$txfPath     = path::glue( dirname( __DIR__ ), 'locale' );

		$bestMatch = static::chooseAcceptedLocale( $language, $this->domain, $projectPath, $txfPath );
		if ( $bestMatch )
			$this->language = $bestMatch;
		else
			$this->language = static::parseLocale( $language );


		$localeTag = $this->language->original;

		putenv( 'LANGUAGE=' . $localeTag );
		putenv( 'LANG=' . $localeTag );
		putenv( 'LC_ALL=' . $localeTag );

		if ( !setlocale( LC_ALL, $localeTag ) )
			log::error( 'could not select locale %s', $localeTag );

		if ( \extension_loaded( 'gettext' ) )
		{
			// bind configured domain to configured path containing l10n files
			if ( static::hasTranslation( $projectPath, $localeTag, $this->domain ) ) {
				bindtextdomain( $this->domain, $projectPath );
				textdomain( $this->domain );
				bind_textdomain_codeset( $this->domain, 'UTF-8' );
			}

			if ( $this->domain !== 'txf' ) {
				// bind domain "txf" to l10n path included with current extension
				if ( static::hasTranslation( $txfPath, $localeTag, 'txf' ) ) {
					bindtextdomain( 'txf', $txfPath );
					bind_textdomain_codeset( 'txf', 'UTF-8' );
				}
			}
		}

		self::$collectionMode = config::get( 'locale.collect.mode', '' );
		self::$collectionFile = config::get( 'locale.collect.file' );
	}

	public function __get( $name ) {
		switch ( $name ) {
			case "locale" :
			case "language" :
				return $this->language;
		}
	}

	/**
	 * Parses provided string for contained locale tag returning found and
	 * normalized pieces as object properties `locale`, `region`, `encoding` and
	 * the originally provided string in property `original`. If given string is
	 * omitting either piece of locale tag the related property is set `null`.
	 *
	 * @param string $locale
	 * @return null|object
	 */
	public static function parseLocale( $locale ) {
		if ( preg_match( '/^([a-z]+)(?:[-_]([a-z]+))?(?:\.([a-z0-9_-]+))?$/i', $locale, $matches ) ) {
			return (object) array(
				'original' => $locale,
				'locale'   => strtolower( $matches[1] ),
				'region'   => $matches[2] ? strtolower( $matches[2] ) : null,
				'encoding' => $matches[3] ? strtolower( strtr( $matches[3], array( '-' => '', '_' => '' ) ) ) : null,
			);
		}

		return null;
	}

	/**
	 * Lists parsed locale descriptors according to folders found in provided
	 * path.
	 *
	 * @param string $path pathname of folder to contain l10ns
	 * @return object[] parsed locale descriptors
	 */
	protected static function findAvailable( $path ) {
		$availables = array();

		$dir = opendir( $path );
		while ( ( $folder = readdir( $dir ) ) !== false ) {
			$found = static::parseLocale( $folder );
			if ( $found )
				$availables[] = $found;
		}
		closedir( $dir );

		return $availables;
	}

	protected static function hasTranslation( $path, $locale, $domain ) {
		if ( file_exists( path::glue( $path, $locale, 'LC_MESSAGES', $domain . '.mo' ) ) ) {
			return $locale;
		}

		$available = static::findAvailable( $path );
		$requested = static::parseLocale( $locale );

		return static::findMatching( $available, $requested, function( $locale ) use ( $path, $domain ) {
			return file_exists( path::glue( $path, $locale->original, 'LC_MESSAGES', $domain . '.mo' ) );
		} );
	}

	public static function findMatching( $availables, $requested, $customTest ) {
		if ( $requested ) {
			foreach ( $availables as $available ) {
				$isMatching = static::isMatching( $requested, $available, $customTest );
				if ( $isMatching )
					return $isMatching;
			}
		}

		return false;
	}

	/**
	 * Returns locale in $toBeChecked if it basically matching locale given in
	 * $toBeComparedWith.
	 *
	 * @param object $toBeChecked locale descriptor (as returned by static::parseLocale())
	 * @param object $toBeComparedWith locale descriptor (as returned by static::parseLocale())
	 * @param callable $customTest function invoked with $toBeChecked to eventually decide if basically matching locale shall be considered matching
	 * @return object|null value of $toBeChecked on matching, null otherwise
	 */
	protected static function isMatching( $toBeChecked, $toBeComparedWith, $customTest = null ) {
		if ( $toBeChecked && $toBeComparedWith )
			if ( $toBeChecked->locale == $toBeComparedWith->locale )
				if ( is_null( $toBeChecked->region ) || is_null( $toBeComparedWith->region ) || $toBeChecked->region == $toBeComparedWith->region )
					if ( is_null( $toBeChecked->encoding ) || is_null( $toBeComparedWith->encoding ) || $toBeChecked->encoding == $toBeComparedWith->encoding )
						if ( !$customTest || call_user_func( $customTest, $toBeChecked ) )
							return $toBeChecked;

		return null;
	}

	/**
	 * Selects locale to use obeying explicit requests in query or by coolkie as
	 * well as preferences sent by browser regarding accepted language.
	 *
	 * @param string $defaultLocale configured default locale
	 * @param string $domain name of gettext domain to use for texting if related translation is available or not
	 * @returns object finally accepted locale to use (parsed)
	 */
	protected static function chooseAcceptedLocale( $defaultLocale, $domain, $projectPath, $txfPath ) {
		$save = false;

		// select list of locales to test
		$locale = input::vget( 'locale' );
		if ( $locale ) {
			$save = true;
		} else {
			if ( $_COOKIE['locale'] ) {
				$locale = $_COOKIE['locale'];
				$save   = true;
			} else {
				$locale = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
			}
		}

		$locales = preg_split( '/[,;]/', $locale );


		$availables = $found = null;

		foreach ( $locales as $requested ) {
			$requested = self::parseLocale( $requested );
			if ( !$requested )
				continue;

			if ( file_exists( path::glue( $projectPath, $requested->original, 'LC_MESSAGES', $domain . '.mo' ) ) ) {
				$found = $requested;
				break;
			}

			if ( !$availables ) {
				$availables = static::findAvailable( $projectPath );
				foreach ( static::findAvailable( $txfPath ) as $txfLocale ) {
					$found = false;
					foreach ( $availables as $locale )
						if ( static::isMatching( $txfLocale, $locale ) ) {
							$found = true;
							break;
						}

					if ( !$found )
						$availables[] = $locale;
				}
			}

			$found = false;
			foreach ( $availables as $available ) {
				$found = self::isMatching( $available, $requested );
				if ( $found )
					break;
			}

			if ( $found )
				break;
		}

		if ( $found ) {
			if ( $save ) {
				setcookie( 'locale', $found->original, time() + 365 * 86400, '/' . application::current()->prefixPathname, $_SERVER['HTTP_HOST'] );
			}

			return $found;
		}

		setcookie( 'locale', '', time() - 86400, '/' . application::current()->prefixPathname, $_SERVER['HTTP_HOST'] );

		return static::parseLocale( $defaultLocale );
	}

	/**
	 * Commonly handles cases of failing to translate string.
	 *
	 * This is either due to missing gettext extension or to missing requested
	 * translation in dictionary.
	 *
	 * @internal
	 * @return string lookup string or fallback matching singular/plural case according to given count value
	 */

	protected static function onMissingGettext( $singular, $plural, $count = 1, $fallbackSingular = null, $fallbackPlural = null ) {
		// missing i18n/l10n support
		// -> provide matching fallback or lookup preferring the former over the latter
		return ( $count == 1 ) ?
			( $fallbackSingular !== null ? $fallbackSingular : $singular )
			:
			( $fallbackPlural !== null ? $fallbackPlural : $plural );
	}

	/**
	 * Commonly processes result of looking l10n dictionary for translation
	 * matching lookup string.
	 *
	 * @internal
	 * @return string translated string or key/fallback if translation failed
	 */

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

		// look for matching translation in default domain (l10n of current app)
		$translated = ngettext( $singular, $plural, $count );

		// try l10n of TXF as fallback on missing translation in dictionary of app
		$isTranslated = ( $translated !== ( $count == 1 ? $singular : $plural ) );
		if ( !$isTranslated && static::current()->getDomain() !== 'txf' ) {
			return static::domainGet( 'txf', $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
		}

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

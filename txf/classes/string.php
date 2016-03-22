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
 * Implements managed strings API for transparently working with strings in
 * different encodings including multi-byte encodings.
 *
 */


class string
{
	/**
	 * mark on whether using mbstring or not
	 *
	 * @var boolean
	 */

	protected static $mbMode;

	/**
	 * internally used encoding detection order to use
	 *
	 * @var string
	 */

	protected static $encodingDetectOrder;

	/**
	 * set of encodings using single byte per character
	 *
	 * This is used for detecting multibyte character sets on missing support
	 * for extension mbstring.
	 *
	 * @var array
	 */

	protected static $singleByteWidthEncodings = array();

	/**
	 * internally managed native string
	 *
	 * @var string
	 */

	protected $data;

	/**
	 * encoding used on managing string
	 *
	 * @var string
	 */

	protected $encoding;


	/**
	 * Initializes use of managed strings, thus should be called once, only.
	 *
	 */

	public static function init()
	{
		self::$mbMode = \extension_loaded( 'mbstring' );

//		self::$encodingDetectOrder = 'utf-8,iso-8859-15';

		if ( !self::$mbMode )
			self::$singleByteWidthEncodings = array(
					'ASCII', '7BIT', '8BIT',
					'CP866', 'IBM866',
					'CP936', 'CP950',
					'CP1251', 'WINDOWS-1251',
					'CP1252', 'WINDOWS-1252',
					'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4',
					'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8',
					'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13', 'ISO-8859-14',
					'ISO-8859-15',
					);
	}


	/**
	 * @param string $string string to manage
	 * @param string $encoding encoding to use on managing
	 */

	public function __construct( $string, $encoding = null )
	{
		if ( $string instanceof self )
		{
			$this->data     = $string->data;
			$this->encoding = $string->encoding;
		}
		else
		{
			$this->data     = strval( $string );
			$this->encoding = is_null( $encoding ) ? null : strtoupper( trim( $encoding ) );

			if ( is_null( $this->encoding ) && self::$mbMode )
			{
				if ( self::$encodingDetectOrder )
					$encoding = \mb_detect_encoding( $this->data, self::$encodingDetectOrder );
				else
					$encoding = \mb_detect_encoding( $this->data );

				if ( $encoding )
					$this->encoding = strtoupper( trim( $encoding ) );
			}

			if ( !is_null( $this->encoding ) && !self::$mbMode )
				if ( !in_array( $this->encoding, self::$singleByteWidthEncodings ) )
					\trigger_error( sprintf( 'Use of characterset/encoding %s requires missing mbstring extension.', $this->encoding ), \E_USER_WARNING );
		}
	}


	/**
	 * Tests if provided value is a native or managed string.
	 *
	 * @param mixed $value value to test
	 * @return boolean true on providing native or managed string, false otherwise
	 */

	public static function isString( $value )
	{
		return is_string( $value ) || ( $value instanceof self );
	}

	/**
	 * Detects if internally used encoding matches provided one.
	 *
	 * @param string $encoding encoding identifier, such as "UTF-8"
	 * @return boolean true on internal string encoded in selected encoding
	 */

	public function isEncoding( $encoding )
	{
		return !strcasecmp( $this->encoding, trim( $encoding ) );
	}

	/**
	 * Retrieves current encoding detection order used by class.
	 *
	 * On providing $newOrder this new new detection order is used further on
	 * and previous detection order is returned. Empty string might be used
	 * and returned to indicate use of detection order defined in PHP
	 * configuration,
	 *
	 * @param string $newOrder new detection order to use
	 * @return string current/previous detection order
	 */

	public static function encodingDetectionOrder( $newOrder = null )
	{
		$currentOrder = self::$encodingDetectOrder;

		if ( !is_null( $newOrder ) )
		{
			if ( trim( $newOrder ) === '' )
				self::$encodingDetectOrder = null;
			else if ( preg_match( '/^([a-z][a-z0-9-]+\s*,\s*)*[a-z][a-z0-9-]+$/i', $newOrder ) )
				self::$encodingDetectOrder = $newOrder;
			else
				throw new \InvalidArgumentException( 'invalid encoding detection order' );
		}

		return strval( $currentOrder );
	}

	/**
	 * Wraps provided native string in a managed string instance.
	 *
	 * On providing managed string instance it's cloned, thus ignoring provided
	 * $encding.
	 *
	 * @param string $string native or managed string to wrap/clone
	 * @param string $encoding encoding of provided string, ignored on cloning
	 * @return string managed string instance
	 */

	public static function wrap( $string, $encoding = null, $useIfNotAString = null )
	{
		if ( static::isString( $useIfNotAString ) )
			if ( !static::isString( $string ) )
				$string = $useIfNotAString;

		return ( $string instanceof self ) ? clone $string
										   : new static( $string, $encoding );
	}

	/**
	 * Recursively wraps elements in provided array in instances of class
	 * string.
	 *
	 * @param array $array
	 * @param string $encoding optional encoding to use on all managed string
	 * @return array
	 */

	public static function wrapArray( $array, $encoding = null )
	{
		assert( 'is_array( $array )' );

		foreach ( $array as $key => $value )
			$array[$key] = is_array( $value ) ? static::wrapArray( $value, $encoding )
											  : static::wrap( $value, $encoding );

		return $array;
	}

	/**
	 * Converts internal value to native string.
	 *
	 * @return string
	 */

	public function __toString()
	{
		return $this->data;
	}

	/**
	 * Aids data::describe() in describing this string instance.
	 *
	 * @return array type description and string value as separate elements
	 */

	public function __describe()
	{
		return array( 'string[' . $this->length() . '/' . $this->byteCount() . ']', $this->convertTo( 'utf-8' ) );
	}

	/**
	 * Provides getter functionality on some protected and virtual properties.
	 *
	 * @param string $name name of property to read
	 * @return mixed
	 */

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'length' :
				return $this->length();

			case 'bytes' :
				return $this->byteCount();

			case 'encoding' :
				return $this->encoding;

			case 'string' :
				return $this->data;

			case 'isEmpty' :
				return ( $this->length() == 0 );

			default :
				if ( preg_match( '/^as(_?[A-Z0-9][a-z0-9]*)+$/', trim( $name ) ) )
				{
					// convert camel-case to dash-separated notation with
					// underscore supporting in separating groups of digits from
					// each other
					$encoding = substr( $name, 2 );
					$encoding = preg_replace( '/[A-Z]|\d+/', '-\0', $encoding );
					$encoding = str_replace( '_', '', $encoding );
					$encoding = ltrim( $encoding, '-' );

					return $this->convertTo( $encoding )->data;
				}
		}
	}

	/**
	 * Retrieves number of characters in string.
	 *
	 * @return integer
	 */

	public function length()
	{
		return self::$mbMode ? \mb_strlen( $this->data, $this->encoding )
							 : \strlen( $this->data );
	}

	/**
	 * Retrieves number of bytes used by string.
	 *
	 * @return integer
	 */

	public function byteCount()
	{
		return \strlen( $this->data );
	}

	/**
	 * Converts internal string to desired encoding.
	 *
	 * @param string $encoding desired encoding of string
	 * @return string
	 */

	public function convertTo( $encoding )
	{
		if ( !self::$mbMode )
		{
			\trigger_error( 'Converting encodings is not supported due to missing extension mbstring.', E_USER_WARNING );
			return clone $this;
		}

		if ( $this->encoding && ( $this->encoding == $encoding ) )
			return clone $this;

		return new static( \mb_convert_encoding( $this->data, $encoding, $this->encoding ), $encoding );
	}

	/**
	 * Compares managed and provided string case-sensitively.
	 *
	 * @param string $counterpart
	 * @return integer =0 on match, <0 on managed literally less than provided
	 *                  string, >0 on managed literally more than provided one
	 */

	public function compare( string $counterpart )
	{
		return strcmp( (string) $this->convertTo( 'utf-8' ), (string) $counterpart->convertTo( 'utf-8' ) );
	}

	/**
	 * Compares managed and provided string case-insensitively.
	 *
	 * @param string $counterpart
	 * @return integer =0 on match, <0 on managed literally less than provided
	 *                  string, >0 on managed literally more than provided one
	 */

	public function compareNoCase( string $counterpart )
	{
		return strcmp( (string) $this->toLower()->convertTo( 'utf-8' ), (string) $counterpart->toLower()->convertTo( 'utf-8' ) );
	}

	/**
	 * Extracts substring from current string.
	 *
	 * @param integer $offset
	 * @param integer $length
	 * @return string
	 */

	public function substr( $offset, $length = null )
	{
		if ( is_null( $length ) )
			$length = strlen( $this->data );

		$extract = self::$mbMode ? \mb_substr( $this->data, $offset, $length, $this->encoding )
								 : \substr( $this->data, $offset, $length );

		return new static( $extract, $this->encoding );
	}

	/**
	 * Searches for pattern returning index of first match or false if not
	 * found.
	 *
	 * @param string $pattern pattern to search
	 * @param integer $offset number of characters to skip before searching
	 * @return string|false
	 */

	public function indexOf( string $pattern, $offset = null )
	{
		if ( !$pattern->length )
			return false;

		if ( $offset > $this->length )
			return false;

		return self::$mbMode ? \mb_strpos( $this->data, $pattern->convertTo( $this->encoding ), $offset, $this->encoding )
							 : \strpos( $this->data, (string) $pattern, $offset );
	}

	/**
	 * Searches case-insensitively for pattern returning index of first match or
	 * false if not found.
	 *
	 * @param string $pattern pattern to search
	 * @param integer $offset number of characters to skip before searching
	 * @return string|false
	 */

	public function indexOfNoCase( string $pattern, $offset = null )
	{
		if ( !$pattern->length )
			return false;

		if ( $offset > $this->length )
			return false;

		return self::$mbMode ? \mb_stripos( $this->data, $pattern->convertTo( $this->encoding ), $offset, $this->encoding )
							 : \stripos( $this->data, (string) $pattern, $offset );
	}

	protected function _lastIndexOf( string $pattern, $offset = null, $caseSensitive = true )
	{
		if ( !$pattern->length )
			return false;

		if ( abs( $offset ) > $this->length )
			return false;

		if ( $offset > 0 )
			$haystack = self::$mbMode ? \mb_substr( $this->data, 0, -$offset, $this->encoding )
									  : substr( $this->data, 0, -$offset );
		else if ( $offset < 0 )
			$haystack = self::$mbMode ? \mb_substr( $this->data, -$offset, $this->encoding )
									  : substr( $this->data, -$offset );
		else
			$haystack = $this->data;

		if ( $caseSensitive )
			$index = self::$mbMode ? \mb_strrpos( $haystack, $pattern->convertTo( $this->encoding ), 0, $this->encoding )
								   : \strrpos( $haystack, (string) $pattern );
		else
			$index = self::$mbMode ? \mb_strripos( $haystack, $pattern->convertTo( $this->encoding ), 0, $this->encoding )
								   : \strripos( $haystack, (string) $pattern );

		if ( $index !== false )
			if ( $offset < 0 )
				$index += -$offset;

		return $index;
	}

	/**
	 * Searches for pattern returning index of last match or false if not found.
	 *
	 * This method is behaving differently from strrpos() and mb_strrpos() in
	 * that provided offset is basically related to end of string.
	 *
	 * @param string $pattern pattern to search
	 * @param integer $offset number of characters to skip before searching
	 * @return string|false
	 */

	public function lastIndexOf( string $pattern, $offset = null )
	{
		return $this->_lastIndexOf( $pattern, $offset, true );
	}

	/**
	 * Searches case-insensitively for pattern returning index of last match or
	 * false if not found.
	 *
	 * This method is behaving differently from strripos() and mb_strripos() in
	 * that provided offset is basically related to end of string.
	 *
	 * @param string $pattern pattern to search
	 * @param integer $offset number of characters to skip before searching
	 * @return string|false
	 */

	public function lastIndexOfNoCase( string $pattern, $offset = null )
	{
		return $this->_lastIndex( $pattern, $offset, false );
	}

	/**
	 * Parses provided query (as given in URL on GET request or in body of a
	 * POST request) for strings with encoding-awareness.
	 *
	 * @param string $urlEncodedQuery set of ampersand-separated assignments
	 * @return array hash with each element representing one of the assigments
	 */

	public static function parseString( $urlEncodedQuery )
	{
		self::$mbMode ? \mb_parse_str( $urlEncodedQuery, $array )
					  : \parse_str( $urlEncodedQuery, $array );

		return static::wrapArray( $array );
	}

	/**
	 * Converts string to contain lowercase letters, only.
	 *
	 * This method is returning another instance of class string.
	 *
	 * @return string converted string
	 */

	public function toLower()
	{
		return new static( self::$mbMode ? \mb_strtolower( $this->data, $this->encoding )
										 : \strtolower( $this->data ), $this->encoding );
	}

	/**
	 * Converts string to contain uppercase letters, only.
	 *
	 * This method is returning another instance of class string.
	 *
	 * @return string converted string
	 */

	public function toUpper()
	{
		return new static( self::$mbMode ? \mb_strtoupper( $this->data, $this->encoding )
										 : \strtoupper( $this->data ), $this->encoding );
	}

	/**
	 * Trims string on either end.
	 *
	 * @param string $chars set of characters to remove at both ends of string
	 * @return string trimmed string
	 */

	public function trim( string $chars = null )
	{
		$chars = is_null( $chars ) ? " \r\t\n\f" : _S($chars)->asUtf8;
		return static::wrap( trim( $this->asUtf8, $chars ), 'utf-8'  )->convertTo( $this->encoding );
	}

	/**
	 * Trims string at its beginning.
	 *
	 * @param string $chars set of characters to remove at beginning of string
	 * @return string trimmed string
	 */

	public function ltrim( string $chars = null )
	{
		$chars = is_null( $chars ) ? " \r\t\n\f" : _S($chars)->asUtf8;
		return static::wrap( ltrim( $this->asUtf8, $chars ), 'utf-8'  )->convertTo( $this->encoding );
	}

	/**
	 * Trims string at its end.
	 *
	 * @param string $chars set of characters to remove at end of string
	 * @return string trimmed string
	 */

	public function rtrim( string $chars = null )
	{
		$chars = is_null( $chars ) ? " \r\t\n\f" : _S($chars)->asUtf8;
		return static::wrap( rtrim( $this->asUtf8, $chars ), 'utf-8'  )->convertTo( $this->encoding );
	}

	/**
	 * Trims provided string so it fits in requested number of characters.
	 *
	 * @param integer $count maximum number of characters
	 * @param string $ellipsis indicator string used at end of string on
	 *                          cropping off some data
	 * @return string
	 */

	public function limit( $count, string $ellipsis = null )
	{
		if ( self::$mbMode )
		{
			if ( is_null( $ellipsis ) )
				$ellipsis = static::wrap( "\xe2\x80\xa6", 'utf-8' );

			return new static( \mb_strimwidth( $this->data, 0, $count, $ellipsis->convertTo( $this->encoding ), $this->encoding ), $this->encoding );
		}

		if ( is_null( $ellipsis ) )
			$ellipsis = static::wrap( '...' );

		if ( strlen( $this->data ) > $count )
			return new static( substr( $this->data, 0, max( 0, $count - $ellipsis->length() ) ) . $ellipsis->convertTo( $this->encoding ), $this->encoding );

		return clone $this;
	}

	/**
	 * Splits string into substrings of at most $size characters each.
	 *
	 * This is imitating str_split().
	 *
	 * @param string $size number of maximum character per chunk
	 * @return array set of strings each containing at most $size characters
	 */

	public function chunked( $size )
	{
		$out = array();

		for ( $i = 0; $i < $this->length(); $i += $size )
			$out[] = new static( $this->substr( $i, $size ), $this->encoding );

		return $out;
	}

	/**
	 * Replaces occurrences of substring.
	 *
	 * This method tries to imitate behaviour of str_replace(). Though the
	 * latter is already safe for utf-8, managed strings may use different
	 * encodings as well.
	 * As another convenience option $pattern may contain hash while
	 * $replacement is null so mapping in hash is used
	 *
	 * @param string|array $pattern single (or set of) substring(s) to replace
	 * @param string|array $replacement single (or set of) substring(s)
	 *                      replacing related substring(s) in $pattern
	 * @return string string with replacements applied
	 */

	public function replace( $pattern, $replacement = null )
	{
		// using str_replace requires to use all strings encoded in UTF-8
		if ( is_array( $pattern ) && ( is_array( $replacement ) || is_null( $replacement ) ) )
		{
			// normalize either array
			if ( is_null( $replacement ) )
			{
				$usingHash   = set::isHash( $pattern );

				$replacement = static::wrapArray( $usingHash ? array_values( $pattern ) : array() );
				$pattern     = static::wrapArray( $usingHash ? array_keys( $pattern ) : $pattern );
			}
			else
			{
				$replacement = static::wrapArray( $replacement );
				$pattern     = static::wrapArray( $pattern );
			}

			// convert elements in either array to UTF-8 encoding
			foreach ( $pattern as $key => $value )
				$pattern[$key] = $value->asUtf8;

			foreach ( $replacement as $key => $value )
				$replacement[$key] = $value->asUtf8;

			// ensure both arrays containing same number of elements
			if ( count( $replacement ) > count( $pattern ) )
				$replacement = array_slice( $replacement, 0, count( $pattern ) );
			else
				$replacement = array_pad( $replacement, count( $pattern ), '' );
		}
		else if ( static::isString( $pattern ) && ( static::isString( $replacement ) || is_null( $replacement ) ) )
		{
			$pattern     = static::wrap( $pattern )->asUtf8;
			$replacement = static::wrap( $replacement )->asUtf8;
		}
		else
			throw new \InvalidArgumentException( 'provide two strings or 2 arrays' );

		// convert subject of replacement to UTF-8 as well
		$subject = $this->asUtf8;

		// perform str_replace
		$result = str_replace( $pattern, $replacement, $subject );

		// finally convert result back from UTF-8 encoding to current one
		return static::wrap( $result, 'utf-8' )->convertTo( $this->encoding );
	}

	/**
	 * Maps selected set of characters or substrings in current string
	 * into some other entity or substring each.
	 *
	 * This method tries to imitate strtr().
	 *
	 * @param string|array $original original entities to translate
	 * @param string $translated translated entities
	 * @return string managed string with content adjusted
	 */

	public function translate( $original, $translated = null )
	{
		if ( static::isString( $original ) && static::isString( $translated ) )
		{
			$original   = static::wrap( $original )->chunked( 1 );
			$translated = static::wrap( $translated )->chunked( 1 );
		}

		return $this->replace( $original, $translated );
	}

	/**
	 * Splits internally managed string into chunks using provided pattern
	 * for selecting separators.
	 *
	 * @param string $pcre PCRE pattern used to split managed string
	 * @return array set of chunks
	 */

	public function split( string $pcre )
	{
		$result = preg_split( $pcre->asUtf8 . 'u', $this->asUtf8 );

		foreach ( $result as $key => $chunk )
			$result[$key] = static::wrap( $chunk, 'utf-8' )->convertTo( $this->encoding );

		return $result;
	}

	/**
	 * Checks provided PCRE pattern for matching internally managed string.
	 *
	 * @param string $pcre PCRE pattern to test on managed string
	 * @return array set of matching subpatterns, false on mismatch
	 */

	public function match( string $pcre )
	{
		if ( !preg_match( $pcre->asUtf8 . 'u', $this->asUtf8, $matches ) )
			return false;

		foreach ( $matches as $key => $chunk )
			$matches[$key] = static::wrap( $chunk, 'utf-8' )->convertTo( $this->encoding );

		return $matches;
	}
}


string::init();


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


class data
{
	/**
	 * Tests whether provided value can be used as a keyword or not.
	 *
	 * Valid keywords are always evaluating true, so you don't need to use
	 * type-safe check on return for matching false.
	 *
	 * @param mixed $in value to be tested
	 * @return string|false normalized keyword on valid keyword, false otherwise
	 */

	public static function isKeyword( $in )
	{
		if ( !string::isString( $in ) )
			log::debug( 'use of non-string value "%s"', static::describe( $in ) );

		$in = trim( $in );

		if ( preg_match( '/^[a-z_][a-z0-9_]*$/i', $in ) )
			return $in;

		return false;
	}

	/**
	 * Tests whether provided value contains non-empty string value.
	 *
	 * Any non-string is converted to string prior to checking.
	 *
	 * @param mixed $in value to be tested
	 * @return string|false non-empty string value, false otherwise
	 */

	public static function isNonEmptyString( $in )
	{
		if ( !string::isString( $in ) )
			log::debug( 'use of non-string value' );

		$in = trim( $in );

		if ( $in !== '' )
			return $in;

		return false;
	}

	/**
	 * Converts provided argument into array.
	 *
	 * This method is for conveniently supporting mixed arguments in your own
	 * methods. If an argument is expected to be an array of something using
	 * this filter enables your caller to provide single-element arrays as a
	 * scalar.
	 *
	 * Providing null in $value always results in empty array. The same applies
	 * to all other values evaluating as false unless you set $keepFalse true.
	 *
	 * Providing an object instance the returned array contains its properties.
	 *
	 * @example
	 *
	 * Your method is called like method( array( a, b, c ) ) and so might be
	 * invoked with method( array( a ) ).  By using this function inside your
	 * method it's even simpler to call the method with method( a ) then.
	 *
	 * @param mixed $value value to be converted
	 * @param boolean $keepFalse true to have non-empty array on providing false
	 * @return array resulting array
	 */

	public static function asArray( $value, $keepFalse = false )
	{
		if ( is_array( $value ) )
			return $value;

		if ( is_object( $value ) )
			return get_object_vars( $value );

		if ( is_null( $value ) )
			return array();

		if ( !$value && !$keepFalse )
			return array();

		return array( $value );
	}

	/**
	 * Shortens string on exceeding selected count of characters.
	 *
	 * @param string $in string to be shortened on demand
	 * @param integer $length maximum number of characters to keep
	 * @param string $ellipsis ellipsis marker used at end of shortened string
	 * @return string potentially shortened string
	 */

	public static function limitString( $in, $length = 30, $ellipsis = '…' )
	{
		$in = strval( $in );

		if ( mb_strlen( $in ) > $length )
			return substr( $in, 0, max( 0, $length - mb_strlen( $ellipsis ) ) ) . $ellipsis;

		return $in;
	}

	/**
	 * Describes provided value.
	 *
	 * This method is returning string containing provided value extended by its
	 * type and length/size. If value is exceeding length of 30 characters it's
	 * being shortened.
	 *
	 * @param mixed $value value to describe
	 * @param boolean $includeType true to have type included in description
	 * @param boolean $fullSize true to disable shortening of longer values
	 * @return string description of provided value
	 */

	public static function describe( $value, $includeType = true, $fullSize = false )
	{
		$type = gettype( $value );

		switch ( $type )
		{
			case 'string' :
				$type  = 'string[' . strlen( $value ) . ']';
				$value = '"' . ( $fullSize ? $value : string::wrap( $value )->limit( 40 ) ) . '"';
				break;
			case 'boolean' :
				$type  = 'boolean';
				$value = $value ? 'true' : 'false';
				break;
			case 'unknown' :
				$type  = 'unknown';
				$value = '?';
				break;
			case 'array' :
				$type  = 'array[' . count( $value ) . ']';
				$value = '[' . implode( ', ', array_map( array( self, 'describe' ), $value ) ) . ']';
				break;
			case 'object' :
				$type = get_class( $value );

				// can't use is_callable() here due to occasional use of __call() in inspected objects
				$inspector = new \ReflectionObject( $value );
				if ( $inspector->hasMethod( '__describe' ) )
					list( $type, $value ) = $value->__describe();
				else if ( $inspector->hasMethod( '__toString' ) )
					$value = strval( $value );
				else
					$value = '[no-string]';

				if ( !$fullSize )
					$value = string::wrap( $value )->limit( 40 );

				break;
		}

		if ( $includeType )
			return "($type) $value";

		return strval( $value );
	}

	/**
	 * Converts provided value to best matching type according to actual value.
	 *
	 * This method is parsing a provided string for containing an integer,
	 * decimal or special string for marking boolean values. Any non-string
	 * value is passed as is.
	 *
	 * @param mixed $value value to convert optionally
	 * @param string $type name of type to prefer
	 * @return provided or converted value
	 */

	public static function autoType( $value, $type = null )
	{
		if ( string::isString( $value ) )
		{
			$type = strtolower( trim( $type ) );


			if ( $type === 'string' )
				return $value;

			if ( strtolower( trim( $value ) ) == 'null' )
				return null;


			if ( $type === 'integer' || preg_match( '/^[+-]?\d+$/', trim( $value ) ) || $type === 'integer' )
				return intval( $value );


			if ( is_numeric( $value ) )
				return doubleval( $value );

			// due to different locale is_numeric() probably won't detect
			// non-localized decimals
			if ( preg_match( '/^(([+-]?)\d+)(\.(\d+))?$/', trim( $value ), $matches ) )
				return doubleval( $matches[1] ) + "$matches[2]1" * doubleval( $matches[4] ) * pow( 10, -strlen( $matches[4] ) );

			if ( $type === 'double' )
				return doubleval( $value );


			if ( preg_match( '/^(o(ff|n)|true|false|yes|no)$/i', trim( $value ), $matches ) )
				return in_array( strtolower( $matches[1] ), array( 'on', 'yes', 'true' ) );

			if ( $type === 'boolean' )
				return in_array( strtolower( trim( $value ) ), array( 'on', 'yes', 'true' ) );


			if ( preg_match( '/^=\?8bit\?B\?(.+)\?=$/i', $value, $matches ) )
				return static::autotype( unserialize( base64_decode( $matches[1] ) ), $type );
		}

		return $value;
	}

	public static function asAutoTypable( $value )
	{
		if ( is_double( $value ) )
			// get non-localized string version of a double
			return sprintf( '%F', $value );

		if ( is_bool( $value ) )
			return $value ? 'true' : 'false';

		if ( is_null( $value ) )
			return 'null';

		if ( is_scalar( $value ) )
			return strval( $value );

		return '=?8bit?B?' . base64_encode( serialize( $value ) ) . '?=';
	}

	public static function qualifyString( $in, $values = null )
	{
		if ( is_array( $values ) )
			$cb = function( $matches ) use ( $values )
			{
				$chunks = explode( '::', $matches[1] );

				return array_key_exists( $chunks[0], $values ) ? $values[trim( $chunks[0] )] : $matches[1];
			};
		else
			$cb = function( $matches ) use ( $values )
			{
				$chunks = explode( '::', $matches[1] );

				switch( strtolower( trim( $chunks[0] ) ) )
				{
					case 'appdir' :
						return application::current()->pathname;

					case 'appurl' :
						return application::current()->scriptURL( $chunks[1] );

					case 'date' :
						return date( $chunks[1], count( $chunks ) > 2 ? intval( $chunks[2] ) : time() );

					default :
						return '';
				}
			};

		return preg_replace_callback( '/\{\{([^}]+)\}\}/', $cb, $in );
	}
}


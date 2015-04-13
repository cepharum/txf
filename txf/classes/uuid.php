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


class uuid
{
	/**
	 * Creates random UUID as hex string.
	 *
	 * @return string random UUID as hex-string
	 */

	public static function createRandom()
	{
		return sprintf( '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0x0fff ) | 0x4000,
					mt_rand( 0, 0x3fff ) | 0x8000,
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ),
					mt_rand( 0, 0xffff ) );
	}

	/**
	 * Converts hex-string UUID to binary form.
	 *
	 * @throws \InvalidArgumentException on providing invalid hex-string UUID
	 * @param $hex string hex-string UUID to convert
	 * @return string binary UUID
	 */

	public static function hex2binary( $hex )
	{
		if ( !static::isValidHex( $hex ) )
			throw new \InvalidArgumentException( 'not a hex-string UUID' );

		return pack( 'H32', str_replace( '-', '', $hex ) );
	}

	/**
	 * Converts binary UUID to hex-string form.
	 *
	 * @throws \InvalidArgumentException on providing invalid binary UUID
	 * @param $binary string binary UUID to convert
	 * @return string hex-string UUID
	 */

	public static function binary2hex( $binary )
	{
		if ( !static::isValidBinary( $binary ) )
			throw new \InvalidArgumentException( 'not a binary UUID' );

		$data = unpack( 'H32', $binary );
		$hex  = array_shift( $data );

		return substr( $hex, 0, 8 ) . '-' .
	           substr( $hex, 8, 4 ) . '-' .
	           substr( $hex, 12, 4 ) . '-' .
	           substr( $hex, 16, 4 ) . '-' .
	           substr( $hex, 20, 12 );
	}

	/**
	 * Tests if provided value is a valid hex-string UUID.
	 *
	 * @param $uuid string value to test
	 * @return bool true if value is hex-string UUID, false otherwise (e.g. if it's binary UUID)
	 */

	public static function isValidHex( $uuid )
	{
		return !!preg_match( '/^[\xa-f]{8}-[\xa-f]{4}-[\xa-f]{4}-[\xa-f]{4}-[\xa-f]{12}$/i', $uuid );
	}

	/**
	 * Tests if provided value is a valid binary UUID.
	 *
	 * @param $uuid string value to test
	 * @return bool true if value is binary UUID, false otherwise (e.g. if it's hex-string UUID)
	 */

	public static function isValidBinary( $uuid )
	{
		return ( strlen( $uuid ) === 16 );
	}

	/**
	 * Tests if provided value is either binary or hex-string UUID.
	 *
	 * Any provided binary UUID is converted to hex-string form implicitly.
	 *
	 * @param $uuid string value to test, converted to hex-string on return when providing binary UUID
	 * @return bool true if value is a valid UUID, false otherwise
	 */

	public static function isValid( &$uuid )
	{
		if ( static::isValidBinary( $uuid ) )
		{
			$uuid = static::binary2hex( $uuid );
			return true;
		}

		return static::isValidHex( $uuid );
	}

	/**
	 * Detects if two provided UUIDs are equal or not.
	 * 
	 * This method is normalizing either UUID prior to comparing, thus you might give
	 * a mix of binary and hex-encoded UUIDs.
	 * 
	 * @param $a string first UUID to compare with second
	 * @param $b string second UUID to compare with first
	 * @return bool true if both UUIDs have identical value, false otherwise (e.g. if either UUID is invalid)
	 */

	function areEqual( $a, $b ) {
		if ( static::isValid( $a ) && static::isValid( $b ) ) {
			return !strcasecmp( $a, $b );
		}

		return false;
	}
}

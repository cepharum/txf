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


class blowfish
{
	/**
	 * Creates salted hash of provided value.
	 *
	 * @param mixed $value data to be hashed, any non-string gets serialized first
	 * @param string $salt salt to use explicitly, omit for random salt
	 * @return string salted hash of provided data
	 */

	public static function get( $value, $salt = null )
	{
		if ( $salt === null )
			$salt = static::getRandomSalt();

		if ( !is_string( $value ) )
			$value = serialize( $value );

		return crypt( $value, $salt );
	}

	/**
	 * Generates random salt for use with blowfish hashing.
	 *
	 * @return string
	 */
	protected static function getRandomSalt() {
		$alphabet = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

		$iterations = config::get( 'blowfish.iterations', 11 );
		if ( $iterations < 4 || $iterations > 31 )
			throw new \InvalidArgumentException( 'invalid blowfish iteration count in configuration' );

		$out = '$2y$' . sprintf( '%02d', $iterations ) . '$';

		for ( $i = 0; $i < 22; $i++ ) {
			$out .= $alphabet[mt_rand( 0, strlen( $alphabet ) )];
		}

		return $out;
	}

	/**
	 * Extracts salt from provided hash.
	 *
	 * @param string $hash salted hash previously returned from a call to blowfish::get()
	 * @return string salt of provided hash
	 */

	public static function extractSalt( $hash )
	{
		if ( !$hash || !static::isValidHash( $hash ) )
			throw new \InvalidArgumentException( 'invalid hash for extracting salt from' );

		return substr( $hash, 0, 29 );
	}

	/**
	 * Detects if provided hash looks like blowfish hash or not.
	 *
	 * @param string $hash
	 * @return bool
	 */
	public static function isValidHash( $hash ) {
		return !!preg_match( '/^\$2y\$\d\d\$[0-9a-zA-Z]{21}/', $hash );
	}
}

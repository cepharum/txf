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


class ssha
{
	/**
	 * Creates salted hash of provided value.
	 *
	 * @param mixed $value data to be hashed, any non-string gets serialized first
	 * @param string $salt salt to use explicitly, omit for random salt
	 * @return string salted hash of provided data
	 */

	public static function get( $value, $salt = null, $raw = false )
	{
		if ( $salt === null )
			$salt = sha1( mt_rand() );

		if ( !is_string( $value ) )
			$value = serialize( $value );

		$hash = sha1( $value . $salt, true ) . $salt;

		return $raw ? $hash : '{SSHA}' . base64_encode( $hash );
	}

	/**
	 * Extracts salt from provided hash.
	 *
	 * @param string $hash salted hash previously returned from a call to ssha::get()
	 * @return string salt of provided hash
	 */

	public static function extractSalt( $hash )
	{
		if ( substr( $hash, 0, 6 ) === '{SSHA}' )
			return substr( base64_decode( substr( $hash, 6 ) ), 20 );

		return substr( $hash, 20 );
	}
}

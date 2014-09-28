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


class url {

	/**
	 * Detects whether provided URL is relative or not.
	 *
	 * Relative URLs are implicitly file-like URLs as well.
	 *
	 * @param string $url URL to test
	 * @return bool true on $url containing relative URL, false otherwise
	 */

	public static function isRelative( $url ) {
		return !preg_match( '#^\w+:|^/#', strval( $url ) );
	}

	/**
	 * Detects whether provided URL is addressing file-like resource or not.
	 *
	 * Addressing "file-like" resources includes local pathnames, all schemes
	 * addressing remote host name and/or pathname. In opposition, javascript-
	 * and mail-URLs are considered not addressing "file-like" resources.
	 *
	 * @param string $url URL to test
	 * @return bool true on $url obviously addressing file or similar
	 */

	public static function isFile( $url ) {
		return static::isRelative( $url ) || preg_match( '#^\w+:/#', strval( $url ) );
	}

	/**
	 * Resolves concatenated parts of URL pathname as provided.
	 *
	 * @param $first first of multiple pathname fragments
	 * @return string URL pathname referring to
	 */

	public static function resolve( $first ) {
		$in = func_get_args();
		$in = explode( '/', trim( implode( '/', $in ) ) );

		$out = array();
		foreach ( $in as $part )
			switch ( $part )
			{
				case '' :
					if ( !count( $out ) )
						// mark resulting pathname being absolute
						$out[] = '';
				case '.' :
					break;

				case '..' :
					switch ( count( $out ) )
					{
						case 0 :
							array_push( $out, '..' );
							break;
						case 1 :
							if ( $out[0] === '' )
								// resulting path is absolute -> can't descend any further
								throw new \InvalidArgumentException( 'resolving path ascends beyond root' );
						default :
							if ( $out[0] === '..' ) {
								array_push( $out, '..' );
							} else {
								array_pop( $out );
							}
							break;
					}
					break;
				default :
					array_push( $out, $part );
			}

		return implode( '/', $out );
	}
}

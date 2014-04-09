<?php
/**
 * Copyright (c) 2013-2014, cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban <thomas.urban@cepharum.de>
 * @project: Lebenswissen
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

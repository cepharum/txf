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

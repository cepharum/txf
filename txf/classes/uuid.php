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
}

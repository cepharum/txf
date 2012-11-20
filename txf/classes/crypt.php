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
 * Provides API for symmetrically encrypting/decrypting data.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 */


class crypt
{
	/**
	 * handle of mcrypt module to use
	 *
	 * @var resource
	 */

	protected static $module = null;


	/**
	 * Instantiates module of mcrypt to use.
	 *
	 * @return resource handle of opened mcrypt module
	 */

	protected static function getModule()
	{
		if ( self::$module === null )
		{
			self::$module = mcrypt_module_open( MCRYPT_3DES, null, MCRYPT_MODE_CFB, null );
		}

		return self::$module;
	}

	/**
	 * Create initialization vector.
	 *
	 * This vector is considered to be recreatable the very same way resulting
	 * in the same vector, though hardly depending on current user's session to
	 * be less predictable by attackers gathering access on ciphers.
	 *
	 * @return string initialization vector to use
	 */

	protected static function getIV()
	{
		// request key for creating cookie to be included with IV
		$key = static::getKey();

		$iv  = ssha::get( $_SERVER['REMOTE_ADDR'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_HOST'] ) .
			   ssha::get( $_SERVER['HTTP_HOST'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'], $_SERVER['REMOTE_ADDR'] );

		return substr( $iv, 0, mcrypt_enc_get_iv_size( static::getModule() ) );
	}

	/**
	 * Retrieves key and creates one on demand.
	 *
	 * This key might be random but has to be stored in user's cookies to be
	 * available in requests of user's current session, only!
	 *
	 * @return string key to use
	 */

	protected static function getKey()
	{
		if ( !array_key_exists( '_txf', $_COOKIE ) )
		{
			$key = ssha::get( uniqid( mt_rand(), true ) . uniqid( mt_rand(), true ) ) .
				   ssha::get( uniqid( mt_rand(), true ) . uniqid( mt_rand(), true ) );

			\setcookie( '_txf', base64_encode( $key ) );
			$_COOKIE['_txf'] = base64_encode( $key );
		}

		return substr( base64_decode( $_COOKIE['_txf'] ), 0, mcrypt_enc_get_key_size( static::getModule() ) );
	}

	/**
	 * Encrypts provided cleartext message.
	 *
	 * crypt::encrypt() and crypt::decrypt() transparently work on systems
	 * missing mcrypt support by always passing provided cleartext.
	 *
	 * @param string $cleartext arbitrary string of data to encrypt
	 * @return string cipher or provided cleartext (on missing mcrypt support)
	 */

	public static function encrypt( $cleartext )
	{
		// pass back cleartext if mcrypt is missing
		if ( !is_callable( 'mcrypt_module_open' ) )
		{
			log::warning( 'missing mcrypt for actually encrypting data, keeping it cleartext' );
			return $cleartext;
		}


		// encrypt provided cleartext prefixed by hash for checking integrity on decryption
		$module = static::getModule();

		mcrypt_generic_init( $module, static::getKey(), static::getIV() );

		$cipher = mcrypt_generic( $module, sha1( $cleartext, true ) . $cleartext );

		mcrypt_generic_deinit( $module );


		// return cipher properly tagged to be detected as such on decryption
		return 'TXF!CIPH' . $cipher;
	}

	/**
	 * Decrypts cipher encrypted by crypt::encrypt() previously.
	 *
	 * Encrypted cipher is decryptable in current session, only.
	 *
	 * crypt::encrypt() and crypt::decrypt() transparently work on systems
	 * missing mcrypt support by always passing provided cleartext.
	 *
	 * @throws \RuntimeException raised on missing mcrypt
	 * @throws \InvalidArgumentException raised on decrypting failed
	 * @param string $cipher encrypted message
	 * @return string decrypted message
	 */

	public static function decrypt( $cipher )
	{
		// test for tag on cipher and pass back provided cipher as is if tag is missing
		if ( substr( $cipher, 0, 8 ) !== 'TXF!CIPH' )
		{
			log::warning( 'actually not decrypting since cipher is not properly encrypted' );
			return $cipher;
		}

		if ( !is_callable( 'mcrypt_module_open' ) )
			throw new \RuntimeException( 'missing mcrypt' );


		// actually decrypt provided cipher
		$module = static::getModule();

		mcrypt_generic_init( $module, static::getKey(), static::getIV() );

		$decrypted = mdecrypt_generic( $module, substr( $cipher, 8 ) );

		mcrypt_generic_deinit( $module );


		// check integrity of decrypted message
		$cleartext = substr( $decrypted, 20 );
		$hash      = substr( $decrypted, 0, 20 );

		if ( sha1( $cleartext, true ) !== $hash )
			throw new \InvalidArgumentException( 'decryption failed' );


		return $cleartext;
	}
}

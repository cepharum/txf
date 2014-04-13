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
 * @project: Cepharum.Config
 */

namespace de\toxa\txf;


/**
 * Implements hash generator e.g. for authorizing user to invoke some selected
 * action.
 *
 * @package de\toxa\txf
 */

class hash_generator
{
	/**
	 * Normalizes provided timestamp to describe date, only.
	 *
	 * @param int $timestamp
	 * @return int unique timestamp of provided timestamp's day
	 */

	protected static function _date( $timestamp )
	{
		return mktime( 12, 0, 0, idate( 'm', $timestamp ), idate( 'd', $timestamp ), idate( 'Y', $timestamp ) );
	}

	/**
	 * Retrieves hash on an object descriptor and its related secret information
	 * to be valid at date of timestamp.
	 *
	 * @param string $object some public object hash is to be used for (e.g. a user's name)
	 * @param string $secret some internal-only information related to that object (e.g. the user's internal ID or similar)
	 * @param int $timestamp timestamp of day generated hash is considered valid on
	 * @return string hash on object and its related secret valid for day of given timestamp
	 */

	protected static function _get( $object, $secret, $timestamp )
	{
		$salt = ssha::get( $object, 'cePharUm-S3cr3t-54lT' . static::_date( $timestamp ), true );
		$hash = substr( preg_replace( '#[/+=]#', '', ssha::get( $secret, $salt ) ), 12, 16 );

		return $hash;
	}

	/**
	 * Retrieves hash on an object descriptor and its related secret information.
	 *
	 * @param string $object some public object hash is to be used for (e.g. a user's name)
	 * @param string $secret some internal-only information related to that object (e.g. the user's internal ID or similar)
	 * @return string
	 * @throws \InvalidArgumentException on missing/invalid object or secret
	 */

	public static function get( $object, $secret )
	{
		$object = trim( data::asString( $object ) );
		$secret = trim( data::asString( $secret ) );

		if ( $object === '' || $secret === '' )
			throw new \InvalidArgumentException( 'missing/invalid hashing data' );

		return static::_get( $object, $secret, time() );
	}

	/**
	 * Checks if provided hash for given object and its secret has been valid
	 * selected days before at most.
	 *
	 * @param string $object some public object hash is to be used for (e.g. a user's name)
	 * @param string $secret some internal-only information related to that object (e.g. the user's internal ID or similar)
	 * @param string $hash hash to be validated
	 * @param int $maxAgeInDays maximum number of days to go back from today
	 * @return bool true on valid hash, false otherwise
	 */

	public static function isValid( $object, $secret, $hash, $maxAgeInDays = 3 )
	{
		for ( $i = 0; $i <= $maxAgeInDays; $i++ )
			if ( $hash === static::_get( $object, $secret, time() - $i * 86400 ) )
				return true;

		return false;
	}

	/**
	 * Retrieves up-to-date hash on provided user.
	 *
	 * The provided action name is an arbitrary string used to get distinct
	 * hashes for some particular action hash will be used with.
	 *
	 * This method is using provided user's login name as object, so it's okay
	 * to embed user's login name in conjunction with generated hash
	 *
	 * @param user $user user to generate hash for
	 * @param string $action name of associated action to be validated by hash
	 * @return string hash authorizing user to invoke selected action
	 */

	public static function getOnUser( user $user, $action )
	{
		return static::get( $user->getLoginName(), $user->getName() . '@' . $action );
	}

	/**
	 * Checks if hash is validating selected action for given user.
	 *
	 * The provided action name must be the same as on generating hash for
	 * this action.
	 *
	 * @param user $user user to generate hash for
	 * @param string $action name of associated action to be validated by hash
	 * @param string $hash hash to be checked on action for given user
	 * @param int $maxAgeInDays maximum number of days to go back from today
	 * @return bool true on hash validating user's action, false otherwise
	 */

	public static function isValidForUser( user $user, $action, $hash, $maxAgeInDays = 3 )
	{
		$object = $user->getLoginName();
		$secret = $user->getName() . '@' . $action;

		return static::isValid( $object, $secret, $hash, $maxAgeInDays );
	}

	/**
	 * Conveniently retrieves hash for user selected by its name.
	 *
	 * @param string $username name of user used on selecting user by user::load()
	 * @param string $action name of associated action to be validated by hash
	 * @return string hash authorizing user to invoke selected action
	 * @throws \InvalidArgumentException on missing/invalid user
	 */

	public static function getOnUsername( $username, $action )
	{
		if ( !is_string( $username ) || ( $username = trim( $username ) ) === '' )
			throw new \InvalidArgumentException( 'missing/invalid username' );

		$user = user::load( $username );
		if ( $user instanceof ldap_user )
			return static::getOnUser( $user, $action );

		throw new \InvalidArgumentException( 'invalid user' );
	}
}

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


abstract class user
{
	protected function __construct();

	/**
	 * Retrieves internal ID of current user.
	 * 
	 * This might be any kind of data, e.g. numeric ID of a Unix system user or
	 * the DN of a user stored in an LDAP tree.
	 * 
	 * @return opaque internal ID of user
	 */

	public function getID();

	/**
	 * Retrieves globally unique ID (GUID) of current user.
	 * 
	 * @return string GUID of user e.g. for storing related data in a database
	 */

	public function getGUID();

	/**
	 * Retrieves user's login name.
	 * 
	 * @return string arbitrary string considered user's login name
	 */

	public function getLoginName();

	/**
	 * Retrieves human-readable name of user.
	 * 
	 * @return string arbitrary string considered human-readable name of user
	 */

	public function getName();

	/**
	 * Retrieves arbitrary property of current user.
	 * 
	 * @param string $propertyName name of property to fetch
	 * @param mixed $defaultIfMissing optional value to return if property isn't set
	 * @return mixed value of selected property, provided default if property is missing
	 */

	public function getProperty( $propertyName, $defaultIfMissing = null );

	/**
	 * Adjusts arbitrary property of current user.
	 * 
	 * @throws \InvalidArgumentException when property isn't supported
	 * @throws \UnexpectedValueException when value doesn't comply with optional constraints
	 * @param string $propertyName name of property to adjust
	 * @param mixed $propertyValue new value of property, null to unset property
	 * @return user current instance for chaining calls
	 */

	public function setProperty( $propertyName, $propertyValue = null );

	/**
	 * Authenticates current user.
	 * 
	 * This method is expected to implement some kind of session-based 
	 * authentication persistence, thus ignoring any provided or missing
	 * credentials if that session-based authentication succeeded internally.
	 * 
	 * @throws unauthorized_exception when authentication fails
	 * @param mixed $credentials arbitrary data required for authenticating user
	 * @return user current instance for chaining calls
	 */

	public function authenticate( $credentials );

	/**
	 * Detects whether user is authenticated or not.
	 * 
	 * @return boolean true if user is authenticated, false otherwise
	 */

	public function isAuthenticated();

	/**
	 * Configures instance for accessing selected source.
	 * 
	 * @param array $configuration properties required for connecting to source
	 * @return boolean true on successfully connecting to source, false otherwise
	 */

	protected function configure( $configuration );

	/**
	 * Searches source for single user and loads it.
	 * 
	 * This method is considered to return if unique user has been found and
	 * loaded from source, only.
	 *
	 * @throws \OutOfBoundException when user isn't found 
	 * @param string $userIdOrLoginName ID or name of user to load
	 */

	protected function search( $userIdOrLoginName );




	/**
	 * Loads selected user either from sources selected in configuration or
	 * from source(s) provided in call explicitly.
	 * 
	 * @throws \OutOfBoundsException when user wasn't found
	 * @param string $userIdOrLoginName ID or login name of user to load
	 * @param string $explicitSource first source to look for selected user
	 * @return user found user
	 */

	final public static function load( $userIdOrLoginName, $explicitSource = null )
	{
		// get list of sources to look up for requested user
		$sources = func_get_args();
		array_shift( $sources );

		if ( empty( $sources ) )
			$sources = config::get( 'user.sources.list' );

		// traverse list of sources ...
		if ( is_array( $sources ) )
			foreach ( $sources as $source )
				if ( trim( $source ) !== '' && ctype_alnum( $source ) )
				{
					// read its configuration
					$definition = config::get( 'user.sources.setup.' . $source );
					if ( is_array( $definition ) )
					{
						// read name of class for managing user source from configuration
						$class = data::isKeyword( $definition['class'] );
						if ( !$class )
							$class = data::isKeyword( $definition['type'] . '_user' );

						// check if selected class exists
						if ( $class && class_exists( $class, true ) )
							try
							{
								// create instance of managing class
								$class = new \ReflectionClass( $class );
								$user = $class->newInstance();

								if ( $user instanceof self )
									// provide setup data for configuring manager
									if ( $user->configure( $definition ) )
									{
										// search user
										$user->search( $userIdOrLoginName );

										// no exception? ... so it's loaded
										return $user;
									}
							}
							catch ( \OutOfBoundsException $e ) {}
					}
				}

		throw new \OutOfBoundsException( 'no such user: ' . $userIdOrLoginName );
	}
}



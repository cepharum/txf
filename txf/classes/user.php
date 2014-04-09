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
 * Abstract implementation of user management providing generic functionality
 * and declaring user management API.
 *
 * This class isn't relying on model API by intention for supporting user
 * management w/o need for model support (e.g. for using reduced-size TXF). Same
 * rule applies to integrating role management: this class prevents hard links
 * to class role for supporting very simple user management not relying on
 * additional classes such as role.
 *
 */

abstract class user
{
	/**
	 * cached reference on instance representing current user
	 *
	 * @var user
	 */

	private static $__current = null;



	abstract protected function __construct();

	/**
	 * Retrieves internal ID of current user.
	 *
	 * This might be any kind of data, e.g. numeric ID of a Unix system user or
	 * the DN of a user stored in an LDAP tree.
	 *
	 * @return opaque internal ID of user
	 */

	abstract public function getID();

	/**
	 * Retrieves globally unique ID (UUID) of current user.
	 *
	 * @return string UUID of user e.g. for storing related data in a database
	 */

	abstract public function getUUID();

	/**
	 * Retrieves user's login name.
	 *
	 * @return string arbitrary string considered user's login name
	 */

	abstract public function getLoginName();

	/**
	 * Retrieves human-readable name of user.
	 *
	 * @return string arbitrary string considered human-readable name of user
	 */

	abstract public function getName();

	/**
	 * Retrieves arbitrary property of current user.
	 *
	 * @param string $propertyName name of property to fetch
	 * @param mixed $defaultIfMissing optional value to return if property isn't set
	 * @return mixed value of selected property, provided default if property is missing
	 */

	abstract public function getProperty( $propertyName, $defaultIfMissing = null );

	/**
	 * Adjusts arbitrary property of current user.
	 *
	 * @throws \InvalidArgumentException when property isn't supported
	 * @throws \UnexpectedValueException when value doesn't comply with optional constraints
	 * @param string $propertyName name of property to adjust
	 * @param mixed $propertyValue new value of property, null to unset property
	 * @return user current instance for chaining calls
	 */

	abstract public function setProperty( $propertyName, $propertyValue = null );

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

	abstract public function authenticate( $credentials );

	/**
	 * Reauthenticates user on restoring current user from session.
	 *
	 * This method is required since website is using multiple runtimes each
	 * requiring current user to reauthenticate.
	 *
	 * @throws unauthorized_exception when authentication fails
	 * @return user current instance for chaining calls
	 */

	abstract public function reauthenticate();

	/**
	 * Detects whether user is authenticated or not.
	 *
	 * @return boolean true if user is authenticated, false otherwise
	 */

	abstract public function isAuthenticated();

	/**
	 * Changes current user's password.
	 *
	 * @param string $newToken new password of user
	 * @throws \RuntimeException
	 */

	public function changePassword( $newToken )
	{
		throw new \RuntimeException( _L('Missing support for changing password of current user.') );
	}

	/**
	 * Checks if user is authorized to act in requested role.
	 *
	 * @note keep this method loosely bound to class role for supporting user
	 *       management not relying on roles.
	 *
	 * @param string|role $role role to check on current user
	 * @param boolean $skipCache true to circumvent any caches used by User API implementors
	 * @return boolean true if user is authorized to act in requested role
	 */

	abstract public function is( $role, $skipCache = false );

	/**
	 * Drops any internally cached mark on user being authenticated.
	 *
	 * This is called e.g. on logging of current user. Since several portions of
	 * code may keep a reference on current user instance, this is required to
	 * actually enforce loss of authenticated state.
	 */

	abstract public function unauthenticate();

	/**
	 * Configures instance for accessing selected source.
	 *
	 * @param array $configuration properties required for connecting to source
	 * @return boolean true on successfully connecting to source, false otherwise
	 */

	abstract protected function configure( $configuration );

	/**
	 * Searches source for single user and loads it.
	 *
	 * This method is considered to return if unique user has been found and
	 * loaded from source, only.
	 *
	 * @throws \OutOfBoundException when user isn't found
	 * @param string $userIdOrLoginName ID or name of user to load
	 * @return user instance of uniquely found user
	 */

	abstract protected function search( $userIdOrLoginName );


	private static function &session()
	{
		return txf::session( config::get( 'user.auth.global' ) ? session::SCOPE_GLOBAL : session::SCOPE_APPLICATION );
	}

	/**
	 * Returns instance representing current user.
	 *
	 * @return user instance representing current user
	 */

	final public static function current()
	{
		if ( self::$__current instanceof self )
			return self::$__current;

		// gain access on persistent session data for fetching any current user
		$session =& self::session();
		if ( array_key_exists( 'user', $session ) && $session['user'] instanceof self )
		{
			try {
				self::$__current = $session['user']->reauthenticate();

				return self::$__current;
			} catch ( \Exception $e ) {
				view::flash( _L('Failed to re-validate current user\'s authentication. Please login again!'), 'error' );
			}

			unset( $session['user'] );
		}

		// provide instance describing guest user on fallback
		return guest_user::getInstance();
	}

	/**
	 * Requests to authorize provided user as current user.
	 *
	 * This requires provision of selected user's credentials used for internal
	 * authentication. Credentials might be omitted unless user instance has not
	 * been authenticated before.
	 *
	 * @throws unauthorized_exception in case of authentication failures
	 * @param user $user instance of user requested to become current one
	 * @param mixed $credentials user's credentials used for authentication
	 */

	final public static function setCurrent( user $user, $credentials = null )
	{
		if ( $user->isAuthenticated() || $user->authenticate( $credentials ) )
		{
			self::$__current = $user;

			// gain access on persistent session data for storing current user
			$session =& self::session();
			$session['user'] = $user;
		}
	}

	/**
	 * Drops current user.
	 *
	 * This method is used to "log off" any current user.
	 */

	final public static function dropCurrent()
	{
		if ( self::$__current instanceof self )
		{
			// enforce drop of user's authenticated state
			self::$__current->unauthenticate();

			// drop reference on current user
			self::$__current = null;
		}

		// gain access on persistent session data for dropping current user's
		// data there as well
		$session =& self::session();
		unset( $session['user'] );
	}

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

		if ( !count( $sources ) )
			$sources = config::getList( 'user.sources.enabled' );

		if ( !count( $sources ) )
			throw new \RuntimeException( 'missing/invalid user sources configuration' );


		// traverse list of sources ...
		foreach ( $sources as $source )
			if ( trim( $source ) !== '' && ctype_alnum( $source ) )
			{
				// read its configuration
				$definition = config::get( 'user.sources.setup.' . $source );
				if ( is_array( $definition ) )
				{
					// read name of class for managing user source from configuration
					$class = array_key_exists( 'class', $definition ) ? data::isKeyword( $definition['class'] ) : null;
					if ( !$class )
						$class = data::isKeyword( $definition['type'] . '_user' );

					if ( txf::import( $class ) )
						try
						{
							// create instance of managing class
							$factory = new \ReflectionClass( $class );
							$user = $factory->newInstance();

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
						catch ( \ErrorException $e )
						{
							log::warning( "failed to search user source: ". $e );
						}
				}
			}

		throw new \OutOfBoundsException( 'no such user: ' . $userIdOrLoginName );
	}
}


/**
 * Implements guest user source.
 *
 * This class is used to provide unauthenticated guest user which e.g. used
 * whenever user management hasn't got authenticated current user available from
 * a different source.
 *
 * @author Thomas Urban
 */

class guest_user extends user
{
	private static $single;

	protected function __construct() {}
	public function getID() { return 0; }
	public function getUUID() { return '00000000-0000-0000-0000-000000000000'; }
	public function getLoginName() { return 'guest'; }
	public function getName() { return _L('guest'); }
	public function getProperty( $propertyName, $defaultIfMissing = null ) { return $defaultIfMissing; }
	public function setProperty( $propertyName, $propertyValue = null ) { throw new \RuntimeException( 'property is read-only' ); }
	public function authenticate( $credentials ) { return $this; }
	public function reauthenticate() { return $this; }
	public function isAuthenticated() { return false; }
	public function is( $role, $skipCache = false ) { return false; }
	public function unauthenticate() {}
	protected function configure( $configuration ) {}
	protected function search( $userIdOrLoginName )
	{
		if ( !$userIdOrLoginName || ( $userIdOrLoginName === 'guest' ) )
			return self::$single;

		throw new \OutOfBoundsException( 'no such user' );
	}

	public static function getInstance() { return self::$single; }
	public static function init() { self::$single = new guest_user(); }
}

guest_user::init();

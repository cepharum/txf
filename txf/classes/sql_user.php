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


class sql_user extends user
{
	/**
	 * unique numeric ID of user's record in database
	 *
	 * @var integer
	 */

	protected $rowID = null;

	/**
	 * cached copy of user's record
	 *
	 * @var array
	 */

	protected $record = null;

	/**
	 * cached mark on whether user is authenticated or not
	 *
	 * @var boolean
	 */

	private $_authenticated = null;

	/**
	 * set of cached datasource connections
	 *
	 * @var array
	 */

	protected static $datasources = array();

	/**
	 * configuration of user's datasource as provided in call to sql_user::configure()
	 *
	 * @var array
	 */

	private $configuration = null;

	/**
	 * encrypted credentials of current user
	 *
	 * @var string
	 */

	private $credentials = null;

	/**
	 * cached roles of current user
	 *
	 * @var array
	 */

	protected $roleCache = array();



	public function __construct() {}

	/**
	 * Retrieves connection to configured datasource containing users database.
	 *
	 * @return datasource\connection connection to datasource
	 */

	protected function datasource()
	{
		if ( !is_array( $this->configuration ) )
			throw new \RuntimeException( _L('Missing user source configuration.') );

		$conf = $this->configuration;
		$hash = sha1( serialize( $conf ) );

		if ( !array_key_exists( $hash, self::$datasources ) )
		{
			// gain access on datasource configured to contain users
			if ( $conf['datasource'] )
				$ds = datasource::selectConfigured( $conf['datasource'] );
			else
				$ds = datasource::selectConfigured( 'default' );

			if ( !( $ds instanceof datasource\pdo ) )
				throw new \UnexpectedValueException( _L('Unsupported kind of datasource for managing users.') );

			// create dataset in datasource on demand
			if ( !$ds->createDataset( $conf['set'], array(
						$this->colname( 'uuid' )      => 'CHAR(36) NOT NULL',
						$this->colname( 'loginname' ) => 'CHAR(64) NOT NULL',
						$this->colname( 'password' )  => 'CHAR(128) NOT NULL',
						$this->colname( 'name' )      => 'CHAR(128)',
						) ) )
				throw $ds->exception( _L('failed to created dataset for managing users') );

			// ensure to have a single user at least by default
			$count = $ds->cell( sprintf( 'SELECT COUNT(*) FROM %s', $ds->quoteName( $conf['set'] ) ) );
			if ( trim( $count ) === '0' )
			{
				$ctx = $this;

				$ds->transaction()->wrap( function( $conn ) use ( $ctx, $conf )
				{
					if ( !$conn->test( sprintf( 'INSERT INTO %s (id,%s,%s,%s,%s) VALUES (?,?,?,?,?)',
							$conn->quoteName( $conf['set'] ), $ctx->colname( 'uuid' ),
							$ctx->colname( 'loginname' ), $ctx->colname( 'password' ),
							$ctx->colname( 'name' ) ),
							$conn->nextID ( $conf['set'] ), uuid::createRandom(),
							'admin', ssha::get( 'nimda' ),
							_L('Admin User') ) )
						throw $conn->exception( _L('failed to create default user') );

					return true;
				} );
			}

			self::$datasources[$hash] = $ds;
		}

		return self::$datasources[$hash];
	}

	public function colname( $original )
	{
		if ( array_key_exists( 'properties', $this->configuration ) && array_key_exists( $original, $this->configuration['properties'] ) )
			return $this->configuration['properties'][$original];

		return $original;
	}

	/**
	 * Retrieves record of user selected by ID unless cached before.
	 *
	 * @return array record describing single user
	 */

	protected function getRecord()
	{
		assert( '$this->rowID' );

		if ( !is_array( $this->record ) )
		{
			$ds = $this->datasource();

			$this->record = array();

			// translate property names according to configuration
			foreach ( $ds->row( sprintf( 'SELECT * FROM %s WHERE id=?', $ds->quoteName( $this->configuration['set'] ) ), $this->rowID ) as $name => $value )
				$this->record[$this->colname( $name )] = $value;
		}

		return $this->record;
	}

	/**
	 * Retrieves internal ID of current user.
	 *
	 * This might be any kind of data, e.g. numeric ID of a Unix system user or
	 * the DN of a user stored in an LDAP tree.
	 *
	 * @return opaque internal ID of user
	 */

	public function getID()
	{
		return $this->rowID;
	}

	/**
	 * Retrieves universally unique ID (UUID) of current user.
	 *
	 * @return string UUID of user e.g. for storing related data in a database
	 */

	public function getUUID()
	{
		$uuid = $this->getProperty( 'uuid' );
		if ( strlen( trim( $uuid ) ) < 36 )
		{
			$uuid = uuid::createRandom();

			$this->setProperty( 'uuid', $uuid );
		}

		return $uuid;
	}

	/**
	 * Retrieves user's login name.
	 *
	 * @return string arbitrary string considered user's login name
	 */

	public function getLoginName()
	{
		return $this->getProperty( 'loginname' );
	}

	/**
	 * Retrieves human-readable name of user.
	 *
	 * @return string arbitrary string considered human-readable name of user
	 */

	public function getName()
	{
		return $this->getProperty( 'name' );
	}

	/**
	 * Retrieves arbitrary property of current user.
	 *
	 * @param string $propertyName name of property to fetch
	 * @param mixed $defaultIfMissing optional value to return if property isn't set
	 * @return mixed value of selected property, provided default if property is missing
	 */

	public function getProperty( $propertyName, $defaultIfMissing = null )
	{
		$record = $this->getRecord();

		if ( array_key_exists( $propertyName, $record ) && !is_null( $record[$propertyName] ) )
			return $record[$propertyName];

		return $defaultIfMissing;
	}

	/**
	 * Adjusts arbitrary property of current user.
	 *
	 * @throws \InvalidArgumentException when property isn't supported
	 * @throws \UnexpectedValueException when value doesn't comply with optional constraints
	 * @param string $propertyName name of property to adjust
	 * @param mixed $propertyValue new value of property, null to unset property
	 * @return user current instance for chaining calls
	 */

	public function setProperty( $propertyName, $propertyValue = null )
	{
		$record = $this->getRecord();

		// ensure selected property is part of current user's record
		if ( array_key_exists( $propertyName, $record ) )
			// ensure selected property isn't marked read-only
			if ( !in_array( $propertyName, array( 'id' ) ) )
			{
				// update property in datasource
				$ds = static::datasource();

				if ( $propertyName == 'password' )
					$propertyValue = ssha::get( $propertyValue );

				$ds->query( sprintf( 'UPDATE %s SET %s=? WHERE id=?', $ds->quoteName( $this->configuration['set'] ), $ds->quoteName( $this->colname( $propertyName ) ) ), $propertyValue, $this->rowID );

				// update value in cached copy of record as well
				$this->record[$propertyName] = $propertyValue;
			}

		return $this;
	}



	/**
	 * Encrypts provided credentials in property of current instance.
	 *
	 * @param string $credentials password used on authentication
	 */

	private function saveCredentials( $credentials )
	{
		$this->credentials = crypt::create( function()
		{
			return ssha::get( $_COOKIE['_txf'] . $_SERVER['REMOTE_ADDR'] . $_COOKIE['_txf'] . $_SERVER['HTTP_HOST'], md5( $_SERVER['HTTP_USER_AGENT'] ) ) .
				   ssha::get( $_SERVER['HTTP_HOST'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['_txf'], md5( $_SERVER['REMOTE_ADDR'] ) );
		} )->encrypt( $credentials );
	}

	/**
	 * Decrypts credentials previously stored in property of current instance.
	 *
	 * @return string password previously used on authentication
	 */

	private function getCredentials()
	{
		return crypt::create( function()
		{
			return ssha::get( $_COOKIE['_txf'] . $_SERVER['REMOTE_ADDR'] . $_COOKIE['_txf'] . $_SERVER['HTTP_HOST'], md5( $_SERVER['HTTP_USER_AGENT'] ) ) .
				   ssha::get( $_SERVER['HTTP_HOST'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['_txf'], md5( $_SERVER['REMOTE_ADDR'] ) );
		} )->decrypt( $this->credentials );
	}

	public function unauthenticate()
	{
		// drop all data associated with previously authenticated user
		$this->rowID = $this->credentials = $this->record = null;
	}

	public function reauthenticate()
	{
		exception::enterSensitive();

		$record = $this->getRecord();
		$token  = @$record['password'];

		if ( $token && $this->credentials && ssha::get( $this->getCredentials(), ssha::extractSalt( $token ) ) !== $token )
			throw new unauthorized_exception( 'invalid/missing credentials' );

		exception::leaveSensitive();

		// reset any previously cached copy of user's node
		$this->record = null;

		return $this;
	}

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

	public function authenticate( $credentials )
	{
		if ( $this->isAuthenticated() )
			return $this;


		exception::enterSensitive();

		$record = $this->getRecord();
		$token  = @$record['password'];

		if ( $credentials && $token )
		{
			if ( ssha::get( $credentials, ssha::extractSalt( $token ) ) === $token )
			{
				$this->_authenticated = true;

				// store credentials in session
				$this->saveCredentials( $credentials );

				// reset any previously cached copy of user's node
				$this->record = null;
			}
		}

		exception::leaveSensitive();


		if ( $this->_authenticated )
			return $this;

		throw new unauthorized_exception( _L('invalid/missing password.') );
	}

	/**
	 * Detects whether user is authenticated or not.
	 *
	 * @return boolean true if user is authenticated, false otherwise
	 */

	public function isAuthenticated()
	{
		if ( is_null( $this->_authenticated ) && $this->credentials )
			$this->reauthenticate();

		return !!$this->_authenticated;
	}

	/**
	 * Checks if user is authorized to act in requested role.
	 *
	 * @param string|role $role role to check on current user
	 * @param boolean $skipCache true to ignore cached results of previous tests
	 * @return boolean true if user is authorized to act in requested role
	 */

	public function is( $role, $skipCache = false )
	{
		$roleName = trim( $role instanceof role ? $role->name : is_string( $role ) ? $role : '' );
		if ( !$roleName )
			throw new \InvalidArgumentException( 'invalid role' );

//		if ( $skipCache || !array_key_exists( $roleName, $this->roleCache ) )
			$this->roleCache[$roleName] = sql_role::select( $this->datasource(), $role )->isAdoptedByUser( $this );

		return $this->roleCache[$roleName];
	}

	/**
	 * Configures instance for accessing selected source.
	 *
	 * @param array $configuration properties required for connecting to source
	 * @return boolean true on successfully connecting to source, false otherwise
	 */

	protected function configure( $configuration )
	{
		if ( is_array( $configuration ) && count( $configuration ) && !is_array( $this->configuration ) )
		{
			if ( !array_key_exists( 'set', $configuration ) )
				$configuration['set'] = 'users';

//			if ( !array_key_exists( 'datasource', $configuration ) )
//				$configuration['datasource'] = 'users';

			$this->configuration = $configuration;
		}

		return !!$this->datasource();
	}

	/**
	 * Searches source for single user and loads it.
	 *
	 * This method is considered to return if unique user has been found and
	 * loaded from source, only.
	 *
	 * @throws \OutOfBoundException when user isn't found
	 * @param string $u{serIdOrLoginName ID or name of user to load
	 */

	protected function search( $userIdOrLoginName )
	{
		$property = ctype_digit( trim( $userIdOrLoginName ) ) ? 'id' : $this->colname( 'loginname' );

		$ds = $this->datasource();

		$query = sprintf( 'SELECT id FROM %s WHERE %s=?', $ds->quoteName( $this->configuration['set'] ), $ds->quoteName( $property ) );

		$matches = $ds->allNumeric( $query, $userIdOrLoginName );
		if ( $matches && count( $matches ) === 1 && ctype_digit( trim( $matches[0][0] ) ) )
		{
			$this->rowID = intval( $matches[0][0] );

			return $this;
		}

		throw new \OutOfBoundsException( 'no such user' );
	}
}

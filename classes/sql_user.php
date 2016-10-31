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


use de\toxa\txf\datasource\datasource_exception;

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
	 * Retrieves name of set of users in datasource.
	 *
	 * @return string
	 */

	public function datasourceSet() {
		if ( !is_array( $this->configuration ) )
			throw new \RuntimeException( _L('Missing user source configuration.') );

		$conf = $this->configuration;

		return $conf['set'];
	}

	/**
	 * Retrieves names of properties to use on accessing datasource.
	 *
	 * Retrieving names this way obeys any configured mapping to fit special
	 * datasource.
	 *
	 * You might provide additional property names to look up in further
	 * arguments to this method. Method is returning map of internal names into
	 * mapped ones then.
	 *
	 * @param string $internalName
	 * @return string|array mapped name of single given property, map of mapped property names on providing multiple
	 */

	public function datasourcePropertyName( $internalName ) {
		$names = array_unique( func_get_args() );
		$names = array_combine( $names, $names );
		$names = name_mapping::map( $names, 'txf.sql_user' );

		$names = array_flip( $names );

		switch ( count( $names ) ) {
			case 0 :
				return null;
			case 1 :
				return array_shift( $names );
			default :
				return $names;
		}
	}

	/**
	 * Retrieves connection to configured datasource containing users database.
	 *
	 * @throws \Exception
	 * @return datasource\connection connection to datasource
	 */

	public function datasource()
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

			// apply optionally configured mapping of a user's properties
			$definition = array(
				'uuid'      => 'CHAR(36) NOT NULL',
				'loginname' => 'CHAR(64) NOT NULL',
				'password'  => 'CHAR(128) NOT NULL',
				'name'      => 'CHAR(128)',
				'lock'      => 'CHAR(128)',
				'email'     => 'CHAR(128)',
			);

			$mappedDefinition = name_mapping::map( $definition, 'txf.sql_user' );

			// create data set in datasource on demand
			if ( !$ds->createDataset( $conf['set'], $mappedDefinition ) )
				throw $ds->exception( _L('failed to create dataset for managing users') );

			// ensure to have a single user at least by default
			if ( !intval( $ds->createQuery( $conf['set'] )->execute( true )->cell() ) )
			{
				$record = name_mapping::map( array(
					'uuid'      => uuid::createRandom(),
					'loginname' => 'admin',
					'password'  => blowfish::get( 'nimda' ),
					'name'      => _L('Administrator'),
					'lock'      => '',
					'email'     => '',
				), 'txf.sql_user' );

				$currentUser = $this;

				$ds->transaction()->wrap( function( datasource\connection $conn ) use ( $record, $conf, $currentUser )
				{
					$names   = array_map( function( $n ) use ( $conn ) { return $conn->quoteName( $n ); }, array_keys( $record ) );
					$markers = array_map( function() { return '?'; }, $record );

					$newUserID = $conn->nextID( $conf['set'] );

					$values = array_values( $record );
					array_unshift( $values, $newUserID );

					$sql = sprintf( 'INSERT INTO %s (id,%s) VALUES (?,%s)',
									$conn->qualifyDatasetName( $conf['set'] ),
									implode( ',', $names ),
									implode( ',', $markers ) );

					if ( !$conn->test( $sql, $values ) )
						throw $conn->exception( _L('failed to create default user') );

					// load created user for adopting administrator role
					sql_role::select( $conn, 'administrator' )->makeAdoptedBy( user::load( $newUserID ) );

					return true;
				} );
			}

			self::$datasources[$hash] = $ds;
		}

		return self::$datasources[$hash];
	}

	/**
	 * Creates new user record.
	 *
	 * @param string[] $properties set of properties (incl. loginname, password, name, email, lock)
	 * @return int|null created user's ID or null on unexpected error
	 * @throws \InvalidArgumentException on missing selected required properties (loginname, password)
	 * @throws datasource_exception on user existing in datasource already or on failing to add new record
	 */

	public function create( $properties ) {

		// prepare properties to be written on creating new record in datasource
		$record  = array();
		$mapping = $this->datasourcePropertyName( 'uuid', 'loginname', 'password', 'name', 'lock', 'email' );

		foreach ( $mapping as $old => $new ) {
			switch ( $old ) {
				case 'uuid' :
					$record[$new] = uuid::createRandom();
					break;
				case 'password' :
					$record[$new] = blowfish::get( $properties[$old] );
					break;
				case 'loginname' :
					$record[$new] = substr( trim( $properties[$old] ), 0, 64 );
					break;
				default :
					$record[$new] = substr( trim( $properties[$old] ), 0, 128 );
					break;
			}
		}

		// validate normalized properties
		if ( !$record['loginname'] )
			throw new \InvalidArgumentException( _L('Creating user without login name rejected.') );

		if ( trim( $properties['password'] ) === '' )
			throw new \InvalidArgumentException( _L('Creating user without password rejected.') );


		// create new record unless found record matching login name (thus using transaction)
		$conf      = $this->configuration;
		$newUserID = null;

		return $this->datasource()->transaction()->wrap( function( datasource\connection $conn ) use ( $record, $conf, $mapping, &$newUserID ) {

			$dataSet = $conn->qualifyDatasetName( $conf['set'] );


			// test if user with same login name exists or not
			$sql = sprintf( 'SELECT id FROM %s WHERE %s=?', $dataSet,
			                $conn->quoteName( $mapping['loginname'] ) );

			if ( $conn->cell( $sql, $record['loginname'] ) )
				throw $conn->exception( _L( 'Selected user exists already.' ) );


			// create new record using provided properties
			$names   = array_map( function ( $n ) use ( $conn ) { return $conn->quoteName( $n ); }, array_keys( $record ) );
			$markers = array_map( function () { return '?'; }, $record );

			$newUserID = $conn->nextID( $conf['set'] );

			$values = array_values( $record );
			array_unshift( $values, $newUserID );

			$sql = sprintf( 'INSERT INTO %s (id,%s) VALUES (?,%s)', $dataSet,
			                implode( ',', $names ),
			                implode( ',', $markers ) );

			if ( !$conn->test( $sql, $values ) )
				throw $conn->exception( _L( 'Creating new user in datasource failed.' ) );


			return true;
		} ) ? $this->search( $newUserID ) : null;
	}

	/**
	 * Deletes user from datasource.
	 *
	 * @throws datasource_exception on failed deleting user
	 */

	public function delete() {
		assert( '$this->rowID' );

		$conn = $this->datasource();
		$conf = $this->configuration;

		if ( !$conn->test( sprintf( 'DELETE FROM %s WHERE %s=?',
		                      $conn->qualifyDatasetName( $conf['set'] ),
		                      $conn->quoteName( name_mapping::mapSingle( 'id', 'txf.sql_user' ) ) ),
		             $this->rowID ) )
			throw new datasource_exception( $conn, _L('Deleting user in datasource failed.') );


		$this->rowID = null;
		$this->record = null;
	}

	/**
	 * Retrieves record of user selected by ID unless cached before.
	 *
	 * @throws unauthorized_exception on having lost user's record (e.g. due to externally modified datasource)
	 * @return array record describing single user
	 */

	protected function getRecord()
	{
		assert( '$this->rowID' );

		if ( !is_array( $this->record ) )
		{
			$ds = $this->datasource();

			$this->record = array();

			$record = $ds->createQuery( $this->configuration['set'] )
						->addCondition( 'id=?', true, $this->rowID )
						->execute()->row();

			if ( !is_array( $record ) || !count( $record ) ) {
				throw new unauthorized_exception( 'lost user in data source' );
			}

			// translate property names according to configuration
			$this->record = name_mapping::mapReversely( $record, 'txf.sql_user' );
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
					$propertyValue = blowfish::get( $propertyValue );

				// update value in cached copy of record
				$this->record[$propertyName] = $propertyValue;

				// update value in data source
				$propertyName = name_mapping::mapSingle( $propertyName, 'txf.sql_user' );
				if ( !is_null( $propertyName ) )
				{
					$sql = sprintf( 'UPDATE %s SET %s=? WHERE id=?',
								$ds->qualifyDatasetName( $this->configuration['set'] ),
								$ds->quoteName( $propertyName ) );

					if ( !$ds->test( $sql, $propertyValue, $this->rowID ) )
						throw new \RuntimeException( 'failed to store updated property' );
				}
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
			return blowfish::get( $_COOKIE['_txf'] . $_SERVER['REMOTE_ADDR'] . $_COOKIE['_txf'] . $_SERVER['HTTP_HOST'], md5( $_SERVER['HTTP_USER_AGENT'] ) ) .
			       blowfish::get( $_SERVER['HTTP_HOST'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['_txf'], md5( $_SERVER['REMOTE_ADDR'] ) );
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
			return blowfish::get( $_COOKIE['_txf'] . $_SERVER['REMOTE_ADDR'] . $_COOKIE['_txf'] . $_SERVER['HTTP_HOST'], md5( $_SERVER['HTTP_USER_AGENT'] ) ) .
			       blowfish::get( $_SERVER['HTTP_HOST'] . $_COOKIE['_txf'] . $_SERVER['HTTP_USER_AGENT'] . $_COOKIE['_txf'], md5( $_SERVER['REMOTE_ADDR'] ) );
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

		if ( $token && $this->credentials ) {
			$credentials = $this->getCredentials();

			if ( blowfish::isValidHash( $token ) )
				$hash = blowfish::get( $credentials, blowfish::extractSalt( $token ) );
			else if ( ssha::isValidHash( $token ) )
				$hash = ssha::get( $credentials, ssha::extractSalt( $token ) );
			else
				throw new \RuntimeException( "unknown hashing on user's token" );

			if ( $hash !== $token )
				throw new unauthorized_exception( 'invalid/missing credentials', unauthorized_exception::REAUTHENTICATE, $this );
		}

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
			if ( blowfish::isValidHash( $token ) )
				$hash = blowfish::get( $credentials, blowfish::extractSalt( $token ) );
			else if ( ssha::isValidHash( $token ) )
				$hash = ssha::get( $credentials, ssha::extractSalt( $token ) );
			else
				throw new \RuntimeException( "unknown hashing on user's token" );

			if ( $hash === $token )
			{
				if ( trim( $record['lock'] ) !== '' )
					throw new unauthorized_exception( _L('account is locked'), unauthorized_exception::ACCOUNT_LOCKED, $this );

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

		throw new unauthorized_exception( _L('invalid/missing password.'), unauthorized_exception::TOKEN_MISMATCH, $this );
	}

	/**
	 * Detects whether user is authenticated or not.
	 *
	 * @return boolean true if user is authenticated, false otherwise
	 */

	public function isAuthenticated()
	{
		try
		{
			if ( is_null( $this->_authenticated ) && $this->credentials )
				$this->reauthenticate();

			return !!$this->_authenticated;
		}
		catch ( \Exception $e )
		{
			return ( $this->_authenticated = false );
		}
	}

	public function changePassword( $newToken )
	{
		exception::enterSensitive();

		if ( preg_match( '/\s/', $newToken ) || strlen( $newToken ) < 8 || strlen( $newToken ) > 16 )
			throw new \InvalidArgumentException( 'invalid password' );

		$db   = $this->datasource();
		$conf = $this->configuration;

		$sql  = sprintf( 'UPDATE %s SET %s=? WHERE %s=?',
					$db->qualifyDatasetName( $conf['set'] ),
					$db->quoteName( name_mapping::mapSingle( 'password', 'txf.sql_user' ) ),
					$db->quoteName( name_mapping::mapSingle( 'id', 'txf.sql_user' ) )
					);

		if ( $db->test( $sql, blowfish::get( $newToken ), $this->getID() ) )
		{
			$this->saveCredentials( $newToken );

			$this->record = null;
		}

		exception::leaveSensitive();

		return true;
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

		if ( $skipCache || !array_key_exists( $roleName, $this->roleCache ) )
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
				$configuration['set'] = 'user';

			if ( !array_key_exists( 'datasource', $configuration ) )
				$configuration['datasource'] = 'users';

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
	 * @param string $userIdOrLoginName ID or name of user to load
	 * @return \de\toxa\txf\user
	 * @throws unauthorized_exception
	 */

	protected function search( $userIdOrLoginName )
	{
		$property = ctype_digit( trim( $userIdOrLoginName ) ) ? 'id' : name_mapping::mapSingle( 'loginname', 'txf.sql_user' );

		$matches = $this->datasource()
						->createQuery( $this->configuration['set'] )
						->addCondition( $property . '=?', true, $userIdOrLoginName )
						->addProperty( 'id' )
						->limit( 2 )
						->execute();

		switch ( $matches->count() )
		{
			case 0 :
				throw new unauthorized_exception( 'ambiguous user selection', unauthorized_exception::USER_NOT_FOUND );
			case 1 :
				$this->rowID = intval( $matches->cell() );
				return $this;
			default :
				throw new unauthorized_exception( 'no such user', unauthorized_exception::USER_NOT_FOUND );
		}
	}
}

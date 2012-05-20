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



	protected function __construct() {}

	/**
	 * Retrieves connection to configured datasource containing users database.
	 * 
	 * @return datasource\connection connection to datasource
	 */

	protected function datasource()
	{
		if ( !is_array( $this->configuration ) )
			throw new \RuntimeException( 'missing user source configuration' );

		$hash = hash( serialize( $this->configuration ) );

		if ( !array_key_exists( $hash, self::$datasources ) )
		{
			$ds = call_user_func( array( 'datasource', $this->configuration['datasource'] ) );
			if ( $ds )
				$ds->createDataset( $this->configuration['set'], array(
						'id'        => 'INTEGER PRIMARY KEY',
						'uuid'      => 'CHAR(36) NOT NULL',
						'loginname' => 'CHAR(64) NOT NULL',
						'password'  => 'CHAR(64) NOT NULL',
						'name'      => 'CHAR(128)',
						) );

			self::$datasources[$hash] = $ds;
		}


		return self::$datasources[$hash];
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

			$this->record = $ds->row( sprintf( 'SELECT * FROM %s WHERE id=?', $ds->quoteName( $this->configuration['set'] ) ), $this->rowID );
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

				$ds->query( sprintf( 'UPDATE %s SET %s=? WHERE id=?', $ds->quoteName( $this->configuration['set'] ), $ds->quoteName( $propertyName ) ), $propertyValue, $this->rowID );

				// update value in cached copy of record as well
				$this->record[$propertyName] = $propertyValue;
			}

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
		if ( !$this->isAuthenticated() )
		{
			$record = $this->getRecord();

			if ( $credentials && @$record['password'] )
			{
				if ( ssha::get( $credentials, ssha::extractSalt( $record['password'] ) ) === $record['password'] )
				{
					$this->_authenticated = true;

					return $this;
				}
			}

			throw new unauthorized_exception( 'invalid password' );
		}
	}

	/**
	 * Detects whether user is authenticated or not.
	 *
	 * @return boolean true if user is authenticated, false otherwise
	 */

	public function isAuthenticated()
	{
		if ( is_null( $this->_authenticated ) )
		{
			// check for permanent 
		}

		return !!$this->_authenticated;
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
	 * @throws \OutOfBoundException when user isn't found
	 * @param string $u{serIdOrLoginName ID or name of user to load
	 */

	protected function search( $userIdOrLoginName )
	{
		$property = ctype_digit( trim( $userIdOrLoginName ) ) ? 'id' : 'loginname';

		$ds = $this->datasource();

		$matches = $ds->allNumeric( sprintf( 'SELECT id FROM %s WHERE %s=?', $ds->quoteName( $this->configuration['set'] ), $ds->quoteName( $property ) ), $userIdOrLoginName );
		if ( $matches && count( $matches ) === 1 && ctype_digit( trim( $matches[0][0] ) ) )
		{
			$this->rowID = intval( $matches[0][0] );

			return $this;
		}

		throw new \OutOfBoundsException( 'no such user' );
	}
}

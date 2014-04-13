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

use de\toxa\txf\datasource\ldap as LDAP;


/**
 * Supports LDAP-backed user database.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 */

class ldap_user extends user
{
	/**
	 * setup as provided in call to ldap_user::configure()
	 *
	 * @var set
	 */

	protected $setup;

	/**
	 * established connection to LDAP server
	 *
	 * @var LDAP\server
	 */

	protected $server;

	/**
	 * DN of managed user
	 *
	 * @var string
	 */

	protected $userDN;

	/**
	 * cached LDAP node of currently managed user
	 *
	 * @var LDAP\node
	 */

	protected $userNode = null;

	/**
	 * encrypted credentials of current user
	 *
	 * @var string
	 */

	private $credentials = null;



	public function __construct() {}

	protected function readNode( $attributes = null )
	{
		if ( $attributes !== null )
			return $this->server->read( $this->userDN, $attributes );

		if ( !$this->userNode )
			$this->userNode = $this->server->read( $this->userDN );

		return $this->userNode;
	}

	public function getID()
	{
		return $this->readNode()->attributeByName( $this->setup->read( 'idAttr', 'uidNumber' ) )->read( 0 );
	}

	public function getUUID()
	{
		return $this->readNode( 'entryUUID' )->attributeByName( $this->setup->read( 'uuidAttr', 'entryUUID' ) )->read( 0 );
	}

	public function getLoginName()
	{
		return $this->readNode()->attributeByName( $this->setup->read( 'loginAttr', 'uid' ) )->read( 0 );
	}

	public function getName()
	{
		try {
			$name = $this->readNode()->attributeByName( $this->setup->read( 'nameAttr', 'cn' ) )->read( 0 );
		} catch ( \Exception $e ) {
			$name = '';
		}

		if ( trim( $name ) !== '' )
			return $name;


		// if missing attribute CN use value of first RDN in user's DN instead
		$rdns = ldap_explode_dn( $this->userDN, 0 );
		return $rdns[0];
	}

	public function getProperty( $propertyName, $defaultIfMissing = null )
	{
		return $this->readNode()->attributeByName( $propertyName )->read( 0 );
	}

	public function setProperty( $propertyName, $propertyValue = null )
	{
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

	public function reauthenticate()
	{
		exception::enterSensitive();

		// rebind using credentials stored in session
		if ( !$this->bindAs( $this->userDN, $this->getCredentials() ) )
			throw new unauthorized_exception( 'invalid/missing credentials', unauthorized_exception::REAUTHENTICATE, $this );

		exception::leaveSensitive();

		// reset any previously cached copy of user's node
		$this->userNode = null;

		return $this;
	}

	public function authenticate( $credentials )
	{
		if ( !$this->bindAs( $this->userDN, $credentials ) )
			throw new unauthorized_exception( 'invalid/missing credentials', unauthorized_exception::TOKEN_MISMATCH, $this );

		// store credentials in session
		$this->saveCredentials( $credentials );

		// reset any previously cached copy of user's node
		$this->userNode = null;

		return $this;
	}

	public function isAuthenticated()
	{
		return ( $this->server->getBoundAs() === $this->userDN );
	}

	public function is( $role, $skipCache = false )
	{
		// @todo implement some role checking
		return false;
	}

	public function unauthenticate()
	{
		// drop all data associated with previously authenticated user
		$this->userDN = $this->credentials = $this->userNode = null;
	}

	protected function bindAs( $dn, $password )
	{
		try
		{
			if ( $this->setup->saslMech && $this->setup->saslRealm )
				$this->server->bindAs( $dn, $password, $this->setup->saslMech, $this->setup->saslRealm );
			else
			{
				$this->server->simpleBindAs( $dn, $password );
			}

			return $this->server->isBound();
		}
		catch ( \Exception $e )
		{
			return false;
		}
	}

	protected function configure( $configuration )
	{
		$this->setup = new set( $configuration );

		$this->server = new LDAP\server( $this->setup->read( 'server', 'ldapi:///' ), $this->setup->basedn );

		if ( data::autoType( $this->setup->tls, 'boolean' ) )
			$this->server->startTls();

		if ( $this->setup->binddn )
			if ( !$this->bindAs( $this->setup->binddn, $this->setup->bindpw ) )
				throw new LDAP\protocol_exception( 'failed to bind', $this->server );

		return true;
	}

	protected function search( $userIdOrLoginName )
	{
		if ( mail::isValidAddress( $userIdOrLoginName ) )
		{
			$match = $this->server->searchSub( sprintf( $this->setup->read( 'searchByMail', '(mailPublic=%s)' ), $userIdOrLoginName ), $this->setup->basedn );
		}
		else if ( strpos( $userIdOrLoginName, '=' ) !== false )
		{
			$match = $this->server->searchBase( $userIdOrLoginName );
		}
		else if ( ctype_digit( $userIdOrLoginName ) )
		{
			$match = $this->server->searchSub( sprintf( $this->setup->read( 'searchById', '(uidNumber=%d)' ), $userIdOrLoginName ), $this->setup->basedn );
		}
		else if ( uuid::isValid( $userIdOrLoginName ) )
		{
			$match = $this->server->searchSub( sprintf( $this->setup->read( 'searchByUuid', '(entryUUID=%s)' ), $userIdOrLoginName ), $this->setup->basedn );
		}
		else
		{
			$match = $this->server->searchSub( sprintf( $this->setup->read( 'searchByLogin', '(uid=%s)' ), $userIdOrLoginName ), $this->setup->basedn );
		}

		if ( $match->current() )
		{
			$this->userDN = $match->current()->getDN();

			return $this;
		}

		throw new unauthorized_exception( 'no such user', unauthorized_exception::USER_NOT_FOUND );
	}

	/**
	 * Retrieves datasource containing current user's entry.
	 *
	 * @return LDAP\server link to datasource (LDAP server) containing current user's entry
	 * @throws \RuntimeException
	 */

	public function getDatasource() {
		if ( $this->server instanceof LDAP\server ) {
			return $this->server;
		}

		throw new \RuntimeException( 'request for unconfigured link to datasource' );
	}

	/**
	 * Retrieves LDAP DN of current user.
	 *
	 * @return string
	 * @throws \RuntimeException
	 */

	public function getDN()
	{
		if ( $this->server instanceof LDAP\server ) {
			return $this->userDN;
		}

		throw new \RuntimeException( 'request for DN on unconfigured link to datasource' );
	}

	/**
	 * Retrieves base DN of current datasource.
	 *
	 * @return ldap_user current instance
	 * @throws \RuntimeException
	 */

	public function elevateForPasswordChange()
	{
        if ( $this->server instanceof LDAP\server )
        {
            exception::enterSensitive();

            $dn = $this->setup->read( 'pwchanger.dn' );
            $pw = $this->setup->read( 'pwchanger.token' );

            if ( $dn && $pw )
            {
                if ( $this->server->simpleBindAs( $dn, $pw )->getBoundAs() !== $dn )
                    throw new \RuntimeException( 'failed to elevate user for changing password' );

                exception::leaveSensitive();

                return $this;
            }

            throw new \RuntimeException( 'missing configuration for elevating user on link to datasource' );
        }

		throw new \RuntimeException( 'request for elevating user on unconfigured link to datasource' );
	}
}

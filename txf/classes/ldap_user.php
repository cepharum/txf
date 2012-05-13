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


class ldap_user extends user
{
	/**
	 * setup as provided in call to ldap_user::configure()
	 *
	 * @var array
	 */

	protected $setup = array();

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



	protected function __construct() {}

	protected function readNode()
	{
		if ( !$this->userNode )
			$this->userNode = $this->server->read( $this->userDN );

		return $this->userNode;
	}

	public function getID()
	{
		return $this->readNode()->attributeByName( 'uidNumber' )->read( 0 );
	}

	public function getGUID()
	{
		return $this->readNode()->attributeByName( 'uidNumber' )->read( 0 );
	}

	public function getLoginName()
	{
		return $this->readNode()->attributeByName( 'uid' )->read( 0 );
	}

	public function getName()
	{
		return $this->readNode()->attributeByName( 'cn' )->read( 0 );
	}

	public function getProperty( $propertyName, $defaultIfMissing = null )
	{
		return $this->readNode()->attributeByName( $propertyName )->read( 0 );
	}

	public function setProperty( $propertyName, $propertyValue = null )
	{
	}

	public function authenticate( $credentials )
	{
		if ( !$this->bindAs( $this->userDN, $credentials ) )
			throw new unauthorized_exception( 'invalid credentials' );

		// reset any previously cached copy of user's node
		$this->userNode = null;

		return $this;
	}

	public function isAuthenticated()
	{
		return ( $this->server->getBoundAs() === $this->userDN );
	}

	protected function bindAs( $dn, $password )
	{
		if ( $configuration['sasl-mech'] && $configuration['sasl-realm'] )
			$this->server->bindAs( $dn, $password, $configuration['sasl-mech'], $configuration['sasl-realm'] );
		else
			$this->server->simpleBindAs( $dn, $password );

		return $this->server->isBound();
	}

	protected function configure( $configuration )
	{
		$this->server = new LDAP\server( $configuration['server'], $configuration['basedn'] );

		if ( $configuration['tls'] )
			$this->server->startTls();

		if ( $configuration['binddn'] )
			if ( !$this->bindAs( $configuration['binddn'], $configuration['bindpw'] ) )
				throw new LDAP\protocol_exception( 'failed to bind' );

		$this->setup = $configuration;
	}

	protected function search( $userIdOrLoginName )
	{
		if ( ctype_digit( $userIdOrLoginName ) )
			$query = strpos( '(uidNumber=%d)', $userIdOrLoginName );
		else
			$query = sprintf( $this->setup['search'], $userIdOrLoginName );

		$this->userDN = $this->searchSub( $query, $this->setup['basedn'] )->getDN();
	}
}

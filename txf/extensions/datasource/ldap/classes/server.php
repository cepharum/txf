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


namespace de\toxa\txf\datasource\ldap;


/**
 * LDAP server connection manager
 *
 * This class is managing single connection to an LDAP server.
 *
 */

class server
{
	/**
	 * link identifier of connected server required to use LDAP API
	 *
	 * @var resource
	 */

	private $link;

	/**
	 * URL or name of host to connect with
	 *
	 * @var string
	 */

	private $host;

	/**
	 * base DN to use by default on searching for entries
	 *
	 * @var string
	 */

	private $baseDN;

	/**
	 * DN used previously to bind with LDAP tree
	 *
	 * @var string
	 */

	private $boundAs = null;


	/**
	 * @param string $host address of LDAP server, e.g. its IP or hostname
	 * @param string $baseDN base DN to use by default on searching entries
	 */

	public function __construct( $host, $baseDN )
	{
		$this->host   = $host;
		$this->baseDN = $baseDN;

		$this->connect();
	}

	public function __wakeup()
	{
		$this->connect();
	}

	/**
	 * Establishes connection to LDAP server actually.
	 *
	 */

	protected function connect()
	{
		if ( !is_resource( $this->link ) )
		{
			$this->link = ldap_connect( $this->host );

			// ensure to use LDAPv3 protocol (required for proper binding and TLS support)
			ldap_set_option( $this->link, LDAP_OPT_PROTOCOL_VERSION, 3 );
			ldap_set_option( $this->link, LDAP_OPT_REFERRALS, 0 );
		}
	}

	/**
	 * Starts TLS (encrypted connection to server).
	 *
	 * @return server current instance
	 */

	public function startTls()
	{
		// start TLS on connection
		ldap_start_tls( $this->link );

		return $this;
	}

	/**
	 * Retrieves link identifier of currently connected server required on
	 * using LDAP API.
	 *
	 * @return resource
	 */

	public function getLink()
	{
		return $this->link;
	}

	/**
	 * Tries to bind using simple bind.
	 *
	 * @param string $userDN DN to bind as
	 * @param string $password password to use
	 * @return server current instance
	 */

	public function simpleBindAs( $dn, $password )
	{
		$this->boundAs = @ldap_bind( $this->link, $dn, $password ) ? $dn : false;

		return $this;
	}

	/**
	 * Tries to bind using SASL.
	 *
	 * @param string $userDN DN to bind as
	 * @param string $password password to use
	 * @param string $saslMech optionally selected SASL mech
	 * @param string $saslRealm optionally selected SASL realm
	 * @return server current instance
	 */

	public function bindAs( $dn, $password, $saslMech, $saslRealm )
	{
		$this->boundAs = @ldap_sasl_bind( $this->link, $dn, $password, $saslMech, $saslRealm ) ? $dn : false;

		return $this;
	}

	/**
	 * Detects if tree has been successfully bound previously.
	 *
	 * @return boolean true if binding succeeded previously
	 */

	public function isBound()
	{
		return $this->boundAs !== false && $this->boundAs !== null;
	}

	/**
	 * Retrieves DN currently bound as.
	 *
	 * @return string|null|false DN currently bound as, null if not bound, false if binding failed previously
	 */

	public function getBoundAs()
	{
		return $this->boundAs;
	}

	/**
	 * Searches whole thread of ldap tree.
	 *
	 * This method is an alias for Server::searchSub().
	 *
	 * @see server::searchSub()
	 * @param string $query term describing filter for desired result set
	 * @param string $baseDN optional base DN to use instead of the one provided on constructing server
	 * @return nodeset set of matching entries
	 */

	public function search( $query, $baseDN = null )
	{
		return $this->searchSub( $query, $baseDN );
	}

	/**
	 * Searches whole thread of ldap tree.
	 *
	 * @param string $query term describing filter for desired result set
	 * @param string $baseDN optional base DN to use instead of the one provided on constructing server
	 * @return nodeset set of matching entries
	 */

	public function searchSub( $query = null, $baseDN = null )
	{
		if ( $query === null )
			$query = 'objectClass=*';

		return new nodeset( $this->link, ldap_search( $this->link, $baseDN ? $baseDN : $this->baseDN, $query ) );
	}

	/**
	 * Searches immediate children of entry described by base DN.
	 *
	 * @param string $query term describing filter for desired result set
	 * @param string $baseDN optional base DN to use instead of the one provided on constructing server
	 * @return nodeset set of matching entries
	 */

	public function searchOne( $query = null, $baseDN = null )
	{
		if ( $query === null )
			$query = 'objectClass=*';

		return new nodeset( $this->link, ldap_list( $this->link, $baseDN ? $baseDN : $this->baseDN, $query ) );
	}

	/**
	 * Lists all entries immediately subordinated to entry selected by DN.
	 *
	 * @param string $parentDN DN of entry immediately superordinated to entries to be listed
	 * @return nodeset set of matching entries
	 */

	public function listSubs( $parentDN = null )
	{
		return $this->searchOne( null, $parentDN );
	}

	/**
	 * Retrieves result set containing entry selected by base DN unless provided
	 * query isn't satisfied.
	 *
	 * @param string $query term describing filter for desired result set
	 * @param string $baseDN optional base DN to use instead of the one provided on constructing server
	 * @return nodeset set of matching entries
	 */

	public function searchBase( $query = null, $baseDN = null )
	{
		if ( $query === null )
			$query = 'objectClass=*';

		return new nodeset( $this->link, ldap_read( $this->link, $baseDN ? $baseDN : $this->baseDN, $query ) );
	}

	/**
	 * Retrieves single entry selected by its DN.
	 *
	 * @throws protocol_exception
	 * @param string $dn DN of entry to read
	 * @param array|string $attributes (comma-separated) list of attributes to fetch, omit to get all attributes fetched by default
	 * @return node entry selected by DN
	 */

	public function read( $dn, $attributes = null )
	{
		if ( is_string( $attributes ) )
			$attributes = preg_split( '/,+/', $attributes );
		if ( is_array( $attributes ) )
		{
			$attributes = array_filter( $attributes );
			if ( !count( $attributes ) )
				$attributes = null;
		}

		if ( $attributes == null )
			$attributes = array();


		$resultset = ldap_read( $this->link, $dn, 'objectClass=*', $attributes );
		if ( $resultset )
		{
			$entry = ldap_first_entry( $this->link, $resultset );
			if ( $entry )
			{
				return new node( $this->link, $entry );
			}
		}

		throw new protocol_exception( 'failed to read entry', $this->link, $dn );
	}

	/**
	 * Creates new entry in LDAP tree using selected DN.
	 *
	 * The entry isn't created actually due to lacking required attributes. But
	 * returned entry manager is prepared to collect attributes to be written in
	 * a single step actually adding the entry then.
	 *
	 * @param string $dn DN of entry to create
	 * @return node entry manager instance
	 */

	public function createEntry( $dn )
	{
		return new node( $this->link, $dn );
	}
}

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
 * LDAP search result set manager
 *
 */

class nodeset
{
	/**
	 * cached link identifier for use with LDAP API
	 *
	 * @var resource
	 */

	private $link;

	/**
	 * cached result set identifier for use with LDAP API
	 *
	 * @var resource
	 */

	private $result;

	/**
	 * mark on whether result set is faked to revise support of chaining calls
	 *
	 * @var boolean
	 */

	private $faking;

	/**
	 * result identifier addressing previously fetched element of result set
	 *
	 * @var resource
	 */

	private $cursor;

	/**
	 * reference on most recently fetched entry
	 *
	 * @var node
	 */

	private $current;



	/**
	 * @param resource $link link identifier for use with LDAP API
	 * @param resource $result result set identifier for use with LDAP API
	 */

	public function __construct( $link, $result )
	{
		$this->link   = $link;
		$this->result = $result;

		$this->faking = !$link || !$result;

		$this->cursor = $this->current = null;
	}

	/**
	 * Detects if current instance represents valid set of LDAP entries or not.
	 *
	 * @return boolean true if instance is properly managing set of LDAP nodes
	 */

	public function valid()
	{
		return !$this->faking;
	}

	/**
	 * Restarts traversing over entries matching some query.
	 *
	 * This method isn't dropping reference on entry fetched by nodeset::next()
	 * most recently.
	 *
	 * @return nodeset current result set
	 */

	public function reset()
	{
		$this->cursor = null;

		return $this;
	}

	/**
	 * Advances focus to next entry in set of entries matching some previous
	 * query.
	 *
	 * @return node|false next entry in result set, false at end of set
	 */

	public function next()
	{
		if ( $this->faking )
			return ( $this->current = false );

		if ( $this->cursor )
			$this->cursor = ldap_next_entry( $this->link, $this->cursor );
		else if ( $this->cursor === null )
			$this->cursor = ldap_first_entry( $this->link, $this->result );

		if ( !$this->cursor )
			return ( $this->current = false );

		return ( $this->current = new node( $this->link, $this->cursor ) );
	}

	/**
	 * Fetches reference on entry currently focused in result set.
	 *
	 * This reference isn't reset by nodeset::reset() implicitly, but updated
	 * with every call of nodeset::next().
	 *
	 * @return node|false focused entry in result set, false at end of set
	 */

	public function current()
	{
		if ( $this->current === null )
			$this->next();

		return $this->current;
	}
}

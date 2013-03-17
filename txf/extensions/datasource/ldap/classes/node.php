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
 * Manages single entry in LDAP tree.
 *
 *
 */

class node
{
	/**
	 * link identifier required for accessing current entry by LDAP API
	 *
	 * @var resource
	 */

	private $link;

	/**
	 * search result identifier required for accessing current entry by LDAP API
	 *
	 * @var resource
	 */

	private $node;

	/**
	 * cursor/focus on current attribute fetched by node::first() and
	 * node::next()
	 *
	 * @var resource
	 */

	private $cursor;

	/**
	 * reference on attribute instance managing fetched previously by
	 * node::first() and node::next()
	 *
	 * @var attribute
	 */

	private $current;

	/**
	 * DN of an entry to be created, thus not existing in tree actually
	 *
	 * @var string
	 */

	private $createAsDN;

	/**
	 * set of attribute writes to be combined
	 *
	 * @var array
	 */

	private $adjustment;



	public function __construct( $link, $node )
	{
		$this->link = $link;
		$this->node = $node;

		$this->cursor = null;
	}

	/**
	 * Creates new entry on selected server using provided DN.
	 *
	 * The created entry is implicitly starting adjustment so it's possible to
	 * add attributes and their values. The attribute used in RDN IS NOT added
	 * for writing implicitly! The whole entry isn't created in LDAP tree until
	 * started adjustment is commited using node::commitAdjusting().
	 *
	 * @throws protocol_exception
	 * @param resource $link link to server hosting tree to create entry in
	 * @param type $dn DN of entry to be created
	 * @return node created entry
	 */

	public static function create( $link, $dn )
	{
		$dn = trim( $dn );

		if ( !preg_match( '/^[^=]+=[^=,]+/', $dn ) )
			throw new protocol_exception( 'invalid DN', $server->link, $dn );

		$new = new static( $link, null );
		$new->createAsDN = $dn;

		return $new->beginAdjusting();
	}

	/**
	 * Creates entry directly subordinated to current one.
	 *
	 * @see node::create()
	 * @param string $rdn RDN of subordinated entry
	 * @return node instance of entry to be subordinated
	 */

	public function createChild( $rdn )
	{
		return static::create( $this->link, "$rdn," . $this->getDN() );
	}

	/**
	 * Retrieves link identifier required to access this entry using LDAP API.
	 *
	 * @return resource
	 */

	public function getLink()
	{
		return $this->link;
	}

	/**
	 * Retrieves identifier required to access this entry using LDAP API.
	 *
	 * @return resource
	 */

	public function getID()
	{
		return $this->node;
	}

	/**
	 * Retrieves distinguished name of current entry.
	 *
	 * @return string distinguished name of entry
	 */

	public function getDN()
	{
		return $this->createAsDN ? $this->createAsDN : @ldap_get_dn( $this->link, $this->node );
	}

	/**
	 * Indicates whether current node instance is managing entry actually
	 * existing in LDAP tree.
	 *
	 * @return boolean true if entry exists actually, false otherwise
	 */

	public function actuallyExists()
	{
		return !$this->createAsDN;
	}

	/**
	 * Restarts traversal of attributes in current entry.
	 *
	 * This method isn't dropping reference on attribute fetched by node::next()
	 * most recently.
	 *
	 * @return node current entry
	 */

	public function reset()
	{
		$this->cursor = null;

		return $this;
	}

	/**
	 * Advances focus to next attribute in current entry.
	 *
	 * @return attribute|false next attribute in entry, false at end of attribute set
	 */

	public function next()
	{
		if ( $this->cursor )
		{
			$name = @ldap_next_attribute( $this->link, $this->node );
			$this->cursor = ( $name !== false );
		}
		else if ( $this->cursor === null )
		{
			$name = @ldap_first_attribute( $this->link, $this->node );
			$this->cursor = ( $name !== false );
		}

		if ( !$this->cursor )
			return ( $this->current = false );

		return ( $this->current = new attribute( $this, $name ) );
	}

	/**
	 * Retrieves most recently focused attribute of current entry.
	 *
	 * This reference isn't updated on resetting cursor/focus using node::reset(),
	 * but on advancing cursor/focus by calling node::next().
	 *
	 * @return attribute|false previously focused attribute in entry, false at end of attribute set
	 */

	public function current()
	{
		return $this->current;
	}

	/**
	 * Retrieves managed attribute selected by its name.
	 *
	 * The attribute mustn't exist actually in entry. Retrieved managers are
	 * (re-)instantiated on every request.
	 *
	 * @param string $name name of attribute
	 * @return attribute selected attribute's manager instance
	 */

	public function attributeByName( $name )
	{
		return new attribute( $this, $name );
	}

	/**
	 * Initiates grouped write/deletion process.
	 *
	 * @throws protocol_exception
	 * @return node current instance
	 */

	public function beginAdjusting()
	{
		if ( is_array( $this->adjustment ) )
			throw new protocol_exception( 'invalid nesting of grouped write of attributes', $this->link, $this->getDN() );

		$this->adjustment = array();

		return $this;
	}

	/**
	 * Indicates if current node is currently managing grouped write/delete
	 * operation.
	 *
	 * @return boolean true if there is a grouped writing in progress, false otherwise
	 */

	public function isAdjusting()
	{
		return is_array( $this->adjustment );
	}

	/**
	 * Adds writing an attribute to a grouped writing/deletion process.
	 *
	 * Set of values is filtered to exclude all empty strings. If resulting
	 * set is empty whole attribute is deleted.
	 *
	 * @throws protocol_exception
	 * @param attribute $attribute attribute to be adjusted
	 * @param array $values set of values to be assigned to selected attribute
	 * @return node current instance
	 */

	public function adjust( attribute $attribute, $values )
	{
		if ( !is_array( $this->adjustment ) )
			throw new protocol_exception( 'invalid use of grouped writing', $this->link, $this->getDN() );

		$this->adjustment[$attribute->getName()] = array_filter( array_map( function( $a ) { return trim( $a ); }, $values ), function( $a ) { return $a !== ''; } );

		return $this;
	}

	/**
	 * Commits processing a grouped write/deletion process.
	 *
	 * @throws protocol_exception
	 * @return node current instance
	 */

	public function commitAdjusting()
	{
		if ( !is_array( $this->adjustment ) )
			throw new protocol_exception( 'there is nothing to commit', $this->link, $this->getDN() );

		if ( !$this->actuallyExists() )
		{
			// entry has been prepared to create a new entry ... it's time to add entry now
			if ( !@ldap_add( $this->link, $this->getDN(), $this->adjustment ) )
				throw new protocol_exception( 'failed to add entry', $this->link, $this->getDN() );

			// convert instance to prevent it from adding further entries
			$this->node = @ldap_read( $this->link, $this->getDN(), "objectClass" );
			$this->createAsDN = null;
		}
		else if ( !@ldap_modify( $this->link, $this->getDN(), $this->adjustment ) )
			throw new protocol_exception( 'failed to adjust', $this->link, $this->getDN() );

		return $this;
	}

	/**
	 * Cancels processing a grouped write/deletion process.
	 *
	 * @throws protocol_exception
	 * @return node current instance
	 */

	public function cancelAdjusting()
	{
		if ( !is_array( $this->adjustment ) )
			throw new protocol_exception( 'there is nothing to cancel', $this->link, $this->getDN() );

		if ( !$this->actuallyExists() )
			// cancelling creation of new entry ... thus don't return instance
			return false;

		$this->adjustment = null;

		return $this;
	}

	/**
	 * Removes entry from tree.
	 *
	 * As a result this instance isn't managing any existing entry of tree
	 * anymore. But it's prepared to start a fresh entry using same DN just as
	 * if it was added using node::create() or node::createChild().
	 *
	 * @throws protocol_exception
	 * @return node current instance
	 */

	public function delete()
	{
		if ( $this->isAdjusting() )
			throw new protocol_exception( 'must not delete entry while adjusting it', $this->link, $this->getDN() );

		$dn = $this->getDN();

		if ( !@ldap_delete( $this->link, $dn ) )
			throw new protocol_exception( 'failed to delete entry', $this->link, $this->getDN() );

		// convert instance into manager of entry to be created
		$this->createAsDN = $dn;
		$this->beginAdjusting();

		return $this;
	}

	/**
	 * Moves/renames current node/entry.
	 *
	 * If $newParent is given this entry/node is moved in LDAP tree to its new
	 * position.
	 *
	 * @example
	 *
	 *       DN of entry:  cn=John Doe,ou=people,dc=example,dc=com
	 * RDN of same entry:  cn=John Doe
	 *
	 * @throws protocol_exception
	 * @param string $newRDN relative DN of current entry
	 * @param node $newParent node entry entry is subordinated to on moving, omit to rename locally
	 * @param boolean $keepPreviousRDN true to keep previous RDN as "normal" attribute
	 * @return node current instance
	 */

	public function move( $newRDN, node $newParent = null, $keepPreviousRDN = true )
	{
		if ( $this->isAdjusting() )
			throw new protocol_exception( 'must not move while adjusting entry', $this->link, $this->getDN() );

		if ( $newParent )
			$superRDN = $newParent->getDN();
		else
			$superRDN = trim( preg_replace( '/^[^,]+,/', '', $this->getDN() ) );

		if ( !@ldap_rename( $this->link, $this->getDN(), $newRDN, $superRDN, !!$keepPreviousRDN ) )
			throw new protocol_exception( 'failed to move entry', $this->link, $this->getDN() );

		return $this;
	}
}

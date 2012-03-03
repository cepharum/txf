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
 * 
 */


namespace de\toxa\txf;


/**
 * Structured data entity manager
 * 
 * This API offers opportunity to organize data in hierarchical structure quite
 * simply. Hierarchies are created by simply addressing them:
 * 
 * @example
 * $data = new data();
 * $data->support->may2011->tickets->addItem( new message( "my message" ) );
 * $data->support->departments->berlin->moderators->thomas_urban->name->label = "Name";
 * $data->support->departments->berlin->moderators->thomas_urban->name->value = "Thomas Urban";
 * 
 * 
 * @author Thomas Urban <thomas.urban@toxa.de>
 * 
 */

class thread
{
	/**
	 * label on a leaf entity's value
	 * 
	 * @var string
	 */

	protected $label = null;

	/**
	 * value of a leaf entity
	 * 
	 * @var mixed
	 */

	protected $value = null;

	/**
	 * special value available to describe case of having no regular value in a 
	 * leaf entity
	 * 
	 * @var mixed
	 */

	protected $specialValue = null;

	/**
	 * set of subordinated entities in a container entity
	 * 
	 * @var array[data]
	 */

	protected $subs  = null;



	/**
	 * Creates unfixed data entity.
	 */

	public function __construct()
	{
	}

	/**
	 * Detects if entity is a container for other entities.
	 * 
	 */

	public function isContainer()
	{
		return is_array( $this->subs ) && is_null( $this->label );
	}

	/**
	 * Detects if entity is a leaf for storing simple value.
	 * 
	 */

	public function isLeaf()
	{
		return is_null( $this->subs ) && !is_null( $this->label );
	}

	/**
	 * Detects if type of entity is fixed, already.
	 * 
	 * On creation entities aren't fixed to be either leaf or container. It gets
	 * fixed implicitly by accessing value or subordinated entities or 
	 * explicitly by using one method of fixAsContainer() or fixAsLeaf().
	 * 
	 */

	public function isFixed()
	{
		return $this->isContainer() || $this->isLeaf();
	}

	/**
	 * Fixes current entity as container to contain subordinated entities.
	 *
	 * @throws RuntimeException if entity is fixed as a leaf, already 
	 */

	public function fixAsContainer()
	{
		if ( $this->isLeaf() )
			throw new RuntimeException( 'cannot turn leaf into container' );

		if ( !is_array( $this->subs ) )
			$this->subs = array();
	}

	/**
	 * Fixes current entity as leaf to contain labelled value.
	 *
	 * @throws RuntimeException if entity is fixed as a container, already
	 * @param string $label label of leaf's value to assign implicitly 
	 */

	public function fixAsLeaf( $label = '' )
	{
		if ( $this->isContainer() )
			throw new RuntimeException( 'cannot turn container into leaf' );

		if ( is_null( $this->label ) )
			$this->label = trim( $label );
	}

	/**
	 * Detects if current data entity is actually set to some value.
	 * 
	 * @return boolean
	 */

	public function set()
	{
		return $this->isLeaf() ? !is_null( $this->value ) : !!count( $this->subs );
	}

	public function __get( $name )
	{
		switch ( strtolower( $name ) )
		{
			case 'label' :
				if ( !$this->isLeaf() )
					throw new RuntimeException( 'cannot retrieve label on a non-leaf' );
				
				return $this->label;

			case 'value' :
				if ( !$this->isLeaf() )
					throw new RuntimeException( 'cannot retrieve value on a non-leaf' );
				
				return $this->value;

			case 'specialvalue' :
				if ( !$this->isLeaf() )
					throw new RuntimeException( 'cannot retrieve special value on a non-leaf' );
				
				return $this->specialValue;

			default :
				if ( $this->isLeaf() )
					throw new RuntimeException( sprintf( 'cannot retrieve sub-entity on leaf "%s"', $this->label ) );

				if ( !$this->isContainer() )
					$this->fixAsContainer();

				if ( !array_key_exists( $name, $this->subs ) )
					$this->subs[$name] = new static();

				return $this->subs[$name];
		}
	}

	public function __set( $name, $value )
	{
		switch ( strtolower( $name ) )
		{
			case 'label' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'request for label on a non-leaf' );
				
				$this->fixAsLeaf( $value );
				break;

			case 'value' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'invalid request for changing value on a container' );

				$this->fixAsLeaf();

				$this->value = $value;
				break;

			case 'specialvalue' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'invalid request for changing special value on a container' );

				$this->fixAsLeaf();

				$this->specialValue = $value;
				break;

			default :
				if ( $this->isLeaf() )
					throw new RuntimeException( sprintf( 'cannot assign container data to leaf "%s"', $this->label ) );

				if ( !( $value instanceof static ) )
					throw new RuntimeException( 'invalid assignment of leaf data to container' );

				$this->fixAsContainer();

				$this->subs[$name] = new static();

				foreach ( $value->subs as $name => $value )
					$this->subs[$name]->__set( $name, $value );

				return $this->subs[$name];
		}
	}

	public function __unset( $name )
	{
		switch ( strtolower( $name ) )
		{
			case 'label' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'request for label on a non-leaf' );
				
				$this->fixAsLeaf( $value );
				break;

			case 'value' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'invalid request for changing value on a container' );

				$this->fixAsLeaf();

				$this->value = $value;
				break;

			case 'specialvalue' :
				if ( $this->isContainer() )
					throw new RuntimeException( 'invalid request for changing special value on a container' );

				$this->fixAsLeaf();

				$this->specialValue = $value;
				break;

			default :
				if ( $this->isLeaf() )
					throw new RuntimeException( sprintf( 'cannot assign container data to leaf "%s"', $this->label ) );

				if ( !( $value instanceof static ) )
					throw new RuntimeException( 'invalid assignment of leaf data to container' );

				$this->fixAsContainer();

				$this->subs[$name] = new static();

				foreach ( $value->subs as $name => $value )
					$this->subs[$name]->__set( $name, $value );

				return $this->subs[$name];
		}
	}

	/**
	 * Extracts a set of subordinated data entities.
	 * 
	 * The set of entities to extract is selected by $selector, which is either
	 *  - a function getting name and value of sub to decide
	 *  - an array of names of subentities to extract
	 *  - a string to match the selector using PCRE pattern or
	 *  - a boolean-evaluated value e.g. useful to get all subentities for iteration.
	 * 
	 * Missing subentities aren't included with returned set.
	 * 
	 * @param mixed $selector
	 * @param boolean $invert if true, subs are extended if selector is NOT matching
	 * @return array[data] set of extracted subentities
	 */

	public function select( $selector, $invert = false )
	{
		if ( $this->isLeaf() )
			throw new RuntimeException( 'cannot retrieve subs on a leaf' );

		if ( !is_array( $this->subs ) )
			return array();

		$out = array();
		foreach ( $this->subs as $name => $sub )
		{
			if ( is_callable( $selector ) )
				$include = $selector( $name, $sub );
			else if ( is_array( $selector ) )
				$include = in_array( $name, $selector );
			else if ( is_string( $selector ) )
				$include = preg_match( $selector, $name );
			else
				$include = !!$selector;

			if ( $include ^ !!$invert )
				$out[$name] = $sub;
		}

		return $out;
	}

	/**
	 * Adds item to set collected in current leaf entity.
	 * 
	 * @param mixed $value item to add
	 */

	public function addItem( $value )
	{
		if ( !$this->hasItems() )
			throw new RuntimeException( 'cannot add item to scalar leaf' );

		$this->fixAsLeaf();

		if ( !is_array( $this->value ) )
			$this->value = array();

		$this->value[] = $value;
	}

	/**
	 * Retrieves item from set collected in current leaf entity selected by its
	 * numeric index.
	 *
	 * @throws OutOfBoundException on using item index out of bounds
	 * @param integer $index index of item in set to retrieve
	 * @return mixed selected item
	 */

	public function item( $index )
	{
		if ( !$this->hasItems() )
			throw new RuntimeException( 'cannot retrieve item on scalar leaf' );

		if ( !is_array( $this->value ) || $index < 0 || $index >= count( $this->value ) )
			throw new OutOfBoundsException( 'index out of bounds' );

		return $this->value[$index];
	}

	/**
	 * Retrieves all items in set collected in current leaf entity.
	 * 
	 * @return array set of items
	 */

	public function items()
	{
		if ( !$this->hasItems() )
			throw new RuntimeException( 'cannot retrieve items on scalar leaf' );

		return is_array( $this->value ) ? $this->value : array();
	}

	/**
	 * Retrieves cardinality of set collected in current leaf entity.
	 * 
	 * @throws RuntimeException when used on a non-set leaf
	 * @return integer cardinality of set
	 */

	public function itemCount()
	{
		if ( !$this->hasItems() )
			throw new RuntimeException( 'cannot retrieve items on scalar leaf' );

		return is_array( $this->value ) ? count( $this->value ) : 0;
	}

	/**
	 * Indicates whether current leaf is a set for collecting data items or not.
	 * 
	 * @return boolean true on leaf available for collecting items, false otherwise
	 */

	public function hasItems()
	{
		if ( $this->isContainer() )
			throw new RuntimeException( 'cannot have items on a container' );

		return !$this->isLeaf() || is_array( $this->value ) || is_null( $this->value );
	}
}


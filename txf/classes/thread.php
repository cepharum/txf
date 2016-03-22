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
 * simply. Hierarchies are created by addressing them:
 * 
 * @example
 * $data = new de\toxa\txf\thread();
 * $data->support->may2011->tickets->addItem( new message( "my message" ) )->setRecipient();
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

	protected $___label = null;

	/**
	 * value of a leaf entity
	 * 
	 * @var mixed
	 */

	protected $___value = null;

	/**
	 * special value available to describe case of having no regular value in a 
	 * leaf entity
	 * 
	 * @var mixed
	 */

	protected $___specialValue = null;

	/**
	 * set of subordinated entities in a container entity
	 * 
	 * @var array[data]
	 */

	protected $___subs  = null;

	/**
	 * public name of a node's property providing its label
	 *
	 * @var string
	 */

	protected $___labelName;

	/**
	 * public name of a node's property providing its value
	 * 
	 * @var string
	 */

	protected $___valueName;

	/**
	 * public name of node's property providing its special value
	 * 
	 * @var string
	 */

	protected $___specialValueName;



	/**
	 * Creates unfixed data entity.
	 * 
	 * @param string $labelName public name of property providing a node's label
	 * @param string $valueName public name of property providing a node's value
	 * @param string $specialName public name of property providing a node's special value
	 */

	public function __construct( $labelName = 'label', $valueName = 'value', $specialName = 'specialValue' )
	{
		assert( 'is_string( $labelName ) && trim( $labelName ) !== ""' );
		assert( 'is_string( $valueName ) && trim( $valueName ) !== ""' );
		assert( 'is_string( $specialName ) && trim( $specialName ) !== ""' );

		$this->___labelName = $labelName;
		$this->___valueName = $valueName;
		$this->___specialValueName = $specialName;
	}

	/**
	 * Detects if entity is a container for other entities.
	 * 
	 */

	public function isContainer()
	{
		return is_array( $this->___subs );
	}

	/**
	 * Detects if entity is a leaf for storing simple value.
	 * 
	 */

	public function isLeaf()
	{
		return ( $this->___subs === false );
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
			throw new \RuntimeException( 'cannot turn leaf into container' );

		if ( !is_array( $this->___subs ) )
			$this->___subs = array();
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
			throw new \RuntimeException( 'cannot turn container into leaf' );

		$this->___subs = false;

		if ( trim( $label ) !== '' )
			$this->___label = trim( $label );
	}

	/**
	 * Detects if current data entity is actually set to some value.
	 * 
	 * @return boolean
	 */

	public function set()
	{
		return $this->isLeaf() ? !is_null( $this->___value ) : ( is_array( $this->___subs ) && !!count( $this->___subs ) );
	}

	public function __get( $name )
	{
		switch ( strtolower( $name ) )
		{
			case $this->___labelName :
				if ( !$this->isLeaf() )
					throw new \RuntimeException( 'cannot retrieve label on a container' );
				
				return $this->___label;

			case $this->___valueName :
				if ( !$this->isLeaf() )
					throw new \RuntimeException( 'cannot retrieve value on a container' );
				
				return $this->___value;

			case $this->___specialValueName :
				if ( !$this->isLeaf() )
					throw new \RuntimeException( 'cannot retrieve special value on a container' );
				
				return $this->___specialValue;

			default :
				if ( $this->isLeaf() )
					throw new \RuntimeException( sprintf( 'cannot retrieve sub-entity on leaf "%s"', $this->___label ) );

				if ( !$this->isContainer() )
					$this->fixAsContainer();

				if ( !array_key_exists( $name, $this->___subs ) )
					$this->___subs[$name] = new static( $this->___labelName, $this->___valueName, $this->___specialValueName );

				return $this->___subs[$name];
		}
	}

	public function __set( $name, $value )
	{
		switch ( strtolower( $name ) )
		{
			case $this->___labelName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'request for label on a non-leaf' );
				
				$this->fixAsLeaf( $value );
				break;

			case $this->___valueName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'invalid request for changing value on a container' );

				$this->fixAsLeaf();

				$this->___value = $value;
				break;

			case $this->___specialValueName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'invalid request for changing special value on a container' );

				$this->fixAsLeaf();

				$this->___specialValue = $value;
				break;

			default :
				if ( $this->isLeaf() )
					throw new \RuntimeException( sprintf( 'cannot assign container data to leaf "%s"', $this->___label ) );

				if ( !( $value instanceof static ) )
					throw new \RuntimeException( 'invalid assignment of leaf data to container' );

				$this->fixAsContainer();

				$this->___subs[$name] = new static( $this->___labelName, $this->___valueName, $this->___specialValueName );

				if ( is_array( $value->___subs ) )
					foreach ( $value->___subs as $name => $value )
					{
						$this->___subs[$name]->__set( $name, $value );
					}

				return $this->___subs[$name];
		}
	}

	public function __unset( $name )
	{
		switch ( strtolower( $name ) )
		{
			case $this->___labelName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'invalid request for unsetting label on a container' );
				
				$this->___label = null;
				break;

			case $this->___valueName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'invalid request for unsetting value on a container' );

				$this->___value = null;
				break;

			case $this->___specialValueName :
				if ( $this->isContainer() )
					throw new \RuntimeException( 'invalid request for unsetting special value on a container' );

				$this->___specialValue = null;
				break;

			default :
				if ( $this->isLeaf() )
					throw new \RuntimeException( sprintf( 'cannot unset container data on leaf "%s"', $this->___label ) );

				$this->___subs = null;
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
			throw new \RuntimeException( 'cannot retrieve subs on a leaf' );

		if ( !is_array( $this->___subs ) )
			return array();

		$out = array();
		foreach ( $this->___subs as $name => $sub )
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
	 * Retrieves set of threads contained in current containing node.
	 * 
	 * @return array[thread] set of threads contained in current node
	 */

	public function content()
	{
		if ( $this->isLeaf() )
			throw new \RuntimeException( 'invalid request for content on a leaf' );

		return is_array( $this->___subs ) ? $this->___subs : array();
	}

	/**
	 * Detects if current node is a non-empty container.
	 * 
	 * @return integer number of thread contained in current node
	 */

	public function hasContent()
	{
		return count( $this->content() ) > 0;
	}

	/**
	 * Adds item to set collected in current leaf entity.
	 * 
	 * @param mixed $value item to add
	 * @return mixed added value
	 */

	public function addItem( $value )
	{
		if ( !$this->hasItems() )
			throw new \RuntimeException( 'cannot add item to scalar leaf' );

		$this->fixAsLeaf();

		if ( !is_array( $this->___value ) )
			$this->___value = array();

		$this->___value[] = $value;

		return $value;
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
			throw new \RuntimeException( 'cannot retrieve item on scalar leaf' );

		if ( !is_array( $this->___value ) || $index < 0 || $index >= count( $this->___value ) )
			throw new OutOfBoundsException( 'index out of bounds' );

		return $this->___value[$index];
	}

	/**
	 * Retrieves all items in set collected in current leaf entity.
	 * 
	 * @return array set of items
	 */

	public function items()
	{
		if ( !$this->hasItems() )
			throw new \RuntimeException( 'cannot retrieve items on scalar leaf' );

		return is_array( $this->___value ) ? $this->___value : array();
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
			throw new \RuntimeException( 'cannot retrieve items on scalar leaf' );

		return is_array( $this->___value ) ? count( $this->___value ) : 0;
	}

	/**
	 * Indicates whether current leaf is a set for collecting data items or not.
	 * 
	 * This method is returning true for previously uninitialized leafs, too.
	 * 
	 * @return boolean true on leaf available for collecting items, false otherwise
	 */

	public function hasItems()
	{
		if ( $this->isContainer() )
			throw new \RuntimeException( 'cannot have items on a container' );

		return !$this->isLeaf() || is_array( $this->___value ) || is_null( $this->___value );
	}

	/**
	 * Extends current thread by provided XML.
	 * 
	 * @param SimpleXMLElement|string $xml XML document used to extend current thread
	 * @return thread current instance
	 */

	public function fromXml( $xml )
	{
		if ( is_string( $xml ) || $xml instanceof string )
			$xml = simplexml_load_string( trim( $xml ) );

		if ( $xml instanceof \SimpleXMLElement )
		{
			foreach ( $xml as $name => $data )
			{
				$attributes = $xml->attributes();

				if ( $data instanceof \SimpleXMLElement && $data->count() )
					$this->__get( $name )->fromXml( $data );
				else
				{
					$this->__set( $this->___valueName, data::autoType( strval( $data ), $attributes->type ) );

					if ( $attributes->default )
						$this->__set( $this->___specialValueName, $attributes->default );
				}

				if ( $attributes->label )
					$this->__set( $this->___labelName, $attributes->label );
			}
		}

		return $this;
	}

	/**
	 * Serializes current thread to XML.
	 * 
	 * The resulting XML is designed to be reversible thus may be used for
	 * serializing this thread.
	 * 
	 * @param string $rootName element name of root whole thread is wrapped in
	 * @param string $namespace namespace to use on elements of whole thread
	 * @param integer $indent initial indentation depth
	 * @return string XML code describing current thread
	 */

	public function toXml( $rootName = 'thread', $namespace = '', $indent = 0 )
	{
		if ( $this->isLeaf() )
			throw new \RuntimeException( 'cannot convert leaf to XML' );

		if ( trim( $rootName ) === '' )
			throw new \InvalidArgumentException( 'missing root element name' );

		return $this->nodeToXml( $rootName, $namespace, $indent );
	}

	/**
	 * Recursively serializes current node.
	 * 
	 * This method is used internally to implement conversion of a thread to
	 * XML code.
	 * 
	 * @param string $rootName element name of root whole thread is wrapped in
	 * @param string $namespace namespace to use on elements of whole thread
	 * @param integer $depth initial indentation depth
	 * @return string XML code describing current node of thread
	 */

	protected function nodeToXml( $name, $namespace = '', $depth = 0 )
	{
		if ( $this->isLeaf() )
		{
			// serialize leaf nodes
			$meta = array(
						'type'    => strtolower( gettype( $this->___value ) ),
						'default' => $this->___specialValue,
						'label'   => $this->___label,
						);

			if ( is_array( $this->___value ) )
			{
				// manage collections/sets in a leaf node separately

				if ( !count( $this->___value ) )
					// empty set -> use short tag
					return xml::describeElement( $name, $namespace, $depth, null, $meta );

				// write sequence of XML elements with same name
				$out = '';

				foreach ( $this->___value as $item )
				{
					if ( $item instanceof self )
						// subordinated is another thread ... render recursively
						$out .= $item->nodeToXml( $name, $namespace, $depth );
					else
					{
						// subordinated is any other value ... serialize to string
						$meta['type'] = strtolower( gettype( $item ) );
						$out .= xml::describeElement( $name, $namespace, $depth, strval( $item ), $meta );
					}

					// don't repeat basic attributes "default" and "label"
					$meta = array();
				}

				return $out;
			}

			return xml::describeElement( $name, $namespace, $depth, $this->___value, $meta );
		}


		// serialize container nodes of thread by triggering serialization of
		// subordinated nodes recursively
		$value = '';

		foreach ( $this->___subs as $subname => $sub )
			$value .= $sub->nodeToXml( static::fixXmlName( $subname ), $namespace, $depth + 1 );

		return xml::describeElement( $name, $namespace, $depth, $value );
	}
}


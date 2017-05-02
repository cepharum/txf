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


/**
 * Implementation of array-related tools and operations managing a set of
 * elements all matching requested type of value. Elements are rejected/removed
 * unless matching that type.
 *
 * @author Thomas Urban <info@toxa.de>
 * @version 1.0
 */


class typed_set extends set
{

	protected $type;


	/**
	 * @param array $set array to be managed
	 */

	public function __construct( $type, $set = null )
	{
		if ( is_callable( $type ) )
			$this->type = $type;
		else if ( !str::isString( $type ) )
			throw new \InvalidArgumentException( 'invalid type specifier' );
		else
			$this->type = trim( _S($type)->asUtf8 );

		if ( func_num_args() > 1 )
			parent::__construct( $this->filterByType( $set, false ) );
		else
			parent::__construct();
	}

	/**
	 * Tests if provided value is matching current set's type of value.
	 *
	 * @param string $value value to test
	 * @return boolean true on matching type, false otherwise
	 */

	protected function isMatchingType( $value )
	{
		if ( is_callable( $this->type ) )
			return !!call_user_func( $this->type, $value );

		if ( is_object( $value ) )
			return ( $value instanceof $this->type );

		return !strcasecmp( gettype( $value ), $this->type );
	}

	/**
	 * Processes provided array removing all elements not matching current set's
	 * type of value.
	 *
	 * @param array $set array of elements to filter
	 * @param boolean $failOnInvalidType if true, throw exception on invalid
	 *                                    elements, otherwise remove them
	 * @return array filtered array
	 */

	protected function filterByType( $set, $failOnInvalidType = false )
	{
		if ( is_array( $set ) )
		{
			foreach ( $set as $key => $element )
				if ( is_array( $element ) )
				{
					$filtered = $this->filterByType( $element, $failOnInvalidType );

					if ( empty( $filtered ) && count( $element ) )
						unset( $set[$key] );
					else
						$set[$key] = $filtered;
				}
				else if ( !$this->isMatchingType( $element ) )
				{
					if ( $failOnInvalidType )
						throw new \InvalidArgumentException( 'invalid type of element' );

					unset( $set[$key] );
				}

			if ( !self::isHash( $set ) )
				$set = array_values( $set );

			return $set;
		}
		else
		{
			if ( $failOnInvalidType )
				return array();

			throw new \InvalidArgumentException( 'invalid set' );
		}
	}


	/**
	 * Conveniently wraps provided array in instance of class set.
	 *
	 * @note This method actually REQUIRES argument $type. It's defined optional
	 *       due to stay compatible with parent class.
	 *
	 * @param array $set
	 * @return set created set managing (copy of) provided data
	 */

	public static function wrap( $set = array(), $type = null )
	{
		return new static( $type, $set );
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'type' :
				return $this->type;

			default :
				return parent::__get( $name );
		}
	}

	/**
	 * Extracts path-selected subset from set for read access.
	 *
	 * Using $default is provided since NULL might be a valid result, too.
	 *
	 * @throws \UnexpectedValueException on trying to descend into leaf node
	 *
	 * @param string $path pathname to subset
	 * @param type|array $default default to use
	 * @return mixed found subset, $default on missing
	 */

	public function read( $path, $default = null )
	{
		if ( is_array( $default ) )
			$default = $this->filterByType( $default, false );
		else if ( !is_null( $default ) && !$this->isMatchingType( $default ) )
			throw new \InvalidArgumentException( 'invalid type of default value' );

		return parent::read( $path, $default );
	}

	/**
	 * Retrieves reference on path-selected subset from set for write access.
	 *
	 * This method is automatically creating selected subset or any containing
	 * superordinated subset of set on demand.
	 *
	 * @throws \UnexpectedValueException on trying to descend into leaf node
	 *
	 * @param string $path pathname to subset
	 * @param mixed $value some value to write (replacing existing content)
	 */

	public function write( $path, $value )
	{
		if ( is_array( $value ) )
			$value = $this->filterByType( $value, false );
		else if ( !is_null( $value ) && !$this->isMatchingType( $value ) )
			throw new \InvalidArgumentException( 'invalid type of value' );

		parent::write( $path, $value );
	}

	/**
	 * Appends value as element to set.
	 *
	 * @param type $value
	 * @return type appended value
	 */

	public function push( $value )
	{
		if ( is_array( $value ) )
			$value = $this->filterByType( $value, false );
		else if ( !is_null( $value ) && !$this->isMatchingType( $value ) )
			throw new \InvalidArgumentException( 'invalid type of value' );

		return parent::push( $value );
	}

	/**
	 * Prepends value as element to set.
	 *
	 * @param type $value
	 * @return type prepended value
	 */

	public function unshift( $value )
	{
		if ( is_array( $value ) )
			$value = $this->filterByType( $value, false );
		else if ( !is_null( $value ) && !$this->isMatchingType( $value ) )
			throw new \InvalidArgumentException( 'invalid type of value' );

		return parent::unshift( $value );
	}

	/**
	 * Creates new set from provided XML document.
	 *
	 * @param string|\SimpleXMLElement $xml xml
	 * @return set set representing data found in XML
	 */

	public static function fromXml( $xml )
	{
		return static::wrap( parent::fromXml( $xml ) );
	}

	/**
	 * Extends current set by provided set(s).
	 *
	 * @note This method is actually adjusting set managed by current instance.
	 *
	 * @return set extended set
	 */

	public function extend()
	{
		$extensions = func_get_args();

		foreach ( $extensions as $extension )
			$this->data = array_merge_recursive( $this->data, static::wrap( $extension, $this->type )->data );

		return $this;
	}
}


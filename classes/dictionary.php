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
 * Dictionary implementation
 *
 * This dictionary is very similar to common associative arrays. Different from
 * those dictionaries are mutable in a more convenient way.
 *
 * @note This implementation is provided a sorted dictionary suitable for use as
 *       associative array.
 * @author Thomas Urban
 */

class dictionary
{
	private $_keys = array();

	private $_values = array();


	public function __construct( $set = null )
	{
		if ( is_array( $set ) )
		{
			$this->_keys   = array_keys( $set );
			$this->_values = array_values( $set );
		}
		else if ( $set !== null )
			throw new \InvalidArgumentException( 'invalid array for initializing dictionary' );
	}

	public static function createOnArray( $set )
	{
		return new static( $set );
	}

	protected function _keyToIndex( $key )
	{
		return array_search( $key, $this->_keys, false );
	}

	protected function _keyMustExist( $key )
	{
		$index = $this->_keyToIndex( $key );
		if ( $index === false )
			throw new \InvalidArgumentException( 'no such key: '. $key );

		return $index;
	}

	protected function _keyMustntExist( $key )
	{
		if ( $this->_keyToIndex( $key ) !== false )
			throw new \InvalidArgumentException( 'key exists already: ' . $key );
	}

	private function _insertAt( $index, $key, $value )
	{
		if ( !is_string( $key ) && !is_integer( $key ) )
			throw new \InvalidArgumentException( 'invalid key type' );

		if ( $index === null || $index == count( $this->_keys ) )
		{
			$this->_keys[]   = $key;
			$this->_values[] = $value;
		}
		else
		{
			array_splice( $this->_keys,   $index, 0, array( $key ) );
			array_splice( $this->_values, $index, 0, array( $value ) );
		}

		return $this;
	}

	private function _removeAt( $index )
	{
		if ( ctype_digit( trim( $index ) ) )
		{
			array_splice( $this->_keys, $index, 1 );
			array_splice( $this->_values, $index, 1 );
		}

		return $this;
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'length' :
			case 'count' :
			case 'size' :
				return count( $this->_keys );

			case 'items' :
			case 'elements' :
				return array_combine( $this->_keys, $this->_values );

			case 'keys' :
				return $this->_keys;

			case 'values' :
				return $this->_values;

			default :
				return $this->value( $name );
		}
	}

	public function keyAtIndex( $index )
	{
		if ( $index === false || $index < 0 || $index >= count( $this->_keys ) )
			throw new \OutOfBoundsException();

		return $this->_keys[intval( $index )];
	}

	public function valueAtIndex( $index )
	{
		if ( $index === false || $index < 0 || $index >= count( $this->_keys ) )
			throw new \OutOfBoundsException();

		return $this->_values[intval( $index )];
	}

	public function value( $key )
	{
		$index = $this->_keyToIndex( $key );
		if ( $index === false )
			throw new \OutOfBoundsException( 'no such key: ' . $key );

		return $this->_values[$index];
	}

	public function setValue( $key, $value, $addIfMissing = true )
	{
		$index = $this->_keyToIndex( $key );
		if ( $index !== false )
			$this->_values[$index] = $value;
		else if ( $addIfMissing )
			$this->_insertAt( null, $key, $value );
		else
			throw new \InvalidArgumentException( 'no such key' );

		return $this;
	}

	public function rename( $oldKey, $newKey )
	{
		$oldIndex = $this->_keyToIndex( $oldKey );
		if ( $oldIndex === false )
			throw new \InvalidArgumentException( 'no such key' );

		$newIndex = $this->_keyToIndex( $newKey );
		if ( $newIndex !== false )
		{
			if ( $oldIndex !== $newIndex )
				throw new \RuntimeException( 'requested key exists already' );

			$this->_keys[$oldIndex] = $newKey;
		}

		return $this;
	}

	public function exists( $key )
	{
		return ( $this->_keyToIndex( $key ) !== false );
	}

	public function insertValueBefore( $newKey, $newValue, $referenceKey = null )
	{
		return $this->insertAtIndex( $newKey, $newValue, $referenceKey !== null ? $this->_keyToIndex( $referenceKey ) : null );
	}

	public function insertAtIndex( $newKey, $newValue, $referenceIndex = null )
	{
		$this->_keyMustntExist( $newKey );

		if ( $referenceIndex === false || $referenceIndex < 0 || $referenceIndex > count( $this->_keys ) )
			throw new \OutOfBoundsException( 'reference not found' );

		return $this->_insertAt( $referenceIndex, $newKey, $newValue );
	}

	public function remove( $key )
	{
		return $this->_removeAt( $this->_keyToIndex( $key ) );
	}

	public function removeAtIndex( $index )
	{
		if ( $index === false || $index < 0 || $index >= count( $this->_keys ) )
			throw new \OutOfBoundsException( 'invalid index to remove' );

		return $this->_removeAt( $index );
	}

	public function sort( $sortingCallback = null, $sortAscendingly = true, $sortKeys = false )
	{
		if ( $sortingCallback === null )
			$sortingCallback = $sortKeys ? 'strcasecmp' : 'strnatcasecmp';

		$data = $this->items;

		if ( $sortAscendingly )
			$sortKeys ? uksort( $data, $sortingCallback ) : uasort( $data, $sortingCallback );
		else
		{
			$negator = function( $l, $r ) use ( $sortingCallback ) { return call_user_func( $sortingCallback, $r, $l ); };

			$sortKeys ? uksort( $data, $negator ) : uasort( $data, $negator );
		}

		$this->_keys   = array_keys( $data );
		$this->_values = array_values( $data );

		return $this;
	}
}

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

namespace de\toxa\txf\datasource\ldap;


class attribute
{
	/**
	 * @var node
	 */

	private $node;

	/**
	 * @var string
	 */

	private $name;

	/**
	 * @var boolean
	 */

	private $faking;


	/**
	 * @param node $node node this attribute is part of
	 * @param string $name name of attribute to manage
	 */

	public function __construct( node $node, $name )
	{
		$this->node = $node;
		$this->name = $name;

		$this->faking = !$node || !is_string( $name ) || trim( $name ) === '';
	}

	/**
	 * Detects if current instance represents valid attribute or not.
	 *
	 * @return boolean true if instance is properly managing single attribute
	 */

	public function valid()
	{
		return !$this->faking;
	}

	/**
	 * Retrieves name of attribute.
	 *
	 * @return string
	 */

	public function getName()
	{
		return $this->faking ? false : $this->name;
	}

	/**
	 * Retrieves entry/node this attribute is part of.
	 *
	 * @return node
	 */

	public function getNode()
	{
		return $this->faking ? false : $this->node;
	}

	/**
	 * Reads values of attribute.
	 *
	 * @param integer $atIndex 0-based index of value to read from set of values
	 * @return array set of attribute's values
	 */

	public function read( $atIndex = null )
	{
		if ( $this->faking )
			return $atIndex === null ? array() : null;

		$values = @ldap_get_values( $this->node->getLink(), $this->node->getID(), $this->name );
		if ( is_array( $values ) && array_key_exists( 'count', $values ) )
			unset( $values['count'] );

		return $atIndex === null ? $values : $values[$atIndex];
	}

	/**
	 * Writes values of attribute.
	 *
	 * @throws protocol_exception
	 * @param array $set values to assign
	 * @return attribute current instance
	 */

	public function writeArray( $set )
	{
		return call_user_func_array( array( &$this, 'write' ), $set );
	}

	/**
	 * Writes values of attribute.
	 *
	 * This method takes a variable number of arguments each considered one
	 * value of attribute to be written.
	 *
	 * The method is filtering out all empty/unset values. If the resulting set
	 * of values is empty, the whole attribute is deleted instead.
	 *
	 * @throws protocol_exception
	 * @param string $firstValue first value of attribute to write
	 * @return attribute current instance
	 */

	public function write( $firstValue )
	{
		if ( !$this->faking )
		{
			$values = array_filter( array_map( function( $a ) { return trim( $a ); }, func_get_args() ), function( $a ) { return $a !== ''; } );
			if ( !count( $values ) )
				return $this->deleteArray( $this->read() );

			if ( $this->node->isAdjusting() )
				$this->node->adjust( $this, $values );
			else if ( !@ldap_modify( $this->node->getLink(), $this->node->getDN(), array( $this->name => $values ) ) )
				throw new protocol_exception( sprintf( 'failed to modify attribute %s', $this->name ), $this->node->getLink(), $this->node->getDN() );
		}

		return $this;
	}

	/**
	 * Deletes selected values of current attribute.
	 *
	 * Provided set of values is filtered first to exclude any empty string
	 * value. If filtered set is empty nothing's deleted actually.
	 *
	 * @throws protocol_exception
	 * @param array $valuesToDelete set of values to remove from current attribute
	 * @return attribute current instance
	 */

	public function deleteArray( $valuesToDelete )
	{
		return call_user_func_array( array( &$this, 'delete' ), $valuesToDelete );
	}

	/**
	 * Deletes values of current attribute given in arguments.
	 *
	 * This method takes a variable number of arguments each representing
	 * existing value of current attribute to be deleted/removed. Since empty
	 * strings are ignored, there may be no value to delete. In that case
	 * no values are deleted at all.
	 *
	 * @throws protocol_exception
	 * @param string $firstValueToDelete first value of attribute to delete
	 * @return attribute current instance
	 */

	public function delete( $firstValueToDelete )
	{
		if ( !$this->faking )
		{
			$values = array_filter( array_map( function( $a ) { return trim( $a ); }, func_get_args() ), function( $a ) { return $a !== ''; } );
			if ( count( $values ) )
			{
				if ( $this->node->isAdjusting() )
					$this->node->adjust( $this, array_diff( $this->read(), $values ) );
				else if ( !@ldap_mod_del( $this->node->getLink(), $this->node->getDN(), array( $this->name => $values ) ) )
					throw new protocol_exception( sprintf( 'failed to delete values of attribute %s', $this->name ), $this->node->getLink(), $this->node->getDN() );
			}
		}

		return $this;
	}
}

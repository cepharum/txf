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
 * Provides opaque value management using set of name-value-pairs.
 *
 * This class is designed to optionally change internal management in future
 * without affecting other classes falsely relying on its actual structure.
 *
 * @author Thomas Urban
 *
 */


class variable_space
{

	/**
	 * hash used internally for managing all declared variables.
	 *
	 * @var array
	 */

	protected $__heap = array();


	/**
	 * Creates initially empty variable space.
	 */

	public function __construct()
	{
	}

	/**
	 * Creates variable space from passed arguments.
	 *
	 * This method expects an even number of arguments with every pair of
	 * arguments declaring one variable to be included in variable space
	 * initially. First argument in a pair is the variable's name, second is its
	 * value to be assigned.
	 *
	 * @param string $name name of first variable to declare in variable space
	 * @param mixed $value value of first variable
	 * @throws \InvalidArgumentException
	 * @return variable_space variable space containing all provided variables
	 */

	public static function create( $name = null, $value = null )
	{
		$arguments = func_get_args();

		if ( count( $arguments ) & 1 )
			throw new \InvalidArgumentException( 'wrong argument count' );

		$instance = new static;

		while ( count( $arguments ) )
		{
			$name  = array_shift( $arguments );
			$value = array_shift( $arguments );

			if ( ( $name = data::isNonEmptyString( $name ) ) === false )
				throw new \InvalidArgumentException( 'invalid variable name' );

			$instance->__heap[$name] = $value;
		}

		return $instance;
	}

	/**
	 * Reads value of variable selected by its name.
	 *
	 * Exception is thrown on using invalid variable name, only!
	 *
	 * @param string $name
	 * @throws \InvalidArgumentException
	 * @return mixed|null value of selected variable, null on missing variable
	 */

	public function read( $name )
	{
		if ( ( $name = data::isNonEmptyString( $name ) ) === false )
			throw new \InvalidArgumentException( 'invalid variable name' );

		return array_key_exists( $name, $this->__heap ) ? $this->__heap[$name] : null;
	}

	/**
	 * Adjusts value of selected variable.
	 *
	 * If selected variable isn't found in variable space, it's created
	 * implicitly.
	 *
	 * @param string $name name for variable to change
	 * @param mixed $value value to assign
	 * @throws \InvalidArgumentException
	 * @return mixed adjusted value for chaining assignments
	 */

	public function update( $name, $value = null )
	{
		if ( ( $name = data::isNonEmptyString( $name ) ) === false )
			throw new \InvalidArgumentException( 'invalid variable name' );

		if ( \func_num_args() == 1 )
			unset( $this->__heap[$name] );
		else
			$this->__heap[$name] = $value;

		return $value;
	}

	/**
	 * Drops selected variable.
	 *
	 * @param string $name name of variable to drop
	 */

	public function drop( $name )
	{
		$this->update( $name );
	}

	/**
	 * Provides convenient method for reading variable.
	 *
	 * @note Due to requiring valid PHP property name it's not possible to
	 *       address as many variables as on using self::read().
	 *
	 * @param string $name name of variable to read
	 * @return mixed value of variable, null on missing it
	 */

	public function __get( $name )
	{
		return $this->read( $name );
	}

	/**
	 * Provides convenient method for writing variable.
	 *
	 * @note Due to requiring valid PHP property name it's not possible to
	 *       address as many variables as on using self::read().
	 *
	 * @param string $name name of variable to write
	 * @param mixed $value value to assign
	 * @return mixed assigned value for chaining assignments
	 */

	public function __set( $name, $value )
	{
		return $this->update( $name, $value );
	}

	/**
	 * Retrieves all variables as a hash.
	 *
	 * @return array hash of all currently declared variables
	 */

	public function asArray()
	{
		return $this->__heap;
	}

	/**
	 * Creates new variable space initially containing all elements in given
	 * array as variables.
	 *
	 * @param array $array hash of values
	 * @return variable_space variable space containing all elements of array
	 */

	public static function fromArray( $array )
	{
		assert( 'is_array( $array );' );

		$space = new static;
		$space->__heap = $array;

		return $space;
	}
}


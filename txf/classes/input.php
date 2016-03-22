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


/**
 * Manages filtered access on multiple layers of input data.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @version 1.0
 */


class input extends singleton
{
	/** source name for actual GET input */
	const SOURCE_ACTUAL_GET = 'get';
	/** source name for actual POST input */
	const SOURCE_ACTUAL_POST = 'post';
	/** source name for session-based input */
	const SOURCE_STATEFUL = 'stateful';
	/** source name for caller-provided default */
	const SOURCE_DEFAULT  = 'default';
	/** source name for input defined configuration */
	const SOURCE_CONFIG   = 'config';

	/** Selects input filter matching any string (but not null). */
	const FORMAT_STRING  = 'string';
	/** Selects input filter matching any valid keyword. */
	const FORMAT_KEYWORD = 'keyword';
	/** Selects input filter matching integers. */
	const FORMAT_INTEGER = 'integer';
	/** Selects input filter matching boolean switch names (e.g. yes or no). */
	const FORMAT_BOOL    = 'bool';
	/** Selects input filter matching GUIDs. */
	const FORMAT_GUID    = 'guid';



	protected $sources = array();


	public function __construct()
	{
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' )
			$this->addSource( self::SOURCE_ACTUAL_POST, new input_source_actual( input_source_actual::METHOD_POST ) );

		$this
			->addSource( self::SOURCE_ACTUAL_GET, new input_source_actual( input_source_actual::METHOD_GET ) )
			->addSource( self::SOURCE_STATEFUL, new input_source_stateful() )
			->addSource( self::SOURCE_CONFIG, new input_source_config() )
			->addSource( self::SOURCE_DEFAULT, new input_source_default() );
	}

	/**
	 * Checks if selected input source has been enqueued before.
	 *
	 * This method succeeds on disabled sources, too.
	 *
	 * @param string $source name of source to test, must be keyword
	 * @return boolean true if input source is found in queue
	 */

	public function hasSource( $source )
	{
		return array_key_exists( $source, $this->sources );
	}

	/**
	 * Retrieves enqueued manager instance for selected input source.
	 *
	 * This method is basically available for customizing single input source
	 * managers.
	 *
	 * @param string $source name of source to read, must be keyword
	 * @return input_source manager of selected input source
	 */

	public function getSourceManager( $source )
	{
		if ( !$this->hasSource( $source ) )
			throw new \InvalidArgumentException( 'no such source manager' );

		return $this->sources[$source]['manager'];
	}

	/**
	 * Detects if selected input source is enabled currently.
	 *
	 * @param string $source name of source to test, must be keyword
	 * @return boolean true if source exists and is enabled
	 */

	public function sourceIsEnabled( $source )
	{
		return $this->hasSource( $source ) && $this->sources[$sources]['enabled'];
	}

	/**
	 * Enables or disables selected input source.
	 *
	 * @param string $source name of source to adjust, must be keyword
	 * @param boolean $enabled true enables source while fals is disabling
	 * @return input current instance for chaining calls
	 */

	public function enableSource( $source, $enabled = true )
	{
		if ( $this->hasSource( $source ) )
			$this->sources[$source]['enabled'] = (bool) $enabled;

		return $this;
	}

	/**
	 * Removes selected input source from queue.
	 *
	 * @param string $source name of source to remove, must be keyword
	 * @return input current instance for chaining calls
	 */

	public function removeSource( $source )
	{
		$source = data::isKeyword( $source );

		if ( $this->hasSource( $source ) )
			unset( $this->sources[$source] );

		return $this;
	}

	/**
	 * Inserts or moves input source manager in current queue of sources.
	 *
	 * Input sources are used to read input from different sources such as
	 * actual script input, session-based state or application's configuration.
	 *
	 * Omit $manager to move any existing manager selected by name in $source.
	 *
	 * @param string $source name of source, must be keyword
	 * @param input_source $manager input source manager to insert/move
	 * @param array $providing names of sources becoming successors of $manager
	 * @param array $depends names of sources becoming predecessors of $manager
	 * @return input current instance for chaining calls
	 */

	public function addSource( $source, $manager = null, $providing = null, $depends = null )
	{
		if ( !( $source = data::isKeyword( $source ) ) )
			throw new \InvalidArgumentException( 'invalid/missing source name' );


		$queueBackup = $this->sources;

		try
		{
			/*
			 * support moving existing manager
			 */

			if ( is_null( $manager ) )
			{
				if ( !$this->hasSource( $source ) )
					throw new \InvalidArgumentException( 'no such input source manager' );

				// extract existing manager from its current location in queue
				$index   = array_search( $source, $existingSourcesInOrder );

				$manager = array_splice( $this->sources, $index, 1 );
				$manager = array_shift( $manager );
				$manager = $manager['manager'];
			}

			if ( !( $manager instanceof input_source ) )
				throw new \InvalidArgumentException( 'invalid input source manager' );


			/*
			 * find index of requested location for inserting new source
			 */

			$existingSourcesInOrder = array_keys( $this->sources );

			$providing = data::asArray( $providing );
			$depends   = data::asArray( $depends );

			if ( empty( $providing ) )
				$providingIndex = count( $this->sources );
			else
				$providingIndex = min( array_map( function( $a ) { return array_search( $a, $existingSourcesInOrder ); }, $providing ) );

			if ( empty( $depends ) )
				$dependsIndex = -1;
			else
				$dependsIndex = max( array_map( function( $a ) { return array_search( $a, $existingSourcesInOrder ); }, $depends ) );

			if ( $providingIndex <= $dependsIndex )
				throw new \InvalidArgumentException( 'cannot insert manager at requested location' );


			/*
			 * split existing queue at request location and insert/move manager
			 */

			$this->sources = array_merge(
								array_slice( $this->sources, 0, $providingIndex, true ),
								array( $source => array(
													'enabled'  => true,
													'manager'  => $manager,
													'volatile' => $manager->isVolatile(),
													) ),
								array_slice( $this->sources, $providingIndex, true )
								);


			return $this;
		}
		catch ( Exception $e )
		{
			$this->sources = $queueBackup;

			throw $e;
		}
	}

	/**
	 * Reads single input value.
	 *
	 * Use this method or its counterpart input::vget() in preference over
	 * accessing $_GET, $_POST or other super global arrays.
	 *
	 * The method traverses current queue of input sources asking each for
	 * knowing value selected by $name. If $format is defining an expected
	 * format a known value must be additionally valid according to $format.
	 * First known (and thus valid) input is returned after normalization.
	 *
	 * Using $format is strongly encouraged! It takes one of the FORMAT_*
	 * constants, a single preg_match pattern or any custom format provider
	 * (array consisting of elements 'prepare', 'valid' and 'convert').
	 *
	 * If $format is set and no source is providing value and exception is
	 * thrown unless null is valid according to the selected format.
	 *
	 * @param string $name name of value to read
	 * @param mixed $default default to use
	 * @param mixed $format expected format's definition
	 * @return mixed found value, processed according to format definition
	 */

	final public static function get( $name, $default = null, $format = null, $optional = false )
	{
		return static::current()->readValue( $name, $default, true, $format, $optional );
	}

	/**
	 * Reads single volatile input value.
	 *
	 * This method basically works like input::get(), but in opposition to that
	 * it's excluding any persistent input source and it's skipping persisting
	 * finally read value prior to returning.
	 *
	 * @see input::get()
	 *
	 * @param string $name name of value to read, must be keyword
	 * @param mixed $default default to use
	 * @param mixed $format format definition
	 * @return mixed validated and normalized value
	 */

	final public static function vget( $name, $default = null, $format = null, $optional = false )
	{
		return static::current()->readValue( $name, $default, false, $format, $optional );
	}

	/**
	 * Requests selected value to persist in all enabled and supporting input
	 * sources.
	 *
	 * The value isn't validated prior to persisting, but will be optionally
	 * filtered on reading back using input::get() and input::vget().
	 *
	 * @param string $name name of value to persist
	 * @param mixed $value value to persist
	 */

	final public static function persist( $name, $value )
	{
		$name = data::isKeyword( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'invalid name' );

		static::current()->persistValue( $name, $value );
	}

	/**
	 * Detects if a parameter is persistent currently.
	 *
	 * @param string $name name of parameter to test for persistence
	 * @return boolean true if parameter is persistent in current context
	 */

	final public static function isPersistent( $name )
	{
		$name = data::isKeyword( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'invalid name' );

		foreach ( static::current()->sources as $source )
			if ( $source['enabled'] && !$source['volatile'] )
				if ( $source['manager']->hasValue( $name ) )
					return true;

		return false;
	}

	/**
	 * Requests selected value to be dropped in all enabled input sources.
	 *
	 * @param string $name name of value to be dropped
	 */

	final public static function drop( $name )
	{
		$name = data::isKeyword( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'invalid name' );

		static::current()->persistValue( $name, $value );
	}

	/**
	 * Tests provided value for meeting criteria of selected format.
	 *
	 * @param mixed $value arbitrary value
	 * @param mixed $format format definition
	 * @return boolean true on $value considered valid according to $format
	 */

	final public static function validate( $value, $format = null )
	{
		return static::current()->valueIsValid( $value, $format );
	}

	/**
	 * Normalizes provided value according to selected format.
	 *
	 * @param mixed $value arbitrary value
	 * @param mixed $format format definition
	 * @return mixed value normalized according to selected format
	 */

	final public static function normalize( $value, $format = null )
	{
		return static::current()->fixValue( $value, $format );
	}

	/**
	 * Retrieves manager instance of source selected by name.
	 *
	 * @param string $name name of source to access, must be keyword
	 * @return input_source
	 */

	final public static function source( $name )
	{
		return static::current()->getSourceManager( $name );
	}

	/**
	 * Reads named input variable.
	 *
	 * Support for $default requires related source manager to be included in
	 * queue of enabled input sources.
	 *
	 * @param string $name name of value to read, must be keyword
	 * @param mixed $default value to return by default
	 * @param boolean $checkPersistents if true, check persistent sources as well
	 * @param mixed $format format definition used to filter values
	 * @return mixed|null available input value, null on missing any
	 */

	final protected function readValue( $name, $default = null, $checkPersistents = true, $format = null, $optional = false )
	{
		$name = data::isKeyword( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'invalid input variable name' );

		// normalize format once to be used in normalized form
		$format = self::normalizeFormat( $format );

		foreach ( $this->sources as $source )
			if ( $source['enabled'] )
				if ( $checkPersistents || $source['volatile'] )
					if ( $source['manager']->hasValue( $name, $default ) )
					{
						// current source provides value --> validate
						$value = $source['manager']->getValue( $name, $default );

						if ( is_array( $value ) )
						{
							// got an array
							// --> its elements are validated separately
							foreach ( $value as $key => $element )
								if ( $this->valueIsValid( $element, $format ) )
									$value[$key] = $this->convertValue( $element, $format );
								else
									unset( $value[$key] );

							if ( count( $value ) )
								// (real subset of) array is valid -> return that
								return $checkPersistents ? $this->persistValue( $name, $value ) : $value;
						}
						else if ( $this->valueIsValid( $value, $format ) )
						{
							// value is valid -> return
							if ( $checkPersistents )
								$this->persistValue( $name, $value );

							return $this->convertValue( $value, $format );
						}
					}


		// throw exception if missing any input isn't considered valid input
		// according to provided format definition
		if ( !$optional && !$this->valueIsValid( null, $format ) )
			throw new \RuntimeException( 'invalid input' );
	}

	/**
	 * Normalizes provided format definition.
	 *
	 * @param mixed $format one of several sorts of format definition
	 * @return array array with separate definitions for stages of filtering
	 */

	final protected static function normalizeFormat( $format )
	{
		if ( is_null( $format ) )
			return array();

		if ( is_callable( $format ) )
			return $format;

		if ( is_string( $format ) )
			switch ( trim( $format ) )
			{
				case self::FORMAT_STRING :
					return array(
								'valid'   => function( $value, $format ) { return !preg_match( '/<\s*script\W/i', $value ); },
								'convert' => function( $value, $format ) { return strval( $value ); },
								);

				case self::FORMAT_KEYWORD :
					return array(
								'prepare' => function( $value, $format ) { return trim( $value ); },
								'valid'   => '/^[_a-z]+[_a-z0-9]*$/i',
								);

				case self::FORMAT_INTEGER :
					return array(
								'prepare' => function( $value, $format ) { return trim( $value ); },
								'valid'   => '/^[+-]?\d+$/',
								'convert' => function( $value, $format ) { return intval( $value ); },
								);

				case self::FORMAT_BOOL :
					return array(
								'prepare' => function( $value, $format ) { if ( is_string( $value ) ) return trim( $value ); else return !!$value ? 'y' : 'n'; },
								'valid'   => '/^(on|off|y(es)?|no?|true|false|0|1)$/i',
								'convert' => function( $value, $format ) { return in_array( strtolower( $value ), array( 'y', 'yes', 'on', 'true', '1' ) ); }
								);

				case self::FORMAT_GUID :
					return array(
								'prepare' => function( $value, $format ) { return trim( $value ); },
								'valid'   => '/^[\da-f]{8}(-[\da-f]{4}){3}-[\da-f]{12}$/i',
								'convert' => function( $value, $format ) { return strtolower( $value ); }
								);

				default :
					// consider format string containing preg_match pattern
					return array( 'valid' => $format );
			}

		if ( is_array( $format ) )
		{
			if ( !array_key_exists( 'prepare', $format ) &&
				 !array_key_exists( 'valid', $format ) &&
				 !array_key_exists( 'convert', $format ) && count( $format ) )
			{
				// got simple list -> map to hash
				$temp = array();
				$temp['valid']   = array_shift( $format );
				$temp['convert'] = array_shift( $format );
				$temp['prepare'] = array_shift( $format );
				$format = $temp;
			}

			return $format;
		}

		throw new \InvalidArgumentException( 'no such format' );
	}

	/**
	 * Tests if provided value meets criteria of format definition.
	 *
	 * @param mixed $value value to test for matching format criteria
	 * @param mixed $format expected format definition
	 * @return boolean true on valid value
	 */

	final protected function valueIsValid( $value, $format = null )
	{
		$format = self::normalizeFormat( $format );

		if ( is_callable( $format ) )
			return !!call_user_func( $format, $value, 'valid' );

		if ( is_callable( $format['prepare'] ) )
			$value = call_user_func( $format['prepare'], $value, $format );

		if ( is_callable( $format['valid'] ) )
			$valid = call_user_func( $format['valid'], $value, $format );
		else if ( is_bool( $format['valid'] ) )
			$valid = $format['valid'];
		else if ( is_string( $format['valid'] ) && $format['valid'] )
			$valid = preg_match( $format['valid'], $value );
		else
			$valid = is_null( $format['valid'] );

		return $valid;
	}

	/**
	 * Converts provided value according to given format definition.
	 *
	 * @param mixed $value value to convert
	 * @param mixed $format format definition
	 * @return mixed converted value
	 */

	final protected function convertValue( $value, $format = null )
	{
		$format = self::normalizeFormat( $format );

		if ( is_callable( $format ) )
			return call_user_func( $format, $value, 'convert' );

		if ( is_callable( $format['prepare'] ) )
			$value = call_user_func( $format['prepare'], $value, $format );

		if ( is_callable( $format['convert'] ) )
			$value = call_user_func( $format['convert'], $value, $format );

		return $value;
	}

	/**
	 * Persists value in all enabled and persistent input sources.
	 *
	 * @param string $name name of value to persist
	 * @param mixed $value value to persist
	 * @return mixed provided value
	 */

	final protected function persistValue( $name, $value )
	{
		foreach ( $this->sources as $source )
			if ( $source['enabled'] && !$source['volatile'] )
				$source['manager']->persistValue( $name, $value );

		return $value;
	}

	/**
	 * Persists value in all enabled input sources.
	 *
	 * @param string $name name of value to persist
	 * @param mixed $value value to persist
	 * @return mixed provided value
	 */

	final protected function dropValue( $name )
	{
		foreach ( $this->sources as $source )
			if ( $source['enabled'] )
				$source['manager']->dropValue( $name );

		return $value;
	}
}


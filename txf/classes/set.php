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
 * Implementation of array-related tools and operations.
 *
 * @author Thomas Urban <info@toxa.de>
 * @version 1.0
 */


class set
{

	/**
	 * internally managed array containing set's elements.
	 *
	 * @var array
	 */

	protected $data;



	/**
	 * @param array $set array to be managed
	 */

	public function __construct( $set = null )
	{
		if ( func_num_args() >= 1 )
			if ( !is_array( $set ) )
				throw new \InvalidArgumentException( 'invalid initial set' );

		$this->data = $set ? $set : array();
	}

	/**
	 * Conveniently wraps provided array in instance of class set.
	 *
	 * @param array $set
	 * @return set created set managing (copy of) provided data
	 */

	public static function wrap( $set = array() )
	{
		return new static( $set );
	}

	public function __get( $name )
	{
		switch ( $name )
		{
			case 'elements' :
				return $this->data;

			case 'count' :
				return count( $this->data );

			default :
				return $this->read( $name );
		}
	}

	/**
	 * Checks if provided thread pathname is valid for use with methods read(),
	 * write(), has() and remove().
	 *
	 * @param string $path path to check
	 * @return boolean true on provided path is valid, false otherwise
	 */

	public static function isValidThreadPath( $path )
	{
		return string::isString( $path ) && preg_match( '/^([^.]+\.)*[^.]+$/', strval( $path ) );
	}

	/**
	 * Detects if provided array is considered hash or set.
	 *
	 * Sets have positive integer keys, only. Due to supporting removing of
	 * elements and slicing set those integer keys mustn't be linear.
	 *
	 * Hashes are arrays that are not considered set, thus having at least one
	 * key including non-digit character.
	 *
	 * @return boolean true on provided array being hash
	 */

	public static function isHash( $set )
	{
		assert( 'is_array( $set );' );

		return !!count( array_filter( array_keys( $set ), function( $a ) { return preg_match( '/\D/', strval( $a ) ); } ) );
	}

	/**
	 * Detects if set includes subset selectable by provided path.
	 *
	 * @param string $path path selecting subset
	 * @return boolean true if subset is available, false otherwise
	 */

	public function has( $path )
	{
		if ( !static::isValidThreadPath( $path ) )
			throw new \InvalidArgumentException( 'invalid thread path' );

		try
		{
			static::processThread( $this->data, $result, $path );

			return true;
		}
		catch ( \OutOfBoundsException $e )
		{
			return false;
		}
		catch ( \UnexpectedValueException $e )
		{
			return false;
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
	 * @param mixed $default default to use
	 * @return mixed found subset, $default on missing
	 */

	public function read( $path, $default = null )
	{
		if ( !static::isValidThreadPath( $path ) )
			throw new \InvalidArgumentException( 'invalid thread path' );

		try
		{
			$cb = function( $stage, &$scope, &$result, $passed, $current, $next )
			{
				if ( $stage == set::STAGE_MATCH )
					$result = $scope;
			};

			static::processThread( $this->data, $result, $path, null, $cb );

			return $result;
		}
		catch ( \OutOfBoundsException $e )
		{
			return $default;
		}
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
		if ( !static::isValidThreadPath( $path ) )
			throw new \InvalidArgumentException( 'invalid thread path' );

		$cb = function( $stage, &$scope, &$result, $passed, $current, $next ) use ($value)
		{
			switch ( $stage )
			{
				case set::STAGE_MISSING :
					if ( count( $next ) )
						$scope[$current] = array();
					else
						$scope[$current] = null;
					break;

				case set::STAGE_MATCH :
					$result = true;
					break;

				case set::STAGE_BUBBLE :
					if ( $result )
					{
						$scope[$current] = $value;
						$result = false;
					}
					break;
			}
		};

		$result = false;

		static::processThread( $this->data, $result, $path, null, $cb );
	}

	/**
	 * Removes subset selected by path.
	 *
	 * On removing a subset this method is removing any containing
	 * superordinated subsets that got empty after removal as well, unless
	 * $autoReduce is set false.
	 *
	 * @param string $path path to subset to remove
	 * @param boolean $autoReduce see description
	 */

	public function remove( $path, $autoReduce = true )
	{
		if ( !static::isValidThreadPath( $path ) )
			throw new \InvalidArgumentException( 'invalid thread path' );

		$cb = function( $stage, &$scope, &$result, $passed, $current, $next ) use ($autoReduce)
		{
			switch ( $stage )
			{
				case set::STAGE_BUBBLE :
					if ( $result === 1 )
					{
						// remove selected subset from its superordinated scope
						unset( $scope[$current] );
						$result++;
					}
					else if ( $result && $autoReduce )
					{
						// removed selected subset before
						// --> autoreduce unless disabled by caller
						if ( empty( $scope[$current] ) )
						{
							unset( $scope[$current] );

							if ( count( $scope ) )
								$result = false;
						}
						else
							$result = false;
					}

					break;

				case set::STAGE_MATCH :
					$result = 1;
					break;
			}
		};

		$result = false;

		static::processThread( $this->data, $result, $path, null, $cb );
	}

	/**
	 * Appends provided value at end of current set.
	 *
	 * @param mixed $value element to append
	 * @return mixed appended element
	 */

	public function push( $value )
	{
		array_push( $this->data, $value );

		return $value;
	}

	/**
	 * Prepends provided value at beginning of current set.
	 *
	 * @param mixed $value element to prepend
	 * @return mixed prepended element
	 */

	public function unshift( $value )
	{
		array_unshift( $this->data, $value );

		return $value;
	}

	/**
	 * Removes and returns last element in set.
	 *
	 * @return mixed removed element
	 */

	public function pop()
	{
		return array_pop( $this->data );
	}

	/**
	 * Renives abd returns first element in set.
	 *
	 * @return mixed
	 */

	public function shift()
	{
		return array_shift( $this->data );
	}

	/**
	 * Signals callback invocation on missing selected subset.
	 */

	const STAGE_MISSING = 'missing';

	/**
	 * Signals callback invocation before descending into subset.
	 */

	const STAGE_DESCEND = 'descend';

	/**
	 * Signals callback invocation after returning from descending into subset.
	 */

	const STAGE_BUBBLE  = 'bubble';

	/**
	 * Signals callback invocation on selected subset.
	 */

	const STAGE_MATCH   = 'match';

	/**
	 * Descends into subsets of set selected by thread's pathname for processing
	 * passed and finally selected element(s) using callback.
	 *
	 * @throws \OutOfBoundsException on missing a selected subset
	 * @throws \UnexpectedValueException on trying to descend into leaf node
	 *
	 * @param array $array current scope of set
	 * @param string|array $path left path elements used for descending
	 * @return mixed reference on selected subset
	 */

	protected function processThread( &$array, &$result, $path, $passedPath = null, $processor = null )
	{
		if ( !is_array( $path ) )
			$path = preg_split( '/\./', $path );

		if ( !is_array( $passedPath ) )
			$passedPath = array();


		if ( count( $path ) )
		{
			// extract next element from path
			$name = trim( array_shift( $path ) );

			if ( ctype_digit( $name ) && ( $name > 0 ) )
				// prefering 1-based indices in thread pathnames
				$name -= 1;

			// ensure to have selected element
			if ( !\array_key_exists( $name, $array ) )
				if ( is_callable( $processor ) )
					$processor( self::STAGE_MISSING, $array, $result, $passedPath, $name, $path );

			if ( !\array_key_exists( $name, $array ) )
				throw new \OutOfBoundsException( 'selected thread not found' );

			// throw exception on trying to pass leaf node
			if ( count( $path ) && !is_array( $array[$name] ) )
				throw new \UnexpectedValueException( 'cannot descend into existing leaf node' );

			// descend into subset
			if ( is_callable( $processor ) )
				$processor( self::STAGE_DESCEND, $array, $result, $passedPath, $name, $path );

			static::processThread( $array[$name], $result, $path, array_merge( $passedPath, array( $name ) ), $processor );

			if ( is_callable( $processor ) )
				$processor( self::STAGE_BUBBLE, $array, $result, $passedPath, $name, $path );
		}
		else
			if ( is_callable( $processor ) )
				$processor( self::STAGE_MATCH, $array, $result, $passedPath, null, $path );
	}

	/**
	 * Converts current set into XML document.
	 *
	 * @param string $rootTagName tag name of root node
	 * @return SimpleXMLElement XML data
	 */

	public function toXml( $rootTagName = 'root' )
	{
		$xml = static::array2Xml( $this->data, null, $rootTagName );

		return simplexml_load_string( "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n$xml" );
	}

	/**
	 * Creates new set from provided XML document.
	 *
	 * @param string|\SimpleXMLElement $xml xml
	 * @return set set representing data found in XML
	 */

	public static function fromXml( $xml )
	{
		if ( string::isString( $xml ) )
			$xml = simplexml_load_string( (string) $xml );

		if ( !( $xml instanceof \SimpleXMLElement ) )
			throw new \InvalidArgumentException( 'invalid XML document' );

		return _A( static::xml2Array( $xml ) );
	}

	public function extendFromXml( $xml )
	{
		if ( string::isString( $xml ) )
			$xml= simplexml_load_string( (string) $xml );

		if ( !( $xml instanceof \SimpleXMLElement ) )
			throw new \InvalidArgumentException( 'invalid XML document' );

		$this->data = static::xml2Array( $xml, $this->data );

		return $this;
	}

	/**
	 * Converts XML-notated data into array.
	 *
	 * @param \SimpleXMLElement $xml XML thread to convert
	 * @param array $level array to be extended/replaced by extracted XML data
	 * @return array array representing data found in XML
	 */

	protected static function xml2Array( \SimpleXMLElement $xml, $level = array() )
	{
		$overlay = array();

		foreach ( $xml->children() as $sub )
		{
			// get name of element to convert
			$name = $sub->getName();

			// want to extend some existing data of element?
			$extend = trim( $sub['extend'] );
			$extend = $extend ? preg_match( '/^on|yes|true|y|extend$/i', $extend )
							  : array_key_exists( $name, $overlay );


			// prepare to actually extend some existing data
			if ( array_key_exists( $name, $overlay ) )
				$existing = $overlay[$name];
			else if ( array_key_exists( $name, $level ) )
				$existing = $level[$name];
			else
			{
				$existing = array();
				$extend   = false;
			}

			if ( $extend )
				// start new set initially containing previously collected sole element
				$overlay[$name] = is_array( $existing ) && !static::isHash( $existing ) ? $existing : array( $existing );


			// get described data of current element
			$value = self::xml2Array( $sub, $extend || !is_array( $existing ) ? array() : $existing );


			if ( $extend )
				// merge with existing data
				$overlay[$name][] = $value;
			else
				// set data replacing some existing data
				$overlay[$name] = $value;
		}


		// apply created overlay to initially provided level of data
		foreach ( $overlay as $name => $value )
			$level[$name] = $value;


		if ( count( $level ) )
			return $level;

		if ( (string) $xml === '' )
			return array();

		return data::autoType( _S($xml,'utf-8')->asUtf8 );
	}

	/**
	 * Recursively converts provided value to XML thread.
	 *
	 * @param mixed $set data to convert
	 * @param string $indent indentation to use on value
	 * @param string $name name used to wrap around converted data
	 * @return string XML notation of provided data
	 */

	protected static function array2Xml( $set, $indent = null, $name = 'root' )
	{
		$name = _S($name)->asUtf8;

		if ( !xml::isValidName( $name ) )
			throw new \UnexpectedValueException( 'invalid element name cannot be converted to XML' );


		if ( is_array( $set ) )
		{
			// there are subordinated elements to be converted into XML nodes
			//
			// collect hash-like and set-like elements separately
			$separateName = $sameName = array();

			foreach ( $set as $subName => $sub )
				if ( ctype_digit( trim( $subName ) ) )
					$sameName[] = static::array2Xml( $sub, $indent, $name );
				else
					$separateName[$subName] = static::array2Xml( $sub, "$indent ", $subName );

			// exclude to have both sorts of elements (as it can't be mapped into XML notation)
			if ( count( $separateName ) && count( $sameName ) )
				throw new \UnexpectedValueException( 'unsupported state of mixing hash-like and set-like array in converting to XML' );

			// convert elements to XML
			if ( count( $separateName ) )
				return "$indent<$name>\n" . implode( '', $separateName ) . "$indent</$name>\n";
			else if ( count( $sameName ) )
			{
				// prevent multiple root nodes here
				if ( strval( $indent ) === '' )
					if ( count( $set ) > 1 )
						throw new \UnexpectedValueException( 'cannot convert array with multiple root elements into XML' );

				return implode( '', $sameName );
			}
			else
				// support empty elements
				return "$indent<$name/>\n";
		}
		else
		{
			// it's a leaf node
			// -> convert to element containing single text element
			if ( string::isString( $set ) )
				$set = _S($set)->asUtf8;
			else
				$set = data::asAutoTypable( $set );

			return "$indent<$name>$set</$name>\n";
		}
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
		{
			if ( $extension instanceof self )
				$this->data = array_merge_recursive( $this->data, $extension->data );
			else
				$this->data = array_merge_recursive( $this->data, _A($extension)->data );
		}

		return $this;
	}

	/**
	 * Extracts hash elements from provided array.
	 * 
	 * This method is available to normalize any data to be pure hash.
	 * 
	 * @param mixed $in array to be reduced, arbitrary data to be ignored
	 * @return array all hash elements of provided input, empty array on non-array input
	 */

	public static function asHash( $in )
	{
		$out = array();

		if ( is_array( $in ) )
			foreach ( $in as $key => $value )
				if ( !is_integer( $key ) )
					$out[$key] = $value;

		return $out;
	}
}

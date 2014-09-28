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


class browseable_array implements browseable
{
	protected $_items = array();

	protected $_name = null;

	protected $_sortBy = null;

	protected $_window = array( 'offset' => 0, 'size' => 0 );


	/**
	 * @param array[array] $array set of data records to make browseable
	 * @param string $strName some name for use in attribute "class" describing data
	 * @throws \InvalidArgumentException on missing/invalid set of data
	 */

	public function __construct( $array, $strName = null )
	{
		if ( !is_array( $array ) )
			throw new \InvalidArgumentException( 'no array data to make browseable' );

		$this->_items = $array;

		$strName = trim( data::asString( $strName ) );
		if ( $strName !== '' )
			$this->_name = $strName;
	}

	/**
	 * Creates new instance making provided array "browseable".
	 *
	 * @param array[array] $array set of data records to make browseable
	 * @param string $strName some name for use in attribute "class" describing data
	 * @return browseable
	 */

	public static function create( $array, $strName = null )
	{
		return new static( $array, $strName );
	}


	/**
	 * Requests to fetch data sorted by selected property in selected order.
	 *
	 * @note Multiple requests for sorting by some property are cumulated to
	 *       sort by either property in decreasing priority. Succeeding calls of
	 *       sortyBy() will be ignored on datasources not supporting sorting by
	 *       multiple properties.
	 *
	 * @param string $property name of property to sort data by
	 * @param boolean $ascending true for sorting by property in ascending order
	 * @return browseable current instance
	 * @throws \InvalidArgumentException on missing/invalid property name
	 */

	public function sortBy( $property, $ascending = true )
	{
		$property = trim( data::asString( $property ) );
		if ( $property === '' )
			throw new \InvalidArgumentException( 'invalid property name' );

		$this->_sortBy = array( $property, !!$ascending );
	}

	/**
	 * Requests to fetch selected number of items on fetching data.
	 *
	 * @param integer $count number of items to skip on fetching
	 * @return browseable current instance
	 * @throws \InvalidArgumentException on providing non-integer or negative value
	 */

	public function offset( $count )
	{
		if ( ctype_digit( trim( $count ) ) )
			$this->_window['offset'] = intval( $count );
		else
			throw new \InvalidArgumentException( 'invalid offset of browseable excerpt' );

		return $this;
	}

	/**
	 * Requests to fetch selected number of items at most.
	 *
	 * @param integer $count number of items to fetch at most
	 * @return browseable current instance
	 * @throws \InvalidArgumentException on providing non-integer or negative value
	 */

	public function size( $count )
	{
		if ( ctype_digit( trim( $count ) ) )
			$this->_window['size'] = intval( $count );
		else
			throw new \InvalidArgumentException( 'invalid size of browseable excerpt' );

		return $this;
	}


	/**
	 * Fetches items matching all previous criteria.
	 *
	 * @return array[array] items fetched from datasource
	 */

	public function items()
	{
		if ( $this->_sortBy )
		{
			$property  = $this->_sortBy[0];
			$ascending = $this->_sortBy[1];

			uasort( $this->_items, function( $left, $right ) use ( $property, $ascending )
			{
				if ( !is_array( $left ) )
					$left = null;
				else if ( !array_key_exists( $property, $left ) )
					$left = null;
				else
					$left = data::asString( $left[$property] );

				if ( !is_array( $right ) )
					$right = null;
				else if ( !array_key_exists( $property, $right ) )
					$right = null;
				else
					$right = data::asString( $right[$property] );


				if ( $left === null && $right === null )
					return 0;

				if ( $right === null )
					$result = 1;
				else if ( $left === null )
					$result = -1;
				else
					$result = strcasecmp( $left, $right );


				return $ascending ? $result : -$result;
			} );
		}


		if ( $this->_window['size'] > 0 )
			return array_slice( $this->_items, $this->_window['offset'], $this->_window['size'], true );

		return array_slice( $this->_items, $this->_window['offset'], null, true );
	}

	/**
	 * Fetches number of items in datasource.
	 *
	 * @return integer number of items in datasource
	 */

	public function count()
	{
		return count( $this->_items );
	}

	/**
	 * Retrieves name describing current datasource/data in detail.
	 *
	 * This name is designed to be used in class-attributes of HTML code
	 * genereated by consumers.
	 *
	 * @return string|null name of datasource, e.g. "users", "ldap-tree", "authenticated-users", null on an unnamed datasource
	 */

	public function name()
	{
		return $this->_name;
	}
}

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
 * Common pager controller implementation
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 *
 */

class pager implements widget
{
	/**
	 * name of input parameter selecting first item to show
	 *
	 * @var string
	 */

	protected $offsetName;

	/**
	 * name of input parameter selecting number of items per page
	 *
	 * @var string
	 */

	protected $sizeName;

	/**
	 * number of items to be pages
	 *
	 * @var integer
	 */

	protected $itemCount;

	/**
	 * mark on whether pager input is volatile or not
	 *
	 * if true, both input parameter are volatile, if false, both are persisting
	 * if null, size is persisting while offset is volatile
	 *
	 * @var boolean
	 */

	protected $isVolatile;

	/**
	 * mark on whether using buttons instead of links or not
	 *
	 * @var boolean
	 */

	protected $useButtons;



	/**
	 * @param integer $itemCount number of records to be paged
	 * @param boolean $isVolatile if true, offset/items per page must be always found in actual input
	 *                            if false, both are retrieved from cache unless contained in actual input
	 *                            if null, offset is expected in actual input while size is not
	 * @param string $offsetName name of input selecting offset
	 * @param string $sizeName name of input selecting count of items per page
	 */

	public function __construct( $itemCount, $isVolatile = null, $offsetName = 'skip', $sizeName = 'count' )
	{
		if ( !ctype_digit( trim( $itemCount ) ) )
			throw new \InvalidArgumentException( 'invalid item count' );

		if ( !data::isKeyword( $offsetName ) )
			throw new \InvalidArgumentException( 'invalid offset selector' );

		if ( !data::isKeyword( $sizeName ) )
			throw new \InvalidArgumentException( 'invalid size selector' );


		$this->offsetName = $offsetName;
		$this->sizeName   = $sizeName;
		$this->itemCount  = $itemCount;

		$this->isVolatile = is_null( $isVolatile ) ? null : !!$isVolatile;
	}

	/**
	 * Enables/disables buttons to use instead of links on rendering pager.
	 *
	 * @param boolean $enableButtons true to select use of buttons instead of links
	 * @return \de\toxa\txf\pager current pager instance
	 */

	public function enableButtons( $enableButtons )
	{
		$this->useButtons = !!$enableButtons;

		return $this;
	}

	/**
	 * Retrieves current number of items to show per page.
	 *
	 * @return integer number of items per page
	 */

	public function size()
	{
		$config = config::get( 'pager.size.default', 20 );
		$size   = $this->isVolatile ? input::vget( $this->sizeName, $config, input::FORMAT_INTEGER )
									: input::get( $this->sizeName, $config, input::FORMAT_INTEGER );

		return max( config::get( 'pager.size.min', 5 ), min( config::get( 'pager.size.max', 100 ), $size ) );
	}

	public function showFullFinalPage()
	{
		return ( config::get( 'pager.offset.final', 'full' ) == 'full' );
	}

	/**
	 * Retrieves current number of records to skip.
	 *
	 * @return integer number of items to skip
	 */

	public function offset()
	{
		$offset = ( $this->isVolatile !== false ) ? input::vget( $this->offsetName, 0, input::FORMAT_INTEGER )
												  : input::get( $this->offsetName, 0, input::FORMAT_INTEGER );

		return max( 0, min( $this->itemCount - ( $this->showFullFinalPage() ? $this->size() : 1 ), $offset ) );
	}

	/**
	 * Applies current pager state on provided query.
	 *
	 * @param datasource\query $query query to be limited according to pager
	 */

	public function applyOnQuery( datasource\query $query )
	{
		$query->limit( $this->size(), $this->offset() );
	}

	/**
	 * Retrieves pager prepared for use with given datasource query.
	 *
	 * @param datasource\query $query datasource query pager gets used with
	 * @return pager pager instance prepared for use with query
	 */

	public static function createOnQuery( datasource\query $query )
	{
		$pager = new static( $query->execute( true )->cell() );

		$pager->applyOnQuery( $query );

		return $pager;
	}

	public function processInput()
	{
		return $this;
	}

	/**
	 * Renders pager widget.
	 *
	 * @return string code describing pager widget
	 */

	public function getCode()
	{
		$size   = $this->size();
		$offset = $this->offset();


		// compile data to use on rendering pager
		$setup  = array(
					'sizeName'    => $this->sizeName,
					'offsetName'  => $this->offsetName,
					'itemCount'   => $this->itemCount,
					'pageOffsets' => array(),
					'sizes'       => config::getList( 'pager.sizeOption', array( 10, 20, 50, 100 ) ),
					'size'        => $size,
					'offset'      => $offset,
					'useButtons'  => $this->useButtons,
					);

		for ( $i = $offset; $i > -$size; $i -= $size )
			array_unshift( $setup['pageOffsets'], max( 0, $i ) );

		$setup['currentPage'] = count( $setup['pageOffsets'] ) - 1;

		for ( $i = $offset + $size; $i < $this->itemCount; $i += $size )
			array_push( $setup['pageOffsets'], min( $this->itemCount - ( $this->showFullFinalPage() ? $size : 1 ), $i ) );



		// render pager using template
		return view::render( 'widgets/pager', $setup );
	}

	public function __toString()
	{
		return $this->getCode();
	}
}


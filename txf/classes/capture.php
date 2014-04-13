<?php
/**
 * Copyright (c) 2013-2014, cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban <thomas.urban@cepharum.de>
 * @project: Cepharum.Config
 */

namespace de\toxa\txf;


/**
 * Implements capture buffer for collecting string chunk by chunk.
 *
 * @package de\toxa\txf
 */

class capture {

	/**
	 * Marks position in captured string any wrapped content gets inserted at.
	 */

	const middleMarker = '%%%txf_CODE_MIDDLE_MARKER%%%';

	/**
	 * content of capture buffer
	 *
	 * @var string
	 */

	protected $_captured;


	public function __construct()
	{
		$this->reset();
	}

	/**
	 * Creates new capture instance.
	 *
	 * This is available for getting instance ready for chained calls.
	 *
	 * @return capture
	 */

	public static function create()
	{
		return new static;
	}

	/**
	 * Drops all previously captured content.
	 *
	 * @return $this
	 */

	public function reset()
	{
		$this->_captured = static::middleMarker;

		return $this;
	}

	/**
	 * Puts (additional) content into capture buffer.
	 *
	 * @param mixed $strContent content to be captured, gets converted to string
	 *                          internally using data::asString()
	 * @param bool $blnPrepend true for prepending any existing content
	 *
	 * @return $this
	 */

	public function put( $strContent, $blnPrepend = false )
	{
		if ( $strContent === false )
			return $this->reset();

		$strContent = data::asString( $strContent );

		if ( $blnPrepend )
			$this->_captured = $strContent . $this->_captured;
		else
			$this->_captured .= $strContent;

		return $this;
	}

	/**
	 * Retrieves all previously captured content from current buffer.
	 *
	 * @return string
	 */

	public function get()
	{
		return $this->wrap( '' );
	}

	/**
	 * Retrieves provided content wrapped in content of current capture buffer.
	 *
	 * @param mixed $strContent content to be wrapped, gets converted to string
	 *                          internally using data::asString()
	 * @return string
	 */

	public function wrap( $strContent )
	{
		return str_replace( static::middleMarker, data::asString( $strContent ),
							$this->_captured );
	}
}

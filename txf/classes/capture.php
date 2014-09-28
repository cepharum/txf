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

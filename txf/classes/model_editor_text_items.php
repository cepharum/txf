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

class model_editor_text_items extends model_editor_text
{
	protected $minCount = 0;
	protected $maxCount = 0;
	protected $separator = ';';


	public function __construct()
	{
		$this
			->collapseWhitespace()
			->trim();
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$input = parent::normalize( $input, $property, $editor );

		if ( $input !== null ) {
			$junctor = $this->separator[0];

			$items = preg_split( '/[' . preg_quote( $this->separator ) . ']/', $input );
			$items = array_map( function( $n ) { return trim( $n ); }, $items );
			$items = array_filter( $items, function( $n ) { return $n !== ''; } );

			$input = implode( "$junctor ", $items );
		}

		return $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		parent::validate( $input, $property, $editor );

		$items = preg_split( '/[' . preg_quote( $this->separator ) . ']/', $input );

		if ( $this->minCount > 0 && $this->minCount > count( $items ) )
			throw new \InvalidArgumentException( _L('Provide additional information here!') );

		if ( $this->maxCount > 0 && $this->maxCount < count( $items ) )
			throw new \InvalidArgumentException( _L('Provide less information here!') );

		return true;
	}

	/**
	 * Request to require minimum number of items in list of elements separated
	 * by given separator.
	 *
	 * @param integer $count minimum count of items
	 * @return $this
	 */

	public function minimumCount( $count )
	{
		$this->minCount = intval( $count );

		return $this;
	}

	/**
	 * Request to require maximum number of items in list of elements separated
	 * by given separator.
	 *
	 * @param integer $count maximum count of items
	 * @return $this
	 */

	public function maximumCount( $count )
	{
		$this->maxCount = intval( $count );

		return $this;
	}

	/**
	 * Request to split input into elements using one of given characters for
	 * separating input into chunks.
	 *
	 * @note First provided separator is considered major separator and thus
	 *       used on normalizing for joining split elements back into string.
	 * @note Default separator is semicolon.
	 *
	 * @param string $separators string containing all supported separators
	 * @return $this
	 */

	public function separator( $separator )
	{
		$this->separator = strval( $separator );

		return $this;
	}
}

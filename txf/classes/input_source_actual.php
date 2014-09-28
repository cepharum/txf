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
 * Implementation of input source accessing actually available script input.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 */


class input_source_actual implements input_source
{
	const METHOD_POST = 'POST';
	const METHOD_GET = 'GET';


	protected $set;


	public function __construct( $method )
	{
		switch ( $method )
		{
			case self::METHOD_POST :
				$this->set =& $_POST;
				break;

			case self::METHOD_GET :
				$this->set =& $_GET;
				break;

			default :
				throw new \InvalidArgumentException( 'invalid method' );
		}
	}

	public function isVolatile()
	{
		return true;
	}

	public function hasValue( $name )
	{
		return array_key_exists( $name, $this->set );
	}

	public function getValue( $name )
	{
		return $this->set[$name];
	}

	public function persistValue( $name, $value )
	{
	}

	public function dropValue( $name )
	{
		unset( $this->set[$name] );
	}

	public function getAllValues()
	{
		return $this->set;
	}
}


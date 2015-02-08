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
 * Implementation of input source accessing session for stateful input data.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 */


class input_source_stateful implements input_source
{
	protected $set;


	public function __construct()
	{
		$this->set =& txf::session( session::SCOPE_CLASS + session::SCOPE_SCRIPT, 'input_source_stateful' );
	}

	public function isVolatile()
	{
		return false;
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
		$this->set[$name] = $value;
	}

	public function dropValue( $name )
	{
		unset( $this->set[$name] );
	}

	public function listNames()
	{
		return array_keys( $this->set );
	}
}


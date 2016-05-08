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

require_once 'classes/shortcuts.php';
require_once 'classes/data.php';

class dataTest extends \PHPUnit_Framework_TestCase
{
	public function testAutotypePassingData()
	{
		$this->assertEquals( null, data::autoType( null ) );
		$this->assertEquals( 0, data::autoType( 0 ) );
		$this->assertEquals( 100, data::autoType( 100 ) );
		$this->assertEquals( 0.0, data::autoType( 0.0 ) );
		$this->assertEquals( 123.4, data::autoType( 123.4 ) );
		$this->assertEquals( false, data::autoType( false ) );
		$this->assertEquals( true, data::autoType( true ) );
		$this->assertEquals( array( 1, 0 ), data::autoType( array( 1, 0 ) ) );
		$this->assertEquals( array( 'c' => 5, 'a' => 6.5, 1 => true ), data::autoType( array( 'c' => 5, 'a' => 6.5, 1 => true ) ) );
		$this->assertEquals( '', data::autoType( '' ) );
		$this->assertEquals( 'some string', data::autoType( 'some string' ) );
	}

	public function testAutotypeActing()
	{
		$this->assertEquals( null, data::autoType( 'null' ) );
		$this->assertEquals( null, data::autoType( 'nULl' ) );
		$this->assertEquals( 0, data::autoType( '0' ) );
		$this->assertEquals( 100, data::autoType( '100' ) );
		$this->assertEquals( -100, data::autoType( "-100 \n\r " ) );
		$this->assertEquals( 0.0, data::autoType( ' 0.0' ) );
		$this->assertEquals( 123.4, data::autoType( "\x09 123.4 " ) );
		$this->assertEquals( -123.465, data::autoType( "\x09 -123.465 " ) );
		$this->assertEquals( false, data::autoType( 'fALse' ) );
		$this->assertEquals( false, data::autoType( 'nO' ) );
		$this->assertEquals( false, data::autoType( 'oFf' ) );
		$this->assertEquals( true, data::autoType( 'trUe' ) );
		$this->assertEquals( true, data::autoType( 'YeS' ) );
		$this->assertEquals( true, data::autoType( 'oN' ) );
	}


}

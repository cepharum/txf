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
require_once 'classes/path.php';

class pathTest extends \PHPUnit_Framework_TestCase
{
	public function testRelativeToAnother()
	{
		$this->assertEquals( '', path::relativeToAnother( '', '' ) );

		$this->assertEquals( '', path::relativeToAnother( 'alphA', 'alphA' ) );
		$this->assertEquals( '', path::relativeToAnother( 'alphA/bEta', 'alphA/bEta' ) );
		$this->assertEquals( false, path::relativeToAnother( 'alphA/bEta', 'alphA/betA' ) );

		$this->assertEquals( 'betA', path::relativeToAnother( 'alphA', 'alphA/betA' ) );
		$this->assertEquals( 'betA', path::relativeToAnother( 'alphA/', 'alphA/betA' ) );
		$this->assertEquals( 'betA/gammA', path::relativeToAnother( 'alphA/', 'alphA/betA/gammA' ) );
		$this->assertEquals( false, path::relativeToAnother( '/alphA/', 'alphA/betA/gammA' ) );
		$this->assertEquals( 'betA/gammA', path::relativeToAnother( '/alphA/', '/alphA/betA/gammA' ) );
	}

	public function testGlue()
	{
		$this->assertEquals( 'a', path::glue( 'a' ) );
		$this->assertEquals( 'a', path::glue( 'a', '' ) );
		$this->assertEquals( 'a', path::glue( '', 'a' ) );
		$this->assertEquals( 'a', path::glue( '', 'a', '' ) );

		$this->assertEquals( 'a/b', path::glue( 'a/b' ) );
		$this->assertEquals( 'a/b', path::glue( 'a/b', '' ) );
		$this->assertEquals( 'a/b', path::glue( '', 'a/b' ) );
		$this->assertEquals( 'a/b', path::glue( '', 'a/b', '' ) );
		$this->assertEquals( 'a/b', path::glue( 'a', 'b' ) );
		$this->assertEquals( 'a/b', path::glue( 'a', '/b' ) );
		$this->assertEquals( 'a/b', path::glue( 'a/', 'b' ) );
		$this->assertEquals( 'a/b', path::glue( 'a/', '/b' ) );

		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA', 'bETa' ) );
		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA/', 'bETa' ) );
		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA', '/bETa' ) );
		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA/', '/bETa' ) );

		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA//', '/bETa' ) );
		$this->assertEquals( 'alPHA/bETa', path::glue( 'alPHA//', '//bETa' ) );

		$this->assertEquals( '/alPHA/bETa', path::glue( '/alPHA//', '/bETa' ) );
		$this->assertEquals( '/alPHA/bETa', path::glue( '/alPHA//', '//bETa' ) );

		$this->assertEquals( '//alPHA/bETa', path::glue( '//alPHA//', '//bETa' ) );
		$this->assertEquals( '//alPHA/bETa', path::glue( '//alPHA//', '////bETa' ) );
		$this->assertEquals( '////alPHA/bETa', path::glue( '////alPHA//', '////bETa' ) );

		$this->assertEquals( '/alPHA/bETa', path::glue( '', '/alPHA//', '/bETa' ) );
		$this->assertEquals( '/alPHA/bETa', path::glue( '', '/alPHA//', '//bETa' ) );

		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '/bETa/gaMMa' ) );
		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '//bETa/gaMMa' ) );

		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '/bETa/gaMMa/' ) );
		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '//bETa/gaMMa/', '' ) );
		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '/bETa/gaMMa///' ) );
		$this->assertEquals( 'alPHA/bETa/gaMMa', path::glue( 'alPHA//', '//bETa/gaMMa///', '' ) );
	}

	public function testSplit()
	{
		$this->assertEquals( array( '/alpha/beta/gamma/delta' ), path::split( '/alpha/beta/gamma/delta', 100 ) );
		$this->assertEquals( array( '/alpha/beta' ), path::split( '/alpha/beta/gamma/delta', 2 ) );
		$this->assertEquals( array( '/alpha/beta', 'gamma/delta' ), path::split( '/alpha/beta/gamma/delta', 2, 2 ) );
	}

	public function testAddTrailingSlash()
	{
		$this->assertEquals( '/', path::addTrailingSlash( '' ) );

		$this->assertEquals( 'a/', path::addTrailingSlash( 'a' ) );

		$this->assertEquals( 'alphA/', path::addTrailingSlash( 'alphA' ) );
		$this->assertEquals( 'alphA/', path::addTrailingSlash( 'alphA/' ) );
		$this->assertEquals( 'alphA//', path::addTrailingSlash( 'alphA//' ) );

		$this->assertEquals( '/alphA/', path::addTrailingSlash( '/alphA' ) );
		$this->assertEquals( '/alphA/', path::addTrailingSlash( '/alphA/' ) );
		$this->assertEquals( '/alphA//', path::addTrailingSlash( '/alphA//' ) );
		$this->assertEquals( '///alphA//', path::addTrailingSlash( '///alphA//' ) );
	}
}

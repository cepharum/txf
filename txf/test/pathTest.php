<?php

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
<?php

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
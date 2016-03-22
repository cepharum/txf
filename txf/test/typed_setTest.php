<?php

namespace de\toxa\txf;

require_once 'classes/shortcuts.php';
require_once 'classes/set.php';
require_once 'classes/typed_set.php';
require_once 'classes/xml.php';


class testClassA
{
	public $test;
}

class testClassB extends testClassA
{
	public $testTwo;
}


class typed_setTest extends \PHPUnit_Framework_TestCase
{
	public function testWrappingWrongTypedElements()
	{
		$this->assertEquals( array(), typed_set::wrap( array(), 'integer' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 'test' ), 'integer' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( false ), 'integer' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( true ), 'integer' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( '1' ), 'integer' )->elements );

		$this->assertEquals( array(), typed_set::wrap( array( array( true ) ), 'integer' )->elements );
		$this->assertEquals( array( array() ), typed_set::wrap( array( array() ), 'integer' )->elements );
	}

	public function testWrappingFilteringElementsByType()
	{
		$source = array( 1, 2.0, '3', true, false );

		$this->assertEquals( array( 1 ), typed_set::wrap( $source, 'integer' )->elements );
		$this->assertEquals( array( 2.0 ), typed_set::wrap( $source, 'double' )->elements );
		$this->assertEquals( array( '3' ), typed_set::wrap( $source, 'string' )->elements );
		$this->assertEquals( array( true, false ), typed_set::wrap( $source, 'boolean' )->elements );
	}

	public function testWrappingNotSupportingTypeArray()
	{
		$this->assertEquals( array(), typed_set::wrap( array(), 'array' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 1 ), 'array' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( array( 1 ) ), 'array' )->elements );
		$this->assertEquals( array( array() ), typed_set::wrap( array( array() ), 'array' )->elements );
	}

	public function testWrappingFilteringArraysHashesAndMultiLevel()
	{
		$this->assertEquals( array( 1 ), typed_set::wrap( array( 1 ), 'integer' )->elements );
		$this->assertEquals( array( 'a' => 'b' ), typed_set::wrap( array( 1, 'a' => 'b' ), 'string' )->elements );
		$this->assertEquals( array( 1 ), typed_set::wrap( array( 5 => 1, 'a' => 'b' ), 'integer' )->elements );
		$this->assertEquals( array( 'c' => 1 ), typed_set::wrap( array( 'c' => 1, 'a' => 'b' ), 'integer' )->elements );
		$this->assertEquals( array( 1, 6 ), typed_set::wrap( array( 5 => 1, 'a' => 'b', 3 => 6 ), 'integer' )->elements );
		$this->assertEquals( array( array( 1, 6 ) ), typed_set::wrap( array( 5 => array( 1, 'a' => 'b', 3 => 6 ), 'd' => 'e' ), 'integer' )->elements );
	}

	public function testWrappingObjects()
	{
		$object = simplexml_load_string( '<?xml version="1.0"?><test/>' );

		$this->assertEquals( array( $object ), typed_set::wrap( array( $object ), 'SimpleXMLElement' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'stdClass' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassA' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassB' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'string' )->elements );

		$object = (object) array( 'test' => null );

		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'SimpleXMLElement' )->elements );
		$this->assertEquals( array( $object ), typed_set::wrap( array( $object ), 'stdClass' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassA' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassB' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'string' )->elements );

		$object = new testClassA;

		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'SimpleXMLElement' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'stdClass' )->elements );
		$this->assertEquals( array( $object ), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassA' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassB' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'string' )->elements );

		$object = new testClassB;

		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'SimpleXMLElement' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'stdClass' )->elements );
		$this->assertEquals( array( $object ), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassA' )->elements );
		$this->assertEquals( array( $object ), typed_set::wrap( array( $object ), 'de\toxa\txf\testClassB' )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( $object ), 'string' )->elements );
	}

	public function testWrappingUsingCallbackByNameAsType()
	{
		$validator = 'is_string';

		$this->assertEquals( array(), typed_set::wrap( array( 1 ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'b' ), typed_set::wrap( array( 1, 'a' => 'b' ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'b' ), typed_set::wrap( array( 5 => 1, 'a' => 'b' ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'b' ), typed_set::wrap( array( 5 => 1, 'a' => 'b', 3 => 6, false ), $validator )->elements );
		$this->assertEquals( array( 5 => array( 'a' => 'b' ), 'd' => 'e' ), typed_set::wrap( array( 5 => array( 1, false, 'a' => 'b', 3 => 6 ), 'd' => 'e' ), $validator )->elements );
	}

	public function testWrappingUsingImmediateCallbackAsType()
	{
		$validator = function( $value ) { return in_array( $value, array( 'g', 5, true ), true ); };

		$this->assertEquals( array(), typed_set::wrap( array( 1 ), $validator )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 1, 'a' => 'b' ), $validator )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 5 => 1, 'a' => 'b' ), $validator )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 5 => 1, 'a' => 'b', 3 => 6, false ), $validator )->elements );
		$this->assertEquals( array(), typed_set::wrap( array( 5 => array( 1, false, 'a' => 'b', 3 => 6 ), 'd' => 'e' ), $validator )->elements );

		$this->assertEquals( array( 5 ), typed_set::wrap( array( 5 ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'g' ), typed_set::wrap( array( 1, 'a' => 'g' ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'g' ), typed_set::wrap( array( 5 => 1, 'a' => 'g' ), $validator )->elements );
		$this->assertEquals( array( 'a' => 'g', 3 => 5, true ), typed_set::wrap( array( 'a' => 'g', 3 => 5, true ), $validator )->elements );
		$this->assertEquals( array( 'd' => 'g' ), typed_set::wrap( array( 5 => array( 1, false, 'a' => 'b', 3 => 6 ), 'd' => 'g' ), $validator )->elements );
	}

	public function testWrappingUsingImmediateCallbackAsType2()
	{
		$trigger   = 5;
		$validator = function( $value ) use ($trigger) { return ( $value >= $trigger ); };

		$this->assertEquals( array( 7, 9, 6, 9 ), typed_set::wrap( array( 3, 7, 4, 9, 2, 3, 6, 9, 1 ), $validator )->elements );

		$trigger   = 3;
		$validator = function( $value ) use ($trigger) { return ( $value >= $trigger ); };
		$this->assertEquals( array( 3, 7, 4, 9, 3, 6, 9 ), typed_set::wrap( array( 3, 7, 4, 9, 2, 3, 6, 9, 1 ), $validator )->elements );
	}

	public function testWritingValueToTypedSet()
	{
		$set = typed_set::wrap( array(), 'integer' );

		$this->assertEquals( 0, $set->count );

		$set->write( 'test', 1 );

		$this->assertEquals( 1, $set->count );

		$set->write( 'test', 2 );

		$this->assertEquals( 1, $set->count );

		$set->write( 'taste', 2 );

		$this->assertEquals( 2, $set->count );
	}

	/**
	 * @expectedException InvalidArgumentException
	 */

	public function testWritingValueToTypedSetWrongType()
	{
		$set = typed_set::wrap( array(), 'integer' );
		$set->write( 'test', '1' );
	}
}


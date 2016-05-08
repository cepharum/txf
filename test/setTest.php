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
require_once 'classes/set.php';
require_once 'classes/xml.php';

class setTest extends \PHPUnit_Framework_TestCase
{
	protected $set;

	protected $previous;

	protected function setUp()
	{
		$this->previous = string::encodingDetectionOrder( 'utf-8' );

		$this->set = new set( array(
								'context' => array(
										'applications' => array(
												'unittest' => array(
														'path'   => '/home/unit/test/public_html',
														'icons'  => array(
																		'small', 'medium', 'large'
																		),
														'stages' => array(
																		'initial', 'runtime', 'shutdown', 'fallback' => 'disabled',
																		),
														),
												'default' => 'unittest',
												),
										'threads' => array(
												'active' => array(
														'newsreader' => array(
																'pid' => 200,
																)
														)
												)
										)
								) );
	}

	protected function tearDown()
	{
		string::encodingDetectionOrder( $this->previous );
	}

	public function testWrapping()
	{
		$this->assertThat( _A(), $this->isInstanceof( 'de\toxa\txf\set' ) );
		$this->assertThat( _A(1), $this->isInstanceof( 'de\toxa\txf\set' ) );
		$this->assertThat( _A(1,'ddf'), $this->isInstanceof( 'de\toxa\txf\set' ) );
		$this->assertThat( _A(array(1,'d')), $this->isInstanceof( 'de\toxa\txf\set' ) );
		$this->assertThat( _A(true,array(1,'d'),5.0), $this->isInstanceof( 'de\toxa\txf\set' ) );
	}

	public function testWrapping2()
	{
		$this->assertEquals( 0, _A()->count );
		$this->assertEquals( 1, _A(1)->count );
		$this->assertEquals( 2, _A(1,'ddf')->count );
		$this->assertEquals( 2, _A(array(1,'d'))->count );
		$this->assertEquals( 3, _A(true,array(1,'d'),5.0)->count );
	}

	public function testConvertingFromXml()
	{
		$this->assertEquals( array( _S('Hello World!','utf-8') ), set::fromXml( _S('<root>Hello World!</root>','utf-8') )->elements );
	}

	public function testConvertingFromXml2()
	{
		$this->assertEquals( array( 'major' => array( _S('Hello','utf-8'), _S('World!','utf-8') ) ), set::fromXml( _S('<root><major>Hello</major> <major>World!</major></root>','utf-8') )->elements );
	}

	public function testConvertingFromXml3()
	{
		$this->assertEquals( array( 'major' => _S('Hello','utf-8'), 'minor' => _S('World!','utf-8') ), set::fromXml( _S('<root><major>Hello</major> <minor>World!</minor></root>','utf-8') )->elements );
	}

	public function testConvertingFromXml4()
	{
		$this->assertEquals( array( 'major' => _S('Hello','utf-8'), 'minor' => _S('World!','utf-8') ), set::fromXml( _S('<root><major>Hello</major> old <minor>World!</minor></root>','utf-8') )->elements );
	}

	public function testConvertingFromXml5()
	{
		$this->assertEquals( array( 'major' => array() ), set::fromXml( _S('<root><major/></root>','utf-8') )->elements );
	}

	public function testConvertingFromXml6()
	{
		$string = _S("\x1A\x040\x04:\x04 \x00B\x045\x041\x04O\x04 \x007\x04>\x042\x04C\x04B\x04?\x00",'utf-16le');
		$data   = base64_encode( serialize( $string ) );

		$kak_tebja_sawut = "\xD0\x9A\xD0\xB0\xD0\xBA \xD1\x82\xD0\xB5\xD0\xB1\xD1\x8F \xD0\xB7\xD0\xBE\xD0\xB2\xD1\x83\xD1\x82?";

		$source = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<root>
 <messages>
  <message>
   <en>Hello World!</en>
   <de>Hallo Welt!</de>
  </message>
  <message>
   <en>What's your name?</en>
   <de>Wie hei\xC3\x9Ft du?</de>
   <ru>$kak_tebja_sawut</ru>
  </message>
  <message/>
 </messages>
 <data>=?8bit?B?$data?=</data>
</root>
EOT;

		$expected = array(
						'messages' => array(
											'message' => array(
																array(
																	'en' => _S('Hello World!'),
																	'de' => _S('Hallo Welt!'),
																	),
																array(
																	'en' => _S('What\'s your name?'),
																	'de' => _S("Wie hei\xC3\x9Ft du?"),
																	'ru' => _S($kak_tebja_sawut),
																	),
																array(),
															),
											),
						'data' => $string,
						);

		$this->assertEquals( $expected, set::fromXml( $source )->elements );
	}

	public function testConvertingToXml()
	{
		$source = array(
						'Hello World!'
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<root>Hello World!</root>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml()->saveXML() ) );
	}

	/**
	 * @expectedException UnexpectedValueException
	 */

	public function testConvertingToXml2()
	{
		$source = array(
						'Hello',
						'World!',
						);

		_A($source)->toXml();
	}

	/**
	 * @expectedException UnexpectedValueException
	 */

	public function testConvertingToXml3()
	{
		$source = array(
						'Hello World!',
						);

		_A($source)->toXml( '/test' );
	}

	/**
	 * @expectedException UnexpectedValueException
	 */

	public function testConvertingToXml4()
	{
		$source = array(
						'/test' => 'Hello World!',
						);

		_A($source)->toXml();
	}

	public function testConvertingToXml5()
	{
		$source = array(
						'Hello World!',
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test>Hello World!</test>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	public function testConvertingToXml6()
	{
		$source = array();

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test/>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	public function testConvertingToXml7()
	{
		$source = array(
						'message' => 'Hello World!',
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test>
 <message>Hello World!</message>
</test>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	public function testConvertingToXml8()
	{
		$source = array(
						'prefix' => 'Hello',
						'suffix' => 'World!',
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test>
 <prefix>Hello</prefix>
 <suffix>World!</suffix>
</test>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	public function testConvertingToXml9()
	{
		$source = array(
						'message' => array(
										'prefix' => 'Hello',
										'suffix' => 'World!',
										),
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test>
 <message>
  <prefix>Hello</prefix>
  <suffix>World!</suffix>
 </message>
</test>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	public function testConvertingToXml10()
	{
		$source = array(
						'message' => array(
										'Hello',
										'World!',
										),
						);

		$expected = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<test>
 <message>Hello</message>
 <message>World!</message>
</test>
EOT;

		$this->assertEquals( $expected, trim( _A($source)->toXml( 'test' )->saveXML() ) );
	}

	/**
	 * @expectedException UnexpectedValueException
	 */

	public function testConvertingToXml11()
	{
		$source = array(
						'message' => array(
										'Hello',
										'World!',
										'prefix' => 'test',
										),
						);

		_A($source)->toXml();
	}

	public function testXmlConversionConsistency()
	{
		$source = array(
						'message' => array(
										_S('Hello'),
										_S('World!'),
										),
						);

		$this->assertEquals( $source, set::fromXml( _A($source)->toXml() )->elements );
	}

	public function testXmlConversionConsistency2()
	{
		$source = array(
						'message' => array(
										_S('Hello'),
										_S('World!'),
										),
						'data' => _A(),
						);

		$this->assertEquals( $source, set::fromXml( _A($source)->toXml() )->elements );
	}

	public function testXmlConversionReverseConsistency()
	{
		$data = base64_encode( serialize( _A( '1', 5, 'test', null, false ) ) );

		$kak_tebja_sawut = "\xD0\x9A\xD0\xB0\xD0\xBA \xD1\x82\xD0\xB5\xD0\xB1\xD1\x8F \xD0\xB7\xD0\xBE\xD0\xB2\xD1\x83\xD1\x82?";

		$source = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<root>
 <messages>
  <message>
   <en>Hello World!</en>
   <de>Hallo Welt!</de>
  </message>
  <message>
   <en>What's your name?</en>
   <de>Wie heißt du?</de>
   <ru>$kak_tebja_sawut</ru>
  </message>
  <message/>
 </messages>
 <data>=?8bit?B?$data?=</data>
</root>
EOT;

		$this->assertEquals( $source, trim( set::fromXml( $source )->toXml()->saveXML() ) );
	}

	public function testValidatingThreadPath()
	{
		$this->assertEquals( false, set::isValidThreadPath( false ) );
		$this->assertEquals( false, set::isValidThreadPath( array() ) );
		$this->assertEquals( false, set::isValidThreadPath( '' ) );

		$this->assertEquals( true, set::isValidThreadPath( '1' ) );
		$this->assertEquals( true, set::isValidThreadPath( 'context' ) );
		$this->assertEquals( true, set::isValidThreadPath( 'context.threads' ) );
		$this->assertEquals( true, set::isValidThreadPath( 'context.threads.active.newsreader.pid' ) );
		$this->assertEquals( true, set::isValidThreadPath( 'context.threads.active.newsreader.pid.200' ) );

		$this->assertEquals( false, set::isValidThreadPath( '.1' ) );
		$this->assertEquals( false, set::isValidThreadPath( '1.' ) );
		$this->assertEquals( false, set::isValidThreadPath( '.1.' ) );
		$this->assertEquals( false, set::isValidThreadPath( '1.2..3' ) );
	}

	public function testTestingThread()
	{
		$this->assertEquals( true, $this->set->has( 'context' ) );
		$this->assertEquals( false, $this->set->has( 'threads' ) );
		$this->assertEquals( true, $this->set->has( 'context.threads' ) );
		$this->assertEquals( true, $this->set->has( 'context.threads.active.newsreader.pid' ) );
		$this->assertEquals( false, $this->set->has( 'context.threads.active.newsreader.pid.200' ) );
		$this->assertEquals( false, $this->set->has( 'context.1.active.newsreader.pid' ) );
		$this->assertEquals( true, $this->set->has( 'context.applications.unittest.icons.1' ) );
		$this->assertEquals( true, $this->set->has( 'context.applications.unittest.icons.2' ) );
		$this->assertEquals( true, $this->set->has( 'context.applications.unittest.icons.3' ) );
		$this->assertEquals( false, $this->set->has( 'context.applications.unittest.icons.4' ) );
	}

	public function testReadingThread()
	{
		$this->assertType( 'array', $this->set->read( 'context' ) );
		$this->assertType( 'array', $this->set->read( 'context.threads' ) );
		$this->assertType( 'array', $this->set->read( 'threads', array() ) );
		$this->assertType( 'null', $this->set->read( 'threads' ) );
		$this->assertType( 'int', $this->set->read( 'context.threads.active.newsreader.pid' ) );
		$this->assertSame( 200, $this->set->read( 'context.threads.active.newsreader.pid' ) );
		$this->assertType( 'array', $this->set->read( 'context.applications.unittest.icons' ) );
		$this->assertType( 'string', $this->set->read( 'context.applications.default' ) );
		$this->assertType( 'string', $this->set->read( 'context.applications.unittest.icons.2' ) );
		$this->assertSame( 'medium', $this->set->read( 'context.applications.unittest.icons.2' ) );
		$this->assertSame( 'unittest', $this->set->read( 'context.applications.default' ) );
		$this->assertEquals( array( 'small', 'medium', 'large' ), $this->set->read( 'context.applications.unittest.icons' ) );
		$this->assertEquals( array( 'newsreader' => array( 'pid' => 200 ) ), $this->set->read( 'context.threads.active' ) );
	}

	public function testWritingThread()
	{
		$this->set->write( 'context.drives', array( 'hda', 'usbcd0', 'stick' ) );
		$this->assertType( 'array', $this->set->read( 'context.drives' ) );
		$this->assertSame( 'usbcd0', $this->set->read( 'context.drives.2' ) );

		$this->assertType( 'string', $this->set->read( 'context.applications.default' ) );
		$this->set->write( 'context.applications.default', null );
		$this->assertType( 'null', $this->set->read( 'context.applications.default' ) );
		$this->set->write( 'context.applications.default', 5 );
		$this->assertType( 'int', $this->set->read( 'context.applications.default' ) );
		$this->assertSame( 5, $this->set->read( 'context.applications.default' ) );
	}

	public function testRemovingThread()
	{
		$this->assertSame( true, $this->set->has( 'context.threads.active.newsreader.pid' ) );
		$this->set->remove( 'context.threads.active.newsreader.pid', false );
		$this->assertSame( false, $this->set->has( 'context.threads.active.newsreader.pid' ) );

		$this->assertSame( true, $this->set->has( 'context.threads.active' ) );
		$this->set->remove( 'context.threads.active.newsreader', true );
		$this->assertSame( false, $this->set->has( 'context.threads.active' ) );
		$this->assertSame( false, $this->set->has( 'context.threads' ) );
		$this->assertSame( true, $this->set->has( 'context.applications' ) );
		$this->assertSame( true, $this->set->has( 'context' ) );

		$this->set->remove( 'context.applications.default', true );
		$this->assertSame( true, $this->set->has( 'context.applications' ) );
		$this->set->remove( 'context.applications.unittest', true );
		$this->assertSame( false, $this->set->has( 'context.applications' ) );
		$this->assertSame( false, $this->set->has( 'context' ) );

		$this->assertSame( 0, count( $this->set->elements ) );
	}

	public function testExtending()
	{
		$this->assertSame( false, $this->set->has( 'people' ) );
		$this->set->extend( array( 'people' => array() ) );
		$this->assertSame( true, $this->set->has( 'people' ) );

		$this->assertSame( false, $this->set->has( 'context.servers' ) );
		$this->set->extend( array( 'context' => array( 'servers' => array( 'www.test.com', 'mail.test.com' ) ) ) );
		$this->assertSame( true, $this->set->has( 'context.servers' ) );
		$this->assertSame( true, $this->set->has( 'context.servers.2' ) );

		$this->assertSame( false, $this->set->has( 'context.1' ) );
		$this->assertSame( false, $this->set->has( 'context.2' ) );
		$this->set->extend( array( 'context' => 'token' ) );
		$this->assertSame( true, $this->set->has( 'context.1' ) );
		$this->assertSame( false, $this->set->has( 'context.2' ) );
		$this->assertSame( 'token', $this->set->read( 'context.1' ) );

		$this->assertSame( false, $this->set->has( '1' ) );
		$this->assertSame( false, $this->set->has( '2' ) );
		$this->set->extend( 'marker' );
		$this->assertSame( true, $this->set->has( '1' ) );
		$this->assertSame( false, $this->set->has( '2' ) );
		$this->assertSame( 'marker', $this->set->read( '1' ) );
	}

	public function testExtending2()
	{
		$this->assertSame( false, $this->set->has( 'people' ) );
		$this->assertSame( false, $this->set->has( 'context.servers' ) );
		$this->assertSame( false, $this->set->has( 'context.1' ) );
		$this->assertSame( false, $this->set->has( 'context.2' ) );
		$this->assertSame( false, $this->set->has( '1' ) );
		$this->assertSame( false, $this->set->has( '2' ) );

		$this->set->extend(
						array( 'people' => array() ),
						array( 'context' => array( 'servers' => array( 'www.test.com', 'mail.test.com' ) ) ),
						array( 'context' => 'token' ),
						'marker'
						);

		$this->assertSame( true, $this->set->has( 'people' ) );
		$this->assertSame( true, $this->set->has( 'context.servers' ) );
		$this->assertSame( true, $this->set->has( 'context.servers.2' ) );
		$this->assertSame( true, $this->set->has( 'context.1' ) );
		$this->assertSame( false, $this->set->has( 'context.2' ) );
		$this->assertSame( 'token', $this->set->read( 'context.1' ) );
		$this->assertSame( true, $this->set->has( '1' ) );
		$this->assertSame( false, $this->set->has( '2' ) );
		$this->assertSame( 'marker', $this->set->read( '1' ) );
	}

	public function testShifting()
	{
		$set = set::wrap( array( 1, true, 2.0, '3.0' ) );

		$this->assertEquals( 4, $set->count );
		$this->assertEquals( 1, $set->shift() );
		$this->assertEquals( 3, $set->count );
		$this->assertEquals( true, $set->shift() );
		$this->assertEquals( 2, $set->count );
		$this->assertEquals( 2.0, $set->shift() );
		$this->assertEquals( 1, $set->count );
		$this->assertEquals( '3.0', $set->shift() );
		$this->assertEquals( 0, $set->count );
	}

	public function testShifting2()
	{
		$this->assertEquals( 1, $this->set->count );
		$this->assertType( 'array', $set->shift() );
		$this->assertEquals( 0, $this->set->count );
	}

	public function testPopping()
	{
		$set = set::wrap( array( 1, true, 2.0, '3.0' ) );

		$this->assertEquals( 4, $set->count );
		$this->assertEquals( '3.0', $set->pop() );
		$this->assertEquals( 3, $set->count );
		$this->assertEquals( 2.0, $set->pop() );
		$this->assertEquals( 2, $set->count );
		$this->assertEquals( true, $set->pop() );
		$this->assertEquals( 1, $set->count );
		$this->assertEquals( 1, $set->pop() );
		$this->assertEquals( 0, $set->count );
	}
}

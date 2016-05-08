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
require_once 'classes/string.php';
require_once 'classes/set.php';

class stringTest extends \PHPUnit_Framework_TestCase
{
	protected $long;

	protected $short;

	protected $previous;


	protected function setUp()
	{
		if ( !\extension_loaded( 'mbstring' ) )
			$this->markTestSkipped( 'missing mbstring extension' );

		$this->previous = string::encodingDetectionOrder( 'utf-7,utf-8,iso-8859-15' );

		// "Mäßige Zuwächse im €-Raum laut Societé" in UTF7
		$this->long = _S('M+AOQA3w-ige Zuw+AOQ-chse im +IKw--Raum laut Societ+AOk-');

		// "€-Societé"
		$this->short = _S('+IKw--Societ+AOk-');
	}

	protected function tearDown()
	{
		string::encodingDetectionOrder( $this->previous );
	}

	public function provideEncodedStringsAndLengths()
	{
		return array(
					array( '', null, 0, 0 ),
					array( 'a', null, 1, 1 ),
					array( 'test', 'utf-8', 4, 4 ),
					array( "t\xc3\xa4st", 'utf-8', 4, 5 ),
					array( "Ren\xC3\xA9 wei\xC3\x9Ft W\xC3\xA4nde", 'utf-8', 16, 19 ),
					array( "&#1086;&#1073;&#1088;&#1072;&#1079;&#1077;&#1094;", 'html', 7, 49 ),
					array( "&#1086;&#1073;&#1088;&#1072;&#1079;&#1077;&#1094;", null, 49, 49 ),
					array( "образец", 'utf-8', 7, 14 ),
					array( "\xCF\xC2\xD2\xC1\xDA\xC5\xC3", 'KOI8-R', 7, 7 ),
					array( "+BD4EMQRABDAENwQ1BEY-", 'utf-7', 7, 21 ),
					);
	}

	public function provideEncodedStringsForSearching()
	{
		return array(
					// haystack, encoding, needle, encoding, expected-position
					array( null, null, null, null, false ),
					array( null, null, 'a', null, false ),
					array( 'a', null, null, null, false ),
					array( 'test', 'utf-8', 'est', 'iso-8859-15', 1 ),
					array( "t\xE4st", 'iso-8859-15', "\xC3\xA4st", 'utf-8', 1 ),
					array( "t\xC3\xA4st", 'utf-8', "\xe4st", 'iso-8859-15', 1 ),
					array( "m\xC3\xA4\xC3\x9Fig", 'utf-8', "\xDFig", 'iso-8859-15', 2 ),
					array( "m&auml;&szlig;ig", 'html', "\xDFig", 'iso-8859-15', 2 ),
					array( "m&auml;&szlig;ig", 'html', "\xC3\x9Fig", 'utf-8', 2 ),
					array( "m&auml;&szlig;ig", 'html', "\xC3\x9Fig", 'utf-8', 2 ),
					);
	}

	public function testWrapping()
	{
		$this->assertThat( string::wrap( 'test' ), $this->isInstanceOf( 'de\toxa\txf\string' ) );
		$this->assertThat( _S( 'test' ), $this->isInstanceOf( 'de\toxa\txf\string' ) );
		$this->assertThat( _S( '' ), $this->isInstanceOf( 'de\toxa\txf\string' ) );
		$this->assertThat( _S( 4 ), $this->isInstanceOf( 'de\toxa\txf\string' ) );

		$this->assertThat( _S( array( 4, 5 ) ), $this->isInstanceOf( 'de\toxa\txf\string' ) );
		$this->assertEquals( 'Array', _S( array( 4, 5 ) )->string );
	}

	/**
	 * @expectedException \Exception
	 */

	public function testWrapping2()
	{
		$this->assertThat( _S( (object) array( 4, 5 ) ), $this->isInstanceOf( 'de\toxa\txf\string' ) );
	}

	public function testWrapping3()
	{
		$this->assertEquals( _S('Array'), _S(array( 1, 2 )) );
		$this->assertEquals( _S('1'), _S(1) );
		$this->assertEquals( _S('1'), _S(true) );

		$this->assertEquals( _S(''), _S(array( 1, 2 ),null,'') );
		$this->assertEquals( _S(''), _S(1,null,'') );
		$this->assertEquals( _S(''), _S(true,null,'') );
	}

	public function testWrappingKeepsEncoding()
	{
		$this->assertEquals( 38, _S("\x00\x00\x00M\x00\x00\x00\xE4\x00\x00\x00\xDF\x00\x00\x00i\x00\x00\x00g\x00\x00\x00e\x00\x00\x00 \x00\x00\x00Z\x00\x00\x00u\x00\x00\x00w\x00\x00\x00\xE4\x00\x00\x00c\x00\x00\x00h\x00\x00\x00s\x00\x00\x00e\x00\x00\x00 \x00\x00\x00i\x00\x00\x00m\x00\x00\x00 \x00\x00 \xAC\x00\x00\x00-\x00\x00\x00R\x00\x00\x00a\x00\x00\x00u\x00\x00\x00m\x00\x00\x00 \x00\x00\x00l\x00\x00\x00a\x00\x00\x00u\x00\x00\x00t\x00\x00\x00 \x00\x00\x00S\x00\x00\x00o\x00\x00\x00c\x00\x00\x00i\x00\x00\x00e\x00\x00\x00t\x00\x00\x00\xE9",'utf-32be')->length );
		$this->assertEquals( 152, _S("\x00\x00\x00M\x00\x00\x00\xE4\x00\x00\x00\xDF\x00\x00\x00i\x00\x00\x00g\x00\x00\x00e\x00\x00\x00 \x00\x00\x00Z\x00\x00\x00u\x00\x00\x00w\x00\x00\x00\xE4\x00\x00\x00c\x00\x00\x00h\x00\x00\x00s\x00\x00\x00e\x00\x00\x00 \x00\x00\x00i\x00\x00\x00m\x00\x00\x00 \x00\x00 \xAC\x00\x00\x00-\x00\x00\x00R\x00\x00\x00a\x00\x00\x00u\x00\x00\x00m\x00\x00\x00 \x00\x00\x00l\x00\x00\x00a\x00\x00\x00u\x00\x00\x00t\x00\x00\x00 \x00\x00\x00S\x00\x00\x00o\x00\x00\x00c\x00\x00\x00i\x00\x00\x00e\x00\x00\x00t\x00\x00\x00\xE9",'utf-32be')->bytes );
		$this->assertEquals( 38, _S("M\xE4\xDFige Zuw\xE4chse im \xA4-Raum laut Societ\xE9",'iso-8859-15')->length );
		$this->assertEquals( 38, _S("M\xE4\xDFige Zuw\xE4chse im \xA4-Raum laut Societ\xE9",'iso-8859-15')->bytes );
		$this->assertEquals( 38, _S("M+AOQA3w-ige Zuw+AOQ-chse im +IKw--Raum laut Societ+AOk-",'utf-7')->length );
		$this->assertEquals( 56, _S("M+AOQA3w-ige Zuw+AOQ-chse im +IKw--Raum laut Societ+AOk-",'utf-7')->bytes );
		$this->assertEquals( 60, _S("TcOkw59pZ2UgWnV3w6RjaHNlIGltIOKCrC1SYXVtIGxhdXQgU29jaWV0w6k=",'base64')->length );
		$this->assertEquals( 60, _S("TcOkw59pZ2UgWnV3w6RjaHNlIGltIOKCrC1SYXVtIGxhdXQgU29jaWV0w6k=",'base64')->bytes );
	}

	public function testWrappingDetectsEncoding()
	{
		$this->assertEquals( 'ISO-8859-15', _S("\x00\x00\x00M\x00\x00\x00\xE4\x00\x00\x00\xDF\x00\x00\x00i\x00\x00\x00g\x00\x00\x00e\x00\x00\x00 \x00\x00\x00Z\x00\x00\x00u\x00\x00\x00w\x00\x00\x00\xE4\x00\x00\x00c\x00\x00\x00h\x00\x00\x00s\x00\x00\x00e\x00\x00\x00 \x00\x00\x00i\x00\x00\x00m\x00\x00\x00 \x00\x00 \xAC\x00\x00\x00-\x00\x00\x00R\x00\x00\x00a\x00\x00\x00u\x00\x00\x00m\x00\x00\x00 \x00\x00\x00l\x00\x00\x00a\x00\x00\x00u\x00\x00\x00t\x00\x00\x00 \x00\x00\x00S\x00\x00\x00o\x00\x00\x00c\x00\x00\x00i\x00\x00\x00e\x00\x00\x00t\x00\x00\x00\xE9")->encoding );
		$this->assertEquals( 'ISO-8859-15', _S("\x00M\x00\xE4\x00\xDF\x00i\x00g\x00e\x00 \x00Z\x00u\x00w\x00\xE4\x00c\x00h\x00s\x00e\x00 \x00i\x00m\x00  \xAC\x00-\x00R\x00a\x00u\x00m\x00 \x00l\x00a\x00u\x00t\x00 \x00S\x00o\x00c\x00i\x00e\x00t\x00\xE9")->encoding );
		$this->assertEquals( 'UTF-8', _S("M\xC3\xA4\xC3\x9Fige Zuw\xC3\xA4chse im \xE2\x82\xAC-Raum laut Societ\xC3\xA9")->encoding );
		$this->assertEquals( 'ISO-8859-15', _S("M\xE4\xDFige Zuw\xE4chse im \xA4-Raum laut Societ\xE9")->encoding );
		$this->assertEquals( 'UTF-7', _S("M+AOQA3w-ige Zuw+AOQ-chse im +IKw--Raum laut Societ+AOk-")->encoding );
		$this->assertEquals( 'UTF-7', _S("Hello World")->encoding );
	}

	public function testGettingProperties()
	{
		$this->assertEquals( 9, $this->short->length );
		$this->assertEquals( 17, $this->short->bytes );
		$this->assertEquals( '+IKw--Societ+AOk-', $this->short->string );
		$this->assertEquals( 'UTF-7', $this->short->encoding );
		$this->assertEquals( "\xE2\x82\xAC-Societ\xC3\xA9", $this->short->asUtf8 );
		$this->assertEquals( "\xA4-Societ\xE9", $this->short->asIso8859_15 );
	}

	public function testCheckingEncoding()
	{
		$this->assertEquals( true, $this->short->isEncoding( 'utf-7' ) );
		$this->assertEquals( true, $this->short->isEncoding( 'UtF-7' ) );
		$this->assertEquals( false, $this->short->isEncoding( 'UtF7' ) );
		$this->assertEquals( false, $this->short->isEncoding( 'UTF-32' ) );
	}

	/**
	 * @dataProvider provideEncodedStringsAndLengths
	 */

	public function testGettingLengthAndBytecount( $string, $encoding, $length, $byteCount )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( $length, $instance->length );
		$this->assertEquals( $byteCount, $instance->bytes );
	}

	public function testConvertingTo()
	{
		$this->assertEquals( "M\x00\x00\x00\xE4\x00\x00\x00\xDF\x00\x00\x00i\x00\x00\x00g\x00\x00\x00e\x00\x00\x00 \x00\x00\x00Z\x00\x00\x00u\x00\x00\x00w\x00\x00\x00\xE4\x00\x00\x00c\x00\x00\x00h\x00\x00\x00s\x00\x00\x00e\x00\x00\x00 \x00\x00\x00i\x00\x00\x00m\x00\x00\x00 \x00\x00\x00\xAC \x00\x00-\x00\x00\x00R\x00\x00\x00a\x00\x00\x00u\x00\x00\x00m\x00\x00\x00 \x00\x00\x00l\x00\x00\x00a\x00\x00\x00u\x00\x00\x00t\x00\x00\x00 \x00\x00\x00S\x00\x00\x00o\x00\x00\x00c\x00\x00\x00i\x00\x00\x00e\x00\x00\x00t\x00\x00\x00\xE9\x00\x00\x00", (string) $this->long->convertTo('UtF-32Le') );
		$this->assertEquals( "M\xE4\xDFige Zuw\xE4chse im \x80-Raum laut Societ\xE9", (string) $this->long->convertTo('winDOWs-1252') );
	}

	public function testComparing()
	{
		// "€-Societé"
		$this->assertEquals( 0, $this->short->compare( _S("\xA4-Societ\xE9") ) );
		$this->assertGreaterThan( 0, $this->short->compare( _S("\xA4-SOciet\xC9") ) );
		$this->assertLessThan( 0, $this->short->compare( _S("\xA4-sOciet\xC9") ) );
	}

	public function testComparingWithoutCase()
	{
		// "€-Societé"
		$this->assertEquals( 0, _S('+IKw--Societ+AOk-')->compareNoCase( _S("\xA4-Societ\xE9") ) );
		$this->assertEquals( 0, _S('+IKw--Societ+AOk-')->compareNoCase( _S("\xA4-SOciet\xC9") ) );
		$this->assertEquals( 0, _S('+IKw--Societ+AOk-')->compareNoCase( _S("\xA4-sOciet\xC9") ) );

		$this->assertGreaterThan( 0, _S('+IKw--tociet+AOk-')->compareNoCase( _S("\xA4-Societ\xE9") ) );
		$this->assertGreaterThan( 0, _S('+IKw--Tociet+AOk-')->compareNoCase( _S("\xA4-SOciet\xC9") ) );
		$this->assertGreaterThan( 0, _S('+IKw--Societ+AOk-.')->compareNoCase( _S("\xA4-sOciet\xC9") ) );

		$this->assertLessThan( 0, _S('+IKw--pociet+AOk-')->compareNoCase( _S("\xA4-Societ\xE9") ) );
		$this->assertLessThan( 0, _S('+IKw--Pociet+AOk-')->compareNoCase( _S("\xA4-SOciet\xC9") ) );
		$this->assertLessThan( 0, _S('+IKw--ociet+AOk-')->compareNoCase( _S("\xA4-sOciet\xC9") ) );
	}

	public function testExtractingSubstring()
	{
		$this->assertEquals( "+IKw--Societ+AOk-", (string) $this->short->substr( 0 ) );
		$this->assertEquals( "+IKw--Societ+AOk-", (string) $this->short->substr( 0, 14 ) );
		$this->assertEquals( "+IKw--Soc", (string) $this->short->substr( 0, 5 ) );
		$this->assertEquals( "-Societ+AOk-", (string) $this->short->substr( 1 ) );
		$this->assertEquals( "-Societ+AOk-", (string) $this->short->substr( 1, 14 ) );
		$this->assertEquals( "-Societ", (string) $this->short->substr( 1, -1 ) );
		$this->assertEquals( "+IKw--Societ", (string) $this->short->substr( 0, -1 ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearching( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( $position, $instance->indexOf( _S($needle,$needleEncoding) ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearchingWithOffsetOne( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( ( $position > 0 ) ? $position : false , $instance->indexOf( _S($needle,$needleEncoding), 1 ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearchingWithOffsetTwo( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( ( $position > 1 ) ? $position : false , $instance->indexOf( _S($needle,$needleEncoding), 2 ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearchingWithOffsetThree( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( false , $instance->indexOf( _S($needle,$needleEncoding), 3 ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearchingReversely( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( $position, $instance->lastIndexOf( _S($needle,$needleEncoding) ) );
	}

	/**
	 * @dataProvider provideEncodedStringsForSearching
	 */

	public function testSearchingReverselyWithOffsetOne( $string, $encoding, $needle, $needleEncoding, $position )
	{
		$instance = _S($string,$encoding);

		$this->assertEquals( false, $instance->lastIndexOf( _S($needle,$needleEncoding), 1 ) );
	}

	public function testSearchingForward()
	{
		$this->assertEquals( 19, $this->long->indexOf( _S("\x00\x00 \xAC",'utf-32') ) );
		$this->assertEquals( false, $this->long->indexOf( _S("\x00\x00 \xAD",'utf-32') ) );
		$this->assertEquals( 1, $this->long->indexOf( _S('+AOQ-') ) );
		$this->assertEquals( 1, $this->long->indexOf( _S('+AOQ-'), 1 ) );
		$this->assertEquals( 10, $this->long->indexOf( _S('+AOQ-'), 2 ) );
	}

	public function testSearchingBackward()
	{
		$this->assertEquals( 10, $this->long->lastIndexOf( _S("\x00\x00\x00\xE4",'utf-32') ) );
		$this->assertEquals( 10, $this->long->lastIndexOf( _S("\x00\x00\x00\xE4",'utf-32'), 11 ) );
		$this->assertEquals( 10, $this->long->lastIndexOf( _S('+AOQ-'), 27 ) );
		$this->assertEquals( 1, $this->long->lastIndexOf( _S('+AOQ-'), 28 ) );
	}

	public function testParsingUrlQuery()
	{
		$query  = "%c3%a4=gr%c3%b6%c3%9fe&%c3%ad=ren%c3%a9";
		$parsed = string::parseString( $query );

		$this->assertEquals( 2, count( $parsed ) );
		$this->assertEquals( _S("gr\xC3\xB6\xC3\x9Fe"), $parsed[_S("\xC3\xA4")->string] );
		$this->assertEquals( _S("ren\xC3\xA9"), $parsed[_S("\xC3\xAD")->string] );
	}

	public function testLoweringCase()
	{
		$this->assertEquals( _S('m+AOQA3w-ige zuw+AOQ-chse im +IKw--raum laut societ+AOk-'), $this->long->toLower() );
		$this->assertEquals( _S('+IKw--societ+AOk-'), $this->short->toLower() );
	}

	public function testUpperingCase()
	{
		$this->assertEquals( _S('M+AMQA3w-IGE ZUW+AMQ-CHSE IM +IKw--RAUM LAUT SOCIET+AMk-'), $this->long->toUpper() );
		$this->assertEquals( _S('+IKw--SOCIET+AMk-'), $this->short->toUpper() );
	}

	public function testLimiting()
	{
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->limit( 20, _S('...') ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->limit( 9, _S('...') ) );
		$this->assertEquals( _S('+IKw--Soc...'), $this->short->limit( 8, _S('...') ) );
		$this->assertEquals( _S('+IKw--Socie+ICY-'), $this->short->limit( 8 ) );
	}

	public function testChunking()
	{
		$this->assertEquals( 9, count( $this->short->chunked( 1 ) ) );
		$this->assertEquals( 5, count( $this->short->chunked( 2 ) ) );
		$this->assertEquals( 3, count( $this->short->chunked( 3 ) ) );
		$this->assertEquals( 3, count( $this->short->chunked( 4 ) ) );
		$this->assertEquals( 2, count( $this->short->chunked( 5 ) ) );
		$this->assertEquals( 2, count( $this->short->chunked( 8 ) ) );
		$this->assertEquals( 1, count( $this->short->chunked( 9 ) ) );
		$this->assertEquals( array( _S('+IKw--Soc'), _S('iet+AOk-') ), $this->short->chunked( 5 ) );
	}

	public function testReplacing()
	{
		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( _S('o'), _S('O') ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( _S('iet'), _S('aier') ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( _S('et') ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->replace( _S(''), _S('A') ) );

		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( 'o', 'O' ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( 'iet', 'aier' ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( 'et' ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->replace( '', 'A' ) );

		$medium = _S('+IKw--Societ+AOk- de Suisse');

		$this->assertEquals( _S('+IKw--SociEt+AOk dE SuissE'), $medium->replace( _S('e'), _S('E') ) );
		$this->assertEquals( _S('+IKw--Societ+AOk de Suitttte'), $medium->replace( _S('s'), _S('tt') ) );
		$this->assertEquals( _S('+IKw--ociet+AOk de uisse'), $medium->replace( _S('S') ) );
	}

	public function testReplacingWithArrays()
	{
		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( array( _S('o')->asUtf8 => _S('O') ) ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( array( _S('iet')->asUtf8 => _S('aier') ) ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( array( _S('et')->asUtf8 => null ) ) );

		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( array( 'o' => 'O' ) ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( array( 'iet' => 'aier' ) ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( array( 'et' => null ) ) );

		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( array( _S('o') ), array( _S('O') ) ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( array( _S('iet') ), array( _S('aier') ) ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( array( _S('et')->asUtf8 ) ) );

		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( array( 'o' ), array( 'O' ) ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( array( 'iet' ), array( 'aier' ) ) );
		$this->assertEquals( _S('+IKw--Soci+AOk-'), $this->short->replace( array( 'et' ) ) );

		$this->assertEquals( _S('+IKw--SOciet+AOk-'), $this->short->replace( array( 'o' ), array( 'O', 'ignored' ) ) );
		$this->assertEquals( _S('+IKw--Socaier+AOk-'), $this->short->replace( array( 'iet' ), array( 'aier', 'ignored' ) ) );

		$medium = _S('+IKw--Societ+AOk- de Suisse');

		$this->assertEquals( _S('+IKw--SociEt+AOk dE SuissE'), $medium->replace( array( _S('e')->asUtf8 => _S('E') ) ) );
		$this->assertEquals( _S('+IKw--Societ+AOk de Suitttte'), $medium->replace( array( _S('s')->asUtf8  => _S('tt') ) ) );
		$this->assertEquals( _S('+IKw--ociet+AOk de uisse'), $medium->replace( array( _S('S') ) ) );

		$this->assertEquals( _S('+IKw--cit+AOk d uiss'), $medium->replace( array( _S('S'), _S('o'), _S('e') ) ) );
		$this->assertEquals( _S('+IKw--cit+AOk d uiss'), $medium->replace( array( 'S', _S('o'), 'e' ) ) );

		$this->assertEquals( _S('+IKw--Socier+AOk de Suirrrre'), $medium->replace( array( 's' => 'tt', 't' => 'r' ) ) );
		$this->assertEquals( _S('+IKw--Socies+AOk de Suisssse'), $medium->replace( array( 's' => 'tt', 't' => 's' ) ) );
	}

	public function testReplacingWithDifferentEncoding()
	{
		$this->assertEquals( _S('+IKw--S+APY-ciet+AOk-'), $this->short->replace( _S('o'), _S("\xC3\xB6") ) );
		$this->assertEquals( _S('+IKw--Soc+AOgA4gDp-'), $this->short->replace( _S('iet'), _S("\xC3\xA8\xC3\xA2") ) );
		$this->assertEquals( _S('+IKw--Societ'), $this->short->replace( _S("\xC3\xA9") ) );
	}

	public function testTranslatingWithStrings()
	{
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( _S('a'), _S('b') ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( _S('ab'), _S('ba') ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( _S('aa'), _S('bb') ) );

		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( 'a', 'b' ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( 'ab', 'ba' ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( 'aa', 'bb' ) );

		$this->assertEquals( _S('+IKw--Sociat+AOk-'), $this->short->translate( 'e', 'a' ) );
		$this->assertEquals( _S('+IKw--Sociat+AOk-'), $this->short->translate( 'ee', 'aa' ) );
		$this->assertEquals( _S('+IKw--Sociat+AOk-'), $this->short->translate( 'ee', 'ab' ) );
		$this->assertEquals( _S('+IKw--Sociat+AOk-'), $this->short->translate( 'ee', 'ab' ) );

		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( 'ea', 'ae' ) );
		$this->assertEquals( _S('+IKw--Socict+AOk-'), $this->short->translate( 'eae', 'aec' ) );

		$this->assertEquals( _S('+IKw--Soiet+AOk-'), $this->short->translate( 'ec', 'e' ) );
		$this->assertEquals( _S('+IKw--Societ+AOk-'), $this->short->translate( '', 'eabcdef' ) );
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */

	public function testTranslatingWithStringsForFailing()
	{
		$this->short->translate( null, 'eabcdef' );
	}

	public function testSplitting()
	{
		$this->assertEquals( 1, count( $this->short->split( _S('/a/') ) ) );
		$this->assertEquals( 2, count( $this->short->split( _S('/S/') ) ) );
		$this->assertEquals( 2, count( $this->short->split( _S('/[SE]/') ) ) );
		$this->assertEquals( 3, count( $this->short->split( _S('/[SE]/i') ) ) );
		$this->assertEquals( 2, count( $this->short->split( _S('/t+AOk-/i') ) ) );
		$this->assertEquals( 1, count( $this->short->split( _S('/t+AOk-/i','utf-8') ) ) );
		$this->assertEquals( array( _S(''), _S('-Societ+AOk-') ), $this->short->split( _S('/+IKw-/') ) );

		$subject = _S('+IKw Societ+AOk de Suisse');

		$this->assertEquals( 3, count( $subject->split( _S('/[+IKwA6Q-]/') ) ) );
		$this->assertEquals( 2, count( $subject->split( _S('/[+IKwAyQ-]/') ) ) );
		$this->assertEquals( 3, count( $subject->split( _S('/[+IKwAyQ-]/i') ) ) );
	}

	public function testMatching()
	{
		$this->assertSame( false, $this->short->match( _S('/a/') ) );
		$this->assertType( 'array', $this->short->match( _S('/S/') ) );
		$this->assertType( 'array', $this->short->match( _S('/t+AOk-/i') ) );
		$this->assertSame( false, $this->short->match( _S('/t+AOk-/i','utf-8') ) );
		$this->assertEquals( array( _S('+IKw--Societ+AOk-'), _S('+AOk-') ), $this->short->match( _S('/+IKw--Societ(+AOk-)/') ) );
		$this->assertEquals( array( _S('Societ+AOk-'), _S('+AOk-') ), $this->short->match( _S('/Societ(+AOk-)/') ) );
		$this->assertEquals( array( _S('+IKw--Societ+AOk-'), _S('+AOk-') ), $this->short->match( _S("/\xE2\x82\xAC-Societ(\xC3\xA9)/",'utf-8') ) );
		$this->assertSame( false, $this->short->match( _S("/\xE2\x82\xAC-Societ(\xC3\x89)/",'utf-8') ) );
		$this->assertEquals( array( _S('+IKw--Societ+AOk-'), _S('+AOk-') ), $this->short->match( _S("/\xE2\x82\xAC-Societ(\xC3\x89)/i",'utf-8') ) );
	}
}

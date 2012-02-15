<?php


namespace de\toxa\txf;


class xml
{
	public static function isValidTagName( $name )
	{
		$nameStartChar = ':A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';
		$nameChar      = $nameStartChar . '\x{203F}-\x{2040}\x{300}-\x{36F}\x{B7}0-9.-';

		return preg_match( "/^[$nameStartChar][$nameChar]*\$/u", $name );
	}
}
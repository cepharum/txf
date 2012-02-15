<?php


/**
 * Fake text translation class providing at least interface for using related
 * API.
 *
 */


namespace de\toxa\txf;


class translation
{
	public static function get( $singular, $plural, $count )
	{
		$count = abs( $count );

		return ( $count == 1 ) ? $singular : $plural;
	}
}


<?php

namespace de\toxa\txf;

/**
 * factory for data-filtering closures
 *
 * @author Thomas Urban
 */

class filter
{
	/**
	 * Retrieves closure passing provided data when containing relative URL.
	 *
	 * @return function
	 */

	public static function relativeUrl()
	{
		return function( $input )
		{
			$info = parse_url( $input );
			if ( !is_array( $info ) )
				return false;

			if ( @$info['scheme'] || @$info['host'] || @$info['port'] ||
					@$info['user'] || @$info['pass'] || !@$info['path'] )
				return false;

			return $input;
		};
	}

	/**
	 * Retrieves closure passing provided data when containing absolute URL.
	 *
	 * @return function
	 */

	public static function absoluteUrl()
	{
		return function( $input )
		{
			$info = parse_url( $input );
			if ( !is_array( $info ) )
				return false;

			return @$info['scheme'] && @$info['host'] ? $info : false;
		};
	}

	/**
	 * Retrieves closure passing provided data when containing parseable URL.
	 *
	 * @return function
	 */

	public static function url()
	{
		return function( $input )
		{
			return is_array( parse_url( $input ) ) ? $input : false;
		};
	}
}

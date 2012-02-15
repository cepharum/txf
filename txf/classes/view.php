<?php


/**
 * Serves as a proxy for view manager moved into extension.
 *
 * This proxy is considered to provide more convenient access on actually used
 * view manager. In addition this class is declaring some commonly useful
 * "macros" for use in template processing.
 *
 * @author Thomas Urban
 */

namespace de\toxa\txf;


class view extends view\skinnable\manager
{
	/**
	 * Wraps provided string.
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param string $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string wrapped string
	 */

	public static function wrap( $what, $wrapping, $wrappingAppend = null )
	{
		if ( is_array( $what ) )
		{
			$out = '';
			foreach ( $what as $item )
				$out .= static::wrap( $item, $wrapping, $wrappingAppend );

			return $out;
		}

		if ( is_null( $wrappingAppend ) )
		{
			$halves = explode( '|', $wrapping );
			return array_shift( $halves ) . $what . array_shift( $halves );
		}

		return $wrapping . $what . $wrappingAppend;
	}

	/**
	 * Wraps provided string unless its empty (ignoring any whitespace).
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param string $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string|null wrapped string, null on empty $what
	 */

	public static function wrapNotEmpty( $what, $wrapping, $wrappingAppend = null )
	{
		if ( ( is_array( $what ) && count( $what ) ) || ( !is_array( $what ) && !is_null( $what ) && trim( $what ) !== '' ) )
			return static::wrap( $what, $wrapping, $wrappingAppen );

		return null;
	}

	/**
	 * Wraps provided string unless its false (or similar value considered value).
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param mixed $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string|null wrapped string, null on empty $what
	 */

	public static function wrapNotFalse( $what, $wrapping, $wrappingAppend = null )
	{
		if ( $what )
			return static::wrap( $what, $wrapping, $wrappingAppen );

		return null;
	}
}


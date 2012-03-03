<?php


/**
 * Copyright 2012 Thomas Urban, toxA IT-Dienstleistungen
 * 
 * This file is part of TXF, toxA's web application framework.
 * 
 * TXF is free software: you can redistribute it and/or modify it under the 
 * terms of the GNU General Public License as published by the Free Software 
 * Foundation, either version 3 of the License, or (at your option) any later 
 * version.
 * 
 * TXF is distributed in the hope that it will be useful, but WITHOUT ANY 
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR 
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with 
 * TXF. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright 2012, Thomas Urban, toxA IT-Dienstleistungen, www.toxa.de
 * @license GNU GPLv3+
 * @version: $Id$
 * 
 */


namespace de\toxa\txf;


/**
 * Serves as a proxy for view manager moved into extension.
 *
 * This proxy is considered to provide more convenient access on actually used
 * view manager. In addition this class is declaring some commonly useful
 * "macros" for use in template processing.
 *
 * @author Thomas Urban
 */

 
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


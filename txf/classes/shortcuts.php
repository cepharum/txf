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
 *
 * @param <type> $singular
 * @param <type> $plural
 * @param <type> $count
 * @return string
 */

function _L( $singular, $plural, $count )
{
	return translation::get( $singular, $plural, $count );
}


/**
 * Wraps provided native string in a managed string instance.
 *
 * On providing managed string instance it's cloned.
 *
 * If parameter $useIfNotAString is containing string and $string is not a
 * string, then the former is used to replace the latter. This is available
 * for conveniently validating parameters expected to be string.
 *
 * @param string $string native or managed string to wrap/clone
 * @param string $encoding encoding of provided string, ignored on cloning
 * @param string $useIfNotAString if $string is not a string this string is used instead
 * @return string managed string instance
 */

function _S( $string, $encoding = null, $useIfNotAString = null )
{
	return string::wrap( $string, $encoding, $useIfNotAString );
}


/**
 * Conveniently creates new set instance prefilled with provided argument(s).
 *
 * If a single array is given, then its elements are used to prefill the set.
 * Otherwise all arguments become elements of new set.
 *
 * @return set created set
 */

function _A()
{
	$arguments = func_get_args();

	if ( ( count( $arguments ) == 1 ) && is_array( $arguments[0] ) )
		return set::wrap( $arguments[0] );

	return set::wrap( $arguments );
}


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


class xml
{
	/**
	 * Regular expression partial pattern matching valid initial character of 
	 * element name.
	 */

	const nameLeadingCharPattern = ':A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}';

	/**
	 * Regular expression partial pattern matching valid non-initial character 
	 * of element name.
	 */

	const nameCharPattern = ':A-Z_a-z\x{C0}-\x{D6}\x{D8}-\x{F6}\x{F8}-\x{2FF}\x{370}-\x{37D}\x{37F}-\x{1FFF}\x{200C}\x{200D}\x{2070}-\x{218F}\x{2C00}-\x{2FEF}\x{3001}-\x{D7FF}\x{F900}-\x{FDCF}\x{FDF0}-\x{FFFD}\x{10000}-\x{EFFFF}\x{203F}-\x{2040}\x{300}-\x{36F}\x{B7}0-9.-';



	/**
	 * Detects if provided name is a valid XML element name.
	 * 
	 * @param string $name element name to test
	 * @return boolean true if provided name is a valid XML element name
	 */

	public static function isValidName( $name )
	{
		return preg_match( '/^[' . self::nameLeadingCharPattern . '][' . self::nameCharPattern . ']*$/u', $name );
	}

	/**
	 * Fixes provided name to be usable as a valid XML element name.
	 * 
	 * The method is replacing all invalid characters in name by an underscore.
	 * 
	 * @param string $name element name to fix
	 * @return string fixed element name
	 */

	public static function fixName( $name )
	{
		return preg_replace( '/^[^' . self::nameLeadingCharPattern . ']/', '_', preg_replace( '/[^' . self::nameCharPattern . ']/', '_', $name ) );
	}

	/**
	 * Serializes provided hash as element attribute descriptor.
	 * 
	 * @param array $hash associative set of attributes
	 * @return string XML-like description of array elements as element attributes
	 */

	public static function arrayToAttributes( $hash )
	{
		$hash = array_filter( $hash, function( $item ) { return $item !== null; } );

		return implode( '', array_map( function( $name, $value )
		{
			return ' ' . self::fixName( $name ) . '="' . htmlspecialchars( $value ) . '"';
		}, array_keys( $hash ), $hash ) );
	}

	/**
	 * Fixes name and optionally given namespace and combines them into 
	 * qualified element name.
	 * 
	 * @param string $name name of element
	 * @param string $namespace namespace tag of element
	 * @return string qualified name of element
	 */

	public static function qualifiedName( $name, $namespace = '' )
	{
		$name      = str_replace( self::fixName( $name ), ':', '_' );
		$namespace = str_replace( self::fixName( trim( $namespace ) ), ':', '_' );

		return ( $namespace !== '' ) ? "$namespace:$name" : $name;
	}

	/**
	 * Describes XML element.
	 * 
	 * This method is to support creation of XML code by providing single method
	 * for describing single element in XML. It might be used bottom-up from
	 * leaves to the root by successively wrapping values in an element and then
	 * put the result as value in the containing element.
	 *
	 * @param string $name name of element
	 * @param string $namespace namespace tag to use on qualifying element's name
	 * @param integer $depth steps of indentation to use on element
	 * @param string $value value of element
	 * @param array attributes hash describing attributes of element to write
	 * @return string XML code of element
	 */

	public static function describeElement( $name, $namespace = null, $depth = 0, $value = null, $attributes = array() )
	{
		$qname      = self::qualifiedName( $namespace, $name );
		$attributes = self::arrayToAttributes( $attributes );

		// have proper indentation
		$indent = str_pad( '', $depth, "\t" );

		// support short tags
		if ( $value === null )
			return "$indent<$qname$attributes/>\n";

		// return full tag representation of described element
		return $indent . wordwrap( "<$qname$attributes>" . htmlspecialchars( $value ) . "</$qname>", 80, "$indent\t" ) . "\n";
	}

	public static function describe( $data, $rootName = 'xml', $namespace = '', $depth = 0 )
	{
	}
}


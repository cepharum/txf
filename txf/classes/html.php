<?php


/**
 * Provides methods for working with HTML code.
 *
 * @author Thomas Urban
 *
 */

namespace de\toxa\txf;


class html
{
	/**
	 * Reduces and converts provided code to be validly included in a tag's
	 * attribute.
	 *
	 * This is achieved by removing any contained tags and escaping special
	 * characters such as ampersand and tag delimiters.
	 *
	 * @param string $value HTML code fragment
	 * @return string HTML code containing literal text, only
	 */

	public static function inAttribute( $value )
	{
		return htmlspecialchars( strip_tags( $value ) );
	}

	/**
	 * Prepares provided string to be inserted as CDATA in HTML.
	 *
	 * CDATA is string that isn't processed as HTML code.
	 *
	 * @param string $value string to prepare
	 * @param boolean $escaping if true, escape special chars instead of wrapping in cdata
	 * @return string prepared string
	 */

	public static function cdata( $value, $escaping = false )
	{
		return $escaping ? htmlspecialchars( $value ) : '<![CDATA[' . $value . ']]>';
	}

	/**
	 * Reduces provided string to contain only characters valid for use as ID
	 * of an HTML element.
	 *
	 * @param string $value string to reduce
	 * @param boolean set true if name will be used for form field (permitting [] at end)
	 * @return string reduced string
	 */

	public static function idname( $value, $asNameOfFormField = false )
	{
		$value = trim( $value );

		if ( $asNameOfFormField )
		{
			$value = preg_replace( '/[^-a-z0-9_\[\]]/i', '', $value );

			if ( preg_match( '/^[-a-z0-9_]+(\[[-a-z0-9_]*\])?$/i', $value ) )
				return $value;
		}

		return preg_replace( '/[^-a-z0-9_]/i', '', $value );
	}

	/**
	 * Reduces provided string to contain only characters valid for use as class
	 * of an HTML element.
	 *
	 * @param string $value string to reduce
	 * @return string reduced string
	 */

	public static function classname( $value )
	{
		$temp = preg_split( '/\s+/', trim( $value ) );
		return implode( ' ', array_map( array( self, 'idname' ), $temp ) );
	}
}
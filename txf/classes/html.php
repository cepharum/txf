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
 * Provides methods for working with HTML code.
 *
 * @author Thomas Urban
 *
 */

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
	 * @param string|array $value string containing class names to reduce, optionally array of class names
	 * @return string string properly describing set of element classes
	 */

	public static function classname( $value )
	{
		return implode( ' ', array_map( array( self, 'idname' ), is_array( $value ) ? array_map( function( $item ) { return trim( $item ); }, $value ) : preg_split( '/\s+/', trim( $value ) ) ) );
	}

	/**
	 * Converts provided two-dimensional array to HTML markup describing table.
	 *
	 * @param array $arrData two-dimensional array to render in HTML
	 * @param string $id optional ID to use on table
	 * @param function $cellFormat method called to render value of a cell
	 * @param function $headerFormat method called to render value of a header
	 * @param string $empty string to render in columns unset in a row
	 * @param string $basicIndent string prefixing every line of rendered HTML code
	 * @return string HTML code describing table
	 */

	public static function arrayToTable( $arrData, $id = '', $cellFormat = null, $headerFormat = null, $empty = '', $basicIndent = '', $class = '' )
	{
		if ( !is_array( $arrData ) )
			throw new \InvalidArgumentException( 'not an array' );


		// filter out unusable content in provided data
		$arrData = array_filter( $arrData, function( $row ) { return is_array( $row ) || is_object( $row ); } );
		$arrData = array_map( function( $row ) { return is_object( $row ) ? get_object_vars( $row ) : $row; }, $arrData );
		$arrData = array_filter( $arrData, function( $row ) { return !!count( $row ); } );


		// collect set of columns
		$header = array();

		foreach ( $arrData as $row )
			foreach ( $row as $name => $cell )
				$header[$name] = '';

		$header = array_flip( array_keys( $header ) );


		// convert column headers into table's header
		$out = "$basicIndent	<thead>\n$basicIndent		<tr>\n";

		foreach ( $header as $name => $index )
		{
			$cellClass = html::classname( ( ( $index % 2 ) ? 'even' : 'odd' ) . ( $index ? '' : ' first' ) . ( ( $index == count( $header ) - 1 ) ? ' last' : '' ) . ' ' . $name );

			$label = trim( is_callable( $headerFormat ) ? call_user_func( $headerFormat, $name ) : htmlspecialchars( $name ) );
			if ( $label[0] == '|' )
				$label = '';

			$out .= "$basicIndent			<th class=\"$cellClass\">$label</th>\n";
		}

		$out .= "$basicIndent		</tr>\n$basicIndent	</thead>\n";


		// convert array data into table's body
		$out .= "$basicIndent	<tbody>\n";

		$rowIndex = 0;

		foreach ( $arrData as $index => $record )
		{
			$rowClass = ( ( $rowIndex % 2 ) ? 'even' : 'odd' ) . ( $rowIndex ? '' : ' first' ) . ( ++$rowIndex == count( $arrData ) ? ' last' : '' );

			$out .= "$basicIndent		<tr class=\"$rowClass\">\n";

			$row = $header;
			foreach ( $row as $name => $cell )
			{
				$cellClass = html::classname( ( ( $cell % 2 ) ? 'even' : 'odd' ) . ( $cell ? '' : ' first' ) . ( ( $cell == count( $row ) - 1 ) ? ' last' : '' ) . ' ' . $name );

				if ( array_key_exists( $name, $record ) )
					$cell = is_callable( $cellFormat ) ? call_user_func( $cellFormat, $record[$name], $name, $record, $index ) : htmlspecialchars( $record[$name] );
				else
					$cell = $empty;

				$out .= "$basicIndent			<td class=\"$cellClass\">$cell</td>\n";
			}

			$out .= "$basicIndent		</tr>\n";
		}

		if ( !$rowIndex )
			$out .= "$basicIndent		<tr><td colspan=\"2\" class=\"empty\">" . _L('There is no data to display here!','',1) . "</td></tr>\n";

		$out .= "$basicIndent	</tbody>\n";


		// combine table's header and body into complete table
		$id    = $id    ? " id=\"$id\"" : '';
		$class = $class ? " class=\"$class\"" : '';

		return "\n$basicIndent<table$id$class>\n$out$basicIndent</table>\n";
	}

	/**
	 * Converts provided single-dimensional array to HTML markup describing
	 * two-column grid ("card") with labels in its left and related values in
	 * its right column.
	 *
	 * @param array $arrData single-dimensional array to render in HTML
	 * @param string $id optional ID to use on rendered card
	 * @param function $cellFormat method called to render value of a cell in right column
	 * @param function $headerFormat method called to render value of a header in left column
	 * @param string $empty string to render in right-hand cells unset
	 * @param string $basicIndent string prefixing every line of rendered HTML code
	 * @return string HTML code describing card
	 */

	public static function arrayToCard( $arrData, $id = '', $cellFormat = null, $headerFormat = null, $empty = '', $basicIndent = '' )
	{
		if ( !is_array( $arrData ) )
			throw new \InvalidArgumentException( 'not an array' );


		$id = trim( $id ) ? ' id="' . static::idname( $id ) . '"' : '';


		// convert array data into card
		$out = "$basicIndent<div class=\"card\"$id>\n";

		$rowIndex = 0;

		foreach ( $arrData as $header => $cell )
		{
			$cell = is_callable( $cellFormat ) ? call_user_func( $cellFormat, $header, $cell, $arrData ) : htmlspecialchars( $cell );
			if ( $cell !== null )
			{
				$rowClass = ( ( $rowIndex % 2 ) ? 'even' : 'odd' ) . ( $rowIndex ? '' : ' first' ) . ( ( ++$rowIndex == count( $arrData ) ) ? ' last' : '' );

				$out .= "$basicIndent	<div class=\"$rowClass\">\n";

				if ( trim( $cell ) === '' )
					$cell = $empty;

				$label = trim( is_callable( $headerFormat ) ? call_user_func( $headerFormat, $header ) : htmlspecialchars( "$header:" ) );
				if ( $label[0] == '|' )
					$label = '';

				$out .= "$basicIndent		<label>$label</label>\n";
				$out .= "$basicIndent		<div>$cell</div>\n";
				$out .= "$basicIndent	</div>\n";
			}
		}

		if ( !$rowIndex )
			$out .= "$basicIndent	<span class=\"empty\">" . _L('There is no data to display here!','',1) . "</span>\n";

		return $out . "$basicIndent</div>\n";
	}
}
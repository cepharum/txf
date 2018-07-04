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

	public static function inAttribute( $value, $escapeQuotesOnly = false )
	{
		return $escapeQuotesOnly ? strtr( $value, array( '"' => '&quot;' ) ) : htmlspecialchars( strip_tags( $value ) );
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
	 * Removes all scripting from provided code.
	 *
	 * @param string $code HTML code optionally containing scripting sections
	 * @return string provided HTML code with any contained scripting sections removed
	 */

	public static function noscript( $code )
	{
		return preg_replace( '#<script\W.*</script>#i', '', $code );
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
		return implode( ' ', array_map( array( get_class(), 'idname' ), is_array( $value ) ? array_map( function( $item ) { return trim( $item ); }, $value ) : preg_split( '/\s+/', trim( $value ) ) ) );
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
			$out .= "$basicIndent		<tr><td colspan=\"2\" class=\"empty\">" . _L('There is no data to display here.','',1) . "</td></tr>\n";

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
	 * @param callable $cellFormat method called to render value of a cell in right column
	 * @param callable $headerFormat method called to render value of a header in left column
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
			$cellClass = ( is_array( $cell ) && count( $cell ) > 1 ) ? 'multi' : 'single';

			$cell = is_callable( $cellFormat ) ? call_user_func( $cellFormat, $cell, $header, $arrData, null ) : htmlspecialchars( $cell );
			if ( $cell !== null )
			{
				if ( is_array( $cell ) ) {
					$cell = array_filter( $cell, function ( $v ) {
						return trim( $v ) !== '';
					} );
					$cell = implode( "\n", $cell );
				}

				if ( trim( $cell ) === '' )
					$cell = $empty;

				$label = trim( is_callable( $headerFormat ) ? call_user_func( $headerFormat, $header ) : htmlspecialchars( "$header:" ) );
				if ( $label[0] == '|' || trim( $label ) === ':' )
					$label = '';

				$rowClass = array(
					( $rowIndex % 2 ) ? 'even' : 'odd'
				);

				if ( !$rowIndex )
					$rowClass[] = 'first';
				if ( ++$rowIndex == count( $arrData ) )
					$rowClass[] = 'last';
				if ( trim( $label ) === '' )
					$rowClass[] = 'no-label';

				$rowClass = implode( ' ', $rowClass );

				$out .= "$basicIndent	<div class=\"$rowClass\">\n";
				$out .= "$basicIndent		<label>$label</label>\n";
				$out .= "$basicIndent		<div class=\"$cellClass\">$cell</div>\n";
				$out .= "$basicIndent	</div>\n";
			}
		}

		if ( !$rowIndex )
			$out .= "$basicIndent	<span class=\"empty\">" . _L('There is no data to display here.','',1) . "</span>\n";

		return $out . "$basicIndent</div>\n";
	}

	/**
	 * Extracts existing links from provided HTML fragment to be wrapped in a
	 * new link prior to appending extracted links.
	 *
	 * @param callable|null $processor callback invoked per found link to
	 *        generate related link to be appended later, omit to keep extracted
	 *        link as-is
	 * @param string|null $link URL of link to be wrapping provided HTML
	 *        fragment, null to extract contained links, but skip eventual
	 *        generation of link
	 * @param string $label HTML fragment to extract additional links from and
	 *        wrap in link afterwards
	 * @param string|null $class classes to apply on link generated eventually
	 * @param string|null $title title of link generated eventually
	 * @param bool $external true if eventually generated link is linking sth.
	 *        external
	 * @return string resulting HTML fragment
	 */
	public static function extendLink( $processor, $link, $label, $class = null, $title = null, $external = false ) {
		if ( !is_callable( $processor ) )
			$processor = function( $options ) {
				return markup::link( $options['href'], $options['label'], $options['class'], $options['title'], !array_key_exists( 'onclick', $options ) );
			};

		if ( preg_match_all( '#<a\s([^>]+)>#i', $label, $links, PREG_SET_ORDER ) )
			$tail = implode( ' ', array_map( function( $link ) use ( $processor ) {
				$opts  = array();
				$index = 0;

				while ( preg_match( '/\s*([^=]+)=("[^"]*"|\'[^\']*\')/', $link[1], $next, PREG_OFFSET_CAPTURE, $index ) ) {
					$index = $next[0][1] + strlen( $next[0][0] );

					$name  = trim( $next[1][0] );
					$value = substr( $next[2][0], 1, -1 );

					$opts[$name] = $value;
				}

				return call_user_func( $processor, $opts );
			}, $links ) );
		else
			$tail = '';

		$label = strip_tags( $label );

		return trim( ( $link ? markup::link( $link, $label, $class, $title, $external ) : $label ) . ' ' . $tail );
	}
}

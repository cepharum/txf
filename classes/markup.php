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
 * Wraps text in proper markup code using templates.
 *
 * This class is designed to use templates for applying markup on page elements
 * such as headline and paragraphs. In addition to improving separation of
 * content and design this is beneficially introducing low-level customizations
 * of rendered code.
 *
 * The class is using magic method __call() to select a template's name by
 * invoking method, so using it becomes as simple as this
 *
 *   markup::h1( 'Major Headline' );
 *
 * for marking up provided text as a first-level headline.
 *
 * Supported templates are located in subfolder markup of your current skin or
 * its fallbacks. In case of given example it's "markup/h1".
 *
 * @author Thomas Urban
 *
 * == Commonly supported magic methods
 *
 * = text-styling
 * @method static string paragraph( $text, $class = null ) renders paragraph of text
 * @method static string bullets( $items, $class = null ) renders unnumbered list of items
 * @method static string enumeration( $items, $class = null ) renders numbered list of items
 * @method static string h1( $text, $class = null ) renders heading level 1
 * @method static string h2( $text, $class = null ) renders heading level 2
 * @method static string h3( $text, $class = null ) renders heading level 3
 * @method static string h4( $text, $class = null ) renders heading level 4
 * @method static string h5( $text, $class = null ) renders heading level 5
 * @method static string h6( $text, $class = null ) renders heading level 6
 *
 * @method static string emphasize( $text, $class = null ) renders emphasizing text
 * @method static string strong( $text, $class = null ) renders strongly emphasizing text
 * @method static string link( $href, $label, $class = null, $title = null, $external = false ) renders link
 *
 * @method static string image( $src, $class = null, $alt = "", $href = null, $title = null, $width = 0, $height = 0 ) renders optionally clickable image
 *
 * = layout-related
 * @method static string block( $text, $class = null ) renders text in block-like container
 * @method static string inline( $text, $class = null ) renders text in inline container
 * @method static string flash( $context, $messages ) renders messages in given context
 *
 * = form related
 * @method static string textedit( $name, $value, $label = null, $placeholder = null, $class = null ) renders single-line text editing control
 * @method static string date( $name, $value, $label = null, $placeholder = null, $class = null ) renders single-line date editing control
 * @method static string time( $name, $value, $label = null, $placeholder = null, $class = null ) renders single-line date editing control
 * @method static string datetime( $name, $value, $label = null, $placeholder = null, $class = null ) renders single-line datetime editing control
 * @method static string textarea( $name, $value, $label = null, $rows, $columns, $placeholder = null, $class = null ) renders multi-line text editing control
 * @method static string password( $name, $label = null, $class = null ) renders input field for blindly entering password
 * @method static string checkbox( $name, $value, $checked, $label = null, $title = null, $class = null ) renders checkbox
 * @method static string selector( $name, $options, $value, $label = null, $multi = false, $class = null ) renders selector
 * @method static string upload( $name, $label = null, $class = null ) renders file upload control
 * @method static string button( $name, $value, $label = null, $class = null, $title = null, $class = null ) renders clickable button
 */


class markup
{
	public static function __callStatic( $method, $arguments )
	{
		return view::render( 'markup/' . $method, variable_space::create( 'arguments', $arguments, 'text', array_shift( $arguments ) ) );
	}

	/**
	 * Links all parts of provided code that are not linking already.
	 *
	 * @param string $in HTML code to be linked
	 * @param string $link URL of link target
	 * @param string $classes classes to use on linking
	 * @param string $title title to use on linking
	 * @param boolean $external true if link is considered external (to be opened in new window)
	 * @return string revised HTML code
	 */
	public static function linkStatic( $link, $in, $classes = null, $title = null, $external = false ) {
		$chunks = preg_split( '#(<a\s|</a>)#', $in, null, PREG_SPLIT_DELIM_CAPTURE );

		$out = '';

		for ( $i = 0; $i < count( $chunks ); $i++ ) {
			if ( strtolower( trim( $chunks[$i] ) ) === '<a' ) {
				$out .= $chunks[$i] . $chunks[$i + 1] . $chunks[$i + 2];
				$i += 2;
			} else {
				$out .= preg_replace_callback( '#(^\]?\s*[;,.:]?\s*)(.*?)(\s*[;,.:]?\s*\[?$)#', function( $matches ) use ( $link, $classes, $title, $external ) {
					if ( $matches[2] ) {
						return $matches[1] . markup::link( $link, $matches[2], $classes, $title, $external ) . $matches[3];
					}

					return $matches[1] . $matches[3];
				}, $chunks[$i] );
			}
		}

		return $out;
	}
}


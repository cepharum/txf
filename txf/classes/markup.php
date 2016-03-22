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
 * @method static string textedit( $name, $value, $label = null ) renders single-line text editing control
 * @method static string textarea( $name, $value, $label = null, $rows, $columns ) renders multi-line text editing control
 * @method static string password( $name, $label = null ) renders input field for blindly entering password
 * @method static string checkbox( $name, $value, $checked, $label = null, $title = null ) renders checkbox
 * @method static string selector( $name, $options, $value, $label = null ) renders selector
 * @method static string upload( $name, $label = null ) renders file upload control
 * @method static string button( $name, $value, $label = null, $class = null, $title = null ) renders clickable button
 */


class markup
{
	public static function __callStatic( $method, $arguments )
	{
		return view::render( 'markup/' . $method, variable_space::create( 'arguments', $arguments, 'text', array_shift( $arguments ) ) );
	}
}


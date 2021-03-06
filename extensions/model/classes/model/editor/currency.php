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

namespace de\toxa\txf\model;

use \de\toxa\txf\config;
use \de\toxa\txf\input;
use \de\toxa\txf\html_form;
use \de\toxa\txf\markup;


class model_editor_currency extends model_editor_abstract
{
	protected static $currencies = null;

	/**
	 * set of pcre patterns matching supported notations of decimals providing
	 * properly split amount each
	 *
	 * @array[pattern-string]
	 */

	protected static $amountNotations = array(
		'basic'   => '/^([+-])?(\d+)(?:[,.](\d{2}))?$/',				// basic is used on normalizing input
		'option1' => '/^([+-])?(\d+(?:,\d{3})*)(?:\.(\d{2}))?$/',
		'option2' => '/^([+-])?(\d+(?:\.\d{3})*)(?:,(\d{2}))?$/',
	);

	public function __construct()
	{
		if ( !is_array( static::$currencies ) )
			static::$currencies = config::get( 'data.currencies', array( 'EUR' => \de\toxa\txf\_L('€') ) );
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		if ( $this->isReadOnly )
			return null;

		// don't normalize input provided by editor, but separately read input
		// from multiple included fields
		$field = $editor->propertyToField( $property );

		$amount   = trim( input::vget( "{$field}_amount" ) );
		$currency = trim( input::vget( "{$field}_currency" ) );

		// consider missing monetary input if amount is empty
		if ( $amount === '' )
			return null;

		if ( array_key_exists( $currency, static::$currencies ) )
			// normalize entered amount according to provided set of supported notations
			foreach ( static::$amountNotations as $amountPattern )
				if ( preg_match( $amountPattern, $amount, $matches ) )
					return sprintf( '%s%s.%02d %s', ( $matches[1] == '-' ? '-' : '' ),
									strtr( $matches[2], array( '.' => '', ',' => '' ) ),
									$matches[3], $currency );

		// provide amount w/o currency as fallback (causing validation failure)
		return $amount;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $input === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('This information is required.') );

			return true;
		}

		$parts = explode( ' ', $input );

		if ( count( $parts ) == 2 )
			if ( preg_match( '/^[+-]?\d+\.\d{2}$/', $parts[0] ) )
				return true;

		throw new \InvalidArgumentException( \de\toxa\txf\_L('Your input is invalid.') );
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		if ( $this->isReadOnly )
			return $this->renderStatic( $form, $name, $input, $label, $editor, $field );

		$parts = explode( ' ', $input );

		$code  = markup::textedit( "{$name}_amount", $parts[0] );
		$code .= markup::selector( "{$name}_currency", static::$currencies, $parts[1] );

		$classes = implode( ' ', array_filter( array( $this->class, 'currency' ) ) );

		$form->setRow( $name, $label, $code, $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$value = $this->formatValue( $name, $input, $editor, $field );

		$classes = implode( ' ', array_filter( array( $this->class, 'currency' ) ) );

		$form->setRow( $name, $label, markup::inline( $value, 'static' ), $this->isMandatory, null, null, $classes );

		return $this;
	}

	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field )
	{
		$parts = explode( ' ', $value );

		return implode( ' ', array( $parts[0], array_key_exists( $parts[1], static::$currencies ) ? static::$currencies[$parts[1]] : $parts[1] ) );
	}
}

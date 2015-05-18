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

use \de\toxa\txf\dictionary;
use \de\toxa\txf\html_form;

class model_editor_selector extends model_editor_text
{
	/**
	 * options to provide for selection
	 *
	 * @var dictionary
	 */

	protected $options;



	public function __construct( $options = null )
	{
			$this->options = dictionary::createOnArray( $options );
	}

	public static function create( $options = null )
	{
		return new static( $options );
	}

	public function addOption( $value, $label )
	{
		$this->options->setValue( $value, $label );

		return $this;
	}

	public function removeOption( $value )
	{
		$this->options->remove( $value );

		return $this;
	}

	public function sortOptions( $byValue )
	{
		$this->options->sort( null, true, !$byValue );

		return $this;
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$input = trim( $input );

		return $this->options->exists( $input ) ? $input : null;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		if ( $this->isMandatory && $this->options->exists( $input ) )
			$this->options->remove( '' );
		else if ( !$this->options->exists( '' ) )
			$this->options->insertAtIndex( '', \de\toxa\txf\_L('-'), 0 );

		$classes = implode( ' ', array_filter( array( $this->class, 'selector' ) ) );

		$form->setSelectorRow( $name, $label, $this->options->items, $input, $this->isMandatory(), $this->hint, null, $classes );

		return $this;
	}

	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field )
	{
		return $this->options->exists( $value ) ? $this->options->value( $value ) : \de\toxa\txf\_L('-');
	}
}

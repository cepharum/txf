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

use \de\toxa\txf\html_form;
use \de\toxa\txf\markup;

class model_editor_texteditor extends model_editor_text
{
	protected $_rows = 5;
	protected $_columns = 60;

	public static function create( $rows = 5, $columns = 60 )
	{
		$field = new static();

		$field->_rows    = intval( $rows );
		$field->_columns = intval( $columns );

		return $field;
	}

	public function setEditor( model_editor $editor )
	{
		$editor->form()->post();

		return parent::setEditor( $editor );
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'texteditor' ) ) );

		$form->setTextareaRow( $name, $label, $input, $this->_rows, $this->_columns, $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'texteditor' ) ) );

		$form->setRow( $name, $label, markup::inline( $input, 'static' ), $this->isMandatory, null, null, $classes );

		return $this;
	}
}

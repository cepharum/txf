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

interface model_editor_element
{
	public function setEditor( model_editor $editor );
	public function getEditor();

	public function normalize( $input, $property, model_editor $editor );
	public function validate( $input, $property, model_editor $editor );
	public function render( html_form $form, $name, $value, $label, model_editor $editor, model_editor_field $field );
	public function renderStatic( html_form $form, $name, $value, $label, model_editor $editor, model_editor_field $field );
	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field );

	public function mandatory( $mandatory = true );
	public function isMandatory();

	public function readOnly( $readOnly = true );
	public function isReadOnly();

	public function declareDefaultValue( $defaultValue );
	public function getFixableValue( $value );

	public function onSelectingItem( model_editor $editor, model $item, model_editor_field $field );
	public function onLoading( model_editor $editor, model $item = null, $propertyName, model_editor_field $field );
	public function afterLoading( model_editor $editor, model $item = null, $propertyName, $loadedValue );
	public function beforeValidating( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field );
	public function beforeStoring( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field );
	public function afterStoring( model_editor $editor, model $item, $itemProperties, model_editor_field $field );
	public function onDeleting( model_editor $editor, model $item, model_editor_field $field );
}

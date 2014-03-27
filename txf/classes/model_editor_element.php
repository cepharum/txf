<?php


namespace de\toxa\txf;

interface model_editor_element
{
	public function normalize( $input, $property, model_editor $editor );
	public function validate( $input, $property, model_editor $editor );
	public function render( html_form $form, $name, $input, $label, model_editor $editor );
	public function renderStatic( html_form $form, $name, $value, $label, model_editor $editor );
	public function mandatory( $mandatory = true );
	public function isMandatory();
}

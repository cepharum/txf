<?php


namespace de\toxa\txf;

/**
 * Implements model editor element to use with hidden properties.
 *
 * This element is available to hide fixed properties in editor rather than
 * rendering their read-only representation.
 *
 * @package de\toxa\txf
 */

class model_editor_hidden extends model_editor_text
{
	public function normalize( $input, $property, model_editor $editor )
	{
		return null;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$form->setHidden( $name, $input );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$form->setHidden( $name, $input );

		return $this;
	}
}

<?php


namespace de\toxa\txf;

class model_editor_date extends model_editor_text
{
	public function normalize( $input, $property, model_editor $editor )
	{
		$input = trim( $input );

		if ( preg_match( '/^(\d{1,2})\s*\.\s*(\d{1,2})\s*\.(\d{4})(?=$|\D)/', $input, $matches ) )
			return sprintf( '%04d-%02d-%02d', $matches[3], $matches[2], $matches[1] );

		return $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		return parent::validate( $input, $property, $editor ) && !!preg_match( '/^(\d{4}-\d\d-\d\d)([T ]\d\d:\d\d(:\d\d)?)?$/', $input );
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		parent::render( $form, $name, $input, $label, $editor );

		$form->setRowClass( $name, 'date' );

		return $this;
	}
}

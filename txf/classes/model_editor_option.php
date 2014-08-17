<?php


namespace de\toxa\txf;

class model_editor_option extends model_editor_text
{
	public function normalize( $input, $property, model_editor $editor )
	{
		return preg_match( '/^y(es)?|j(a)?|on|t(rue)?|set|1$/i', trim( $input ) ) ? 1 : 0;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $this->isMandatory() && !$input )
			throw new \InvalidArgumentException( _Ltxl('You need to check this mandatory option.') );

		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'option' ) ) );

		$form->setCheckboxRow( $name, $label, $input, $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'option' ) ) );

		$form->setRow( $name, $label, markup::inline( $input ? _Ltxl('yes') : _Ltxl('no'), 'static' ), null, null, $classes );

		return $this;
	}
}

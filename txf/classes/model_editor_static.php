<?php


namespace de\toxa\txf;

class model_editor_static extends model_editor_abstract
{
	protected $code;



	public function setContent( $code ) {
		$this->code = strval( $code );

		return $this;
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		return null;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'static' ) ) );

		$form->setRow( $name, $label, $this->code, $this->isMandatory(), $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		return $this->render( $form, $name, null, $label, $editor );
	}

	public function formatValue( $name, $value, model_editor $editor )
	{
		return $value;
	}
}

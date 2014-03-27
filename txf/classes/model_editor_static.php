<?php


namespace de\toxa\txf;

class model_editor_static implements model_editor_element
{
	protected $label;
	protected $name;

	public function __construct( $label, $name = null )
	{
		$this->label = trim( $label );
		$this->name  = $name !== null ? trim( $name ) : uniqid( 'label' );
	}

	/**
	 *
	 * @param type $label
	 * @param type $name
	 * @return model_editor_static
	 */

	public static function create( $label, $name = null )
	{
		return new static( $label, $name );
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
		$form->setRow( $this->name, $label, $this->label );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		return $this->render( $form, $name, $input, $label, $editor );
	}

	public function mandatory( $mandatory = true )
	{
		return $this;
	}

	public function isMandatory()
	{
		return false;
	}
}

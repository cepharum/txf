<?php


namespace de\toxa\txf;

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
		parent::__construct();

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

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		if ( $this->isMandatory && $this->options->exists( $input ) )
			$this->options->remove( '' );
		else if ( !$this->options->exists( '' ) )
			$this->options->insertAtIndex( '', _L('-'), 0 );

		$form->setSelectorRow( $name, $label, $this->options->items, $input );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		if ( $this->options->exists( $input ) )
			return parent::renderStatic( $form, $name, $this->options->value( $input ), $label, $editor );

		return parent::renderStatic( $form, $name, _L('-'), $label, $editor );
	}
}

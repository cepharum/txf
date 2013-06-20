<?php


namespace de\toxa\txf;

class model_editor_selector extends model_editor_text
{
	protected $options = array();

	public function __construct( $options = null )
	{
		parent::__construct();

		if ( is_array( $options ) )
			$this->options = $options;
		else if ( $options !== null )
			throw new \InvalidArgumentException( _L('invalid set of selector options') );
	}

	public function addOption( $value, $label )
	{
		$this->options[$value] = $label;

		return $this;
	}

	public function removeOption( $value )
	{
		unset( $this->options[$value] );

		return $this;
	}

	public function sortOptions( $byValue )
	{
		$byValue ? uksort( $this->options, 'strcasecmp' ) : uasort( $this->options, 'strnatcasecmp' );

		return $this;
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$input = trim( $input );

		return array_key_exists( $input, $this->options ) ? $input : null;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$form->setSelectorRow( $name, $label, $this->options, $input );

		return $this;
	}
}

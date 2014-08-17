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
			$this->options->insertAtIndex( '', _Ltxl('-'), 0 );

		$classes = implode( ' ', array_filter( array( $this->class, 'selector' ) ) );

		$form->setSelectorRow( $name, $label, $this->options->items, $input, $this->isMandatory(), $this->hint, null, $classes );

		return $this;
	}

	public function formatValue( $name, $value, model_editor $editor )
	{
		return $this->options->exists( $value ) ? $this->options->value( $value ) : _Ltxl('-');
	}
}

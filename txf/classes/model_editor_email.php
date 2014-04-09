<?php


namespace de\toxa\txf;

class model_editor_email extends model_editor_text
{
	public function validate( $input, $property, model_editor $editor )
	{
		parent::validate( $input, $property, $editor );

		if ( $input != '' ) {
			if ( !mail::isValidAddress( $input ) )
				throw new \InvalidArgumentException( _L('This is not a valid e-mail address!') );
		}

		return true;
	}

	public function formatValue( $name, $value, model_editor $editor )
	{
		return $value ? markup::link( 'mailto:' . $value, $value ) : null;
	}
}

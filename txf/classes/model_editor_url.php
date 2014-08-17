<?php


namespace de\toxa\txf;

class model_editor_url extends model_editor_text
{
	protected $absolute = false;

	public function validate( $input, $property, model_editor $editor )
	{
		parent::validate( $input, $property, $editor );

		if ( $input != '' )
		{
			if ( !url::isFile( $input ) )
				throw new \InvalidArgumentException( _L('This is not a valid URL.') );

			if ( $this->absolute && url::isRelative( $input ) )
				throw new \InvalidArgumentException( _L('This URL must be absolute. Include scheme e.g. http://www.example.com/!') );
		}

		return true;
	}

	public function formatValue( $name, $value, model_editor $editor )
	{
		return $value ? markup::link( $value, $value ) : null;
	}

	public function forceAbsolute( $force = true )
	{
		$this->absolute = !!$force;

		return $this;
	}
}

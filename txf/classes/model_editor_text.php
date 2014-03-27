<?php


namespace de\toxa\txf;

class model_editor_text implements model_editor_element
{
	protected $isMandatory = false;
	protected $minLength = 0;
	protected $maxLength = 0;
	protected $pattern = false;

	public function __construct()
	{
	}

	public static function create()
	{
		return new static();
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$input = trim( $input );

		return ( $input === '' ) ? null : $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $input === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException( _L('This information is required.') );
		}
		else
		{
			if ( $this->minLength > 0 && strlen( $input ) < $this->minLength )
				throw new \InvalidArgumentException( _L('Your input is too short.') );

			if ( $this->maxLength > 0 && strlen( $input ) < $this->maxLength )
				throw new \InvalidArgumentException( _L('Your input is too long.') );

			if ( $this->pattern && $input !== null && !preg_match( $this->pattern, $input ) )
				throw new \InvalidArgumentException( _L('Your input is invalid.') );
		}

		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$form->setTexteditRow( $name, $label, $input );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$form->setRow( $name, $label, markup::inline( $input, 'static' ) );

		return $this;
	}

	public function mandatory( $mandatory = true )
	{
		$this->isMandatory = !!$mandatory;

		return $this;
	}

	public function isMandatory()
	{
		return $this->isMandatory;
	}

	public function minimum( $length )
	{
		$this->minLength = intval( $length );

		return $this;
	}

	public function maximum( $length )
	{
		$this->maxLength = intval( $length );

		return $this;
	}

	public function pattern( $pattern )
	{
		$this->pattern = trim( $pattern );

		return $this;
	}
}

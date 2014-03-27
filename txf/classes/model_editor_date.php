<?php


namespace de\toxa\txf;

class model_editor_date implements model_editor_element
{
	protected $isMandatory = false;
	protected $notBefore = null;
	protected $notAfter = null;

	protected $storageFormat = 'Y-m-d';
	protected $renderFormat = 'Y-m-d';

	protected static $fallbackParserFormats = array(
		'Y-m-d H:i:s',
		'Y-m-d',
	);

	public function __construct( $renderFormat = 'Y-m-d',  $storageFormat = null )
	{
		$this->renderFormat  = $renderFormat;
		$this->storageFormat = $storageFormat !== null ? $storageFormat : $renderFormat;
	}

	public static function create( $renderFormat = 'Y-m-d',  $storageFormat = null )
	{
		return new static( $renderFormat, $storageFormat );
	}

	protected function parseInputToDatetime( $input )
	{
		if ( preg_match( '/^0+$/', preg_replace( '/\D/', '', $input ) ) )
			// input consists of zeroes, only -> consider some unset date
			return null;

		$parsed = \DateTime::createFromFormat( $this->renderFormat, trim( $input ) );
		if ( !$parsed )
			foreach ( static::$fallbackParserFormats as $format )
			{
				$parsed = \DateTime::createFromFormat( $format, trim( $input ) );
				if ( $parsed )
					break;
			}

		return $parsed;
	}

	protected function parseStorageToDatetime( $stored )
	{
		if ( preg_match( '/^0+$/', preg_replace( '/\D/', '', $stored ) ) )
			// input consists of zeroes, only -> consider some unset date
			return null;

		$parsed = \DateTime::createFromFormat( $this->storageFormat, trim( $stored ) );
		if ( !$parsed )
			foreach ( static::$fallbackParserFormats as $format )
			{
				$parsed = \DateTime::createFromFormat( $format, trim( $stored ) );
				if ( $parsed )
					break;
			}

		return $parsed;
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$parsed = $this->parseInputToDatetime( $input );

		return $parsed ? $parsed->format( $this->storageFormat ) : null;
	}

	public function validate( $value, $property, model_editor $editor )
	{
		if ( $value === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException( _L('This information is required.') );
		}
		else
		{
			$ts = $this->parseStorageToDatetime( $value );

			if ( $this->notBefore instanceof \DateTime && $ts < $this->notBefore )
				throw new \InvalidArgumentException( _L('Selected date is out of range.') );

			if ( $this->notAfter instanceof \DateTime && $ts > $this->notAfter )
				throw new \InvalidArgumentException( _L('Selected date is out of range.') );
		}

		return true;
	}

	public function render( html_form $form, $name, $value, $label, model_editor $editor )
	{
		$ts = $this->parseStorageToDatetime( $value );

		$form
			->setTexteditRow( $name, $label, $ts ? $ts->format( $this->renderFormat ) : '' )
			->setRowClass( $name, 'date ' . preg_replace( '/\W/', '', $this->renderFormat ) );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $value, $label, model_editor $editor )
	{
		$ts = $this->parseStorageToDatetime( $value );

		$form
			->setRow( $name, $label, markup::inline( $ts ? $ts->format( $this->renderFormat ) : null, 'static' ) )
			->setRowClass( $name, 'date ' . preg_replace( '/\W/', '', $this->renderFormat ) );

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

	public function notBefore( \DateTime $timestamp )
	{
		$this->notBefore = $timestamp;

		return $this;
	}

	public function notAfter( \DateTime $timestamp )
	{
		$this->notAfter = $timestamp;

		return $this;
	}
}

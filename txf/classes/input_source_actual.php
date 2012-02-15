<?php


/**
 * Implementation of input source accessing actually available script input.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @license GPL
 */


namespace de\toxa\txf;


class input_source_actual implements input_source
{
	const METHOD_POST = 'POST';
	const METHOD_GET = 'GET';


	protected $set;


	public function __construct( $method )
	{
		switch ( $method )
		{
			case self::METHOD_POST :
				$this->set =& $_POST;
				break;

			case self::METHOD_GET :
				$this->set =& $_GET;
				break;

			default :
				throw new \InvalidArgumentException( 'invalid method' );
		}
	}

	public function isVolatile()
	{
		return true;
	}

	public function hasValue( $name )
	{
		return array_key_exists( $name, $this->set );
	}

	public function getValue( $name )
	{
		return $this->set[$name];
	}

	public function persistValue( $name, $value )
	{
	}

	public function dropValue( $name )
	{
		unset( $this->set[$name] );
	}

	public function getAllValues()
	{
		return $this->set;
	}
}


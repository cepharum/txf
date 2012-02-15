<?php


/**
 * Implementation of input source accessing session for stateful input data.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @license GPL
 */


namespace de\toxa\txf;


class input_source_stateful implements input_source
{
	protected $set;

	
	public function __construct()
	{
		$this->set =& txf::session( session::SCOPE_CLASS + session::SCOPE_APPLICATION, 'input_source_stateful' );
	}

	public function isVolatile()
	{
		return false;
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
		$this->set[$name] = $value;
	}

	public function dropValue( $name )
	{
		unset( $this->set[$name] );
	}
}


<?php


/**
 * Implementation of input source accessing current configuration for defaults.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @license GPL
 */


namespace de\toxa\txf;


class input_source_config implements input_source
{
	public function isVolatile()
	{
		return false;
	}

	public function hasValue( $name )
	{
		return config::has( 'input.default.' . $name );
	}

	public function getValue( $name )
	{
		return config::get( 'input.default.' . $name );
	}

	public function persistValue( $name, $value )
	{
	}

	public function dropValue( $name )
	{
	}
}


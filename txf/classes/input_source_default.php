<?php


/**
 * Implementation of input source retrieving caller-provided default.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @license GPL
 */


namespace de\toxa\txf;


class input_source_default implements input_source
{
	public function isVolatile()
	{
		// From this source manager's point of view any caller-provided
		// value (in calls to input::get() or input::vget()) are volatile.
		return true;
	}

	public function hasValue( $name )
	{
		$callerProvidedDefault = @func_get_arg( 1 );
		
		return !is_null( $callerProvidedDefault );
	}

	public function getValue( $name )
	{
		$callerProvidedDefault = @func_get_arg( 1 );

		return $callerProvidedDefault;
	}

	public function persistValue( $name, $value )
	{
	}

	public function dropValue( $name )
	{
	}
}


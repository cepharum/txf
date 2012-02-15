<?php


namespace de\toxa\txf\view\skinnable;


use de\toxa\txf\variable_space;


abstract class engine
{
	public static function create()
	{
		return new engine_php();
	}

	abstract public function hasTemplate( $templateName );
	abstract public function render( $templateName, variable_space $data );
}


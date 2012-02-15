<?php


namespace de\toxa\txf\datasource;


class datasource_exception extends \Exception
{
	public function __construct( $linkOrStatement )
	{
		if ( $linkOrStatement instanceof connection || $linkOrStatement instanceof statement )
		{
			$command = $linkOrStatement->command;

			if ( $command !== null )
				parent::__construct( sprintf( "%s\n(on %s)", $linkOrStatement->errorText(), $linkOrStatement->command ) );
			else
				parent::__construct( $linkOrStatement->errorText() );
		}
		else
			parent::__construct( 'unknown failure' );
	}
}

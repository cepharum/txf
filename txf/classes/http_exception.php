<?php


/**
 * Exception class for controlling HTTP result at any point of processing a
 * request.
 *
 * @author Thomas Urban <info@toxa.de>
 * @version 1.0
 */


namespace de\toxa\txf;


class http_exception extends \Exception
{
	protected $state;

	public function __construct( $stateCode = 500, $message = null, $state = null )
	{
		assert( '( $stateCode >= 100 ) && ( $stateCode <= 999 )' );

		if ( is_null( $state ) )
			$state = static::getStateOnCode( $stateCode );

		if ( !$state )
			throw new \InvalidArgumentException( 'unknown HTTP state' );

		$this->state = $state;

		parent::__construct( $message, $stateCode );
	}

	protected static function getStateOnCode( $code )
	{
		$map = array(
					200 => 'Success',
					400 => 'Bad Request',
					401 => 'Authorization Required',
					403 => 'Forbidden',
					404 => 'Not Found',
					500 => 'Internal Error',
					);

		return $map[intval( $code )];
	}

	public function getResponse()
	{
		return sprintf( 'HTTP/1.0 %d %s', $this->getCode(), $this->state );
	}
}
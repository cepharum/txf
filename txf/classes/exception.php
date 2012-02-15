<?php


namespace de\toxa\txf;


class exception extends \Exception
{
	public function __construct( $message = '', $code = 0, $previous = null )
	{
		parent::__construct( $message, $code, $previous );
	}

	public function reducedTrace( $asString = false )
	{
		$trace = static::reduceExceptionTrace( $this );
		return $asString ? self::renderTrace( $trace ) : $trace;
	}

	public static function reduceExceptionTrace( \Exception $exception )
	{
		$trace = $exception->getTrace();

		if ( defined( 'TXF_FRAMEWORK_PATH' ) )
			foreach ( $trace as &$frame )
			{
				$frame['file'] = self::reducePathname( $frame['file'] );

				if ( is_array( $frame['args'] ) )
					foreach ( $frame['args'] as &$arg )
						if ( is_string( $arg ) )
							$arg = self::reducePathname( $arg );

				unset( $arg );
			}

		return $asString ? self::renderTrace( $trace ) : $trace;
	}

	public static function renderTrace( $trace )
	{
		foreach ( $trace as $index => $frame )
		{
			if ( $frame['line'] )
				$file = "$frame[file] ($frame[line])";
			else if ( $frame['file'] )
				$file = "$frame[file]";
			else
				$file = '[unknown/internal/global]';

			if ( is_array( $frame['args'] ) )
				$args = implode( ', ', array_map( array( '\de\toxa\txf\data', 'describe' ), $frame['args'] ) );
			else
				$args = '';

			$trace[$index] = sprintf( '#%d %s: %s%s%s(%s)', $index, $file, $frame['class'], $frame['type'], $frame['function'], $args );
		}

		return implode( "\n", $trace );
	}

	public static function reducePathname( $in )
	{
		if ( substr( $in, 0, strlen( TXF_APPLICATION_PATH ) + 1 ) === TXF_APPLICATION_PATH . '/' )
			return '[APP]' . substr( $in, strlen( TXF_APPLICATION_PATH ) );

		if ( substr( $in, 0, strlen( TXF_FRAMEWORK_PATH ) + 1 ) === TXF_FRAMEWORK_PATH . '/' )
			return '[TXF]' . substr( $in, strlen( TXF_FRAMEWORK_PATH ) );

		if ( substr( $in, 0, strlen( TXF_INSTALL_PATH ) + 1 ) === TXF_INSTALL_PATH . '/' )
			return '[BASE]' . substr( $in, strlen( TXF_INSTALL_PATH ) );

		return $in;
	}
}
<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf;


class exception extends \Exception
{
	public function __construct( $message = '', $code = 0, $previous = null )
	{
		parent::__construct( $message, $code, $previous );
	}

	protected static $sensitiveCounter = 0;

	public static function enterSensitive()
	{
		self::$sensitiveCounter++;
	}

	public static function leaveSensitive()
	{
		self::$sensitiveCounter = max( 0, --self::$sensitiveCounter );
	}

	public static function isSensitive()
	{
		return ( self::$sensitiveCounter > 0 );
	}

	public function reducedTrace( $asString = false )
	{
		$trace = static::reduceExceptionTrace( $this );
		return $asString ? self::renderTrace( $trace ) : $trace;
	}

	public static function reduceExceptionTrace( \Exception $exception, $asString = false )
	{
		$trace = $exception->getTrace();

		if ( defined( 'TXF_FRAMEWORK_PATH' ) )
			foreach ( $trace as &$frame )
			{
				$frame['file'] = self::reducePathname( $frame['file'] );

				if ( is_array( $frame['args'] ) )
				{
					$isSensitive = self::isSensitive();

					foreach ( $frame['args'] as &$arg )
						if ( $isSensitive )
							$arg = '?';
						else if ( is_string( $arg ) )
							$arg = self::reducePathname( $arg );
				}

				unset( $arg );
			}

		return $asString ? self::renderTrace( $trace ) : $trace;
	}

	public static function renderTrace( $trace )
	{
		// don't include call invoking error/exception handler
		// for its context causing some trouble (including $GLOBALS with recursive references)
		if ( in_array( $trace[0]['function'], array( 'onException', 'onError' ) ) )
			array_shift( $trace );

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

	/**
	 * Replaces pathnames related to current installation by representing tags.
	 *
	 * This is used e.g. on rendering stack traces to prevent exposure of
	 * sensitive security-related information.
	 *
	 * @param string $in text to reduce contained pathnames in
	 * @return string reduced version of provided string
	 */

	public static function reducePathname( $in )
	{
		if ( is_array( $in ) )
		{
			foreach ( $in as &$value )
				$value = static::reducePathname( $value );
		}
		else if ( is_object( $in ) )
		{
			$class = get_class( $in );
			$props = get_object_vars( $in );

			foreach ( $props as &$value )
				$value = static::reducePathname( $value );

			$in = (object) array( 'reducedClass' => $class, 'properties' => $props );
		}
		else if ( is_string( $in ) )
			$in = preg_replace( array(
						'#' . preg_quote( TXF_APPLICATION_PATH . '/', '#' ) . '#',
						'#' . preg_quote( TXF_FRAMEWORK_PATH . '/', '#' ) . '#',
						'#' . preg_quote( TXF_INSTALL_PATH . '/', '#' ) . '#',
						), array(
						'[APP]/', '[TXF]/', '[BASE]/',
						), $in );

		return $in;
	}
}

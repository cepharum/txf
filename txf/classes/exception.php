<?php


/**
 * Copyright 2012 Thomas Urban, toxA IT-Dienstleistungen
 * 
 * This file is part of TXF, toxA's web application framework.
 * 
 * TXF is free software: you can redistribute it and/or modify it under the 
 * terms of the GNU General Public License as published by the Free Software 
 * Foundation, either version 3 of the License, or (at your option) any later 
 * version.
 * 
 * TXF is distributed in the hope that it will be useful, but WITHOUT ANY 
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR 
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with 
 * TXF. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright 2012, Thomas Urban, toxA IT-Dienstleistungen, www.toxa.de
 * @license GNU GPLv3+
 * @version: $Id$
 * 
 */


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
		return preg_replace( array(
						'#' . preg_quote( TXF_APPLICATION_PATH . '/', '#' ) . '#', 
						'#' . preg_quote( TXF_FRAMEWORK_PATH . '/', '#' ) . '#', 
						'#' . preg_quote( TXF_INSTALL_PATH . '/', '#' ) . '#', 
						), array(
						'[APP]/', '[TXF]/', '[BASE]/',
						), $in );
	}
}
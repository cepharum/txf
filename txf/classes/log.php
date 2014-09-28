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


/**
 * Logging
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 *
 */

class log
{
	/**
	 * mark on whether logging has been prepared already or not
	 *
	 * @var boolean
	 */

	protected static $prepared = false;

	/**
	 * Prepares use of logger.
	 *
	 * This method may be called to switch previous name of application. It
	 * might be called at any time as it's preventing multiple preparations
	 * internally.
	 *
	 * @param string $applicationName name of application to show in logs
	 */

	public static function prepare( $applicationName = null )
	{
		if ( !self::$prepared || ( $applicationName !== null && $applicationName !== self::$prepared ) )
		{
			openlog( ( $applicationName !== null ) ? "TXF.$applicationName" : 'TXF', LOG_NDELAY+LOG_PID, LOG_USER );

			self::$prepared = ( $applicationName !== null ) ? $applicationName : true;
		}
	}

	/**
	 * Logs a message for debugging.
	 *
	 * This method takes a variable number of arguments and processes them
	 * similarly to sprintf(). The resulting message is logged accordingly and
	 * returned for further processing.
	 *
	 * @return string formatted message
	 */

	public static function debug()
	{
		static::prepare();

		$args = func_get_args();

		syslog( LOG_DEBUG, $msg = call_user_func_array( 'sprintf', $args ) );

		return $msg;
	}

	/**
	 * Logs a message containing notice.
	 *
	 * This method takes a variable number of arguments and processes them
	 * similarly to sprintf(). The resulting message is logged accordingly and
	 * returned for further processing.
	 *
	 * @return string formatted message
	 */

	public static function notice()
	{
		static::prepare();

		$args = func_get_args();

		syslog( LOG_NOTICE, $msg = call_user_func_array( 'sprintf', $args ) );

		return $msg;
	}

	/**
	 * Logs a message containing warnings.
	 *
	 * This method takes a variable number of arguments and processes them
	 * similarly to sprintf(). The resulting message is logged accordingly and
	 * returned for further processing.
	 *
	 * @return string formatted message
	 */

	public static function warning()
	{
		static::prepare();

		$args = func_get_args();

		syslog( LOG_WARNING, $msg = call_user_func_array( 'sprintf', $args ) );

		return $msg;
	}

	/**
	 * Logs a message containing error.
	 *
	 * This method takes a variable number of arguments and processes them
	 * similarly to sprintf(). The resulting message is logged accordingly and
	 * returned for further processing.
	 *
	 * @return string formatted message
	 */

	public static function error()
	{
		static::prepare();

		$args = func_get_args();

		syslog( LOG_ERR, $msg = call_user_func_array( 'sprintf', $args ) );

		return $msg;
	}

	/**
	 * Logs a message containing exceptional state.
	 *
	 * This method takes a variable number of arguments and processes them
	 * similarly to sprintf(). The resulting message is logged accordingly and
	 * returned for further processing.
	 *
	 * @return string formatted message
	 */

	public static function exception()
	{
		static::prepare();

		$args = func_get_args();

		syslog( LOG_ALERT, $msg = call_user_func_array( 'sprintf', $args ) );

		return $msg;
	}
}

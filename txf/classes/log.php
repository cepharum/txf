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

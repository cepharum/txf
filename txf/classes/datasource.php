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
 * Manages access on configured connections to datasources
 *
 * This class provides convenient way for accessing instances of datasource
 * connection managers by simply using a configured connection's internal name
 * as static method name retrieving related connection on invocation.
 *
 * @example
 *
 *   datasource::users()
 *
 * retrieves connection to datasource configured with internal name "users".
 *
 *   datasource::default()
 *
 * retrieves connection to explicitly selected default datasource or first
 * configured datasource.
 *
 * Datasources are defined in configuration.
 */

class datasource
{
	/**
	 * cached set of datasource definitions read from configuration once per
	 * runtime
	 *
	 * @var array
	 */

	protected static $definitions = null;

	/**
	 * cached set of established connections to datasources
	 *
	 * @var array
	 */

	protected static $connections = array();



	/**
	 * Ensures to read set of connection definitions from configuration.
	 *
	 * This method is prefetching all named datasource definitions from current
	 * configuration. A named datasource link (here called "customlink") is
	 * defined like this:
	 *
	 * <datasource>
	 *  <link>
	 *   <id>customlink</id>
	 *   <dsn>DSN of my datasource</dsn>
	 *   <user>myloginname</user>
	 *   <password>mysecretpassword</password>
	 *  </link>
	 * </datasource>
	 */

	protected static function getLinks()
	{
		if ( !is_array( self::$definitions ) )
		{
			self::$definitions = array();

			// load all properly named datasource configurations
			foreach ( config::getList( 'datasource.link' ) as $link )
				if ( is_array( $link ) && trim( @$link['id'] ) !== '' )
					self::$definitions[trim( $link['id'] )] = $link;
		}

		return self::$definitions;
	}

	/**
	 * Retrieves connection to datasource selected by internal name.
	 *
	 * The method's name is used to look up connection setup in current
	 * configuration. This connection is then established unless there is a
	 * previously established connection to that datasource to be reused.
	 *
	 * Name "default" is handled specially. The configuraiton may select one of
	 * the configured connections to serve as a default connection. If there is
	 * no such default in configuration, first configured connection is
	 * retrieved instead.
	 *
	 * Configuring connection to datasource requires at least these properties:
	 *
	 *   id       internal name of connection
	 *   class    name of class used to manage connections to desired datasource
	 *
	 * In addition these basic options are supported as well:
	 *
	 *   dsn        data source name
	 *   user       username for authentication
	 *   password   password for authentication
	 *
	 * @param string $name name of method/datasource to retrieve connection to
	 * @return datasource\connection established connection to datasource
	 */

	public static function selectConfigured( $name, $reentrant = false )
	{
		/*
		 * look for cached connection matching selected name
		 */

		if ( array_key_exists( $name, self::$connections ) )
			return self::$connections[$name];


		// normalize selected datasource's name
		$name = trim( $name );

		// ensure to cache list of all defined datasources
		static::getLinks();

		// detect request for special name "default"
		if ( $name ===  'default' )
		{
			// lookup name of default connection
			$defaultName = trim( config::get( 'datasource.default' ) );
			if ( $defaultName !== '' )
				if ( !array_key_exists( $defaultName, self::$definitions ) )
					// explicitly selected default datasource isn't configured
					// -> ignore explicit selection
					$defaultName = '';

			if ( $defaultName === '' )
				// there isn't any explicit selection of a default configuration
				// -> is there at least one configured connection?
				if ( count( self::$definitions ) )
				{
					$links = array_values( self::$definitions );
					$link  = array_shift( $links );

					if ( array_key_exists( 'id', $link ) )
						// ---> yes it is, so choose first to be default
						$defaultName = trim( $link['id'] );
				}

			// have found any default datasource to use?
			if ( $defaultName !== '' && $defaultName !== $name )
				// -> yes, so retrieve it recursively
				return static::selectConfigured( $defaultName, true );
		}


		// look for definition matching selected internal name
		if ( array_key_exists( $name, self::$definitions ) )
		{
			// got selected connection
			// -> instantiate class managing connection
			$class = @self::$definitions[$name]['class'];
			if ( !$class )
				$class = __NAMESPACE__ . '\datasource\pdo';

			$creator = new \ReflectionClass( $class );

			return self::$connections[$name] = $creator->newInstance(
													@self::$definitions[$name]['dsn'],
													@self::$definitions[$name]['user'],
													@self::$definitions[$name]['password'],
													self::$definitions[$name] );
		}


		// haven't found selected datasource
		// -> use default as a fallback
		if ( !$reentrant )
			return static::selectConfigured( 'default', true );


		throw new \RuntimeException( 'missing configured datasource: ' . $name );
	}

	public static function __callStatic( $name, $arguments )
	{
		static::selectConfigured( $name );
	}
}


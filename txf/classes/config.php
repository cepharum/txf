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
 * API for runtime configuration support
 *
 * @author Thomas Urban
 */


class config extends singleton
{
	protected $cached = array();


	public static function singleSelectOnly()
	{
		return true;
	}

	public function onLoad()
	{
		$this->import();
	}

	/**
	 * Compiles current configuration from different sources selected by
	 * current context and overloading/extending each other.
	 *
	 */

	protected function import()
	{

		/*
		 * prepare coordinates for collecting all interesting sources/files
		 */

		// may have separate sources in TXF and application context
		$patterns = array(
						TXF_INSTALL_PATH . '/config/%A/%H.xml',
						TXF_APPLICATION_PATH . '/config/%H.xml',
						);

		// may have sources depending on application's name
		$applications = array( 'default', TXF_APPLICATION );

		// may have sources depending on used hostname
		$hosts = array();
		$temp  = explode( '.', $_SERVER['HTTP_HOST'] );

		while ( count( $temp ) )
		{
			$hosts[] = implode( '.', $temp );
			array_shift( $temp );
		}

		$hosts[] = 'default';
		$hosts   = array_reverse( $hosts );



		/*
		 * select all files to read configuration from with latter overlaying former
		 */

		$files = array();

		foreach ( $patterns as $pattern )
			foreach ( $applications as $application )
				foreach ( $hosts as $host )
				{
					$filename = strtr( $pattern, array( '%A' => $application, '%H' => $host ) );
					if ( $files[$filename] )
						// always include repeating file in its highest priority
						unset( $files[$filename] );
					else
						// haven't tested before whether file exists or not
						// --> check now
						if ( !file_exists( $filename ) )
							continue;

					$files[$filename] = $filename;
				}



		/*
		 * read selected files each extending/overlaying its predecessors' setup
		 */

		$setup = new set;

		foreach ( $files as $file )
		{
			$xml = simplexml_load_file( $file );
			$setup->extend( set::fromXml( $xml ) );
		}


		// save read configuration in runtime
		$this->cached = $setup;
	}

	/**
	 * Reads part of current configuration.
	 *
	 * This method enables to select a subset of current configuration by
	 * utilizing class set and its method read() for addressing subset.
	 *
	 * @note Prefer config::getList() whenever you want to fetch a set of
	 *       homogenic options, e.g. a list of files to include.
	 *
	 * @param string $path path of subset
	 * @param mixed $default value to use by default
	 * @return mixed selected subset or $default if former is missing
	 */

	public static function get( $path, $default = null )
	{
		return static::current()->cached->read( $path, $default );
	}

	/**
	 * Reads set of configuration options.
	 *
	 * In opposition to config::get() this method is always returning a set of
	 * matches.
	 *
	 * @example config::get( "view.asset" ) is considered to return a set of
	 *          asset nodes subordinated to a view node. Each expected asset
	 *          node is considered having further subordinated nodes.
	 *
	 *          When there is a single asset node only, config::get() is
	 *          returning array of that single asset node's subordinated nodes.
	 *
	 *          config::getList(), however, tries to detect such situations
	 *          returning a single asset node containing all the subordinated
	 *          nodes of config::get(), then.
	 *
	 * @param string $path path of subset
	 * @param array $default default set of options to retrieve by default
	 * @return array set of configuration options, default on mismatch
	 */

	public static function getList( $path, $default = array() )
	{
		$result = static::current()->cached->read( $path, null );
		if ( !is_array( $result ) )
			return is_null( $result ) ? $default : array( $result );

		foreach ( $result as $key => $value )
		{
			if ( !is_integer( $key ) )
				return array( $result );
		}

		return $result;
	}

	/**
	 * Detects if configuration knows about a particular information bit.
	 *
	 * @param string $path path of information to test
	 * @return boolean true if config contains requested information bit
	 */

	public static function has( $path )
	{
		return static::current()->cached->has( $path );
	}
}


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
						TXF_FRAMEWORK_PATH . '/config/%A/%H.xml',
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
			$setup->extendFromXml( $xml );
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
		try {
			return static::current()->cached->read( $path, $default );
		} catch ( \UnexpectedValueException $e ) {
			return $default;
		}
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


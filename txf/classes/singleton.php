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
 * Singleton management API
 *
 * This class implements base API shared by classes designed to work with
 * singleton instances.
 *
 * This basic interface includes
 *
 *   - selecting singleton instance - either once only or multiple times
 *   - retrieving singleton instance
 *   - conveniently accessing properties of current singleton instance
 *   - overloadable methods called on selecting/unselecting singleton instance
 *     (e.g. to load/unload associated resources)
 *
 * Method(s) that SHOULD BE overloaded:
 *   singleSelectOnly()
 *
 * Method(s) that MAY BE overloaded:
 *   onLoad()
 *   onUnload()
 *
 */


class singleton
{

	/**
	 * singleton instance currently in use
	 *
	 * @var singleton
	 */

	public static $current;



	/**
	 * Processes call for method not available as a static method.
	 *
	 * Due to managing singleton this method is using any available singleton
	 * instance.
	 *
	 * @note This method is REQUIRED to support conveniently getting protected
	 *       properties of managed singleton instances using magic getter in
	 *       static context, e.g. using txf::getContext() instead of
	 *       txf::current()->getContext() for accessing context instance.
	 *
	 *       This feature DOES NOT WORK on actually implemented methods, though.
	 *
	 * @param string $method name of method initially called
	 * @param array $arguments set of arguments in call to that method
	 * @return mixed result returned from available non-static method
	 */

	public static function __callStatic( $method, $arguments )
	{
		$current = static::current();
		if ( $current )
		{
			$handler = array( &$current, $method );
			if ( is_callable( $handler ) )
				return call_user_func_array( $handler, $arguments );

			throw new \BadMethodCallException( 'unknown method: ' . $method );
		}

		throw new \BadMethodCallException( 'invalid call for method without context: ' . $method );
	}


	/**
	 * Processes calls for unknown methods.
	 *
	 * This magic method is supporting use of getter calls from static context.
	 * While __get() works great for non-static contexts this method in
	 * combination with __callStatic() enables conveniently reading properties
	 * of a singleton instance. Magic static getters are derived from property's
	 * name by converting its first letter to uppercase and prepend the "get".
	 *
	 * @example view::getCurrentTheme() is reading view::$current->currentTheme
	 *
	 * @param string $method name of method originally called
	 * @param array $arguments set of arguments passed in calling that method
	 * @return mixed (return ) value of called method (or property)
	 */

	public function __call( $method, $arguments )
	{
		if ( substr( $method, 0, 3 ) === 'get' )
			if ( empty( $arguments ) )
			{
				$property = substr( $method, 3 );

				for ( $i = 0; $i < 2; $i++ )
				{
					if ( property_exists( $this, $property ) )
						return $this->$property;

					// method is considered camel-case
					// -> property has been tested using uppercase initial letter
					//    -> try lowercase initial letter as well
					$property[0] = strtolower( $property[0] );
				}

				throw new \BadMethodCallException( 'invalid call for getter of unknown property' );
			}


		throw new \BadMethodCallException( 'invalid call for method' );
	}


	/**
	 * Initializes singleton setup by selecting new instance of lately bound
	 * class.
	 */

	public static function init()
	{
		static::select( new static );
	}


	/**
	 * Selects different singleton instance to be used furtheron.
	 *
	 * @param singleton $instance instance of lately bound class to become
	 *                             singleton instance
	 * @return singleton any previously selected instance
	 */

	public static function select( $instance )
	{
		if ( static::singleSelectOnly() )
			if ( static::__readCurrent() )
				throw new \BadMethodCallException( 'invalid request for changing singleton selection' );


		$previous = null;

		if ( static::__readCurrent() )
		{
			static::__readCurrent()->onUnload();

			$previous = static::__readCurrent();
		}


		if ( $instance instanceof static )
			static::__writeCurrent( $instance );
		else
			throw new \InvalidArgumentException( 'invalid type of instance' );


		static::__readCurrent()->onLoad();


		return $previous;
	}

	/**
	 * Marks whether changing currently selected singleton instance is enabled
	 * or not.
	 *
	 * @return boolean true on disabling change of currently selected singleton
	 */

	public static function singleSelectOnly()
	{
		return false;
	}

	/**
	 * Processes initialization of singleton instance on selecting it.
	 */

	protected function onLoad() {}

	/**
	 * Processes shutdown of singleton instance on being replaced by another
	 * instance.
	 */

	protected function onUnload() {}

	/**
	 * Detects whether there is a currently selected singleton instance or not.
	 *
	 * @return boolean true on having selected current singleton instance
	 */

	public static function hasCurrent()
	{
		return ( static::__readCurrent() != false );
	}

	/**
	 * Retrieves selected singleton instance.
	 *
	 * This method is implicitly initializing class on demand.
	 *
	 * @return static current instance
	 */

	public static function current()
	{
		if ( !static::__readCurrent() )
			static::init();

		$current = static::__readCurrent();
		if ( $current )
			return $current;

		throw new \BadMethodCallException( 'context not a available' );
	}

	/**
	 * Retrieves selected singleton instance of called class.
	 *
	 * In opposition to self::current() this method isn't initializing on demand
	 * and thus may respond faster. It's considered convenient wrapper for
	 * managing static array of singleton instances - one per derived class.
	 *
	 * @return singleton current instance or null, if missing
	 */

	protected static function __readCurrent()
	{
		if ( !is_array( self::$current ) )
			self::$current = array();

		$name = \get_called_class();
		return self::$current[$name] instanceof self ? self::$current[$name] : null;
	}

	/**
	 * Updates singleton instance to be selected currently.
	 *
	 * This is an internal wrapper for write-accessing collection of singleton
	 * instances. Refer to self::select() for public interface.
	 *
	 * @param singleton $instance singleton instance to be set
	 */

	private static function __writeCurrent( $instance )
	{
		assert( '$instance instanceof static' );

		if ( !is_array( self::$current ) )
			self::$current = array();

		$name = \get_called_class();
		self::$current[$name] = $instance;
	}
}


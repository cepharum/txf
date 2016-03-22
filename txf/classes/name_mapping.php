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
 * Implements common support for mapping data records.
 *
 * Mapping data records may be integrated in database adapters to work with
 * arbitrary data sources using different names per property. While local code
 * is working a fixed set of property names per record, reading from or writing
 * to datasource may use mapped property names to support schema of particula
 * datasource.
 *
 * @package de\toxa\txf
 */

class name_mapping
{
	protected static $_mappings = array();

	/**
	 * Reads mapping definition of selected data set into runtime and prepares
	 * it for improved mapping operation.
	 *
	 * @param string $setName name of data set to be mapped
	 * @param boolean $forward true to get forward mapping, false to get reverse mapping
	 * @return array mapping of names for selected data set in desired direction
	 * @throws \InvalidArgumentException on using invalid name data set
	 */

	protected static function getDefinition( $setName, $forward ) {
		if ( !array_key_exists( $setName, static::$_mappings ) ) {
			if ( !preg_match( '/^([a-z][a-z0-9_]+)(\.([a-z][a-z0-9_]+))*$/i', $setName ) )
				throw new \InvalidArgumentException( 'invalid set name' );

			static::$_mappings[$setName] = array( 'forward' => array(), 'backward' => array() );

			foreach ( config::getList( 'mappings.' . $setName . '.map' ) as $def ) {
				if ( is_array( $def ) ) {
					$from = trim( @$def['from'] );
					$to   = trim( @$def['to'] );
					$drop = !!@$def['drop'] || $to === '';

					static::$_mappings[$setName]['forward'][$from] = $drop ? null : $to;

					if ( $drop ) {
						static::$_mappings[$setName]['backward'][$to] = $from;
					}
				}
			}

			unset( $definition );
		}

		return $forward ? static::$_mappings[$setName]['forward'] : static::$_mappings[$setName]['backward'];
	}

	/**
	 * Maps single name of property used in records related to named data set.
	 *
	 * @param string $name name of property to map
	 * @param string $setName name data set property is related to
	 * @return string|null mapped name of property, might be null to drop property
	 */

	public static function mapSingle( $name, $setName ) {
		$mapping = static::getDefinition( $setName, true );

		return array_key_exists( $name, $mapping ) ? $mapping[$name] : $name;
	}

	/**
	 * Reversely maps single name of property used in records related to named
	 * data set.
	 *
	 * @param string $name name of property to map
	 * @param string $setName name data set property is related to
	 * @return string|null mapped name of property, might be null to drop property
	 */

	public static function mapSingleReversely( $name, $setName ) {
		$mapping = static::getDefinition( $setName, false );

		return array_key_exists( $name, $mapping ) ? $mapping[$name] : $name;
	}

	/**
	 * Maps property names in provided record related to named set of data.
	 *
	 * @param array $record properties of single data record to map
	 * @param string $setName name of data set record is contained
	 * @param boolean $forward true to map forwardly, false to apply reversely
	 * @return array properties of record with mapped names
	 * @throws \InvalidArgumentException on using invalid name data set
	 */

	protected static function apply( $record, $setName, $forward ) {
		$mapping = static::getDefinition( $setName, $forward );

		$out = array();

		foreach ( $record as $key => $value ) {
			if ( array_key_exists( $key, $mapping ) ) {
				$map = $mapping[$key];
				if ( !is_null( $map ) )
					$out[$map] = $value;
			} else {
				$out[$key] = $value;
			}
		}

		return $out;
	}

	/**
	 * Maps property names in provided record related to named set of data.
	 *
	 * @param array $record properties of single data record to map
	 * @param string $setName name of data set record is contained
	 * @return array properties of record with mapped names
	 * @throws \InvalidArgumentException on using invalid name data set
	 */

	public static function map( $record, $setName ) {
		return static::apply( $record, $setName, true );
	}

	/**
	 * Reverts mapping of property names in provided record related to named
	 * set of data.
	 *
	 * @param array $record properties of single data record to map
	 * @param string $setName name of data set record is contained
	 * @return array properties of record with mapped names
	 * @throws \InvalidArgumentException on using invalid name data set
	 */

	public static function mapReversely( $record, $setName ) {
		return static::apply( $record, $setName, false );
	}
}

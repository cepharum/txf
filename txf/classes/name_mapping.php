<?php
/**
 * Copyright (c) 2013-2014, cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban <thomas.urban@cepharum.de>
 * @project: Lebenswissen
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

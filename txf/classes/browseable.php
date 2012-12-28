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
 * Describes interface for fetching optionally sorted excerpt of a set of data
 * to be listed/browsed.
 *
 * @author Thomas Urban
 */

interface browseable
{
	/**
	 * Requests to fetch data sorted by selected property in selected order.
	 *
	 * @note Multiple requests for sorting by some property are cumulated to
	 *       sort by either property in decreasing priority. Succeeding calls of
	 *       sortyBy() will be ignored on datasources not supporting sorting by
	 *       multiple properties.
	 *
	 * @param string $property name of property to sort data by
	 * @param boolean $ascending true for sorting by property in ascending order
	 * @return browseable current instance
	 */

	public function sortBy( $property, $ascending );

	/**
	 * Requests to fetch selected number of items on fetching data.
	 *
	 * @param integer $count number of items to skip on fetching
	 * @return browseable current instance
	 */

	public function offset( $count );

	/**
	 * Requests to fetch selected number of items at most.
	 *
	 * @param integer $count number of items to fetch at most
	 * @return browseable current instance
	 */

	public function size( $count );

	/**
	 * Fetches items matching all previous criteria.
	 *
	 * @return array[array] items fetched from datasource
	 */

	public function items();

	/**
	 * Fetches number of items in datasource.
	 *
	 * @return integer number of items in datasource
	 */

	public function count();

	/**
	 * Retrieves name describing current datasource/data in detail.
	 *
	 * This name is designed to be used in class-attributes of HTML code
	 * genereated by consumers.
	 *
	 * @return string|null name of datasource, e.g. "users", "ldap-tree", "authenticated-users", null on an unnamed datasource
	 */

	public function name();
}

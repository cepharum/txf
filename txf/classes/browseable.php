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

	public function sortBy( $property, $ascending = true );

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

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

namespace de\toxa\txf\datasource;


/**
 * Describes interface for working with a single statement or query sent to some
 * connected data source.
 *
 * @package de\toxa\txf\datasource
 */

interface statement
{
	/**
	 * Executes provided statement using provided arguments for binding markers
	 * in statement.
	 *
	 * Parameters might be given in separate arguments to execute() or as an
	 * array in first argument.
	 *
	 * @param mixed ... values for parameter binding
	 * @return $this
	 */

	public function execute();

	/**
	 * Closes statement releasing all associated resources.
	 *
	 * Closing a result set might be required prior to querying datasource for
	 * another set. Fetching last row of a set used to close it implicitly.
	 *
	 * @return $this
	 */

	public function close();

	/**
	 * Retrieves number of matches in result set to current statement.
	 *
	 * @note This method may be called after executing statement, only.
	 *
	 * @return int
	 */

	public function count();

	/**
	 * Fetches first cell of another record of result set to current statement.
	 *
	 * This method is operating on first result in result set when called for
	 * the first time after invoking execute(). Any succeeding call is fetching
	 * another record from result set for operating on that.
	 *
	 * @note This method may be called after executing statement, only.
	 *
	 * @return mixed|false
	 */

	public function cell();

	/**
	 * Fetches another record of result set to current statement.
	 *
	 * @note This method may be called after executing statement, only.
	 *
	 * @return mixed
	 */

	public function row();

	/**
	 * Fetches all records of result set to current statement.
	 *
	 * Fetching "all" records might be limited to fetching "all outstanding"
	 * records only due to the underlying API or the support for forward-only
	 * result set cursors.
	 *
	 * @note This method may be called after executing statement, only.
	 *
	 * @return mixed
	 */

	public function all();

	/**
	 * Retrieves recent-most error message.
	 *
	 * @return string message describing recent-most error on statement
	 */

	public function errorText();

	/**
	 * Retrieves recent-most error code.
	 *
	 * @return string code describing recent-most error on statement
	 */

	public function errorCode();
}

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

use \de\toxa\txf\datasource\connection as db;


/**
 * Role Management API
 *
 * @author Thomas Urban
 */

interface role
{
	/**
	 * Creates role manager instance on selected role to be managed in provided
	 * datasource.
	 *
	 * @param \de\toxa\txf\datasource\connection $source backing datasource, omit for current one
	 * @param string|role $role role to manage
	 * @return role
	 */

	public static function select( db $source = null, $role );

	/**
	 * Tests if provided user is adopting current role.
	 *
	 * @param \de\toxa\txf\user $user user to check for adopting current role
	 * @return boolean true on user is adopting role
	 * @throws \LogicException
	 */

	public function isAdoptedByUser( user $user );

	public function __get( $property );

	/**
	 * Makes user adopting current role.
	 *
	 * @param user $user user to adopt current role
	 * @return $this
	 * @throws \Exception on failing to adopt role
	 */

	public function makeAdoptedBy( user $user );
}

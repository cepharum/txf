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
 * Exception class to throw on unauthorized operations.
 *
 * @author Thomas Urban <info@toxa.de>
 * @version 1.0
 */


class unauthorized_exception extends \Exception
{
	const USER_NOT_FOUND = 1;
	const TOKEN_MISMATCH = 2;
	const ACCOUNT_LOCKED = 3;
	const REAUTHENTICATE = 4;

	/**
	 * associated user account
	 *
	 * @var user
	 */

	protected $_user = null;


	public function __construct( $message = null, $code = null, user $user = null )
	{
		parent::__construct( $message, $code );

		$this->_user = $user;
	}

	public function isAccountLocked()
	{
		return $this->getCode() == self::ACCOUNT_LOCKED;
	}

	public function isTokenMismatch()
	{
		return $this->getCode() == self::TOKEN_MISMATCH;
	}

	public function isUserNotFound()
	{
		return $this->getCode() == self::USER_NOT_FOUND;
	}

	public function isOnReauthenticate()
	{
		return $this->getCode() == self::REAUTHENTICATE;
	}

	/**
	 * Fetches user account optionally associated with exception.
	 *
	 * @return user
	 */

	public function getUser()
	{
		return $this->_user;
	}
}

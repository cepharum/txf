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

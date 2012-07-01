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
 * Exception class for controlling HTTP result at any point of processing a
 * request.
 *
 * @author Thomas Urban <info@toxa.de>
 * @version 1.0
 */


class http_exception extends \Exception
{
	protected $state;

	/**
	 * @param integer $stateCode HTTP state code, e.g. 200 on success, 403 on missing authentication
	 * @param string $message human-readable description of exception's cause in detail
	 * @param string $state short description of state as defined in HTTP standard, e.g. "Not found" on 404
	 */

	public function __construct( $stateCode = 500, $message = null, $state = null )
	{
		assert( '( $stateCode >= 100 ) && ( $stateCode <= 999 )' );

		if ( is_null( $state ) )
			$state = static::getStateOnCode( $stateCode );

		if ( !$state )
			throw new \InvalidArgumentException( 'unknown HTTP state' );

		$this->state = $state;

		parent::__construct( $message, $stateCode );
	}

	protected static function getStateOnCode( $code )
	{
		$map = array(
					200 => 'Success',
					400 => 'Bad Request',
					401 => 'Authorization Required',
					403 => 'Forbidden',
					404 => 'Not Found',
					500 => 'Internal Error',
					);

		return $map[intval( $code )];
	}

	public function getResponse()
	{
		return sprintf( 'HTTP/1.0 %d %s', $this->getCode(), $this->state );
	}

	public function __toString()
	{
		return strip_tags( $this->asHTML() );
	}

	public function asHtml()
	{
		$code    = $this->getCode();
		$message = $this->getMessage();

		return <<<EOT
<h1>$code {$this->state}</h1>
<p>$message</p>
EOT;
	}
}

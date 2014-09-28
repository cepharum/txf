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
	 * @param \Exception $previous previous exception to link with current one
	 */

	public function __construct( $stateCode = 500, $message = null, $state = null, \Exception $previous = null )
	{
		assert( '( $stateCode >= 100 ) && ( $stateCode <= 999 )' );

		if ( is_null( $state ) )
			$state = static::getStateOnCode( $stateCode );

		if ( !$state )
			throw new \InvalidArgumentException( 'unknown HTTP state' );

		$this->state = $state;

		parent::__construct( $message, $stateCode, $previous );
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

	public function getStatus()
	{
		$status = static::getStateOnCode( $this->getCode() );
		if ( $status == null )
			$status = 'Error';

		return $status;
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

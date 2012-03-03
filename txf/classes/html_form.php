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
 * HTML-based form manager
 *
 * This class provides API for basically building HTML-based forms.
 *
 */

class html_form
{
	/**
	 * internal name of form
	 *
	 * @var string
	 */

	protected $name;

	/**
	 * mark on whether building form for POST method instead of GET or not
	 *
	 * @var boolean
	 */

	protected $usePost;

	/**
	 * explicit URL of script to process form data
	 *
	 * @var string
	 */

	protected $processorUrl;

	/**
	 * maxmimum size of a file to be uploadable
	 *
	 * This property is also used to decide if form is considered to support
	 * form-based file uploads.
	 *
	 * @var integer
	 */

	protected $maxFileSize;

	/**
	 * html code collected to be wrapped in form
	 *
	 * @var string
	 */

	protected $code = '';



	public function __construct( $name )
	{
		$name = trim( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'missing valid form name' );

		$this->name = $name;
	}

	/**
	 * Name of ID variable used to prevent XSRF attacks.
	 *
	 * @return string
	 */

	protected function idName()
	{
		return md5( 'formid::' . application::current()->name . '::' . $this->name );
	}

	/**
	 * Value of ID variable used to prevent XSRF attacks.
	 *
	 * @return string
	 */

	protected function idValue()
	{
		return preg_replace( '/[^a-z0-9_]/i', '', base64_encode( gzcompress( sha1( $this->name . application::current()->name . $_SERVER['REMOTE_ADDR'] ), 9 ) ) );
	}

	/**
	 * Detects if current input is considered containing input for current form.
	 *
	 * @return boolean true if form has actual input, false otherwise
	 */

	public function hasInput()
	{
		try
		{
			$source = input::source( $this->usePost ? input::SOURCE_ACTUAL_POST : input::SOURCE_ACTUAL_GET );
			if ( $source->hasValue( $this->idName() ) )
			{
				$value = $source->getValue( $this->idName() );

				return ( $value == $this->idValue() );
			}
		}
		catch ( \InvalidArgumentException $e )
		{
		}

		return false;
	}

	/**
	 * Selects script to process form data.
	 *
	 * @param string $processorUrl url of script processing form's data
	 */

	public function sendToUrl( $processorUrl )
	{
		if ( trim( $processorUrl ) )
			$this->processorUrl = trim( $processorUrl );
		else
			$this->processorUrl = null;
	}

	/**
	 * Requests generation of GET form.
	 *
	 * @return html_form
	 */

	public function get()
	{
		$this->usePost = false;
		$this->maxFileSize = null;

		return $this;
	}

	/**
	 * Requests generation of POST form.
	 *
	 * @return html_form
	 */

	public function post()
	{
		$this->usePost = true;

		return $this;
	}

	/**
	 * Requests generation of form supporting file upload.
	 *
	 * This is implicitly requesting to generate a POST form.
	 *
	 * @param integer $maxSize number of bytes to be uploadable at maximum
	 * @return html_form
	 */

	public function enableFileUpload( $maxSize = null )
	{
		if ( is_null( $maxSize ) )
			$maxSize = config::get( 'input.file-upload.max-size', 1024*1024 );

		if ( intval( $maxSize ) <= 0 )
			throw new \InvalidArgumentException( 'invalid file size' );

		$this->usePost = true;
		$this->maxFileSize = intval( $maxSize );

		return $this;
	}

	/**
	 * Adds provided code to form's content.
	 *
	 * @param string $htmlCode code to add to form's content
	 * @param boolean $prepend true to prepend code, false to append
	 * @return html_form
	 */

	public function addContent( $htmlCode, $prepend = false )
	{
		if ( $prepend )
			$this->code = _S($htmlCode)->asUtf8 . $this->code;
		else
			$this->code .= _S($htmlCode)->asUtf8;

		return $this;
	}

	/**
	 * Renders HTML code of form.
	 *
	 * @return string generated HTML code
	 */

	public function compile()
	{
		$method = $this->usePost ? 'POST' : 'GET';
		$action = is_null( $this->processorUrl ) ? application::current()->selfUrl() : $this->processorUrl;

		$mime = $this->maxFileSize > 0 ? ' enctype="multipart/form-data"' : '';
		$size = $this->maxFileSize > 0 ? '<input type="hidden" name="MAX_FILE_SIZE" value="' . $this->maxFileSize . "\"/>\n\t" : '';

		$name = html::idname( $this->name );

		$idName = $this->idName();
		$idValue = $this->idValue();

		return <<<EOT
<form action="$action" method="$method"$mime id="$name">
	$size<input type="hidden" name="$idName" value="$idValue"/>
$this->code
</form>
EOT;
	}

	public function __toString()
	{
		return $this->compile();
	}
}


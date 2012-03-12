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

class html_form implements widget
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

	protected $code = '%%%%ROWS_STACK%%%%';


	/**
	 * heap of field rows
	 * 
	 * @var hash
	 */

	protected $rows = array();

	/**
	 * cached template used for rendering single row of form described by setRow()
	 * 
	 * @var string
	 */

	protected static $rowTemplate = null;



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
		return preg_replace( '/[^a-z0-9_]/i', '', base64_encode( gzcompress( sha1( $this->name . '|' . application::current()->name . '|' . $_SERVER['REMOTE_ADDR'] ), 9 ) ) );
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
	 * Appends new row or adjusts previously appended one selected by its name.
	 * 
	 * This method is selecting row of form by its internal name for 
	 * modification. If selected row isn't found it is appended to collection.
	 * 
	 * A row consists of several properties including label, some control/widget
	 * code, mark on being mandatory, a hint and an error message.
	 * 
	 * All arguments but $name are optional and may be null to ignore related
	 * property of row in current request. Providing false for string properties
	 * requests to drop related property. Provided property values are extending
	 * existing ones in case of strings (except $class), but provided strings 
	 * are managed as chunks internally until final rendering of a row's code.
	 * 
	 * NOTE! String chunks are appended to existing properties unless starting 
	 *       with a vertical pipe requesting to prepend it.
	 * 
	 * @param string $name unique internal name of row to modify
	 * @param string|null $label label to render next to the field/row
	 * @param string|null $htmlCode another widget/control's code to contain in row
	 * @param boolean|null $mandatory mark on whether this field must be filled by user (This is affecting visual representation, only!)
	 * @param string|null $hint another hint supporting use of row/field
	 * @param string|null $error another error message to associate with row/field
	 * @param string|null $class HTML class of row
	 * @return html_form current instance for chaining calls
	 */

	public function setRow( $name, $label = null, $htmlCode = null, $mandatory = null, $hint = null, $error = null, $class = null )
	{
		$name = trim( $name );
		if ( $name === '' )
			throw new \InvalidArgumentException( 'missing row name' );


		// select existing row or append new row if selected one isn't found
		if ( !array_key_exists( $name, $this->rows ) )
			$this->rows[$name] = array();

		$row =& $this->rows[$name];


		// transfer all provided string properties of row
		foreach ( array( 'label', 'htmlCode', 'hint', 'error' ) as $var )
			if ( !is_null( $$var ) )
			{
				if ( $$var === false )
					unset( $row[$var] );
				else
				{
					if ( !is_array( @$row[$var] ) )
						@$row[$var] = array();

					$str = strval( $$var );

					if ( $str[0] === '|' )
						array_unshift( $row[$var], substr( $str, 1 ) );
					else
						$row[$var][] = $str;
				}
			}

		// transfer provided string properties of row replacing previous value
		if ( !is_null( $class ) )
		{
			if ( $class )
				$row['class'] = html::classname( $class );
			else
				unset( $row['class'] );
		}

		// transfer provided boolean properties of row
		if ( !is_null( $mandatory ) )
		{
			if ( $mandatory )
				$row['mandatory'] = true;
			else
				unset( $row['mandatory'] );
		}


		return $this;
	}

	/**
	 * Adjusts label of selected row in current form.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param string $label text to append to row's label, false to reset
	 * @return html_form current instance for chaining calls
	 */

	public function setRowLabel( $name, $label )
	{
		return $this->setRow( $name, $label );
	}

	/**
	 * Adjusts HTML code of selected row in current form.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param string $code HTML code to append to row's code, false to reset
	 * @return html_form current instance for chaining calls
	 */

	public function setRowCode( $name, $code )
	{
		return $this->setRow( $name, null, $code );
	}

	/**
	 * Adjusts mark on whether field(s) in row is/are mandatory or not.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param boolean $blnIsMandatory if true, field(s) in row get(s) mandatory
	 * @return html_form current instance for chaining calls
	 */

	public function setRowIsMandatory( $name, $blnIsMandatory )
	{
		return $this->setRow( $name, null, null, !!$blnIsMandatory );
	}

	/**
	 * Adjusts hint of selected row in current form.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param string $hint text of another hint to add, false to reset
	 * @return html_form current instance for chaining calls
	 */

	public function setRowHint( $name, $hint )
	{
		return $this->setRow( $name, null, null, null, $hint );
	}

	/**
	 * Adjusts error message of selected row in current form.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param string $error text of another error message to add, false to reset
	 * @return html_form current instance for chaining calls
	 */

	public function setRowError( $name, $error )
	{
		return $this->setRow( $name, null, null, null, null, $error );
	}

	/**
	 * Adjusts class of selected row in current form.
	 * 
	 * @param string $name name of row, row is added if missing
	 * @param string $class class name(s)
	 * @return html_form current instance for chaining calls
	 */

	public function setRowClass( $name, $class )
	{
		return $this->setRow( $name, null, null, null, null, null, $class );
	}

	/**
	 * Retrieves template for rendering rows managed by setRow().
	 * 
	 * This method manages runtime caching request for template to prevent 
	 * frequent lookups in configuration.
	 * 
	 * The provided template is used in a call to sprintf() with HTML class 
	 * name, compiled label, code, hint and error as further arguments.
	 * 
	 * @return string template to use on rendering single row of form
	 */

	protected static function getRowTemplate()
	{
		if ( is_null( self::$rowTemplate ) )
			self::$rowTemplate = config::get( 'html.form.row', <<<EOT
<div class="form-row %s">
<label>%s</label>
<div class="form-row-data">%s%s%s</div>
</div>
EOT
											);

		return self::$rowTemplate;
	}

	/**
	 * Renders HTML code of form.
	 *
	 * @return string generated HTML code
	 */

	public function getCode()
	{
		$method = $this->usePost ? 'POST' : 'GET';
		$action = is_null( $this->processorUrl ) ? application::current()->selfUrl() : $this->processorUrl;

		$mime = $this->maxFileSize > 0 ? ' enctype="multipart/form-data"' : '';
		$size = $this->maxFileSize > 0 ? '<input type="hidden" name="MAX_FILE_SIZE" value="' . $this->maxFileSize . "\"/>\n\t" : '';

		$name = html::idname( $this->name );

		$idName = $this->idName();
		$idValue = $this->idValue();


		// compile code of form's rows
		$template = self::getRowTemplate();

		$rows = array_filter( $this->rows, function( $row ) { return !!count( $row ); } );
		$rows = array_map( function( $row ) use ( $template )
		{
			$label = view::wrapNotEmpty( @$row['label'], '', "\n" );
			$code  = view::wrapNotEmpty( @$row['htmlCode'], '', "\n" );
			$hint  = view::wrapNotEmpty( @$row['hint'], '<span class="hint">', "</span>\n" );
			$error = view::wrapNotEmpty( @$row['error'], '<span class="error">', "</span>\n" );

			$mandatory = @$row['mandatory'] ? config::get( 'html.form.mandatory', '<span class="mandatory">*</span>' ) : '';

			if ( trim( $label ) !== '' )
				$label = sprintf( config::get( 'html.form.label', '%s%s:' ), $label, $mandatory );

			return sprintf( $template, @$row['class'], $label, $code, $error, $hint );
		}, $rows );

		// embed compiled rows in form's custom content
		$code = str_replace( '%%%%ROWS_STACK%%%%', implode( '', $rows ), $this->code );


		return <<<EOT
<form action="$action" method="$method"$mime id="$name">
	$size<input type="hidden" name="$idName" value="$idValue"/>
$code
</form>
EOT;
	}

	public function __toString()
	{
		return $this->getCode();
	}
}


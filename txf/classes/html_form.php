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
 * HTML-based form manager
 *
 * This class provides API for basically building HTML-based forms.
 *
 * @method string setStaticRow( string $name, string $label, string $value ) adds/updates single-line with static code to form
 * @method string setTexteditRow( string $name, string $label, string $value = null ) adds/updates single-line text edit control to form
 * @method string setPasswordRow( string $name, string $label ) adds/updates single-line password edit control to form
 * @method string setButtonRow( string $name, string $label, string $value = null ) adds/updates single button control to form
 * @method string setSelectorRow( string $name, string $label, array $options, string $value = null ) adds/updates drop down list control to form
 * @method string setTextareaRow( string $name, string $label, string $value = null, int $rows, int $columns ) adds/updates multiline text edit control to form
 * @method string setFileRow( string $name, string $label ) adds/updates file upload control to form
 * @method string setCheckboxRow( string $name, string $label, string $value = null ) adds/updates single checkbox control to form
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
	 * @var array[array]
	 */

	protected $rows = array();

	/**
	 * cached template used for rendering single row of form described by setRow()
	 *
	 * @var string
	 */

	protected static $rowTemplate = null;

	/**
	 * class name(s) of form
	 *
	 * @var string
	 */

	protected $class = null;

	/**
	 * set of hidden variables
	 *
	 * @var array
	 */

	protected $hidden = array();

	/**
	 * Describes desired sorting order of named rows to apply on rendering form.
	 *
	 * @var array
	 */

	protected $sortingOrder = null;

	/**
	 * Marks whether including special request ID in rendering form for
	 * protecting against XSRF attacks.
	 *
	 * @var bool
	 */

	protected $enableXsrfMode = true;



	public function __construct( $name, $class = null )
	{
		$this->rename( $name );

		if ( $class !== null )
			$this->class = trim( preg_replace( '/\s+/', ' ', $class ) );
	}

	public function __call( $method, $arguments )
	{
		/**
		 * implement convenience methods for describing forms
		 *
		 * @example
		 *  $form->setTexteditRow( 'username', 'Your Login' );
		 *  $form->setTextAreaRow( 'message', 'Your Message', 'Type your message here ...' );
		 *  $form->setPASSWoRDRow( 'token', 'Your Password' );
		 *
		 *  $form->setButtonRow( 'Submit', 'Cancel' );
		 *
		 *  $form->setSelectorRow( 'gender', 'Your Gender', array( 'm' => 'male', 'f' => 'female' ), 'm' );
		 *
		 */

		if ( ( substr( $method, 0, 3 ) === 'set' ) &&
		     ( substr( $method, -3 ) === 'Row' ) )
		{
			// normalize segment in method name to match markup-template
			$type = strtolower( substr( $method, 3, -3 ) );

			// get arguments depending on selected template
			switch ( $type )
			{
				case 'selector' :
					list( $name, $label, $options, $value ) = array_splice( $arguments, 0, 4 );
					break;
				case 'textarea' :
					list( $name, $label, $value, $rows, $columns ) = array_splice( $arguments, 0, 5 );
					break;
				default :
					list( $name, $label, $value ) = array_splice( $arguments, 0, 3 );
			}

			// auto-integrate available input
			if ( $type !== 'button' )
				$value = input::vget( $name, $value );

			// recompile arguments to use in call for markup-template
			switch ( $type )
			{
				case 'selector' :
					$args = array( $name, $options, $value );
					break;
				case 'button' :
					$args = array( $name, $value, $label );
					$label = null;
					break;
				case 'file' :
					$type = 'upload';
				case 'upload' :
					$args = array( $name, $label );
					break;
				case 'password' :
					$args = array( $name );
					break;
				case 'textarea' :
					$args = array( $name, $value, '', $rows, $columns );
					break;
				case 'static' :
					$type = 'inline';
					$args = array( $value );
					break;
				default :
					$args = array( $name, $value );
			}

			// add row to form using markup-template for rendering content
			$template = array( 'de\toxa\txf\markup', $type );
			$code     = call_user_func_array( $template, $args );

			// compile arguments to use on adding row (including extra information optionally given in call to this magic caller)
			array_unshift( $arguments, $code );
			array_unshift( $arguments, $label );
			array_unshift( $arguments, $name );

			call_user_func_array( array( &$this, 'setRow' ), $arguments );
		}
		else
			throw new \BadMethodCallException( sprintf( 'invalid call for method html_form::%s(), choose set*Row() instead', $method ) );


		return $this;
	}

	/**
	 * Conveniently creates instance of html_form or any derived class.
	 *
	 * @param string $name name of form
	 * @param string $class optional class attribute to use in form
	 * @return html_form created instance
	 */

	public static function create( $name, $class = null )
	{
		return new static( $name, $class );
	}

	/**
	 * Name of ID variable used to prevent XSRF attacks.
	 *
	 * @return string
	 */

	protected function idName()
	{
		return preg_replace( '/^[^a-z]+/i', '', md5( 'formid::' . application::current()->name . '::' . $this->name ) );
	}

	/**
	 * Renames current form.
	 *
	 * @note Adjusting name of a form may have an impact on derived information
	 *       such as internal IDs and thus may affect processing of form.
	 *
	 * @param string $newName new name of form to assign
	 * @return \de\toxa\txf\html_form
	 * @throws \InvalidArgumentException
	 */

	public function rename( $newName )
	{
		$name = trim( $newName );
		if ( !$name )
			throw new \InvalidArgumentException( 'missing valid form name' );

		$this->name = $name;

		return $this;
	}

	/**
	 * Value of ID variable used to prevent XSRF attacks.
	 *
	 * @return string
	 */

	protected function idValue()
	{
		return preg_replace( '/[^a-z0-9_]/i', '', base64_encode( gzcompress( sha1( $this->name . '|' . application::current()->name . '|' . $_SERVER['REMOTE_ADDR'] . '|' . session_id() ), 9 ) ) );
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
	 * @return html_form current form instance
	 */

	public function sendToUrl( $processorUrl )
	{
		if ( trim( $processorUrl ) )
			$this->processorUrl = trim( $processorUrl );
		else
			$this->processorUrl = null;

		return $this;
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
	 * @throws \InvalidArgumentException on providing invalid maximum file size
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
	 * Sets named value to be passed with form hiddenly.
	 *
	 * @throws \InvalidArgumentException on providing invalid name
	 * @param string $name name of value to pass
	 * @param mixed $value value to pass
	 * @return html_form
	 */

	public function setHidden( $name, $value )
	{
		if ( !preg_match( '/^[a-z_]\S+/i', $name ) )
			throw new \InvalidArgumentException( 'invalid variable name' );

		$name = trim( $name );

		if ( $value !== null )
			$this->hidden[$name] = $value;
		else
			unset( $this->hidden[$name] );

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
	 * @throws \InvalidArgumentException
	 */

	public function setRow( $name, $label = null, $htmlCode = null, $mandatory = null, $hint = null, $error = null, $class = null )
	{
		$name = trim( $name );
		if ( $name === '' )
			throw new \InvalidArgumentException( 'missing row name' );


		// select existing row or append new row if selected one isn't found
		if ( !array_key_exists( $name, $this->rows ) )
			$this->rows[$name] = array( 'name' => $name );

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

	public function setRowIsMandatory( $name, $blnIsMandatory = true )
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
	 *
	 * @param $name
	 * @return $this
	 */
	public function deleteRow( $name )
	{
		if ( array_key_exists( $name, $this->rows ) )
			unset( $this->rows[$name] );

		return $this;
	}

	/**
	 * Detects whether there is any row with error set or not.
	 *
	 * @return boolean true if at least row has an error message, false otherwise
	 */

	public function hasAnyRowError()
	{
		foreach ( $this->rows as $properties )
			if ( @$properties['error'] )
				return true;

		return false;
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

	public function processInput() {}

	/**
	 * Provides set of row names requesting special sorting order to apply on
	 * rows when rendering form.
	 *
	 * @param array $sortingOrder
	 * @throws \InvalidArgumentException
	 */

	public function setSortingOrder( $sortingOrder )
	{
		if ( !is_array( $sortingOrder ) )
			throw new \InvalidArgumentException( 'invalid sorting order definition' );

		$this->sortingOrder = $sortingOrder;
	}

	/**
	 * Selects whether form is going to include special request ID for protecting
	 * against XSRF attacks.
	 *
	 * @param bool $enable
	 * @return $this
	 */

	public function enableXsrfMode( $enable = true )
	{
		$this->enableXsrfMode = !!$enable;

		return $this;
	}

	/**
	 * Renders HTML code of form.
	 *
	 * @return string generated HTML code
	 */

	public function getCode()
	{
		$method = $this->usePost ? 'post' : 'get';
		$action = is_null( $this->processorUrl ) ? application::current()->selfUrl( $this->usePost ? array() : false ) : $this->processorUrl;

		$mime = $this->maxFileSize > 0 ? ' enctype="multipart/form-data"' : '';
		$size = $this->maxFileSize > 0 ? '<input type="hidden" name="MAX_FILE_SIZE" value="' . $this->maxFileSize . "\"/>\n\t" : '';

		$name = html::idname( $this->name );

		$idName = $this->idName();
		$idValue = $this->idValue();


		// compile code of form's rows
		$template = self::getRowTemplate();

		$rows = array_filter( $this->rows, function( $row ) { return !!count( $row ); } );

		if ( $this->sortingOrder )
			data::rearrangeArray( $rows, $this->sortingOrder, true );

		$rows = array_map( function( $row ) use ( $template )
		{
			$label = view::wrapNotEmpty( @$row['label'], '', '' );
			$code  = view::wrapNotEmpty( @$row['htmlCode'], '', '' );
			$hint  = view::wrapNotEmpty( @$row['hint'], '<span class="hint">', "</span>\n" );
			$error = view::wrapNotEmpty( @$row['error'], '<span class="error">', "</span>\n" );

			$mandatory = @$row['mandatory'] ? config::get( 'html.form.mandatory', '<span class="mandatory">*</span>' ) : '';

			if ( trim( $label ) !== '' )
				$label = sprintf( config::get( 'html.form.label', '%s%s:' ), $label, $mandatory );

			return sprintf( $template, trim( 'form-row-name-' . @$row['name'] . ' ' . @$row['class'] ), $label, $code, $error, $hint );
		}, $rows );

		// embed compiled rows in form's custom content
		$code = str_replace( '%%%%ROWS_STACK%%%%', implode( '', $rows ), $this->code );


		$hidden = $this->enableXsrfMode ? "<input type=\"hidden\" name=\"$idName\" value=\"$idValue\"/>" : '';

		foreach ( $this->hidden as $key => $value )
			if ( is_array( $value ) )
			{
				foreach ( $value as $subName => $subValue )
					$hidden .= '<input type="hidden" name="' . html::inAttribute( $key ) . '[' . html::inAttribute( $subName ) . ']" value="' . html::inAttribute( $subValue ) . '"/>';
			}
			else if ( $value !== null )
				$hidden .= '<input type="hidden" name="' . html::inAttribute( $key ) . '" value="' . html::inAttribute( $value ) . '"/>';

		$class = ( $this->class !== null ) ? ' class="' . html::inAttribute( $this->class ) . '"' : '';

		return <<<EOT
<form action="$action" method="$method"$mime id="$name"$class>
	$size$hidden
$code
</form>
EOT;
	}

	public function __toString()
	{
		return $this->getCode();
	}
}


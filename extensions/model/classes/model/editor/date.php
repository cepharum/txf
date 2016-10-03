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

namespace de\toxa\txf\model;

use \de\toxa\txf\html_form;

class model_editor_date extends model_editor_abstract
{
	protected $notBefore = null;
	protected $notAfter = null;

	protected $storageFormat = 'Y-m-d';
	protected $staticFormat = 'Y-m-d';
	protected $editorFormat = 'Y-m-d';

	protected static $fallbackParserFormats = array(
		'Y-m-d H:i:s',
		'Y-m-d',
	);

	public function __construct( $staticFormat = 'Y-m-d',  $editorFormat = null, $storageFormat = null )
	{
		$this->staticFormat  = $staticFormat;
		$this->editorFormat  = $editorFormat !== null ? $editorFormat : $staticFormat;
		$this->storageFormat = $storageFormat !== null ? $storageFormat : $editorFormat;
	}

	public static function create( $staticFormat = 'Y-m-d',  $editorFormat = null, $storageFormat = null )
	{
		return new static( $staticFormat, $editorFormat, $storageFormat );
	}

	protected function parseInputToDatetime( $input )
	{
		if ( preg_match( '/^0+$/', preg_replace( '/\D/', '', $input ) ) )
			// input consists of zeroes, only -> consider some unset date
			return null;

		$parsed = \DateTime::createFromFormat( $this->editorFormat, trim( $input ) );
		if ( !$parsed )
			foreach ( static::$fallbackParserFormats as $format )
			{
				$parsed = \DateTime::createFromFormat( $format, trim( $input ) );
				if ( $parsed )
					break;
			}

		return $parsed;
	}

	protected function parseStorageToDatetime( $stored )
	{
		if ( preg_match( '/^0+$/', preg_replace( '/\D/', '', $stored ) ) )
			// input consists of zeroes, only -> consider some unset date
			return null;

		$parsed = \DateTime::createFromFormat( $this->storageFormat, trim( $stored ) );
		if ( !$parsed )
			foreach ( static::$fallbackParserFormats as $format )
			{
				$parsed = \DateTime::createFromFormat( $format, trim( $stored ) );
				if ( $parsed )
					break;
			}

		return $parsed;
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		if ( $this->isReadOnly )
			return null;

		$parsed = $this->parseInputToDatetime( $input );

		return $parsed ? $parsed->format( $this->storageFormat ) : null;
	}

	public function validate( $value, $property, model_editor $editor )
	{
		parent::validate( $value, $property, $editor );

		if ( $value !== null )
		{
			$ts = $this->parseStorageToDatetime( $value );

			if ( $this->notBefore instanceof \DateTime && $ts < $this->notBefore )
				throw new \InvalidArgumentException( _L('Selected date is out of range.') );

			if ( $this->notAfter instanceof \DateTime && $ts > $this->notAfter )
				throw new \InvalidArgumentException( _L('Selected date is out of range.') );
		}

		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		if ( $this->isReadOnly )
			return $this->renderStatic( $form, $name, $input, $label, $editor, $field );

		$ts = $this->parseStorageToDatetime( $input );

		$classes = array( $this->class, 'date', preg_replace( '/\W/', '', $this->editorFormat ) );
		$classes = implode( ' ', array_filter( $classes ) );

		$form->setTexteditRow( $name, $label, $ts ? $ts->format( $this->editorFormat ) : '', $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$value = $this->formatValue( $name, $input, $editor, $field );

		$classes = array( $this->class, 'date', preg_replace( '/\W/', '', $this->staticFormat ) );
		$classes = implode( ' ', array_filter( $classes ) );

		$form->setRow( $name, $label, markup::inline( $value, 'static' ), $this->isMandatory, null, null, $classes );

		return $this;
	}

	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field )
	{
		$ts = $this->parseStorageToDatetime( $value );

		return $ts ? $ts->format( $this->staticFormat ) : null;
	}

	public function notBefore( \DateTime $timestamp )
	{
		$this->notBefore = $timestamp;

		return $this;
	}

	public function notAfter( \DateTime $timestamp )
	{
		$this->notAfter = $timestamp;

		return $this;
	}
}

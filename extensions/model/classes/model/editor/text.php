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
use \de\toxa\txf\markup;

/**
 * Implements model editor element providing single line text input.
 *
 * @author Thomas Urban <thomas.urban@cepharum.de>
 * @package de\toxa\txf
 */

class model_editor_text extends model_editor_abstract
{
	protected $minLength = 0;
	protected $maxLength = 0;
	protected $pattern = false;
	protected $limitWithoutHtml = false;
	protected $collapseWhitespace = false;
	protected $trim = false;


	public function normalize( $input, $property, model_editor $editor )
	{
		$input = parent::normalize( $input, $property, $editor );

		if ( $input !== null ) {
			if ( $this->collapseWhitespace )
				$input = preg_replace( '/\s+/', ' ', $input );

			if ( $this->trim )
				$input = trim( $input );
		}

		return $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $input === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('This information is required.') );
		}
		else
		{
			$text = $this->limitWithoutHtml ? preg_replace( '/\s+/', '', strip_tags( $input ) ) : $input;

			if ( $this->minLength > 0 && mb_strlen( $text ) < $this->minLength )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('Your input is too short.') );

			if ( $this->maxLength > 0 && mb_strlen( $text ) > $this->maxLength )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('Your input is too long.') );

			if ( $this->pattern && $input !== null ) {
				if ( is_array( $this->pattern ) )
					$match = preg_match( $this->pattern[0], $this->pattern[1] ? preg_replace( '/\s+/', '', $input ) : $input );
				else
					$match = preg_match( $this->pattern, $input );

				if ( !$match )
					throw new \InvalidArgumentException( \de\toxa\txf\_L('Your input is invalid.') );
			}

			if ( preg_match( '#<(script|object|iframe|style|link)[\s/>]#i', $input ) )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('This input contains invalid HTML code.') );
		}

		return true;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'text' ) ) );

		$form->setTexteditRow( $name, $label, $input, $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		$value = $this->formatValue( $name, $input, $editor, $field );

		$classes = implode( ' ', array_filter( array( $this->class, 'text' ) ) );

		$form->setRow( $name, $label, markup::inline( $value, 'static' ), $this->isMandatory, null, null, $classes );

		return $this;
	}

	public function formatValue( $name, $input, model_editor $editor, model_editor_field $field )
	{
		return $input;
	}

	/**
	 * Request to apply any limit in count of non-whitespace characters to ignore
	 * embedded HTML code or not.
	 *
	 * @param bool $stripHtmlTags true to strip all HTML tags prior to validating count of non-whitespace characters
	 * @return $this
	 */

	public function limitWithoutHtml( $stripHtmlTags = true )
	{
		$this->limitWithoutHtml = !!$stripHtmlTags;

		return $this;
	}

	/**
	 * Retrieves internal mark on whether stripping HTML prior to counting
	 * characters on validating or not.
	 *
	 * @return bool
	 */

	public function isLimitingWithoutHtml()
	{
		return $this->limitWithoutHtml;
	}

	/**
	 * Request to require minimum number of non-whitespace characters in input.
	 *
	 * @param integer $length minimum count of non-whitespace characters
	 * @return $this
	 */

	public function minimum( $length )
	{
		$this->minLength = intval( $length );

		return $this;
	}

	/**
	 * Request to require maximum number of non-whitespace characters in input.
	 *
	 * @param integer $length maximum count of non-whitespace characters
	 * @return $this
	 */

	public function maximum( $length )
	{
		$this->maxLength = intval( $length );

		return $this;
	}

	/**
	 * Defines PCRE pattern for validating input in detail.
	 *
	 * @param string $pattern
	 * @param bool $excludingSpace true to apply pattern after temporarily removing any whitespace
	 * @return $this
	 */

	public function pattern( $pattern, $excludingSpace = false )
	{
		$this->pattern = array( trim( $pattern ), $excludingSpace );

		return $this;
	}

	/**
	 * Enables or disables trimming of input on normalizing.
	 *
	 * @param bool $trim true to enable trimming of input, false to disable
	 * @return $this
	 */

	public function trim( $trim = true )
	{
		$this->trim = !!$trim;

		return $this;
	}

	/**
	 * Detects if input is trimmed on normalizing.
	 *
	 * @return bool
	 */

	public function isTrimming()
	{
		return $this->trim;
	}

	/**
	 * Enables or disabled collapsing of sequences of whitespace on normalizing
	 * input.
	 *
	 * @param bool $collapsing true to replace sequences of whitespace by single space
	 * @return $this
	 */

	public function collapseWhitespace( $collapsing = true )
	{
		$this->collapseWhitespace = !!$collapsing;

		return $this;
	}

	/**
	 * Detects if text element is collapsing sequences of whitespace on
	 * normalizing input.
	 *
	 * @return bool
	 */

	public function isCollapsingWhitespace()
	{
		return $this->collapseWhitespace;
	}
}

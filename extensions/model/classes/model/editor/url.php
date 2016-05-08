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

use \de\toxa\txf\markup;
use \de\toxa\txf\url;

class model_editor_url extends model_editor_text
{
	protected $absolute = false;

	public function validate( $input, $property, model_editor $editor )
	{
		parent::validate( $input, $property, $editor );

		if ( $input != '' )
		{
			if ( !url::isFile( $input ) )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('This is not a valid URL.') );

			if ( $this->absolute && url::isRelative( $input ) )
				throw new \InvalidArgumentException( \de\toxa\txf\_L('This URL must be absolute. Include scheme e.g. http://www.example.com/!') );
		}

		return true;
	}

	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field )
	{
		return $value ? markup::link( $value, $value ) : null;
	}

	public function forceAbsolute( $force = true )
	{
		$this->absolute = !!$force;

		return $this;
	}
}

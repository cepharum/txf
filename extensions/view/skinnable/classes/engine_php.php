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

namespace de\toxa\txf\view\skinnable;


use de\toxa\txf\variable_space;


/**
 * Implements rendering engine utilizing native PHP support for high performance
 * template rendering.
 *
 * While this engine provide little security due to enabling use of arbitrary
 * PHP code in templates it's fast, easy to use and simple to implement. A
 * future extension might use available tokenizer to validate template scripts
 * in a preflight e.g. to prevent execution of templates including use of
 * selected methods or superglobal variables.
 *
 * A major caveat is its influence on PHP runtime due to more fatal errors
 * result in exceptions usually breaking whole runtime of requested script.
 *
 * @author Thomas Urban
 *
 */

class engine_php extends engine
{
	/**
	 * manager instance used to locate a selected template file
	 *
	 * @var templateLocator
	 */

	protected $templateLocator;


	public function __construct()
	{
		$this->templateLocator = new template_locator( $this );
	}

	public function getTemplate( $templateName )
	{
		return $this->templateLocator->find( $templateName );
	}

	public function hasTemplate( $templateName )
	{
		try
		{
			$this->templateLocator->find( $templateName );

			return true;
		}
		catch ( \UnexpectedValueException $e )
		{
			return false;
		}
	}

	public function render( $templateName, variable_space $data )
	{
		$templateFile = $this->getTemplate( $templateName );

		extract( $data->asArray() );
		$LOCALDATA = $data;

		ob_start();
		try
		{
			// @todo evaluate performance improvements by caching used templates in closures on frequent use
			include( $templateFile );
			$code = ob_get_clean();
		}
		catch ( \Throwable $e )
		{
			ob_end_clean();
			$code = manager::simpleRenderException( $e );
		}

		return $code;
	}
}


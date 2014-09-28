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


use de\toxa\txf\txf;
use de\toxa\txf\data;
use de\toxa\txf\path;


class template_locator
{
	/**
	 * engine instance using this locator
	 *
	 * @var engine
	 */

	protected $contextEngine;



	public function __construct( engine $context )
	{
		$this->contextEngine = $context;
	}


	public function find( $templateName, $currentSkin = null )
	{
		assert( '\de\toxa\txf\txf::current()' );

		$folders = array(
						TXF_APPLICATION_PATH . '/skins/',
						dirname( dirname( __FILE__ ) ) . '/skins/' . TXF_APPLICATION,
						dirname( dirname( __FILE__ ) ) . '/skins/default',
						);

		$skins = array( 'default' );

		if ( $currentSkin !== null )
			$currentSkin = data::isKeyword( $currentSkin );

		if ( $currentSkin )
			if ( $currentSkin != 'default' )
				array_unshift( $skins, $currentSkin );

		foreach ( $folders as $folder )
			foreach ( $skins as $skin )
			{
				$pathname = path::glue( $folder, $skin, $templateName . '.phpt' );
				if ( is_file( $pathname ) )
					return $pathname;
			}

		throw new \UnexpectedValueException( sprintf( 'template not found: %s (%s)', $templateName, $currentSkin ) );
	}
}


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
 * Processes redirected requests.
 *
 * Requests may be redirected here either by using rewrite capabilities of web
 * server or using requests including this script such as
 *
 *    <your-site>/run.php/appname/script.php
 *
 * which is then processing request for script "script.php" of application
 * "appname".
 *
 * @author Thomas Urban
 *
 */

try
{
	include( 'rewritten.php' );

	// get application actually requested
	$application = txf::getContext()->application;

	// get script of application actually requested
	$script = path::glue( $application->pathname, $application->script );

	// change to that folder for supporting homogenic use of relative pathnames
	chdir( dirname( $script ) );

	// include selected script
	include_once( $script );

	// due to disabled shutdown handler we are required to call related handler manually
	view::current()->onShutdown();
}
catch ( http_exception $e )
{
	header( $e->getResponse() );

	view::variable( 'exception', $e );
	view::addBodyClass( 'exception' );
	view::addBodyClass( 'http-exception' );

	$data = variable_space::create( 'reason', $e );

	try {
		view::main( view::engine()->render( 'error/' . $e->getCode(), $data ) );
	} catch ( \UnexpectedValueException $dummy ) {
		view::main( view::engine()->render( 'error/generic', $data ) );
	}

	view::variable( 'title', $e->getStatus() );
	view::current()->onShutdown();
}

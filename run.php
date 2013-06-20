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
	include( 'txf/rewritten.php' );

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

	$html = $e->asHtml();

	echo <<<EOT
<!DOCTYPE html!>
<html>
<head></head>
<body>
$html
<hr />
TXF
</body>
</html>
EOT;
}

<?php


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


namespace de\toxa\txf;


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

<?php


namespace de\toxa\txf;


include_once( dirname( __FILE__ ) . '/classes/txf.php' );

if ( !txf::hasCurrent() )
{
	txf::setContextMode( txf::CTXMODE_NORMAL );
	txf::select( new txf() );

	view::init();
}
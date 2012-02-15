<?php


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
						TXF_APPLICATION_PATH,
						dirname( dirname( __FILE__ ) ) . '/skins/' . TXF_APPLICATION,
						dirname( dirname( __FILE__ ) ) . '/skins/default',
						);

		$skins = array( 'default' );

		$currentSkin = data::isKeyword( $currentSkin );
		if ( $currentSkin )
			if ( $currentSkin != 'default' )
				array_unshift( $skins, $currentSkin );

		foreach ( $folders as $folder )
		{
			if ( strpos( $folder, '%A' ) !== false )
				$temp = $apps;

			foreach ( $skins as $skin )
			{
				$pathname = path::glue( $folder, $skin, $templateName . '.phpt' );
				if ( is_file( $pathname ) )
					return $pathname;
			}
		}

		throw new \UnexpectedValueException( 'template not found' );
	}
}


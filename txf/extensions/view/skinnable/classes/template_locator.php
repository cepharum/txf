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


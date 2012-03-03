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
 * Implementation of gettext-based translations support
 *
 * This class is considered to be available by redirection, thus class is called
 * "de\toxa\txf\translation" here. See txf::redirectClass() for more.
 *
 */


class translation extends singleton
{

	/**
	 * ID of currently selected locale
	 *
	 * @var string
	 */

	protected $language;

	/**
	 * gettext domain to use by current instance
	 *
	 * @var string
	 */
	
	protected $domain;

	

	public function onLoad()
	{
		$this->language = config::get( 'locale.language', 'en' );
		$this->domain   = config::get( 'locale.domain', TXF_APPLICATION );

		if ( \extension_loaded( 'gettext' ) )
		{
			$path = config::get( 'locale.path', path::glue( TXF_APPLICATION_PATH, 'locale' ) );

			bindtextdomain( $this->domain, $path );
		}

		setlocale( LC_ALL, $this->language );
	}

	public static function get( $singular, $plural, $count )
	{
		$count = abs( $count );

		if ( \extension_loaded( 'gettext' ) )
			return dngettext( $singular, $plural, $count );
		else
			return ( $count == 1 ) ? $singular : $plural;
	}
}


translation::init();

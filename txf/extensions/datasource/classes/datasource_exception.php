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


namespace de\toxa\txf\datasource;


class datasource_exception extends \Exception
{
	public function __construct( $linkOrStatement )
	{
		if ( $linkOrStatement instanceof connection || $linkOrStatement instanceof statement )
		{
			$command = $linkOrStatement->command;

			if ( $command !== null )
				parent::__construct( sprintf( "%s\n(on %s)", $linkOrStatement->errorText(), $linkOrStatement->command ) );
			else
				parent::__construct( $linkOrStatement->errorText() );
		}
		else
			parent::__construct( 'unknown failure' );
	}
}

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
 * Implementation of input source accessing current configuration for defaults.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 */


class input_source_config implements input_source
{
	public function isVolatile()
	{
		return false;
	}

	public function hasValue( $name )
	{
		return config::has( 'input.default.' . $name );
	}

	public function getValue( $name )
	{
		return config::get( 'input.default.' . $name );
	}

	public function persistValue( $name, $value )
	{
	}

	public function dropValue( $name )
	{
	}
}


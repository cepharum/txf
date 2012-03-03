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
 * Wraps text in proper markup code using templates.
 *
 * This class is designed to use templates for applying markup on page elements
 * such as headline and paragraphs. In addition to improving separation of
 * content and design this is beneficially introducing low-level customizations
 * of rendered code.
 *
 * The class is using magic method __call() to select a template's name by
 * invoking method, so using it becomes as simple as this
 *
 *   markup::h1( 'Major Headline' );
 *
 * for marking up provided text as a first-level headline.
 *
 * Supported templates are located in subfolder markup of your current skin or
 * its fallbacks. In case of given example it's "markup/h1".
 *
 * @author Thomas Urban
 */


class markup
{
	public static function __callStatic( $method, $arguments )
	{
		$oblevel = ob_get_level();

		try
		{
			// @todo consider selecting engine depending on current configuration instead of using current view's one
			return view::current()->getEngine()->render( 'markup/' . $method, variable_space::create( 'arguments', $arguments, 'text', array_shift( $arguments ) ) );
		}
		catch ( \Exception $e )
		{
			while ( $oblevel > ob_get_level() )
				ob_end_clean();

			throw $e;
		}
	}
}


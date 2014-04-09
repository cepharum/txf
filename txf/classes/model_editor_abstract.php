<?php
/**
 * Copyright (c) 2013-2014, cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban <thomas.urban@cepharum.de>
 * @project: Lebenswissen
 */

namespace de\toxa\txf;


abstract class model_editor_abstract implements model_editor_element
{
	protected $isMandatory = false;
	protected $hint = null;
	protected $class = null;


	public static function create()
	{
		return new static();
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		$input = trim( $input );

		return ( $input === '' ) ? null : $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $input === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException( _L('This information is required.') );
		}
	}

	public function mandatory( $mandatory = true )
	{
		$this->isMandatory = !!$mandatory;

		return $this;
	}

	public function isMandatory()
	{
		return $this->isMandatory;
	}

	public function setHint( $hintText )
	{
		$this->hint = trim( $hintText );
		if ( !$this->hint )
			$this->hint = null;

		return $this;
	}

	public function setClass( $className )
	{
		$this->class = trim( $className );
		if ( !$this->class )
			$this->class = null;

		return $this;
	}
}

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

	/**
	 * @var model_editor
	 */

	private $editor = null;

	/**
	 * value to use if loading from edited item isn't available
	 *
	 * @var *
	 */

	private $default = null;



	public static function create()
	{
		return new static();
	}

	/**
	 * Selects editor this element belongs to.
	 *
	 * This method is invoked by editor element is attached to.
	 *
	 * @param model_editor $editor
	 * @return $this
	 */

	public function setEditor( model_editor $editor )
	{
		$this->editor = $editor;

		return $this;
	}

	/**
	 * Retrieves optionally selected editor.
	 *
	 * @return model_editor
	 */

	public function getEditor()
	{
		return $this->editor;
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
				throw new \InvalidArgumentException( _Ltxl('This information is required.') );
		}

		return true;
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

	/**
	 * Declares default value to use if loading from edited item isn't
	 * available.
	 *
	 * @param * $defaultValue some value to use by default
	 * @return $this
	 */

	public function declareDefaultValue( $defaultValue )
	{
		$this->__default = $defaultValue;

		return $this;
	}

	/**
	 * Gets called on selecting item in editor.
	 *
	 * This callback is provided mainly to inform editor elements on changing
	 * item in editor.
	 *
	 * @param model_editor $editor
	 * @param model $item
	 */

	public function onSelectingItem( model_editor $editor, model $item ) {
	}

	/**
	 * Gets called on trying to load value of selected property into editor.
	 *
	 * The method must not access provided item to get value there. It's designed
	 * to provide value in special cases when edited value isn't available using
	 * item's property getter (e.g. on fetching relations).
	 *
	 * @param model_editor $editor editor trying to load value of selected property
	 * @param model|null $item item in editor, null if editor is used to create new item on storing
	 * @param string $propertyName name of property to read value from
	 * @return mixed|null some value loaded, null if element doesn't provide special value (e.g. to load it from item)
	 */

	public function onLoading( model_editor $editor, model $item = null, $propertyName ) {
		if ( $item !== null ) {
			// has item ... provide null here to have editor load property of item
			return null;
		}

		// use locally declared default value
		return $this->__default;
	}

	/**
	 * Gets called before storing properties of model item in editor instance.
	 *
	 * This method is working like a filter in that it might adjust properties
	 * in $allData to be stored and return that filtered set to be used by
	 * editor instead of provided one.
	 *
	 * By intention implementations should not adjust existing values related
	 * to different editor element, but remove its own values on demand or add
	 * some more values qualifying current element's data to be stored.
	 *
	 * @note This method is called right before saving properties to edited
	 *       item. It isn't designed to store provided values itself!
	 * @note This method is invoked on every field of editor, but don't rely on
	 *       a particular order of invocations.
	 * @note beforeStoring() is called as part of a transaction that might be
	 *       rolled back by throwing exception.
	 *
	 * @param model_editor $editor editor instance going to store values of item
	 * @param model $item|null item to be updated, null on creating item
	 * @param array $itemProperties values of properties going to be stored by editor
	 * @return array filtered/extended set of values
	 */

	public function beforeStoring( model_editor $editor, model $item = null, $itemProperties )
	{
		// keep properties as-is by default
		return $itemProperties;
	}

	/**
	 * Gets called after storing properties of model item in editor instance.
	 *
	 * This method may be used to post-process item-related data in data source
	 * requiring item to exist in data source. By intention it is called like a
	 * filter returning provided model item or some replacement to use further
	 * on, finally "replacing"  item associated with editor.
	 *
	 * @note This method is invoked on every field of editor, but don't rely on
	 *       a particular order of invocations.
	 * @note afterStoring() is called on an editor element after having called
	 *       beforeStoring() on it, only. Thus combination of beforeStoring()
	 *       and afterStoring() might share information using editor element
	 *       instance.
	 * @note afterStoring() is called as part of a transaction that might be
	 *       rolled back by throwing exception.
	 *
	 * @param model_editor $editor editor instance having stored values of item
	 * @param model $item item created/updated by storing values before
	 * @param array $itemProperties properties and their values stored before
	 * @return model provided $item or some replacement to use in editor instead
	 */

	public function afterStoring( model_editor $editor, model $item, $itemProperties )
	{
		// don't replace item in editor by default
		return $item;
	}

	/**
	 * Gets called prior to deleting provided item.
	 *
	 * Deletion may be prevented by throwing exception.
	 *
	 * @note onDeleting() is called as part of a transaction that might be
	 *       rolled back by throwing exception.
	 *
	 * @param model_editor $editor editor instance requested to delete item
	 * @param model $item item to be deleted
	 */

	public function onDeleting( model_editor $editor, model $item )
	{
		// don't act on deleting item by default
	}
}

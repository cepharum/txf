<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf\model;


abstract class model_editor_abstract implements model_editor_element
{
	protected $isMandatory = false;
	protected $isReadOnly = false;
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

	private $__default = null;



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

		return ( $this->isReadOnly || $input === '' ) ? null : $input;
	}

	public function validate( $input, $property, model_editor $editor )
	{
		if ( $input === null )
		{
			if ( $this->isMandatory )
				throw new \InvalidArgumentException(  \de\toxa\txf\_L('This information is required.') );
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

	public function readOnly( $readOnly = true )
	{
		$this->isReadOnly = !!$readOnly;

		return $this;
	}

	public function isReadOnly()
	{
		return $this->isReadOnly;
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
	 * Provides opportunity to adjust some provided value according to element
	 * on handling values.
	 *
	 * Certain types of editor elements (such as `related`) might require to use
	 * internal-only values to be mapped from/to actual values. Such elements
	 * require fixed values to fake actually expected input by means of such
	 * internal-only value. This method is extracting mapping of actual value
	 * into element's internal-only mapping so model_editor's fixProperty() can
	 * be used properly.
	 *
	 * @param * $actualValue some actual value of property managed by current element
	 * @return * mapped internal-only value related to given actual one, might be given value in most cases though
	 */

	public function getFixableValue( $actualValue )
	{
		return $actualValue;
	}

	/**
	 * Gets called on selecting item in editor.
	 *
	 * This callback is provided mainly to inform editor elements on changing
	 * item in editor.
	 *
	 * @param model_editor $editor
	 * @param model $item
	 * @param model_editor_field $field currently processed field
	 */

	public function onSelectingItem( model_editor $editor, model $item, model_editor_field $field ) {
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
	 * @param model_editor_field $field currently processed field
	 * @return mixed|null some value loaded, null if element doesn't provide special value (e.g. to load it from item)
	 */

	public function onLoading( model_editor $editor, model $item = null, $propertyName, model_editor_field $field ) {
		if ( $item !== null ) {
			// has item ... provide null here to have editor load property of item
			return null;
		}

		// use locally declared default value
		return $this->__default;
	}

	/**
	 * Provides option to transform value of regular property of item once after
	 * having fetch value from datasource.
	 *
	 * This method is NOT invoked on processing input on related field or on
	 * compiling related value as provided by onLoading() above.
	 *
	 * @param model_editor $editor editor trying to load value of selected property
	 * @param model|null $item item in editor, null if editor is used to create new item on storing
	 * @param string $propertyName name of property value originates from
	 * @param mixed $loadedValue value loaded from datasource
	 * @return mixed prepared value
	 */
	public function afterLoading( model_editor $editor, model $item = null, $propertyName, $loadedValue ) {
		// keep loaded value as-is by default
		return $loadedValue;
	}

	/**
	 * Gets called before invoking optionally given custom validator for checking
	 * properties of model item in editor instance.
	 *
	 * This method is working like a filter in that it might adjust properties
	 * in $itemProperties to be stored and return that filtered set to be used
	 * by editor instead of provided one.
	 *
	 * By intention implementations should not adjust existing values related
	 * to different editor element, but remove its own values on demand or add
	 * some more values qualifying current element's data to be validated.
	 *
	 * @note This method is called right before validating properties of edited
	 *       item. It isn't designed to validate provided values itself!
	 * @note This method is invoked on every field of editor, but don't rely on
	 *       a particular order of invocations.
	 * @note beforeValidating() is called as part of a transaction that might be
	 *       rolled back by throwing exception.
	 *
	 * @param model_editor $editor editor instance going to store values of item
	 * @param model $item|null item to be updated, null on creating item
	 * @param array $itemProperties values of properties going to be stored by editor
	 * @param model_editor_field $field currently processed field
	 * @return array filtered/extended set of values
	 */

	public function beforeValidating( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field )
	{
		// keep properties as-is by default
		return $itemProperties;
	}

	/**
	 * Gets called before storing properties of model item in editor instance.
	 *
	 * This method is working like a filter in that it might adjust properties
	 * in $itemProperties to be stored and return that filtered set to be used
	 * by editor instead of provided one.
	 *
	 * By intention implementations should not adjust existing values related
	 * to different editor element, but remove its own values on demand or add
	 * some more values qualifying current element's data to be stored.
	 *
	 * @note This method is called right before permanently saving properties to
	 *       edited item. It isn't designed to store provided values itself!
	 * @note This method is invoked on every field of editor, but don't rely on
	 *       a particular order of invocations.
	 * @note beforeStoring() is called as part of a transaction that might be
	 *       rolled back by throwing exception.
	 *
	 * @param model_editor $editor editor instance going to store values of item
	 * @param model $item|null item to be updated, null on creating item
	 * @param array $itemProperties values of properties going to be stored by editor
	 * @param model_editor_field $field currently processed field
	 * @return array filtered/extended set of values
	 */

	public function beforeStoring( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field )
	{
		if ( $field->isCustom() )
			unset( $itemProperties[$editor->fieldToProperty( $field->name() )] );

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
	 * @param model_editor_field $field currently processed field
	 * @return model provided $item or some replacement to use in editor instead
	 */

	public function afterStoring( model_editor $editor, model $item, $itemProperties, model_editor_field $field )
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
	 * @param model_editor_field $field currently processed field
	 */

	public function onDeleting( model_editor $editor, model $item, model_editor_field $field )
	{
		// don't act on deleting item by default
	}
}

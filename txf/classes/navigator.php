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

namespace de\toxa\txf;


/**
 * Navigator collecting links and actions for navigating in an application.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 *
 */

class navigator implements widget
{
	/**
	 * set of registered navigators
	 *
	 * @var array[navigator]
	 */

	protected static $navigators = array();

	/**
	 * unique name of current navigator
	 *
	 * @var string
	 */

	protected $name;

	/**
	 * items of current navigator
	 *
	 * @var array
	 */

	protected $items = array();

	/**
	 * optional callback invoked to detect currently selected items
	 *
	 * @var callable
	 */

	protected $autoselector;


	/**
	 * @param string $name name of navigator being created
	 */

	protected function __construct( $name )
	{
		$name = static::normalizeName( $name );

		if ( @static::$navigators[$name] )
			throw new \InvalidArgumentException( 'ambigious navigator name' );

		$this->name = $name;

		// initially enable auto-selecting mode using built-in detector
		$this->autoselect( true );
	}

	/**
	 * Normalizes name of navigator/item.
	 *
	 * @throws \InvalidArgumentException on a missing/invalid name
	 *
	 * @param string $name caller-provided name to use
	 * @return string normalized name to use actually
	 */

	protected static function normalizeName( $name )
	{
		$name = trim( $name );

		if ( !preg_match( '/^[a-z_][a-z0-9_-]+$/', $name ) )
			throw new \InvalidArgumentException( 'missing or invalid name' );

		return $name;
	}

	/**
	 * Selects registered navigator or creates it if not registered before.
	 *
	 * @param string $name name of navigator to select/create
	 * @return navigator created/selected navigator instance
	 */

	public static function select( $name )
	{
		$name = static::normalizeName( $name );

		if ( !array_key_exists( $name, static::$navigators ) )
			static::$navigators[$name] = new static( $name );

		return static::$navigators[$name];
	}

	/**
	 * Tests whether selected navigator exists or not.
	 *
	 * @param string $name name of navigator to test
	 * @return boolean true if selected navigator exists, false otherwise
	 */

	public static function exists( $name )
	{
		return array_key_exists( static::normalizeName( $name ), static::$navigators );
	}

	/**
	 * Tests if selected navigator exists and contains any item.
	 *
	 * @param string $name name of navigator to test
	 * @return boolean true if navigator exists and has items, false otherwise
	 */

	public static function hasItems( $name )
	{
		$name = static::normalizeName( $name );

		return static::exists( $name ) ? !!count( static::$navigators[$name]->items ) : false;
	}

	/**
	 * Conveniently wraps method navigator::select() to support method's name
	 * selecting navigator instantly.
	 *
	 * @param string $name name of navigator to select
	 */

	public static function __callStatic( $name, $arguments )
	{
		return static::select( $name );
	}

	/**
	 * Adjusts existing item of current navigator.
	 *
	 * If selected item does not exist, it's appended/inserted accordingly.
	 *
	 * Any item consists of a selection of these properties:
	 *
	 *  label     text for labelling item in navigator
	 *  asset     some asset (image) to illustrate item in navigator
	 *  action    action to perform on activating/clicking item in navigator (e.g. target URL to be opened)
	 *  selected  mark on whether item is selected or not
	 *  sub       subordinated set of items
	 *
	 * In addition every item requires name unique in set of current navigator's
	 * items for selecting the item individually.
	 *
	 * Selecting item isn't proofing for having selected single item, only. Thus
	 * caller needs to ensure to have only one selected item. On using
	 * auto-select this constraint is achieved internally.
	 *
	 * @note Resulting widget contains items with label or asset, only.
	 *
	 * @param string $name name of item in current navigator to adjust/add
	 * @param string|null|false $label label of item to set, null to keep previous one, false to drop previous one
	 * @param string|null|false $action action to associate with item (target URL), null to keep previous one, false to drop previous one
	 * @param string|null|false $asset address of some asset to attach with item, null to keep previous one, false to drop previous one
	 * @param boolean|null $selected true to select item, false to unselect, null to keep previous state
	 * @param navigator|false $sub navigator instance to subordinate to selected item, null to keep current, false to drop previous one
	 * @param string|null $insertBefore name of item to insert or move to current one before, null to append or keep existing at its position
	 * @return navigator current navigator instance for chaining calls
	 */

	public function setItem( $name, $label = null, $action = null, $asset = null, $selected = null, navigator $sub = null, $insertBefore = null )
	{
		$name = static::normalizeName( $name );

		// create compact description of new item to be collected internally
		$newItem = array_filter( compact( 'name', 'label', 'action', 'asset', 'selected', 'sub' ), function( $property ) { return $property !== null; } );

		if ( $selected !== null )
			$this->autoselect( false );


		// support editing existing item
		if ( array_key_exists( $name, array_keys( $this->items ) ) )
		{
			// merge existing item with newly described one
			// - ignore null properties of new item
			// - support false properties in new item dropping existing ones
			$newItem = array_merge( $this->items[$name], $newItem );

			if ( $insertBefore !== null )
				// existing item will be inserted before selected one
				// -> gets moved, thus drop at it's current position
				unset( $this->items[$name] );
		}

		$newItem = array_filter( $newItem, function( $property ) { return $property !== false; } );


		// try to insert item at explicitly selected insertion point
		if ( $insertBefore !== null )
		{
			$offset = array_search( $insertBefore, array_keys( $this->items ) );
			if ( $offset !== false )
			{
				// found valid insertion point
				// -> insert item before selected item
				$this->items = array_merge( array_slice( $this->items, 0, $offset ), array( $name => $newItem ), array_slice( $this->items, $offset ) );

				return $this;
			}
		}


		// by default update/append described new item
		$this->items[$name] = $newItem;

		return $this;
	}

	/**
	 * Removes selected item from current navigator.
	 *
	 * @param string $name name of item to remove
	 * @return navigator current navigator instance for chaining calls
	 */

	public function removeItem( $name )
	{
		$name = static::normalizeName( $name );

		unset( $this->items[$name] );

		return $this;
	}

	/**
	 * Attaches subordinated navigator to selected item of current navigator.
	 *
	 * This method is a convenience wrapper around method setItem().
	 *
	 * @param string $itemName name of item in current navigator to select
	 * @param navigator $sub navigator instance to subordinate to selected item
	 * @return navigator current navigator instance for chaining calls
	 */

	public function attachSub( $itemName, navigator $sub )
	{
		return $this->setItem( $itemName, null, null, null, null, $sub );
	}

	/**
	 * Selects/unselects item of navigator.
	 *
	 * Selecting item this way disables auto-select mode for current navigator.
	 * In addition it's not ensuring to have only one selected item, thus it's
	 * up to caller checking for multiple selected items.
	 *
	 * @param string $itemName name of item in current navigator to select
	 * @param boolean $select if true, item is selected
	 * @return navigator current navigator instance for chaining calls
	 */

	public function selectItem( $itemName, $select = true )
	{
		return $this->setItem( $itemName, null, null, null, !!$select );
	}

	/**
	 * Enables or disables auto-select mode.
	 *
	 * In auto-select mode the widget tries to find any currently selected item
	 * and its containing superordinated navigators for marking them properly.
	 *
	 * Auto-select mode is initially enabled on every navigator and gets
	 * disabled as soon as a single item is selected explicitly.
	 *
	 * Providing true in $enable enables auto-select mode using built-in
	 * detector matching script's URL with an item's action. Optionally $enable
	 * might be function to be called instead of that built-in detector. If
	 * $enable is false auto-select mode gets disabled.
	 *
	 * @param boolean|function $enable see description
	 * @return navigator current navigator instance for chaining calls
	 */

	public function autoselect( $enable = true )
	{
		if ( $enable && !is_callable( $enable ) )
			$enable = function( $item )
			{
				$selected = strtok( $item['action'], '?' );
				$current  = strtok( context::selfURL(), '?' );

				return ( substr( $current, -strlen( $selected ) ) == $selected );
			};

		$this->autoselector = $enable;

		return $this;
	}

	/**
	 * Detects if provided item is selected unless auto-select mode is disabled.
	 *
	 * @param array $item item to test
	 * @return boolean true if item is considered selected, false otherwise
	 */

	protected function detectSelected( $item )
	{
		$cb = $this->autoselector;
		if ( $cb )
			return $cb( $item );

		return false;
	}

	/**
	 * Converts current set of items into multi-dimensional array containing
	 * subordinated navigators' items as ordinary arrays as well.
	 *
	 * Additionally this method tries to detect active/selected items and
	 * threads of navigator.
	 *
	 * @return array set items with subordinated items resolved to arrays
	 */

	protected function qualifyThread( $depth = 1, &$selected )
	{
		$out = array(
					'level' => intval( $depth ),
					'items' => array(),
					);

		foreach ( $this->items as $name => $item )
			if ( @$item['label'] || $item['asset'] )
			{
				// process attached sub navigator
				if ( @$item['sub'] instanceof navigator )
				{
					// resolve that one's items
					$subselected = false;
					$item['sub'] = $item['sub']->qualifyThread( $depth + 1, $subselected );

					// if resolving detected selected sub-item ...
					if ( $subselected  && !$selected )
					{
						// ... transfer related mark to caller
						$selected = true;

						// ... mark containing navigator as active one
						$out['active'] = 'active';
					}
				}
				else
					$item['sub'] = array( 'items' => array() );


				// apply auto-detection of selected item if enabled and on haven't selected item before
				if ( !$selected && $this->detectSelected( $item ) )
				{
					// current item is selected, thus mark
					$item['selected'] = 'selected';

					// and tell any containing navigator about having found selected item
					$selected = true;
				}

				if ( url::isRelative( $item['action'] ) )
					$item['action'] = application::current()->relativePrefix( $item['action'] );


				$out['items'][$name] = $item;
			}

		return $out;
	}

	public function processInput() {}

	/**
	 * Renders code of widget for embedding it in a script's output.
	 *
	 * @return string code of widget
	 */

	public function getCode()
	{
		$selected = false;

		$data = $this->qualifyThread( 1, $selected );

		$data['name'] = $this->name;

		if ( $selected )
			$data['active'] = 'active';

		return view::render( 'widgets/navigator', $data );
	}

	public function __toString()
	{
		return $this->getCode();
	}
}

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
 * Widget implementing common features of browseable lists of data.
 *
 * @author Thomas Urban
 */

class databrowser implements widget
{
	/**
	 * datasource providing browseable data
	 *
	 * @var browseable
	 */

	protected $datasource;

	/**
	 * custom text to show on empty table
	 *
	 * @var string
	 */

	protected $emptyText;

	/**
	 * scope to use on accessing persistent data in session
	 *
	 * @var enum
	 */

	protected $sessionScope;

	/**
	 * name of property providing containing item's unique ID
	 *
	 * @var string
	 */

	protected $idName = 'id';

	/**
	 * set of column definitions
	 *
	 * @var array[array]
	 */

	protected $columns = array();

	/**
	 * instance of class pager used on browsing large datasets
	 *
	 * @var pager
	 */

	protected $pager;

	/**
	 * optional callback invoked on every row for creating related command markup
	 *
	 * @var function|array
	 */

	protected $rowCommander;

	/**
	 * HTML form instance used internally for managing browser input
	 *
	 * @var html_form
	 */

	protected $form = null;

	/**
	 * Mark on whether form in $this->form was provided by caller explicitly or
	 * not.
	 *
	 * @var boolean
	 */

	protected $callerForm = false;

	/**
	 * optional class name of rendered databrowser widget's code
	 *
	 * @var string
	 */

	protected $className;

	/**
	 * mark on whether embedded pager is using volatile input or not
	 *
	 * @var boolean|null
	 */

	protected $volatilePager;



	/**
	 * @param browseable $source datasource providing browseable data
	 * @param string $textOnEmpty custom text to show on empty dataset
	 */

	public function __construct( browseable $source, $textOnEmpty = null, $className = 'databrowser' )
	{
		$this->datasource   = $source;
		$this->sessionScope = session::SCOPE_SCRIPT;
		$this->emptyText    = $textOnEmpty;
		$this->className    = $className;
	}

	/**
	 * Retrieves new databrowser instance for instant use.
	 *
	 * @param browseable $source datasource providing browseable data
	 * @param string $textOnEmpty custom text to show on empty dataset
	 *
	 * @return \de\toxa\txf\databrowser created instance
	 */

	public static function create( browseable $source, $textOnEmpty = null, $className = 'databrowser' )
	{
		return new static( $source, $textOnEmpty, $className );
	}

	/**
	 * Changes current widget to work globally.
	 *
	 * This is required on having same instance of a widget embedded in
	 * multiple scripts of an application.
	 *
	 * @Note This method must be called before processInput() for taking effect.
	 *
	 * @return \de\toxa\txf\databrowser current instance
	 */

	public function makeGlobal()
	{
		$this->sessionScope = session::SCOPE_APPLICATION;

		return $this;
	}

	public function selectIdProperty( $name )
	{
		$this->idName = $name;

		return $this;
	}

	/**
	 * Selects embedded pager's volatility.
	 *
	 * @param string $mode one of "full", "none", "partial" (default)
	 * @return \de\toxa\txf\databrowser current databrowser instance
	 */

	public function setPagerVolatility( $mode )
	{
		switch ( strtolower( trim( $mode ) ) )
		{
			case 'partial' :
			default :
				$this->volatilePager = null;
				break;
			case 'full' :
				$this->volatilePager = true;
				break;
			case 'none' :
				$this->volatilePager = false;
				break;
		}

		return $this;
	}

	/**
	 * Disables use of internally managed form wrapping databrowser.
	 *
	 * By default databrowser widget is embedded in a separate form due to
	 * using buttons and checkboxes for processing commands etc. If you are
	 * intending to have your own form containing this databrowser next to
	 * further elements you might want to call this method to get raw code of
	 * databrowser.
	 *
	 * @return \de\toxa\txf\databrowser current instance
	 */

	public function disableForm()
	{
		$this->form = false;

		return $this;
	}

	/**
	 * Provides HTML form to use instead of any internal one.
	 *
	 * This method fails if databrowser has started to managed its own form
	 * internally before. You need to explicitly disable that form first.
	 *
	 * @param \de\toxa\txf\html_form $form form to be used by databrowser
	 * @return \de\toxa\txf\databrowser current instance
	 * @throws \InvalidArgumentException
	 */

	public function useForm( html_form $form )
	{
		if ( $this->form instanceof html_form )
			throw new \InvalidArgumentException( 'must not replace form of databrowser' );

		$this->form       = $form;
		$this->callerForm = true;

		return $this;
	}

	/**
	 * Adds another column to current databrowser.
	 *
	 * @param string $name name of property in connected datasource to be linked with column
	 * @param string $label label in column header
	 * @param boolean $sortable true to mark column as sortable
	 * @param function|array $formatter callback for individually formatting values of column/property
	 * @return \de\toxa\txf\databrowser current instance
	 */

	public function addColumn( $name, $label, $sortable = false, $formatter = null )
	{
		$this->columns[$name] = array(
									'name'      => $name,
									'label'     => $label,
									'sortable'  => $sortable,
									'formatter' => $formatter,
									);

		return $this;
	}

	/**
	 * Selects callback invoked on every rendered item in datalist rendering
	 * its related command controls.
	 *
	 * @param function|array $callback callback to invoke on every item
	 * @throws \InvalidArgumentException
	 * @return \de\toxa\txf\databrowser current instance
	 */

	public function setRowCommander( $callback )
	{
		if ( !is_callable( $callback ) )
			throw new \InvalidArgumentException( 'invalid callback' );

		$this->rowCommander = $callback;

		return $this;
	}

	/**
	 * Processes available input adjusting internal state of databrowser.
	 *
	 * @return \de\toxa\txf\databrowser current instance
	 */

	public function processInput()
	{
		if ( !$this->pager )
		{
			// process all input once USING pager for semaphore
			$this->pager = new pager( $this->datasource->count(), $this->volatilePager );

			$this->datasource
						->size( $this->pager->size() )
						->offset( $this->pager->offset() );

			if ( $this->getForm() )
				$this->pager->enableButtons( true );
		}

		return $this;
	}

	/**
	 * Retrieves items to list reduced to set of previously defined columns.
	 *
	 * @return array[array] items to list
	 */

	protected function getItems()
	{
		$out = array();

		foreach ( $this->datasource->items() as $key => $item )
		{
			if ( array_key_exists( $this->idName, $item ) )
				$key = $item[$this->idName];

			$out[$key] = array();

			foreach ( $this->columns as $name => $definition )
				$out[$key][$name] = @$item[$name];

			if ( is_callable( $this->rowCommander ) )
				$out[$key]['|command'] = call_user_func( $this->rowCommander, $key, $item );
		}

		return $out;
	}

	/**
	 * Internal callback formatting individual cell.
	 *
	 * @param string $value content of cell
	 * @param string $name name of column/property
	 * @param array $item whole item of current row
	 * @param string $itemId ID of current item
	 * @return string prepared content of cell
	 */

	public function formatCell( $value, $name, $item, $itemId )
	{
		if ( is_callable( $this->columns[$name]['formatter'] ) )
			$value = call_user_func( $this->columns[$name]['formatter'], $value, $name, $item, $itemId );

		return $value !== null ? $value : _L('-');
	}

	/**
	 * Internal callback rendering individual column header label.
	 *
	 * @param string $name name of column
	 * @return string rendered label of column
	 */

	public function formatHeader( $name )
	{
		return ( $name[0] == '|' ) ? '' : $this->columns[$name]['label'];
	}

	/**
	 * Retrieves form used by current databrowser instance.
	 *
	 * @return html_form
	 */

	public function getForm()
	{
		if ( $this->form === null )
		{
			$this->form       = html_form::create( $this->datasource->name() . '_browser', $this->className );
			$this->callerForm = false;
		}

		return $this->form;
	}

	/**
	 * Retrieves markup/code of databrowser to be embedded in view.
	 *
	 * @return string markup/code of databrowser
	 */

	public function getCode()
	{
		// get items to render in databrowser
		try
		{
			$this->processInput();

			$items = $this->getItems();
		}
		catch ( \RuntimeException $e )
		{
			return markup::paragraph( markup::emphasize( _L('Failed to query datasource for listing items!') ) );
		}


		// render table including related pager widget
		if ( count( $items ) )
		{
			// fetch form to use optionally
			$form = $this->getForm();

			// render table view
			$id    = $form ? null : $this->datasource->name();
			$table = html::arrayToTable( $items, $id, array( &$this, 'formatCell' ),
			                             array( &$this, 'formatHeader' ),
			                             '', '', $this->className );

			$code = $this->pager . $table;


			// wrap rendered table view in form
			if ( $form && !$this->callerForm )
				$code = (string) $form->addContent( $code );


			// return code of rendered databrowser
			return $code;
		}


		$text = $this->emptyText ? $this->emptyText : _L('There is no data to be listed here ...');

		return markup::paragraph( markup::emphasize( $text ) );
	}

	public function render()
	{
		return $this->getCode();
	}

/*
 * disabled for __toString() mustn't throw exceptions frequently hardening bug hunting
 * 
	public function __toString()
	{
		return $this->getCode();
	}
 *
 */
}

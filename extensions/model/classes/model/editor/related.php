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

use \de\toxa\txf\datasource\connection;
use \de\toxa\txf\datasource\datasource_exception;
use \de\toxa\txf\markup;
use \de\toxa\txf\html_form;
use \de\toxa\txf\data;

class model_editor_related extends model_editor_abstract
{
	/**
	 * relation to be edited
	 *
	 * @var model_relation
	 */

	protected $relation;

	/**
	 * name of relation as defined in its model
	 *
	 * @var string
	 */

	protected $relationName;

	/**
	 * reference of relation mutable in editor
	 *
	 * @var model_relation_reference
	 */

	protected $mutable;

	/**
	 * lists selectable options to choose from on selecting binding of reference
	 *
	 * This array is mapping serialized IDs of selectable target items into
	 * their label and data prepared for use on binding reference to this item.
	 *
	 * @var array
	 */

	private $availableBindings;

	/**
	 * minimum number of relations to choose
	 *
	 * @var int
	 */

	protected $minCount = 0;

	/**
	 * maximum number of possible relations to choose
	 *
	 * @var int
	 */

	protected $maxCount = 1;

	/**
	 * relation's initial binding to be restored on demand
	 *
	 * @var model_relation_binding
	 */

	protected $initialBinding;

	/**
	 * Gives number of selectors to render in editable mode.
	 *
	 * @var int
	 */

	protected $selectorCount = 1;

	/**
	 * Marks if current relation is unique (in that it must not link same items
	 * multiple times)-
	 *
	 * @var bool
	 */

	protected $unique = true;

	/**
	 * Gets invoked on rendering readonly values of related ones.
	 *
	 * @var callable
	 */

	protected $formatRenderer = null;



	public function __construct( model_relation $relation )
	{
		if ( !$relation )
			throw new \InvalidArgumentException( 'missing relation' );

		$this->relationName = $relation->getName();
		if ( is_null( $this->relationName ) )
			// relation's name is considered equivalent to some property of starting node's model
			throw new \InvalidArgumentException( 'relation must be named for editing' );


		// check if relation's binding makes it suitable for editing here
		// (there must be exactly one unbound reference in relation now)
		$unboundReferences = $relation->getUnboundReferences();
		switch ( count( $unboundReferences ) ) {
			case 1 :
				break;
			case 2 :
				if ( $unboundReferences[0] === $relation->referenceAtIndex( 0 ) ) {
					// first reference might be also unbound due to item not
					// selected yet
					// -> ignore ...
					array_shift( $unboundReferences );
					break;
				}
			default :
				throw new \InvalidArgumentException( 'relation is not properly bound for editing' );
		}


		// store information
		$this->relation = $relation;
		$this->mutable  = array_shift( $unboundReferences );

		// also take snapshot of relation's initial binding state
		$this->initialBinding = model_relation_binding::createOnRelation( $relation )->save();
	}

	/**
	 * Creates instance of editor element for editing provided relation of models.
	 *
	 * @note The relation must be named.
	 * @note The relation must have exactly one unbound reference.
	 *
	 * @param model_relation $relation
	 * @return model_editor_related
	 */

	public static function create( model_relation $relation = null )
	{
		return new static( $relation );
	}

	public function setMinimumCount( $count )
	{
		if ( $count > 1 && !$this->supportsMultiple() )
			throw new \InvalidArgumentException( 'type of mutable reference in relation is not "many-to-many"' );

		if ( $count < 0 || ( $count > $this->maxCount && $this->maxCount > 1 ) )
			throw new \OutOfRangeException( 'invalid number of relations' );

		$this->minCount = intval( $count );

		$this->isMandatory = ( $this->minCount > 0 );

		return $this;
	}

	public function setMaximumCount( $count )
	{
		if ( $count > 1 && !$this->supportsMultiple() )
			// for mutable node isn't many to many there can't be more than one
			// actual binding in mutable node for provided relation ...
			$count = 1;

		if ( $count < $this->minCount )
			throw new \OutOfRangeException( 'invalid number of relations' );

		$this->maxCount = intval( $count );

		return $this;
	}

	/**
	 * Adjusts number of selectors to show at least.
	 *
	 * @param int $count number of selectors to show at least
	 * @return $this
	 */

	public function setSelectableCount( $count )
	{
		if ( !ctype_digit( trim( $count ) ) || $count < 1 )
			throw new \InvalidArgumentException( 'invalid number of selectables' );

		$this->selectorCount = max( 1, intval( $count ) );

		return $this;
	}

	/**
	 * Adjusts whether relation is unique or not.
	 *
	 * In a unique relation two pairs of items must not be put into relation
	 * multiple times. Relations are marked as unique by default.
	 *
	 * @param bool $unique true to mark relation unique
	 * @return $this
	 */

	public function setUnique( $unique = true )
	{
		$this->unique = !!$unique;

		return $this;
	}

	/**
	 * Provides callback to invoke for actually rendering related items.
	 *
	 * @param callable $callable function invoked per related item for rendering it readonly
	 * @return $this
	 */

	public function setFormatter( $callable )
	{
		if ( is_callable( $callable ) ) {
			$this->formatRenderer = $callable;
		} else {
			throw new \InvalidArgumentException( 'invalid formatter callable' );
		}

		return $this;
	}

	/**
	 * Detects if mutable reference of relation is supporting multiple bindings
	 * or instances of relation.
	 *
	 * @return bool true on mutable reference supporting multiple instances
	 */

	public function supportsMultiple()
	{
		return $this->mutable->getReferencingNode()->isManyToMany();
	}

	public function normalize( $input, $property, model_editor $editor )
	{
		if ( $this->isReadOnly )
			return null;

		return array_unique( array_filter( (array) $input ) );
	}

	public function validate( $input, $property, model_editor $editor )
	{
		$min = max( $this->isMandatory ? 1 : 0, $this->minCount );
		if ( count( $input ) < $min )
			throw new \InvalidArgumentException( $min > 1 ? \de\toxa\txf\_L('This information is required multiple times.') : \de\toxa\txf\_L('This information is required.') );

		$available = $this->getSelectableOptions();

		foreach ( (array) $input as $id )
			if ( !array_key_exists( $id, $available ) )
				throw new \InvalidArgumentException( 'selected option is not available' );

		return true;
	}

	protected function getSelectableOptions()
	{
		$available = $this->_getAvailables();

		$out = array();

		foreach ( $available as $key => $info ) {
			if ( array_key_exists( 'local', $info ) )
				$local = $info['local'];
			else
				$local = $available[$key]['local'] = $this->bindingToLocalId( $info['data'] );

			$out[$local] = $info['label'];
		}

		return $out;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor, model_editor_field $field )
	{
		if ( $this->isReadOnly )
			return $this->renderStatic( $form, $name, $input, $label, $editor, $field );

		$available = array_merge( array( '0' => \de\toxa\txf\_L('-') ), $this->getSelectableOptions() );

		$values = array_pad( $input, $this->selectorCount, null );

		if ( \de\toxa\txf\input::vget( $name . '_cmdActionAddSelector' ) )
			$values[] = null;

		if ( count( $values ) > $this->maxCount )
			array_splice( $values, $this->maxCount );

		$selectors = array_map( function( $value ) use ( $name, $available ) {
			return markup::selector( $name . '[]', $available, $value );
		}, $values );

		$classes = implode( ' ', array_filter( array( $this->class, 'related' ) ) );

		$form->setRow( $name, $label, implode( "\n", $selectors ), $this->isMandatory, $this->hint, null, $classes );

		if ( count( $selectors ) < $this->maxCount ) {
			$form->setRowCode( $name, markup::button( $name . '_cmdActionAddSelector', '1', \de\toxa\txf\_L('Add Entry'), \de\toxa\txf\_L('Click this button to add another selector for choosing related information.'), 'actionAddSelector' ) );
		}

		return $this;
	}

	public function renderStatic( html_form $form, $name, $value, $label, model_editor $editor, model_editor_field $field )
	{
		$available = $this->getSelectableOptions();

		$value = array_map( function( $selected ) use ( $available ) {
			return $available[$selected];
		}, (array) $value );

		$classes = implode( ' ', array_filter( array( $this->class, 'related' ) ) );

		$list = markup::bullets( $value, $classes );

		$form->setRow( $name, $label, $list, $this->isMandatory(), $this->hint, null, $classes );

		return $this;
	}

	public function formatValue( $name, $value, model_editor $editor, model_editor_field $field )
	{
		if ( is_null( $value ) || !count( $value ) ) {
			return null;
		}

		if ( $this->formatRenderer ) {
			foreach ( $value as $id => $label ) {
				$value[$id] = call_user_func( $this->formatRenderer, $id, $label );
			}
		}

		$classes = implode( ' ', array_filter( array( $this->class, 'related' ) ) );

		if ( $this->maxCount > 1 ) {
			return markup::bullets( $value, $classes );
		}

		return markup::inline( array_shift( $value ), $classes );
	}


	protected function _getSelectorOfAvailable()
	{
		return array(
			'model'      => $this->mutable->getReferencedNode()->getModel(),// source of properties
			'properties' => $this->mutable->getReferencedPropertyNames(),   // properties to fetch
		    'filter' => array(
			    'properties' => array(),                                    // properties to match
			    'values'     => array(),                                    // values of properties to match
		    )
		);
	}

	protected function _getSelectorOfExisting( model $item = null )
	{
		$mutableNode = $this->mutable->getReferencingNode();

		$selector = array(
			'model'      => $mutableNode->getModel(),                       // source of properties
			'properties' => $this->mutable->getReferencingPropertyNames(),  // properties to fetch
			// derive filter from optionally available opposite reference
		    'filter' => array(
			    'properties' => array(),                                    // properties to match
		        'values'     => array(),                                    // values of properties to match
		    )
		);

		$identifyingReference = $this->mutable->getOppositeReferenceAtNode( $mutableNode );
		if ( $identifyingReference )
		{
			// mutable node is involved in another (opposite) reference
			// -> use that one's binding on mutable node to select existing
			//    relations for editor of mutable node
			$selector['filter']['properties'] = $this->mutable->getOppositePropertiesOf( $mutableNode );
			$selector['filter']['values']     = $this->mutable->getOppositeValuesOf( $mutableNode );

			// is identifying reference bound?
			// (it is unbound if associated editor is going to create new item
			//  and identifying reference is including that item yet missing)
			if ( !is_array( $selector['filter']['values'] ) ) {
				if ( ( $this->relation->referenceAtIndex( 0 ) !== $identifyingReference ) ||
				     !$this->getEditor() || $this->getEditor()->hasItem() )
					// basically this case should be excluded by code in
					// constructor ...
					throw new \RuntimeException( 'mutable reference of relation is not bound properly' );

				// identifying reference is first reference of relation, but
				// item in editor does not exist yet
				// -> reference can't be bound
				//    -> there aren't any relations on that item not yet existing
				//       -> create filter always failing (to fetch empty set)
				$selector['filter']['properties'] = array( '(1+1)' );   // parentheses are used to cheat name quoting
				$selector['filter']['values']     = array( 3 );         // for getting term "(1+1)=3" (to mismatch every row)
			}

			$selector['filter']['values'] = array_values( $selector['filter']['values'] );


			// choose mode to use on updating bindings
			if ( $this->mutable->getReferencingNode()->isManyToMany() )
			{
				// case: a < m > c with a-m bound and m-c unbound
				// - delete all instances of mutable node matching binding of
				//   opposite reference
				//   TODO: what if extra data is attached to m? (have some callback? require use of derived class?)
				$selector['drop'] = array(
					'model'      => $identifyingReference->getReferencingNode()->getModel(),
					'filter'     => &$selector['filter'],
				);
			}
			else
			{
				// case: a > m > c with a-m bound and m-c unbound
				// - for a>m there can be one m at most,
				// - for a-m is bound there must be one m at least
				//   -> don't delete m, keep as is, but "null" it's referencing
				//      in m>c
				$selector['null'] = array(
					'model'      => $mutableNode->getModel(),
					'filter'     => &$selector['filter'],
					'properties' => $this->mutable->getReferencingPropertyNames(),
				);
			}
		}
		else
		{
			// mutable node is not involved in another (opposite) reference
			// -> mutable node is either endpoint of relation

			// -> is mutable node at start of relation?
			$startNode = $this->relation->nodeAtIndex( 0 );
			if ( $mutableNode !== $startNode )
				// -> yes
				throw new \RuntimeException( 'relation can be adjusted from opposite end point, only' );

			// is editor actually working on existing item?
			// (... having ID for use in relations?)
			$editor    = $this->getEditor();
			$itemToUse = $editor->hasItem() ? $editor->item() : $item;

			if ( !$itemToUse )
			{
				// -> no
				//    there aren't any existing records to load, actually

				// but is model managed by editor matching model of mutable node?
				if ( !$mutableNode->getModel()->isSameModel( $editor->model() ) )
					// -> no
					throw new \RuntimeException( 'relation is not compatible with item in editor' );

				// okay, create filter always mismatching w/o failing
				// - get records with (first) property of ID matching 0 and 1 simultaneously
				$idName = $editor->model()->getMethod( 'idName' )->invoke( null, 0 );

				$selector['filter']['properties'] = array( $idName, $idName );
				$selector['filter']['values']     = array( 0, 1 );
			}
			else
			{
				// yes, there is an item in editor

				// is mutable node's model compatible with model of item in editor?
				if ( !$mutableNode->getModel()->isSameModel( $itemToUse->getReflection() ) )
					// -> no
					throw new \RuntimeException( 'relation is not compatible with item in editor' );


				// -> item in editor is suitable instance of mutable node
				//    use item's ID for identifying existing relations on it
				//    (since mutable node is referring to item in editor and
				//     since mutable node is the referencing node, there is
				//     a single existing relation at most, though)
				$id = $itemToUse->id();

				$selector['filter']['properties'] = array_keys( $id );
				$selector['filter']['values']     = array_values( $id );
			}

			// case: m > b with m matching item in editor and m-b unbound
			// - m is item in editor and thus mustn't be deleted on saving relation
			//   -> keep m as is, but "null" it's referencing properties
			$selector['null'] = array(
				'model'      => $mutableNode->getModel(),
				'filter'     => &$selector['filter'],
				'properties' => $this->mutable->getReferencingPropertyNames(),
			);
		}


		return $selector;
	}

	/**
	 * Retrieves set of available bindings (e.g. for stuffing selectors in editor).
	 *
	 * @see model::listItemLabels()
	 * @param connection|null $source data source to fetch items from,
	 *        null to use default of model mentioned in selector
	 * @return array see extended result form of model::listItemLabels()
	 */

	protected function _selectAvailable( connection $source )
	{
		return $this->_select( $source, $this->_getSelectorOfAvailable() );
	}

	/**
	 * Retrieves set of editable bindings actually existing in datasource.
	 *
	 * @see model::listItemLabels()
	 * @param connection|null $source data source to fetch items from,
	 *        null to use default of model mentioned in selector
	 * @return array see extended result form of model::listItemLabels()
	 */

	protected function _selectExisting( connection $source )
	{
		return $this->_select( $source, $this->_getSelectorOfExisting() );
	}

	/**
	 * Retrieves items matching selector from datasource.
	 *
	 * @param connection|null $source data source to fetch items from,
	 *        null to use default of model mentioned in selector
	 * @param array $selector selector retrieved from _getSelectorOfAvailable()
	 *        or _getSelectorOfExisting()
	 * @return array
	 */

	protected function _select( connection $source = null, $selector )
	{
		// get list of items
		/** @var model_relation_model $model */
		$model = $selector['model'];
		$items = $model->listItems( $source, $selector['properties'], $selector['filter']['properties'], $selector['filter']['values'] );

		// ensure additionally fetched properties are suitable for instantly
		// binding (due to having proper sorting order)
		foreach ( $items as $id => $item )
			$items[$id]['data'] = data::rearrangeArray( $item['data'], $selector['properties'] );


		return $items;
	}

	/**
	 * Compiles SQL term for use in WHERE clauses to select records matching all
	 * properties in given filter.
	 *
	 * Resulting term is using parameter markers for values of those properties
	 * to be matched. These values have to be given on querying data source.
	 *
	 * @param connection $source data source to use for quoting names
	 *        of filtering properties
	 * @param array $filter set of "properties" and "values"
	 * @return string SQL-term
	 */

	protected function _compileFilterTerm( connection $source, $filter )
	{
		return implode( ' AND ', array_map( function( $n ) use ( $source ) {
			return $source->quoteName( $n ) . '=?';
		}, $filter['properties'] ) );
	}

	/**
	 * Retrieves name of set associated with described model.
	 *
	 * The name is optionally qualified and quoted on providing data source
	 * considered to contain data set.
	 *
	 * @param connection $source data source to use for quoting name
	 * @param model_relation_model $model description of a model
	 * @return string optionally quoted name of set associated with described model
	 */

	protected function _setNameOfModel( connection $source = null, model_relation_model $model )
	{
		$set = $model->getSetName();

		return is_null( $source ) ? $set : $source->qualifyDatasetName( $set );
	}

	/**
	 * Resets selected set of relations.
	 *
	 * This method is expecting selector including description on how to unbind
	 * some selected nodes. Given selector must include information on either
	 * updating properties of node to NULL or deleting records from data source.
	 *
	 * For these information are included with selector of existing bindings,
	 * only, this method is considered to use in conjunction with selector
	 * returned from model_editor_related::_getSelectorOfExisting().
	 *
	 * @param connection $source connection data source
	 * @param array $selector selector describing bindings to release
	 * @throws datasource_exception on failing to adjust data source
	 */

	protected function _unbindSelected( connection $source, $selector )
	{
		if ( array_key_exists( 'null', $selector ) ) {
			// Selector is configured to keep existing instances of mutable node
			// but drop any information in their referencing properties.
			//
			// This is used in these cases:
			//   1) m > b
			//   2) (m) > b  (m does not exist yet, but is created on saving item in editor)
			//   3) a > m > c
			//
			// In all these cases mutable node m isn't of type many-to-many and
			// mustn't be dropped for either being item in editor or being bound
			// remotely in separate reference of edited relation (a>m in case 3).
			//
			// Select matching items (though there is at most one instance of m,
			// only) and NULL all properties referencing in mutable reference!
			$null = $selector['null'];

			// compile filter for selecting items to NULL
			$filter = $this->_compileFilterTerm( $source, $null['filter'] );

			// get name of data set of mutable node's model
			$set = $this->_setNameOfModel( $source, $null['model'] );

			// compile query to NULL referencing properties in data set
			$term = implode( ',', array_map( function ( $name ) use ( $source ) {
				return $source->quoteName( $name ) . '=NULL';
			}, $null['properties'] ) );

			$query = "UPDATE $set SET $term WHERE $filter";

			// ensure to have related data set
			$null['model']->declareInDatasource( $source );

			// perform modification in data source
			if ( !$source->test( $query, $null['filter']['values'] ) )
				throw new datasource_exception( $source, 'failed to null references' );
		}

		if ( array_key_exists( 'drop', $selector ) ) {
			// Selector is configured to actually remove existing instances of
			// mutable node's model from data source for re-inserting new ones
			// afterwards.
			//
			// This is used on many-to-many-relations operating on one reference
			// of contained many-to-many-node:
			//   1) a < m > b (with a<m bound and m>b mutable/unbound)
			//
			// Select all matching items of model of m according to existing
			// binding on a<m (all items of m referring to a given in binding of
			// a<m) and remove them from data source.
			$drop = $selector['drop'];

			// compile filter for selecting items to remove
			$filter = $this->_compileFilterTerm( $source, $drop['filter'] );

			// get name of data set containing records of items to remove
			$set = $this->_setNameOfModel( $source, $drop['model'] );

			// compile query to remove selected records from data source
			$query = "DELETE FROM $set WHERE $filter";

			// ensure to have related data set
			$drop['model']->declareInDatasource( $source );

			// perform modification in data source
			if ( !$source->test( $query, $drop['filter']['values'] ) )
				throw new datasource_exception( $source, 'failed to drop nodes of reference' );
		}
	}

	protected function _rebind( connection $source, $selector, $binding )
	{
		// ensure to have related data set
		$selector['model']->declareInDatasource( $source );


		if ( array_key_exists( 'drop', $selector ) )
		{
			// previously existing binding instances have been removed from data source
			// -> need to re-insert them now

			// use identifying properties of item to insert (due to bound opposite reference)
			$properties = $selector['filter']['properties'];
			$values     = $selector['filter']['values'];

			// join names and values of binding/referencing properties
			$reference = array_combine( $selector['properties'], $binding );

			assert( 'count( $selector["properties"] ) === count( $binding )' );
			assert( 'count( $reference ) === count( $binding )' );

			// add binding/references properties to list of insertions
			foreach ( $reference as $name => $value )
				if ( array_key_exists( $name, $properties ) )
				{
					if ( $value != $properties[$name] )
						throw new \RuntimeException( 'double use of identifying/referencing property with mismatching value' );
				}
				else
				{
					$properties[] = $name;
					$values[]     = $value;
				}

			// compile insertion statement
			$set        = $this->_setNameOfModel( $source, $selector['model'] );
			$markers    = implode( ',', array_pad( array(), count( $properties ), '?' ) );
			$properties = implode( ',', $source->qualifyPropertyNames( null, $properties ) );

			$query = "INSERT INTO $set ($properties) VALUES ($markers)";

			if ( !$source->test( $query, $values ) )
				throw new datasource_exception( $source, 'failed to insert binding record' );
		}
		else
		{
			// previously existing binding instances haven't been removed from
			// data source, but their referencing regarding mutable reference
			// have been reset
			// -> re-establish referencing on existing binding instances
			// TODO: check if this case is ever going to update more than one record
			// TODO: add support for updating single record out of several matching if required ($offset is selecting record to update, actually)

			// compile term for identifying record(s) to update
			$filter = $this->_compileFilterTerm( $source, $selector['filter'] );

			// join names and values of binding/referencing properties
			$reference = array_combine( $selector['properties'], $binding );

			assert( 'count( $selector["properties"] ) === count( $binding )' );
			assert( 'count( $reference ) === count( $binding )' );

			// collect binding/referencing properties to actually use on updating
			$properties = $values = array();

			foreach ( $reference as $name => $value )
				if ( array_key_exists( $name, $properties ) )
				{
					if ( $value != $properties[$name] )
						throw new \RuntimeException( 'double use of identifying/referencing property with mismatching value' );
				}
				else
				{
					$properties[] = $name;
					$values[]     = $value;
				}

			// compile statement
			$set         = $this->_setNameOfModel( $source, $selector['model'] );
			$assignments = implode( ',', array_map( function( $name ) { return "$name=?"; }, $source->qualifyPropertyNames( null, $properties ) ) );
			$values      = array_merge( $values, $selector['filter']['values'] );

			$query = "UPDATE $set SET $assignments WHERE $filter";


			if ( !$source->test( $query, $values ) )
				throw new datasource_exception( $source, 'failed to insert binding record' );
		}
	}

	/**
	 * Retrieves (cached) set of available bindings for mutable reference/node.
	 *
	 * The resulting array maps serialized IDs of available targets into array
	 * consisting of target's formatted label in element "label" and set of
	 * values for binding this target in element "data".
	 *
	 * @return array[array]
	 */

	protected function _getAvailables()
	{
		if ( !is_array( $this->availableBindings ) )
			$this->availableBindings = $this->_selectAvailable( $this->getEditor()->source() );

		return $this->availableBindings;
	}

	/**
	 * Fetches entry from cached set of available bindings selected by its
	 * 1-based index.
	 *
	 * @throws \OutOfRangeException if local ID is out of range or invalid
	 * @param int $localId 1-based index of entry to fetch
	 * @return array selected entry's values for binding properties of mutable node
	 */

	public function localIdToBinding( $localId )
	{
		if ( $localId >= 1 ) {
			$slice = array_slice( $this->_getAvailables(), $localId - 1, 1 );
			$match = array_shift( $slice );
			if ( is_array( $match ) )
				return $match['data'];
		}

		throw new \OutOfRangeException( 'invalid local ID' );
	}

	/**
	 * Looks up cached set of available bindings of mutable reference for
	 * matching given binding returning 1-based index of matching entry as that
	 * binding's local ID.
	 *
	 * @throws \InvalidArgumentException on binding isn't available (anymore)
	 * @param array $binding values of binding properties of mutable node
	 * @return int local ID of binding if still available
	 */

	public function bindingToLocalId( $binding )
	{
		$binding = array_values( $binding );

		$index = 1;
		foreach ( $this->_getAvailables() as $info )
			if ( $binding == array_values( $info['data'] ) )
				return $index;
			else
				$index++;

		throw new \InvalidArgumentException( 'invalid item ID' );
	}

	public function onSelectingItem( model_editor $editor, model $item, model_editor_field $field )
	{
		// bind element's relation with item sharing its data source
		$this->relation
			->setDatasource( $item->source() )
			->bindNodeOnItem( 0, $item );
	}

	public function onLoading( model_editor $editor, model $item = null, $propertyName, model_editor_field $field )
	{
		if ( $this->relationName == $propertyName )
		{
			// editor obviously tries to load information on current element

			if ( !$item )
				// but editor isn't operating on existing item currently, but
				// is going to create new one
				// -> there is no existing relation
				return array();


			/*
			 * query data source for existing bindings on mutable reference of relation
			 */

			$bindings = $this->_selectExisting( $this->getEditor()->source() );
			if ( !is_array( $bindings ) )
				throw new \RuntimeException( 'failed to load existing relations on edited item' );


			/*
			 * map fetched bindings of mutable node into set of local IDs
			 * of items in set of available bindings
			 */

			$ctx = $this;
			$values = array_filter( array_map( function( $binding ) use ( $ctx ) {
				try {
					return $ctx->bindingToLocalId( $binding['data'] );
				} catch ( \InvalidArgumentException $e ) {
					// binding isn't available anymore
					// -> map to null for filtering off afterwards
					return null;
				}
			}, $bindings ) );


			// finally return simple list of numeric IDs
			return $values;
		}


		// not our cup of tea ...
		return null;
	}

	private $__savedBindings = null;

	public function beforeValidating( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field )
	{
		if ( array_key_exists( $this->relationName, $itemProperties ) )
		{
			// Editor is including information on bindings of this element's
			// relation.
			// -> For this element is using local IDs for addressing selected
			//    relations these local IDs need to be mapped to bindable
			//    addresses for validation.
			$element = $this;

			$itemProperties[$this->relationName] = array_map( function( $local ) use ( $element ) {
				return $element->localIdToBinding( $local );
			}, (array) $itemProperties[$this->relationName] );
		}

		return $itemProperties;
	}

	public function beforeStoring( model_editor $editor, model $item = null, $itemProperties, model_editor_field $field )
	{
		if ( array_key_exists( $this->relationName, $itemProperties ) )
		{
			// editor is including information on bindings of relation
			//
			// Since item in editor might not be created in data source, yet,
			// saving relations isn't possible now, but may be done after
			// storing item's basic properties, thus ...
			//
			// 1) temporarily save binding information on relation
			$this->__savedBindings = (array) $itemProperties[$this->relationName];

			// 2) remove binding information from properties of item to save
			unset( $itemProperties[$this->relationName] );
		} else
			$this->__savedBindings = null;

		return $itemProperties;
	}

	public function afterStoring( model_editor $editor, model $item, $itemProperties, model_editor_field $field )
	{
		if ( is_array( $this->__savedBindings ) ) {
			$datasource = $editor->source();
			$existing   = $this->_getSelectorOfExisting( $item );

			// 1) drop all previously existing bindings
			$this->_unbindSelected( $datasource, $existing );

			// 2) write all current bindings
			foreach ( array_values( $this->__savedBindings ) as $localId )
				$this->_rebind( $datasource, $existing, $this->localIdToBinding( $localId ) );


			$item->dropCachedRecord();
		}

		return $item;
	}

	public function onDeleting( model_editor $editor, model $item, model_editor_field $field )
	{
		$datasource = $editor->source();
		$existing   = $this->_getSelectorOfExisting();

		// 1) drop all previously existing bindings
		$this->_unbindSelected( $datasource, $existing );
	}
}

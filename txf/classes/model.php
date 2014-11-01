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

use \de\toxa\txf\datasource\datasource_exception;


class model
{
	/**
	 *
	 * @var datasource\connection
	 */

	protected $_source;

	/**
	 * extra prefix to prepend to model::$set
	 *
	 * This separation is good for deriving models e.g. in a sub-namespace of an
	 * application for using shared prefix.
	 *
	 * @var string
	 */

	protected static $set_prefix = '';

	/**
	 * name of datasource's table model is stored in
	 *
	 * @var string
	 */

	protected static $set = null;

	/**
	 * ID selecting record in model's table
	 *
	 * @var integer|array
	 */

	private $_id;

	/**
	 * cached copy of item's record
	 *
	 * @var array
	 */

	protected $_record = null;

	/**
	 * list of properties in datasource used as components of an item's ID
	 *
	 * @var array
	 */

	protected static $id = array( 'id' );

	/**
	 * list of properties in datasource used as components of an item's label
	 *
	 * @var array
	 */

	protected static $label = array( 'name' );

	/**
	 * string used to concatenate multiple components of current item's ID
	 *
	 * @var string
	 */

	protected static $id_glue = '::';

	/**
	 * string used to concatenate multiple components of current item's label
	 *
	 * @var string
	 */

	protected static $label_glue = ', ';

	/**
	 * declarations of named relations
	 *
	 * @var array
	 */

	protected static $relations = array();

	/**
	 * Caches compiled instances of named relations.
	 *
	 * @var array
	 */

	protected static $_relationCache = array();



	protected function __construct( datasource\connection $source = null, $itemId )
	{
		if ( $source === null )
			$source = datasource::selectConfigured( 'default' );

		$this->_source = $source;

		$this->bind( $itemId );
	}

	/**
	 * Provides model instances prepared for accessing item by ID.
	 *
	 * @param datasource\connection $source link to datasource to use instead of current one
	 * @param array|integer $itemId ID of item to be managed by instance
	 * @return model instance of model prepared to manage selected item
	 * @throws \InvalidArgumentException on providing invalid item ID
	 * @throws datasource_exception on missing selected item in datasource
	 */

	public static function select( datasource\connection $source = null, $itemId )
	{
		if ( !is_array( $itemId ) && !$itemId )
			throw new \InvalidArgumentException( 'invalid item ID' );

		return new static( $source, $itemId );
	}

	/**
	 * Finds item of model matching provided properties' values.
	 *
	 * If multiple items are matching the first of them is retrieved, only.
	 *
	 * @param datasource\connection $source data source to look for matching item in
	 * @param array $properties set of model's properties mapping into values to match
	 * @return model|null matching model item, null on mismatch
	 * @throws \InvalidArgumentException
	 */

	public static function find( datasource\connection $source = null, $properties )
	{
		if ( !is_array( $properties ) || !count( $properties ) )
			throw new \InvalidArgumentException( 'invalid set of properties to find' );

		if ( $source == null )
			$source = datasource::selectConfigured( 'default' );

		static::updateSchema( $source );


		$query = $source->createQuery( static::$set_prefix . static::$set );

		foreach ( static::$id as $idName )
			$query->addProperty( $idName );


		$definition = static::define();

		foreach ( $properties as $name => $value )
			if ( array_key_exists( $name, $definition ) || in_array( $name, static::$id ) )
				$query->addCondition( "$name=?", true, $value );
			else
				throw new \InvalidArgumentException( 'undefined property' );


		$id = $query->limit( 1 )->execute()->row();
		if ( !$id )
			return null;

		return static::select( $source, $id );
	}

	/**
	 * Creates instance of current model not bound to any item.
	 *
	 * This is useful for accessing particular meta-information on current model
	 * without requiring to choose any of its existing items.
	 *
	 * Using this method is deprecated in favour of using model::getReflection()
	 * instead.
	 *
	 * @param datasource\connection $source link to datasource to use instead of default
	 * @return model
	 * @deprecated
	 */

	public static function proxy( datasource\connection $source = null )
	{
		return new static( $source, 0 );
	}

	/**
	 * Retrieves reflection on model's class.
	 *
	 * @return \ReflectionClass
	 */

	public static function getReflection()
	{
		return new \ReflectionClass( get_called_class() );
	}

	/**
	 * Retrieves single property's name included in current model's IDs.
	 *
	 * @param integer $dimension|null 0-based index of dimension to retrieve, null to retrieve all dimensions
	 * @param integer $maxDimension
	 * @return string name of property included in model's IDs
	 * @throws \LogicException if maximum dimension is exceeding size of model's IDs
	 * @throws \OutOfRangeException if requested dimension is exceeding size of model's ID
	 */

	public static function idName( $dimension = 0, $maxDimension = 1 )
	{
		if ( is_null( $dimension ) )
			return static::$id;

		if ( $maxDimension > count( static::$id ) )
			throw new \LogicException( _L('Model isn\'t supporting IDs with requested dimensionality.') );

		if ( !( $dimension >= 0 ) || $dimension >= count( static::$id ) )
			throw new \OutOfRangeException( _L('Model\'s ID is missing requested dimension.') );

		return static::$id[intval( $dimension )];
	}

	/**
	 * Retrieves one or all property names declared to provide information for
	 * individually labelling instances of this model.
	 *
	 * In difference to model::label() this method is selecting properties of
	 * a model's items included in labelling every item.
	 *
	 * @param int|null $dimension null to fetch whole set of labelling names, integer index to get single element, only
	 * @return array set of labelling property names or single element of it
	 */

	public static function labelName( $dimension = null )
	{
		$label = static::$label;
		if ( !$label )
			throw new \LogicException( 'model does not select label properties' );

		if ( !is_array( $label ) )
			$label = array( strval( $label ) );

		if ( is_null( $dimension ) )
			return $label;

		if ( !( $dimension >= 0 ) || $dimension > count( $label ) )
			throw new \OutOfRangeException( _L('Model\'s label is missing requested dimension.') );

		return $label[intval( $dimension )];
	}

	/**
	 * Formats label using provided values of labelling properties.
	 *
	 * @param array $values map of labelling properties into an item's related values
	 * @return string label/title of single item
	 */

	public static function formatLabel( $values )
	{
		$index = array_flip( static::$label );

		uksort( $values, function( $left, $right ) use( $index ) {
			return @$index[$left] - @$index[$right];
		} );

		return implode( static::$label_glue, $values );
	}

	/**
	 * Retrieves number of dimension in current model's IDs.
	 *
	 * @return integer
	 */

	public function idSize()
	{
		return count( static::$id );
	}

	/**
	 * Detects if provided ID is suitable for binding current model instance to
	 * item.
	 *
	 * @param mixed $id ID to be tested
	 * @return boolean true if provided ID might be used for binding model instance
	 */

	public function isValidId( $id )
	{
		try
		{
			static::normalizeId( $id );

			return true;
		}
		catch ( \InvalidArgumentException $e )
		{
			return false;
		}
	}

	/**
	 * Retrieves URL of view showing selected item.
	 *
	 * @param array|integer $itemId ID of item to show in view
	 * @param array $parameters optional parameters to pass in compiled URL
	 * @return string URL of view
	 * @throws \BadMethodCallException due to requiring to overload this method
	 */

	public static function getterUrl( $itemId = null, $parameters = array() )
	{
		throw new \BadMethodCallException( _L('Generic model isn\'t providing getter URL.') );
	}

	/**
	 * Retrieves URL of view showing list of current model's items.
	 *
	 * @param array $parameters optional parameters to pass in compiled URL
	 * @return string URL of view
	 * @throws \BadMethodCallException due to requiring to overload this method
	 */

	public static function listerUrl( $parameters = array() )
	{
		throw new \BadMethodCallException( _L('Generic model isn\'t providing lister URL.') );
	}

	/**
	 * Retrieves label to describe selected number of current model's items in a
	 * human-readable way.
	 *
	 * Expected result is commonly describing model's items, e.g. "person" or
	 * "comments". It is not about labelling single item.
	 *
	 * @param integer $itemCount number of items to be described, might be used for proper localizing
	 * @return string label describing selected number of model's items
	 * @throws \BadMethodCallException due to requiring to overload this method
	 */

	public static function label( $itemCount = 1 )
	{
		throw new \BadMethodCallException( _L('Generic model isn\'t providing localized label.') );
	}

	/**
	 * Retrieves current item's unfiltered set of properties.
	 *
	 * @see model::published()
	 * @see model::proxy()
	 * @return array current item's unfiltered set of properties
	 * @throws \RuntimeException on missing preparation for accessing single item of model
	 * @throws datasource_exception on failing to uniquely select single record in datasource
	 */

	public function load()
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( !is_array( $this->_record ) )
		{
			static::updateSchema( $this->_source );

			$query = $this->_source->createQuery( static::set() );

			foreach ( $this->_id as $name => $value )
				$query->addCondition( $this->_source->quoteName( $name ) . '=?', true, $value );

			$matches = $query->limit( 2 )->execute();

			if ( $matches->count() !== 1 )
				throw new datasource_exception( $this->_source, 'item missing in datasource' );

			$this->_record = $matches->row();
		}

		return $this->_record;
	}

	/**
	 * Drops record containing properties load from data source before for
	 * caching on repeated retrieval.
	 *
	 * @return $this
	 */

	public function dropCachedRecord()
	{
		$this->_record = null;

		return $this;
	}

	/**
	 * Indicates whether current model instance is bound to single item for
	 * managing acces on it or not (thus serving as a proxy of model, only).
	 *
	 * @return boolean
	 */

	public function isBound()
	{
		return is_array( $this->_id ) && count( array_filter( $this->_id, function( $i ) { return $i; } ) );
	}

	/**
	 * Binds previously unbound instance of model to single item of model for
	 * managing access on it.
	 *
	 * @param int|string|float|array $itemId set of identifying properties and values or single identifying property's value
	 * @param boolean $ignoreNonIdProperties true to prevent exceptions on providing full record of model for binding
	 * @return \de\toxa\txf\model current instance
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 */

	public function bind( $itemId, $ignoreNonIdProperties = false )
	{
		if ( $this->isBound() )
			throw new \LogicException( 'rebinding bound instance rejected' );

		$this->_id = static::normalizeId( $itemId, $ignoreNonIdProperties );

		return $this;
	}

	/**
	 * Normalizes and formally validates provided item ID.
	 *
	 * @param int|string|float|array $itemId ID to normalize
	 * @param boolean $reduceToIdProperties true to drop all properties in a provided record not contained in model's IDs
	 * @param boolean $mayBeEmpty true to return empty array if $id is unset
	 * @return array normalized ID consisting of all required properties used to select item
	 * @throws \InvalidArgumentException
	 */

	public static function normalizeId( $itemId, $reduceToIdProperties = false, $mayBeEmpty = false )
	{
		if ( !is_array( $itemId ) )
		{
			if ( $itemId === null && $mayBeEmpty )
				return array();

			if ( count( static::$id ) !== 1 )
				throw new \InvalidArgumentException( 'malformed model item id' );

			return array( static::$id[0] => $itemId );
		}

		if ( !count( $itemId ) && $mayBeEmpty )
			return array();


		if ( $reduceToIdProperties )
			foreach ( $itemId as $name => $value )
				if ( !in_array( $name, static::$id ) )
					unset( $itemId[$name] );

		if ( count( $itemId ) !== count( static::$id ) )
			throw new \InvalidArgumentException( 'malformed model item id' );

		foreach ( static::$id as $name )
			if ( !array_key_exists( $name, $itemId ) )
				throw new \InvalidArgumentException( 'malformed model item id' );

		return $itemId;
	}

	/**
	 * Retrieves currently managed item's ID.
	 *
	 * @return array set of item's properties and related values used to identify item#s record in datasource
	 */

	public function id()
	{
		return $this->_id;
	}

	/**
	 * Retrieves current item's filtered set of properties.
	 *
	 * @see model::load()
	 * @return array current item's set of properties filtered to include properties current user may read, only
	 * @throws \RuntimeException on missing preparation for accessing single item of model
	 */

	public function published()
	{
		$record = $this->load();

		if ( !is_array( $record ) )
			return array();

		foreach ( $record as $property => $value )
			if ( !$this->isPublic( $property, $value, $record, false ) )
				unset( $record[$property] );

		if ( is_array( static::$relations ) )
			foreach ( static::$relations as $name => $definition ) {
				$value = static::relation( $name )
					->bindNodeOnItem( 0, $this )
					->listRelated();

				if ( $this->isPublic( $name, $value, $record, true ) )
					$record[$name] = $value;
			}

		return $record;
	}

	protected function isPublic( $propertyName, $propertyValue, $record )
	{
		return true;
	}

	public function describe()
	{
		$record = $this->published();

		if ( array_key_exists( 'label', $record ) )
			return $record['label'];

		if ( array_key_exists( 'name', $record ) )
			return $record['name'];

		$idName = static::idName();
		if ( array_key_exists( $idName, $record ) )
			return sprintf( _L('#%d of model %s'), $record[$idName], get_class( $this ) );

		return sprintf( _L('instance of model %s'), get_class( $this ) );
	}

	/**
	 * Fetches properties of previously selected item.
	 *
	 * @throws datasource\datasource_exception
	 * @throws \RuntimeException on trying to fetch property without selecting item first
	 * @throws \InvalidArgumentException on trying to fetch unknown property
	 * @param string $name name of property to fetch
	 * @return mixed value of property
	 */

	public function __get( $name )
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( array_key_exists( $name, $this->_id ) )
			return $this->_id[$name];

		$record = $this->load();

		if ( array_key_exists( $name, $record ) )
			return $record[$name];

		throw new \InvalidArgumentException( "unknown property: " . static::set() . ".$name" );
	}

	public function __set( $name, $value )
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( array_key_exists( $name, $this->_id ) )
			throw new \RuntimeException( "invalid call for changing item id" );

		$record = $this->load();

		if ( !array_key_exists( $name, $record ) )
			throw new \InvalidArgumentException( "unknown property: " . static::set() . ".$name" );


		$link = $this->_source;

		$filter = array_keys( $this->_id );
		$values = array_values( $this->_id );

		array_unshift( $values, $value );

		$qSet   = $link->qualifyDatasetName( static::set() );
		$qName  = $link->quoteName( $name );
		$filter = array_map( function( $col ) use ( $link ) { return $link->quoteName( $col ) . "=?"; }, $filter );

		if ( !$link->test( "UPDATE $qSet SET $qName=? WHERE " . implode( ' AND ', $filter ), $values ) )
			throw new datasource_exception( $link, 'failed to update property in datasource: ' . static::set() . ".$name" );


		return ( $this->_record[$name] = $value );
	}

	/**
	 * Retrieves unqualified and unquoted name of current model's set in
	 * datasource.
	 *
	 * @return string
	 */

	public static function set()
	{
		return static::$set_prefix . static::$set;
	}

	/**
	 * Provides hash of properties' names into their individual type
	 * declarations.
	 *
	 * This method is available for input validation and database upgrade
	 * purposes provided separately.
	 *
	 * @throws \RuntimeException
	 * @return array[string->string] map of property names into their type
	 */

	public static function define()
	{
		throw new \RuntimeException( _L('Generic model does not have defined structure for being abstract.') );
	}

	/**
	 * Detects if model contains property with provided name.
	 *
	 * @param string $name name of property to look up
	 * @return bool true on model including named property, false otherwise
	 */

	public static function isPropertyName( $name )
	{
		return in_array( $name, static::$id, true ) || array_key_exists( $name, static::define() );
	}

	/**
	 * Creates new instance of current model described by provided properties
	 * to be managed in selected (or default) datasource.
	 *
	 * @param datasource\connection $source datasource for managing model instance
	 * @param array $properties set of model-specific properties
	 * @return model
	 * @throws \InvalidArgumentException
	 * @throws datasource_exception
	 */

	public static function create( datasource\connection $source, $properties = array() )
	{
		static::validateIdsOnCreate( $properties );

		$set      = static::$set_prefix . static::$set;
		$callback = new \ReflectionMethod( get_called_class(), '_onCreate' );
		$item     = null;

		static::updateSchema( $source );

		if ( !$source->transaction()->wrap( function( $link ) use ( $callback, $properties, &$item )
		{
			$item = $callback->invoke( null, $link, $properties );

			return true;
		}, 'createModel.' . $set ) )
			throw new datasource_exception( $source, 'failed to commit creation of item in datasource, model ' . $set );

		return new static( $source, $item );
	}

	/**
	 * Retrieves defined schema of current model.
	 *
	 * This method is fetching definition of schema from derivable method
	 * model::define() and validates returned array for containing all
	 * properties required to identify instances of model.
	 *
	 * @return array map of model's properties into their types' definitions
	 * @throws model_exception if schema isn't declaring properties used to identify instances
	 */

	final public static function getSchema()
	{
		$definition = static::define();

		foreach ( static::$id as $property )
			if ( !array_key_exists( $property, $definition ) ) {
				if ( $property === 'id' )
					// apply property "id" implicitly here
					// (don't rely on datasource\connection adding implicitly
					//  here for keeping this code independent from that code)
					$definition['id'] = 'INTEGER NOT NULL';
				else
					throw new model_exception( 'missing ID property in schema definition' );
			}

		return $definition;
	}

	/**
	 * Updates schema of selected data source to contain definition for current
	 * model's set.
	 *
	 * @param datasource\connection $source data source to use
	 * @throws datasource_exception on updating schema failed
	 * @throws model_exception on incomplete/invalid schema definition
	 */

	public static function updateSchema( datasource\connection $source )
	{
		$dataSet = static::$set_prefix . static::$set;

		if ( !$source->exists( $dataSet ) )
		{
			if ( !$source->createDataset( $dataSet, static::getSchema(), static::$id ) )
				throw new datasource_exception( $source, 'updating schema failed' );
		}
	}

	/**
	 * Validates provided set of properties to comply with requirements on
	 * providing ID on creating new item.
	 *
	 * In a model with single-dimensional ID this method is ensuring not to have
	 * ID included with properties since ID is assigned automatically.
	 *
	 * In a model with multi-dimensional ID this method is ensuring to have ALL
	 * components/dimensions of ID included with properties as there is no way
	 * of selecting ID automatically.
	 *
	 * @param array $properties properties provided on call to static method create()
	 * @return void returns on success
	 * @throws \InvalidArgumentException
	 */

	protected static function validateIdsOnCreate( $properties )
	{
		foreach ( static::$id as $name )
			if ( count( static::$id ) !== 1 )
			{
				if ( !array_key_exists( $name, $properties ) )
					throw new \InvalidArgumentException( 'missing ID component in properties of model instance to create' );
			}
			else
			{
				if ( array_key_exists( $name, $properties ) )
					throw new \InvalidArgumentException( 'unexpected ID component in properties of model instance to create' );
			}
	}

	/**
	 * Writes data on item to be created into provided datasource.
	 *
	 * The invocation of this method is wrapped in a transaction in context of
	 * linked datasource. Thus this method may throw exceptions for rolling back
	 * partial modifications in datasource.
	 *
	 * The method is considered to return array consisting of all properties
	 * and related values used to identify created instances of current model.
	 *
	 * The method must be defined public for internal use in a closure. By design
	 * it should be considered protected.
	 *
	 * @param datasource\connection $link link to datasource
	 * @param array $arrProperties properties of new instance to write into datasource
	 * @return array ID-components of created instance (to be used on loading this instance by model::select())
	 * @throws datasource_exception
	 * @throws model_exception on missing parts of multidimensional ID
	 */

	public static function _onCreate( datasource\connection $link, $arrProperties )
	{
		$set = static::$set_prefix . static::$set;


		// auto-assign ID to item unless properties include explicit ID
		if ( count( static::$id ) === 1 )
		{
			$idName = static::idName( 0 );
			if ( !array_key_exists( $idName, $arrProperties ) )
				$arrProperties[$idName] = $link->nextID( $set );
		}


		// validate and extract ID of item to create
		$item = array();
		foreach ( static::$id as $name )
			if ( array_key_exists( $name, $arrProperties ) )
				$item[$name] = $arrProperties[$name];
			else
				throw new model_exception( 'missing properties of created item\'s identifier' );


		// prepare SQL statement for inserting record on new item
		$columns = array_keys( $arrProperties );
		$values  = array_values( $arrProperties );
		$marks   = array_pad( array(), count( $arrProperties ), '?' );

		$columns = array_map( function( $n ) use ( $link ) { return $link->quoteName( $n ); }, $columns );
		$qSet    = $link->qualifyDatasetName( $set );

		$columns = implode( ',', $columns );
		$marks   = implode( ',', $marks );


		// query datasource for inserting record of new item
		if ( $link->test( "INSERT INTO $qSet ($columns) VALUES ($marks)", $values ) === false )
			throw new datasource_exception( $link, 'failed to create item in datasource, model ' . $set );


		return $item;
	}

	/**
	 * Retrieves SQL-like filter term for addressing items of current model.
	 *
	 * @return string
	 */

	public function filter()
	{
		return implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
	}

	/**
	 * Removes current instance of model from datasource.
	 *
	 * @note On deleting current instance all tightly related instances are
	 *       deleted implicitly.
	 *
	 * @note On deleting current instance this object is released and can't be
	 *       used for managing model instance, anymore.
	 *
	 * @throws \RuntimeException
	 * @throws datasource_exception
	 */

	public function delete()
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );


		/*
		 * wrap deletion of item and its tight relations in a transaction
		 */

		$item      = $this;
		$set       = static::set();
		$relations = static::$relations;
		$class     = get_called_class();

		if ( !$this->_source->transaction()->wrap( function( datasource\connection $connection ) use ( $item, $set, $relations, $class )
		{
			// cache information on item to delete
			$idValues = array_values( $item->id() );
			$record   = count( $relations ) ? $item->load() : array();


			/*
			 * step 1) actually delete current item
			 */

			$qSet = $connection->qualifyDatasetName( $set );
			if ( $connection->test( sprintf( 'DELETE FROM %s WHERE %s', $qSet, $item->filter() ), $idValues ) === false )
				throw new datasource_exception( $connection, 'failed to delete requested model instance' );


			/*
			 * step 2) update all related items
			 */

			// on deleting item relations have to be updated
			// - always null references on current item to be deleted
			foreach ( $relations as $relationName => $relationSpec ) {
				// detect if relation is "tight"
				$isTightlyBound = in_array( 'tight', (array) @$relationSpec['options'] ) ||
				                  @$relationSpec['options']['tight'];

				// get first reference in relation
				/** @var model_relation $relation */
				$relation      = call_user_func( array( $class, "relation" ), $relationName );
				$firstNode     = $relation->nodeAtIndex( 0 );
				$secondNode    = $relation->nodeAtIndex( 1 );
				$secondNodeSet = $secondNode->getName( true );

				// prepare collection of information on second node
				$onSecond = array(
					'null'   => array(),
				    'filter' => array(
					    'properties' => array(),
				        'values'     => array(),
				    )
				);

				// extract reusable code to prepare filter for selecting record
				// of second node actually related to deleted item
				$getFilter = function() use ( &$onSecond, $connection, $firstNode, $secondNode, $record ) {
					// retrieve _qualified and quoted_ names of predecessor's properties
					$onSecond['filter']['properties'] = $secondNode->getPredecessorNames( $connection );
					foreach ( $firstNode->getSuccessorNames() as $property )
						$onSecond['filter']['values'][] = @$record[$property];
				};

				// inspect type of relationship between first and second node of
				// current relation
				if ( $secondNode->canBindOnPredecessor() ) {
					// second node in relation is referencing first one
					// -> there are items of second node's model referring to
					//    item removed above

					// -> find records of all those items ...
					$getFilter();

					// ... at least for nulling their references on deleted item
					$onSecond['null'] = $onSecond['filter']['properties'];
				} else {
					// first node in relation is referencing second one
					// -> deleted item was referencing item of second node's model
					//    -> there is basically no need to update any foreign
					//       references on deleted item

					if ( $isTightlyBound )
						// relation is marked as "tight"
						// -> need to delete item referenced by deleted item
						$getFilter();
				}


				// convert filtering properties of second node into set of assignments
				$filter = array_map( function( $name ) { return "$name=?"; }, $onSecond['filter']['properties'] );

				if ( $isTightlyBound ) {
					// in tight relation immediately related elements are
					// deleted as well

					$secondModel = $secondNode->getModel();

					if ( $secondModel->isVirtual() ) {
						// second model is virtual, only
						// -> it's okay to simply delete matching records in datasource
						$qSet = $connection->qualifyDatasetName( $secondNodeSet );
						$term = implode( ' AND ', $filter );

						if ( !$connection->test( "DELETE FROM $qSet WHERE $term", $onSecond['filter']['values'] ) )
							throw new datasource_exception( $connection, 'failed to delete instances of tightly related items in relation ' . $relationName );

						// TODO: add support for tightly bound relation in opposite reference of this virtual node
					} else {
						// query data source for IDs of all tightly related items
						$query = $connection->createQuery( $secondNodeSet );

						// - select related items using properties involved in relation
						foreach ( $onSecond['filter']['properties'] as $index => $name )
							$query->addFilter( "$name=?", true, $onSecond['filter']['values'][$index] );

						// - fetch all properties used to identify items
						$ids = $secondModel->getIdProperties();
						foreach ( $ids as $index => $name )
							$query->addProperty( $connection->quoteName( $name ), "i$index" );

						// iterate over all matches for deleting every one
						$matches = $query->execute();
						$iCount  = count( $ids );

						while ( $match = $matches->row() ) {
							// extract properly sorted ID from matching record
							$id = array();
							for ( $i = 0; $i < $iCount; $i++ )
								$id[$ids[$i]] = $match["i$i"];

							// select item of model and delete it
							$secondModel
								->selectInstance( $connection, $id )
								->delete();
						}
					}
				} else if ( count( $onSecond['null'] ) ) {
					// need to null foreign references on deleted item
					$values = array_merge(
									array_pad( array(), count( $onSecond['filter']['values'] ), null ),
									$onSecond['filter']['values']
								);

					$qSet     = $connection->qualifyDatasetName( $secondNodeSet );
					$matching = implode( ' AND ', $filter );
					$setting  = implode( ',', $filter );

					if ( !$connection->test( "UPDATE $qSet SET $setting WHERE $matching", $values ) )
						throw new datasource_exception( $connection, 'failed to null references on deleted item in relation ' . $relationName );
				}
			}

			return true;
		} ) )
			throw new datasource_exception( $this->_source, 'failed to completely delete item and its tightly bound relations' );


		// drop that item now ...
		$this->_id = null;
	}

	public static function formatCell( $value, $name, $record, $id )
	{
		return $value !== null ? $value : _L('-');
	}

	public static function formatHeader( $name )
	{
		return sprintf( "%s:", static::nameToLabel( $name ) );
	}

	public static function nameToLabel( $name )
	{
		return $name;
	}

	/**
	 * Retrieves connection to data source used by model currently.
	 *
	 * @return datasource\connection
	 */

	public function source()
	{
		return $this->_source;
	}

	/**
	 * Retrieves query on datasource prepared to basically operate on current
	 * model's data set.
	 *
	 * @param string $alias optional alias of model's data set in query
	 * @return datasource\query customizable query on current model
	 */

	public function query( $alias = null )
	{
		$alias = trim( $alias );

		return $this->_source->createQuery( static::set() . ( $alias != '' ? " $alias" : '' ) );
	}

	/**
	 * Normalizes information on a given model.
	 *
	 * This method takes
	 *
	 * - name of a model's class
	 * - instance of a model
	 * - reflection on a model's class
	 *
	 * and returns the reflection on a model's class.
	 *
	 * @throws \InvalidArgumentException if provided class isn't derived from class model
	 * @param model|string|\ReflectionClass $model information on model to normalize
     * @return \ReflectionClass reflection on class of given model
	 */

	public static function normalizeModel( $model )
	{
		if ( $model instanceof self )
			return $model->getReflection();

		if ( is_string( $model ) ) {
			$model = trim( $model );

			if ( $model[0] != '\\' )
				$model = __NAMESPACE__ . '\\' . $model;

			$model = new \ReflectionClass( $model );
		}

		if ( !( $model instanceof \ReflectionClass ) )
			throw new \InvalidArgumentException( 'invalid model information' );

		if ( !$model->isSubclassOf( __NAMESPACE__ . '\model' ) )
			throw new \InvalidArgumentException( 'no such model' );

		return $model;
	}

	/**
	 * Retrieves relation according to declaration selected by given name.
	 *
	 * Relations may be declared in static property $relations of a model.
	 *
	 * @example

	    - have user relate to address via immediately related person
		  (user.person_id => person.id - person.address_id => contact.id)

		user::$relations = array(
	        'contact_address' => array(             // <- declaring name of relation
				'referencing' => array(             // <- declaring user referring to some
					'model'  => 'person',           // <- ... person. ...
					'with'   => 'person_id',        // <- declaring user.person_id to contain foreign key of ...
					'on'     => 'id',               // <- ... person.id    thus:  user.person_id => person.id
					'referencing' => array(         // <- declaring person is then referring to another model ...
						'model' => 'address',       // <- ... address ...
						'alias' => 'contact',       // <- ... calling it "contact" here ...
						'with'  => 'address_id',    // <- ... with person.address_id containing foreign key of ...
						'on'    => 'id',            // <- ... contact.id   thus:  person.address_id => contact.id
					)
				)
			),
		);

		- same relation declared in reverse direction
		  (contact.id <= person.address_id - person.id <= user.person_id)

		address::$relations = array(
			'user' => array(                        // <- declaring name of relation
	            'alias' => 'contact',               // <- declaring alias of initial node in relation being contact
				'referencedBy' => array(            // <- declaring address (called contact) referred to by some
					'model'  => 'person',           // <- ... person. ...
					'with'   => 'address_id',       // <- ... using its property address_id to contain foreign key of ...
					'on'     => 'id',               // <- ... contact.id    thus:  contact.id <= person.address_id
					'referencedBy' => array(        // <- declaring person is then referred to by another model ...
						'model' => 'user',          // <- ... user ...
						'with'  => 'person_id',     // <- ... using its property person_id to contain foreign key of ...
						'on'    => 'id',            // <- ... person.id     thus:  person.id <= user.person_id
					)
				)
			),
		);

	    - example of an m:n-relation
          (customer.id <= ordered.customer_id - ordered.product_id => goods.id)

		customer::$relations = array(
	        'ordered_goods' => array(
				'referencedBy' => array(
					'model' => 'ordered',
					'with'  => 'customer_id',
					'on'    => 'id',
					'referencing' => array(
						'model' => 'product',
						'alias' => 'goods',
						'with'  => 'product_id',
						'on'    => 'id',
					)
				)
			),
		);

	    - same m:n-relation with multi-dimensional reference
          (customer.id <= ordered.customer_id - [ordered.type,ordered.product_id] => [goods.type,goods.id])

		customer::$relations = array(
	        'ordered_goods' => array(
				'referencedBy' => array(
					'model' => 'ordered',
					'with'  => 'customer_id',
					'on'    => 'id',
					'referencing' => array(
						'model' => 'product',
						'alias' => 'goods',
						'with'  => array( 'type', 'product_id' ),
						'on'    => array( 'type', 'id' ),
					)
				)
			),
		);

	    - again, same m:n-relation with multi-dimensional reference, this time
	      relying on implicitly inserted virtual model (named explicitly)
          (customer.id <= ordered.customer_id - [ordered.type,ordered.product_id] => [goods.type,goods.id])

		customer::$relations = array(
	        'ordered_goods' => array(
				'referencedBy' => array(
					'dataset' => 'ordered',
					'with'    => 'customer_id',
					'on'      => 'id',
					'referencing' => array(
						'model' => 'product',
						'alias' => 'goods',
						'with'  => array( 'type', 'product_id' ),
						'on'    => array( 'type', 'id' ),
					)
				)
			),
		);

	    - again, same m:n-relation with multi-dimensional reference, this time
	      relying on implicitly inserted virtual model (not named explicitly)
          (customer.id <= customer_goods.customer_id - [customer_product.type,customer_product.product_id] => [goods.type,goods.id])

	      The inner set's name is derived by combining names of neighbouring sets.

		customer::$relations = array(
	        'ordered_goods' => array(
				'referencedBy' => array(
					'with'    => 'customer_id',
					'on'      => 'id',
					'referencing' => array(
						'model' => 'product',
						'alias' => 'goods',
						'with'  => array( 'type', 'product_id' ),
						'on'    => array( 'type', 'id' ),
					)
				)
			),
		);

	    - finally, same m:n-relation with multi-dimensional reference, this time
	      relying on implicitly inserted virtual model (not named explicitly) and
	      deriving property names from referenced properties' names
          (customer.id <= customer_product.customer_id - [customer_product.product_type,customer_product.product_id] => [goods.type,goods.id])

	      The inner set's name is derived by combining names of neighbouring sets.

		customer::$relations = array(
	        'ordered_goods' => array(
				'referencedBy' => array(
					'on' => 'id',
					'referencing' => array(
						'model' => 'product',
						'alias' => 'goods',
						'on'    => array( 'type', 'id' ),
					)
				)
			),
		);

	 *
	 * @param string $name name of model's relation to retrieve
	 * @param model $bindOnInstance instance of current model to bind relation on
	 * @return model_relation
	 */

	public static function relation( $name, model $bindOnInstance = null )
	{
		$name = data::isKeyword($name);
		if (!$name)
			throw new \InvalidArgumentException('invalid relation name');

		if ( array_key_exists( $name, self::$_relationCache ) ) {
			// read existing relation from runtime cache
			$relation = self::$_relationCache[$name];
		} else {
			if ( !array_key_exists( $name, static::$relations ) )
				throw new \InvalidArgumentException( sprintf( 'no such relation: %s', $name ) );


			try {
				$relation = static::_compileRelation( null, static::$relations[$name] );
				if ( !$relation->isComplete() )
					throw new \InvalidArgumentException('incomplete relation definition: %s');

				$relation->setName( $name );

				// write this relation (unbound) into runtime cache
				self::$_relationCache[$name] = $relation;
			} catch ( \InvalidArgumentException $e ) {
				throw new \InvalidArgumentException(sprintf('%s: %s', $e->getMessage(), $name), $e->getCode(), $e);
			}
		}


		// clone cached relation
		$relation = clone $relation;

		// bind on provided instance if that is a subclass of current one
		if ( $bindOnInstance ) {
			$expectedClass = new \ReflectionClass( get_called_class() );

			if ( !$expectedClass->isSubclassOf( $bindOnInstance ) )
				throw new \InvalidArgumentException( 'provided instance is not compatible' );

			$relation->bindNodeOnItem( 0, $bindOnInstance );
		}


		return $relation;
	}

	/**
	 * @param model_relation $relation
	 * @param $relationSpecification
	 * @return model_relation
	 */

	protected static function _compileRelation( model_relation $relation = null, $relationSpecification )
	{
		// extract declaration of next reference from provided specs
		$toNext   = is_array( @$relationSpecification['referencing'] ) ? $relationSpecification['referencing'] : null;
		$fromNext = is_array( @$relationSpecification['referencedBy'] ) ? $relationSpecification['referencedBy'] : null;

		// ensure specs are containing either of the two options, only
		if ( !( is_array( $toNext ) ^ is_array( $fromNext ) ) )
			throw new \InvalidArgumentException( 'invalid reference mode in relation' );

		// select found declaration of next reference
		$referenceToAppend = $toNext ? $toNext : $fromNext;



		/*
		 * get current end node of relation for appending new reference
		 */

		if ( is_null( $relation ) ) {
			// there isn't any "current end node" for relation is empty, yet
			// -> start new relation on current model
			$previousNode = model_relation_node::createOnModel( new \ReflectionClass( get_called_class() ) );

			// -> apply optionally given global alias
			if ( @$relationSpecification['alias'] )
				$previousNode->setAlias( $relationSpecification['alias'] );
		} else {
			// get end node of existing relation
			$previousNode = $relation->nodeAtIndex( -1 );
		}


		/*
		 * extract description of model associated with next node to append
		 */

		if ( array_key_exists( 'model', $referenceToAppend ) ) {
			// got explicit name of model existing in code
			$model = static::normalizeModel( $referenceToAppend['model'] );
			$model = model_relation_model::createOnModel( $model );
		} else {
			// missing explicit name of model existing in code
			// -> need to manage virtual model
			if ( $fromNext && @$referenceToAppend['referencing'] && !@$referenceToAppend['referencedBy'] ) {
				// next node in relation is of type many-to-many
				// -> suitable for deriving virtual model

				$succeedingReference =& $referenceToAppend['referencing'];

				/*
				 * in a relation a < m > c ...
				 *
				 * ... this code is about to describe virtual model of m
				 * ... $previousNode refers to node a
				 * ... $referenceToAppend provides information on a<m or m more specifically
				 * ... $succeedingReference provides information on m>c or c more specifically
				 */

				if ( !@$succeedingReference['model'] )
					// don't support chains of virtual models in relations
					throw new \InvalidArgumentException( 'invalid chaining of nodes with virtual models' );

				// get model of node succeeding reference is referring to (model of c in example above)
				$nextModel = model_relation_model::createOnModel( static::normalizeModel( $succeedingReference['model'] ) );


				/*
				 * get names of either neighbouring model's set
				 *
				 * (don't care for aliases here for these might change occasionally)
				 */

				$previousSetName = $previousNode->getName( true );
				$nextSetName     = $nextModel->getSetName();

				if ( !$previousSetName || !$nextSetName )
					throw new \InvalidArgumentException( 'missing set names of models neighbouring virtual one' );


				/*
				 * extract name of virtual model's data set
				 */

				if ( array_key_exists( 'dataset', $referenceToAppend ) )
					// specification provides set name explicitly
					$setName = $referenceToAppend['dataset'];
				else
					// specification does not provide set name explicitly
					// -> derive name from set names of neighbouring models
					$setName = "{$previousSetName}_$nextSetName";


				/*
				 * derive properties and types of virtual model's data set
				 */

				// get definitions of properties in either neighbouring model
				$previousDefinition = $previousNode->getModel()->getDefinition();
				$nextDefinition     = $nextModel->getDefinition();

				// get properties in either neighbouring model to refer to in virtual
				$previousNames = array_values( (array) @$referenceToAppend['on'] );
				$nextNames     = array_values( (array) @$succeedingReference['on'] );

				if ( !count( $previousNames ) || !count( $nextNames ) )
					throw new \InvalidArgumentException( 'missing names of properties virtual node is referencing on' );

				// get names of properties in virtual model to use on referencing
				// - try explicitly mentioned names of properties first
				$namesOnPrevious = array_values( (array) @$referenceToAppend['with'] );
				$namesOnNext     = array_values( (array) @$succeedingReference['with'] );

				// - fall back to implicitly deriving property names from referenced properties
				if ( !count( $namesOnPrevious ) ) {
					$namesOnPrevious = array_map( function( $name ) use ( $previousSetName ) {
						return "{$previousSetName}_$name";
					}, $previousNames );

					$referenceToAppend['with'] = $namesOnPrevious;
				}

				if ( !count( $namesOnNext ) ) {
					$namesOnNext = array_map( function( $name ) use ( $nextSetName ) {
						return "{$nextSetName}_$name";
					}, $nextNames );

					$succeedingReference['with'] = $namesOnNext;
				}

				// ensure either reference is working with uniquely named properties
				$clashingNames = array_intersect( $namesOnPrevious, $namesOnNext );
				if ( count( $clashingNames ) )
					throw new \InvalidArgumentException( 'invalid clash of implicit property names: ' . implode( ', ', $clashingNames ) );

				// finally compile definition of virtual model's data set
				$virtualDefinition = array();

				foreach ( $namesOnPrevious as $index => $name ) {
					$relatedName = $previousNames[$index];
					$virtualDefinition[$name] = preg_replace( '/\bprimary\s+key\b/i', '', $previousDefinition[$relatedName] );
				}

				foreach ( $namesOnNext as $index => $name ) {
					$relatedName = $nextNames[$index];
					$virtualDefinition[$name] = preg_replace( '/\bprimary\s+key\b/i', '', $nextDefinition[$relatedName] );
				}

				/*
				 * create virtual model basing on description prepared above
				 */

				$model = model_relation_model::createOnVirtualModel( $setName, $virtualDefinition, array_keys( $virtualDefinition ) );
			} else
				// next node isn't of type many-to-many
				// -> reject implicitly to derive some virtual model
				throw new \InvalidArgumentException( 'definition of relation is missing model of node' );
		}


		/*
		 * create node to append
		 */

		$nextNode = model_relation_node::createOnModel( $model );
		if ( $referenceToAppend['alias'] )
			$nextNode->setAlias( $referenceToAppend['alias'] );


		/*
		 * establish reference between previous and next node as declared
		 */

		$targetProperty = @$referenceToAppend['on'];
		if ( !$targetProperty ) {
			$targetProperty = array( 'id' );
		}

		if ( $toNext ) {
			// establish reference from previous node to new node
			$previousNode->makeReferencingSuccessorIn( $referenceToAppend['with'] );
			$nextNode->makeReferencedByPredecessorOn( $targetProperty );
		} else {
			// establish reference from new node to previous one
			$previousNode->makeReferencedBySuccessorOn( $targetProperty );
			$nextNode->makeReferencingPredecessorIn( $referenceToAppend['with'] );
		}


		/*
		 * append node to relation
		 */

		if ( is_null( $relation ) )
			// relation hasn't been created before at all
			// -> create now
			$relation = model_relation::create( $previousNode );

		$expectingAnotherReference = @$referenceToAppend['referencing'] || @$referenceToAppend['referencedBy'];

		// append next node to relation
		$relation->add( $nextNode, $expectingAnotherReference );


		/*
		 * recursively add another node on having declaration
		 */

		if ( $expectingAnotherReference )
			return static::_compileRelation( $relation, $referenceToAppend );


		return $relation;
	}

	/**
	 * Creates query for browsing model's items stored in provided datasource.
	 *
	 * @param datasource\connection $source datasource containing model's items, omit for using default datasource
	 * @return datasource\query query for listing items of current model
	 */

	public static function browse( datasource\connection $source = null )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		if ( !( $source instanceof datasource\connection ) )
			throw new \InvalidArgumentException( _L('missing link to datasource') );

		static::updateSchema( $source );

		return $source->createQuery( static::$set_prefix . static::$set );
	}

	/**
	 * Serializes an item's properties included in its ID into a string.
	 *
	 * @throws \InvalidArgumentException if unserializing ID does not result in array
	 * @param array $idProperties named properties of item used to identify
	 * @return string item's serialized ID
	 */

	public static function serializeId( $idProperties )
	{
		if ( get_called_class() !== __CLASS__ ) {
			$id = array();

			// ensure to have all components of ID in given properties, sorted properly
			foreach ( static::$id as $name )
				if ( array_key_exists( $name, $idProperties ) )
					$id[$name] = $idProperties[$name];
				else
					throw new \InvalidArgumentException( 'missing component of ID in properties' );

			// use callback if ID glue is callable
			if ( is_callable( static::$id_glue ) )
				return call_user_func( static::$id_glue, $id, static::$id, true );
		} else
			// Explicit invocation of model::serializeId() is available to
			// imitate ID serialization on _arbitrary ID sets_ using default ID
			// glue (expected to be string).
			// This is used by e.g. model_relation_model::getSerializedId().
			// -> For operating on arbitrary ID sets those cases might fail on
			//    validating ID properties as done above.
			//    -> Skip ID validation ...
			$id = $idProperties;

		// concatenate ID components using declared glue
		return implode( static::$id_glue, $id );
	}

	/**
	 * Extracts an item's ID from its serialized form in provided string.
	 *
	 * @throws \InvalidArgumentException if unserializing ID does not result in array
	 * @param string $serializedId serialized ID to convert back into item's ID
	 * @return array item's ID
	 */

	public static function unserializeId( $serializedId )
	{
		if ( is_callable( static::$id_glue ) )
			$id = call_user_func( static::$id_glue, $serializedId, static::$id, false );
		else
			$id = array_combine( static::$id, explode( static::$id_glue, $serializedId ) );

		if ( !is_array( $id ) )
			throw new \InvalidArgumentException( 'invalid item ID' );

		return $id;
	}

	/**
	 * Retrieves query for listing selected properties of current model's items.
	 *
	 * Provided set of properties in $properties is quoted and prefixed by name
	 * of model's data set. Every element in array provided in $properties is
	 * either name of a property in data set or maps alias to use into name of a
	 * property, e.g.
	 *
	 *    array( 'id', 'fullName' => 'CONCAT(lastName,", ",firstName)', 'category' => 'type' )
	 *
	 * is fetching property "id" as "id", concatenation of properties "lastName"
	 * and "firstName" as "fullName" and property "type" as "category".
	 *
	 * @param datasource\query $query query to use instead of model's default query
	 * @param datasource\connection $source datasource to use on implicitly
	 *        calling model::browse() if $query is omitted
	 * @param array|null|false $properties set of (unqualified|unquoted) property
	 *        names to fetch per item, null fetch all properties of model's items
	 *        and false to skip declaring properties to fetch
	 * @return datasource\query
	 */

	public static function listItemProperties( datasource\query $query = null, datasource\connection $source = null, $properties = null )
	{
		if ( $query !== null ) {
			// clone provided query for starting vanilla set of properties to fetch
			$query = clone $query;
			$query->dropProperties();
		} else
			$query = static::browse( $source );


		/*
		 * adjust query to fetch properties every listed item's ID and label
		 * consist of
		 */

		$db  = $query->datasource();
		$set = $db->qualifyDatasetName( static::$set_prefix . static::$set );

		if ( is_null( $properties ) )
			$properties = array( '.*' );

		if ( is_array( $properties ) )
			foreach ( $properties as $alias => $name )
			{
				if ( !preg_match( '/[)(]/', $name ) )
					$name = $db->quoteName( $name );

				if ( is_string( $alias ) )
					$alias = $db->quoteName( $alias );
				else
					$alias = null;

				$query->addProperty( $set . '.' . $name, $alias );
			}


		return $query;
	}

	/**
	 * Lists items of model as array mapping items' IDs into their declared
	 * labels.
	 *
	 * This method is using model::listItemProperties() to fetch list of items'
	 * IDs and labels to return a list mapping serialized IDs into formatted
	 * labels. IDs are serialized using model::serializeId(), labels are formatted
	 * using model::formatLabel().
	 *
	 *     itemID => itemLabel
	 *
	 * By providing additional properties of model to fetch in $properties
	 * (format in compliance with array expected by model::listItemProperties())
	 * the result is adjusted to map serialized ID into array consisting of
	 * related item's label and values of additionally fetched properties.
	 *
	 *     itemID => ( "label" => itemLabel, "data" => ( prop => value, ... ) )
	 *
	 * @note This method is aliasing properties involved in an item's ID and/or
	 *       label to ensure extraction of ID/label properties in proper sorting
	 *       order from every matching record while enabling retrieval of
	 *       additional properties (including those ID/label properties).
	 *       Aliases use special names: properties of ID start with minor i,
	 *       properties of label start with minor l. In both cases 0-based index
	 *       of ID/label property is appended, so first property of ID is
	 *       aliased as "i0", while first property of label is aliased as "l0".
	 *       *You can't fetch actual properties of a model here in case of those
	 *       properties are matching this naming for aliases.*
	 *
	 * @param datasource\query $query query to use instead of model's default query
	 * @param datasource\connection $source datasource to use on implicitly
	 *        calling model::browse() if $query is omitted
	 * @param array|null $properties optional set of properties' names to fetch
	 *        additionally, provide empty array to get empty set of "data" per
	 *        match, see note
	 * @param string|array $filterTerm optional filter term to apply on listing query using addFilter() or list of property names each required to match values in $filterValue
	 * @param array $filterValues optional set of values to associate with filter term
	 * @return array set of entries mapping items' serialized ID into their label
	 */

	public static function listItemLabels( datasource\query $query = null, datasource\connection $source = null, $properties = null, $filterTerm = null, $filterValues = array() )
	{
		// compile aliased sets of properties required to identify and label items
		$idProperties = $labelProperties = array();

		foreach ( array_values( static::$id ) as $index => $name )
			$idProperties['i' . $index] = $name;

		foreach ( array_values( static::$label ) as $index => $name )
			$labelProperties['l' . $index] = $name;


		$wantMoreProperties = is_array( $properties );
		if ( !$wantMoreProperties )
			$properties = array();

		// query datasource for items
		$query = static::listItemProperties( $query, $source, array_merge( $idProperties, $labelProperties, $properties ) );

		if ( is_string( $filterTerm ) && trim( $filterTerm ) !== '' )
			$query->addFilter( $filterTerm, true, (array) $filterValues );
		else if ( is_array( $filterTerm ) && is_array( $filterValues ) && count( $filterTerm ) === count( $filterValues ) ) {
			// this case is used by processing model relations
			// (see model_relation_model::listItems() for more)
			foreach ( $filterTerm as $name ) {
				$query->addFilter( $name . '=?', true, array_shift( $filterValues ) );
			}
		} else if ( $filterTerm )
			throw new \InvalidArgumentException( 'invalid kind of filter term' );

		$matches = $query->execute();


		/*
		 * extract list of items mapping each item's serialized ID into label
		 */

		$list = array();

		while ( $match = $matches->row() ) {
			// split match into properties of ID and properties of label
			$id = $label = array();

			foreach ( $idProperties as $alias => $name ) {
				$id[$name] = $match[$alias];
				unset( $match[$alias] );
			}

			foreach ( $labelProperties as $alias => $name ) {
				$label[$name] = $match[$alias];
				unset( $match[$alias] );
			}

			// serialize item's ID
			$id = static::serializeId( $id );

			// format item's label
			$label = static::formatLabel( $label );

			// add item to result ...
			if ( count( $match ) || $wantMoreProperties )
				// ... enriched by additionally fetched properties
				$list[$id] = array( 'label' => $label, 'data' => $match );
			else
				// ... mapping serialized ID into label
				$list[$id] = $label;
		}


		return $list;
	}

	/**
	 * Retrieves selector element for use in a model editor for choosing instance
	 * of current model.
	 *
	 * @param datasource\query $query
	 * @param datasource\connection $source datasource to use on implicitly calling model::browse() if $query is omitted
	 * @param \ReflectionClass $elementClass class of model_editor_element to return
	 * @return model_editor_selector
	 */

	public static function selector( datasource\query $query = null, datasource\connection $source = null, \ReflectionClass $elementClass = null )
	{
		$options = static::listItemLabels( $query, $source );

		/*
		 * create control for use in model_editor
		 */

		// prefer using optionally provided class
		if ( $elementClass && $elementClass->isSubclassOf( 'de\toxa\txf\model_editor_selector' ) )
			return $elementClass->getMethod( 'create' )->invoke( null, $options );

		// but use model_editor_selector by default
		return model_editor_selector::create( $options );
	}
}

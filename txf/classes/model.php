<?php


namespace de\toxa\txf;

use \de\toxa\txf\datasource\connection as db;
use \de\toxa\txf\datasource\datasource_exception;


class model
{
	/**
	 *
	 * @var db
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



	protected function __construct( db $source = null, $itemId )
	{
		if ( $source === null )
			$source = datasource::selectConfigured( 'default' );

		$this->_source = $source;

		$this->bind( $itemId );
	}

	/**
	 * Provides model instances prepared for accessing item by ID.
	 *
	 * @param db $source link to datasource to use instead of current one
	 * @param array|integer $itemId ID of item to be managed by instance
	 * @return model instance of model prepared to manage selected item
	 * @throws \InvalidArgumentException on providing invalid item ID
	 * @throws datasource_exception on missing selected item in datasource
	 */

	public static function select( db $source = null, $itemId )
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
	 * @param db $source data source to look for matching item in
	 * @param array $properties set of model's properties mapping into values to match
	 * @return model|null matching model item, null on mismatch
	 * @throws \InvalidArgumentException
	 */

	public static function find( db $source = null, $properties )
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
	 * @param db $source link to datasource to use instead of default
	 * @return model
	 */

	public static function proxy( db $source = null )
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
	 * @param integer $dimension 0-based index of dimension to retrieve
	 * @param integer $maxDimension
	 * @return string name of property included in model's IDs
	 * @throws \LogicException if maximum dimension is exceeding size of model's IDs
	 * @throws \OutOfRangeException if requested dimension is exceeding size of model's ID
	 */

	public static function idName( $dimension = 0, $maxDimension = 1 )
	{
		if ( $maxDimension > count( static::$id ) )
			throw new \LogicException( _L('Model isn\'t supporting IDs with requested dimensionality.') );

		if ( $dimension < 0 || $dimension >= count( static::$id ) )
			throw new \OutOfRangeException( _L('Model\'s ID is missing requested dimension.') );

		return static::$id[intval( $dimension )];
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

			$query = $this->_source->createQuery( $this->set() );

			foreach ( $this->_id as $name => $value )
				$query->addCondition( $name . '=?', true, $value );

			$matches = $query->limit( 2 )->execute();

			if ( $matches->count() !== 1 )
				throw new datasource_exception( $this->_source, 'item missing in datasource' );

			$this->_record = $matches->row();
		}

		return $this->_record;
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
	 * @param array|scalar $itemId set of identifying properties and values or single identifying property's value
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
	 * @param scalar|array $id ID to normalize
	 * @param boolean $reduceToIdProperties true to drop all properties in a provided record not contained in model's IDs
	 * @param boolean $mayBeEmpty true to return empty array if $id is unset
	 * @return array normalized ID consisting of all required properties used to select item
	 * @throws \InvalidArgumentException
	 */

	public static function normalizeId( $id, $reduceToIdProperties = false, $mayBeEmpty = false )
	{
		if ( !is_array( $id ) )
		{
			if ( $id === null && $mayBeEmpty )
				return array();

			if ( count( static::$id ) !== 1 )
				throw new \InvalidArgumentException( 'malformed model item id' );

			return array( static::$id[0] => $id );
		}

		if ( !count( $id ) && $mayBeEmpty )
			return array();


		if ( $reduceToIdProperties )
			foreach ( $id as $name => $value )
				if ( !in_array( $name, static::$id ) )
					unset( $id[$name] );

		if ( count( $id ) !== count( static::$id ) )
			throw new \InvalidArgumentException( 'malformed model item id' );

		foreach ( static::$id as $name )
			if ( !array_key_exists( $name, $id ) )
				throw new \InvalidArgumentException( 'malformed model item id' );

		return $id;
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
			if ( !$this->isPublic( $property, $value, $record ) )
				unset( $record[$property] );

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

	public function __get( $name )
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( array_key_exists( $name, $this->_id ) )
			return $this->_id[$name];

		$record = $this->load();

		if ( array_key_exists( $name, $record ) )
			return $record[$name];

		throw new \InvalidArgumentException( "unknown property: " . $this->set() . ".$name" );
	}

	public function __set( $name, $value )
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( array_key_exists( $name, $this->_id ) )
			throw new \RuntimeException( "invalid call for changing item id" );

		$record = $this->load();

		if ( !array_key_exists( $name, $record ) )
			throw new \InvalidArgumentException( "unknown property: " . $this->set() . ".$name" );


		$link = $this->_source;

		$filter = array_keys( $this->_id );
		$values = array_values( $this->_id );

		array_unshift( $values, $value );

		$qSet   = $link->quoteName( $this->set() );
		$qName  = $link->quoteName( $name );
		$filter = array_map( function( $col ) use ( $link ) { return $link->quoteName( $col ) . "=?"; }, $filter );

		if ( !$link->test( "UPDATE $qSet SET $qName=? WHERE " . implode( ' AND ', $filter ), $values ) )
			throw new datasource_exception( $link, 'failed to update property in datasource: ' . $this->set() . ".$name" );


		return ( $this->_record[$name] = $value );
	}

	/**
	 * Retrieves name of current model's set in datasource.
	 *
	 * @return string
	 */

	public function set()
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

	protected static function define()
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
	 * @param db $source datasource for managing model instance
	 * @param array $properties set of model-specific properties
	 * @return model
	 * @throws \InvalidArgumentException
	 * @throws datasource_exception
	 */

	public static function create( db $source, $properties = array() )
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
	 * Updates schema of selected data source to contain definition for current
	 * model's set.
	 *
	 * @param db $source data source to use
	 * @throws datasource_exception on updating schema failed
	 * @throws model_exception on incomplete/invalid schema definition
	 */

	protected static function updateSchema( db $source )
	{
		$dataset = static::$set_prefix . static::$set;

		if ( !$source->exists( $dataset ) )
		{
			$definition = static::define();

			foreach ( static::$id as $property )
				if ( !array_key_exists( $property, $definition ) && $property !== 'id' )
					throw new model_exception( 'missing ID property in schema definition' );

			if ( !$source->createDataset( $dataset, $definition, static::$id ) )
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
	 * @param db $link link to datasource
	 * @param array $arrProperties properties of new instance to write into datasource
	 * @return array ID-components of created instance (to be used on loading this instance by model::select())
	 * @throws datasource_exception
	 * @throws model_exception on missing parts of multidimensional ID
	 */

	public static function _onCreate( db $link, $arrProperties )
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
		$qSet    = $link->quoteName( $set );

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
		$relations = static::$relations;

		if ( !$this->_source->transaction()->wrap( function( db $connection ) use ( $item, $relations )
		{
			$id = array_values( $item->id() );

			if ( $connection->test( sprintf( 'DELETE FROM %s WHERE %s', $item->set(), $item->filter() ), $id ) === false )
				throw new datasource_exception( $connection, 'failed to delete requested model instance' );

			// drop all items in immediate and tight relation to deleted one
			foreach ( $relations as $relation )
			{
				$hasVia    = is_array( @$relation['via'] );
				$neighbour = $hasVia ? array_shift( $relation['via'] ) : is_array( @$relation['from'] ) ? $relation['from'] : null;

				if ( $neighbour && @$neighbour['tight'] )
				{
					// exclude "tight" neighbours in a via-relation not referencing to currently deleted model but vice versa
					if ( !$hasVia || $neighbour['referencing_from'] )
					{
						$relation = model_relation::createFrom( model::normalizeProvidedModel( @$neighbour['model'] ), @$neighbour['referencing'] );

						if ( $connection->test( sprintf( 'DELETE FROM %s WHERE %s', $relation->relatingEnd( true )->set(), $relation->referencingFilter( null, false ) ), $id ) === false )
							throw new datasource_exception( $connection, 'failed to delete requested model instance' );
					}
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
	 * @return db
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

		return $this->_source->createQuery( $this->set() . ( $alias != '' ? " $alias" : '' ) );
	}

	/**
	 * Creates relation instance between current model and further ones.
	 *
	 * The resulting relation is prepared to have foreign items selecting
	 * current model's items by giving reference value matching values of
	 * this model's property selected in $referencedProperty. Omit that for
	 * using current model's ID property by default.
	 *
	 * @example
	 *
	 *   $relation = $this->relating( 'id' );
	 *
	 * $relation is then prepared for
	 *
	 *   foreign.someProp == this.id
	 *
	 * @param string $referencedProperty name of current model's property referenced externally
	 * @return model_relation
	 */

	public function relating( $referencedProperty = null )
	{
		return model_relation::createFrom( $this, $referencedProperty );
	}

	public static function normalizeProvidedModel( $model )
	{
		if ( !( $model instanceof self ) )
		{
			$model = trim( $model );

			if ( $model[0] != '\\' )
				$model = __NAMESPACE__ . '\\' . $model;

			$model = new \ReflectionClass( $model );
			if ( !$model->isSubclassOf( __NAMESPACE__ . '\model' ) )
				throw new \InvalidArgumentException( 'no such model' );

			$model = $model->getMethod( 'proxy' )->invoke( null );
		}

		return $model;
	}

	/**
	 * Retrieves relation according to declaration selected by given name.
	 *
	 * @param string $name name of model's relation to retrieve
	 * @return model_relation
	 */

	public function relation( $name )
	{
		$name = data::isKeyword( $name );
		if ( !$name )
			throw new \InvalidArgumentException( 'invalid relation name' );

		if ( !array_key_exists( $name, static::$relations ) )
			throw new \InvalidArgumentException( sprintf( 'no such relation: %s', $name ) );


		$specs = static::$relations[$name];

		if ( !( @$specs['to'] ^ @$specs['from'] ) )
			throw new \RuntimeException( 'missing/ambigious declaration on target of relation' );


		// create new relation using current or provided model as endpoint
		if ( @$specs['from'] )
			$relation = model_relation::createFrom( self::normalizeProvidedModel( $specs['from']['model'] ), @$specs['from']['referencing'] );
		else if ( @$specs['to']['referencing'] )
			$relation = model_relation::createFrom( $this, @$specs['to']['referencing'] );
		else
			$relation = model_relation::createFrom( $this, @$specs['referencing'] );


		// add all declared waypoints
		if ( is_array( $specs['via'] ) )
			foreach ( $specs['via'] as $waypoint )
			{
				if ( @$waypoint['referencing_from'] && @$waypoint['referencing_to'] )
					$relation->via( self::normalizeProvidedModel( $waypoint['model'] ), @$waypoint['referencing_from'], @$waypoint['referencing_to'], @$waypoint['alias'], true );
				else
					$relation->via( self::normalizeProvidedModel( $waypoint['model'] ), @$waypoint['referenced'], @$waypoint['referencing'], @$waypoint['alias'], false );

				if ( is_array( @$waypoint['filter'] ) )
					foreach ( $waypoint['filter'] as $condition => $parameters )
						$relation->on( $condition, is_array( $parameters ) ? $parameters : is_null( $parameters ) ? array() : array( $parameters ) );
			}


		// add opposite endpoint
		$oppositeModel = @$specs['to'] ? self::normalizeProvidedModel( $specs['to']['model'] ) : $this;
		$opposite      = @$specs['to'] ? @$specs['to'] : $specs;

		$relation->to( $oppositeModel, $opposite['referenced'] );

		if ( is_array( @$opposite['filter'] ) )
			foreach ( $opposite['filter'] as $condition => $parameters )
				$relation->on( $condition, is_array( $parameters ) ? $parameters : is_null( $parameters ) ? array() : array( $parameters ) );


		if ( is_array( $specs['properties'] ) )
			foreach ( $specs['properties'] as $property => $alias )
			{
				if ( is_array( $alias ) )
				{
					$waypoint      = $alias['waypoint'];
					$waypointAlias = $alias['waypointAlias'];

					if ( array_key_exists( 'property', $alias ) )
						$property = $alias['property'];

					$alias = $alias['alias'];
				}
				else
					$waypoint = $waypointAlias = null;

				if ( ctype_digit( trim( $property ) ) )
				{
					$property = $alias;
					$alias    = null;
				}

				$relation->showing( $property, $alias, $waypoint, $waypointAlias );
			}


		if ( is_string( @$specs['sorting'] ) )
			$relation->sortedBy( $specs['sorting'] );
		else if ( is_array( @$specs['sorting'] ) )
			foreach ( $specs['sorting'] as $name => $asc )
			{
				if ( is_integer( $name ) && is_string( $asc ) )
					$relation->sortedBy( $asc );
				else
					$relation->sortedBy( $name, $asc );
			}


		return $relation;
	}

	/**
	 *
	 * @param db $source
	 * @return datasource\query browseable query on current model
	 */

	public static function browse( db $source = null )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		if ( !( $source instanceof db ) )
			throw new \InvalidArgumentException( _L('missing link to datasource') );

		static::updateSchema( $source );

		return $source->createQuery( static::$set_prefix . static::$set );
	}

	/**
	 * Retrieves selector element for use in a model editor for choosing instance
	 * of current model.
	 *
	 * @param datasource\query $query
	 * @param db $source datasource to use on implicitly calling model::browse() if $query is omitted
	 * @param \ReflectionClass $elementClass class of model_editor_element to return
	 * @return model_editor_selector
	 */

	public static function selector( datasource\query $query = null, db $source = null, \ReflectionClass $elementClass = null )
	{
		if ( $query === null )
			$query = static::browse( $source !== null ? $source : datasource::getDefault() );


		// extend query to fetch current set's properties contained in ID and label using common aliases for proper extracting
		$ids = $labels = array();

		foreach ( static::$id as $index => $name )
		{
			$ids[] = '_id' . $index;
			$query->addProperty( static::$set_prefix . static::$set . '.' . $name, '_id' . $index );
		}

		foreach ( static::$label as $index => $name )
		{
			$labels[] = '_label' . $index;
			$query->addProperty( static::$set_prefix . static::$set . '.' . $name, '_label' . $index );
		}


		// actually query database for matches
		$matches = $query->execute();


		// prepare code for glueing IDs and label elements together
		$iGlue = static::$id_glue;
		$lGlue = static::$label_glue;

		$iGlue = is_callable( $iGlue ) ? $iGlue : function( $v ) use ( $iGlue ) { return implode( $iGlue, $v ); };
		$lGlue = is_callable( $lGlue ) ? $lGlue : function( $v ) use ( $lGlue ) { return implode( $lGlue, $v ); };


		// combine selectors and labels of all matches into single associative array
		$options = array();

		while ( $match = $matches->row() )
		{
			$id = $label = array();

			foreach ( $ids as $name ) $id[] = $match[$name];
			foreach ( $labels as $name ) $label[] = $match[$name];

			$options[call_user_func( $iGlue, $id )] = call_user_func( $lGlue, $label );
		}


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

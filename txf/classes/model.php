<?php


namespace de\toxa\txf;

use \de\toxa\txf\datasource\connection as db;


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



	protected function __construct( db $source = null, $itemId )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		$this->_source = $source;

		$this->bind( $itemId );
	}

	/**
	 * Provides model instances prepared for accessing item by ID.
	 *
	 * @param datasource\connection $source link to datasource to use instead of current one
	 * @param array|integer $itemId ID of item to be managed by instance
	 * @return model instance of model prepared to manage selected item
	 */

	public static function select( db $source = null, $itemId )
	{
		return new static( $source, $itemId );
	}

	/**
	 * Creates instance of current model not bound to any item.
	 *
	 * This is useful for accessing particular meta-information on current model
	 * without requiring to choose any of its existing items.
	 *
	 * @param datasource\connection $source link to datasource to use instead of default
	 * @return model
	 */

	public static function proxy( db $source = null )
	{
		return new static( $source, 0 );
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

	public function idName( $dimension = 0, $maxDimension = 1 )
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
	 */

	public function load()
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( !is_array( $this->_record ) )
		{
			$filter = implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
			$vals   = array_values( $this->_id );

			$this->_record = $this->_source->row( "SELECT * FROM " . $this->set() . " WHERE $filter", $vals );
			if ( !is_array( $this->_record ) )
				throw new datasource\datasource_exception( $this->_source, 'item missing in datasource' );
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

		$idName = $this->idName();
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


		$filter = implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
		$vals   = array_values( $this->_id );

		array_unshift( $vals, $value );

		if ( !$this->_source->test( "UPDATE " . $this->set() . " SET $name=? WHERE $filter", $vals ) )
			throw new \de\toxa\txf\datasource\datasource_exception( $this->_source, 'failed to update property in datasource: ' . $this->set() . ".$name" );

		return ( $record[$name] = $value );
	}

	/**
	 * Retrieves name of current model's set in datasource.
	 *
	 * @return string
	 */

	public function set()
	{
		return static::$set_prefix.static::$set;
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
	 * Creates new instance of current model described by provided properties
	 * to be managed in selected (or default) datasource.
	 *
	 * @param \de\toxa\txf\datasource\connection $source datasource for managing model instance
	 * @param array $properties set of model-specific properties
	 * @return model
	 * @throws \InvalidArgumentException
	 * @throws datasource\datasource_exception
	 */

	public static function create( db $source, $properties = array() )
	{
		static::validateIdsOnCreate( $properties );

		$set      = static::$set_prefix . static::$set;
		$callback = new \ReflectionMethod( get_called_class() . "::_onCreate" );

		if ( !$source->transaction()->wrap( function( $link ) use ( $callback, $properties )
		{
			$item = $callback->invoke( null, $link, $properties );

			return true;
		}, "createModel." . $set ) )
			throw new datasource\datasource_exception( $source, 'failed to commit creation of item in datasource, model ' . $set );

		return new static( $source, $item );
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
	 * The method is considered to return array consisiting of all properties
	 * and related values used to identify created instances of current model.
	 *
	 * The method must be defined public for internal use in a closure. By design
	 * it should be considered protected.
	 *
	 * @param \de\toxa\txf\datasource\connection $link link to datasource
	 * @param array $arrProperties properties of new instance to write into datasource
	 * @return array ID-components of created instance (to be used on loading this instance by model::select())
	 * @throws datasource\datasource_exception
	 */

	public static function _onCreate( db $link, $arrProperties )
	{
		$columns = implode( ',', array_keys( $arrProperties ) );
		$marks   = implode( ',', array_pad( array(), count( $arrProperties ), '?' ) );

		$values  = array_values( $arrProperties );

		$set = static::$set_prefix . static::$set;

		if ( count( static::$id ) == 1 )
		{
			$idName = array_values( static::$id );
			$idName = $idName[0];

			array_unshift( $values, $link->nextID( $set ) );

			if ( $link->test( "INSERT INTO $set ($idName,$columns) VALUES (?,$marks)", $values ) === false )
				throw new datasource\datasource_exception( $link, 'failed to create item in datasource, model ' . $set );

			$item = array( $idName => $values[0] );
		}
		else
		{
			if ( $link->test( "INSERT INTO $set ($columns) VALUES ($marks)", $values ) === false )
				throw new datasource\datasource_exception( $link, 'failed to create item in datasource, model ' . $set );

			$item = array();
			foreach ( static::$id as $name )
				$item[$name] = $arrProperties[$name];
		}

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
	 * @throws datasource\datasource_exception
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

		if ( !$this->_source->transaction()->wrap( function( datasource\connection $connection ) use ( $item, $relations )
		{
			$id = array_values( $item->id() );

			if ( $connection->test( sprintf( 'DELETE FROM %s WHERE %s', $item->set(), $item->filter() ), $id ) === false )
				throw new datasource\datasource_exception( $connection, 'failed to delete requested model instance' );

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
							throw new datasource\datasource_exception( $connection, 'failed to delete requested model instance' );
					}
				}
			}

			return true;
		} ) )
			throw new datasource\datasource_exception( $this->_source, 'failed to completely delete item and its tightly bound relations' );


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

	protected static function nameToLabel( $name )
	{
		return $name;
	}

	public function formatter( $requestCellFormatter = true )
	{
		$class = get_class( $this );

		return array( $class, $requestCellFormatter ? 'formatCell' : 'formatHeader' );
	}

	/**
	 *
	 * @return datasource\connection
	 */

	public function source()
	{
		return $this->_source;
	}

	/**
	 *
	 * @return datasource\query customizable query on current model
	 */

	public function query( $customBaseDataset = null )
	{
		return $this->_source->createQuery( $customBaseDataset ? $customBaseDataset : $this->set() );
	}

	/**
	 *
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
	 * @param datasource\connection $source
	 * @return datasource\query browseable query on current model
	 */

	public static function browse( db $source = null )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		if ( !( $source instanceof datasource\connection ) )
			throw new \InvalidArgumentException( _L('missing link to datasource') );

		return $source->createQuery( static::$set_prefix.static::$set );
	}

	/**
	 * Retrieves selector element for use in a model editor for choosing instance
	 * of current model.
	 *
	 * @param datasource\query $query
	 * @return model_editor_selector
	 */

	public static function selector( datasource\query $query = null, datasource\connection $source = null )
	{
		if ( $query === null )
			$query = static::browse( $source !== null ? $source : datasource::getDefault() );


		// extend query to fetch current set's properties contained in ID and label using common aliases for proper extracting
		$ids = $labels = array();

		foreach ( static::$id as $index => $name )
		{
			$ids[] = '_id' . $index;
			$query->addProperty( static::$set_prefix.static::$set . '.' . $name, '_id' . $index );
		}

		foreach ( static::$label as $index => $name )
		{
			$labels[] = '_label' . $index;
			$query->addProperty( static::$set_prefix.static::$set . '.' . $name, '_label' . $index );
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


		return new model_editor_selector( $options );
	}
}

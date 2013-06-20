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



	protected function __construct( db $source = null, $itemId )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		$this->_source = $source;

		if ( is_array( $itemId ) )
			$this->_id = $itemId;
		else if ( ctype_digit( trim( $itemId ) ) )
			$this->_id = array( $this->idName() => $itemId );
		else
			throw new \InvalidArgumentException( 'malformed model item id' );

		foreach ( static::$id as $name )
			if ( !array_key_exists( $name, $this->_id ) )
				throw new \InvalidArgumentException( 'malformed model item id' );
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

	protected function idName( $dimension = 0, $maxDimension = 1 )
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

	protected function idSize()
	{
		return count( static::$id );
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

			$this->_record = $this->_source->row( "SELECT * FROM " . static::$set . " WHERE $filter", $vals );
		}

		return $this->_record;
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

		throw new \InvalidArgumentException( "unknown property: " . static::$set . ".$name" );
	}

	public function __set( $name, $value )
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		if ( array_key_exists( $name, $this->_id ) )
			throw new \RuntimeException( "invalid call for changing item id" );

		$record = $this->load();

		if ( !array_key_exists( $name, $record ) )
			throw new \InvalidArgumentException( "unknown property: " . static::$set . ".$name" );


		$filter = implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
		$vals   = array_values( $this->_id );

		array_unshift( $vals, $value );

		if ( !$this->_source->test( "UPDATE " . static::$set . " SET $name=? WHERE $filter", $vals ) )
			throw new \de\toxa\txf\datasource\datasource_exception( $this->_source, 'failed to update property in datasource: ' . static::$set . ".$name" );

		return ( $record[$name] = $value );
	}

	public function set()
	{
		return static::$set;
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

	public static function create( db $source, $properties = array() )
	{
		$set    = static::$set;
		$item   = null;
		$idName = $this->idName();

		if ( !$source->transaction()->wrap( function( $link ) use ( $properties, $set, &$item, $idName )
		{
			$columns = implode( ',', array_keys( $properties ) );
			$marks   = implode( ',', array_pad( array(), count( $properties ), '?' ) );

			$values  = array_values( $properties );
			array_unshift( $values, $link->nextID( $set ) );

			if ( !$link->test( "INSERT INTO $set ($idName,$columns) VALUES (?,$marks)", $values ) )
				throw new \de\toxa\txf\datasource\datasource_exception( $link, 'failed to create item in datasource, model ' . $set );

			$item = array( $idName => $values[0] );

			return true;
		}, "createModel.$set" ) )
			throw new \de\toxa\txf\datasource\datasource_exception( $source, 'failed to commit creation of item in datasource, model ' . $set );

		return new static( $source, $item );
	}

	public function delete()
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		$filter = implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
		$vals   = array_values( $this->_id );

		$result = $this->_source->test( 'DELETE FROM ' . static::$set . ' WHERE ' . $filter, $vals );

		if ( $result )
			// drop ID to mark item has gone
			$this->_id = $this->_record = null;

		return $result;
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

	public function query( $customBaseDataset )
	{
		return $this->_source->createQuery( $customBaseDataset ? $customBaseDataset : static::$set );
	}

	/**
	 *
	 * @return model_relation
	 */

	public function relation( $referencedProperty = null )
	{
		return new model_relation( $this->_source, $referencedProperty );
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

		return $source->createQuery( static::$set );
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

		$ids = $labels = array();

		foreach ( static::$id as $index => $name )
		{
			$ids[] = '_id' . $index;
			$query->addProperty( static::$set . '.' . $name, '_id' . $index );
		}

		foreach ( static::$label as $index => $name )
		{
			$labels[] = '_label' . $index;
			$query->addProperty( static::$set . '.' . $name, '_label' . $index );
		}

		$matches = $query->execute();
		$options = array();

		while ( $match = $matches->row() )
		{
			$id = $label = array();

			foreach ( $ids as $name ) $id[] = $match[$name];
			foreach ( $labels as $name ) $label[] = $match[$name];

			$options[implode( static::$id_glue, $id )] = implode( static::$label_glue, $label );
		}

		return new model_editor_selector( $options );
	}
}

<?php


namespace de\toxa\txf;

use \de\toxa\txf\datasource\connection as db;


class model
{
	/**
	 *
	 * @var \de\toxa\txf\datasource\connection
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



	protected function __construct( db $source, $itemId )
	{
		$this->_source = $source;

		if ( ctype_digit( trim( $itemId ) ) )
			$this->_id = array( 'id' => $itemId );
		else if ( is_array( $itemId ) )
			$this->_id = $itemId;
		else
			throw new \InvalidArgumentException( 'malformed model item id' );
	}

	public static function select( db $source, $itemId )
	{
		return new static( $source, $itemId );
	}

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

		if ( array_key_exists( 'id', $record ) )
			return sprintf( _L('#%d of model %s'), $record['id'], get_class( $this ) );

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
		$set  = static::$set;
		$item = null;

		if ( !$source->transaction()->wrap( function( $link ) use ( $properties, $set, &$item )
		{
			$columns = implode( ',', array_keys( $properties ) );
			$marks   = implode( ',', array_pad( array(), count( $properties ), '?' ) );

			$values  = array_keys( $properties );
			array_unshift( $values, $link->nextID( $set ) );

			if ( !$link->test( "INSERT INTO $set (id,$columns) VALUES (?,$marks)", $values ) )
				throw new \de\toxa\txf\datasource\datasource_exception( 'failed to create item in datasource, model ' . $set );

			$item = new static( $link, array( 'id' => $values[0] ) );
		}, "createModel.$set" ) )
			throw new \de\toxa\txf\datasource\datasource_exception( 'failed to commit creation of item in datasource, model ' . $set );

		return $item;
	}

	public function delete()
	{
		if ( !is_array( $this->_id ) )
			throw new \RuntimeException( 'item does not exist (anymore)' );

		$filter = implode( ' AND ', array_map( function( $col ) { return "$col=?"; }, array_keys( $this->_id ) ) );
		$vals   = array_values( $this->_id );

		$result = $this->_source->test( "DELETE FROM " . static::$set . ' WHERE ' . $filter, $vals );

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
	 * @return \de\toxa\txf\datasource\connection
	 */

	public function source()
	{
		return $this->_source;
	}

	/**
	 *
	 * @return \de\toxa\txf\datasource\query customizable query on current model
	 */

	public function createCustomQuery( $customBaseDataset )
	{
		return $this->_source->createQuery( $customBaseDataset ? $customBaseDataset : static::$set );
	}

	/**
	 *
	 * @param \de\toxa\txf\datasource\connection $source
	 * @return \de\toxa\txf\datasource\query browseable query on current model
	 */

	public static function browse( db $source )
	{
		return $source->createQuery( static::$set );
	}
}

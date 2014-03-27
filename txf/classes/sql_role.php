<?php


namespace de\toxa\txf;

use \de\toxa\txf\datasource\connection as db;


/**
 * Description of role
 *
 * @author Thomas Urban
 */

class sql_role implements role
{
	/**
	 * datasource backing roles management
	 *
	 * @var db
	 */

	protected $_source;

	/**
	 * ID of currently managed role
	 *
	 * @var integer
	 */

	protected $_id;

	/**
	 * properties of currently managed role
	 *
	 * @var array
	 */

	protected $_record = array();

	/**
	 * semaphore used to implicitly "validate" datasource provided on first
	 * call for managing roles
	 *
	 * @var boolean
	 */

	private static $validated = false;



	public function __construct( db $source = null, $role )
	{
		$this->_source = static::validateDatasource( $source, false );


		if ( ctype_digit( trim( $role ) ) )
			$property = 'id';
		else if ( is_string( $role ) && trim( $role ) !== '' )
			$property = 'name';
		else
			throw new \InvalidArgumentException( 'invalid role name or ID' );


		$this->load( $property, trim( $role ) );
	}

	/**
	 * Ensures provided datasource is containing datasets required for backing
	 * roles management.
	 *
	 * @param \de\toxa\txf\datasource\connection $source
	 * @param boolean $force true to force validating provided source
	 * @throws \RuntimeException
	 */

	public static function validateDatasource( db $source = null, $force = true )
	{
		if ( $source === null )
			$source = datasource::getDefault();

		if ( !self::$validated )
		{
			if ( !$source->exists( 'role' ) )
				if ( false === $source->createDataset( 'role', array(
								'name'  => 'CHAR(32) NOT NULL UNIQUE',
								'label' => 'CHAR(64) NOT NULL',
								) ) )
					throw new \RuntimeException( 'failed to prepare role set' );

			if ( !$source->exists( 'user_role' ) )
				if ( false === $source->createDataset( 'user_role', array(
						'id'          => null,
						'user_id'     => 'INT UNSIGNED NOT NULL',
						'role_id'     => 'INT UNSIGNED NOT NULL',
						), array( 'user_id', 'role_id' ) ) )
					throw new \RuntimeException( 'failed to prepare role/user mapping set' );

			self::$validated = true;
		}

		return $source;
	}

	/**
	 * Creates role manager instance on selected role to be managed in provided
	 * datasource.
	 *
	 * @param \de\toxa\txf\datasource\connection $source backing datasource, omit for current one
	 * @param string|role $role role to manage
	 * @return role
	 */

	public static function select( db $source = null, $role )
	{
		if ( $role instanceof self )
			return $role;

		return new static( $source, $role );
	}

	/**
	 * Gets ID of role matching provided property's value.
	 *
	 * Permitted values are "id" and "name".
	 *
	 * @param string $property property of role to search for record matching value
	 * @param string $role value of property to match
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */

	protected function load( $property, $role )
	{
		if ( !in_array( $property, array( 'id', 'name' ) ) )
			throw new \InvalidArgumentException( 'invalid property to select role' );

		$record = null;

		if ( !$this->_source->transaction()->wrap( function( $db ) use ( $property, $role, &$record )
		{
			$existing = $db->row( sprintf( 'SELECT * FROM role WHERE %s=?', $property ), $role );
			if ( $existing )
			{
				$record = $existing;
				return true;
			}

			$nextID = $db->nextID( 'role' );

			if ( $property === 'name' && $db->test( 'INSERT INTO role (id,name,label) VALUES (?,?,?)', $nextID, $role, $role ) !== false )
			{
				$record = array( 'id' => $nextID, 'name' => $role, 'label' => $role );
				return true;
			}

			return false;
		} ) )
			throw new \RuntimeException( sprintf( 'no such role with %s "%s"', $property, $role ) );


		$this->_record = $record;
		$this->_id     = intval( $record['id'] );
	}

	/**
	 * Tests if provided user is adopting current role.
	 *
	 * @param \de\toxa\txf\user $user user to check for adopting current role
	 * @return boolean true on user is adopting role
	 * @throws \LogicException
	 */

	public function isAdoptedByUser( user $user )
	{
		if ( !$this->_id )
			throw new \LogicException( 'unprepared role instance' );

		$count = $this->_source->createQuery( 'user_role' )
									->addCondition( 'user_id=?', true, $user->getID() )
									->addCondition( 'role_id=?', true, $this->_id )
									->execute( true )->cell();

		return $count > 0;
	}

	public function __get( $property )
	{
		if ( !$this->_id )
			throw new \LogicException( 'unprepared role instance' );

		return @$this->_record[$property];
	}
}

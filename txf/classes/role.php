<?php


namespace de\toxa\txf;

use \de\toxa\txf\datasource\connection as db;


/**
 * Role Management API
 *
 * @author Thomas Urban
 */

interface role
{
	/**
	 * Creates role manager instance on selected role to be managed in provided
	 * datasource.
	 *
	 * @param \de\toxa\txf\datasource\connection $source backing datasource, omit for current one
	 * @param string|role $role role to manage
	 * @return role
	 */

	public static function select( db $source = null, $role );

	/**
	 * Tests if provided user is adopting current role.
	 *
	 * @param \de\toxa\txf\user $user user to check for adopting current role
	 * @return boolean true on user is adopting role
	 * @throws \LogicException
	 */

	public function isAdoptedByUser( user $user );

	public function __get( $property );
}

<?php


namespace de\toxa\txf\datasource;


interface connection
{
	/**
	 * @return transaction
	 */

	public function transaction();

	/**
	 * @return statement
	 */

	public function compile( $query );

	/**
	 * return @boolean
	 */

	public function test( $query );

	/**
	 * @return boolean
	 */

	public function exists( $dataset );

	/**
	 * @return boolean
	 */

	public function createDataset( $name, $definition );

	/**
	 * @return query
	 */

	public function createQuery( $dataset );

	/**
	 * @return integer
	 */

	public function nextID( $dataset );

	/**
	 * @return string
	 */

	public function errorText();

	/**
	 * @return string
	 */

	public function errorCode();
}

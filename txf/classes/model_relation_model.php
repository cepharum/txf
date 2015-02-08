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


use de\toxa\txf\datasource\datasource_exception;

/**
 * Wraps access on model of a relation's node to transparently support nodes
 * with virtual model.
 *
 * This class is designed to provide all information on and functionality of a
 * model involved in a relation as used in processing that relation. For relations
 * might include "virtual models" (models that don't exist in code explicitly,
 * but are managed implicitly as part of a relation) this class is hiding the
 * different behaviour and opportunities between either kind of model.
 *
 * @package de\toxa\txf
 */

class model_relation_model
{
	protected static $declaredCached = array();

	/**
	 * reflection of actually existing model to use
	 *
	 * @note Virtual models don't have reflection.
	 *
	 * @var \ReflectionClass
	 */

	protected $reflection = null;

	/**
	 * Names a virtual model's set in data source.
	 *
	 * @var string
	 */

	protected $set = null;

	/**
	 * Maps names of all properties of a virtual model into their related type
	 * definition (to be used on creating set in data source on demand).
	 *
	 * @var array
	 */

	protected $definition = null;

	/**
	 * Lists names of properties of a virtual model used to identify instances.
	 *
	 * @var array
	 */

	protected $primaries = null;



	protected function __construct()
	{
	}

	/**
	 * Creates proxy for actually declared model.
	 *
	 * @param \ReflectionClass $model reflection of model
	 * @return model_relation_model
	 */

	public static function createOnModel( \ReflectionClass $model )
	{
		if ( !$model->isSubclassOf( 'de\toxa\txf\model' ) )
			throw new \InvalidArgumentException( 'not a model: ' . $model->getName() );

		$result = new static();
		$result->reflection = $model;

		return $result;
	}

	/**
	 * Creates proxy for faked model.
	 *
	 * @param string $setName name of model's set in a data source
	 * @param array $definition map of model's properties' names into their type
	 * @param array $idProperties names of properties in $definition to identify
	 *        records of model
	 * @return model_relation_model
	 */

	public static function createOnVirtualModel( $setName, $definition, $idProperties = null )
	{
		if ( !is_string( $setName ) )
			throw new \InvalidArgumentException( 'set name is not a string' );

		$setName = trim( $setName );
		if ( $setName === '' )
			throw new \InvalidArgumentException( 'set name is empty' );

		if ( !is_array( $definition ) || !count( $definition ) )
			throw new \InvalidArgumentException( 'invalid set of property definitions' );

		if ( is_null( $idProperties ) )
			// use keys of all properties in definition unless caller provided
			// explicit set of identifying properties
			$idProperties = array_keys( $definition );
		else if ( !is_array( $idProperties ) || !count( $idProperties ) )
			throw new \InvalidArgumentException( 'invalid set of primary keys in virtual model' );

		// ensure provided set of identifying properties is including basically
		// defined properties of virtual model, only
		foreach ( $idProperties as $id )
			if ( !array_key_exists( $id, $definition ) )
				throw new \InvalidArgumentException( 'ID property missing in definition: ' . $id );


		// create model instance
		$result = new static();
		$result->set = $setName;
		$result->definition = $definition;
		$result->primaries  = $idProperties;

		return $result;
	}

	/**
	 * Detects if current model is virtual or not.
	 *
	 * Virtual models do not have actual implementation.
	 *
	 * @return bool
	 */

	public function isVirtual()
	{
		return !$this->reflection;
	}

	/**
	 * Retrieves class name of wrapped model.
	 *
	 * @return string
	 */

	public function getModelName()
	{
		if ( $this->isVirtual() )
			return '_virtual_model_on_' . $this->set;

		return $this->reflection->getName();
	}

	/**
	 * Declares data set of model in provided data source.
	 *
	 * @throws datasource_exception on failing to declare model's data set
	 * @param datasource\connection $source
	 * @return model_relation_model current instance
	 */

	public function declareInDatasource( datasource\connection $source )
	{
		$setName = $this->getSetName();

		// test runtime cache for including mark on having declared data set in
		// provided data source before
		if ( array_key_exists( $setName, self::$declaredCached ) )
			foreach ( self::$declaredCached[$setName] as $s )
				if ( $s === $source )
					return $this;


		if ( $this->isVirtual() ) {
			// wrapping faked model
			// -> use provided description to declare model's set in data source
			if ( !$source->createDataset( $setName, $this->definition, $this->primaries ) )
				throw new datasource_exception( $source, 'failed to create data set of model ' . $setName );
		} else
			// wrapping actually existing model
			// -> call its model::updateSchema() on provided data source
			$this->reflection->getMethod( 'updateSchema' )->invoke( null, $source );

		// add mark to runtime cache on having declared model's data set in
		// provided data source
		self::$declaredCached[$setName] = array_merge( (array) self::$declaredCached[$setName], array( $source ) );

		return $this;
	}

	/**
	 * Retrieves model's unqualified and unquoted name of data set containing
	 * records of model in data source.
	 *
	 * @return string
	 */

	public function getSetName()
	{
		if ( $this->isVirtual() )
			return $this->set;

		return $this->reflection->getMethod( 'set' )->invoke( null );
	}

	/**
	 * Lists labels of items matching provided filter.
	 *
	 * This method is basically wrapping model::listItemLabels() on actual
	 * models while imitating it for virtual ones.
	 *
	 * @see model::listItemLabels()
	 *
	 * @param datasource\connection $source data source to search in
	 * @param array $properties additional properties to fetch per matching record
	 * @param array $filterProperties properties of items to test for matching
	 * @param array $filterValues values of properties per item to test for matching
	 * @return array @see model::listItemLabels()
	 * @throws datasource\datasource_exception
	 */

	public function listItems( datasource\connection $source, $properties = null, $filterProperties = null, $filterValues = array() )
	{
		if ( !$this->isVirtual() )
			return $this->reflection->getMethod( 'listItemLabels' )->invoke( null, null, $source, $properties, $filterProperties, $filterValues );


		/*
		 * imitate method model::listItemLabels() for virtual models
		 */

		// ensure set is available in data source
		$this->declareInDatasource( $source );

		// convert set of filtering properties into filtering term
		$filter = implode( ' AND ', array_map( function( $name ) use ( $source ) {
			return $source->quoteName( $name ) . '=?';
		}, $filterProperties ) );

		// create query selecting records of model's set matching filter term
		$query = $source->createQuery( $this->getSetName() )
			->addFilter( $filter, true, $filterValues );

		// fetch properties of ID
		$ids = $this->getIdProperties();
		foreach ( $ids as $index => $name )
			$query->addProperty( $source->quoteName( $name ), "i$index" );

		// optionally fetch caller-provided properties in addition
		if ( $haveAdditionProperties = is_array( $properties ) )
			foreach ( $properties as $name )
				$query->addProperty( $name );

		// fetch matching records from data source
		$matches = $query->execute();

		$items = array();

		while ( $record = $matches->row() ) {
			// extract properties of ID from matching record
			$id = array();
			foreach ( $ids as $index => $name ) {
				$id[$name] = $record["i$index"];
				unset( $record["i$index"] );
			}

			// serialize ID and use it for labelling as well
			$id    = $this->getSerializedId( $id );
			$label = '#' . $id;

			// prepare resulting information depending on whether additional
			// properties have been fetched or not
			if ( $haveAdditionProperties )
				$match = array(
					'label' => $label,
				    'data'  => $record,
				);
			else
				$match = $label;

			// collect match
			$items[$id] = $match;
		}


		return $items;
	}

	/**
	 * Lists names of model's properties used to identify its instances.
	 *
	 * @return array
	 */

	public function getIdProperties()
	{
		if ( $this->isVirtual() )
			return $this->primaries;

		return $this->reflection->getMethod( 'idName' )->invoke( null, null );
	}

	/**
	 * Lists names of model's properties involved in labelling its instances.
	 *
	 * @return array
	 */

	public function getLabelProperties()
	{
		if ( $this->isVirtual() )
			return $this->primaries;

		return $this->reflection->getMethod( 'labelName' )->invoke( null, null );
	}

	/**
	 * Selects instance of model using its ID.
	 *
	 * @param datasource\connection $source data source containing records on items of model
	 * @param array|scalar $id ID of item to select
	 * @return model
	 */

	public function selectInstance( datasource\connection $source, $id )
	{
		if ( $this->isVirtual() )
			throw new \RuntimeException( 'cannot select instances of virtual model' );

		return $this->reflection->getMethod( 'select' )->invoke( null, $source, $id );
	}

	/**
	 * Formats label of an instance of model using provided set of properties
	 * involved in labelling.
	 *
	 * @param array $labelProperties map of properties' names into their values
	 * @return string label of instance
	 */

	public function getFormattedLabel( $labelProperties )
	{
		if ( $this->isVirtual() )
			return sprintf( '%s #%s', $this->getSetName(), implode( '::', $labelProperties ) );

		return $this->reflection->getMethod( 'formatLabel' )->invoke( null, $labelProperties );
	}

	/**
	 * Retrieves definition of model's schema.
	 *
	 * This method is used to access information on a model's schema.
	 *
	 * @return array map of properties' names into their individual type definition
	 */

	public function getDefinition()
	{
		if ( $this->isVirtual() )
			return $this->definition;

		return $this->reflection->getMethod( 'getSchema' )->invoke( null );
	}

	/**
	 * Serializes provided ID of an instance of model.
	 *
	 * @param array $idProperties map of properties' names into their values used to identify instance of model
	 * @return string serialized ID
	 */

	public function getSerializedId( $idProperties )
	{
		if ( $this->isVirtual() )
			return model::serializeId( $idProperties );

		return $this->reflection->getMethod( 'serializeId' )->invoke( null, $idProperties );
	}

	/**
	 * Detects if provided model is same as current one.
	 *
	 * @param model|\ReflectionClass|string|model_relation_model $model
	 * @return bool true if provided model is same as current one
	 */

	public function isSameModel( $model )
	{
		if ( $this->isVirtual() )
			return $model instanceof model_relation_model &&
			       $model->isVirtual() &&
			       $model->set == $this->set;

		return model::normalizeModel( $model )->getName() == $this->reflection->getName();
	}
}

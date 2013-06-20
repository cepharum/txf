<?php

namespace de\toxa\txf;


/**
 * Model relation processor.
 *
 * This class is designed to manage relations between elements of models managed
 * using class model. Relations are describes programmatically, e.g.
 *
 * model_relation::createOn( $person )
 */
class model_relation
{
	/**
	 * model instance describing element relations are referring to
	 *
	 * @var model
	 */

	protected $target = null;

	/**
	 * name of property in target model used to identify elements in relation
	 *
	 * @var string
	 */

	protected $targetProperty = null;

	/**
	 * model of relation's source
	 *
	 * @var model
	 */

	protected $source = null;

	/**
	 * name of property in relation's source used to identify related elements
	 * of target model
	 *
	 * @var string
	 */

	protected $sourceProperty = null;

	/**
	 * optional conditions to be met by referencing model's entries
	 *
	 * This is used e.g. on retrieving candidates for becoming related to
	 * particular element of target/referenced model on using selector().
	 *
	 * @var array
	 */

	protected $sourceConditions = array();

	/**
	 * collection of waypoint sets included in relation containing per-waypoint
	 * conditions used to join it
	 *
	 * @var array
	 */

	protected $waypoints = array();

	/**
	 * set of relation's properties to show
	 *
	 * @var array
	 */

	protected $visibleProperties = array();



	protected function __construct() {}

	/**
	 * Creates new stub for describing elements relating on an element given here.
	 *
	 * @param model $relatedElement element other elements are referring to as part of a relation
	 * @param string $referencedProperty given model's property related elements are referencing
	 * @return model_relation
	 * @throws \InvalidArgumentException
	 */

	public static function createOn( model $relatedElement, $referencedProperty = null )
	{
		$relation = new static();

		$relation->target         = $relatedElement;
		$relation->targetProperty = $this->getProperty( $relatedElement, $referencedProperty );

		return $relation;
	}

	/**
	 * Inserts (another) model involved in relation.
	 *
	 * @param model $waypoint model instance involved in
	 * @param string $referencingProperty property of waypoint model referencing related element instead of relating model's elements
	 * @param string $referencedProxyProperty proeprty of waypoint model to be referenced by related model's elements instead of related element
	 * @param string $waypointAlias optional alias to be used on involving a single model multiple times
	 * @return model_relation
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 */

	public function via( model $waypoint, $referencingProperty, $referencedProxyProperty, $waypointAlias = null )
	{
		// get unique internal name of waypoint
		$waypointName = $this->qualifiedWaypointName( $waypoint, $waypointAlias );

		// detect double definition of a waypoint
		if ( array_key_exists( $waypointName, $this->waypoints ) )
			throw new \LogicException( 'circular relation due to repeated waypoint definition (use alias?)' );

		// validate provided property names involved in passing waypoint on referencing
		$referencing = data::isKeyword( $referencingProperty );
		if ( !$referencing )
			throw new \InvalidArgumentException( sprintf( 'invalid name of referencing property %s in set %s', $referencingProperty, $waypoint->set() ) );

		$referenced = data::isKeyword( $referencedProxyProperty );
		if ( !$referenced )
			throw new \InvalidArgumentException( sprintf( 'invalid name of referenced proxy property %s in set %s', $referencedProxyProperty, $waypoint->set() ) );


		// add waypoint definition to relation
		$this->waypoints[$waypointName] = array(
											// required for compiling query
											'set'         => $waypoint->set(),
											// required for re-identifying existing waypoints
											'alias'       => $waypointAlias,
											// required for describing waypoint properties contained in relationship
											'referencing' => $referencing,
											'referenced'  => $referenced,
											// optional conditions to be met by waypoint's elements on actually relating
											'conditions'  => array(),
											);

		return $this;
	}

	public function on( $condition, $parameters = array(), model $waypoint = null, $waypointAlias = null )
	{
		assert( 'is_array( $parameters )' );

		$waypointName = $this->qualifiedWaypointName( $waypoint, $waypointAlias, true );
		if ( $waypointName == 'source' )
			$this->sourceConditions[$condition] = $parameters;
		else
		{
			if ( !array_key_exists( $waypointName, $this->waypoints ) )
				throw new \LogicException( 'missing preceding declaration of conditional waypoint' );

			$this->waypoints[$waypointName]['conditions'][$condition] = $parameters;
		}

		return $this;
	}

	public function from( model $relatingModel, $referencingProperty = null )
	{
		$this->source         = $relatingModel;
		$this->sourceProperty = $this->getProperty( $relatingModel, $referencingProperty, true );

		return $this;
	}

	public function showing( $propertyName, $propertyAlias = null )
	{
		$name = $this->qualifiedPropertyName( $propertyName );

		if ( $propertyAlias === null )
			$propertyAlias = $propertyName;

		$this->visibleProperties[$name] = $propertyAlias;

		return $this;
	}

	/**
	 * Retrieves relating model in current relation.
	 *
	 * @return model
	 */

	public function relating()
	{
		assert( '$this->sourceModel instanceof model' );

		return $this->sourceModel;
	}

	/**
	 * Retrieves related model in current relation.
	 *
	 * @return model
	 */

	public function related()
	{
		return $this->targetModel;
	}

	/**
	 * Retrieves normalized name of provided property optionally falling back
	 * to use provided model's single-dimension ID property on omitting explicitly
	 * provided name.
	 *
	 * @param model $model model including optionally selected property
	 * @param string $propertyName name of property to normalize
	 * @param boolean $requireExplicitProperty true to require explicit property name in $propertyName
	 * @return string name of property to use
	 * @throws \InvalidArgumentException
	 */

	protected function getProperty( model $model, $propertyName = null, $requireExplicitProperty = false )
	{
		if ( !$propertyName )
		{
			if ( !$requireExplicitProperty )
				throw new \InvalidArgumentException( 'missing property' );

			if ( $model->idSize() > 1 )
				throw new \InvalidArgumentException( 'relations can not handle multi-dimensional IDs' );

			$propertyName = $model->idName();
		}

		// ensure property name is a keyword
		$resolvedName = data::isKeyword( $propertyName );
		if ( !$resolvedName )
			throw new \InvalidArgumentException( sprintf( 'invalid property %s in %s', $propertyName, $model->set() ) );

		return $resolvedName;
	}

	protected function qualifiedWaypointName( model $waypointModel = null, $waypointAlias = null, $rejectTarget = false )
	{
		if ( $waypointModel )
		{
			$set   = $waypointModel->set();
			$alias = $waypointAlias;

			$index = 1;
			foreach ( $this->waypoints as $waypoint )
				if ( $waypoint['set'] === $set && $waypoint['alias'] === $alias )
					break;
				else
					$index++;

			if ( $index > count( $this->waypoints ) && $this->source )
				// haven't found addressed waypoint and both ends of relation have been declared before
				throw new \LogicException( 'no such waypoint: ' . trim( $set . ' ' . $alias ) );

			return 'via' . $index;
		}

		if ( $this->source )
			return 'source';

		if ( count( $this->waypoints ) > 0 )
			return 'via' . count( $this->waypoints );

		if ( $rejectTarget )
			throw new \LogicException( 'missing waypoint or source of relation' );

		return 'target';
	}

	protected function qualifiedPropertyName( $propertyName )
	{
		$propertyName = trim( $propertyName );
		if ( !preg_match( '/^([a-z_][a-z_0-9]*\.)([a-z_][a-z_0-9])*$/i', $propertyName, $match ) )
			throw new \InvalidArgumentException( 'invalid property name: ' . $propertyName );

		if ( trim( $match[1] ) === '' )
			$propertyName = $this->qualifiedWaypointName() . '.' . $propertyName;

		return $propertyName;
	}

	/**
	 * Compiles query on described relation not binding it to any actual related
	 * element or selecting properties to list.
	 *
	 * @return datasource\query
	 */

	public function prepareQuery()
	{
		assert( '$this->target instanceof model' );
		assert( 'trim( $this->targetProperty ) !== ""' );
		assert( '$this->source instanceof model' );
		assert( 'trim( $this->sourceProperty ) !== ""' );


		// start new datasource query on target model's set
		$query      = $this->target->query( $this->target->set() . " $alias" );
		$referenced = "target.{$this->targetProperty}";

		// add all involved sets as waypoints properly chaining referenced and referencing properties
		foreach ( $this->waypoints as $waypointName => $waypoint )
		{
			$condition  = array( "$referenced=$waypointName.{$waypoint[referencing]}" );
			$parameters = array();

			foreach ( $waypoint['conditions'] as $c => $p )
			{
				$condition[] = $c;
				$parameters  = array_merge( $parameters, $p );
			}

			$query->addDataset( $waypoint['set'] . ' ' . $waypointName, $condition, $parameters );

			// replace information on referenced element
			$referenced = "$waypointName.{$waypoint[referenced]}";
		}

		// add relating source model
		$query->addDataset( $this->source->set() . ' source', "$referenced=source.{$this->sourceProperty}" );

		return $query;
	}

	/**
	 * Compiles query on described relation bound to relate on explicitly
	 * selected or initially provided element of target model.
	 *
	 * @note Returned query isn't configured to retrieve any property actually.
	 *
	 * @param mixed $relatedElementId optional ID of related element to use instead of element provided on constructing model_relation
	 * @return datasource\query
	 */

	public function query( $relatedElementId = null )
	{
		$query = $this->prepareQuery( $relatedElementId ? $relatedElementId : $this->target->id );

		return $query->addFilter( "target.{$this->targetProperty}=?", $relatedElementId );
	}

	/**
	 * Retrieves selector widget for embedding in a model_editor instance.
	 *
	 * @param mixed $relatedElementId optional ID of related element to use instead of element provided on constructing model_relation
	 * @return model_editor_selector
	 */

	public function selector( $relatedElementId = null )
	{
		$matches = $this->query( $relatedElementId )
						->addProperty( 'source.' . $this->sourceProperty )
						->execute();

		$items = array();

		while ( ( $match = $matches->cell() ) !== false )
		{
			$model = new \ReflectionClass( $this->source );
			$item  = $model->getMethod( 'select' )->invoke( null, array( $this->source->source(), $match ) );

			$items[$match] = $item->label();
		}

		return new model_editor_selector( $items );
	}

	/**
	 * Retrieves HTML code of relating elements of initially related or
	 * explicitly selected element.
	 *
	 * @param mixed $relatedElementId optional ID of related element to use instead of element provided on constructing model_relation
	 * @return string
	 */

	public function render( $relatedElementId = null )
	{
		$query = $this->query( $relatedElementId );

		if ( count( $this->visibleProperties ) )
			foreach ( $this->visibleProperties as $property )
				$query->addProperty( $property );
		else
			$query->addProperty( 'source.*' );

		return view::render( 'model/relation/generic', $query );
	}
}

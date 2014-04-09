<?php

namespace de\toxa\txf;


/**
 * Model relation processor.
 *
 * This class is designed to manage relations between elements of models managed
 * using class model. Relations are describes programmatically, e.g.
 *
 * model_relation::createFrom( modelA::proxy(), 'modelB_id' )
 *     ->via( modelB::proxy(), 'id', 'modelC_id' )
 *     ->to( modelC::proxy() );
 *
 * A relation is implicitly assigning special aliases to all datasets involved
 * in processing relation like this:
 *
 * source    aliasing dataset of model provided on constructing relation
 * via1      aliasing dataset of model provided on first invocation of model_relation::via()
 * via2      aliasing dataset of model provided on second invocation of model_relation::via()
 * viaN      aliasing dataset of model provided on Nth invocation of model_relation::via()
 * target    aliasing dataset of model provided in call for method model_relation::to()
 *
 * In addition on adding custom conditions using model_relation::on() relative
 * aliases are processed by case-sensitively replacing aliases in provided
 * condition strings. Since every condition is related to a previously added or
 * explicitly selected waypoint of relation, the following aliases are processed
 * in relation to that waypoint:
 *
 * THIS      aliasing dataset of waypoint itself
 * PREV      aliasing dataset of way- or endpoint directly referencing current waypoint
 * NEXT      aliasing dataset of way- or endpoint direclty referenced by current waypoint
 *
 * Thus, for example, if a condition is applied on relation's waypoint "via1",
 * THIS is aliasing via1, PREV is aliasing source and NEXT is aliasing either
 * target on missing second intermediate waypoint or via2 otherwise.
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
	 * optional conditions to be met by referenced model's entries
	 *
	 * @var array
	 */

	protected $targetConditions = array();

	/**
	 * information on source item relation is bound to
	 *
	 * This is either an array according to source model's dimensionality on
	 * selecting single instance. In addition it may be null to use source model
	 * instance as provided on establishing relation. Finally it might be false
	 * to have source unbound.
	 *
	 * @var array|null|false
	 */

	protected $sourceBound = null;

	/**
	 * information on target item relation is bound to
	 *
	 * This is either an array according to target model's dimensionality on
	 * selecting single instance. In addition it may be null to use target model
	 * instance as provided on establishing relation. Finally it might be false
	 * to have target unbound.
	 *
	 * @var array|null|false
	 */

	protected $targetBound = null;

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

	/**
	 * set of properties to sirt by ascendingly or descendingly
	 *
	 * @var array[string=>boolean]
	 */

	protected $sorting = array();



	protected function __construct() {}

	/**
	 * Starts description of elements relating to instance(s) of provided
	 * element.
	 *
	 * @param model $relatingElement element other elements are referring to as part of a relation
	 * @param string $referencingProperty given model's property related elements are referencing
	 * @return model_relation
	 * @throws \InvalidArgumentException
	 */

	public static function createFrom( model $relatingElement, $referencingProperty = null )
	{
		$relation = new static();

		$relation->source         = $relatingElement;
		$relation->sourceProperty = $relation->getProperty( $relatingElement, $referencingProperty );

		return $relation;
	}

	/**
	 * Inserts (another) model involved in relation.
	 *
	 * @param model $waypoint model instance involved in
	 * @param string $referencedProxyProperty property of waypoint model to be referenced by previous waypoint instead of target endpoint
	 * @param string $referencingProperty property of waypoint model referencing target endpoint (or any upcoming waypoint)
	 * @param string $waypointAlias optional alias to be used on including same model multiple times
	 * @param boolean $isMN if true this via is referencing from and to its neighbours (m:n relation), $referencedProxy is "referencing previous" and $referencingProperty is "referencing next"
	 * @return model_relation
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 */

	public function via( model $waypoint, $referencedProxyProperty, $referencingProperty, $waypointAlias = null, $isMN = false )
	{
		// get unique internal name of waypoint
		$waypointName = $this->qualifiedWaypointName( $waypoint, $waypointAlias, true, true );

		// detect double definition of a waypoint
		if ( array_key_exists( $waypointName, $this->waypoints ) )
			throw new \LogicException( 'circular relation due to repeated waypoint definition (use waypoint alias!)' );

		// validate provided property names involved in passing waypoint on referencing
		$referenced = data::isKeyword( $referencedProxyProperty );
		if ( !$referenced )
			throw new \InvalidArgumentException( sprintf( 'invalid name of referenced proxy property %s in set %s', $referencedProxyProperty, $waypoint->set() ) );

		$referencing = data::isKeyword( $referencingProperty );
		if ( !$referencing )
			throw new \InvalidArgumentException( sprintf( 'invalid name of referencing property %s in set %s', $referencingProperty, $waypoint->set() ) );


		// add waypoint definition to relation
		$this->waypoints[$waypointName] = array(
											// required for retrieving managing model in a relation
											'model'       => $waypoint,
											// required for compiling query
											'set'         => $waypoint->set(),
											// required for re-identifying existing waypoints
											'alias'       => $waypointAlias,
											// required for re-identifying existing waypoints
											'isMN'        => !!$isMN,
											// required for describing waypoint properties contained in relationship
											'referenced'  => $referenced,
											'referencing' => $referencing,
											// optional conditions to be met by waypoint's elements on actually relating
											'conditions'  => array(),
											);

		return $this;
	}

	/**
	 * Adds another condition to obey on relation's previously inserted or
	 * selected waypoint.
	 *
	 * Condition is provided as string of terms putting properties of addressed
	 * (previously added or explicitly selected) waypoint and its immediately
	 * neighbouring waypoints into relation. Use the following (case-sensitive)
	 * aliases for addressing current or neighbouring waypoints's sets in term:
	 *
	 * THIS.x  for any property x of previously added/explicitly selected waypoint
	 * NEXT.x  for any property x of waypoint neighbouring towards endpoint given on constructing relation
	 * PREV.x  for any property x of waypoint neighbouring towards endpoint given by method from()
	 *
	 * Terms missing aliases may cause failures on actually querying datasource
	 * later.
	 *
	 * @param string $condition some condition term (use aliases "source", "target" and "via0", "via1" etc. for explicitly addressing sets of models included in relation)
	 * @param array $parameters parameters to bind on condition
	 * @param model $waypoint model of waypoint in relation
	 * @param string $waypointAlias optional alias of waypoint model (to use on including same model multiple times)
	 * @return model_relation current relation
	 * @throws \LogicException
	 */

	public function on( $condition, $parameters = array(), model $waypoint = null, $waypointAlias = null )
	{
		if ( !is_array( $parameters ) )
			throw new \InvalidArgumentException( 'parameters must be given as array' );

		$waypointName = $this->qualifiedWaypointName( $waypoint, $waypointAlias, true );
		if ( $waypointName == 'source' )
			$this->sourceConditions[$condition] = $parameters;
		else if ( $waypointName == 'target' )
			$this->targetConditions[$condition] = $parameters;
		else
		{
			if ( !array_key_exists( $waypointName, $this->waypoints ) )
				throw new \LogicException( 'missing preceding declaration of conditional waypoint' );

			$this->waypoints[$waypointName]['conditions'][$condition] = $parameters;
		}

		return $this;
	}

	/**
	 * Adds finally referenced endpoint ("target") of relation.
	 *
	 * @param model $relatedModel opposite endpoint's model
	 * @param string $referencedProperty alias of opposite endpoint's model to use on including same model multiple times
	 * @return model_relation current instance
	 */

	public function to( model $relatedModel, $referencedProperty = null )
	{
		$this->target         = $relatedModel;
		$this->targetProperty = $this->getProperty( $relatedModel, $referencedProperty );

		return $this;
	}

	/**
	 * Adds property of previously added or explicitly selected waypoint of
	 * relation to be listed in final query retrieved by method model::render().
	 *
	 * @param string $propertyName name of property to list
	 * @param string $propertyAlias alias to assign on property
	 * @param model $waypoint waypoint model to select explicitly
	 * @param string $waypointAlias alias of waypoint to use on including same waypoint model multiple times
	 * @return model_relation current instance
	 */

	public function showing( $propertyName, $propertyAlias = null, model $waypoint = null, $waypointAlias = null )
	{
		$name = $this->qualifiedPropertyName( $propertyName, $waypoint, $waypointAlias );

		if ( $propertyAlias === null )
			$propertyAlias = $propertyName;

		$this->visibleProperties[$name] = $propertyAlias;

		return $this;
	}

	/**
	 * Requests to sort listed relations by selected property in requested
	 * direction.
	 *
	 * @param string $qualifiedProperty qualified name of property to sort by
	 * @param string|boolean $ascending SQL-like "ASC" or "DESC" or true for sorting ascendingly
	 * @return \de\toxa\txf\model_relation current instance
	 * @throws \InvalidArgumentException
	 */

	public function sortedBy( $qualifiedProperty, $ascending = true )
	{
		if ( !is_string( $qualifiedProperty ) )
			throw new \InvalidArgumentException( 'missing property to sort by' );

		if ( !preg_match( '/^(source|target|via\d+)\.[^.]+$/i', trim( $qualifiedProperty ) ) )
			throw new \InvalidArgumentException( 'property is not qualified' );

		if ( is_string( $ascending ) )
			$ascending = !!preg_match( '/^ASC(ENDING)?$/i', trim( $ascending ) );


		$this->sorting[$qualifiedProperty] = !!$ascending;


		return $this;
	}

	/**
	 * Retrieves relating model (source endpoint) in current relation.
	 *
	 * @param boolean $retrieveProxy true to get proxy instead of actual instance
	 * @return model
	 */

	public function relatingEnd( $retrieveProxy = false )
	{
		return static::getProxy( $this->source, !$retrieveProxy );
	}

	/**
	 * Retrieves related model (target endpoint) in current relation.
	 *
	 * @param boolean $retrieveProxy true to get proxy instead of actual instance
	 * @return model
	 */

	public function relatedEnd( $retrieveProxy = false )
	{
		if ( !( $this->target instanceof model ) )
			throw new \LogicException( 'missing target endpoint of relation' );

		return static::getProxy( $this->target, !$retrieveProxy );
	}

	/**
	 * Retrieves model of relation's bound endpoint.
	 *
	 * This method is returning source endpoint unless target is bound.
	 *
	 * @param boolean $retrieveProxy true to get proxy instead of actual instance
	 * @return model model of relation's bound endpoint
	 * @throws \LogicException
	 */

	public function boundEnd( $retrieveProxy = false )
	{
		if ( !( $this->target instanceof model ) )
			throw new \LogicException( 'missing target endpoint of relation' );

		if ( $this->isTargetBound() )
			return static::getProxy( $this->target, !$retrieveProxy );

		return static::getProxy( $this->source, !$retrieveProxy );
	}

	/**
	 * Retrieves model of relation's unbound endpoint.
	 *
	 * This method is returning target endpoint unless it's the bound one. It
	 * fails in case of both ends are bound.
	 *
	 * @param boolean $retrieveProxy true to get proxy instead of actual instance
	 * @return model model of relation's unbound endpoint
	 * @throws \LogicException
	 */

	public function unboundEnd( $retrieveProxy = false )
	{
		if ( !( $this->target instanceof model ) )
			throw new \LogicException( 'missing target endpoint of relation' );

		if ( $this->isTargetBound() )
		{
			if ( $this->isSourceBound() )
				throw new \LogicException( 'relation is bound on either side' );

			return static::getProxy( $this->source, !$retrieveProxy );
		}

		return static::getProxy( $this->target, !$retrieveProxy );
	}

	/**
	 * Retrieves model in relation used to actually manage relating elements.
	 *
	 * In a direct relation (source->target) source is containing reference
	 * on target and thus is considered managing model.
	 *
	 * In an indirect relation (source->[via->]+target) "via" closest to target
	 * is containing reference on target and thus is considered managing model
	 * in current relation.
	 *
	 * @param boolean $retrieveProxy true to get proxy instead of actual instance
	 * @return model managing model
	 */

	public function managing( $retrieveProxy = false )
	{
		if ( count( $this->waypoints ) > 0 )
		{
			$waypointNames = array_keys( $this->waypoints );
			$firstWaypoint = array_pop( $waypointNames );

			return static::getProxy( $this->waypoints[$firstWaypoint]['model'], !$retrieveProxy );
		}

		return static::getProxy( $this->source, !$retrieveProxy );
	}

	/**
	 * Optionally replaces provided model instance by proxy instance of same model.
	 *
	 * @param \de\toxa\txf\model $model
	 * @param boolean $bypass true to keep provided model instance
	 * @return provided model instance or proxy of same model
	 */

	protected static function getProxy( model $model, $bypass = false )
	{
		return $bypass ? $model : $model->proxy( $model->source() );
	}

	/**
	 * Binds or unbinds relation either at its source or its target endpoint.
	 *
	 * @param model|array|scalar|null $itemOrId item or ID to bind, null for unbinding
	 * @param boolean|null $bindTargetInsteadOfSource true for binding target, false for binding source, null for guessing endpoint matching best provided bound
	 * @return model_relation current relation manager
	 */

	public function bind( $itemOrId = null, $bindTargetInsteadOfSource = null )
	{
		if ( $itemOrId === null )
			return $this->unbind( $bindTargetInsteadOfSource );

		if ( $itemOrId instanceof model )
			return $this->bindOnModel( $itemOrId, $bindTargetInsteadOfSource );

		return $this->bindOnID( $itemOrId, $bindTargetInsteadOfSource );
	}

	/**
	 * Unbinds relation either at its source or its target endpoint.
	 *
	 * @param boolean $bindTargetInsteadOfSource true for binding target, false for binding source
	 * @return model_relation current relation manager
	 */

	public function unbind( $unbindTargetInsteadOfSource = false )
	{
		if ( $unbindTargetInsteadOfSource )
			$this->targetBound = false;
		else
			$this->sourceBound = false;

		return $this;
	}

	/**
	 * Binds relation either at its source or its target endpoint to provided
	 * item.
	 *
	 * @param model $item item to bind
	 * @param boolean|null $bindTargetInsteadOfSource true for binding target, false for binding source, null for guessing endpoint matching best provided model
	 * @return model_relation current relation manager
	 */

	public function bindOnModel( model $item, $bindTargetInsteadOfSource = null )
	{
		// validate provided model is compatible with either source or target model of relation
		$sourceClass = new \ReflectionClass( $this->source );
		$targetClass = new \ReflectionClass( $this->target );
		$itemClass   = new \ReflectionClass( $item );

		$isLikeSource = $itemClass->isSubclassOf( $sourceClass );
		$isLikeTarget = $itemClass->isSubclassOf( $targetClass );

		if ( !$isLikeSource && !$isLikeTarget )
			throw new \InvalidArgumentException( 'Cannot bind relation to incompatible item.' );

		if ( $bindTargetInsteadOfSource === null )
			// try to autodetect endpoint to bind
			$bindTargetInsteadOfSource = $isLikeTarget && !$isLikeSource;
		else if ( $bindTargetInsteadOfSource && !$isLikeTarget )
			throw new \InvalidArgumentException( 'Cannot bind relation to incompatible item.' );

		if ( $bindTargetInsteadOfSource )
			$this->targetBound = $item->isBound() ? $item->id() : false;
		else
			$this->sourceBound = $item->isBound() ? $item->id() : false;

		return $this;
	}

	/**
	 * Binds relation either at its source or its target endpoint to item
	 * selected by its ID.
	 *
	 * @param array|scalar $id ID of item to bind
	 * @param boolean|null $bindTargetInsteadOfSource true for binding target, false for binding source, null for guessing endpoint matching best provided item ID
	 * @return model_relation current relation manager
	 */

	public function bindOnID( $itemID, $bindTargetInsteadOfSource = null )
	{
		// validate provided ID is compatible with either source or target model of relation
		$isLikeSource = $this->source->isValidId( $itemId );
		$isLikeTarget = $this->target->isValidId( $itemId );

		if ( !$isLikeSource && !$isLikeTarget )
			throw new \InvalidArgumentException( 'Cannot bind relation to incompatible item.' );

		if ( $bindTargetInsteadOfSource === null )
			// try to autodetect endpoint to bind
			$bindTargetInsteadOfSource = $isLikeTarget && !$isLikeSource;
		else if ( $bindTargetInsteadOfSource && !$isLikeTarget )
			throw new \InvalidArgumentException( 'Cannot bind relation to incompatible item.' );

		if ( $bindTargetInsteadOfSource )
			$this->targetBound = $itemID;
		else
			$this->sourceBound = $itemID;

		return $this;
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
			if ( $requireExplicitProperty )
				throw new \InvalidArgumentException( 'missing property' );

			if ( $model->idSize() > 1 )
				throw new \InvalidArgumentException( 'relations can not handle multi-dimensional IDs' );

			$propertyName = $model->getReflection()->getMethod( 'idName' )->invoke( null );
		}

		// ensure property name is a keyword
		$resolvedName = data::isKeyword( $propertyName );
		if ( !$resolvedName )
			throw new \InvalidArgumentException( sprintf( 'invalid property %s in %s', $propertyName, $model->set() ) );

		return $resolvedName;
	}

	/**
	 * Retrieves internal alias of most recently added or now explicitly
	 * selected waypoint of relation.
	 *
	 * If waypoint is selected explicitly using $waypointModel and optionally
	 * $waypointAlias the list of actual waypoints is traversed for a matching
	 * combination of model and alias.
	 *
	 * On omitting explicit selection of a waypoint the alias of most recently
	 * added way- or endpoint is retrieved instead. Thus, if targeting endpoint
	 * has been added using model_relation::to() before, alias "target" is
	 * returned. Otherwise alias of waypoint added most recently using
	 * model_relation::via() is returned. If no such waypoint has been declared
	 * yet, alias "source" is returned unless $rejectSource is set true (for
	 * throwing exception then).
	 *
	 * @param \de\toxa\txf\model $waypointModel model of waypoint to look up
	 * @param string $waypointAlias optional alias of waypoint to look up
	 * @param boolean $rejectSource true on throwing exception instead of returning "source"
	 * @return string alias of way-/endpoint
	 * @throws \LogicException
	 */

	protected function qualifiedWaypointName( model $waypointModel = null, $waypointAlias = null, $rejectSource = false, $mayBeMissing = false )
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

			if ( !$mayBeMissing && $index > count( $this->waypoints ) && $this->source )
				// haven't found addressed waypoint and both ends of relation have been declared before
				throw new \LogicException( 'no such waypoint: ' . trim( $set . ' ' . $alias ) );

			return 'via' . $index;
		}

		if ( $this->target )
			return 'target';

		if ( count( $this->waypoints ) > 0 )
			return 'via' . count( $this->waypoints );

		if ( $rejectSource )
			throw new \LogicException( 'missing waypoint or source of relation' );

		return 'source';
	}

	protected function qualifiedPropertyName( $propertyName, model $waypoint = null, $waypointAlias = null )
	{
		$propertyName = trim( $propertyName );
		if ( !preg_match( '/^([a-z_][a-z_0-9]*\.)(\*|[a-z_][a-z_0-9]*)$/i', $propertyName, $match ) )
			throw new \InvalidArgumentException( 'invalid property name: ' . $propertyName );

		if ( trim( $match[1] ) === '' )
			$propertyName = $this->qualifiedWaypointName( $waypoint, $waypointAlias ) . '.' . $propertyName;

		return $propertyName;
	}

	/**
	 * Retrieves filter term addressing union of referencing properties of
	 * relation's source or optionally selected waypoint.
	 *
	 * @param string|integer $waypointIndex index of waypoint to use, omit to choose source
	 * @param boolean $waypointIndex false to omit embedding set aliases per property
	 * @return string SQL-compatible filter term
	 * @throws \OutOfRangeException
	 * @throws \InvalidArgumentException
	 */

	public function referencingFilter( $waypointIndex = null, $aliased = true )
	{
		if ( preg_match( '/^(?:via)?([1-9]\d*)$/', trim( $waypointIndex ), $matches ) )
		{
			$index = intval( $matches[1] );
			if ( $index > count( $this->waypoints ) )
				throw new \OutOfRangeException( 'waypoint index out of bounds' );

			$temp        = array_keys( $this->waypoints );
			$set         = $this->waypoints[$temp[$index-1]]['set'];
			$referencing = $this->waypoints[$temp[$index-1]]['referencing'];
		}
		else if ( $waypointIndex )
			throw new \InvalidArgumentException( 'invalid waypoint index' );
		else
		{
			$set         = $this->source->set();
			$referencing = $this->sourceProperty;
		}

		$alias = $aliased ? "$set." : '';

		return implode( ' AND ', array_map( function( $col ) use ( $alias ) { return "$alias$col=?"; }, is_array( $referencing ) ? $referencing : array( $referencing ) ) );
	}

	/**
	 * Compiles query on described relation not binding it to any actual related
	 * element or selecting properties to list.
	 *
	 * @return datasource\query
	 */

	public function prepareQuery()
	{
		assert( $this->source instanceof model );
		assert( trim( $this->sourceProperty ) !== "" );
		assert( $this->target instanceof model );
		assert( trim( $this->targetProperty ) !== "" );


		// start new datasource query on dataset of relating source model
		$query = $this->source->query( 'source' );

		// extract ordered list of sets included in relation
		$sets = array_keys( $this->waypoints );
		array_unshift( $sets, 'source' );
		array_push( $sets, 'target' );

		// extract ordered list of referencing properties per model included in relation
		$props = array_values( array_map( function( $wp ) { return $wp['referencing']; }, $this->waypoints ) );
		array_unshift( $props, $this->sourceProperty );
		array_push( $props, $this->targetProperty );

		// add datasets of included waypoints
		$wps = array_values( $this->waypoints );
		for ( $i = 1; $i < count( $sets ) - 1; $i++ )
		{
			$wp      = $wps[$i-1];
			$prevSet = $sets[$i-1];
			$thisSet = $sets[$i];

			$condition  = array( "$prevSet.{$props[$i-1]}=$thisSet.{$wp[referenced]}" );
			$parameters = array();

			foreach ( $wp['conditions'] as $c => $p )
			{
				$condition[] = strtr( $c, array(
					'THIS' => $thisSet,
					'PREV' => $prevSet,
					'NEXT' => $sets[$i+1],
				) );

				$parameters = array_merge( $parameters, $p );
			}

			$query->addDataset( $wp['set'] . ' ' . $thisSet, implode( ' AND ', $condition ), $parameters );
		}

		// add dataset of related target model
		$query->addDataset( $this->target->set() . ' target', "{$sets[$i-1]}.{$props[$i-1]}=target.{$this->targetProperty}" );

		return $query;
	}

	/**
	 * Detects whether source endpoint of relation is bound or not.
	 *
	 * @return boolean
	 */

	public function isSourceBound()
	{
		return $this->sourceBound || $this->source->isBound();
	}

	/**
	 * Detects whether target endpoint of relation is bound or not.
	 *
	 * @return boolean
	 */

	public function isTargetBound()
	{
		return $this->targetBound || $this->target->isBound();
	}

	/**
	 * Retrieves ID of item bound as source of relation or false if source isn't
	 * bound.
	 *
	 * @return array|false ID of item bound at source, false if source is unbound
	 */

	public function getSourceBoundId()
	{
		return $this->sourceBound === null ? $this->source->isBound() ? $this->source->id() : false : $this->sourceBound;
	}

	/**
	 * Retrieves ID of item bound as target of relation or false if target isn't
	 * bound.
	 *
	 * @return array|false ID of item bound at target, false if target is unbound
	 */

	public function getTargetBoundId()
	{
		return $this->targetBound === null ? $this->target->isBound() ? $this->target->id() : false : $this->targetBound;
	}

	/**
	 * Compiles query on described relation applied on implicitly or explicitly
	 * bound endpoint(s).
	 *
	 * @note Returned query isn't configured to retrieve any property but IDs of
	 *       relation's source and target, actually. You may add more properties
	 *       to fetch using query's API yourself.
	 *
	 * @return datasource\query
	 */

	public function query()
	{
		// basically compile datasource query for fetching records of current relation
		$query = $this->prepareQuery();

		// bind source/target as desired to explicit items of either endpoint
		$sourceBound = $this->getSourceBoundId();
		$targetBound = $this->getTargetBoundId();

		if ( $sourceBound )
			$query->addCondition( implode( ' AND ', array_map( function( $name ) { return "source.$name=?"; }, array_keys( $sourceBound ) ) ), true, array_values( $sourceBound ) );
		else
			$query->addProperty( 'source.' . $this->sourceProperty );

		if ( $targetBound )
			$query->addCondition( implode( ' AND ', array_map( function( $name ) { return "target.$name=?"; }, array_keys( $targetBound ) ) ), true, array_values( $targetBound ) );
		else
			$query->addProperty( 'target.' . $this->targetProperty );

		// add another filter for excluding dead links
		if ( !$sourceBound || !$targetBound )
		{
			if ( $sourceBound )
				$query->addCondition ( 'target.' . $this->targetProperty . ' IS NOT NULL' );
			else
				$query->addCondition ( 'source.' . $this->sourceProperty . ' IS NOT NULL' );
		}


		return $query;
	}

	/**
	 * Retrieves selector widget for embedding in a model_editor instance.
	 *
	 * @param mixed $relatedElementId optional ID of related element to use instead of element provided on constructing model_relation
	 * @return model_editor_selector
	 */

	public function selector()
	{
		$matches = $this->query()
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
	 * @param array $data custom data to be passed to template on rendering
	 * @param string $template name of custom template to use instead of default one on rendering
	 * @return string rendering result
	 */

	public function render( $data = array(), $template = null )
	{
		$query = $this->query();

		foreach ( $this->sorting as $property => $ascending )
			$query->addOrder( $property, !!$ascending );

		if ( count( $this->visibleProperties ) )
			foreach ( $this->visibleProperties as $property )
				$query->addProperty( $property );
		else if ( $this->isSourceBound() )
			$query->addProperty( 'target.*' );
		else
			$query->addProperty( 'source.*' );

		// fetch all matching relation instances from datasource
		$matches = $query->execute()->all();

		// start variable space initialized using provided set of custom data
		$data = variable_space::fromArray( $data );

		// add fetched relation instances to variable space
		$data->update( 'matches', $matches );

		// add reference on current relation manager instance
		$data->update( 'relation', $this );

		// render variable space using selected or default template
		return view::render( $template ? $template : 'model/relation/generic', $data );
	}

	public function __toString()
	{
		return $this->render();
	}

	/**
	 * Retrieves number of matching relations.
	 *
	 * This method is quite expensive due to recompiling query on every call.
	 *
	 * @return integer number of matching/existing relations
	 */

	public function count()
	{
		return intval( $this->query()->execute( true )->cell() );
	}
}

<?php

namespace de\toxa\txf;


/**
 * Model relation processor.
 *
 * This class is designed to manage arbitrary relations between models.
 *
 * @author Thomas Urban <thomas.urban@cepharum.de>
 */

class model_relation
{
	/**
	 * nodes of relation
	 *
	 * @var array[model_relation_node]
	 */

	protected $nodes = array();

	/**
	 * references of (completely declared relation)
	 *
	 * @var array[model_relation_reference]
	 */

	protected $references = null;

	/**
	 * link to datasource
	 * @var datasource\connection
	 */

	protected $datasource = null;

	/**
	 * optional name of relation
	 *
	 * @var string
	 */

	protected $relationName = null;



	protected function __construct() {}

	/**
	 * Creates relation starting with given node.
	 *
	 * @param model_relation_node $start first node of relation
	 * @return model_relation
	 */

	public static function create( model_relation_node $start )
	{
		if ( !$start->isValid() )
			throw new \InvalidArgumentException( 'invalid relational node' );

		if ( $start->wantsPredecessor() || !$start->wantsSuccessor() )
			throw new \InvalidArgumentException( 'node is not suitable for starting relation' );


		$relation = new static();
		$relation->nodes[]    = $start;

		return $relation;
	}

	/**
	 * Adds node to current relation.
	 *
	 * Relation's definition is extended by adding nodes. Added nodes must be
	 * declaring reference on preceding node. Relation is completed by adding
	 * non-partial node not declaring reference on another succeeding node .
	 * Adding further nodes is rejected then.
	 *
	 * @param model_relation_node $node
	 * @param bool $isPartialNode true if node is considered partially defined, yet
	 * @return $this
	 */

	public function add( model_relation_node $node, $isPartialNode = false )
	{
		if ( $this->isComplete() )
			throw new \LogicException( 'adding to completed relation rejected' );

		if ( !$node->isValid() )
			throw new \InvalidArgumentException( 'invalid relational node' );

		if ( !$node->wantsPredecessor() )
			throw new \InvalidArgumentException( 'node is not suitable for linking with preceding node' );

		$predecessor = $this->nodeAtIndex( -1 );
		if ( $predecessor->getSuccessorReferenceWidth() != $node->getPredecessorReferenceWidth() )
			throw new \InvalidArgumentException( 'mismatching width of reference' );

		$bindThere = $predecessor->canBindOnSuccessor();
		$bindHere  = $node->canBindOnPredecessor();
		if ( !( ( $bindThere && !$bindHere ) ^ ( !$bindThere && $bindHere ) ) )
			throw new \InvalidArgumentException( 'node is not compatible with predecessor in binding reference' );


		$this->nodes[] = $node;


		if ( !$isPartialNode && !$node->wantsSuccessor() )
		{
			// transfer set of nodes into set of references
			$this->references = array();

			for ( $i = 1; $i < count( $this->nodes ); $i++ )
				$this->references[] = new model_relation_reference( $this->nodes[$i-1], $this->nodes[$i], $this, count( $this->references ) );
		}


		return $this;
	}

	/**
	 * Detects if relation is declared completely.
	 *
	 * @return bool true if relation is declared completely
	 */

	public function isComplete()
	{
		// relation is declared completely if set of references has been derived
		// from set of nodes on recently adding node not wanting another successor
		return !is_null( $this->references );
	}

	/**
	 * Retrieves number of nodes in relation.
	 *
	 * @return int
	 */

	public function size() {
		return count( $this->nodes );
	}

	/**
	 * Declares name of relation.
	 *
	 * @param string $name name of relation
	 * @return $this
	 */

	public function setName( $name )
	{
		if ( !is_string( $name ) )
			throw new \InvalidArgumentException( 'invalid relation name' );

		$name = trim( $name );
		if ( $name === '' )
			throw new \InvalidArgumentException( 'invalid relation name' );

		$this->relationName = $name;

		return $this;
	}

	/**
	 * Retrieves name of relation declared used model_relation::setName().
	 *
	 * @return string|null name of relation, null on unnamed relation
	 */

	public function getName()
	{
		return $this->relationName;
	}

	/**
	 * Assigns data source to use on processing relation.
	 *
	 * @param datasource\connection $datasource
	 * @return $this
	 */

	public function setDatasource( datasource\connection $datasource )
	{
		$this->datasource = $datasource;

		return $this;
	}

	/**
	 * Retrieves datasource relation is operating on.
	 *
	 * @return datasource\connection datasource associated with relation
	 */

	public function getDatasource()
	{
		return $this->datasource;
	}

	/**
	 * Retrieves relational node of current relation selected by its index.
	 *
	 * Index might be negative to start selecting node from end of current set
	 * of nodes.
	 *
	 * @throws \OutOfBoundsException if index is too big
	 * @throws \InvalidArgumentException on providing non-integer index
	 * @param int $intIndex index of node to retrieve
	 * @return model_relation_node
	 */

	public function nodeAtIndex( $intIndex ) {
		$intIndex = intval( $intIndex );

		if ( $intIndex >= 0 ) {
			if ( $intIndex >= count( $this->nodes ) )
				throw new \OutOfBoundsException( 'node index out of bounds' );

			return $this->nodes[intval( $intIndex )];
		} else {
			if ( abs( $intIndex ) > count( $this->nodes ) )
				throw new \OutOfBoundsException( 'node index out of bounds' );

			return $this->nodes[count( $this->nodes ) + $intIndex];
		}
	}

	/**
	 * Retrieves reference of relation at selected index.
	 *
	 * @param int $intIndex index of reference to fetch
	 * @return model_relation_reference
	 */

	public function referenceAtIndex( $intIndex )
	{
		if ( !$this->isComplete() )
			throw new \LogicException( 'relation is not complete, yet' );

		$intIndex = intval( $intIndex );

		if ( $intIndex >= 0 ) {
			if ( $intIndex >= count( $this->references ) )
				throw new \OutOfRangeException( 'reference index out of bounds' );

			return $this->references[$intIndex];
		} else {
			if ( abs( $intIndex ) > count( $this->references ) )
				throw new \OutOfBoundsException( 'reference index out of bounds' );

			return $this->references[count( $this->references ) + $intIndex];
		}
	}

	/**
	 * Retrieves all unbound references from current relation.
	 *
	 * @return array
	 */

	public function getUnboundReferences()
	{
		if ( !$this->isComplete() )
			throw new \InvalidArgumentException( 'relation is not complete, yet' );

		return array_filter( array_map( function( $reference ) {
			/** @var model_relation_reference $reference */
			return $reference->isBound() ? null : $reference;
		}, $this->references ) );
	}

	/**
	 * Searches relation for node matching model and/or name/alias.
	 *
	 * Optionally provided callback might be used to apply additional checks per
	 * node.
	 *
	 * @param model|\ReflectionClass|null $model model of node to look for
	 * @param string|null $alias name of model or alias of node to look for
	 * @param callback $callback method to invoke on every tested node with node
	 *                 and index as arguments, return true to match eventually
	 * @param bool $endPointsOnly false to search whole relation instead
	 * @return model_relation_node|null
	 */

	public function findNode( $model = null, $alias = null, $callback = null, $endPointsOnly = false ) {
		if ( !$this->isComplete() )
			throw new \LogicException( 'relation is not complete' );

		$node = $this->_isNodeMatching( 0, $model, $alias );
		if ( $node && ( !$callback || call_user_func( $callback, $node, 0 ) ) )
			return $node;

		$node = $this->_isNodeMatching( -1, $model, $alias );
		if ( $node && ( !$callback || call_user_func( $callback, $node, -1 ) ) )
			return $node;

		if ( !$endPointsOnly )
			for ( $index = 1; $index < count( $this->nodes ) - 1; $index++ ) {
				$node = $this->_isNodeMatching( $index, $model, $alias );
				if ( $node && ( !$callback || call_user_func( $callback, $node, $index ) ) )
					return $node;
			}

		return null;
	}

	/**
	 * Unbinds all nodes of relation.
	 *
	 * @return $this
	 */

	public function unbind() {
		if ( $this->isComplete() )
			foreach ( $this->nodes as $node ) {
				/** @var model_relation_node $node */
				$node->bindOnPredecessor( null );
				$node->bindOnSuccessor( null );
			}

		return $this;
	}

	/**
	 * Detects if any node or all nodes of relation is/are bound.
	 *
	 * @param bool $requireAll true to check if all nodes of relation are bound
	 * @return bool true if relation is bound in one or all nodes
	 */

	public function isBound( $requireAll = false ) {
		if ( !$this->isComplete() )
			return false;

		foreach ( $this->nodes as $node )
			/** @var model_relation_node $node */
			if ( $node->isBound() ) {
				if ( !$requireAll )
					return true;
			} else if ( $requireAll )
				return false;

		return $requireAll ? true : false;
	}

	/**
	 * Binds relation on its end node matching selected model using provided
	 * value(s).
	 *
	 * @example The following invocations work equivalently:
	 *     $relation->bindOnEndOfModel( myModel::getReflection(), 123, 'generic' );
	 *     $relation->bindOnEndOfModel( myModel::getReflection(), array( 123, 'generic' ) );
	 *
	 * @param model|\ReflectionClass $model model to search
	 * @param mixed|array $value first value of several, or whole set of values
	 * @return $this
	 */

	public function bindOnEndOfModel( $model, $value ) {
		return $this->_bindOnModel( $model, null, true, data::normalizeVariadicArguments( func_get_args(), 1 ) );
	}

	/**
	 * Binds relation on its end node either matching selected name of model or
	 * any explicitly assigned alias.
	 *
	 * @example The following invocations work equivalently:
	 *     $relation->bindEndOnModel( myModel::getReflection(), 123, 'generic' );
	 *     $relation->bindEndOnModel( myModel::getReflection(), array( 123, 'generic' ) );
	 *
	 * @param string $nameOrAlias name of model or explicitly assigned alias to match
	 * @param mixed|array $value first value of several, or whole set of values
	 * @return $this
	 */

	public function bindOnEndOfName( $nameOrAlias, $value ) {
		return $this->_bindOnModel( null, $nameOrAlias, true, data::normalizeVariadicArguments( func_get_args(), 1 ) );
	}

	/**
	 * Searches node in relation matching model and/or alias/name of model
	 * either at ends of relation or on all of them.
	 *
	 * @param model|\ReflectionClass|null $model model of node to look for
	 * @param string|null $nameOrAlias name of model or alias of node to look for
	 * @param bool $endpointsOnly false to search whole relation instead
	 * @param array|mixed $value first value to bind, or all values as array
	 * @return $this
	 */

	public function bindOnMatching( $model = null, $nameOrAlias = null, $endpointsOnly = true, $value ) {
		if ( !$model && !$nameOrAlias )
			throw new \InvalidArgumentException( 'select either associated model or its name/alias to search for matching node in relation' );

		return $this->_bindOnModel( $model, $nameOrAlias, !!$endpointsOnly, data::normalizeVariadicArguments( func_get_args(), 3 ) );
	}

	/**
	 * Searches node in relation matching model and/or alias/name of model
	 * either at ends of relation or on all of them.
	 *
	 * @param model|\ReflectionClass|null $model model of node to look for
	 * @param string|null $alias name of model or alias of node to look for
	 * @param bool $endpointsOnly false to search whole relation instead
	 * @param array $values values to bind on node
	 * @return $this
	 */

	protected function _bindOnModel( $model = null, $alias = null, $endpointsOnly = true, $values ) {
		if ( !$this->isComplete() )
			throw new \RuntimeException( 'binding incomplete relation rejected' );

		// try matching first node in relation
		if ( $this->_tryBindMatchingNode( 0, $model, $alias, true, $values ) )
			return $this;

		// try matching last node in relation
		if ( $this->_tryBindMatchingNode( -1, $model, $alias, false, $values ) )
			return $this;

		// try nodes in between?
		if ( !$endpointsOnly )
			for ( $index = 1; $index < count( $this->nodes ) - 1; $index++ ) {
				$node = $this->_isNodeMatching( $index, $model, $alias );
				if ( $node ) {
					switch ( $node->getBindMode() ) {
						case model_relation_node::BINDING_BOTH :
							throw new \LogicException( 'ambigious binding' );

						case model_relation_node::BINDING_PREDECESSOR :
							$node->bindOnPredecessor( $values );
							return $this;

						case model_relation_node::BINDING_SUCCESSOR :
							$node->bindOnSuccessor( $values );
							return $this;

						case model_relation_node::BINDING_NEITHER :
							throw new \LogicException( 'ambigious binding, matching node is ' );
					}
				}
			}

		throw new \RuntimeException( 'model is not associated with any selected node of relation' );
	}

	/**
	 * Retrieves node at given index if matching model and/or alias.
	 *
	 * @param int $index index of node, used on calling nodeAtIndex()
	 * @param model|\ReflectionClass|null $model model of node required to match
	 * @param string|null $alias name of model or alias of node required to match
	 * @return model_relation_node|null selected node if matching, null otherwise
	 */

	protected function _isNodeMatching( $index, $model = null, $alias = null ) {
		$node = $this->nodeAtIndex( $index );

		$matching  = ( $model === null || $node->isAssociatedWithModel( $model ) );
		$matching |= ( $alias === null || $node->getName() == $alias );

		return $matching ? $node : null;
	}

	/**
	 * Tries binding values on node at given index if matching model and/or
	 * alias.
	 *
	 * @param int $index index of node, used on calling nodeAtIndex()
	 * @param model|\ReflectionClass|null $model model of node required to match
	 * @param string|null $alias name of model or alias of node required to match
	 * @param bool $tryOnSucceeding try binding values on node's reference to succeeding node
	 * @param array $values values to bind
	 * @return $this
	 */

	protected function _tryBindMatchingNode( $index, $model = null, $alias = null, $tryOnSucceeding = true, $values ) {
		$node = $this->_isNodeMatching( $index, $model, $alias );
		if ( !$node )
			// node isn't matching, so don't try to bind actually
			return null;

		// check if node is capable of binding in desired direction itself
		if ( $tryOnSucceeding )
			$okay = $node->canBindOnSuccessor();
		else
			$okay = $node->canBindOnPredecessor();

		if ( $okay )
			// node can bind itself ... so bind
			$tryOnSucceeding
				? $node->bindOnSuccessor( $values )
				: $node->bindOnPredecessor( $values );
		else
			// node can't bind itself ... bind on neighbour of either reference
			$tryOnSucceeding
				? $this->nodeAtIndex( $index + 1 )->bindOnPredecessor( $values )
				: $this->nodeAtIndex( $index - 1 )->bindOnSuccessor( $values );

		return $this;
	}

	/**
	 * Binds any reference of selected node to provided item.
	 *
	 * @param int $intNodeIndex index of node to bind
	 * @param model $item item providing values to use on binding node
	 * @return $this
	 */

	public function bindNodeOnItem( $intNodeIndex, model $item )
	{
		$node = $this->nodeAtIndex( $intNodeIndex );
		if ( !$node->getModel()->isSameModel( $item ) )
			throw new \InvalidArgumentException( 'item is not matching model of selected node' );

		if ( !$this->datasource )
			$this->setDatasource( $item->source() );

		if ( $node->wantsPredecessor() ) {
			$values = array();
			foreach ( $node->getPredecessorNames() as $name )
				$values[$name] = $item->__get( $name );

			if ( $node->canBindOnPredecessor() )
				$node->bindOnPredecessor( $values );
			else
				$this->nodeAtIndex( $intNodeIndex - 1 )->bindOnSuccessor( $values );
		}

		if ( $node->wantsSuccessor() ) {
			$values = array();
			foreach ( $node->getSuccessorNames() as $name )
				$values[$name] = $item->__get( $name );

			if ( $node->canBindOnSuccessor() )
				$node->bindOnSuccessor( $values );
			else
				$this->nodeAtIndex( $intNodeIndex + 1 )->bindOnPredecessor( $values );
		}

		return $this;
	}

	/**
	 * Retrieves conditions describing all currently bound nodes.
	 *
	 * The resulting array includes element for each binding of nodes in current
	 * relation (containing up to two elements per node). Every element consist
	 * of element 'filter' giving SQL-like condition with parameter markers and
	 * element 'values' containing values to bind as parameters accordingly.
	 *
	 * @return array
	 */

	public function getConditionOnBoundNodes() {
		if ( !$this->isComplete() )
			throw new \RuntimeException( 'relation is not complete' );

		$conditions = array();

		foreach ( $this->nodes as $node ) {
			/** @var model_relation_node $node */
			$state = $node->isBound( null );
			if ( $state & model_relation_node::BINDING_PREDECESSOR )
				$conditions[] = array(
					'filter' => implode( ' AND ', array_map( function( $p ) { return "$p=?"; }, $node->getPredecessorNames( $this->datasource, true ) ) ),
					'values' => $node->getPredecessorValues(),
				);

			if ( $state & model_relation_node::BINDING_SUCCESSOR )
				$conditions[] = array(
					'filter' => implode( ' AND ', array_map( function( $p ) { return "$p=?"; }, $node->getSuccessorNames( $this->datasource, true ) ) ),
					'values' => $node->getSuccessorValues(),
				);
		}

		return $conditions;
	}

	/**
	 * Creates query including all models of relation properly joined according
	 * to declared references.
	 *
	 * @param bool $bound true to apply
	 * @param bool $reverse true to start describing query at end point of relation
	 * @return datasource\query
	 */

	public function createQuery( $bound = true, $reverse = false ) {
		if ( !$this->isComplete() )
			throw new \RuntimeException( 'relation is not complete' );

		if ( !$this->datasource )
			throw new \RuntimeException( 'relation is not configured to use datasource, yet' );


		/*
		 * prepare to traverse nodes of relation in requested order
		 */

		if ( $reverse )
			$range = array( 0, 1, count( $this->nodes ) - 1, 1 );
		else
			$range = array( count( $this->nodes ) - 1, count( $this->nodes ) - 2, 0, -1 );


		/*
		 * start query on data set of first node
		 */

		/** @var model_relation_node $start */
		$start = $this->nodes[$range[0]];
		$query = $this->datasource->createQuery( $start->getFullName( $this->datasource ) );


		/*
		 * joins in all further nodes' data sets
		 */

		$previous = $start;

		for ( $index = $range[1]; $index != $range[2]; $index += $range[3] ) {
			// get next node to join
			/** @var model_relation_node $node */
			$node = $this->nodes[$index];

			// compile condition to join next data set with previously joined one
			$there = $previous->getSuccessorNames( $this->datasource, true );
			$here  = $node->getPredecessorNames( $this->datasource, true );

			$names = array();
			for ( $index = 0; $index < count( $there ); $index++ )
				$names[] = $there[$index] . '=' . $here[$index];

			// add data set
			$query->addDataset( $node->getFullName( $this->datasource ), implode( ' AND ', $names ) );

			// keep track of current node being previous one in next iteration
			$previous = $node;
		}


		/*
		 * add bound nodes as filters
		 */

		if ( $bound )
			foreach ( $this->getConditionOnBoundNodes() as $condition )
				$query->addFilter( $condition['filter'], true, $condition['values'] );


		return $query;
	}

	/**
	 * Detects if first node of relation is bound.
	 *
	 * @return bool
	 */

	public function isBoundAtStart() {
		return $this->nodeAtIndex( 0 )->isBound();
	}

	/**
	 * Detects if last node of relation is bound.
	 *
	 * @return bool
	 */

	public function isBoundAtEnd() {
		return $this->nodeAtIndex( -1 )->isBound();
	}

	/**
	 * Searches node in relation matching model and/or alias checking if it's
	 * bound or not.
	 *
	 * @param model|\ReflectionClass|null $model model of node required to match
	 * @param string|null $alias name of model or alias of node required to match
	 * @return bool
	 */

	public function isBoundAtModel( $model = null, $alias = null ) {
		$node = $this->findNode( $model, $alias, null, false );
		if ( $node )
			return $node->isBound();

		return false;
	}

	/**
	 * Lists all (partially) unbound nodes of relation.
	 *
	 * Due to the semantics of used method model_relation_node::isBound() this
	 * list is containing nodes that can be bound actually, but are not bound
	 * currently.
	 *
	 * @param bool $blnListIndexes true to have list of unbound node's indexes instead of nodes themselves
	 * @return array list of unbounded nodes
	 */

	public function getUnboundNodes( $blnListIndexes = false ) {
		if ( !$this->isComplete() )
			throw new \LogicException( 'relation is not complete, yet' );

		$list = array();

		foreach ( $this->nodes as $index => $node )
			/** @var model_relation_node $node */
			if ( !$node->isBound() )
				$list[] = $blnListIndexes ? $index : $node;

		return $list;
	}

	/**
	 * Detects if relation is of type "many-to-many".
	 *
	 * A many-to-many-relation consists of at least one many-to-many-node. This
	 * is a node actively referencing either of its neighbouring nodes.
	 *
	 * @return bool true if at least one node of relation is many-to-many
	 */

	public function isManyToMany() {
		if ( !$this->isComplete() )
			return false;

		if ( $this->size() <= 2 )
			return false;

		return !!$this->findNode( null, null, function( $node ) {
			/** @var model_relation_node $node */
			return $node->isManyToMany();
		} );
	}

	/**
	 * Compiles query joining sets of all nodes in relation and optionally
	 * filtering according to current binding of relation.
	 *
	 * @param bool $blnBind true to add filter according to relation's binding
	 * @return datasource\query compiled query
	 */

	public function query( $blnBind = true )
	{
		if ( !$this->isComplete() )
			throw new \LogicException( 'relation is not complete' );

		if ( !$this->datasource )
			throw new \RuntimeException( 'relation is not configured to use datasource, yet' );


		/** @var datasource\query $query */
		$source = $this->datasource;
		$query  = null;

		foreach ( $this->references as $reference ) {
			/** @var model_relation_reference $reference */

			// cache access on nodes of reference
			$predecessor = $reference->getPredecessor();
			$successor   = $reference->getSuccessor();

			// create query starting on on current node
			if ( !$query )
				$query = $source->createQuery( $predecessor->getFullName( $source ) );

			// convert reference into join
			$preSet  = $source->quoteName( $predecessor->getName() );
			$postSet = $source->quoteName( $successor->getName() );

			$join = implode( ' AND ', array_map( function( $preProp, $postProp ) use ( $source, $preSet, $postSet ) {
				return "$preSet.$preProp=$postSet.$postProp";
			}, $predecessor->getSuccessorNames( $source ), $successor->getPredecessorNames( $source ) ) );

			// add join to query
			$query->addDataset( $successor->getFullName( $source ), $join );


			// optionally convert binding of reference into filter on query
			if ( $blnBind && $reference->isBound() ) {
				// qualify and quote names of binding properties in reference
				$bindSet    = $reference->getReferencingNode()->getName();
				$properties = $source->quotePropertyNames( $bindSet, $reference->getReferencingPropertyNames() );

				// convert set of names into filtering term
				$filter = implode( ' AND ', array_map( function( $name ) { return "$name=?"; }, $properties ) );

				// add filter to query
				$query->addCondition( $filter, true, $reference->getBindingValues() );
			}
		}


		return $query;
	}

	/**
	 * Lists related entities of a partially bound relation.
	 *
	 * @param bool $asModelInstances true to return list of matching model
	 *        instances instead of items' labels
	 * @param int $listNodeAtIndex index of node to list (default: node at
	 *        opposite end of relation)
	 * @return array list of related elements
	 */

	public function listRelated( $asModelInstances = false, $listNodeAtIndex = -1 )
	{
		// get query over all nodes of relation prepared for filtering
		$query  = $this->query( true );

		$source = $query->datasource();
		$node   = $this->nodeAtIndex( $listNodeAtIndex );
		$model  = $node->getModel();
		$set    = $source->quoteName( $node->getName() );


		/*
		 * select properties to fetch
		 */

		// always require IDs of listed model's instances
		$ids = $model->getIdProperties();
		foreach ( $ids as $index => $property )
			$query->addProperty( $set . '.' . $source->quoteName( $property ), "i$index" );

		// list labels of listed model's instance unless fetching model instances
		if ( !$asModelInstances ) {
			$labels = $model->getLabelProperties();
			foreach ( $labels as $index => $property )
				$query->addProperty( $set . '.' . $source->quoteName( $property ), "l$index" );
		}


		/*
		 * query for matching records
		 */

		$matches = $query->execute();

		// transform matches into map of serialized IDs into labels or model instances
		$iCount = count( $ids );
		$lCount = count( $labels );
		$result = array();

		while ( $row = $matches->row() )
		{
			// extract ID from returned record
			$id = array();
			for ( $i = 0; $i < $iCount; $i++ )
				$id[$ids[$i]] = $row["i$i"];


			if ( $asModelInstances ) {
				// select model instance
				$match = $model->selectInstance( $source, $id );
			} else {
				// extract labelling properties from returned record
				$label = array();
				for ( $i = 0; $i < $lCount; $i++ )
					$label[$labels[$i]] = $row["l$i"];

				// render label on item
				$match = $model->getFormattedLabel( $label );
			}

			// add mapping to resulting list
			$result[$model->getSerializedId( $id )] = $match;
		}


		return $result;
	}

	/**
	 * Retrieves editor instance for managing current relation.
	 *
	 * @return model_editor_related
	 */

	public function editor()
	{
		return new model_editor_related( $this );
	}

	/**
	 * Retrieves HTML code of relating elements of initially related or
	 * explicitly selected element.
	 *
	 * @param array $data custom data to be passed to template on rendering
	 * @param string $template name of custom template to use instead of default one on rendering
	 * @param int $listNodeAtIndex index of node to list properties of
	 *        (default: node at opposite end of relation)
	 * @return string rendering result
	 */

	public function render( $data = array(), $template = null, $listNodeAtIndex = -1 )
	{
		$query = $this->query();

		// extend query to fetch all properties of selected node's model
		$query->addProperty( $this->datasource->quoteName( $this->nodeAtIndex( $listNodeAtIndex )->getName() ) . '.*' );

		// process query
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
		return intval( $this->createQuery()->execute( true )->cell() );
	}
}

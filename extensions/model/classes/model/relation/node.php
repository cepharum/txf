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

namespace de\toxa\txf\model;

use \de\toxa\txf\datasource\connection;

/**
 * Implements single node in a relation of models.
 *
 * - Every relation consists of at least two such nodes.
 * - Every relation's nodes build a simple chain without branches.
 * - Every node is linked with a model.
 * - Every node is managing at most two references to its immediate neighbours in chain of node.
 * - Every reference consists of a non-empty set of properties of current node's model.
 * - Every reference is either "referencing" or "referenced".
 * - Referencing nodes select properties storing foreign key values of referenced model.
 * - Referenced nodes select properties to have values matching foreign key values stored in referencing model.
 * - Properties of a referencing node's model may be changed on adjusting relation.
 *
 * - In a reference both involved nodes must have same number of properties named in their mutual references.
 *
 * @package de\toxa\txf
 */

class model_relation_node {

	const BINDING_NEITHER     = 0;
	const BINDING_PREDECESSOR = 1;
	const BINDING_SUCCESSOR   = 2;
	const BINDING_BOTH        = 3;


	/**
	 * @var model_relation_model
	 */

	protected $model = null;

	/**
	 * Lists names of current model's properties involved in reference on
	 * preceding node of relation.
	 *
	 * If null, there isn't any reference on a preceding node in relation.
	 *
	 * @var array|null
	 */

	protected $backwardNames = null;

	/**
	 * Marks whether reference on preceding node is pointing from current to
	 * preceding node's model or not.
	 *
	 * If true, current model's properties named in $backwardNames are containing
	 * foreign key-like values of preceding node's model.
	 *
	 *  true: predecessor.property <- current.property
	 * false: predecessor.property -> current.property
	 *
	 * @var boolean|null
	 */

	protected $backwardIsReferencing = null;

	/**
	 * Stores values of properties in $backwardNames to bind node in referencing
	 * preceding node.
	 *
	 * @note This array mustn't be set or used if $backwardIsReferencing is
	 *       false.
	 * @var array
	 */

	protected $backwardValues = null;

	/**
	 * Lists names of current model's properties involved in reference on
	 * succeeding node of relation.
	 *
	 * If null, there isn't any reference on a succeeding node in relation.
	 *
	 * @var array|null
	 */

	protected $forwardNames = null;

	/**
	 * Marks whether reference on succeeding node is pointing from current to
	 * succeeding node's model or not.
	 *
	 * If true, current model's properties named in $forwardNames are containing
	 * foreign key-like values of succeeding node's model.
	 *
	 *  true:                         current.property -> successor.property
	 * false:                         current.property <- successor.property
	 *
	 * @var boolean|null
	 */

	protected $forwardIsReferencing = null;

	/**
	 * Stores values of properties in $forwardNames to bind node in referencing
	 * succeeding node.
	 *
	 * @note This array mustn't be set or used if $forwardIsReferencing is false.
	 * @var array
	 */

	protected $forwardValues = null;

	/**
	 * name of alias to use instead of set name of current node's model.
	 *
	 * @var string
	 */

	protected $alias = null;


	protected function __construct()
	{
	}

	/**
	 * Creates relation node representing provided model as part of a relation.
	 *
	 * @param model|\ReflectionClass|model_relation_model $model model associated with node
	 * @return model_relation_node
	 */

	public static function createOnModel( $model )
	{
		$node = new static();
		$node->model = static::_normalizeModel( $model );

		return $node;
	}

	/**
	 * Declares properties used to reference related properties in preceding
	 * node's model.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->makeReferencingPredecessorIn( 'remote_id', 'remote_type' );
	 *     $node->makeReferencingPredecessorIn( array( 'remote_id', 'remote_type' ) );
	 *
	 * @param string|array $propertyName name of first property, set of all involved properties
	 * @return $this
	 */

	public function makeReferencingPredecessorIn( $propertyName )
	{
		$this->backwardNames = $this->_normalizeNames( func_get_args() );
		$this->backwardIsReferencing = true;

		return $this;
	}

	/**
	 * Declares properties addressed to match values stored in related properties
	 * of preceding node's model.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->makeReferencedByPredecessorOn( 'id', 'type' );
	 *     $node->makeReferencedByPredecessorOn( array( 'id', 'type' ) );
	 *
	 * @param string|array $propertyName name of first property, set of all involved properties
	 * @return $this
	 */

	public function makeReferencedByPredecessorOn( $propertyName )
	{
		$this->backwardNames = $this->_normalizeNames( func_get_args() );
		$this->backwardIsReferencing = false;

		return $this;
	}

	/**
	 * Declares properties used to reference related properties in succeeding
	 * node's model.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->makeReferencingSuccessorIn( 'remote_id', 'remote_type' );
	 *     $node->makeReferencingSuccessorIn( array( 'remote_id', 'remote_type' ) );
	 *
	 * @param string|array $propertyName name of first property, set of all involved properties
	 * @return $this
	 */

	public function makeReferencingSuccessorIn( $propertyName )
	{
		$this->forwardNames = $this->_normalizeNames( func_get_args() );
		$this->forwardIsReferencing = true;

		return $this;
	}

	/**
	 * Declares properties addressed to match values stored in related properties
	 * of succeeding node's model.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->makeReferencedBySuccessorOn( 'id', 'type' );
	 *     $node->makeReferencedBySuccessorOn( array( 'id', 'type' ) );
	 *
	 * @param string|array $propertyName name of first property, set of all involved properties
	 * @return $this
	 */

	public function makeReferencedBySuccessorOn( $propertyName )
	{
		$this->forwardNames = $this->_normalizeNames( func_get_args() );
		$this->forwardIsReferencing = false;

		return $this;
	}

	/**
	 * Adjusts alias of current node to use instead of associated model's
	 * set name.
	 *
	 * @param string|null $aliasName alias to use instead of model's set name
	 *                    furtheron, null to revoke any previously set alias
	 * @return $this
	 */

	public function setAlias( $aliasName )
	{
		if ( $aliasName !== null ) {
			if ( !is_string( $aliasName ) )
				throw new \InvalidArgumentException( 'invalid alias name' );

			$aliasName = trim( $aliasName );
			if ( !preg_match( '', $aliasName ) )
				throw new \InvalidArgumentException( 'invalid alias name' );
		}

		$this->alias = $aliasName;

		return $this;
	}

	/**
	 * Detects if current node is associated with selected model.
	 *
	 * @param model|\ReflectionClass $model model to compare with
	 * @return bool true if models are matching
	 */

	public function isAssociatedWithModel( $model )
	{
		$model = static::_normalizeModel( $model );

		return ( $model->getModelName() == $this->model->getModelName() );
	}

	/**
	 * Retrieves name to use on addressing this node's set in datasource.
	 *
	 * This method is retrieving any alias declared explicitly using setAlias()
	 * or name of data set managed by associated model. On providing connection
	 * to datasource the retrieved name (not the alias!) is qualified BUT NOT
	 * quoted according to qualification rules of connected datasource.
	 *
	 * @param bool $ignoreAlias set true to force retrieval of model's set name
	 * @param connection $datasource connected datasource used for optionally qualifying set's name
	 * @return string name to use on addressing this node's data set
	 */

	public function getName( $ignoreAlias = false, connection $datasource = null )
	{
		if ( $this->alias && !$ignoreAlias ) {
			return $this->alias;
		}

		$set = $this->model->getSetName();

		return $datasource ? $datasource->qualifyDatasetName( $set, false ) : $set;
	}

	/**
	 * Retrieves name of data set optionally combined with declared alias.
	 *
	 * On providing datasource connection either part of resulting name is
	 * quoted (and qualified) according to quoting and qualification rules of
	 * connected datasource.
	 *
	 * @param connection $db
	 * @return string
	 */

	public function getFullName( connection $db = null )
	{
		$name  = $this->getName( true );
		$alias = $this->alias ? $this->alias : null;

		if ( $db ) {
			$name = $db->qualifyDatasetName( $name );

			if ( $alias ) {
				$alias = $db->quoteName( $alias );
			}
		}

		return $alias ? $name . ' ' . $alias : $name;
	}

	/**
	 * Retrieves reflection of associated model's class.
	 *
	 * @return model_relation_model
	 */

	public function getModel()
	{
		return $this->model;
	}

	/**
	 * Retrieves name of model.
	 *
	 * @return string
	 */

	public function getModelName()
	{
		return $this->model->getModelName();
	}

	/**
	 * Detects if current node is declared properly.
	 *
	 * This detection is limited to scope of single node. You might need to use
	 * containing relation's validation checks to test this node's integrity.
	 *
	 * @return bool true on node declared properly
	 */

	public function isValid()
	{
		return !is_null( $this->backwardNames ) || !is_null( $this->forwardNames );
	}

	/**
	 * Detects if current node might serve as an endpoint of relation due to
	 * establishing single reference either to preceding or to succeeding node,
	 * only.
	 *
	 * @return bool true if current node isn't declared to reference two neighbouring nodes
	 */

	public function isEndPoint()
	{
		return is_null( $this->backwardNames ) || is_null( $this->forwardNames );
	}

	/**
	 * Detects if node is essential part of a many-to-many relation in that is
	 * it actively referencing on both available references.
	 *
	 * @example In a relation like
	 *
	 *     ( null, customer.id ) <= ( order.customer_id, order.product_id ) => ( product.id, null )
	 *
	 * the inner node of relation is essential for establishing many-to-many
	 * relation between customers and products in that one customer can order
	 * many products (first "many") and one product can be ordered by many
	 * customers (second "many"). In opposition to that a relation like
	 *
	 *     ( null, customer.id ) <= ( order.customer_id, order.id ) <= ( invoice.order_id, null )
	 *
	 * isn't many-to-many for one invoice can't have many customers.
	 *
	 * @return bool
	 */

	public function isManyToMany()
	{
		return !is_null( $this->backwardNames ) && $this->backwardIsReferencing &&
		       !is_null( $this->forwardNames ) && $this->forwardIsReferencing;
	}

	/**
	 * Retrieves names of current node's properties used in establishing
	 * reference on preceding node of relation.
	 *
	 * Returned array is empty if no reference on preceding node has been
	 * declared. On providing datasource connection all names are quoted and
	 * qualified implicitly according to quoting and qualification rules of
	 * linked datasource.
	 *
	 * @param connection $context
	 * @param bool $qualifyNames true to have all names prefixed with name or alias of node's data set
	 * @return array
	 */

	public function getPredecessorNames( connection $context = null, $qualifyNames = false )
	{
		return $this->_qualifyNames( $this->backwardNames, $context, $qualifyNames );
	}

	/**
	 * Retrieves names of current node's properties used in establishing
	 * reference on succeeding node of relation.
	 *
	 * Returned array is empty if no reference on preceding node has been
	 * declared. On providing datasource connection all names are quoted
	 * implicitly according to quoting rules of linked datasource.
	 *
	 * @param connection $context
	 * @param bool $qualifyNames true to have all names prefixed with name or alias of node's data set
	 * @return array
	 */

	public function getSuccessorNames( connection $context = null, $qualifyNames = false )
	{
		return $this->_qualifyNames( $this->forwardNames, $context, $qualifyNames );
	}

	/**
	 * Retrieves currently bound values of node's properties used in establishing
	 * reference on preceding node of relation.
	 *
	 * null is returned if no reference on preceding node has been declared or
	 * if reference isn't bound to values currently.
	 *
	 * @return array
	 */

	public function getPredecessorValues()
	{
		return ( $this->backwardNames && $this->backwardValues ) ? $this->backwardValues : null;
	}

	/**
	 * Retrieves currently bound values of node's properties used in establishing
	 * reference on succeeding node of relation.
	 *
	 * null is returned if no reference on succeeding node has been declared or
	 * if reference isn't bound to values currently.
	 *
	 * @return array
	 */

	public function getSuccessorValues()
	{
		return ( $this->forwardNames && $this->forwardValues ) ? $this->forwardValues : null;
	}

	/**
	 * Retrieves number of properties of current node's model used in reference
	 * on preceding node.
	 *
	 * @return int number of properties
	 */

	public function getPredecessorReferenceWidth()
	{
		return is_null( $this->backwardNames ) ? 0 : count( $this->backwardNames );
	}

	/**
	 * Retrieves number of properties of current node's model used in reference
	 * on succeeding node.
	 *
	 * @return int number of properties
	 */

	public function getSuccessorReferenceWidth() {
		return is_null( $this->forwardNames ) ? 0 : count( $this->forwardNames );
	}

	/**
	 * Detects if node is expecting preceding node or not.
	 *
	 * @return bool
	 */

	public function wantsPredecessor()
	{
		return !!$this->getPredecessorReferenceWidth();
	}

	/**
	 * Detects if node is expecting succeeding node or not.
	 *
	 * @return bool
	 */

	public function wantsSuccessor()
	{
		return !!$this->getSuccessorReferenceWidth();
	}

	/**
	 * Retrieves information on bindings supported by node.
	 *
	 * The result is on of the constants BINDING_NEITHER, BINDING_PREDECESSOR,
	 * BINDING_SUCCESSOR or BINDING_BOTH.
	 *
	 * @return int
	 */

	public function getBindMode()
	{
		$mode  = $this->canBindOnPredecessor() ? self::BINDING_PREDECESSOR : 0;
		$mode += $this->canBindOnSuccessor()   ? self::BINDING_SUCCESSOR   : 0;

		return $mode;
	}

	/**
	 * Detects if node can bind on referencing its preceding node in relation.
	 *
	 * @return bool
	 */

	public function canBindOnPredecessor()
	{
		return !is_null( $this->backwardNames ) && $this->backwardIsReferencing;
	}

	/**
	 * Provides value(s) to bind node on referencing its preceding node in
	 * relation.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->bindOnPredecessor( 123, 'generic' );
	 *     $node->bindOnPredecessor( array( 123, 'generic' ) );
	 *
	 * @param mixed|array $value first value to assign first property of predecessor's reference
	 * @return $this
	 */

	public function bindOnPredecessor( $value )
	{
		if ( $value === null )
			// unbinding actually
			$this->backwardValues = null;
		else if ( !$this->canBindOnPredecessor() )
			throw new \LogicException( 'invalid request for binding node with its predecessor' );
		else
			$this->backwardValues = $this->_normalizeValues( func_get_args(), $this->backwardNames );

		return $this;
	}

	/**
	 * Detects if node can bound on referencing its succeeding node.
	 *
	 * @return bool
	 */

	public function canBindOnSuccessor()
	{
		return !is_null( $this->forwardNames ) && $this->forwardIsReferencing;
	}

	/**
	 * Provides value(s) to bind node on referencing its succeeding node in
	 * relation.
	 *
	 * @example
	 * The following examples are working equivalently:
	 *     $node->bindOnSuccessor( 123, 'generic' );
	 *     $node->bindOnSuccessor( array( 123, 'generic' ) );
	 *
	 * @param mixed|array $value first value to assign first property of successor's reference
	 * @return $this
	 */

	public function bindOnSuccessor( $value )
	{
		if ( $value === null )
			// unbinding actually
			$this->forwardValues = null;
		else if ( !$this->canBindOnSuccessor() )
			throw new \LogicException( 'invalid request for binding node with its successor' );
		else
			$this->forwardValues = $this->_normalizeValues( func_get_args(), $this->forwardNames );

		return $this;
	}

	/**
	 * Detects if node is bound.
	 *
	 * Tested references of node must be declared and have values assigned to be
	 * considered bound. By default this method is testing either reference of
	 * node. But you might focus onf testing either reference on preceding or on
	 * succeeding node explicitly by using according mode as parameter. Testing
	 * node this way results in boolean true on all tested and declared references
	 * having bound values.
	 *
	 * In addition you give null or false as $mode to get actual binding state
	 * of node as result.
	 *
	 * @note Unbindable references of current node are considered bound. Use
	 *       methods canBindOnPredecessor() and canBindOnSuccessor() to exclude
	 *       these cases.
	 *
	 * @param int|null $mode selection of node's references to test for being bound
	 * @return bool|int true if tested reference(s) is/are bound, bind state of node
	 */

	public function isBound( $mode = self::BINDING_BOTH )
	{
		$bound = 0;

		if ( $mode != self::BINDING_SUCCESSOR )
			if ( !$this->canBindOnPredecessor() || !is_null( $this->backwardValues ) )
				$bound += self::BINDING_PREDECESSOR;

		if ( $mode != self::BINDING_PREDECESSOR )
			if ( !$this->canBindOnSuccessor() || !is_null( $this->forwardValues ) )
				$bound += self::BINDING_SUCCESSOR;

		return !$mode ? $bound : $bound > 0;
	}

	/**
	 * Qualifies provided set of property names by optionally prefixing them
	 * with name/alias of current node's set in database.
	 *
	 * On providing connection to datasource names of set and all properties are
	 * quoted according to quoting rules of that datasource.
	 *
	 * @param array $names set of property names to qualify
	 * @param connection $context
	 * @param bool $qualifyActually true to prefix all names with set's name
	 * @return array set of qualified (and optionally quoted) names of properties
	 */

	protected function _qualifyNames( $names, connection $context = null, $qualifyActually = false )
	{
		if ( is_null( $names ) )
			return array();

		$setName = $qualifyActually ? $this->getName( false, $context ) : null;

		if ( $context )
			return $context->qualifyPropertyNames( $setName, $names );

		if ( $qualifyActually )
			return array_map( function( $name ) use ( $setName ) { return "$setName.$name"; }, $names );

		return $names;
	}

	/**
	 * Normalizes provided information to describe class of a model to associate
	 * with relational node.
	 *
	 * Input is validated for being model instance or a model's reflection.
	 * Output is always a model's reflection then.
	 *
	 * @throws \InvalidArgumentException on providing neither model nor its reflection
	 * @param model|\ReflectionClass|mixed $model information on model to normalize
	 * @return model_relation_model
	 */

	protected static function _normalizeModel( $model )
	{
		if ( $model instanceof model_relation_model )
			return $model;

		if ( $model instanceof model )
			$model = $model->getReflection();

		if ( !( $model instanceof \ReflectionClass ) )
			throw new \InvalidArgumentException( 'invalid relation node model' );

		return model_relation_model::createOnModel( $model );
	}

	/**
	 * Normalizes set of property names to be used in reference between two
	 * neighbouring nodes.
	 *
	 * A reference consists of one or more properties at either end, thus
	 * requiring selection of one or more properties per end of a reference.
	 *
	 * This method tries to simplify provision of property names to a method
	 * calling this normalization in that the calling method may accept all
	 * properties in a single argument as array or each property in another
	 * argument, e.g.
	 *
	 *     calling_method( array( 'a', 'b', 'c', 'd' ) );
	 *     calling_method( 'a', 'b', 'c', 'd' );
	 *
	 * By providing all arguments in $names here, either use case is detected
	 * and normalized to have array of provided names. Thus even
	 *
	 *     calling_method( 'a' )
	 *
	 * is resulting in a single-element array on return.
	 *
	 * This normalizing method is checking all provided property names to be
	 * non-empty strings.
	 *
	 * @throws \InvalidArgumentException on property names being either empty or
	 *                                   not a string
	 * @param array $names array of arguments provided to calling method
	 * @return array names of properties extracted from provided set of arguments
	 */

	protected static function _normalizeNames( $names )
	{
		if ( is_string( $names ) ) {
			$names = trim( $names );
			if ( $names === '' )
				throw new \InvalidArgumentException( 'empty reference property name' );

			return array( $names );
		}

		if ( is_array( $names ) ) {
			// support case of passing all names in single arguments as array
			// (thus resulting in array of arrays here by passing func_get_args())
			if ( count( $names ) === 1 && is_array( @$names[0] ) )
				return static::_normalizeNames( $names[0] );

			$normalized = array();

			foreach ( $names as $name ) {
				$name = static::_normalizeNames( $name );
				$normalized[] = array_shift( $name );
			}

			if ( count( array_unique( $normalized ) ) !== count( $normalized ) )
				throw new \InvalidArgumentException( 'ambigious reference elements' );

			if ( !count( $normalized ) )
				throw new \InvalidArgumentException( 'empty set of reference properties' );

			return $normalized;
		}

		throw new \InvalidArgumentException( 'invalid reference definition' );
	}

	/**
	 * Normalizes provided set of values to use on binding reference established
	 * using given set of properties.
	 *
	 * This method tries to simplify provision of property names to a method
	 * calling this normalization in that the calling method may accept all
	 * properties in a single argument as array or each property in another
	 * argument, e.g.
	 *
	 *     calling_method( array( 'a', 'b', 'c', 'd' ) );
	 *     calling_method( 'a', 'b', 'c', 'd' );
	 *
	 * By providing all arguments in $names here, either use case is detected
	 * and normalized to have array of provided names. Thus even
	 *
	 *     calling_method( 'a' )
	 *
	 * is resulting in a single-element array on return.
	 *
	 * @throws \InvalidArgumentException on provided set of values isn't matching reference's set of names in number of elements
	 * @param array $values array of arguments provided to calling method
	 * @param array $names either $this->backwardNames or $this->forwardNames
	 * @return array set of values to use on binding reference of current node
	 */

	protected static function _normalizeValues( $values, $names )
	{
		if ( is_array( $values ) ) {
			// support case of passing all names in single argument as array
			// (thus resulting in array of arrays here by passing func_get_args())
			if ( count( $values ) === 1 && is_array( @$values[0] ) )
				return static::_normalizeValues( $values[0], $names );

			if ( count( $values ) != count( $names ) )
				throw new \InvalidArgumentException( 'unexpected number of values' );

			return $values;
		}

		throw new \LogicException( 'invalid use of _normalizeValues()' );
	}
}

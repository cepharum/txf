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


/**
 * Implements storage for particular state of binding relation.
 *
 * This class is provided to keep track of a particular relation e.g. by saving
 * it in session prior to writing to datasource persistently.
 *
 * @package de\toxa\txf
 */

class model_relation_binding {

	/**
	 * definition of associated relation
	 *
	 * @var model_relation
	 */

	protected $relation;

	/**
	 * @var array
	 */

	protected $values = null;



	protected function __construct( model_relation $relation ) {
		$this->relation = $relation;

		$this->values = null;
	}

	/**
	 * Creates binding instance associated with given relation.
	 *
	 * The resulting instance is capable of saving and restoring values of a
	 * particular relation.
	 *
	 * @param model_relation $relation
	 * @return model_relation_binding
	 */

	public static function createOnRelation( model_relation $relation ) {
		return new static( $relation );
	}

	/**
	 * Takes snapshot of values currently bound in associated relation saving
	 * them in current instance or in another clone of it.
	 *
	 * @param bool $clone true to have values stored in clone of current instance
	 * @return model_relation_binding current instance or clone created if $clone is true
	 */

	public function save( $clone = false ) {
		if ( !$this->relation->isComplete() )
			throw new \LogicException( 'relation is not complete' );


		// collect all current bound values of relation's nodes into array
		$values = array();

		for ( $index = 0; $index < $this->relation->size(); $index++ ) {
			$node = $this->relation->nodeAtIndex( $index );

			$values[] = array(
							$node->getName(),               // 0 - the name or alias of node's model
							$node->getPredecessorValues(),  // 1 - values bound on referencing preceding node
							$node->getSuccessorValues(),    // 2 - values bound on referencing succeeding node
							null,                           // 3 - optionally attached data (e.g. to store with relation data)
							);
		}


		// save collected values in current instance or in another clone of it
		$target = $clone ? clone $this : $this;
		$target->values = $values;


		return $target;
	}

	/**
	 * Restores values of associated relation saved into current instance.
	 *
	 * @return $this
	 */

	public function restore() {
		if ( !$this->relation->isComplete() )
			throw new \LogicException( 'relation is not complete' );

		if ( !is_array( $this->values ) )
			throw new \LogicException( 'no such values to restore' );


		// validate number of values is matching number of nodes in relation
		if ( count( $this->values ) != $this->relation->size() )
			throw new \LogicException( 'number of nodes is not matching saved set of values' );

		// validate names of nodes matching saved sequence
		foreach ( $this->values as $index => $values )
			if ( $this->relation->nodeAtIndex( $index )->getName() !== $values[0] )
				throw new \LogicException( 'saved set of values does not match defined relation (anymore)' );


		// rebind nodes of relation using saved values
		foreach ( $this->values as $index => $values )
			$this->relation->nodeAtIndex( $index )
				->bindOnPredecessor( $values[1] )
				->bindOnSuccessor( $values[2] );


		return $this;
	}

	/**
	 * Maps name of model or its alias associated with a node to the node's
	 * index for accessing bound data here.
	 *
	 * @param string $name a model's name or alias associated with node in relation
	 * @return bool|int index of found node, false if node isn't found
	 */

	public function nameToIndex( $name ) {
		if ( !is_array( $this->values ) )
			throw new \LogicException( 'save state of relation, first' );

		foreach ( $this->values as $index => $value )
			if ( $value[0] === $name )
				return $index;

		return false;
	}

	/**
	 * Attaches same arbitrary data to node of relation selected by its alias
	 * or associated model's name.
	 *
	 * @param string $name alias or associated model's name
	 * @param mixed $data data to attach
	 * @return $this
	 */

	public function setAttachment( $name, $data ) {
		$index = $this->nameToIndex( $name );
		if ( $index === false )
			throw new \InvalidArgumentException( 'no such name or alias of a node\'s model' );

		$this->values[$index][3] = $data;

		return $this;
	}

	/**
	 * Fetches arbitrary data attached to node of relation selected by its alias
	 * or associated model's name.
	 *
	 * @param string $name alias or associated model's name
	 * @return mixed|null attached data, null if no data is attached
	 */

	public function getAttachment( $name ) {
		$index = $this->nameToIndex( $name );
		if ( $index === false )
			throw new \InvalidArgumentException( 'no such name or alias of a node\'s model' );

		return $this->values[$index][3];
	}
}

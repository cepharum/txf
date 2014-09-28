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


class model_relation_reference
{

	/**
	 * predecessor's end of reference
	 *
	 * @var model_relation_node
	 */

	protected $predecessorEnd;

	/**
	 * successor's end of reference
	 *
	 * @var model_relation_node
	 */

	protected $successorEnd;

	/**
	 * marks if reference is originating in predecessor's node and pointing at
	 * successor's node (left to right, true) or vice versa
	 *
	 * @var bool
	 */

	protected $referencingLeftToRight;

	/**
	 * relation including current reference
	 *
	 * @note This information is used to fetch neighbouring references.
	 *
	 * @var model_relation
	 */

	protected $relation;

	/**
	 * index of current reference in its containing relation's set of references
	 *
	 * @note This information is used to fetch neighbouring references.
	 *
	 * @var int
	 */

	protected $intIndexInRelation;



	public function __construct( model_relation_node $predecessor, model_relation_node $successor, model_relation $relation, $intIndexInRelation )
	{
		// validate nodes being suitable in desired reference
		if ( !$predecessor->wantsSuccessor() || !$successor->wantsPredecessor() )
			throw new \InvalidArgumentException( 'provided nodes do not expect each other' );

		if ( $predecessor->getSuccessorReferenceWidth() !== $successor->getPredecessorReferenceWidth() )
			throw new \InvalidArgumentException( 'provided nodes do not fit on each other' );

		// validate referencing direction
		$dir  = $predecessor->canBindOnSuccessor() ? 1 : 0;
		$dir += $successor->canBindOnPredecessor() ? 2 : 0;

		switch ( $dir ) {
			case 0 :
			case 3 :
				throw new \InvalidArgumentException( 'provided nodes conflict in referencing direction' );

			default :
				$this->referencingLeftToRight = ( $dir == 1 );
		}


		$this->predecessorEnd     = $predecessor;
		$this->successorEnd       = $successor;
		$this->relation           = $relation;
		$this->intIndexInRelation = intval( $intIndexInRelation );
	}

	/**
	 * Retrieves node reference is originating from.
	 *
	 * References store identifying values of referenced node's model in
	 * properties of referencing node's model.
	 *
	 *     referencing_node.foreignKey_prop = referenced_node.id
	 *
	 * @return model_relation_node
	 */

	public function getReferencingNode()
	{
		return $this->referencingLeftToRight ? $this->predecessorEnd : $this->successorEnd;
	}

	/**
	 * Retrieves node reference is pointing at.
	 *
	 * References store identifying values of referenced node's model in
	 * properties of referencing node's model.
	 *
	 *     referencing_node.foreignKey_prop = referenced_node.id
	 *
	 * @return model_relation_node
	 */

	public function getReferencedNode()
	{
		return $this->referencingLeftToRight ? $this->successorEnd : $this->predecessorEnd;
	}

	/**
	 * Retrieves node of reference nearer to the start of relation.
	 *
	 * @return model_relation_node
	 */

	public function getPredecessor()
	{
		return $this->predecessorEnd;
	}

	/**
	 * Retrieves node of reference nearer to the end of relation.
	 *
	 * @return model_relation_node
	 */

	public function getSuccessor()
	{
		return $this->successorEnd;
	}

	/**
	 * Retrieves opposite reference on selected node also involved in current
	 * reference.
	 *
	 * @example
	 * Consider relation spanning over nodes/models "a", "b" and "c". This
	 * relation consists of two references "a"-"b" and "b"-"c", with the latter
	 * referencing from "b" to "c" ("b"->"c").
	 *
	 * The opposite reference of its referencing node ("b") is "a"-"b" while the
	 * opposite reference of its referenced node ("c") is missing, thus
	 * returning null here.
	 *
	 * @param model_relation_node $node either end of current reference
	 * @return model_relation_reference|null opposite reference at selected end,
	 *         null if missing another reference there
	 */

	public function getOppositeReferenceAtNode( model_relation_node $node )
	{
		if ( $node != $this->predecessorEnd && $node != $this->successorEnd )
			throw new \InvalidArgumentException( 'foreign node rejected' );

		if ( $node == $this->predecessorEnd && $this->intIndexInRelation == 0 )
			// there is no opposite of predecessor if current reference is first
			// one in relation
			return null;

		try {
			return $this->relation->referenceAtIndex( $this->intIndexInRelation + ( $node == $this->predecessorEnd ? -1 : +1 ) );
		} catch ( \OutOfRangeException $e ) {
			return null;
		}
	}

	/**
	 * Retrieves properties involved in opposite reference at selected node of
	 * current reference.
	 *
	 * @example
	 * Consider relation spanning over nodes/models "a", "b" and "c". This
	 * relation consists of two references "a"-"b" and "b"-"c", with the latter
	 * referencing from "b" to "c" ("b"->"c").
	 *
	 * The opposite reference of its referencing node ("b") is "a"-"b". Opposite
	 * properties of its referencing node ("b") are those properties of "b"
	 * involved in establishing reference "a"-"b", no matter whether they are
	 * used to reference item of "a" or items of "a" are referencing them in
	 * turn.
	 *
	 * @param model_relation_node $node either end of current reference
	 * @param datasource\connection $source optional connection to data source
	 *        used to implicitly qualify returned properties
	 * @return array property names of selected node's model
	 */

	public function getOppositePropertiesOf( model_relation_node $node, datasource\connection $source = null )
	{
		if ( $node != $this->predecessorEnd && $node != $this->successorEnd )
			throw new \InvalidArgumentException( 'foreign node rejected' );

		if ( $node == $this->predecessorEnd )
			return $node->getPredecessorNames( $source );

		return $node->getSuccessorNames( $source );
	}

	/**
	 * Retrieves values of properties involved in opposite reference at selected
	 * node of current reference.
	 *
	 * This method is retrieving binding values of properties as returned by
	 * getOppositePropertiesOf() in case opposite reference is bound.
	 *
	 * @see model_relation_reference::getOppositePropertiesOf()
	 *
	 * @param model_relation_node $node either end of current reference
	 * @return array|null binding values of given node's properties involved in
	 *         opposite reference, null if opposite reference is not bound
	 */

	public function getOppositeValuesOf( model_relation_node $node )
	{
		if ( $node != $this->predecessorEnd && $node != $this->successorEnd )
			throw new \InvalidArgumentException( 'foreign node rejected' );

		if ( $node == $this->predecessorEnd )
			return $node->getPredecessorValues();

		return $node->getSuccessorValues();
	}

	/**
	 * Retrieves names of properties of referencing node actually involved in
	 * referencing.
	 *
	 * Returned properties are containing values identifying item of referenced
	 * node's model to establish reference/relation.
	 *
	 * Values of returned properties MUST be adjusted on managing relation!
	 *
	 * @param bool $qualify true to prepend all properties' names by name of set
	 * @param datasource\connection $source optionally used to quote names for use in querying connected data source
	 * @return array set of property names, optionally qualified and/or quoted
	 */

	public function getReferencingPropertyNames( $qualify = false, datasource\connection $source = null )
	{
		list( $names, $set ) = $this->_getNames( $this->referencingLeftToRight, $qualify );

		return $this->_qualifyNames( $names, $set, $source );
	}

	/**
	 * Retrieves names of properties of referenced node actually involved in
	 * referencing.
	 *
	 * Returned properties are containing values of item of referenced node's
	 * model identifying this item individually.
	 *
	 * Values of returned properties MUST NOT be adjusted on managing relation!
	 *
	 * @param bool $qualify true to prepend all properties' names by name of set
	 * @param datasource\connection $source optionally used to quote names for use in querying connected data source
	 * @return array set of property names, optionally qualified and/or quoted
	 */

	public function getReferencedPropertyNames( $qualify = false, datasource\connection $source = null )
	{
		list( $names, $set ) = $this->_getNames( !$this->referencingLeftToRight, $qualify );

		return $this->_qualifyNames( $names, $set, $source );
	}

	/**
	 * Detects if reference is bound currently.
	 *
	 * @return bool true if reference is bound currently
	 */

	public function isBound()
	{
		if ( $this->referencingLeftToRight )
			return $this->predecessorEnd->isBound( model_relation_node::BINDING_SUCCESSOR );

		return $this->successorEnd->isBound( model_relation_node::BINDING_PREDECESSOR );
	}

	/**
	 * Binds reference using provided values.
	 *
	 * Values are used to bind referencing node of current reference.
	 *
	 * @param array $values values for binding reference
	 * @return $this
	 */

	public function bind( $values )
	{
		if ( $this->referencingLeftToRight )
			$this->predecessorEnd->bindOnSuccessor( data::normalizeVariadicArguments( func_get_args(), 0 ) );
		else
			$this->successorEnd->bindOnPredecessor( data::normalizeVariadicArguments( func_get_args(), 0 ) );

		return $this;
	}

	/**
	 * Retrieves values used to bind reference currently.
	 *
	 * @return array|null set of values binding reference, null on unbound reference
	 */

	public function getBindingValues()
	{
		if ( $this->referencingLeftToRight )
			return $this->predecessorEnd->getSuccessorValues();

		return $this->successorEnd->getPredecessorValues();
	}

	/**
	 * Unbinds current reference of relation.
	 *
	 * @return $this
	 */

	public function unbind()
	{
		if ( $this->referencingLeftToRight )
			$this->predecessorEnd->bindOnSuccessor( null );
		else
			$this->successorEnd->bindOnPredecessor( null );

		return $this;
	}

	/**
	 * Retrieves model of items suitable for binding this reference.
	 *
	 * @return model_relation_model
	 */

	public function getBindingProviderModel()
	{
		return $this->referencingLeftToRight ? $this->successorEnd->getModel() : $this->predecessorEnd->getModel();
	}

	/**
	 * Retrieves properties of items suitable for binding this reference.
	 *
	 * Values of retrieved properties are suitable for binding reference. Thus
	 * this method is actually aliasing getReferencedPropertyNames() above.
	 *
	 * @return array
	 */

	public function getBindingProviderProperties()
	{
		return $this->getReferencedPropertyNames( false, null );
	}

	/**
	 * Validates and normalizes provided set of properties.
	 *
	 * Normalizations includes rearranging elements of provided array according
	 * to sequence of property names declared in current reference. Validation
	 * includes checking for provided and resulting set of values is matching
	 * each other as well as matching number of properties in current reference.
	 *
	 * @throws \InvalidArgumentException on mismatching size of provided values
	 * @param array $identifyingProperties set of unqualified property names
	 *        mapping into values to be used on binding reference afterwards
	 * @return array properly sorted set of names mapping into values
	 */

	public function normalizeValuesForBinding( $identifyingProperties )
	{
		$bindingProperties = $this->getReferencingPropertyNames( false, null );

		$normalized = data::rearrangeArray( $identifyingProperties, $bindingProperties );

		if ( count( $normalized ) !== count( $identifyingProperties ) ||
			 count( $normalized ) !== count( $bindingProperties ) )
			throw new \InvalidArgumentException( 'mismatching size of identifying properties' );

		return $normalized;
	}

	/**
	 * Retrieves properties' names and associated model's set name or alias from
	 * node at either end of reference.
	 *
	 * @param bool $blnUsePredecessor true to operate on node at predecessor's end
	 * @param bool $blnWantSetNames true to extract set name of selected node's model
	 * @return array two-element array consisting of properties' names and model's set name/alias or null
	 */

	protected function _getNames( $blnUsePredecessor, $blnWantSetNames )
	{
		if ( $blnUsePredecessor )
			return array(
				$this->predecessorEnd->getSuccessorNames(),
				$blnWantSetNames ? $this->predecessorEnd->getName() : null
			);

		return array(
			$this->successorEnd->getPredecessorNames(),
			$blnWantSetNames ? $this->successorEnd->getName() : null
		);
	}

	/**
	 * Qualifies and/or quotes provided property names.
	 *
	 * @param array $arrNames set of property names to process
	 * @param string|null $strSetOrAlias name/alias of data set, provide for qualified names, omit for unqualified names
	 * @param datasource\connection $source used optionally to quote names for use in querying connected data source
	 * @return array set of qualified and/or quoted names of properties
	 */

	protected function _qualifyNames( $arrNames, $strSetOrAlias = null, datasource\connection $source = null )
	{
		$parts = array();

		if ( is_string( $strSetOrAlias ) )
			$parts[] = $source ? $source->quoteName( trim( $strSetOrAlias ) ) : $strSetOrAlias;

		foreach ( $arrNames as $key => $name ) {
			$temp = $parts;
			$temp[] = $source ? $source->quoteName( trim( $name ) ) : trim( $name );

			$arrNames[$key] = implode( '.', $temp );
		}

		return $arrNames;
	}
}

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

namespace de\toxa\txf\datasource\ldap;


/**
 * LDAP search result set manager
 *
 */

class nodeset
{
	/**
	 * cached link identifier for use with LDAP API
	 *
	 * @var resource
	 */

	private $link;

	/**
	 * cached result set identifier for use with LDAP API
	 *
	 * @var resource
	 */

	private $result;

	/**
	 * mark on whether result set is faked to revise support of chaining calls
	 *
	 * @var boolean
	 */

	private $faking;

	/**
	 * result identifier addressing previously fetched element of result set
	 *
	 * @var resource
	 */

	private $cursor;

	/**
	 * reference on most recently fetched entry
	 *
	 * @var node
	 */

	private $current;



	/**
	 * @param resource $link link identifier for use with LDAP API
	 * @param resource $result result set identifier for use with LDAP API
	 */

	public function __construct( $link, $result )
	{
		$this->link   = $link;
		$this->result = $result;

		$this->faking = !$link || !$result;

		$this->cursor = $this->current = null;
	}

	/**
	 * Detects if current instance represents valid set of LDAP entries or not.
	 *
	 * @return boolean true if instance is properly managing set of LDAP nodes
	 */

	public function valid()
	{
		return !$this->faking;
	}

	/**
	 * Restarts traversing over entries matching some query.
	 *
	 * This method isn't dropping reference on entry fetched by nodeset::next()
	 * most recently.
	 *
	 * @return nodeset current result set
	 */

	public function reset()
	{
		$this->cursor = null;

		return $this;
	}

	/**
	 * Advances focus to next entry in set of entries matching some previous
	 * query.
	 *
	 * @return node|false next entry in result set, false at end of set
	 */

	public function next()
	{
		if ( $this->faking )
			return ( $this->current = false );

		if ( $this->cursor )
			$this->cursor = @ldap_next_entry( $this->link, $this->cursor );
		else if ( $this->cursor === null )
			$this->cursor = @ldap_first_entry( $this->link, $this->result );

		if ( !$this->cursor )
			return ( $this->current = false );

		return ( $this->current = new node( $this->link, $this->cursor ) );
	}

	/**
	 * Fetches reference on entry currently focused in result set.
	 *
	 * This reference isn't reset by nodeset::reset() implicitly, but updated
	 * with every call of nodeset::next().
	 *
	 * @return node|false focused entry in result set, false at end of set
	 */

	public function current()
	{
		if ( $this->current === null )
			$this->next();

		return $this->current;
	}
}

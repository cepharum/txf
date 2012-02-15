<?php


/**
 * Interface declaring minimum functionality of an input source manager.
 *
 * @author Thomas Urban <thomas.urban@toxa.de>
 * @license GPL
 */


namespace de\toxa\txf;


interface input_source
{

	/**
	 * Detects if values of current input source are volatile or not.
	 *
	 * Actual script input is considered volatile, while configuration-based
	 * defaults or session-based state data is considered persistent.
	 *
	 * This method is used by input manager on enqueueing this source to get
	 * mark on being volatile used for disabling persistent sources in queue on
	 * demand, e.g. on using input::vget().
	 *
	 * @return boolean true on source providing volatile input
	 */

	public function isVolatile();

	/**
	 * Looks for available input in current source.
	 *
	 * This method is used on traversing queue of input sources to find first
	 * source actually providing input.
	 *
	 * @param string $name name of value to look up
	 * @return boolean true on knowing some input value associated with $name
	 */

	public function hasValue( $name );

	/**
	 * Reads value from source.
	 *
	 * Though null might be used to detect if value actually exists in current
	 * source there is a separate method for doing this. However, if hasValue()
	 * indicates availability of data and getValue() is returning NULL it's
	 * stopping traversal of source queue. But due to support for filtering
	 * input on read you cannot even rely on this behaviour.
	 *
	 * @param string $name name of value to be read
	 * @return mixed read value, null on missing value
	 */

	public function getValue( $name );

	/**
	 * Provides value to be associated with name for persisting.
	 *
	 * This method is called on all enabled input sources after retrieving
	 * available input for return to caller. It's intended use is to store
	 * it in a writable storage to be available on next request for getting
	 * value of same name.
	 *
	 * The method is intentionally called on persistent sources, only.
	 *
	 * @param string $name name of value to persist
	 * @param mixed $value value to persist
	 */

	public function persistValue( $name, $value );

	/**
	 * Explicitly requests to drop value selected by name.
	 *
	 * @param string $name name of value to drop
	 */

	public function dropValue( $name );
}


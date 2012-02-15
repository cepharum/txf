<?php


namespace de\toxa\txf;


/**
 *
 * @param <type> $singular
 * @param <type> $plural
 * @param <type> $count
 * @return string
 */

function _L( $singular, $plural, $count )
{
	return translation::get( $singular, $plural, $count );
}


/**
 * Wraps provided native string in a managed string instance.
 *
 * On providing managed string instance it's cloned.
 *
 * If parameter $useIfNotAString is containing string and $string is not a
 * string, then the former is used to replace the latter. This is available
 * for conveniently validating parameters expected to be string.
 *
 * @param string $string native or managed string to wrap/clone
 * @param string $encoding encoding of provided string, ignored on cloning
 * @param string $useIfNotAString if $string is not a string this string is used instead
 * @return string managed string instance
 */

function _S( $string, $encoding = null, $useIfNotAString = null )
{
	return string::wrap( $string, $encoding, $useIfNotAString );
}


/**
 * Conveniently creates new set instance prefilled with provided argument(s).
 *
 * If a single array is given, then its elements are used to prefill the set.
 * Otherwise all arguments become elements of new set.
 *
 * @return set created set
 */

function _A()
{
	$arguments = func_get_args();

	if ( ( count( $arguments ) == 1 ) && is_array( $arguments[0] ) )
		return set::wrap( $arguments[0] );

	return set::wrap( $arguments );
}


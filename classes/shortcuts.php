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


/**
 * Looks up localized version of provided text matching selected number of items
 * text is referring to.
 *
 * @param string $singular default text to show on single item
 * @param string $plural default text to show on no/multiple items
 * @param integer $count number of items text is considered to refer to
 * @param string $fallbackSingular optional translation to provide on missing (match in) translation table for singular form
 * @param string $fallbackPlural optional translation to provide on missing (match in) translation table for plural form
 * @return string localized version of text or related text in $singular/$plural on mismatch
 */

function _L( $singular, $plural = null, $count = 1, $fallbackSingular = null, $fallbackPlural = null )
{
	return locale::get( $singular, is_null( $plural ) ? $singular : $plural, $count, $fallbackSingular, $fallbackPlural );
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
 * @return \de\toxa\txf\str managed string instance
 */

function _S( $string, $encoding = null, $useIfNotAString = null )
{
	return str::wrap( $string, $encoding, $useIfNotAString );
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

/**
 * Removes any <script>-tags from provided HTML code.
 *
 * @param string $code HTML code to be sanitized for not containing any script code
 * @return string code escaped for proper embedding
 */

function _H( $code )
{
	return html::noscript( $code );
}

/**
 * Escapes provided string to be used in value of an XML/HTML element's attribute.
 *
 * @param string $attribute code to embed in an attribute's value
 * @return string code escaped for proper embedding
 */

function _HA( $attribute )
{
	return html::inAttribute( $attribute );
}

/**
 * Retrieves first provided argument evaluating as true.
 *
 * This method is useful for implicitly providing some default values.
 *
 * @return mixed
 */

function _1()
{
	for ( $i = 0; $i < func_num_args(); $i++ )
		if ( func_get_arg( $i ) )
			return func_get_arg( $i );
}

/**
 * Retrieves first provided argument evaluating as true.
 *
 * This method is useful for implicitly providing some default values.
 *
 * @note This method is different from _1() in that last provided argument is
 *       returned as final fallback even though it's falsy. It's been added for
 *       adjusting semantics of _1() might cause issues in other parts of code.
 *
 * @return mixed
 */
function _D() {
	$argc = func_num_args();

	if ( $argc > 0 ) {
		for ( $i = 0; $i < $argc; $i++ )
		if ( func_get_arg( $i ) )
			return func_get_arg( $i );

		return func_get_arg( $argc - 1 );
	}
}

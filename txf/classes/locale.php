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
 * Fake text locale class providing at least interface for using related API.
 *
 */


class locale
{
	/**
	 * Fetches translation from current default localization dictionary.
	 *
	 * This method is wrapping access on adoptable i18n/l10n systems. Thus it is
	 * requiring provision of at least one key string for trying to map into
	 * some localized version.
	 *
	 * This basic implementation isn't looking up any dictionary but providing
	 * proper fallback if provided or provided lookup key otherwise.
	 *
	 * @param string $singular key to look up translation describing singular case
	 * @param string [$plural] key to look up translation describing plural case (omit for reusing singular key)
	 * @param int [$count] number of items to be actually described by translated string
	 * @param string [$fallbackSingular] string to return instead of lookup key on missing translation (if count == 1)
	 * @param string [$fallbackPlural] string to return instead of lookup key on missing translation (if count != 1)
	 * @return string found translation, proper fallback on missing translation, key matching given count on omitting fallback
	 */

	public static function get( $singular, $plural = null, $count = 1, $fallbackSingular = null, $fallbackPlural = null )
	{
		$count = abs( $count );

		return ( $count == 1 || $plural == null ) ?
					( $fallbackSingular !== null ? $fallbackSingular : $singular )
				:
					( $fallbackPlural !== null ? $fallbackPlural : $plural );
	}

	/**
	 * Fetches translation from localization dictionary selected by "domain".
	 *
	 * This method is wrapping access on adoptable i18n/l10n systems. Thus it is
	 * requiring provision of at least one key string for trying to map into
	 * some localized version.
	 *
	 * This method is adding selection of "domain", which is actually one of
	 * several possible translation/localization dictionaries.
	 *
	 * This basic implementation is forwarding call to locale::get() by dropping
	 * provided selection of domain.
	 *
	 * @param string $domain name of translation/l10n dictionary to look up
	 * @param string $singular key to look up translation describing singular case
	 * @param string [$plural] key to look up translation describing plural case (omit for reusing singular key)
	 * @param int [$count] number of items to be actually described by translated string
	 * @param string [$fallbackSingular] string to return instead of lookup key on missing translation (if count == 1)
	 * @param string [$fallbackPlural] string to return instead of lookup key on missing translation (if count != 1)
	 * @return string found translation, proper fallback on missing translation, key matching given count on omitting fallback
	 */

	public static function domainGet( $domain, $singular, $plural = null, $count = 1, $fallbackSingular = null, $fallbackPlural = null )
	{
		return static::get( $singular, $plural, $count, $fallbackSingular, $fallbackPlural );
	}
}

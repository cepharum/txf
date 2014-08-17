<?php


/**
 * Copyright 2012 Thomas Urban, toxA IT-Dienstleistungen
 *
 * This file is part of TXF, toxA's web application framework.
 *
 * TXF is free software: you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * TXF is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * TXF. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright 2012, Thomas Urban, toxA IT-Dienstleistungen, www.toxa.de
 * @license GNU GPLv3+
 * @version: $Id$
 *
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

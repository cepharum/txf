<?php
/**
 * (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf\user;

use \de\toxa\txf\widget as widget;
use \de\toxa\txf\user as user;
use \de\toxa\txf\txf as txf;
use \de\toxa\txf\view as view;
use \de\toxa\txf\input as input;
use \de\toxa\txf\url as url;


/**
 * Implements widget for logging out any current user.
 *
 * @example
 *
 *     <?php namespace de\toxa\txf;
 *
 *     user\widget_logout::create()->processInput();
 *
 * @package de\toxa\txf\user
 */


class widget_logout implements widget {

	/**
	 * Creates new instance of widget.
	 *
	 * @return widget_logout
	 */

	public static function create() {
		return new static();
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @return widget current instance
	 */

	public function processInput() {

		if ( user::current()->isAuthenticated() ) {
			user::dropCurrent();
		}

		view::flash( \de\toxa\txf\_L('You logged out successfully.') );

		$referrer = input::vget( 'referrer' );
		$referrer = url::isRelative( $referrer ) ? $referrer : null;

		txf::redirectTo( \de\toxa\txf\_1($referrer, 'home') );
	}

	/**
	 * Retrieves code for embedding widget in current view.
	 *
	 * This widget isn't actually providing any code for its logging out current
	 * user without interacting.
	 *
	 * @return string code embeddable in view
	 */

	public function getCode() {
		return '';
	}
}

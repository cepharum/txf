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

namespace de\toxa\txf;


class widget_user_login implements widget {

	/**
	 * Refers to html form used to ask user for providing login credentials.
	 *
	 * @var html_form
	 */

	protected $form;


	public function __construct( $formName = null ) {
		if ( is_string( $formName ) && trim( $formName ) !== '' ) {
			$formName = trim( $formName );
		} else {
			$formName = 'login';
		}

		$this->form = $this->createForm( $formName );
	}

	/**
	 * Creates HTML form used to ask user for providing login credentials.
	 *
	 * This form should use parameter "name" for login name of user, "token" for
	 * password of user and "submit" with value "login" or "cancel" for
	 * selecting whether user actually wants to authenticate or not.
	 *
	 * @param $formName {string} name of HTML form
	 * @return html_form created instance of HTML form
	 */

	protected function createForm( $formName ) {
		$form = html_form::create( $formName );

		$form->setTexteditRow( 'name', _L('login name') );
		$form->setPasswordRow( 'token', _L('password'), '' );
		$form->setButtonRow( 'submit', _L('Authenticate'), 'login' );
		$form->setButtonRow( 'submit', _L('Cancel'), 'cancel' );

		return $form;
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @return widget current instance
	 */

	public function processInput() {

		if ( user::current()->isAuthenticated() ) {
			view::flash( _L('You are logged in, already.') );
			$this->redirect();
		}


		if ( $this->form->hasInput() ) {

			if ( input::vget( 'submit' ) == 'cancel' ) {
				txf::redirectTo( 'home' );
			}

			$username = input::vget( 'name' );
			if ( $username ) {
				try {
					user::setCurrent( user::load( $username ), input::vget( 'token' ) );

					$this->redirect();
				} catch ( unauthorized_exception $ex ) {
					if ( $ex->isAccountLocked() )
						view::flash( sprintf( _L('Your account is locked! <a href="%s">Resend unlock mail now.</a>'), context::scriptURL( 'account', array(), 'resend/' . $ex->getUser()->getID() ) ), 'error' );
					else {
						sleep( 3 );

						if ( $ex->isUserNotFound() ) {
							view::flash( _L('User does not exist.'), 'error' );
						} else {
							view::flash( _L('Authentication failed.'), 'error' );
						}
					}
				}
			} else {
				view::flash( _L('Provide login name and password!') );
			}
		} else {
			$session  =& txf::session();

			$referrer = input::vget( 'referrer' );
			$session['referrer'] = url::isRelative( $referrer ) ? $referrer : null;
		}
	}

	protected function redirect() {
		$session =& txf::session();

		$target = _1( @$session['referrer'], 'home' );
		unset( $session['referrer'] );

		txf::redirectTo( $target );
	}

	/**
	 * Retrieves code for embedding widget in current view.
	 *
	 * @return string code embeddable in view
	 */

	public function getCode() {
		return strval( $this->form );
	}
}

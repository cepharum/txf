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
use \de\toxa\txf\html_form as html_form;
use \de\toxa\txf\user as user;
use \de\toxa\txf\view as view;
use \de\toxa\txf\input as input;
use \de\toxa\txf\txf as txf;
use \de\toxa\txf\url as url;
use \de\toxa\txf\unauthorized_exception as unauthorized_exception;

/**
 * Implements widget for logging in users.
 *
 * @example
 *
 *     <?php namespace de\toxa\txf;
 *
 *     $widget = user\widget_login::create()->processInput();
 *
 *     view::main( markup::h2( _L('Log In') ) );
 *     view::main( $widget->getCode() );
 *
 * @package de\toxa\txf\user
 */

class widget_login implements widget {

	/**
	 * Refers to html form used to ask user for providing login credentials.
	 *
	 * @var html_form
	 */

	protected $form;

	/**
	 * Contains name of form.
	 *
	 * @var string
	 */

	protected $formName = 'login';

	/**
	 * Contains URL of script enabling user to resend some required unlock mail.
	 *
	 * @var string
	 */
	protected $resendUnlockMailUrl = null;



	/**
	 * Creates new instance of widget.
	 *
	 * @return widget_login
	 */

	public static function create() {
		return new static();
	}

	/**
	 * Retrieves reference on current widget's form (creating it if required).
	 *
	 * @return html_form
	 */

	protected function getForm() {
		if ( !$this->form ) {
			$this->form = $this->createForm( $this->formName );
		}

		return $this->form;
	}

	/**
	 * Selects name of form to use instead of default "login".
	 *
	 * @param string $formName name of form to use
	 * @return $this
	 */

	public function setFormName( $formName ) {
		if ( !is_string( $formName ) || trim( $formName ) === '' ) {
			throw new \InvalidArgumentException( "invalid form name" );
		}

		$this->formName = trim( $formName );

		return $this;
	}

	/**
	 * Provides URL of script user may refer to on failed login due to account
	 * being locked (e.g. on account registration) for resending mail to unlock
	 * account.
	 *
	 * The provided URL might contain "%s" to be replaced by user's ID.
	 *
	 * @param string $url URL of script user might fetch for triggering new unlock mail
	 * @return $this
	 */

	public function setResendUnlockMailUrl( $url ) {
		if ( !is_string( $url ) || trim( $url ) === '' ) {
			throw new \InvalidArgumentException( "invalid URL of script resending unlock mail" );
		}

		$this->resendUnlockMailUrl = trim( $url );

		return $this;
	}

	/**
	 * Creates HTML form used to ask user for providing login credentials.
	 *
	 * This form should use parameter "name" for login name of user, "token" for
	 * password of user and "submit" with value "login" or "cancel" for
	 * selecting whether user actually wants to authenticate or not.
	 *
	 * @param string $formName name of HTML form
	 * @return html_form created instance of HTML form
	 */

	protected function createForm( $formName ) {
		$form = html_form::create( $formName );

		$form->setTexteditRow( 'name', \de\toxa\txf\_L('login name') );
		$form->setPasswordRow( 'token', \de\toxa\txf\_L('password'), '' );
		$form->setButtonRow( 'submit', \de\toxa\txf\_L('Authenticate'), 'login' );
		$form->setButtonRow( 'submit', \de\toxa\txf\_L('Cancel'), 'cancel' );

		return $form;
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @return $this current instance
	 */

	public function processInput() {

		if ( user::current()->isAuthenticated() ) {
			view::flash( \de\toxa\txf\_L('You are logged in, already.') );
			$this->redirect();
		}


		$form = $this->getForm();

		if ( $form->hasInput() ) {

			if ( input::vget( 'submit' ) == 'cancel' ) {
				$this->redirect();
			}

			$username = input::vget( 'name' );
			if ( $username ) {
				try {
					user::setCurrent( user::load( $username ), input::vget( 'token' ) );

					$this->redirect();
				} catch ( unauthorized_exception $ex ) {
					if ( $ex->isAccountLocked() ) {
						if ( $this->resendUnlockMailUrl ) {
							view::flash( sprintf( \de\toxa\txf\_L('Your account is locked! <a href="%s">Resend unlock mail now.</a>'), sprintf( $this->resendUnlockMailUrl, $ex->getUser()->getID() ) ), 'error' );
						} else {
							view::flash( sprintf( \de\toxa\txf\_L('Your account is locked!') ), 'error' );
						}
					}
					else {
						sleep( 3 );

						if ( $ex->isUserNotFound() ) {
							view::flash( \de\toxa\txf\_L('User does not exist.'), 'error' );
						} else {
							view::flash( \de\toxa\txf\_L('Authentication failed.'), 'error' );
						}
					}
				}
			} else {
				view::flash( \de\toxa\txf\_L('Provide login name and password!') );
			}
		} else {
			$session  =& txf::session();

			$referrer = input::vget( 'referrer' );
			$session['referrer'] = url::isRelative( $referrer ) ? $referrer : null;
		}

		return $this;
	}

	protected function redirect() {
		$session =& txf::session();

		$target = \de\toxa\txf\_1( @$session['referrer'], 'home' );
		unset( $session['referrer'] );

		txf::redirectTo( $target );
	}

	/**
	 * Retrieves code for embedding widget in current view.
	 *
	 * @return string code embeddable in view
	 */

	public function getCode() {
		return strval( $this->getForm() );
	}
}

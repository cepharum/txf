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
use \de\toxa\txf\exception as exception;
use \de\toxa\txf\input as input;
use \de\toxa\txf\txf as txf;
use \de\toxa\txf\url as url;
use \de\toxa\txf\unauthorized_exception as unauthorized_exception;

/**
 * Implements widget for changing logged in user's password.
 *
 * @example
 *
 *     <?php namespace de\toxa\txf;
 *
 *     $widget = user\widget_password::create()->processInput();
 *
 *     view::main( markup::h2( _L('Change Password') ) );
 *     view::main( $widget->getCode() );
 *
 * @package de\toxa\txf\user
 */

class widget_password implements widget {

	/**
	 * Refers to html form used to ask user for providing current and new
	 * password.
	 *
	 * @var html_form
	 */

	protected $form;

	/**
	 * Contains name of form.
	 *
	 * @var string
	 */

	protected $formName = 'password';

	/**
	 * Stores URL for redirecting user to on having changed password.
	 *
	 * @var string
	 */

	protected $redirectUrl = null;

	/**
	 * Stores optional callback to invoke for assessing quality of entered
	 * password.
	 *
	 * @var callable
	 */

	protected $passwordValidator = null;



	/**
	 * Creates new instance of widget.
	 *
	 * @return widget_password
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
	 * Selects name of form to use instead of default "password".
	 *
	 * @param string $formName name of form to use
	 * @return $this
	 */

	public function setFormName( $formName ) {
		if ( !is_string( $formName ) || trim( $formName ) === '' ) {
			throw new \InvalidArgumentException( 'invalid form name' );
		}

		$this->formName = trim( $formName );

		return $this;
	}

	/**
	 * Provides URL to redirect user to on having changed password.
	 *
	 * The provided URL might contain "%s" to be replaced by user's ID.
	 *
	 * @param string|null $url URL of script user is redirected to, null for omitting previously set one
	 * @return $this
	 */

	public function setRedirectUrl( $url ) {
		if ( is_null( $url ) ) {
			$this->redirectUrl = null;
		} else {
			if ( !is_string( $url ) || trim( $url ) === '' ) {
				throw new \InvalidArgumentException( 'invalid redirect URL' );
			}

			$this->redirectUrl = trim( $url );
		}

		return $this;
	}

	/**
	 * Provides callback to invoke for assessing quality of password to set.
	 *
	 * @param callable $callback callback to invoke
	 * @return $this
	 */

	public function setPasswordValidator( $callback ) {
		if ( !is_callable( $callback ) ) {
			throw new \InvalidArgumentException( 'invalid password validator' );
		}

		$this->passwordValidator = $callback;

		return $this;
	}

	/**
	 * Assesses quality/strength of provided password.
	 *
	 * This method is used for assessing password if widget user didn't select
	 * custom callback.
	 *
	 * @param string $password
	 * @throws \InvalidArgumentException on password is considered too weak
	 */

	protected function passwordValidatorDefault( $password ) {
		if ( preg_match( '/\s/', $password ) )
			throw new \InvalidArgumentException( \de\toxa\txf\_L('Password must not contain whitespace.') );

		if ( strlen( $password ) < 8 )
			throw new \InvalidArgumentException( \de\toxa\txf\_L('Password is too short (min. 8 characters).') );

		if ( strlen( $password ) > 16 )
			throw new \InvalidArgumentException( \de\toxa\txf\_L('Password is too long (max. 16 characters).') );

		if ( !preg_match( '/\d/', $password ) || !preg_match( '/[a-z]/', $password ) ||
		     !preg_match( '/[A-Z]/', $password ) || !preg_match( '/[^\da-z]/i', $password ) )
			throw new \InvalidArgumentException( \de\toxa\txf\_L('Password has to contain at least one upper and one lower latin letter, one digit and one special character.') );
	}

	/**
	 * Creates HTML form used to ask user for providing current and new password
	 * to set.
	 *
	 * This form should use parameter "old" for user's current password, "new"
	 * for password to be set, "repeat" for repetition of password to be set
	 * (required for excluding typos on blindly entering it) and "submit" with
	 * value "change" or "cancel" for selecting whether user actually wants to
	 * change password or not.
	 *
	 * @param string $formName name of HTML form
	 * @return html_form created instance of HTML form
	 */

	protected function createForm( $formName ) {
		return html_form::create( $formName )->post()
			->setPasswordRow( 'old', \de\toxa\txf\_L('current password') )
			->setPasswordRow( 'new', \de\toxa\txf\_L('new password'), '' )
			->setPasswordRow( 'repeat', \de\toxa\txf\_L('repeat password'), '' )
			->setButtonRow( 'submit', \de\toxa\txf\_L('Change Password'), 'change' )
			->setButtonRow( 'submit', \de\toxa\txf\_L('Cancel'), 'cancel' );
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @return $this current instance
	 */

	public function processInput() {

		if ( !user::current()->isAuthenticated() ) {
			view::flash( \de\toxa\txf\_L('You must be logged in.') );
			$this->redirect();
		}


		$form = $this->getForm();

		if ( $form->hasInput() ) {

			if ( input::vget( 'submit' ) == 'cancel' ) {
				$this->redirect();
			}


			$passwordOld  = trim( input::vget( 'old' ) );
			$passwordNewA = trim( input::vget( 'new' ) );
			$passwordNewB = trim( input::vget( 'repeat' ) );

			if ( $passwordOld === '' )
				$form->setRowError( 'old', \de\toxa\txf\_L('Provide current password!') );

			if ( $passwordNewA === '' || $passwordNewB === '' )
				$form->setRowError( 'new', \de\toxa\txf\_l('Provide new password twice for excluding typos.') );
			else if ( $passwordNewA !== $passwordNewB )
				$form->setRowError( 'new', \de\toxa\txf\_L('Doubly entered passwords don\'t match.') );
			else {
				try {
					if ( is_callable( $this->passwordValidator ) )
						call_user_func( $this->passwordValidator, $passwordNewA );
					else
						$this->passwordValidatorDefault( $passwordNewA );
				} catch ( \InvalidArgumentException $e ) {
					$form->setRowError( 'new', $e->getMessage() );
				}
			}


			exception::enterSensitive();

			if ( !$form->hasAnyRowError() ) {
				try {
					$user = user::load( user::current()->getID() );

					try {
						$user->authenticate( $passwordOld );
					} catch ( unauthorized_exception $e ) {
						$form->setRowError( 'old', \de\toxa\txf\_L('Authenticating request using old password failed.') );
					}
				} catch ( unauthorized_exception $e ) {
					$form->setRowError( 'old', \de\toxa\txf\_L('Current user isn\'t available.') );
				}
			}

			$hasError = false;

			if ( !$form->hasAnyRowError() ) {
				try {
					user::current()->changePassword( $passwordNewA );

					view::flash( \de\toxa\txf\_L('Password has been changed successfully.') );

					try {
						user::current()->authenticate( $passwordNewA );
					} catch ( unauthorized_exception $e ) {
						view::flash( \de\toxa\txf\_L('Updating current session for using changed password failed. Probably you need to login, again.'), 'error' );
					}
				} catch ( \RuntimeException $e ) {
					$hasError = true;

					view::flash( \de\toxa\txf\_L('Your input is okay, but changing password failed nevertheless.'), 'error' );
				}
			}

			exception::leaveSensitive();


			if ( !$hasError && !$form->hasAnyRowError() ) {
				$this->redirect();
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

		$target = \de\toxa\txf\_1( $this->redirectUrl, @$session['referrer'], 'home' );
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

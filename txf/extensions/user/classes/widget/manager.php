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

use de\toxa\txf\application;
use de\toxa\txf\browseable;
use de\toxa\txf\datasource;
use de\toxa\txf\http_exception;
use de\toxa\txf\sql_user;
use \de\toxa\txf\widget as widget;
use \de\toxa\txf\databrowser as databrowser;
use \de\toxa\txf\html_form as html_form;
use \de\toxa\txf\user as user;
use \de\toxa\txf\view as view;
use \de\toxa\txf\exception as exception;
use \de\toxa\txf\markup as markup;
use \de\toxa\txf\context as context;
use \de\toxa\txf\input as input;
use \de\toxa\txf\txf as txf;
use \de\toxa\txf\url as url;
use \de\toxa\txf\unauthorized_exception as unauthorized_exception;

/**
 * Implements widget for managing user accounts.
 *
 * This widget is basically available to users with role 'administrator', only.
 *
 * @example
 *
 *     <?php namespace de\toxa\txf;
 *
 *     $widget = user\widget_manager::create()->processInput();
 *
 *     view::main( markup::h2( _L('User Management') ) );
 *     view::main( $widget->getCode() );
 *
 * @package de\toxa\txf\user
 */

class widget_manager implements widget {

	/**
	 * Refers to html form used to show details editor per user.
	 *
	 * @var html_form
	 */

	protected $form;

	/**
	 * Refers to databrowser used to list users.
	 *
	 * @var databrowser
	 */

	protected $browser;

	/**
	 * Contains name of form/databrowser.
	 *
	 * @var string
	 */

	protected $controlName = 'user_management';

	/**
	 * Selects explicit user ID.
	 *
	 * @var int|null
	 */

	protected $selectedUserId = null;

	/**
	 * Selects action to perform.
	 *
	 * @var string|null
	 */
	protected $selectedAction = null;

	/**
	 * Stores optional callback to invoke for assessing quality of entered
	 * passwords.
	 *
	 * @var callable
	 */

	protected $passwordValidator = null;

	/**
	 * Lists code of controls to put on some panel related to widget.
	 *
	 * @var string[]
	 */

	protected $panelControls = array();



	/**
	 * Creates new instance of widget.
	 *
	 * @return widget_password
	 */

	public static function create() {
		return new static();
	}

	/**
	 * Selects name of form to use instead of default "password".
	 *
	 * @param string $controlName name of form to use
	 * @return $this
	 */

	public function setControlName( $controlName ) {
		if ( !is_string( $controlName ) || trim( $controlName ) === '' ) {
			throw new \InvalidArgumentException( 'invalid form/databrowser name' );
		}

		$this->controlName = trim( $controlName );

		return $this;
	}

	/**
	 * Retrieves named set of URLs to use for addressing actions of current
	 * widget.
	 *
	 * Every URL might contain %s to be replaced by single user's ID. This
	 * replacement is required if %s is contained in URL.
	 *
	 * @return object object with URLs in properties add, edit, delete, view, list
	 */

	protected function getUrls() {
		return (object) array(
			'add'    => context::selfUrl( false, 'add' ),
			'edit'   => context::selfUrl( false, '%s', 'edit' ),
			'delete' => context::selfUrl( false, '%s', 'delete' ),
			'view'   => context::selfUrl( false, '%s' ),
			'list'   => context::selfUrl( false, false ),
		);
	}

	/**
	 * Retrieves reference on current widget's form (creating it if required).
	 *
	 * @param string[] $user record of existing user loaded from datasource, omit on adding user
	 * @return html_form
	 */

	protected function getForm( $user = array() ) {
		if ( !$this->form ) {
			$this->form = $this->createForm( $this->controlName, $user )->post();

			if ( !count( $user ) && array_key_exists( 'id', $user ) ) {
				$this->form->setRowCode( 'name', $user['name'] );
			}
		}

		return $this->form;
	}

	/**
	 * Retrieves reference on current widget's browser (creating it if required).
	 *
	 * @throws http_exception on missing proper user configuration
	 * @return html_form
	 */

	protected function getBrowser() {
		if ( !$this->browser ) {
			$provider = user::getProvider();
			if ( !( $provider instanceof sql_user ) )
				throw new http_exception( 400, \de\toxa\txf\_L('This manager is suitable for managing SQL-based users, only!') );

			$db    = $provider->datasource();
			$names = $provider->datasourcePropertyName( 'id', 'uuid', 'loginname', 'name', 'email' );

			$list = $db->createQuery( $provider->datasourceSet() )
				->addProperty( $names['id'], 'id' )
				->addProperty( $names['uuid'], 'uuid' )
				->addProperty( $names['loginname'], 'loginname' )
				->addProperty( $names['name'], 'name' )
				->addProperty( $names['email'], 'email' );

			$this->browser = $this->createBrowser( $this->controlName, $list, $this->getUrls() );
		}

		return $this->browser;
	}

	/**
	 * Explicitly selects some user to operate on.
	 *
	 * This method might be called in conjunction with setSelectedAction() for
	 * preventing widget from checking its implicit parameters.
	 *
	 * Provide
	 *  * null or false for selecting not to operate on single user but list all
	 *    available users
	 *  * 0 for selecting addition of user (might be achieved using action "add"
	 *    as well
	 *  * some positive integer for selecting user by its local-only ID
	 *
	 * @param int|null|false $userId ID of user to use explicitly
	 * @return $this current widget
	 */

	public function setSelectedUserId( $userId ) {
		if ( is_null( $userId ) || $userId === false ) {
			// caller is explicitly selecting not to select any user for listing
			// available users
			// -> set some normalized mark disabling check of implicit parameters
			$this->selectedUserId = false;
		} else if ( ctype_digit( trim( $userId ) ) ) {
			// got ID of some user to select explicitly (or 0 for adding new user)
			// -> store this ID for disabling check of implicit parameters
			$this->selectedUserId = intval( $userId );
		} else
			throw new \InvalidArgumentException( 'invalid explicit user ID' );

		return $this;
	}

	/**
	 * Sets explicit action to perform in current request.
	 *
	 * Use this method for overriding implicit detection of action to perform,
	 * e.g. if widget is embedded in a more complex context resulting in more
	 * complex request URL.
	 *
	 *
	 * @param string $action one out of several action names, see docs
	 * @return $this current widget
	 */

	public function setSelectedAction( $action ) {
		$this->selectedAction = $this->isValidAction( $action );
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
	 * Normalizes and validates provided name of an action to perform in current
	 * request.
	 *
	 * Supported actions are
	 *  * `add` for adding new user
	 *  * `edit` for editing existing user
	 *  * `view` for inspecting an existing user's details
	 *  * `delete` for deleting existing user
	 *  * `cancel` for cancelling any previous action
	 *  * `unlock` for unlocking a locked existing user
	 *  * `lock` for locking an unlocked existing user
	 *  * 'activate' for sending activation mail to some locked existing user's
	 *    email address
	 *
	 * @throws \InvalidArgumentException on providing invalid or unknown name
	 * @param string $action name of action to normalize and validate
	 * @return string normalized name of action
	 */

	protected function isValidAction( $action ) {
		$action = strtolower( trim( $action ) );

		if ( !in_array( $action, array( 'add', 'edit', 'view', 'delete', 'cancel', 'unlock', 'lock', 'activate' ) ) ) {
			throw new \InvalidArgumentException( 'invalid action' );
		}

		return $action;
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
	 * Detects if current user is authorized to use this widget.
	 *
	 * @return bool true if user is authorized, false otherwise
	 */

	protected function isUserAuthorized() {
		if ( !user::current()->isAuthenticated() )
			return false;

		if ( !user::current()->is( 'administrator' ) )
			return false;

		return true;
	}

	/**
	 * Detects mode of widget in current request by checking some implicit
	 * parameters unless caller has provided parameters explicitly.
	 *
	 * @return array tuple consisting of requested action and userID
	 */

	protected function detectMode() {
		$selectors = application::current()->selectors;

		if ( is_null( $this->selectedUserId ) ) {
			// need to implicitly detect a user's ID
			if ( ctype_digit( trim( $selectors[0] ) ) ) {
				$userId = intval( array_shift( $selectors ) );
			} else {
				$userId = false;
			}
		} else {
			$userId = intval( $this->selectedUserId );
		}

		if ( is_null( $this->selectedAction ) ) {
			try {
				$action = $this->isValidAction( trim( $selectors[0] ) );
				array_shift( $selectors );
			} catch ( \InvalidArgumentException $e ) {
				$action = $userId ? 'view' : is_integer( $userId ) ? 'add' : null;
			}
		} else {
			$action = $this->selectedAction;
		}

		return array( $action, $userId );
	}

	/**
	 * Detects if widget is requested for listing users.
	 *
	 * @return bool true if widget is expected to list users, false on requesting to operate on single user
	 */

	protected function isListing() {
		list( $action, $userId ) = $this->detectMode();

		switch ( $action ) {
			case 'list' :
				return true;
			case 'add' :
				return false;
			default :
				if ( $action )
					return !is_integer( $userId );

				return true;
		}
	}

	/**
	 * Registers code of some control to embed in panel related to current
	 * widget.
	 *
	 * @param string $name name of control (might be used to replace/remove it later)
	 * @param string $code rendered output of control
	 * @return $this current widget
	 */

	protected function setPanelControl( $name, $code ) {
		$name = strtolower( trim( $name ) );

		if ( $code ) {
			$this->panelControls[$name] = strval( $code );
		} else {
			unset( $this->panelControls[$name] );
		}

		return $this;
	}

	/**
	 * Creates HTML form used to ask user for providing current and new password
	 * to set.
	 *
	 * This form should provide a user's properties "loginname", "name", "email"
	 * and "password" (with its repetition in "repeat"). In addition it might
	 * manage "lock" including to send unlock mail to given mail address (see
	 * below).
	 *
	 * The form should use property "submit" with proper values for selecting
	 * actions (see docs on isValidAction() for complete list):
	 *
	 *   "save" for saving changes to existing user or create new user
	 *   "cancel" for reverting any changes to existing user
	 *   "unlock" for sending unlock mail to existing user
	 *
	 * @param string $formName name of HTML form
	 * @param string[] $user properties of user as read from datasource
	 * @return html_form created instance of HTML form
	 */

	protected function createForm( $formName, $user = array() ) {
		return html_form::create( $formName )
			->setTexteditRow( 'name', \de\toxa\txf\_L('full name'), input::vget( 'name', $user['name'] ) )
			->setTexteditRow( 'loginname', \de\toxa\txf\_L('login name'), input::vget( 'loginname', $user['loginname'] ) )
			->setPasswordRow( 'password', \de\toxa\txf\_L('password'), '' )
			->setPasswordRow( 'repeat', \de\toxa\txf\_L('repeat password'), '' )
			->setTexteditRow( 'email', \de\toxa\txf\_L('e-mail'), input::vget( 'email', $user['email'] ) )
			->setButtonRow( 'submit', \de\toxa\txf\_L('Save User'), 'save' )
			->setButtonRow( 'submit', \de\toxa\txf\_L('Cancel'), 'cancel' );
	}

	/**
	 * Generates databrowser for listing available users for managing.
	 *
	 * @param string $formName name of control to use (e.g. as class name)
	 * @param browseable $list list of users
	 * @param object $urls URLs to use in properties edit, add and delete (containing %s to be replaced by a user's ID)
	 * @return databrowser
	 */

	protected function createBrowser( $formName, browseable $list, $urls ) {
		$this->setPanelControl( 'add', markup::link( $urls->add, \de\toxa\txf\_L('add user') ) );

		return databrowser::create( $list, \de\toxa\txf\_L('There is no user to be listed here.'), $formName )
			->addColumn( 'loginname', \de\toxa\txf\_L('login name'), true )
			->addColumn( 'name', \de\toxa\txf\_L('name'), true )
			->addColumn( 'email', \de\toxa\txf\_L('email'), true )
			->setRowCommander( function( $id, $record ) use ( $urls ) {
				return implode( ' ', array_filter( array(
                           markup::link( sprintf( $urls->edit, $id ), \de\toxa\txf\_L('edit') ),
                           $record['uuid'] !== user::current()->getUuid() ? markup::link( sprintf( $urls->delete, $id ), \de\toxa\txf\_L('delete') ) : '',
                       ) ) );
			} )
			->setPagerVolatility( 'none' );
	}

	/**
	 * Processes input while editing/adding user record.
	 *
	 * @param sql_user $provider provider used on creating new user record
	 * @param int|false $userId ID of user to edit, false/0 on adding new user
	 * @return sql_user|null edited or created user, null if creating user failed
	 */

	protected function processInputOnEditing( $provider, $userId )
	{
		if ( $userId ) {
			$user = user::load( $userId );
			$userData = array(
				'id'        => $user->getID(),
				'loginname' => $user->getLoginName(),
				'name'      => $user->getName(),
				'email'     => $user->getProperty( 'email' ),
			);
		} else {
			$user = null;
			$userData = array();
		}


		$form = $this->getForm( $userData );
		if ( $form->hasInput() ) {

			if ( input::vget( 'submit' ) == 'cancel' ) {
				txf::redirectTo( $this->getUrls()->list );
			}

			/*
			 * read in and normalize all provided information on user
			 */

			$loginName = $user ? $userData['loginname'] : trim( input::vget( 'loginname' ) );
			$name      = trim( input::vget( 'name' ) );
			$email     = trim( input::vget( 'email' ) );
			$passwordA = trim( input::vget( 'password' ) );
			$passwordB = trim( input::vget( 'repeat' ) );


			/*
			 * validate all information on user
			 */

			if ( $loginName === '' )
				$form->setRowError( 'loginname', \de\toxa\txf\_L('Provide login name of user!') );
			else if ( strlen( $loginName ) > 64 )
				$form->setRowError( 'loginname', \de\toxa\txf\_L('Provided login name is too long!') );

			if ( $name && strlen( $name ) > 128 )
				$form->setRowError( 'loginname', \de\toxa\txf\_L('Provided full name is too long!') );

			if ( $email ) {
				if ( strlen( $name ) > 128 )
					$form->setRowError( 'loginname', \de\toxa\txf\_L('Provided mail address is too long!') );
				else if ( !\de\toxa\txf\mail::isValidAddress( $email ) )
					$form->setRowError( 'email', \de\toxa\txf\_L('Provided mail address is invalid!') );
			}

			// validate optionally provided password
			if ( !$user || $passwordA || $passwordB ) {
				if ( $passwordA === '' || $passwordB === '' ) {
					if ( $user )
						$form->setRowError( 'password', \de\toxa\txf\_l('Provide new password twice for excluding typos.') );
					else
						$form->setRowError( 'password', \de\toxa\txf\_l('Provide password of new user and repeat for excluding typos.') );
				} else if ( $passwordA !== $passwordB )
					$form->setRowError( 'password', \de\toxa\txf\_L('Doubly entered passwords don\'t match.') );
				else {
					try {
						if ( is_callable( $this->passwordValidator ) )
							call_user_func( $this->passwordValidator, $passwordA );
						else
							$this->passwordValidatorDefault( $passwordA );
					} catch ( \InvalidArgumentException $e ) {
						$form->setRowError( 'password', $e->getMessage() );
					}
				}
			}


			/*
			 * save changes to datasource
			 */

			$hasError = $form->hasAnyRowError();

			if ( !$hasError ) {
				exception::enterSensitive();

				if ( $user ) {
					try {
						$user->datasource()->transaction()->wrap( function( datasource\connection $conn ) use( $user, $name, $email, $passwordA ) {
							$user->setProperty( 'name', $name );
							$user->setProperty( 'email', $email );

							if ( trim( $passwordA ) !== '' ) {
								$user->changePassword( $passwordA );

								if ( $user->getUUID() === user::current()->getUUID() ) {
									try {
										user::current()->authenticate( $passwordA );
									} catch ( unauthorized_exception $e ) {
										view::flash( \de\toxa\txf\_L('Updating current session for using changed password failed. Probably you need to login, again.'), 'error' );
									}
								}
							}

							view::flash( \de\toxa\txf\_L('Successfully changed information on selected user.') );

							return true;
						} );
					} catch ( \Exception $e ) {
						$hasError = true;

						view::flash( \de\toxa\txf\_L('Failed to save information on user in datasource.'), 'error' );
					}
				} else {
					try {
						$user = $provider->create( array(
					                                 'loginname' => $loginName,
					                                 'name' => $name,
					                                 'password' => $passwordA,
					                                 'email' => $email,
					                                 'lock' => '',
				                                 ) );

						view::flash( \de\toxa\txf\_L('Successfully created new user.') );
					} catch ( \Exception $e ) {
						$hasError = true;

						view::flash( \de\toxa\txf\_L('Failed to create new user record in datasource.'), 'error' );
					}
				}

				exception::leaveSensitive();
			}


			if ( !$hasError ) {
				txf::redirectTo( $this->getUrls()->list );
			}
		}


		return $user;
	}

	/**
	 * Processes input of widget updating its internal state.
	 *
	 * @throws http_exception on trying to use widget without authorization
	 * @return $this current instance
	 */

	public function processInput() {

		if ( !$this->isUserAuthorized() )
			throw new http_exception( 403, \de\toxa\txf\_L('You must not manage users!') );

		$provider = user::getProvider();
		if ( !( $provider instanceof sql_user ) )
			throw new http_exception( 400, \de\toxa\txf\_L('This manager is suitable for managing SQL-based users, only!') );


		list( $action, $userId ) = $this->detectMode();

		if ( $this->isListing() )  {
			$this->getBrowser()->processInput();
		} else {
			switch ( $action ) {
				case 'edit' :
				case 'add' :
					$this->processInputOnEditing( $provider, $userId );
					break;

				case 'delete' :
					if ( $userId === user::current()->getID() )
						throw new http_exception( 403, \de\toxa\txf\_L('Deleting current user account rejected.') );

					user::load( $userId )->delete();

					txf::redirectTo( $this->getUrls()->list );
					break;

				default :
					// TODO implement all else actions (lock, unlock, ...)
					txf::redirectTo( $this->getUrls()->list );
			}
		}

		return $this;
	}

	/**
	 * Retrieves code for embedding widget in current view.
	 *
	 * @return string code embeddable in view
	 */

	public function getCode() {
		return strval( $this->isListing() ? $this->getBrowser()->getCode() . markup::block( implode( ' ', array_filter( $this->panelControls ) ), 'panel' ) : $this->getForm()->getCode() );
	}
}

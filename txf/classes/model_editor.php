<?php


namespace de\toxa\txf;

/**
 * commonly model instance editor/viewer
 *
 * @author Thomas Urban
 */

class model_editor
{
	/**
	 * link to datasource model is stored in
	 *
	 * @var datasource\connection
	 */

	protected $datasource;

	/**
	 * form to use on editing
	 *
	 * @var html_form
	 */

	protected $form;

	/**
	 * desired name/ID of form managed in editor
	 *
	 * @var string
	 */

	protected $formName;

	/**
	 * class of model to be edited
	 *
	 * @var \ReflectionClass
	 */

	protected $class;

	/**
	 * model instance to be edited
	 *
	 * @var model
	 */

	protected $item;

	/**
	 * set of properties to be actually available in current editor
	 *
	 * @var array
	 */

	protected $enabled = array();

	/**
	 * set of additional permissions of current user on editing
	 *
	 * @var array
	 */

	protected $may = array( 'edit' => true );

	/**
	 * map of property names into definition of related editor element
	 *
	 * supported properties per element in this array are:
	 *  - type, one of textedit, password, selector, ... (choosing form element template)
	 *  - label, humand-readable description of field
	 *  - data, optional data to provide on rendering form's element, e.g. list of selectables
	 *  - normalizer, optional callback to invoke for normalizing/transforming input value on saving
	 *  - validator, optional callback to invoke for validating input on saving
	 *  - hint, optional text to show as hint next to form element
	 *
	 * @var array
	 */

	protected $fields = array();

	/**
	 * collection of error messages per field/property errors
	 *
	 * @var array
	 */

	protected $errors = array();

	/**
	 * set of implicitly or explicitly fixed properties
	 *
	 * @var array[string->mixed]
	 */

	protected $fixedValues = null;

	/**
	 * Marks if editor was called for creating new item (instead of adjusting
	 * existing one).
	 *
	 * @var boolean
	 */

	protected $onCreating = false;



	protected function __construct( datasource\connection $datasource = null, $formName = null )
	{
		if ( $datasource === null )
			$datasource = datasource::getDefault();

		$this->datasource = $datasource;
		$this->formName   = _1($formName,'model_editor');
	}

	/**
	 *
	 * @param \de\toxa\txf\datasource\connection $datasource
	 * @param \ReflectionClass $model
	 * @return model_editor
	 */

	public static function createOnModel( datasource\connection $datasource = null, \ReflectionClass $model, $formName = null )
	{
		$editor = new static( $datasource, $formName, $model, null );

		$editor->class = $model;
		$editor->item  = null;

		return $editor;
	}

	public static function createOnItem( datasource\connection $datasource = null, model $item, $formName = null )
	{
		$model  = new \ReflectionClass( $item );
		$editor = new static( $datasource, $formName, $model, $item );

		$editor->class = $model;
		$editor->item  = $item;

		return $editor;
	}

	public static function create( datasource\connection $datasource, \ReflectionClass $model, model $item = null, $formName = null )
	{
		if ( !$item )
			return static::createOnModel( $datasource, $model, $formName );

		if ( !$model->isInstance( $item ) )
			throw new \InvalidArgumentException( _L('Selected item is not instance of requested model.') );

		return static::createOnItem( $datasource, $item, $formName );
	}

	public function enable( $property, $enabled = true )
	{
		$this->enabled[$property] = !!$enabled;

		return $this;
	}

	/**
	 * Grants (or revokes) permission to delete currently edited item.
	 *
	 * @param boolean $revoke true to revoke instead of granting permission
	 * @return model_editor current editor instance
	 */

	public function mayDelete( $revoke = false )
	{
		$this->may['delete'] = !$revoke;

		return $this;
	}

	/**
	 * Grants (or revokes) permission to actually modify currently edited item.
	 *
	 * @param boolean $revoke true to revoke instead of granting permission
	 * @return model_editor current editor instance
	 */

	public function mayEdit( $revoke = false )
	{
		$this->may['edit'] = !$revoke;

		return $this;
	}

	public function isCreating()
	{
		return !!$this->onCreating;
	}

	public function describeField( $property, $label, model_editor_element $type = null )
	{
		if ( $type === null )
			$type = new model_editor_text();

		$this->fields[$property] = array(
									'label' => $label,
									'type'  => $type,
									);

		return $this;
	}

	public function isEditable()
	{
		return user::current()->isAuthenticated();
	}

	public function useForm( html_form $form )
	{
		$this->form = $form;

		return $this;
	}

	/**
	 * Retrieves form to use on current editor instance.
	 *
	 * @return html_form
	 */

	public function form()
	{
		if ( !( $this->form instanceof html_form ) )
			$this->form = html_form::create( $this->formName );

		return $this->form;
	}

	/**
	 * Fetches all currently fixed data.
	 *
	 * @return array
	 */

	protected function getFixed()
	{
		if ( is_null( $this->fixedValues ) )
		{
			$this->fixedValues = input::vget( '_fix' );

			if ( !is_array( $this->fixedValues ) )
				$this->fixedValues = array();
		}

		return $this->fixedValues;
	}

	/**
	 * Detects whether selected property is currently fixed or not.
	 *
	 * @param string $property name of property to test
	 * @return boolean true if property is fixed, false otherwise
	 */

	protected function isFixedValue( $property )
	{
		$fixed = $this->getFixed();

		return is_array( $fixed ) && array_key_exists( $property, $fixed );
	}

	/**
	 * Explicitly requests to fix selected properties.
	 *
	 * @param string $property first property to fix, add more on demand
	 * @return model_editor current editor instance
	 */

	public function fixOnEditing( $property )
	{
		$this->getFixed();

		$properties = func_get_args();

		foreach ( $properties as $property )
			if ( !array_key_exists( $property, $this->fixedValues ) && $this->item )
				$this->fixedValues[$property] = $this->item->__get( $property );

		return $this;
	}

	public function fixProperty( $property, $value )
	{
		$this->getFixed();

		if ( !array_key_exists( $property, $this->fixedValues ) )
			$this->fixedValues[$property] = $value;

		return $this;
	}

	public function __get( $property )
	{
		$fixed = $this->getFixed();

		if ( is_array( $fixed ) && array_key_exists( $property, $fixed ) )
			return $fixed[$property];

		return input::vget( $this->propertyToField( $property ), $this->item ? $this->item->__get( $property ) : null );
	}

	public function processInput( $validatorCallback = null )
	{
		if ( $this->isEditable() && $this->form()->hasInput() )
		{
			switch ( input::vget( '_cmd' ) )
			{
				case 'cancel' :
					// permit closing editor due to user requesting to cancel editing
					return true;

				case 'delete' :
					// delete current edited item
					if ( $this->may['delete'] && $this->item )
					{
						if ( $this->item->delete() )
							$this->item = null;

						return true;
					}

					view::flash( _L('You must not delete this item!'), 'error' );
					return false;

				case 'save' :
					// extract some protected properties from current instance to be used in transaction-wrapped callback
					$ctx     = $this;
					$class   = $this->class;
					$source  = $this->datasource;
					$fields  = $this->fields;
					$enabled = $this->enabled;
					$item    = $this->item;
					$errors  = array();

					$this->onCreating = !$this->hasItem();
					if ( !$this->onCreating && !$this->may['edit'] )
					{
						view::flash( _L('You must not edit this item!'), 'error' );
						return false;
					}

					// wrap modification on model in transaction
					$success = $source->transaction()->wrap( function() use ( $ctx, $class, $source, $fields, $enabled, &$item, &$errors, $validatorCallback )
					{
						$properties = array();

						foreach ( $fields as $property => $definition )
							if ( !count( $enabled ) || !@$enabled[$property] )
								try
								{
									// normalize input
									$input = call_user_func( array( $definition['type'], 'normalize' ), $ctx->__get( $property ), $property, $ctx );

									// validate input
									$success = call_user_func( array( $definition['type'], 'validate' ), $input, $property, $ctx );

									// save input if valid
									if ( $success )
									{
										$properties[$property] = $input;

										if ( $item )
											$item->__set( $property, $input );
									}
									else
										$errors[$property] = _L('Your input is invalid!');
								}
								catch ( \Exception $e )
								{
									$errors[$property] = $e->getMessage();
								}

						if ( count( $errors ) )
							return false;

						if ( is_callable( $validatorCallback ) )
						{
							$localErrors = call_user_func( $validatorCallback, $properties, $errors );
							if ( $localErrors === false || is_string( $localErrors ) || ( is_array( $localErrors ) && count( $localErrors ) ) )
							{
								if ( is_array( $localErrors ) )
									$errors = array_merge( $errors, $localErrors );
								else if ( is_string( $localErrors ) )
									view::flash( $localErrors, 'error' );

								return false;
							}
						}

						if ( !$item )
							$item = $class->getMethod( 'create' )->invoke( null, $source, $properties );

						return true;
					} );

					// transfer adjusted properties back to protected scope of current instance
					$this->errors = $errors;
					if ( !$this->item && $item )
						$this->item = $item;


					if ( $success )
					{
						// permit closing editor after having saved all current input
						view::flash( _L('Your changes have been saved.') );
						return true;
					}

					view::flash( _L('Failed to save your changes!'), 'error' );
			}
		}

		// don't close editor
		return false;
	}

	public function render()
	{
		return $this->isEditable() ? $this->renderEditable() : $this->renderReadonly();
	}

	public function renderEditable()
	{
		if ( !$this->isEditable() )
			throw new \LogicException( _L('Model editor is not enabled.') );


		$form = $this->form();

		if ( $this->item )
			$form->setHidden( 'id', $this->item->id );

		if ( !array_key_exists( '_referer', $this->fields ) )
			$form->setHidden( '_referer', input::vget( '_referer' ) );

		$fixed = array();

		foreach ( $this->fields as $property => $definition )
			if ( !count( $this->enabled ) || !@$this->enabled[$property] )
			{
				$label = $definition['label'];
				$input = $this->__get( $property );
				$field = $this->propertyToField( $property );

				if ( $this->isFixedValue( $property ) )
				{
					$fixed[$property] = $input;

					call_user_func( array( $definition['type'], 'renderStatic' ), $form, $field, $input, $label, $this );
				}
				else
				{
					call_user_func( array( $definition['type'], 'render' ), $form, $field, $input, $label, $this );

					if ( array_key_exists( $property, $this->errors ) )
						$form->setRowError( $field, $this->errors[$property] );

					$form->setRowHint( $field, @$definition['hint'] );
					$form->setRowIsMandatory( $field, call_user_func( array( $definition['type'], 'isMandatory' ) ) );
				}
			}

		if ( count( $fixed ) )
			$form->setHidden( '_fix', $fixed );

		// compile buttons to show at end of editor
		if ( !$this->item || $this->may['edit'] )
			$form->setButtonRow( '_cmd', $this->item ? _L('Save') : _L('Create'), 'save' );

		$form->setButtonRow( '_cmd', _L('Cancel'), 'cancel' );

		if ( $this->item && $this->may['delete'] )
			$form->setButtonRow( '_cmd', _L('Delete'), 'delete' );


		// return HTML code of editor
		return $form->getCode();
	}

	public function propertyToField( $propertyName )
	{
		return $propertyName;
	}

	public function renderReadonly()
	{
		if ( !$this->item )
			throw new http_exception( 400, _L('Your request is not including selection of item to be displayed.') );

		return html::arrayToCard( $this->item->published(),
					strtolower( basename( strtr( $this->class->getName(), '\\', '/' ) ) ) . 'Details',
					$this->item->formatter(), $this->item->formatter( false ), _L('-') );
	}

	public function hasError()
	{
		return !!count( $this->errors );
	}

	public function hasItem()
	{
		return !!$this->item;
	}

	/**
	 * Retrieves model managed in editor, if any.
	 *
	 * @return model
	 */

	public function item()
	{
		return $this->item;
	}

	public function selectItem( $id )
	{
		if ( $this->item )
			throw new \LogicException( _L('Editor is already operating on model instance.') );

		if ( $id !== null )
		{
			$this->item = $this->class->getMethod( 'select' )->invoke( null, $this->datasource, $id );
			if ( !$this->item )
				return false;
		}

		return true;
	}

	public function description( $property )
	{
		return array_key_exists( $property, $this->fields ) ? $this->fields[$property] : false;
	}
}

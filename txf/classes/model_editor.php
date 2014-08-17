<?php


namespace de\toxa\txf;

/**
 * Wraps description of single field of editor.
 *
 * @package de\toxa\txf
 */

class model_editor_field
{
	protected $label;
	protected $type;
	protected $custom = false;

	/**
	 * @param string|null $label label to show next to field
	 * @param model_editor_element $type controller managing this field
	 * @param bool $custom true if field isn't related to some property of model
	 *        in editor
	 */

	public function __construct( $label = null, model_editor_element $type, $custom = false )
	{
		$this->label  = trim( $label );
		$this->type   = $type;
		$this->custom = !!$custom;
	}

	/**
	 * Retrieves label to show next to editor field.
	 *
	 * @return string
	 */

	public function label() { return $this->label; }

	/**
	 * Retrieves type handler of current field.
	 *
	 * @return model_editor_element
	 */

	public function type() { return $this->type; }

	/**
	 * Indicates whether field is representing custom information or is related
	 * to property of model associated with editor.
	 *
	 * @return bool
	 */

	public function isCustom() { return $this->custom; }
}

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
	 * @var array[model_editor_field]
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

	/**
	 * Set of field names describing desired order in editor.
	 *
	 * @var array
	 */

	protected $sortingOrder = null;



	protected function __construct( datasource\connection $datasource = null, $formName = null )
	{
		if ( $datasource === null )
			$datasource = datasource::selectConfigured( 'default' );

		$this->datasource = $datasource;
		$this->formName   = _1($formName,'model_editor');
	}

	/**
	 *
	 * @param $datasource \de\toxa\txf\datasource\connection
	 * @param $model \ReflectionClass
	 * @param $formName string
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
	 * Retrieves datasource editor is configured to operate on.
	 *
	 * If editor is operating on some existing item that one's data source is
	 * retrieved. Otherwise this method is returning data source provided on
	 * creating editor.
	 *
	 * @return datasource\connection
	 */

	public function source()
	{
		return $this->datasource;
	}

	/**
	 * Grants (or revokes) permission to delete currently edited item.
	 *
	 * @param boolean $grant true to grant permission to delete, false otherwise
	 * @return model_editor current editor instance
	 */

	public function mayDelete( $grant = true )
	{
		$this->may['delete'] = !!$grant;

		return $this;
	}

	/**
	 * Grants (or revokes) permission to actually modify currently edited item.
	 *
	 * @param boolean $grant true to grant permission to edit, false otherwise
	 * @return $this current editor instance
	 */

	public function mayEdit( $grant = true )
	{
		$this->may['edit'] = !!$grant;

		return $this;
	}

	/**
	 * Detects if editor is going to create new item on saving.
	 *
	 * @return bool
	 */

	public function isCreating()
	{
		return !!$this->onCreating;
	}

	/**
	 * @deprecated use addField() instead
	 *
	 * @param $property
	 * @param null $label
	 * @param model_editor_element $type
	 * @return $this
	 */

	public function describeField( $property, $label = null, model_editor_element $type = null )
	{
		return $this->addField( $property, $label, $type );
	}

	/**
	 * @deprecated use addCustomField() instead
	 *
	 * @param $fieldName
	 * @param $label
	 * @param model_editor_element $type
	 * @return $this
	 */

	public function describeCustomField( $fieldName, $label, model_editor_element $type )
	{
		return $this->addCustomField( $fieldName, $label, $type );
	}

	/**
	 * Adds field for editing named property of associated item using provided
	 * editor element.
	 *
	 * @param string $property name of property to edit using provided editor element
	 * @param string|null $label label to use on field
	 * @param model_editor_element $type editor element to use on adjusting property
	 * @return $this
	 */

	public function addField( $property, $label = null, model_editor_element $type = null )
	{
		$property = trim( $property );

		if ( !$this->class->getMethod( 'isPropertyName' )->invoke( null, $property ) ) {

			$isRelation = false;
			if ( $type instanceof model_editor_related ) {
				try {
					$this->class->getMethod( 'relation' )->invoke( null, $property );
					$isRelation = true;
				} catch ( \InvalidArgumentException $e ) {
				}
			}

			if ( !$isRelation )
				throw new \InvalidArgumentException( 'no such property in associated model: ' . $property );
		}

		if ( $type === null )
			$type = new model_editor_text();

		$type->setEditor( $this );

		if ( $label === null )
			$label = $this->class->getMethod( 'nameToLabel' )->invoke( null, $property );

		if ( trim( $label ) === '' )
			throw new \InvalidArgumentException( 'missing label on editor property: ' . $property );


		$this->fields[$property] = new model_editor_field( $label, $type );


		if ( $this->item )
			// ensure field may act on selecting particular item (done before)
			$type->onSelectingItem( $this, $this->item );


		return $this;
	}

	/**
	 * Adds field for editing custom information in editor.
	 *
	 * This method is to be used on adding field not related to any property of
	 * item in editor.
	 *
	 * @param string $fieldName internal name of field to use
	 * @param string $label label of field
	 * @param model_editor_element $type editor element to use for adjusting field's value
	 * @return $this
	 */

	public function addCustomField( $fieldName, $label, model_editor_element $type )
	{
		$fieldName = trim( $fieldName );
		if ( !$fieldName )
			throw new \InvalidArgumentException( 'missing/invalid name on custom editor field' );

		if ( $type === null )
			throw new \InvalidArgumentException( 'missing control on custom editor field: ' . $fieldName );

		if ( $label === null )
			throw new \InvalidArgumentException( 'missing label on custom editor field: ' . $fieldName );

		if ( trim( $label ) === '' )
			throw new \InvalidArgumentException( 'missing label on editor property: ' . $fieldName );


		$this->fields[$fieldName] = new model_editor_field( $label, $type, true );

		return $this;
	}

	/**
	 * @throws \InvalidArgumentException on trying to select missing field
	 * @param string $fieldName name of field to retrieve
	 * @return model_editor_field selected field
	 */

	public function getField( $fieldName ) {
		if ( array_key_exists( $fieldName, $this->fields ) ) {
			return $this->fields[$fieldName];
		}

		throw new \InvalidArgumentException( 'no such field in editor: ' . $fieldName );
	}

	/**
	 * Indicates whether editor is marked editable.
	 *
	 * @return bool
	 */

	public function isEditable()
	{
		return user::current()->isAuthenticated() && $this->may['edit'];
	}

	/**
	 * Selects form to embed editor in instead of individual form implicitly.
	 *
	 * @param html_form $form
	 * @return $this
	 */

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
	 * Fetches all currently fixed properties of item.
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
	 * This method is fixing selected properties of item unless item is created.
	 * Every fixed property is declared to keep its current value then, but
	 * provide edit option when creating item.
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

	/**
	 * Declares provided property to have fixed value as given.
	 *
	 * This method is designed to assure a property's value isn't undefined or
	 * changed on creating or editing item.
	 *
	 * @param string $property
	 * @param mixed $value
	 * @return $this
	 */

	public function fixProperty( $property, $value )
	{
		$this->getFixed();

		if ( !array_key_exists( $property, $this->fixedValues ) )
			$this->fixedValues[$property] = $value;

		return $this;
	}

	/**
	 * Reads value of property of item in editor.
	 *
	 * This getter is asking several sources to provide selected property's
	 * value, testing sources in this order:
	 *
	 * # First, property is looked up in a special set of fixed/immutable values.
	 * # Next, current (volatile) input of script is checked to contain some matching value.
	 * # Then all fields of editor are checked for providing some matching value.
	 * # Finally, item in editor is directly requested to provide value.
	 *
	 * @param string $property name of property to fetch
	 * @return mixed value of fetched property
	 */

	public function __get( $property )
	{
		// 1. try optional set of fixed/immutable properties
		$fixed = $this->getFixed();
		if ( is_array( $fixed ) && array_key_exists( $property, $fixed ) )
			return $fixed[$property];

		// 2. try actual input of current script
		$input = input::vget( $this->propertyToField( $property ), null );
		if ( !is_null( $input ) )
			return $input;

		// 3. try manager for related field of editor
		foreach ( $this->fields as $name => $field ) {
			/** @var model_editor_field $field */
			if ( $name === $property ) {
				$value = $field->type()->onLoading( $this, $this->item, $property );
				if ( !is_null( $value ) )
					return $value;

				break;
			}
		}

		// 4. try item in editor
		if ( $this->item )
			return $this->item->__get( $property );

		// fail ... there is no value for selected property
		return null;
	}

	/**
	 * Processes input on current editor.
	 *
	 * @param callable $validatorCallback
	 * @return bool|string false on input failures requiring user action,
	 *                     "saved" on input successfully saved to data source,
	 *                     "cancel" on user pressing cancel button,
	 *                     "delete" on user deleting record
	 */

	public function processInput( $validatorCallback = null )
	{
		if ( $this->isEditable() && $this->form()->hasInput() )
		{
			switch ( input::vget( '_cmd' ) )
			{
				case 'cancel' :
					// permit closing editor due to user requesting to cancel editing
					return 'cancel';

				case 'delete' :
					// delete current edited item
					if ( $this->may['delete'] && $this->item )
					{
						$ctx    = $this;
						$item   = $this->item;
						$fields = $this->fields;

						$this->datasource->transaction()->wrap( function() use ( $ctx, $item, $fields )
						{
							foreach ( $fields as $field )
								/** @var model_editor_field $field */
								$field->type()->onDeleting( $ctx, $item );

							$item->delete();

							return true;
						} );

						$this->item = null;

						return 'delete';
					}

					view::flash( _L('You must not delete this item.'), 'error' );
					return false;

				case 'save' :
					// extract some protected properties from current instance to be used in transaction-wrapped callback
					$ctx     = $this;
					$class   = $this->class;
					$source  = $this->datasource;
					$fields  = $this->fields;
					$enabled = $this->enabled;
					$item    = $this->item;
					$fixed   = $this->getFixed();
					$errors  = array();

					$this->onCreating = !$this->hasItem();
					if ( !$this->onCreating && !$this->may['edit'] )
					{
						view::flash( _L('You must not edit this item.'), 'error' );
						return false;
					}

					// wrap modification on model in transaction
					$success = $source->transaction()->wrap( function() use ( $ctx, $class, $source, $fields, $enabled, $fixed, &$item, &$errors, $validatorCallback )
					{
						$properties = array();

						foreach ( $fields as $property => $definition ) {
							/** @var model_editor_field $definition */
							if ( !count( $enabled ) || !@$enabled[$property] )
								try
								{
									// normalize input
									$input = call_user_func( array( $definition->type(), 'normalize' ), $ctx->__get( $property ), $property, $ctx );

									// validate input
									$success = call_user_func( array( $definition->type(), 'validate' ), $input, $property, $ctx );

									// save input if valid
									if ( $success )
										$properties[$property] = $input;
									else
										$errors[$property] = _L('Your input is invalid.');
								}
								catch ( \Exception $e )
								{
									$errors[$property] = $e->getMessage();
								}
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
							// will be creating new item below, but ensure to
							// use fixed initial values provided additionally
							foreach ( $fixed as $name => $value )
								$properties[$name] = $value;


						// optionally pre-process saving properties of item
						foreach ( $fields as $field )
							/** @var model_editor_field $field */
							$properties = $field->type()->beforeStoring( $ctx, $item, $properties );

						if ( $item )
							// update properties of existing item
							foreach ( $properties as $name => $value )
								$item->__set( $name, $value );
						else {
							// create new item
							$item = $class->getMethod( 'create' )
							              ->invoke( null, $source, $properties );

							// tell all elements to have item now
							foreach ( $fields as $field )
								/** @var model_editor_field $field */
								$field->type()->onSelectingItem( $ctx, $item );
						}

						// optionally post-process saving properties of item
						foreach ( $fields as $field )
							/** @var model_editor_field $field */
							$item = $field->type()->afterStoring( $ctx, $item, $properties );

						return true;
					} );

					// transfer adjusted properties back to protected scope of current instance
					$this->errors = $errors;

					// write back item created or probably replaced by afterStoring() call in transaction
					$this->item = $item;


					if ( $success )
					{
						// permit closing editor after having saved all current input
						view::flash( _L('Your changes have been saved.') );
						return 'saved';
					}

					view::flash( _L('Failed to save your changes.'), 'error' );
			}
		}

		// don't close editor
		return false;
	}

	/**
	 * Renders editor using renderEditable() or renderStatic() internally,
	 * depending on whether editor is marked editable or not
	 *
	 * @return string
	 * @throws http_exception
	 */

	public function render()
	{
		return $this->isEditable() ? $this->renderEditable() : $this->renderReadonly();
	}

	/**
	 * Renders editor with fields providing controls for editing properties of
	 * item in editor.
	 *
	 * @return string
	 * @throws \LogicException on trying to render editable view of editor unless editing has been enabled
	 */

	public function renderEditable()
	{
		if ( !$this->isEditable() )
			throw new \LogicException( _L('Model editor is not enabled.') );


		$form = $this->form();

		if ( $this->item )
			$form->setHidden( 'id', $this->item->getReflection()->getMethod( "serializeId" )->invoke( null, $this->item->id() ) );

		if ( !array_key_exists( '_referrer', $this->fields ) )
			$form->setHidden( '_referrer', input::vget( '_referrer' ) );

		$fixed = array();

		foreach ( $this->fields as $property => $field ) {
			/** @var model_editor_field $field */
			if ( !count( $this->enabled ) || !@$this->enabled[$property] )
			{
				$label = $field->label();
				$name  = $this->propertyToField( $property );

				$input = $field->isCustom() ? null : $this->__get( $property );

				if ( $this->isFixedValue( $property ) )
				{
					$fixed[$property] = $input;

					$field->type()->renderStatic( $form, $name, $input, $label, $this );
				}
				else
				{
					$field->type()->render( $form, $name, $input, $label, $this );

					if ( array_key_exists( $property, $this->errors ) )
						$form->setRowError( $name, $this->errors[$property] );
				}
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

		if ( $this->sortingOrder )
			$form->setSortingOrder( $this->sortingOrder );


		// return HTML code of editor
		return $form->getCode();
	}

	/**
	 * Maps name of property into name of related field of editor.
	 *
	 * @param string $propertyName
	 * @return string
	 */

	public function propertyToField( $propertyName )
	{
		return $propertyName;
	}

	/**
	 * Renders editor with fields limited to displaying values instead of
	 * providing controls for editing them.
	 *
	 * @return string
	 * @throws http_exception on trying to render without selecting item first
	 */

	public function renderReadonly()
	{
		if ( !$this->item )
			throw new http_exception( 400, _L('Your request is not including selection of item to be displayed.') );

		$fields = $this->fields;
		$editor = $this;

		$modelLabelFormatter = array( $this->class->getName(), 'formatHeader' );
		$modelCellFormatter  = array( $this->class->getName(), 'formatCell' );

		$labelFormatter = function( $name ) use ( $modelLabelFormatter, $fields, $editor ) {
			if ( array_key_exists( $name, $fields ) ) {
				$label = $fields[$name]->label();
				if ( $label )
					return sprintf( '%s:', $label );
			}

			return call_user_func( $modelLabelFormatter, $name );
		};

		$cellFormatter = function( $value, $name, $record, $id ) use ( $modelCellFormatter, $fields, $editor ) {
			$field = @$fields[$name];
			/** @var model_editor_field $field */
			return $field ? $field->type()->formatValue( $name, $value, $editor ) : null;
		};

		$record = $this->item->published();

		if ( $this->sortingOrder )
			data::rearrangeArray( $record, $this->sortingOrder );

		return html::arrayToCard( $record,
					strtolower( basename( strtr( $this->class->getName(), '\\', '/' ) ) ) . 'Details',
					$cellFormatter, $labelFormatter, _L('-') );
	}

	/**
	 * Detects if editor has tracked errors or not.
	 *
	 * @return bool
	 */

	public function hasError()
	{
		return !!count( $this->errors );
	}

	/**
	 * Detects if editor is working on existing item of model or not.
	 *
	 * @return bool
	 */

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

	/**
	 * Retrieves reflection of model managed in editor.
	 *
	 * @return \ReflectionClass
	 */

	public function model()
	{
		return $this->class;
	}

	/**
	 * Associates editor instance with single item of related model.
	 *
	 * Providing null is available for convenience, too. In that case editor is
	 * actually kept unassociated with a particular item of model, but returns
	 * true here nevertheless. It's intended to call selectItem( null ) in case
	 * of trying to edit model instance that does not exist, yet. This way
	 * callers won't have to test for existing item themselves.
	 *
	 * It is possible to provide item instance instead of item's ID here. In
	 * that case editor is switching to use provided item's data source further
	 * one.
	 *
	 * Selecting item is available once, only. This method is throwing exception
	 * on trying to select another item.
	 *
	 * @param mixed $id ID or instance of item to associate with editor
	 * @return bool true on success, false on error
	 * @throws \LogicException on trying to re-associate editor
	 */

	public function selectItem( $id )
	{
		if ( $this->item )
			throw new \LogicException( _L('Editor is already operating on model instance.') );

		if ( $id !== null )
		{
			if ( is_object( $id ) && $this->class->isInstance( $id ) )
				$this->item = $id;
			else
				$this->item = $this->class->getMethod( 'select' )->invoke( null, $this->datasource, $id );

			if ( !$this->item )
				return false;

			$this->datasource = $this->item->source();
			if ( !$this->datasource )
				throw new \RuntimeException( 'item is not associated with data source' );


			foreach ( $this->fields as $field ) {
				/** @var model_editor_field $field */
				$field->type()->onSelectingItem( $this, $this->item );
			}
		}

		return true;
	}

	/**
	 * Fetches available field definition on provided property.
	 *
	 * @param string $property name of property
	 * @return model_editor_field|false
	 */

	public function description( $property )
	{
		return array_key_exists( $property, $this->fields ) ? $this->fields[$property] : false;
	}

	public function setSortingOrder( $sortingOrder )
	{
		if ( !is_array( $sortingOrder ) )
			throw new \InvalidArgumentException( 'invalid sorting order definition' );

		$this->sortingOrder = $sortingOrder;
	}
}

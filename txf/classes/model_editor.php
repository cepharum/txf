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


	protected function __construct( datasource\connection $datasource = null )
	{
		if ( $datasource === null )
			$datasource = datasource::getDefault();

		$this->datasource = $datasource;
	}

	public static function createOnModel( datasource\connection $datasource = null, \ReflectionClass $model )
	{
		$editor = new static( $datasource, $model, null );

		$editor->class = $model;
		$editor->item  = null;

		return $editor;
	}

	public static function createOnItem( datasource\connection $datasource = null, model $item )
	{
		$model  = new \ReflectionClass( $item );
		$editor = new static( $datasource, $model, $item );

		$editor->class = $model;
		$editor->item  = $item;

		return $editor;
	}

	public static function create( datasource\connection $datasource, \ReflectionClass $model, model $item = null )
	{
		if ( !$item )
			return static::createOnModel( $datasource, $model );

		if ( !$model->isInstance( $item ) )
			throw new \InvalidArgumentException( _L('Selected item is not instance of requested model.') );

		return static::createOnItem( $datasource, $item );
	}

	public function enable( $property, $enabled = true )
	{
		$this->enabled[$property] = !!$enabled;

		return $this;
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
			$this->form = html_form::create( 'model_editor' );

		return $this->form;
	}

	public function processInput()
	{
		if ( $this->isEditable() && $this->form()->hasInput() )
		{
			switch ( input::vget( '_cmd' ) )
			{
				case 'cancel' :
					// permit closing editor due to user requesting to cancel editing
					return true;

				case 'save' :
					// extract some protected properties from current instance to be used in transaction-wrapped callback
					$ctx     = $this;
					$class   = $this->class;
					$source  = $this->datasource;
					$fields  = $this->fields;
					$enabled = $this->enabled;
					$item    = $this->item;
					$errors  = array();

					// wrap modification on model in transaction
					$success = $source->transaction()->wrap( function() use ( $ctx, $class, $source, $fields, $enabled, &$item, &$errors )
					{
						$properties = array();

						foreach ( $fields as $property => $definition )
							if ( !count( $enabled ) || !@$enabled[$property] )
								try
								{
									// normalize input
									$input = input::vget( $property, $item ? $item->__get( $property ) : null );
									$input = call_user_func( array( $definition['type'], 'normalize' ), $input, $property, $ctx );

									// validate input
									$success = true;
									$success = call_user_func( array( $definition['type'], 'validate' ), $input, $property, $ctx );

									// save input if valid
									if ( $success )
									{
										if ( $item )
											$item->__set( $property, $input );
										else
											$properties[$property] = $input;
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


		if ( $this->item )
			$this->form()->setHidden( 'id', $this->item->id );

		if ( !array_key_exists( 'list', $this->fields ) )
			$this->form()->setHidden( 'list', input::vget( 'list' ) );

		foreach ( $this->fields as $property => $definition )
			if ( !count( $this->enabled ) || !@$this->enabled[$property] )
			{
				$field = $this->propertyToField( $property );
				$input = input::vget( $field, $this->item ? $this->item->__get( $property ) : null );
				$label = $definition['label'];

				call_user_func( array( $definition['type'], 'render' ), $this->form(), $field, $input, $label, $this );

				if ( array_key_exists( $property, $this->errors ) )
					$this->form()->setRowError( $field, $this->errors[$property] );

				$this->form()->setRowHint( $field, @$definition['hint'] );
				$this->form()->setRowIsMandatory( $field, call_user_func( array( $definition['type'], 'isMandatory' ) ) );
			}

		return $this->form()
			->setButtonRow( '_cmd', $this->item ? _L('Save') : _L('Create'), 'save' )
			->setButtonRow( '_cmd', _L('Cancel'), 'cancel' )
			->getCode();
	}

	protected function propertyToField( $propertyName )
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

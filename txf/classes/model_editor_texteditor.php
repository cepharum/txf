<?php


namespace de\toxa\txf;

class model_editor_texteditor extends model_editor_text
{
	protected $_rows = 5;
	protected $_columns = 60;

	public static function create( $rows = 5, $columns = 60 )
	{
		$field = parent::create();

		$field->_rows    = intval( $rows );
		$field->_columns = intval( $columns );

		return $field;
	}

	public function render( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'texteditor' ) ) );

		$form->setTextareaRow( $name, $label, $input, $this->_rows, $this->_columns, $this->isMandatory, $this->hint, null, $classes );

		return $this;
	}

	public function renderStatic( html_form $form, $name, $input, $label, model_editor $editor )
	{
		$classes = implode( ' ', array_filter( array( $this->class, 'texteditor' ) ) );

		$form->setRow( $name, $label, markup::inline( $input, 'static' ), $this->isMandatory, null, null, $classes );

		return $this;
	}
}

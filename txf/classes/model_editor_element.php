<?php


namespace de\toxa\txf;

interface model_editor_element
{
	public function setEditor( model_editor $editor );
	public function getEditor();

	public function normalize( $input, $property, model_editor $editor );
	public function validate( $input, $property, model_editor $editor );
	public function render( html_form $form, $name, $value, $label, model_editor $editor );
	public function renderStatic( html_form $form, $name, $value, $label, model_editor $editor );
	public function formatValue( $name, $value, model_editor $editor );
	public function mandatory( $mandatory = true );
	public function isMandatory();

	public function declareDefaultValue( $defaultValue );

	public function onSelectingItem( model_editor $editor, model $item );
	public function onLoading( model_editor $editor, model $item = null, $propertyName );
	public function beforeStoring( model_editor $editor, model $item = null, $itemProperties );
	public function afterStoring( model_editor $editor, model $item, $itemProperties );
	public function onDeleting( model_editor $editor, model $item );
}

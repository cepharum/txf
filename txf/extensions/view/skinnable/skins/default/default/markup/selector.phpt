<?php namespace de\toxa\txf; list( $name, $options, $value, $label, $multiSelect ) = $arguments ?>
<?php

if ( !is_array( $options ) )
	$options = array();

if ( !is_array( $value ) && $multiSelect )
	$value = array( $value );

if ( count( $arguments ) == 2 )
	if ( count( $options ) )
		$value = input::vget( $name );

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

$class = $multiSelect ? 'multi' : 'single';
$mode  = $multiSelect ? ' multiple="multiple"' : '';
$tag   = $multiSelect ? '[]' : '';

?>
<span>
	<select class="<?php echo html::inAttribute( $class ) ?>" name="<?php echo $name . $tag ?>"<?php echo $mode ?>>
<?php
foreach ( $options as $option => $label )
{
/*
 * disabled for breaking support for model_editor_selector() providing model instances per numeric ID
	if ( is_integer( $option ) )
	{
		$attr   = ' value=""';
		$option = $label;
	}
	else
 *
 */
		$attr = ' value="' . html::inAttribute( $option ) . '"';

	if ( $multiSelect )
		$checked = in_array( trim( $option ), $value );
	else
		$checked = ( trim( $option ) === trim( $value ) );

	$checked = $checked ? ' selected="selected"' : '';

?>
		<option<?php echo $attr . $checked ?>><?php echo htmlspecialchars( (string) $label ) ?></option>
<?php } ?>
	</select>
</span>

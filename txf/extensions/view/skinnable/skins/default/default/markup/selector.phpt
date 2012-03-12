<?php namespace de\toxa\txf; list( $name, $options, $value, $label ) = $arguments ?>
<?php

if ( !is_array( $options ) )
	$options = array();

if ( count( $arguments ) == 2 )
	if ( count( $options ) )
		$value = input::vget( $name );

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
	<select class="single" name="<?php echo $name ?>">
<?php 
foreach ( $options as $option => $label )
{
	if ( is_integer( $option ) )
	{
		$attr   = ' value=""';
		$option = $label;
	}
	else
		$attr = ' value="' . html::inAttribute( $option ) . '"';

	$checked = ( $option == $value ) ? ' selected="selected"' : '';

?>
		<option<?php echo $attr . $checked ?>><?php echo htmlspecialchars( $label ) ?></option>
<?php } ?>		
	</select>	
</span>
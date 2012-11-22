<?php namespace de\toxa\txf; list( $name, $value, $checked, $label ) = $arguments ?>
<?php

if ( trim( $value ) === '' )
	$value = 'y';

if ( preg_match( '/^([^\[]+)\[([^\]]*)\]$/', $name, $matches ) )
{
	$store = $matches[1];
	$index = $matches[2];
}
else
{
	$store = $name;
	$index = null;
}

if ( count( $arguments ) < 3 )
{
	// autodetect whether option is checked or not
	$data = input::vget( $store );

	if ( is_array( $data ) )
		$checked = ( $index === null ) ? in_array( $value, $data ) : !!$data[$index];
	else
		$checked = ( $data == $value );
}


$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
 <input type="checkbox" class="checkbox" name="<?php echo $name ?>" value="<?php echo html::inAttribute( $value ) ?>"/>
</span>
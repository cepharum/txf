<?php namespace de\toxa\txf; list( $name, $value, $label ) = $arguments ?>
<?php

if ( count( $arguments ) == 1 )
	$value = input::vget( $name );

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
 <input type="text" class="text" name="<?php echo $name ?>" value="<?php echo html::inAttribute( $value ) ?>"/>
</span>
<?php namespace de\toxa\txf; list( $name, $label, $class ) = $arguments ?>
<?php

if ( count( $arguments ) == 1 )
	$value = input::vget( $name );

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

if ( $class ) {
	$class = ' class="' . html::inAttribute( $class ) . '"';
}

?>
<span<?php echo $class ?>>
 <input type="password" class="text password" name="<?php echo $name ?>"/>
</span>

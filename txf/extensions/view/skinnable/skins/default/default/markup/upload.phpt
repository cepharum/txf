<?php namespace de\toxa\txf; list( $name, $label, $class ) = $arguments ?>
<?php

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

if ( $class ) {
	$class = ' class="' . html::inAttribute( $class ) . '"';
}

?>
<span<?php echo $class ?>>
 <input type="file" class="file" name="<?php echo $name ?>"/>
</span>

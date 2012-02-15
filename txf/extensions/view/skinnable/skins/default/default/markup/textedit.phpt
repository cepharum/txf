<?php namespace de\toxa\txf; list( $name, $value, $label ) = $arguments ?>
<?php

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
 <input type="text" name="<?php echo $name ?>" value="<?php echo html::inAttribute( $value ) ?>"/>
</span>
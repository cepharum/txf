<?php namespace de\toxa\txf; list( $name, $label ) = $arguments ?>
<?php

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
 <input type="file" class="file" name="<?php echo $name ?>"/>
</span>
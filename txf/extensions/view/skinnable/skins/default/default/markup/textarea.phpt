<?php namespace de\toxa\txf; list( $name, $value, $label, $rows, $columns ) = $arguments ?>
<?php

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

?>
<span>
 <textarea name="<?php echo $name ?>"<?php echo view::wrapNotFalse( intval( $rows ), ' rows="|"' ) . view::wrapNotFalse( intval( $columns ), ' cols="|"' ); ?>><?php echo html::cdata( $value, true ) ?></textarea>
</span>
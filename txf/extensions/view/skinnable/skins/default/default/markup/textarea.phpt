<?php namespace de\toxa\txf; list( $name, $value, $label, $rows, $columns, $placeholder ) = $arguments ?>
<?php

if ( count( $arguments ) == 1 )
	$value = input::vget( $name );

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

if ( $placeholder ) {
	$placeholder = ' placeholder="' . html::inAttribute( $placeholder ) . '"';
}

?>
<span>
 <textarea name="<?php echo $name ?>"<?php echo view::wrapNotFalse( intval( $rows ), ' rows="|"' ) . view::wrapNotFalse( intval( $columns ), ' cols="|"' ) . $placeholder; ?>><?php echo html::cdata( $value, true ) ?></textarea>
</span>

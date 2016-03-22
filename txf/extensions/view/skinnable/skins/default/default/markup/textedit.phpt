<?php namespace de\toxa\txf; list( $name, $value, $label ) = $arguments ?>
<?php

if ( count( $arguments ) == 1 ) {
	try {
		$value = input::vget( $name );
	} catch ( \Exception $e ) {
		$value = null;
	}
}

$name = html::idname( $name, true );

echo view::wrapNotEmpty( $label, "<label for=\"$name\">|:</label>" );

if ( $value !== null )
	$value = ' value="' . html::inAttribute( $value ) . '"';

?>
<span>
 <input type="text" class="text" name="<?php echo $name ?>"<?php echo $value ?>/>
</span>

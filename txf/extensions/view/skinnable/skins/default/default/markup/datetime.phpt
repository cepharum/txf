<?php namespace de\toxa\txf; list( $name, $value, $label, $placeholder, $class ) = $arguments ?>
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

if ( $placeholder ) {
	$placeholder = ' placeholder="' . html::inAttribute( $placeholder ) . '"';
}

if ( $class ) {
	$class = ' class="' . html::inAttribute( $class ) . '"';
}

?>
<span<?php echo $class ?>>
 <input type="datetime" class="text date time" name="<?php echo $name ?>"<?php echo $value . $placeholder ?>/>
</span>

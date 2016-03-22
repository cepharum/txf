<?php namespace de\toxa\txf; list( $items, $class ) = $arguments ?>
<?php
if ( count( $arguments ) ) {
	if ( !is_array( $items ) )
	{
		$items = $arguments;
		$class = '';
	}
?>
<ol<?php echo view::wrapNotEmpty( html::classname( $class ), ' class="|"' ) ?>><?php

foreach ( $items as $text )
{
?><li><?php echo $text ?></li><?php
}
?></ol>
<?php } ?>
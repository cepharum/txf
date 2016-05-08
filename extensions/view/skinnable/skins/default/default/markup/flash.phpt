<?php

namespace de\toxa\txf;

list( $context, $messages ) = $arguments;

if ( is_array( $messages ) )
{
?>
<div<?php echo view::wrap( html::className( $context ), ' class="flash |"' ) ?>><?php 

foreach ( $messages as $message )
	echo '<div>' . $message . '</div>';

?></div><?php

}

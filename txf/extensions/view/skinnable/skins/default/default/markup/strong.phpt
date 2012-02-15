<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<strong<?php echo view::wrapNotEmpty( html::classname( $class ), ' class="|"' ) ?>><?php echo $text ?></strong>
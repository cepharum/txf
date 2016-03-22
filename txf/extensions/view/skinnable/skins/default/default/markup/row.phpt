<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<div<?php echo view::wrapNotEmpty( html::classname( $class ), ' class="|"' ) ?>><?php echo $text ?></div>
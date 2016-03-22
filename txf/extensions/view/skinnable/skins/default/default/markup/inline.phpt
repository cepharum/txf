<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<span<?php echo view::wrapNotEmpty( html::className( $class ), ' class="|"' ) ?>><?php echo $text ?></span>
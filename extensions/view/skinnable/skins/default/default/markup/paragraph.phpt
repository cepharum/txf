<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<p<?php echo view::wrapNotEmpty( html::className( $class ), ' class="|"' ) ?>><?php echo $text ?></p>
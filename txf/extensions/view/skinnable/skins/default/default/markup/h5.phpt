<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<h5<?php echo view::wrapNotEmpty( html::classname( $class ), ' class="|"' ) ?>><?php echo $text ?></h5>

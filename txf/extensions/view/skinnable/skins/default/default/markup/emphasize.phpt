<?php namespace de\toxa\txf; list( $text, $class ) = $arguments ?>
<em<?php echo view::wrapNotEmpty( html::classname( $class ), ' class="|"' ) ?>><?php echo $text ?></em>
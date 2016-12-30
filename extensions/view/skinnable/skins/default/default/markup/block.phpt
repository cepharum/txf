<?php namespace de\toxa\txf; list( $text, $class, $title ) = $arguments ?>
<div<?php echo view::wrapNotEmpty( html::className( $class ), ' class="|"' ) ?><?php echo view::wrapNotEmpty( html::inAttribute( $title ), ' title="|"' ) ?>><?php echo $text ?></div>

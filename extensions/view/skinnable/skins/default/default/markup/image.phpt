<?php namespace de\toxa\txf; list( $src, $class, $alt, $href, $title, $width, $height ) = $arguments;

$tag = $href ? 'a' : 'span';

$class  = view::wrapNotEmpty( html::className( $class ), ' class="|"' );
$href   = view::wrapNotEmpty( html::inAttribute( $href ), ' href="|"' );
$title  = view::wrapNotEmpty( html::inAttribute( $title ), ' title="|"' );
$width  = view::wrapNotEmpty( html::inAttribute( $width ), ' width="|"' );
$height = view::wrapNotEmpty( html::inAttribute( $height ), ' height="|"' );

?><<?php echo "$tag$class$href$title" ?>><img src="<?php echo html::inAttribute( $src ) ?>" alt="<?php echo html::inAttribute( $alt ) ?>" <?php echo "$width$height" ?>/></<?php echo $tag ?>>
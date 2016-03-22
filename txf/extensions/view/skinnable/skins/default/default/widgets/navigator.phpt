<?php namespace de\toxa\txf; ?>
<?php if ( count( $items ) ) { ?>
<div class="navigator" id="<?php echo html::idName( "navigation-$name" ) ?>">
<?php echo view::render( 'widgets/navigator-level', array( 'level' => $level, 'active' => $active, 'items' => $items ) ); ?>
</div>
<?php } ?>
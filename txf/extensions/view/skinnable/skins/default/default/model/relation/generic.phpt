<?php

namespace de\toxa\txf;

if ( count( $matches ) > 0 ) { ?>
<div class="model_relation">
<?php if ( $headline ) { ?>
	<h<?php echo _1($headlineLevel,1) ?>><?php echo _H($headline) ?></h<?php echo _1($headlineLevel,1) ?>>
<?php } ?>
	<ul>
<?php foreach ( $matches as $index => $record ) { ?>
		<li class="item-<?php echo intval( $index ) + 1 ?>">
			<label><?php echo _H($relation->unboundEnd( true )->bind( $record, true )->describe()) ?></label>
			<span class="actions">
				<a class="show" href="<?php echo $relation->unboundEnd()->getterUrl( $record ) ?>"><?php echo _Ltxl('Show') ?></a>
<?php if ( user::current()->isAuthenticated() && $delete  ) { ?>
				<a class="delete" href="<?php echo $relation->managing()->getterUrl( $record, array( 'action' => 'delete' ) ) ?>"><?php echo _1($delete !== true ? $delete : null,_Ltxl('Delete')) ?></a>
<?php } ?>
			</span>
		</li>
<?php } ?>
	</ul>
<?php if ( user::current()->isAuthenticated() && $add ) { ?>
	<span class="actions">
		<a class="add" href="<?php echo $relation->managing()->getterUrl( null, array( 'action' => 'add' ) ) ?>"><?php echo _1($add !== true ? $add : null,_Ltxl('Add')) ?></a>
	</span>
<?php } ?>
</div>
<?php }

<?php namespace de\toxa\txf; ?>
<?php 

if ( count( $items ) ) 
{
?>
<ul<?php echo view::wrapNotEmpty( html::classname( "level-$level $active" ), ' class="', '"' ) ?>>
<?php
	foreach ( array_values( $items ) as $index => $item )
	{
?>
<li<?php echo view::wrapNotEmpty( html::classname( ( $index ? '' : 'first' ) . " $item[selected]" ), ' class="', '"' ) ?>>
<?php
		if ( $item['selected'] )
		{
?>
	<span><?php echo $item['label'] ?></span>
<?php
		} else {
?>
	<a href="<?php echo html::inAttribute( $item['action'] ) ?>"><?php echo $item['label'] ?></a>
<?php
		}

		echo view::render( 'widgets/navigator-level', $item['sub'] );
?>
</li>
<?php
	}
?>
</ul>
<?php 
}
<?php namespace de\toxa\txf; ?>
<?php

if ( config::get( 'pager.visible' ) || count( $pageOffsets ) > 1 || ( $itemCount > min( $sizes ) ) )
{
	view::addAsset( 'widget-pager', 'pager.css', 'text/css', null, true );

?>
<div class="pager">
	<span class="back-flip">
<?php if ( $currentPage ) { ?>
<?php if ( $useButtons ) { ?>
		<button name="<?php echo html::inAttribute( $offsetName ) ?>" value="<?php echo $pageOffsets[$currentPage-1] ?>" type="submit">&#x25C0;</button>
<?php } else { ?>
		<a href="<?php echo context::selfUrl( array( $offsetName => $pageOffsets[$currentPage-1] ) ) ?>">&#x25C0;</a>
<?php } ?>
<?php } ?>
	</span>
<?php if ( $itemCount > min( $sizes ) ) { ?>
	<span class="sizes">
<?php
	foreach ( $sizes as $sizeOption )
	{
		if ( $sizeOption == $size )
		{
?>
		<span><?php echo $sizeOption ?></span>
<?php
		} else if ( $useButtons ) {
?>
		<button name="<?php echo html::inAttribute( $sizeName ) ?>" value="<?php echo $sizeOption ?>" type="submit"><?php echo $sizeOption ?></button>
<?php
		} else {
?>
		<a href="<?php echo context::selfUrl( array( $sizeName => $sizeOption ) ) ?>"><?php echo $sizeOption ?></a>
<?php
		}

		if ( $sizeOption > $itemCount )
			break;
	}
?>
	</span>
<?php } ?>
	<span class="pages">
<?php

$radius = config::get( 'pager.pages.radius', 4 );
$slice = array_slice( $pageOffsets, max( 0, min( $currentPage - $radius, count( $pageOffsets ) - 2 * $radius - 1 ) ), 2 * $radius + 1, true );

foreach ( $slice as $index => $pageOffset )
	if ( $index == $currentPage ) {
?>
		<span class="selected">
			<?php echo $index + 1 ?>
		</span>
<?php } else if ( $useButtons ) { ?>
		<span>
			<button name="<?php echo html::inAttribute( $offsetName ) ?>" value="<?php echo $pageOffset ?>" type="submit"><?php echo $index + 1 ?></button>
		</span>
<?php } else { ?>
		<span>
			<a href="<?php echo context::selfUrl( array( $offsetName => $pageOffset ) ) ?>"><?php echo $index + 1 ?></a>
		</span>
<?php } ?>
	</span>
	<span class="info"><?php echo $offset + 1 ?>…<?php echo min( $itemCount, $offset + $size ) ?> / <?php echo $itemCount ?></span>
	<span class="fwd-flip">
<?php if ( $currentPage < count( $pageOffsets ) - 1 ) { ?>
<?php if ( $useButtons ) { ?>
		<button name="<?php echo html::inAttribute( $offsetName ) ?>" value="<?php echo $pageOffsets[$currentPage+1] ?>" type="submit">&#x25B6;</button>
<?php } else { ?>
		<a href="<?php echo context::selfUrl( array( $offsetName => $pageOffsets[$currentPage+1] ) ) ?>">&#x25B6;</a>
<?php } ?>
<?php } ?>
	</span>
</div>
<?php } ?>
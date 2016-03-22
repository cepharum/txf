<div class="exception">
<?php list( $major, $minor ) = explode( "\n", \de\toxa\txf\exception::reducePathname( $exception->getMessage(), 0 ) ); ?>
 <h2><?php echo $major ?> (<?php echo $exception->getCode() ?>)</h2>
<?php if ( $minor ) { ?>
 <h3><?php echo $minor ?></h3>
<?php } ?>
 <p>
  in <strong><?php echo \de\toxa\txf\exception::reducePathname( $exception->getFile() ) ?></strong> at line <?php echo intval( $exception->getLine() ) ?>
 </p>
 <pre><?php echo \de\toxa\txf\exception::reduceExceptionTrace( $exception, true ) ?></pre>
</div>

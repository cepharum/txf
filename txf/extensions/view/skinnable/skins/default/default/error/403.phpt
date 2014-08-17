<?php namespace de\toxa\txf; ?>
<div class="request-error error-403 error-4xx">
	<h1><?php echo _Ltxl('Forbidden') ?></h1>
	<p>
		<?php echo _Ltxl('You are not authorized to see this page.') ?>
		<?php if ( user::current()->isAuthenticated() ) echo _Ltxl('Please log in!') ?>
	</p>
</div>

<?php namespace de\toxa\txf; ?>
<div class="request-error error-403 error-4xx">
	<h1><?php echo _L('Forbidden') ?></h1>
	<p>
		<?php echo _L('You are not authorized to see this page.') ?>
		<?php if ( user::current()->isAuthenticated() ) echo _L('Please log in!') ?>
	</p>
</div>

<?php namespace de\toxa\txf; /** @var $variables */ /** @var $regions */ /** @var $clientData */ ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<!-- powered by cepharum txf -->
	<title><?php echo html::inAttribute( $variables->title ) ?></title>
	<?php echo view::wrapNotEmpty( view::getAssetsOfType( view::ASSET_TYPE_STYLE ), '<link rel="stylesheet" type="text/css" href="|"/>' ) ?>
	<?php echo $clientData ?>
	<?php echo data::qualifyString( config::get( 'view.html.header', '' ) ) ?>
</head>
<body>
<div id="page">
	<div id="north">
		<?php echo $regions->head ?>
	</div>
	<div id="middle">
		<?php echo view::wrapNotEmpty( $regions->left, '<div id="west">', '</div>' ); ?>
		<?php echo view::wrapNotEmpty( $regions->right, '<div id="east">', '</div>' ); ?>
		<div id="core">
			<?php echo $regions->error ?>
			<?php echo $regions->main ?>
		</div>
	</div>
	<div id="south">
		<?php echo $regions->foot ?>
	</div>
</div>
<?php echo view::wrapNotEmpty( view::getAssetsOfType( view::ASSET_TYPE_SCRIPT ), '<script type="text/javascript" src="|"></script>' ) ?>
</body>
</html>

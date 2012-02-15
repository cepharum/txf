<?php

/**
 * Wraps text in proper markup code using templates.
 *
 * This class is designed to use templates for applying markup on page elements
 * such as headline and paragraphs. In addition to improving separation of
 * content and design this is beneficially introducing low-level customizations
 * of rendered code.
 *
 * The class is using magic method __call() to select a template's name by
 * invoking method, so using it becomes as simple as this
 *
 *   markup::h1( 'Major Headline' );
 *
 * for marking up provided text as a first-level headline.
 *
 * Supported templates are located in subfolder markup of your current skin or
 * its fallbacks. In case of given example it's "markup/h1".
 *
 * @author Thomas Urban
 */


namespace de\toxa\txf;


class markup
{
	public static function __callStatic( $method, $arguments )
	{
		$oblevel = ob_get_level();

		try
		{
			// @todo consider selecting engine depending on current configuration instead of using current view's one
			return view::current()->getEngine()->render( 'markup/' . $method, variable_space::create( 'arguments', $arguments, 'text', array_shift( $arguments ) ) );
		}
		catch ( \Exception $e )
		{
			while ( $oblevel > ob_get_level() )
				ob_end_clean();

			throw $e;
		}
	}
}


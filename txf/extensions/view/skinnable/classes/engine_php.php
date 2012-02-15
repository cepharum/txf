<?php


/**
 * Implements rendering engine utilizing native PHP support for high performance
 * template rendering.
 *
 * While this engine provide little security due to enabling use of arbitrary
 * PHP code in templates it's fast, easy to use and simple to implement. A
 * future extension might use available tokenizer to validate template scripts
 * in a preflight e.g. to prevent execution of templates including use of
 * selected methods or superglobal variables.
 *
 * A major caveat is its influence on PHP runtime due to more fatal errors
 * result in exceptions usually breaking whole runtime of requested script.
 *
 * @author Thomas Urban
 *
 */

namespace de\toxa\txf\view\skinnable;


use de\toxa\txf\variable_space;


class engine_php extends engine
{
	/**
	 * manager instance used to locate a selected template file
	 *
	 * @var templateLocator
	 */

	protected $templateLocator;


	public function __construct()
	{
		$this->templateLocator = new template_locator( $this );
	}

	public function getTemplate( $templateName )
	{
		return $this->templateLocator->find( $templateName );
	}

	public function hasTemplate( $templateName )
	{
		try
		{
			$this->templateLocator->find( $templateName );

			return true;
		}
		catch ( \UnexpectedValueException $e )
		{
			return false;
		}
	}

	public function render( $templateName, variable_space $data )
	{
		$templateFile = $this->getTemplate( $templateName );

		extract( $data->asArray() );
		$LOCALDATA = $data;

		ob_start();
		try
		{
			// @todo evaluate performance improvements by caching used templates in closures on frequent use
			include( $templateFile );
			$code = ob_get_clean();
		}
		catch ( \Exception $e )
		{
			ob_end_clean();
			$code = manager::simpleRenderException( $e );
		}

		return $code;
	}
}


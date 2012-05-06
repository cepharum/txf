<?php


/**
 * Copyright 2012 Thomas Urban, toxA IT-Dienstleistungen
 * 
 * This file is part of TXF, toxA's web application framework.
 * 
 * TXF is free software: you can redistribute it and/or modify it under the 
 * terms of the GNU General Public License as published by the Free Software 
 * Foundation, either version 3 of the License, or (at your option) any later 
 * version.
 * 
 * TXF is distributed in the hope that it will be useful, but WITHOUT ANY 
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR 
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with 
 * TXF. If not, see http://www.gnu.org/licenses/.
 *
 * @copyright 2012, Thomas Urban, toxA IT-Dienstleistungen, www.toxa.de
 * @license GNU GPLv3+
 * @version: $Id$
 * 
 */


namespace de\toxa\txf\view\skinnable;


use de\toxa\txf\variable_space;
use de\toxa\txf\i18n\lang;
use de\toxa\txf\data;
use de\toxa\txf\config;
use de\toxa\txf\txf;


/**
 * Implements skinnable view manager.
 *
 * This manager is supporting viewports mapped to regions of a page regions,
 * variables and skins to be selected providing different sets of templates each
 * used to convert some set of data into code for output.
 *
 * @author Thomas Urban
 *
 */

class manager extends \de\toxa\txf\singleton
{

	/**
	 * Tag used to mark initial mid of a viewport's code.
	 *
	 * This is used in a page's main region to embed buffered script output.
	 */

	const codeMiddleMarker = '%%%txf_CODE_MIDDLE_MARKER%%%';



	/**
	 * set of named viewports
	 *
	 * @var array
	 */

	protected $viewports = array();

	/**
	 * set of variables available in context of view manager
	 *
	 * @var variable_space
	 */

	protected $variables;

	/**
	 * engine used to convert data into output code
	 *
	 * @var engine
	 */

	protected $engine;


	/**
	 * mMarks whether manager is called while shutting down script
	 *
	 * This is used to operate differently when rendering in shutdown mode and
	 * to prevent rendering in shutdown in case of code explicitly called
	 * render() before.
	 *
	 * @var boolean
	 */

	private $whileShuttingDown;


	/**
	 * marks whether page has been rendered before or not
	 *
	 * @var boolean
	 */

	private $renderedBefore;


	/**
	 * holds initial OB level to properly return there on rendering page e.g.
	 * in case of an exception
	 *
	 * @var integer
	 */

	private $initialOBLevel;


	/**
	 * assets registered for current view
	 *
	 * @var array
	 */

	protected $assets = array();


	const ASSET_TYPE_SCRIPT = 'text/javascript';

	const ASSET_TYPE_STYLE = 'text/css';



	public function __construct()
	{
		$this->variables = new \de\toxa\txf\variable_space;
		$this->engine    = engine::create();

		$this->whileShuttingDown = false;
		$this->renderedBefore    = false;

		$this->initialOBLevel    = 0;
	}


	/**
	 * Disables support for switching view manager.
	 */

	public static function singleSelectOnly()
	{
		return true;
	}

	/**
	 * Conveniently provides option to use shorter method invocations for adding
	 * content to selected viewports of page.
	 *
	 * @param string $viewport "method name" actually selecting viewport here
	 * @param array $arguments set of arguments
	 */

	public static function __callStatic( $viewport, $arguments )
	{
		$manager = self::current();

		array_unshift( $arguments, $viewport );

		return call_user_func_array( array( &$manager, 'viewport' ), $arguments );
	}

	/**
	 * Initializes use of current view manager instance.
	 */

	public function onLoad()
	{
		$this->initialOBLevel = \ob_get_level();
		ob_start();

		if ( txf::getContextMode() == txf::CTXMODE_NORMAL )
			register_shutdown_function( array( &$this, 'onShutdown' ) );

		if ( !@$_GET['rawerror'] )
		{
			set_exception_handler( array( &$this, 'onException' ) );
			set_error_handler( array( &$this, 'onError' ) );
		}


		// read default assets from configuration
		$assets = config::get( 'view.asset' );
		if ( is_array( $assets ) )
			foreach ( $assets as $asset )
				if ( @$asset['url'] && @$asset['type'] )
				{
					$id = trim( @$asset['id'] );
					if ( $id === '' )
						$id = md5( @$asset['url'] );

					$this->assets[$id] = $asset;
				}


		// initialize HTTP defaults
		header( 'Content-Type: text/html; charset=utf-8' );


		// initialize content of viewports
		$content = config::get( 'view.static' );
		if ( is_array( $content ) )
			foreach ( $content as $name => $content )
				$this->writeInViewport( $name, $content );
	}

	/**
	 * Gets called on script shutdown.
	 */

	public function onShutdown()
	{
		if ( !$this->whileShuttingDown )
		{
			$this->whileShuttingDown = true;

			try
			{
				echo $this->renderPage();
			}
			catch ( \Exception $e )
			{
				echo self::simpleRenderException( $e ); 
			}
		}
	}

	/**
	 * Gets called in case of PHP encountering soft PHP error converting that
	 * error into exception.
	 *
	 * @param integer $code error level
	 * @param string $text error message
	 * @param string $file file error has been encountered in
	 * @param integer $line line number in that file
	 * @param mixed $context
	 * @throws \ErrorException
	 */

	public function onError( $code, $text, $file, $line, $context )
	{
		self::onException( new \ErrorException( $text, $code, 0, $file, $line ) );
	}

	/**
	 * Catches exceptions not catched in code for embedding rendered exception
	 * description in selected page design (if possible).
	 *
	 * @param \Exception $exception
	 */

	public function onException( \Exception $exception )
	{
		if ( $exception instanceof \ErrorException )
			if ( $exception->getCode() & E_NOTICE )
				return;

		if ( $exception instanceof \de\toxa\txf\http_exception )
			header( $exception->getResponse() );


		try
		{
			try
			{
				$code = $this->engine->render( 'exception', variable_space::create( 'exception', $exception ) );
			}
			catch ( Exception $e )
			{
				$code = lang::get( 'failed to render exception' );
			}

			$this->viewport( 'error', $code );


			if ( txf::getContextMode() == txf::CTXMODE_REWRITTEN )
				// this mode does not have registered shutdown handler
				// --> call it explicitly here
				$this->onShutdown();
		}
		catch ( \Exception $e )
		{
			echo '<h1>Failed to render exception</h1>';
			echo self::simpleRenderException( $exception );
			echo '<h1>Encountered error was</h1>';
			echo self::simpleRenderException( $e );
		}

		exit();
	}

	/**
	 * Renders exception without utilizing template engine.
	 *
	 * @param \Exception $exception
	 */

	protected static function simpleRenderException( \Exception $exception )
	{
		$trace = \de\toxa\txf\exception::renderTrace( \de\toxa\txf\exception::reduceExceptionTrace( $exception ) );

		return sprintf( <<<EOT
<div class="exception-no-skin">
	<h2>%s (%d)</h2>
	<p>in <strong>%s</strong> at line %s</p>
	<pre>%s</pre>
</div>
EOT
						, $exception->getMessage(), $exception->getCode(),
						\de\toxa\txf\exception::reducePathname( $exception->getFile() ),
						$exception->getLine(), $trace );
	}

	/**
	 * Adds some content to selected viewport.
	 *
	 * @param string $viewportName name of viewport to write into
	 * @param string $viewportCode some code to append/prepend
	 * @param boolean $append if true, code is appended, otherwise it's prepended
	 * @throws \InvalidArgumentException
	 */

	public function writeInViewport( $viewportName, $viewportCode = null, $append = true )
	{
		if ( !( $viewportName = data::isKeyword( $viewportName ) ) )
			throw new \InvalidArgumentException( 'invalid viewport name' );

		if ( is_null( $viewportCode ) )
		{
			// retrieve current content of selected viewport
			if ( array_key_exists( $viewportName, $this->viewports ) )
				return str_replace( self::codeMiddleMarker, '', $this->viewports[$viewportName] );

			return '';
		}

		// extend selected viewport's content
		if ( !array_key_exists( $viewportName, $this->viewports ) )
			$this->viewports[$viewportName] = self::codeMiddleMarker;

		if ( $append )
			$this->viewports[$viewportName] .= $viewportCode;
		else
			$this->viewports[$viewportName]  = $viewportCode . $this->viewports[$viewportName];
	}

	/**
	 * Conveniently wraps use of self::writeInViewport() without requesting view
	 * manager first.
	 *
	 * @param string $viewportName name of page region to write into
	 * @param string $viewportCode some code to append/prepend
	 * @param boolean $append if true, code is appended, otherwise it's prepended
	 */

	public static function viewport( $viewportName, $viewportCode = null, $append = true )
	{
		return static::current()->writeInViewport( $viewportName, $viewportCode, $append );
	}

	/**
	 * Accesses internal variable by name for reading or writing value.
	 *
	 * @param string $name name of variable to access
	 * @param mixed $value value to assign, omit it to read current value
	 * @return mixed read variables value on read, null on missing it
	 */

	public function accessVariable( $name, $value = null )
	{
		if ( $this instanceof self )
			$instance = $this;
		else
			$instance = self::current();

		if ( $instance )
		{
			if ( \func_num_args() >= 2 )
				return $this->variables->update( $name, $value );

			return $this->variables->read( $name );
		}
	}

	/**
	 * Conveniently wraps self::accessVariable() for accessing variables without
	 * requiring to gather current view manager instance first.
	 *
	 * @param string $name name of variable to access
	 * @param mixed $value value to assign, omit it to read current value
	 * @return mixed read variables value on read, null on missing it
	 */

	public static function variable( $name, $value = null )
	{
		return static::current()->accessVariable( $name, $value );
	}

	/**
	 * Adds asset to current view.
	 *
	 * This method is considered to be used to append asset files like javascript
	 * code or CSS definitions to current output.
	 *
	 * @param string $id ID used to identify asset after adding to current view
	 * @param string $source URL/address of asset, omit to drop existing asset
	 * @param enum $type one of view::ASSET_TYPE_* constants
	 * @param boolean $blnIfNotExists if true, asset is added unless existing already
	 * @param string|true $insertBeforeId ID of existing asset this will be inserted before
	 *                     omit to append at end (default if selected asset is missing),
	 *                     provide true or "*" to prepend before first existing asset
	 * @return void
	 */

	public static function addAsset( $id, $source, $type, $insertBeforeId = null, $blnIfNotExists = false )
	{
		if ( trim( $id ) === '' )
			throw new \InvalidArgumentException( 'missing asset id' );


		if ( trim( $source ) === null )
			unset( static::current()->assets[$id] );
		else
		{
			if ( $blnIfNotExists && array_key_exists( $id, static::current()->assets ) )
				return;

			$newAsset = array(
							'url' => $source,
							'type' => $type,
							);

			if ( $insertBeforeId !== null )
			{
				if ( $insertBeforeIf === '*' || $insertBeforeId === true )
					$offset = 0;
				else
					$offset = array_search( $insertBeforeId, array_keys( $static::current()->assets ) );

				if ( $offset !== false )
				{
					static::current()->assets = array_merge(
													array_slice( static::current()->assets, 0, max( 0, $offset-1 ), true ),
													array( $id => $newAsset ),
													array_slice( static::current()->assets, $offset, count( static::current()->assets ) - $offset, true )
													);

					return;
				}
			}

			static::current()->assets[$id] = $newAsset;
		}
	}

	/**
	 * Retrieves URLs of assets matching requested type.
	 *
	 * @param enum $type one of the view::ASSET_TYPE_* constants
	 * @return array set of matching assets' URLs
	 */

	public static function getAssetsOfType( $type )
	{
		return array_map( function( $asset )
			{
				return data::qualifyString( $asset['url'] );
			}, array_filter( static::current()->assets, function( $asset ) use ( $type )
			{
				return ( $type == @$asset['type'] );
			} ) );
	}

	protected function getRawOutput()
	{
		$output = '';

		while ( ob_get_level() > $this->initialOBLevel )
			$output = ob_get_clean();

		return $output;
	}

	/**
	 * Maps viewports onto regions of page.
	 *
	 * This mapping is supported to improve content/view abstraction by enabling
	 * content of viewports being assembled into code of page's regions in a
	 * configurable way ...
	 *
	 * @param array $viewports content of viewports
	 * @return array content of regions
	 */

	protected function collectRegions( $viewports )
	{
		$configs = array(
						// current application's customization
						config::get( 'view.region', array() ),

						// basic page setup
						array(
							array( 'name' => 'main',  'viewport' => array( 'title', 'error', 'main' ) ),
							array( 'name' => 'head',  'viewport' => array( 'header' ) ),
							array( 'name' => 'foot',  'viewport' => array( 'footer' ) ),
							array( 'name' => 'left',  'viewport' => array( 'navigation' ) ),
							array( 'name' => 'right', 'viewport' => array( 'aside' ) ),
							),
						);


		$regions = array();

		foreach ( $configs as $config )
			if ( is_array( $config ) )
				foreach ( $config as $region )
				{
					// get name of region to customize
					$name = trim( @$region['name'] );
					if ( $name === '' )
					{
						log::debug( 'ignoring nameless region configuration' );
						continue;
					}

					if ( !array_key_exists( $name, $regions ) )
					{
						// region haven't been collected before ...

						if ( array_key_exists( 'code', $region ) )
							// there is a line of code containing markers selecting viewports to collect their content in current region
							// e.g. "{{title}}<some-literal-content-to-insert/>{{main}}"
							$regions[$name] = data::qualifyString( $region['code'], $viewports );
						else if ( is_array( @$region['viewport'] ) )
							// collect set of viewports named in configuration
							foreach ( @$region['viewport'] as $viewportName )
								$regions[$name] .= \de\toxa\txf\view::wrapNotEmpty( @$viewports[$viewportName], config::get( 'view.viewport.wrap.' . $viewportName, '' ) );

		
						// support default content to show if a region keeps empty finally
						if ( trim( $regions[$name] ) === '' )
							$regions[$name] = trim( @$region['default'] );
					}
				}


		return $regions;
	}

	/**
	 * Renders current page shutting down script afterwards.
	 *
	 * Rendered code of page is sent to client.
	 *
	 * This method is not going to return at all!
	 */

	public static function render()
	{
		$manager = static::current();
		if ( $manager )
			echo $manager->renderPage();

		if ( !$manager->whileShuttingDown )
			// don't exit in shutdown to permit processing further shutdown
			// functions
			exit();
	}

	/**
	 * Returns rendered code of whole current page.
	 *
	 * @return string code of page
	 */

	public function renderPage()
	{
		if ( $this->renderedBefore )
			return '';

		$this->renderedBefore = true;


		try
		{
			$rawOutput = $this->getRawOutput();

			$viewports = array();
			foreach ( $this->viewports as $key => $code )
				$viewports[$key] = str_replace( self::codeMiddleMarker, ( $key == 'main' ) ? $rawOutput : '', $code );

			if ( !\array_key_exists( 'main', $viewports ) )
				$viewports['main'] = $rawOutput;

			$regions = $this->collectRegions( $viewports );

			$data = variable_space::create(
										'variables', $this->variables,
										'regions', variable_space::fromArray( $regions ),
										'view', $this
									);

			$code = $this->engine->render( 'page', $data );

		}
		catch ( Exception $e )
		{
			$code = self::simpleRenderException( $e );
		}

		return $code;
	}

	/**
	 * Accesses configuration option of currently used template engine.
	 *
	 * @param string $optionName name of option
	 * @param mixed $optionValue value to assign, omit to read option value
	 * @return mixed current option value on read, null on missing it
	 */

	public static function configure( $optionName, $optionValue )
	{
		return static::current()->engine->option( $optionName, $optionValue );
	}

	/**
	 * Selects different skin,
	 *
	 * @param mixed $skinSelector skin selector
	 */

	public static function selectSkin( $skinSelector )
	{
		return static::configure( 'skin', $skinSelector );
	}
}


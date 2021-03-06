<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 cepharum GmbH, Berlin, http://cepharum.de
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @author: Thomas Urban
 */

namespace de\toxa\txf;


/**
 * Serves as a proxy for view manager moved into extension.
 *
 * This proxy is considered to provide more convenient access on actually used
 * view manager. In addition this class is declaring some commonly useful
 * "macros" for use in template processing.
 *
 * @author Thomas Urban
 *
 * == Commonly supported magic methods
 *
 * = writing into declared view ports
 * @method static void title( string $text, bool $append = true ) appends text to title viewport
 * @method static void error( string $text, bool $append = true ) appends text to error viewport
 * @method static void main( string $text, bool $append = true ) appends text to main viewport
 * @method static void header( string $text, bool $append = true ) appends text to header viewport
 * @method static void footer( string $text, bool $append = true ) appends text to footer viewport
 * @method static void debug( string $text, bool $append = true ) appends text to debug viewport
 * @method static void navigation( string $text, bool $append = true ) appends text to navigation viewport
 * @method static void aside( string $text, bool $append = true ) appends text to aside viewport
 */


class view extends view\skinnable\manager
{
	/**
	 * Wraps provided string.
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param string $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string wrapped string
	 */

	public static function wrap( $what, $wrapping, $wrappingAppend = null )
	{
		if ( is_array( $what ) )
		{
			$out = '';
			foreach ( $what as $item )
				$out .= static::wrap( $item, $wrapping, $wrappingAppend );

			return $out;
		}

		if ( is_null( $wrappingAppend ) )
		{
			$halves = explode( '|', $wrapping );
			return array_shift( $halves ) . $what . array_shift( $halves );
		}

		return $wrapping . $what . $wrappingAppend;
	}

	/**
	 * Wraps provided string unless its empty (ignoring any whitespace).
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param string $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string|null wrapped string, null on empty $what
	 */

	public static function wrapNotEmpty( $what, $wrapping, $wrappingAppend = null )
	{
		if ( ( is_array( $what ) && count( $what ) ) || ( !is_array( $what ) && !is_null( $what ) && trim( $what ) !== '' ) )
			return static::wrap( $what, $wrapping, $wrappingAppend );

		return null;
	}

	/**
	 * Wraps provided string unless its false (or similar value considered value).
	 *
	 * If provided $wrappingAppend is null/omitted, $wrapping is considered to
	 * include a single pipe to be replaced by string in $what. Otherwise it
	 * ($wrapping) is prepended to string while $wrappingAppend is appended.
	 *
	 * @param mixed $what string to be wrapped
	 * @param string $wrapping string to wrap around
	 * @param string $wrappingAppend string to wrap after $what
	 * @return string|null wrapped string, null on empty $what
	 */

	public static function wrapNotFalse( $what, $wrapping, $wrappingAppend = null )
	{
		if ( $what )
			return static::wrap( $what, $wrapping, $wrappingAppend );

		return null;
	}

	/**
	 * Adds message to be flashed on next rendered page.
	 *
	 * @param string $message message to flash
	 * @param string $context context/kind of message, e.g. notice, error, alert
	 */

	public static function flash( $message, $context = 'notice' )
	{
		$session =& txf::session( session::SCOPE_GLOBAL );
		$session['view']['flashes'][$context][] = $message;
	}

	/**
	 * Renders template using provided data.
	 *
	 * This method is managing exceptions thrown inside to bubble up to current
	 * level of output buffering at least.
	 *
	 * @param string $template template name to render
	 * @param variable_space|array $data data to use on rendering template
	 * @return string rendered template
	 */

	public static function render( $template = null, $data = null )
	{
		$oblevel = ob_get_level();

		try
		{
			if ( $data === null || is_array( $data ) )
				$data = variable_space::fromArray( (array) $data );

			// @todo consider selecting engine depending on current configuration instead of using current view's one
			return static::engine()->render( $template, $data );
		}
		catch ( \Throwable $e )
		{
			while ( $oblevel < ob_get_level() )
				ob_end_clean();

			static::error( log::exception( '[%s:%s in %s@%d]', get_class( $e ), $e->getMessage(), $e->getFile(), $e->getLine() ) );

			return '';
		}
	}


	/**
	 * @var array[capture]
	 */

	protected static $_capturedStack = array();

	protected static function configureCapturing()
	{
		if ( !count( static::$_capturedStack ) )
			static::$_capturedStack[] = new capture();
	}

	public static function getCaptured()
	{
		static::configureCapturing();

		return static::$_capturedStack[0]->get();
	}

	public static function startCapture( $blnAppend = true )
	{
		static::configureCapturing();

        if ( !$blnAppend )
            static::$_capturedStack[0]->reset();

		array_unshift( static::$_capturedStack, new capture() );

		ob_start();
	}

	public static function stopCapture()
	{
		static::configureCapturing();

		if ( count( static::$_capturedStack ) < 2 )
			throw new \RuntimeException( 'no capturing has been started previously' );

		static::$_capturedStack[1]->put( static::$_capturedStack[0]->get() );
		static::$_capturedStack[1]->put( ob_get_clean() );

		array_shift( static::$_capturedStack );
	}
}

function _Con( $append = true ) { return view::startCapture( $append ); }
function _Coff() { return view::stopCapture(); }
function _C() { return view::getCaptured(); }

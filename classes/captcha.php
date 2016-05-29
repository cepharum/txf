<?php
/**
 * (c) 2016 cepharum GmbH, Berlin, http://cepharum.de
 *
 * The MIT License (MIT)
 *
 * Copyright (c) 2014 cepharum GmbH
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal
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
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE
 * SOFTWARE.
 *
 * @author: cepharum
 */

namespace de\toxa\txf;


/**
 * Generates captcha per script to be injected into forms asking user to solve
 * some simple calculation to confirm being human.
 *
 * @package de\toxa\txf
 */
class captcha {
	/**
	 * Refers to current script's session storage.
	 *
	 * @var array
	 */
	private $session = null;

	/**
	 * Tracks result of checking any current input previously.
	 *
	 * @note This one is used to support arbitrary order of checking input and
	 *       injecting captcha into some form.
	 *
	 * @var boolean
	 */
	private $valid = null;


	public function __construct() {
		$this->session =& txf::session();
	}

	/**
	 * Creates captcha for controlling current script.
	 *
	 * @note Creating two separate instances in a single script results in two
	 *       instances managing same session storage w/o properly interacting
	 *       with each other. *Thus, don't use twice per script!*
	 *
	 * @return captcha
	 */
	public static function create() {
		return new static();
	}

	/**
	 * Checks if provided response is matching expected response to provided
	 * parameters.
	 *
	 * @param $a
	 * @param $b
	 * @param $answer
	 * @return bool
	 */
	protected static function isValid( $a, $b, $answer ) {
		return $a + $b > 0 && $b != 0 && $a + $b == $answer;
	}

	/**
	 * Delivers previously generated captcha challenge. On missing challenge in
	 * session storage some new one is generated.
	 *
	 * @param bool $force true to force generating new challenge
	 * @return array challenge parameters
	 */
	protected function &generate( $force = false ) {
		if ( $force || !is_array( $this->session['captcha'] ) ) {
			mt_srand( intval( microtime( true ) * 1000 ) );

			do {
				$a   = mt_rand( 5, 20 );
				$b   = mt_rand( -10, 15 );
				$sum = $a + $b;
			}
			while ( !static::isValid( $a, $b, $sum ) );

			$this->session['captcha'] = compact( 'a', 'b', 'sum' );
		}

		return $this->session['captcha'];
	}

	/**
	 * Injects captcha into provided form using optionally selected field name.
	 *
	 * @param html_form $form
	 * @param string $name name of field to use in form for receiving response
	 * @return $this fluent interface
	 */
	public function injectIntoForm( html_form $form, $name = 'c_info' ) {
		if ( $form->hasInput() )
			$this->check();

		input::drop( $name );

		$info  =& $this->generate();
		$query = sprintf( _L( 'Tragen Sie das Ergebnis von %d %s %d als Ziffernfolge in das folgende Feld ein!' ),
		                  $info['a'], ( $info['b'] < 0 ? '-' : '+' ),
			              abs( $info['b'] ) );

		$info['name'] = $name;

		$form
			->setStaticRow( 'c_query', null, $query )
			->setTexteditRow( $name, null );

		return $this;
	}

	/**
	 * Detects if any response by user is matching captcha's expected response.
	 *
	 * @return bool
	 */
	public function check() {
		if ( $this->valid === null ) {
			$info =& $this->generate();

			$name = $info['name'];
			if ( $name )
				$this->valid = !!$this->isValid( $info['a'], $info['b'], input::vget( $name ) );
			else
				$this->valid = false;

			// always generate new captcha
			$this->generate( true );
		}

		return $this->valid;
	}
}

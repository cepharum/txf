<?php


namespace de\toxa\txf;


class path
{

	/**
	 * Extracts relative pathname from an absolute pathname in relation to
	 * another absolutely given pathname.
	 *
	 * The method succeeds if outer pathname is containing inner one. Otherwise
	 * it's returning false thus rendering useful in detecting this relationship
	 * of two pathnames.
	 *
	 * @param string $outer pathname to containing folder
	 * @param string $inner pathname to contained file or folder
	 * @return string inner pathname relative to outer one on $outer containing
	 *                 $inner, false if $outer isn't containing $inner
	 */

	public static function relativeToAnother( $outer, $inner )
	{
		if ( strlen( $inner ) == strlen( $outer ) )
		{
			if ( $inner === $outer )
				return '';
		}
		else if ( substr( $inner, 0, strlen( $outer ) ) === $outer )
		{
			$length = strlen( $outer );
			if ( $outer[$length-1] == '/' )
				$length--;

			if ( $inner[$length] == '/' )
				return substr( $inner, $length + 1 );
		}

		return false;
	}

	/**
	 * Detects if provided path addresses resource contained in webspace.
	 *
	 * This method is checking if given pathname is subordinated to current
	 * server's document root. On using aliasing/rewriting pathname might be
	 * valid though failing here. In that case setting environment variable
	 * to proper document root containing pathname is required.
	 *
	 * @param string $pathname pathname of resource to test
	 * @return boolean true on resource contained in web folder
	 */

	public static function isInWebfolder( $pathname )
	{
		$result = self::relativeToAnother( $_SERVER['DOCUMENT_ROOT'], $pathname );

		if ( !$result && array_key_exists( 'DOCUMENT_ROOT', $_ENV ) && is_dir( $_ENV['DOCUMENT_ROOT'] ) )
			$result = self::relativeToAnother( $_ENV['DOCUMENT_ROOT'], $pathname );

		return ( $result !== false );
	}

	/**
	 * Concatenates several path fragments to a single one.
	 *
	 * Provide a variable number of pathname fragments each in a separate
	 * argument. The method is automatically filtering out all empty or
	 * navigating elements and ensuring to have single slash between all
	 * concatenated parts. Any trailing slash is removed as well.
	 *
	 * Note! Double slashes mustn't appear at borders of fragments excluding
	 *       at beginning of first fragment.
	 *
	 * @return string concatenated pathname fragments, false on missing any
	 *                 valid fragment in arguments
	 */

	public static function glue( $fragment )
	{
		$fragments = func_get_args();

		// fix provided set of fragments
		$fragments = array_values( array_unique( array_filter( array_map( function( $a ) { return trim( $a ); }, $fragments ) ) ) );

		$out = false;	// to be returned on empty set of fragments

		for ( $i = 0; $i < count( $fragments ); $i++ )
			if ( $i )
				$out .= '/' . trim( $fragments[$i], '/' );
			else
				$out = rtrim( $fragments[0], '/' );


		return preg_replace( '#(^\.\.?/)|(/\.\.?(?=(/|$)))#', '', $out );
	}

	/**
	 * Splits provided pathname at selected separators returning set of resulting
	 * chunks each containing a selected number of pathname fragments.
	 *
	 * An asterisk can be used for wildcard to be filled with all fragments
	 * left after processing all other size selectors.
	 *
	 * The direction of processing depends on using positive or negative size
	 * selectors and might be defined due to using asterisk as wildcard in first
	 * or last position. You mustn't mix positive and negative sizes in a single
	 * call.
	 *
	 * @example
	 *
	 * [ '/a/b', 'c/d/e', 'f/g', 'h' ] = path::split( '/a/b/c/d/e/f/g/h',  2,  3,  2,  1 );
	 * [ '/a/b', 'c/d/e', 'f/g', 'h' ] = path::split( '/a/b/c/d/e/f/g/h', -2, -3, -2, -1 );
	 *
	 * [ '/a/b', 'c/d/e', 'f/g/h' ]    = path::split( '/a/b/c/d/e/f/g/h',  2,  3,  4 );
	 * [ '/a', 'b/c/d', 'e/f/g/h' ]    = path::split( '/a/b/c/d/e/f/g/h', -2, -3, -4 );
	 *
	 * [ '/a/b', 'c/d/e/f/g', 'h' ]    = path::split( '/a/b/c/d/e/f/g/h',  2, '*',  1 );
	 * [ '/a/b', 'c/d/e/f/g', 'h' ]    = path::split( '/a/b/c/d/e/f/g/h', -2, '*', -1 );
	 *
	 * [ '/a/b/c/d/e', 'f/g/h' ]       = path::split( '/a/b/c/d/e/f/g/h',  5, '*',  5 );
	 * [ '/a/b/c', 'd/e/f/g/h' ]       = path::split( '/a/b/c/d/e/f/g/h', -5, '*', -5 );
	 *
	 * [ 'a/b/c', 'd/e/f/g/h' ]        = path::split( 'a/b/c/d/e/f/g/h',  -5, '*', -5 );
	 * [ dirname($a), basename($a) ]   = path::split( $a, '*', -1 );
	 *
	 * @param string $pathname pathname to split
	 * @param integer|* $fragmentSizeSelector single fragment size selector
	 * @return array split pathname fragments
	 */

	public static function split( $pathname, $fragmentSizeSelector )
	{
		// get selectors and validate
		$selectors = func_get_args();
		array_shift( $selectors );

		$hasNegative = $hasPositive = false;
		$hasAsterisk = array();

		foreach ( $selectors as $index => $selector )
		{
			$selector = trim( $selector );

			// each selector must be integer numeric or asterisk
			if ( !preg_match( '/^(\*|[+-]?[1-9]\d*?)$/', $selector ) )
				throw new \InvalidArgumentException( 'invalid selector: ' . $selector );

			if ( is_numeric( $selector ) )
			{
				$hasPositive |= ( $selector[0] != '-' );
				$hasNegative |= ( $selector[0] == '-' );
			}
			else
				$hasAsterisk[] = $index;
		}

		if ( count( $hasAsterisk ) > 1 )
			throw new \InvalidArgumentException( 'use at most one wildcard' );

		// set constraints on direction when asterisk is used in first or last position
		$hasPositive |= ( @$hasAsterisk[0] === count( $selectors ) - 1 );
		$hasNegative |= ( @$hasAsterisk[0] === 0 );

		// direction must be unambigious
		if ( !( $hasPositive ^ $hasNegative ) )
			throw new \InvalidArgumentException( 'ambigious direction of processing' );


		// get raw set of fragments
		$fragments = explode( '/', trim( $pathname ) );
		if ( $fragments[0] ===  '' )
		{
			$fragments[1] = '/' . $fragments[1];
			array_shift( $fragments );
		}

		// prepare proper order of processing according to using positive or negative numbers
		$forward  = array(
						'next'   => 'array_shift',
						'shift'  => function( &$set, $c ) { return array_splice( $set, 0, $c ); },
						'push'   => 'array_push',
						'target' => 'prefix',
						);
		$backward = array(
						'next'   => 'array_pop',
						'shift'  => function( &$set, $c ) { return array_splice( $set, -abs( $c ), $c ); },
						'push'   => 'array_unshift',
						'target' => 'suffix',
						);

		$order = $hasNegative ? array( $backward, $forward ) : array( $forward, $backward );


		// declare collectors for split parts
		$affixes = array(
						'prefix' => array(),
						'suffix' => array(),
						);

		// approximate any included asterisk from preferred direction first
		while ( !is_null( $major = $order[0]['next']( $selectors ) ) )
			if ( empty( $fragments ) )
				break;
			else if ( $major === '*' )
			{
				// met asterisk --> stop here for approximating from other end
				while ( !is_null( $minor = $order[1]['next']( $selectors ) ) )
					if ( empty( $fragments ) )
						break;
					else
						$order[1]['push']( $affixes[$order[1]['target']], implode( '/', $order[1]['shift']( $fragments, $minor ) ) );
				break;
			}
			else
				$order[0]['push']( $affixes[$order[0]['target']], implode( '/', $order[0]['shift']( $fragments, $major ) ) );

		if ( count( $fragments ) && ( $major === '*' ) )
			// include any leftover fragments matching asterisk
			$affixes['prefix'][] = implode( '/', $fragments );


		return array_merge( $affixes['prefix'], $affixes['suffix'] );
	}

	/**
	 * Ensures to have trailing slash in provided path.
	 *
	 * @param string $in pathname to extend on demand
	 * @return string extended pathname
	 */

	public static function addTrailingSlash( $in )
	{
		return ( substr( $in, -1 ) == '/' ) ? $in : $in . '/';
	}
}


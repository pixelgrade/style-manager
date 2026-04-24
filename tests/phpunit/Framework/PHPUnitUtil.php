<?php
declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Tests\Framework;

use ReflectionClass;
use ReflectionMethod;

class PHPUnitUtil {
	/**
	 * Retrieve the current test suite.
	 *
	 * @return string
	 */
	public static function get_current_suite() {
		$suite = '';

		$options = self::get_phpunit_options();

		if ( ! empty( $options[1] ) ) {
			// File or directory.
			$source = isset( $options[1][1]) ? $options[1][1] : $options[1][0];
			$source = str_replace( 'tests/phpunit', '', $source );

			if ( 0 === strpos( $source, '/Unit' ) ) {
				$suite = 'Unit';
			}
		}

		foreach ( $options[0] as $arg ) {
			if ( '--testsuite' === $arg[0] ) {
				$suite = $arg[1];
				break;
			}
		}

		return $suite;
	}

	/**
	 * Retrieve PHPUnit CLI arguments.
	 *
	 * @return array
	 */
	public static function get_phpunit_options() {
		$options = [];
		$sources = [];
		$argv    = $GLOBALS['argv'] ?? [];

		foreach ( $argv as $index => $arg ) {
			if ( 0 === $index ) {
				continue;
			}

			if ( '--testsuite' === $arg && isset( $argv[ $index + 1 ] ) ) {
				$options[] = [ '--testsuite', $argv[ $index + 1 ] ];
				continue;
			}

			if ( 0 === strpos( $arg, '--testsuite=' ) ) {
				$options[] = [ '--testsuite', substr( $arg, strlen( '--testsuite=' ) ) ];
				continue;
			}

			if ( isset( $arg[0] ) && '-' !== $arg[0] && file_exists( $arg ) ) {
				$sources[] = $arg;
			}
		}

		return [ $options, $sources ];
	}

	/**
	 * Get a private method for testing/documentation purposes.
	 * How to use for MyClass->foo():
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getPrivateMethod($cls, 'foo');
	 *      $foo->invoke($cls, $...);
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your private method
	 *
	 * @return ReflectionMethod The method you asked for
	 */
	public static function getPrivateMethod( object $obj, string $name ): ReflectionMethod {
		$class  = new ReflectionClass( $obj );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * Get a protected method for testing/documentation purposes.
	 * How to use for MyClass->foo():
	 *      $cls = new MyClass();
	 *      $foo = PHPUnitUtil::getProtectedMethod($cls, 'foo');
	 *      $foo->invoke($cls, $...);
	 *
	 * @param object $obj  The instantiated instance of your class
	 * @param string $name The name of your protected method
	 *
	 * @return ReflectionMethod The method you asked for
	 */
	public static function getProtectedMethod( object $obj, string $name ): ReflectionMethod {
		return self::getPrivateMethod( $obj, $name );
	}

}

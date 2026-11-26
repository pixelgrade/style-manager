<?php
/**
 * Run on the installed Carbon Fields copy, or pass the extracted package's plugin root.
 */

$plugin_root = $argv[1] ?? dirname( __DIR__ );
require $plugin_root . '/vendor/htmlburger/carbon-fields/core/Pimple/Container.php';

$deprecations = array();
set_error_handler(
	static function ( $level, $message ) use ( &$deprecations ) {
		$deprecations[] = $message;
		return true;
	},
	E_DEPRECATED
);

$container = new \Carbon_Fields\Pimple\Container();
$container['factory'] = $container->factory( static fn () => (object) array( 'extended' => false ) );
$container->extend(
	'factory',
	static function ( $service ) {
		$service->extended = true;
		return $service;
	}
);
$first  = $container['factory'];
$second = $container['factory'];

$protected = static fn () => 'protected';
$container['callback'] = $container->protect( $protected );
$container['shared']   = static fn () => new stdClass();
restore_error_handler();

if ( $first === $second || ! $first->extended || ! $second->extended || $protected !== $container['callback'] || $container['shared'] !== $container['shared'] ) {
	fwrite( STDERR, "Carbon Fields factories, callbacks or shared services changed behavior.\n" );
	exit( 1 );
}

if ( $deprecations ) {
	fwrite( STDERR, implode( "\n", $deprecations ) . "\n" );
	exit( 1 );
}

echo "Carbon Fields factories, callbacks and shared services passed with zero deprecations.\n";

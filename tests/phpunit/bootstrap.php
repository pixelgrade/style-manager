<?php
declare ( strict_types = 1 );

use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Framework\TestSuite;
use Pixelgrade\StyleManager\Vendor\Psr\Log\NullLogger;

require dirname( __DIR__, 2 ) . '/vendor/autoload.php';

define( 'STYLE_MANAGER_RUNNING_UNIT_TESTS', true );
define( 'STYLE_MANAGER_TESTS_DIR', __DIR__ );
define( 'WP_PLUGIN_DIR', __DIR__ . '/Fixture/wp-content/plugins' );

if ( 'Unit' === PHPUnitUtil::get_current_suite() ) {
	// For the Unit suite we shouldn't need WordPress loaded.
	// This keeps them fast.
	return;
}

require_once dirname( __DIR__, 2 ) . '/vendor/antecedent/patchwork/Patchwork.php';

$suite = new TestSuite();

$GLOBALS['wp_tests_options'] = [
	'active_plugins'  => [ 'pixelgradelt_records/style-manager.php' ],
	'timezone_string' => 'Europe/Bucharest',
];

$suite->addFilter( 'muplugins_loaded', function() {
	require dirname( __DIR__, 2 ) . '/style-manager.php';
} );

$suite->addFilter( 'style_manager/compose', function( $plugin, $container ) {
	$container['logger'] = new NullLogger();
}, 10, 2 );

$suite->bootstrap();

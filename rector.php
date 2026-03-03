<?php

use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Config\RectorConfig;

/** Define ABSPATH as this file's directory */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/vendor/wordpress/wordpress/');
}

return static function (RectorConfig $rectorConfig): void {
	// get parameters
	$parameters = $rectorConfig->parameters();

	// paths to refactor; solid alternative to CLI arguments
	$rectorConfig->paths( [
		__DIR__ . '/src',
		__DIR__ . '/vendor_prefixed',
		__DIR__ . '/vendor/htmlburger/carbon-fields',
	] );

	$rectorConfig->skip( [
		__DIR__ . '/src/_js',
		__DIR__ . '/src/_scss',
	] );

	// Rector is static reflection to load code without running it - see https://phpstan.org/blog/zero-config-analysis-with-static-reflection
	$parameters->set(Option::AUTOLOAD_PATHS, [
		__DIR__ . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php',
	]);

	// do you need to include constants, class aliases or custom autoloader? files listed will be executed
//	$parameters->set(Option::BOOTSTRAP_FILES, [
//
//	]);

	// is your PHP version different from the one your refactor to? [default: your PHP version]
	$parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_81);

	// Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
	$parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, __DIR__ . '/phpstan.neon.dist');

};

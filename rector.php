<?php

use Rector\Core\Configuration\Option;
use Rector\Core\ValueObject\PhpVersion;
use Rector\Set\ValueObject\DowngradeSetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/** Define ABSPATH as this file's directory */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/vendor/wordpress/wordpress/');
}

return static function (ContainerConfigurator $containerConfigurator): void {
	// get parameters
	$parameters = $containerConfigurator->parameters();

	// paths to refactor; solid alternative to CLI arguments
	$parameters->set(Option::PATHS, [
		__DIR__ . '/src',
		__DIR__ . '/vendor',
		__DIR__ . '/vendor_prefixed',
	]);

	$parameters->set(Option::SKIP, [
		__DIR__ . '/src/_js',
		__DIR__ . '/src/_scss',
	]);

	// Rector is static reflection to load code without running it - see https://phpstan.org/blog/zero-config-analysis-with-static-reflection
	$parameters->set(Option::AUTOLOAD_PATHS, [
		__DIR__ . "/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php",
	]);

	// do you need to include constants, class aliases or custom autoloader? files listed will be executed
//	$parameters->set(Option::BOOTSTRAP_FILES, [
//
//	]);

	// is your PHP version different from the one your refactor to? [default: your PHP version]
	$parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_70);

	// Path to phpstan with extensions, that PHPSTan in Rector uses to determine types
	$parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, __DIR__ . '/phpstan.neon.dist');

	// here we can define, what sets of rules will be applied
	$containerConfigurator->import( DowngradeSetList::PHP_80 );
	$containerConfigurator->import( DowngradeSetList::PHP_74 );
	$containerConfigurator->import( DowngradeSetList::PHP_73 );
	$containerConfigurator->import( DowngradeSetList::PHP_72 );
	$containerConfigurator->import( DowngradeSetList::PHP_71 );
	$containerConfigurator->import( DowngradeSetList::PHP_70 );
};

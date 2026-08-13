<?php
/**
 * Contract: optional typed callbacks are explicitly nullable on PHP 8.4+.
 *
 * Run standalone: php tests/php84-explicit-nullable-contract.php
 */

$source = file_get_contents( __DIR__ . '/../src/Lab/Config.php' );

if ( false === $source ) {
	throw new RuntimeException( 'Could not read src/Lab/Config.php.' );
}

$callback_parameters = [
	'get_palette_runtime_payload',
	'get_sm_option',
	'get_wp_option',
	'admin_url',
	'home_url',
	'create_nonce',
];

foreach ( $callback_parameters as $parameter ) {
	if ( preg_match( '/(?<!\?)callable\s+\$' . preg_quote( $parameter, '/' ) . '\s*=\s*null/', $source ) ) {
		throw new RuntimeException( sprintf( '%s must use an explicit nullable callable type for PHP 8.4.', $parameter ) );
	}

	if ( ! preg_match( '/\?callable\s+\$' . preg_quote( $parameter, '/' ) . '\s*=\s*null/', $source ) ) {
		throw new RuntimeException( sprintf( 'Expected %s to use ?callable with a null default.', $parameter ) );
	}
}

echo "Style Manager PHP 8.4 explicit nullable contract OK\n";

<?php
/**
 * Helper functions
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager;

\defined( 'ABSPATH' ) || \in_array( \PHP_SAPI, [ 'cli', 'phpdbg' ], true ) || exit;

/**
 * Retrieve the main plugin instance.
 *
 * @since 2.0.0
 *
 * @return Plugin
 */
function plugin(): Plugin {
	static $instance;
	$instance = $instance ?: new Plugin();

	return $instance;
}

/**
 * Autoload mapped classes.
 *
 * @since 2.0.0
 *
 * @param string $class Class name.
 */
function autoloader_classmap( string $class ) {
	$class_map = [
		'PclZip' => ABSPATH . 'wp-admin/includes/class-pclzip.php',
	];

	if ( isset( $class_map[ $class ] ) ) {
		require_once $class_map[ $class ];
	}
}

/**
 * Generate a random string.
 *
 * @since 2.0.0
 *
 * @param int $length Length of the string to generate.
 *
 * @throws \Exception
 * @return string
 */
function generate_random_string( int $length = 12 ): string {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	$str = '';
	$max = \strlen( $chars ) - 1;
	for ( $i = 0; $i < $length; $i ++ ) {
		$str .= $chars[ random_int( 0, $max ) ];
	}

	return $str;
}

/**
 * Whether a plugin identifier is the main plugin file.
 *
 * Plugins can be identified by their plugin file (relative path to the main
 * plugin file from the root plugin directory) or their slug.
 *
 * This doesn't validate whether or not the plugin actually exists.
 *
 * @since 2.0.0
 *
 * @param string $plugin_file Plugin slug or relative path to the main plugin file.
 *
 * @return bool
 */
function is_plugin_file( string $plugin_file ): bool {
	return '.php' === substr( $plugin_file, - 4 );
}

/**
 * Display a notice about missing dependencies.
 *
 * @since 2.0.0
 */
function display_missing_dependencies_notice() {
	$message = sprintf(
	/* translators: %s: documentation URL */
		__( 'Style Manager is missing required dependencies. <a href="%s" target="_blank" rel="noopener noreferer">Learn more.</a>', '__plugin_txtd' ),
		'https://github.com/pixelgrade/style-manager'
	);

	printf(
		'<div class="style-manager-compatibility-notice notice notice-error"><p>%s</p></div>',
		wp_kses(
			$message,
			[
				'a' => [
					'href'   => true,
					'rel'    => true,
					'target' => true,
				],
			]
		)
	);
}

/**
 * Determine if we are looking at the Customize screen.
 *
 * @return bool
 */
function is_customizer(): bool {
	global $pagenow;
	return ( is_admin() && 'customize.php' === $pagenow );
}

/**
 * Determine if the Style Manager functionality is supported.
 *
 * @return bool
 */
function is_sm_supported(): bool {
	return apply_filters( 'style_manager/is_supported', current_theme_supports( 'customizer_style_manager' ) );
}

const PIXELGRADE_PLUS_ADVANCED_CONTROLS_ENTITLEMENT = 'advanced_style_manager_controls';

/**
 * Determine if the shared Pixelgrade Plus entitlement bridge can answer.
 *
 * Existing Style Manager controls must stay available while Plus is absent or
 * dormant. The entitlement bridge is considered available only after a plugin
 * hooks the shared filter.
 *
 * @since 2.2.14
 *
 * @return bool
 */
function pixelgrade_plus_entitlement_bridge_is_available(): bool {
	$available = \function_exists( 'has_filter' ) && false !== \has_filter( 'pixelgrade/has_entitlement' );

	if ( \function_exists( 'apply_filters' ) ) {
		$available = (bool) \apply_filters( 'style_manager/pixelgrade_plus_entitlement_bridge_is_available', $available );
	}

	return $available;
}

/**
 * Check a Pixelgrade Plus entitlement without hard-depending on Plus.
 *
 * @since 2.2.14
 *
 * @param string $entitlement                       Entitlement key.
 * @param bool   $default_when_bridge_unavailable  Result when Plus cannot answer.
 * @return bool
 */
function has_pixelgrade_plus_entitlement( string $entitlement, bool $default_when_bridge_unavailable = false ): bool {
	if ( ! pixelgrade_plus_entitlement_bridge_is_available() ) {
		return $default_when_bridge_unavailable;
	}

	if ( ! \function_exists( 'apply_filters' ) ) {
		return false;
	}

	return (bool) \apply_filters( 'pixelgrade/has_entitlement', false, $entitlement );
}

/**
 * Determine if advanced Style Manager controls are available.
 *
 * @since 2.2.14
 *
 * @return bool
 */
function advanced_style_manager_controls_are_unlocked(): bool {
	return has_pixelgrade_plus_entitlement( PIXELGRADE_PLUS_ADVANCED_CONTROLS_ENTITLEMENT, true );
}

/**
 * Get a Style Manager option's value, if there is a value, and return it.
 * Otherwise, try to get the default parameter or the default from config.
 *
 * @since 2.0.0
 *
 * @param string     $option_id
 * @param mixed|null $default        Optional.
 * @param array|null $option_details Optional.
 *
 * @return mixed
 */
function get_option( string $option_id, $default = null, $option_details = null ) {
	return plugin()->get_container()->get('options')->get( $option_id, $default, $option_details );
}

/**
 * Get the Style Manager configuration (and value, hence "details") of a certain option.
 *
 * @since 2.0.0
 *
 * @param string $option_id
 * @param bool   $minimal_details Optional. Whether to return only the minimum amount of details (mainly what is needed on the frontend).
 *                                The advantage is that these details are cached, thus skipping the customizer_config!
 * @param bool   $skip_cache      Optional.
 *
 * @return array|false The option config or false on failure.
 */
function get_option_details( string $option_id, $minimal_details = false, $skip_cache = false ) {
	return plugin()->get_container()->get('options')->get_details( $option_id, $minimal_details, $skip_cache );
}

/**
 * Get all Style Manager options' details.
 *
 * @since 2.0.0
 *
 * @param bool $only_minimal_details Optional. Whether to return only the minimal details.
 *                                   Defaults to returning all details.
 * @param bool $skip_cache           Optional. Whether to skip the options cache and regenerate.
 *                                   Defaults to using the cache.
 * @return array
 */
function get_option_details_all( $only_minimal_details = false, $skip_cache = false ): array {
	return plugin()->get_container()->get('options')->get_details_all( $only_minimal_details, $skip_cache );
}

/**
 * Determine if a certain option exists.
 *
 * @since 2.0.0
 *
 * @param string $key The option key.
 *
 * @return bool
 */
function has_option( string $key ): bool {
	return plugin()->get_container()->get('options')->has_option( $key );
}

/**
 * Get the key under which all Style Manager options are saved.
 *
 * @since 2.0.0
 *
 * @param bool $skip_cache Optional. Whether to skip the options cache and regenerate.
 *                         Defaults to using the cache.
 *
 * @return string
 */
function get_options_key( bool $skip_cache = false ): string {
	return plugin()->get_container()->get('options')->get_options_key( $skip_cache );
}

/**
 * Get the entire Style Manager Customizer fields config or a certain entry key.
 *
 * @since 2.0.0
 *
 * @param bool|string $key
 *
 * @return array|mixed|null
 */
function get_customizer_config( $key = false ) {
	return plugin()->get_container()->get('options')->get_customizer_config( $key );
}

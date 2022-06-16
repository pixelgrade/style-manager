<?php
/**
 * Style Manager
 *
 * @wordpress-plugin
 * Plugin Name: Style Manager
 * Plugin URI:  https://wordpress.org/plugins/style-manager
 * Description: Auto-magical system to style your entire WordPress site.
 * Version: 2.2.7
 * Author: Pixelgrade
 * Author URI: https://pixelgrade.com
 * Author Email: contact@pixelgrade.com
 * Text Domain: style-manager
 * License:     GPL-2.0 or later.
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages/
 * Requires at least: 5.5.0
 * Tested up to: 6.0
 * Requires PHP: 7.1
 * GitHub Plugin URI: pixelgrade/style-manager
 * Release Asset: true
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin version.
 *
 * @var string
 */
const VERSION        = '2.2.7';

/**
 * Plugin required minimal PHP version.
 *
 * @var string
 */
const PHP_VERSION    = '7.1';

// Load the Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require __DIR__ . '/vendor/autoload.php';
}

/**
 * Display admin notice, if the server is using old PHP version.
 *
 * @since 2.0.0
 */
function old_php_version_notice() { ?>

	<div class="notice notice-error">
		<p>
			<?php
			printf(
				wp_kses( /* translators: %1$s - WordPress.org for recommended WordPress hosting. */
					__( 'Your site is running an <strong>old version</strong> of PHP that is no longer supported. Please contact your web hosting provider to update your PHP version or switch to a <a href="%1$s" target="_blank" rel="noopener noreferrer">recommended WordPress hosting company</a>.', '__plugin_txtd' ),
					array(
						'a'      => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
						'strong' => array(),
					)
				),
				'https://wordpress.org/hosting/'
			);
			?>
			<br><br>
			<?php
			printf(
				wp_kses( /* translators: %s - WordPress.org requirements URL with more details. */
					__( '<strong>The Style Manager plugin is disabled</strong> on your site until you fix the issue. <a href="%s" target="_blank" rel="noopener noreferrer">Read more for additional information.</a>', '__plugin_txtd' ),
					array(
						'a'      => array(
							'href'   => array(),
							'target' => array(),
							'rel'    => array(),
						),
						'strong' => array(),
					)
				),
				'https://wordpress.org/about/requirements/'
			);
			?>
		</p>
	</div>

	<?php

	// In case this is on plugin activation.
	if ( isset( $_GET['activate'] ) ) { //phpcs:ignore
		unset( $_GET['activate'] );     //phpcs:ignore
	}
}

/**
 * Display admin notice and prevent plugin code execution, if the server is
 * using old PHP version.
 *
 * @since 2.0.0
 */
if ( version_compare( phpversion(), PHP_VERSION, '<' ) ) {
	add_action( 'admin_notices', __NAMESPACE__ . '\old_php_version_notice' );

	return;
}

// Display a notice and bail if dependencies are missing.
if ( ! function_exists( __NAMESPACE__ . '\autoloader_classmap' ) ) {
	require_once __DIR__ . '/src/functions.php';
	add_action( 'admin_notices', __NAMESPACE__ . '\display_missing_dependencies_notice' );

	return;
}

// Autoload mapped classes.
spl_autoload_register( __NAMESPACE__ . '\autoloader_classmap' );

// Load the WordPress plugin administration API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

// Create a container and register a service provider.
$style_manager_container = new Container();
$style_manager_container->register( new ServiceProvider() );

// Initialize the plugin and inject the container.
$style_manager_plugin = plugin()
	->set_basename( plugin_basename( __FILE__ ) )
	->set_directory( plugin_dir_path( __FILE__ ) )
	->set_file( __DIR__ . '/style-manager.php' )
	->set_slug( 'style-manager' )
	->set_url( plugin_dir_url( __FILE__ ) )
	->set_container( $style_manager_container )
	->register_hooks( $style_manager_container->get( 'hooks.activation' ) )
	->register_hooks( $style_manager_container->get( 'hooks.deactivation' ) );

// Compose before the theme is set up; this should give plenty of opportunities to hook.
add_action( 'setup_theme', [ $style_manager_plugin, 'compose' ], 15 );

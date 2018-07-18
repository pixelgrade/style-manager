<?php
/**
 * @wordpress-plugin
 * Plugin Name: Style Manager
 * Plugin URI:  https://wordpress.org/plugins/style-manager
 * Description: Auto-magical system to style your WordPress site.
 * Version: 1.0.0
 * Author: Pixelgrade
 * Author URI: https://pixelgrade.com
 * Author Email: contact@pixelgrade.com
 * Requires at least: 4.9.0
 * Tested up to: 4.9.7
 * Text Domain: style-manager
 * License:     GPL-2.0 or later.
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once( plugin_dir_path( __FILE__ ) . 'includes/lib/abstracts/class-Singleton_Registry.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/lib/abstracts/class-Plugin_Init.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/lib/class-Array.php' );
require_once( plugin_dir_path( __FILE__ ) . 'extras.php' );

require_once( plugin_dir_path( __FILE__ ) . 'includes/customizer/class-Font_Selector.php' );

/**
 * Returns the main instance of StyleManager_Plugin to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return StyleManager_Plugin StyleManager_Plugin instance.
 */
function StyleManager_Plugin() {

	require_once( plugin_dir_path( __FILE__ ) . 'includes/class-StyleManager_Plugin.php' );

	return StyleManager_Plugin::getInstance( __FILE__, '1.0.0' );
}

StyleManager_Plugin();

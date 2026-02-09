<?php
/**
 * Style Manager
 *
 * @wordpress-plugin
 * Plugin Name: Style Manager
 * Plugin URI:  https://github.com/pixelgrade/style-manager
 * Update URI:  false
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

if ( ! class_exists( 'WUpdates_Plugin_Updates_mg8pX' ) ) {
	/**
	 * WUpdates_Plugin_Updates_mg8pX Class
	 *
	 * This class handles the updates to a plugin, automagically.
	 */
	class WUpdates_Plugin_Updates_mg8pX {

		/*
		 * The current plugin basename
		 */
		var $basename = '';

		function __construct( $basename ) {
			$this->basename = $basename;

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_version' ) );
			add_filter( 'plugins_api', array( $this, 'shortcircuit_plugins_api_to_org' ), 10, 3 );
			add_action( 'install_plugins_pre_plugin-information', array( $this, 'plugin_update_popup' ) );
			add_filter( 'wupdates_gather_ids', array( $this, 'add_details' ), 10, 1 );
		}

		function check_version( $transient ) {

			// Nothing to do here if the checked transient entry is empty or if we have already checked
			if ( empty( $transient->checked ) || empty( $transient->checked[ $this->basename ] ) || ! empty( $transient->response[ $this->basename ] ) || ! empty( $transient->no_update[ $this->basename ] ) ) {
				return $transient;
			}

			// Lets start gathering data about the plugin
			// First, the plugin directory name
			$slug = dirname( $this->basename );
			// Then WordPress version
			include( ABSPATH . WPINC . '/version.php' );
			$http_args = array (
				'body' => array(
					'slug' => $slug,
					'plugin' => $this->basename,
					'url' => home_url( '/' ), //the site's home URL
					'version' => 0,
					'locale' => get_locale(),
					'phpv' => phpversion(),
					'data' => null, //no optional data is sent by default
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url( '/' ),
			);

			// If the plugin has been checked for updates before, get the checked version
			if ( ! empty( $transient->checked[ $this->basename ] ) ) {
				$http_args['body']['version'] = $transient->checked[ $this->basename ];
			}

			// Use this filter to add optional data to send
			// Make sure you return an associative array - do not encode it in any way
			$optional_data = apply_filters( 'wupdates_call_data_request', $http_args['body']['data'], $slug, $http_args['body']['version'] );

			// Encrypting optional data with private key, just to keep your data a little safer
			// You should not edit the code bellow
			$optional_data = json_encode( $optional_data );
			$w=array();$re="";$s=array();$sa=md5('2cb528e208f114ced2b3b2ee3014725d2866be97');
			$l=strlen($sa);$d=$optional_data;$ii=-1;
			while(++$ii<256){$w[$ii]=ord(substr($sa,(($ii%$l)+1),1));$s[$ii]=$ii;} $ii=-1;$j=0;
			while(++$ii<256){$j=($j+$w[$ii]+$s[$ii])%255;$t=$s[$j];$s[$ii]=$s[$j];$s[$j]=$t;}
			$l=strlen($d);$ii=-1;$j=0;$k=0;
			while(++$ii<$l){$j=($j+1)%256;$k=($k+$s[$j])%255;$t=$w[$j];$s[$j]=$s[$k];$s[$k]=$t;
				$x=$s[(($s[$j]+$s[$k])%255)];$re.=chr(ord($d[$ii])^$x);}
			$optional_data=bin2hex($re);

			// Save the encrypted optional data so it can be sent to the updates server
			$http_args['body']['data'] = $optional_data;

			// Check for an available update
			$url = $http_url = set_url_scheme( 'https://wupdates.com/wp-json/wup/v1/plugins/check_version/mg8pX', 'http' );
			if ( $ssl = wp_http_supports( array( 'ssl' ) ) ) {
				$url = set_url_scheme( $url, 'https' );
			}

			$raw_response = wp_remote_post( $url, $http_args );
			if ( $ssl && is_wp_error( $raw_response ) ) {
				$raw_response = wp_remote_post( $http_url, $http_args );
			}
			// We stop in case we haven't received a proper response
			if ( is_wp_error( $raw_response ) || 200 != wp_remote_retrieve_response_code( $raw_response ) ) {
				return $transient;
			}

			$response = (array) json_decode($raw_response['body']);
			if ( ! empty( $response ) ) {
				// You can use this action to show notifications or take other action
				do_action( 'wupdates_before_response', $response, $transient );
				if ( isset( $response['allow_update'] ) && $response['allow_update'] && isset( $response['transient'] ) ) {
					$transient->response[ $this->basename ] = (object) $response['transient'];
				} else {
					//it seems we don't have an update available - remember that
					$transient->no_update[ $this->basename ] = (object) array(
						'slug' => $slug,
						'plugin' => $this->basename,
						'new_version' => ! empty( $response['version'] ) ? $response['version'] : '0.0.1',
					);
				}
				do_action( 'wupdates_after_response', $response, $transient );
			}

			return $transient;
		}

		function add_details( $ids = array() ) {
			// Now add the predefined details about this product
			// Do not tamper with these please!!!
			$ids[ $this->basename ] = array( 'name' => 'Style Manager', 'slug' => 'style-manager', 'id' => 'mg8pX', 'type' => 'plugin', 'digest' => '34b4b416aab9a1225ef4136831e7ab94', );

			return $ids;
		}

		function shortcircuit_plugins_api_to_org( $res, $action, $args ) {
			if ( 'plugin_information' != $action || empty( $args->slug ) || 'style-manager' != $args->slug ) {
				return $res;
			}

			$screen = get_current_screen();
			// Only fire on the update-core.php admin page
			if ( empty( $screen->id ) || ( 'update-core' !== $screen->id && 'update-core-network' !== $screen->id ) ) {
				return $res;
			}

			$res         = new stdClass();
			$transient = get_site_transient( 'update_plugins' );
			if ( isset(  $transient->response[ $this->basename ]->tested ) ) {
				$res->tested = $transient->response[ $this->basename ]->tested;
			} else {
				$res->tested = false;
			}

			return $res;
		}

		function plugin_update_popup() {
			$slug = sanitize_key( $_GET['plugin'] );

			if ( 'style-manager' !== $slug ) {
				return;
			}

			// It's good to have an error message on hand, at all times
			$error_msg = '<p>' . esc_html__( 'Could not retrieve version details. Please try again.' ) . '</p>';

			$transient = get_site_transient( 'update_plugins' );
			// If we have not URL, life is sad... and full of handy error messages
			if ( empty( $transient->response[ $this->basename ]->url ) ) {
				echo $error_msg;
				exit;
			}

			// Try to get the page
			$response = wp_remote_get( $transient->response[ $this->basename ]->url );
			if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) ) {
				echo $error_msg;
				exit;
			}

			// Get the body and display it
			$data = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $data ) || empty( $data ) ) {
				echo $error_msg;
			} else {
				echo wp_kses_post( $data );
			}

			exit;
		}
	}
} // End WUpdates_Plugin_Updates_mg8pX class check

$plugin_updates = new WUpdates_Plugin_Updates_mg8pX( plugin_basename( __FILE__ ) );
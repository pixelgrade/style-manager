<?php
/**
 * Standalone contract for Style Manager's functional Cloud request context.
 *
 * Run with: php tests/service-request-context-contract.php
 */

namespace Pixelgrade\StyleManager {
	const VERSION = '2.3.1-test';
}

namespace {
	function home_url( $path = '/' ) {
		return 'https://style.example.test' . $path;
	}

	function get_bloginfo( $key ) {
		$values = array(
			'version'  => '6.9.1',
			'language' => 'fr-FR',
		);

		return isset( $values[ $key ] ) ? $values[ $key ] : '';
	}

	function is_ssl() {
		return true;
	}

	function is_rtl() {
		return false;
	}

	function wp_get_environment_type() {
		return 'production';
	}

	function apply_filters( $hook, $value ) {
		return $value;
	}

	$GLOBALS['style_manager_stats_requests'] = array();
	function wp_remote_request( $url, $args ) {
		$GLOBALS['style_manager_stats_requests'][] = array( 'url' => $url, 'args' => $args );

		return array( 'response' => array( 'code' => 202 ) );
	}

	function style_manager_context_assert_same( $expected, $actual, $message ) {
		if ( $expected !== $actual ) {
			fwrite( STDERR, $message . PHP_EOL );
			fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
			fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
			exit( 1 );
		}
	}

	require_once dirname( __DIR__ ) . '/vendor/autoload.php';

	class Style_Manager_Service_Context_Client extends \Pixelgrade\StyleManager\Client\PixelgradeCloud {
		public $captured_request = array();

		public function __construct() {
			$this->endpoints = array(
				'cloud' => array(
					'stats' => array(
						'method' => 'POST',
						'url'    => 'https://cloud.pixelgrade.com/wp-json/pixcloud/v1/front/stats',
					),
				),
			);
		}

		protected function get_active_theme_data(): array {
			return array( 'slug' => 'anima' );
		}

		protected function request_design_assets( array $request_data ): ?array {
			$this->captured_request = $request_data;

			return array( 'ok' => true );
		}

		public function site_data(): array {
			return parent::get_site_data();
		}
	}

	$client = new Style_Manager_Service_Context_Client();
	$client->fetch_design_assets();

	style_manager_context_assert_same( 'design_assets_requested', $client->captured_request['service'], 'Design-asset traffic must name the observed service request.' );
	style_manager_context_assert_same( 'https://style.example.test/', $client->captured_request['site_url'], 'Design-asset traffic must retain the canonical site URL.' );

	$site_data = $client->site_data();
	style_manager_context_assert_same( 'production', $site_data['environment_type'], 'Cloud context must identify the WordPress environment.' );
	style_manager_context_assert_same( 'fr-FR', $site_data['wp']['language'], 'Cloud context must identify the site locale.' );
	style_manager_context_assert_same( false, $site_data['wp']['rtl'], 'Cloud context must identify RTL state.' );
	style_manager_context_assert_same( '2.3.1-test', $site_data['style_manager']['version'], 'Cloud context must identify the Style Manager version.' );

	$client->send_stats( array(
		'site_url'  => 'https://style.example.test/',
		'theme_data' => array( 'slug' => 'anima' ),
		'site_data' => $site_data,
	) );
	style_manager_context_assert_same( 'style_manager_stats_submitted', $GLOBALS['style_manager_stats_requests'][0]['args']['body']['service'], 'Caller-provided stats payloads must still name the observed service request.' );

	echo "Style Manager service context contract OK\n";
}

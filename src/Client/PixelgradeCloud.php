<?php
/**
 * This is the class that handles the communication with the Pixelgrade Cloud.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Client;

use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Provides the interface to communicate with the Pixelgrade Cloud.
 *
 * @since 2.0.0
 */
class PixelgradeCloud implements CloudInterface {

	/**
	 * Endpoints configuration.
	 *
	 * @var array
	 */
	protected array $endpoints;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param LoggerInterface $logger Logger.
	 */
	public function __construct(
		array $endpoints,
		LoggerInterface $logger
	) {
		$this->endpoints = $endpoints;
		$this->logger = $logger;
	}

	/**
	 * Fetch the design assets data from the Pixelgrade Cloud.
	 *
	 * @since 2.0.0
	 *
	 * @return array|null
	 */
	public function fetch_design_assets(): ?array {
		$request_data = [
			// We will only fetch design assets that are handled by Style Manager.
			'types' => [
				'color_palettes_v2',
				'font_palettes',
				'cloud_fonts',
				'system_fonts',
				'font_categories',
				'theme_configs',
			],
			'site_url' => home_url('/'),
			// We are only interested in data needed to identify the theme and eventually deliver only design assets suitable for it.
			'theme_data' => $this->get_active_theme_data(),
			// We are only interested in data needed to identify the plugin version and eventually deliver design assets suitable for it.
			'site_data' => $this->get_site_data(),
			// Extra post statuses besides `publish`.
			'post_status' => [],
		];

		// Handle development and testing constants.
		if ( defined('STYLE_MANAGER_FETCH_DRAFT_ASSETS') && true === STYLE_MANAGER_FETCH_DRAFT_ASSETS ) {
			$request_data['post_status'][] = 'draft';
		}
		if ( defined('STYLE_MANAGER_FETCH_PRIVATE_ASSETS') && true === STYLE_MANAGER_FETCH_PRIVATE_ASSETS ) {
			$request_data['post_status'][] = 'private';
		}
		if ( defined('STYLE_MANAGER_FETCH_FUTURE_ASSETS') && true === STYLE_MANAGER_FETCH_FUTURE_ASSETS ) {
			$request_data['post_status'][] = 'future';
		}

		/**
		 * Filters request data sent to the cloud.
		 *
		 * @param array  $request_data
		 */
		$request_data = apply_filters( 'style_manager/pixelgrade_cloud_request_data', $request_data );
		// This is for backwards compatibility.
		$request_data = apply_filters( 'customify_pixelgrade_cloud_request_data', $request_data, $this );

		$request_args = [
			'method' => $this->endpoints['cloud']['getDesignAssets']['method'],
			'timeout'   => 5,
			'blocking'  => true,
			'body'      => $request_data,
			'sslverify' => true,
		];
		// Get the design assets from the cloud.
		$response = wp_remote_request( $this->endpoints['cloud']['getDesignAssets']['url'], $request_args );
		// Bail in case of decode error or failure to retrieve data.
		// We will return the data already available.
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$response_data = json_decode( wp_remote_retrieve_body( $response ), true );
		// Bail in case of decode error or failure to retrieve data.
		if ( null === $response_data || empty( $response_data['data'] ) || empty( $response_data['code'] ) || 'success' !== $response_data['code'] ) {
			return null;
		}

		return apply_filters( 'style_manager/fetch_design_assets', $response_data['data'] );
	}

	/**
	 * Get the active theme data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_active_theme_data(): array {
		$theme_data = [];

		$slug = basename( get_template_directory() );

		$theme_data['slug'] = $slug;

		// Get the current theme style.css data.
		$current_theme = wp_get_theme( get_template() );
		if ( ! empty( $current_theme ) && ! is_wp_error( $current_theme ) ) {
			$theme_data['name'] = $current_theme->get('Name');
			$theme_data['themeuri'] = $current_theme->get('ThemeURI');
			$theme_data['version'] = $current_theme->get('Version');
			$theme_data['textdomain'] = $current_theme->get('TextDomain');
		}

		// Maybe get the WUpdates theme info if it's a theme delivered from WUpdates.
		$wupdates_ids = apply_filters( 'wupdates_gather_ids', [] );
		if ( ! empty( $wupdates_ids[ $slug ] ) ) {
			$theme_data['wupdates'] = $wupdates_ids[ $slug ];
		}

		return apply_filters( 'style_manager/get_theme_data', $theme_data );
	}

	/**
	 * Get the site data.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_site_data(): array {
		$site_data = [
			'url' => home_url('/'),
			'is_ssl' => is_ssl(),
			'wp' => [
				'version' => get_bloginfo('version'),
			],
			'style_manager' => [
				'version' => VERSION,
			],
		];

		return apply_filters( 'style_manager/get_site_data', $site_data );
	}

	/**
	 * Send stats to the Pixelgrade Cloud.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data     The data to be sent.
	 * @param bool  $blocking Optional. Whether this should be a blocking request. Defaults to false.
	 *
	 * @return array|\WP_Error
	 */
	public function send_stats( array $data = [], bool $blocking = false ) {
		if ( empty( $data ) ) {
			// This is what we send by default.
			$data = [
				'site_url' => home_url('/'),
				// We are only interested in data needed to identify the theme and eventually deliver only design assets suitable for it.
				'theme_data' => $this->get_active_theme_data(),
				// We are only interested in data needed to identify the plugin version and eventually deliver design assets suitable for it.
				'site_data' => $this->get_site_data(),
			];
		}

		/**
		 * Filters request data sent to the cloud.
		 *
		 * @param array $data
		 */
		$data = apply_filters( 'style_manager/pixelgrade_cloud_request_data', $data );
		// This is for backwards compatibility.
		$data = apply_filters( 'customify_pixelgrade_cloud_request_data', $data, $this );

		$request_args = [
			'method' => $this->endpoints['cloud']['stats']['method'],
			'timeout'   => 5,
			'blocking'  => $blocking,
			'body'      => $data,
			'sslverify' => true,
		];

		// Make the request and return the response.
		return wp_remote_request( $this->endpoints['cloud']['stats']['url'], $request_args );
	}
}

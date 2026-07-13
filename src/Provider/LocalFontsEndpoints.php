<?php
/**
 * REST endpoints for the "local fonts" (self-hosted cloud fonts) control
 * surface, consumed by both the Settings screen and the Pixelgrade Design hub.
 *
 * @since   2.4.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Screen\Settings;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Exposes the local fonts (self-hosted cloud fonts) status and controls
 * (enable/disable, manual mirror) over REST so any admin surface -- the
 * Settings screen's Site Editor equivalent, or the Pixelgrade Design hub --
 * can drive the same state without duplicating the underlying logic.
 *
 * @since 2.4.0
 */
class LocalFontsEndpoints extends AbstractHookProvider {

	/**
	 * The REST namespace shared by every Style Manager REST route.
	 *
	 * @since 2.4.0
	 */
	const REST_NAMESPACE = 'style_manager/v1';

	/**
	 * Local font store.
	 *
	 * @var LocalFontStore
	 */
	protected LocalFontStore $local_font_store;

	/**
	 * Style Manager Fonts.
	 *
	 * @var Fonts
	 */
	protected Fonts $sm_fonts;

	/**
	 * Local fonts mirroring provider.
	 *
	 * @var LocalFonts
	 */
	protected LocalFonts $local_fonts;

	/**
	 * Plugin settings.
	 *
	 * @var PluginSettings
	 */
	protected PluginSettings $plugin_settings;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 *
	 * @param LocalFontStore  $local_font_store Local font store.
	 * @param Fonts           $sm_fonts         Style Manager Fonts.
	 * @param LocalFonts      $local_fonts      Local fonts mirroring provider.
	 * @param PluginSettings  $plugin_settings  Plugin settings.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		LocalFontStore $local_font_store,
		Fonts $sm_fonts,
		LocalFonts $local_fonts,
		PluginSettings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->local_font_store = $local_font_store;
		$this->sm_fonts         = $sm_fonts;
		$this->local_fonts      = $local_fonts;
		$this->plugin_settings  = $plugin_settings;
		$this->logger           = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.4.0
	 */
	public function register_hooks() {
		$this->add_action( 'rest_api_init', 'register_routes' );
	}

	/**
	 * Register the REST routes.
	 *
	 * @since 2.4.0
	 */
	protected function register_routes(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			'/local-fonts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_get' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		\register_rest_route(
			self::REST_NAMESPACE,
			'/local-fonts/settings',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_save_settings' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		\register_rest_route(
			self::REST_NAMESPACE,
			'/local-fonts/mirror',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_mirror' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}

	/**
	 * Permissions check: the same capability the Settings screen requires.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return \current_user_can( 'manage_options' );
	}

	/**
	 * Return the current local fonts status/controls payload.
	 *
	 * @since 2.4.0
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get() {
		return \rest_ensure_response( $this->get_payload() );
	}

	/**
	 * Persist the "host cloud fonts locally" toggle and return the fresh payload.
	 *
	 * @since 2.4.0
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_save_settings( \WP_REST_Request $request ) {
		$enabled = (bool) $request->get_param( 'enabled' );

		// Match the checkbox semantics used everywhere else in the plugin:
		// 'yes' truthy, '' falsy (see PluginSettings/Screen\Settings).
		$this->plugin_settings->set( 'typography_host_cloud_fonts_locally', $enabled ? 'yes' : '' );

		return \rest_ensure_response( $this->get_payload() );
	}

	/**
	 * Trigger a manual mirror of every currently-used, unhealthy cloud font
	 * family, then return the fresh payload.
	 *
	 * A no-op (but never an error) when local hosting or cloud fonts are
	 * disabled -- the UI hides the triggering button in that state, but the
	 * server-side gate stays intrinsic rather than client-trusted.
	 *
	 * @since 2.4.0
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_mirror() {
		if ( $this->is_local_hosting_enabled() ) {
			$this->local_fonts->mirror_used_fonts();
		}

		return \rest_ensure_response( $this->get_payload() );
	}

	/**
	 * Whether both the local-hosting toggle and the cloud fonts feature are enabled.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function is_local_hosting_enabled(): bool {
		return $this->is_cloud_fonts_enabled()
			&& (bool) $this->plugin_settings->get( 'typography_host_cloud_fonts_locally', 'yes' );
	}

	/**
	 * Whether the cloud fonts feature itself is enabled.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function is_cloud_fonts_enabled(): bool {
		return (bool) $this->plugin_settings->get( 'typography_cloud_fonts', 'yes' );
	}

	/**
	 * Build the local fonts status/controls payload: the union of manifest
	 * families and currently-used cloud font families, so a family that is
	 * used but not yet (successfully) mirrored still shows up.
	 *
	 * @since 2.4.0
	 *
	 * @return array
	 */
	public function get_payload(): array {
		$used_families = $this->sm_fonts->get_used_cloud_font_families();
		$manifest      = $this->local_font_store->get_manifest();

		$families             = [];
		$used_unhealthy_count = 0;

		foreach ( $manifest as $family => $entry ) {
			$family = (string) $family;
			if ( '' === $family ) {
				continue;
			}

			$entry   = is_array( $entry ) ? $entry : [];
			$healthy = $this->local_font_store->is_healthy( $family );
			$used    = in_array( $family, $used_families, true );

			$families[ $family ] = [
				'family'  => $family,
				'display' => ! empty( $entry['family_display'] ) ? (string) $entry['family_display'] : $family,
				'status'  => (string) ( $entry['status'] ?? '' ),
				'healthy' => $healthy,
				'used'    => $used,
			];

			if ( $used && ! $healthy ) {
				$used_unhealthy_count++;
			}
		}

		foreach ( $used_families as $family ) {
			if ( ! is_string( $family ) || '' === $family || isset( $families[ $family ] ) ) {
				continue;
			}

			// Used but absent from the manifest -- never (successfully) mirrored.
			$families[ $family ] = [
				'family'  => $family,
				'display' => $family,
				'status'  => '',
				'healthy' => false,
				'used'    => true,
			];

			$used_unhealthy_count++;
		}

		return [
			'enabled'            => (bool) $this->plugin_settings->get( 'typography_host_cloud_fonts_locally', 'yes' ),
			'cloudFontsEnabled'  => $this->is_cloud_fonts_enabled(),
			'supported'          => \Pixelgrade\StyleManager\is_sm_supported(),
			'families'           => array_values( $families ),
			'usedUnhealthyCount' => $used_unhealthy_count,
			'settingsUrl'        => $this->get_settings_url(),
		];
	}

	/**
	 * Build the Settings screen URL.
	 *
	 * Mirrors Screen\Settings::get_page_parent() without needing an instance
	 * of that (admin-only, is_admin()-gated) screen class -- this endpoint's
	 * `rest_api_init` callback can run on a request where the admin menu was
	 * never registered, so `menu_page_url()` cannot be relied upon here.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_settings_url(): string {
		$parent_slug = \is_network_admin() ? 'settings.php' : 'options-general.php';

		return \admin_url( $parent_slug . '?page=' . Settings::MENU_SLUG );
	}
}

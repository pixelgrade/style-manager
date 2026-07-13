<?php
/**
 * Pixelgrade Assistant plugin integration.
 *
 * @link    https://wordpress.org/plugins/pixelgrade-assistant/
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Integration;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Pixelgrade Assistant plugin integration provider class.
 *
 * @since 2.0.0
 */
class PixelgradeAssistant extends AbstractHookProvider {

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Plugin settings.
	 *
	 * @var PluginSettings
	 */
	protected PluginSettings $plugin_settings;

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
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options        $options          Options.
	 * @param PluginSettings $plugin_settings  Plugin settings.
	 * @param LocalFontStore $local_font_store Local font store.
	 * @param Fonts          $sm_fonts         Style Manager Fonts.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		LocalFontStore $local_font_store,
		Fonts $sm_fonts
	) {
		$this->options          = $options;
		$this->plugin_settings  = $plugin_settings;
		$this->local_font_store = $local_font_store;
		$this->sm_fonts         = $sm_fonts;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_filter( 'pre_set_theme_mod_pixassist_license', 'invalidate_all_caches', 10, 1 );
		$this->add_filter( 'pixassist_styles_data', 'add_design_system_preview', 10, 1 );
		$this->add_action( 'admin_enqueue_scripts', 'maybe_enqueue_design_hub_assets', 10, 1 );
		$this->add_filter( 'pixassist_setup_readiness_checks', 'add_local_fonts_readiness_check', 10, 2 );
	}

	/**
	 * Advertises the versioned saved design-system preview contract to Assistant.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $data Assistant Styles tab data.
	 *
	 * @return mixed
	 */
	protected function add_design_system_preview( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data['designSystemPreview'] = [
			'schemaVersion' => DesignSystemPreviewEndpoint::SCHEMA_VERSION,
			'path'          => '/' . DesignSystemPreviewEndpoint::REST_NAMESPACE . DesignSystemPreviewEndpoint::REST_PATH,
		];

		return $data;
	}

	/**
	 * Invalidate all caches on license update.
	 *
	 * @since 2.0.0
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	protected function invalidate_all_caches( $value ) {
		$this->options->invalidate_all_caches();

		return $value;
	}

	/**
	 * Enqueue the Style Manager "Fonts" section bundle for the Pixelgrade
	 * Design hub's Site Setup tab.
	 *
	 * Gated on: being on the hub's top-level admin page, the hub itself being
	 * available (Assistant active, Pixelgrade Care not shadowing it), Style
	 * Manager being supported on the current theme, and the cloud fonts
	 * feature being enabled -- so the bundle never loads its filter into a
	 * hub screen that has nothing to contribute.
	 *
	 * @since 2.4.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	protected function maybe_enqueue_design_hub_assets( $hook_suffix ): void {
		if ( 'toplevel_page_pixelgrade' !== $hook_suffix ) {
			return;
		}

		if ( ! $this->assistant_hub_is_available() ) {
			return;
		}

		if ( ! \Pixelgrade\StyleManager\is_sm_supported() ) {
			return;
		}

		if ( ! $this->plugin_settings->get( 'typography_cloud_fonts', 'yes' ) ) {
			return;
		}

		\wp_enqueue_script(
			'style-manager-design-hub',
			$this->plugin->get_url( 'dist/js/design-hub.js' ),
			[ 'wp-hooks', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
			VERSION,
			true
		);

		// The hub's own bundle already enqueues the 'wp-components' stylesheet
		// (its Account/Styles panels render wp.components throughout), so this
		// bundle doesn't need to enqueue it again.
	}

	/**
	 * Whether the Pixelgrade Design hub is available on this request.
	 *
	 * Extracted as a seam over the global `function_exists()` check -- the
	 * function itself is defined by the Assistant plugin at runtime, which
	 * can't be toggled on/off mid test-suite the way an overridden method can.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function assistant_hub_is_available(): bool {
		return \function_exists( 'pixassist_get_hub_url' );
	}

	/**
	 * Contribute a "Fonts" row to Assistant's Setup tab readiness checklist:
	 * the signal that pre-existing sites are quietly migrated to hosting their
	 * cloud fonts locally, without a proactive admin notice.
	 *
	 * A no-op (returns $checks untouched) unless Style Manager is supported on
	 * the current theme and the cloud fonts feature is enabled -- so a site
	 * that never uses cloud fonts never sees this row at all.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $checks Existing readiness check rows.
	 * @param array $facts  Facts gathered by Assistant for the readiness checks.
	 *
	 * @return mixed
	 */
	protected function add_local_fonts_readiness_check( $checks, $facts ) {
		if ( ! is_array( $checks ) ) {
			$checks = [];
		}

		if ( ! \Pixelgrade\StyleManager\is_sm_supported() ) {
			return $checks;
		}

		if ( ! $this->plugin_settings->get( 'typography_cloud_fonts', 'yes' ) ) {
			return $checks;
		}

		$used_families = [];
		foreach ( $this->sm_fonts->get_used_cloud_font_families() as $family ) {
			if ( is_string( $family ) && '' !== $family ) {
				$used_families[] = $family;
			}
		}
		$used_count = count( $used_families );

		$unhealthy_count = 0;
		foreach ( $used_families as $family ) {
			if ( ! $this->local_font_store->is_healthy( $family ) ) {
				$unhealthy_count++;
			}
		}

		$hosting_enabled = (bool) $this->plugin_settings->get( 'typography_host_cloud_fonts_locally', 'yes' );

		$status = 'ok';
		if ( ( ! $hosting_enabled && $used_count > 0 ) || $unhealthy_count > 0 ) {
			$status = 'warning';
		}

		if ( 0 === $used_count ) {
			$value = esc_html__( 'No cloud fonts in use yet.', '__plugin_txtd' );
		} elseif ( 'warning' === $status ) {
			// While hosting is off, every used family still loads straight from
			// Pixelgrade Cloud regardless of its local mirror's health.
			$still_from_cloud_count = $hosting_enabled ? $unhealthy_count : $used_count;

			$value = esc_html(
				sprintf(
					/* translators: %d: number of fonts still loaded from Pixelgrade Cloud. */
					_n(
						'%d font still loads from Pixelgrade Cloud',
						'%d fonts still load from Pixelgrade Cloud',
						$still_from_cloud_count,
						'__plugin_txtd'
					),
					$still_from_cloud_count
				)
			);
		} else {
			$value = esc_html(
				sprintf(
					/* translators: %d: number of fonts served from this site. */
					_n(
						'%d font served from your site',
						'%d fonts served from your site',
						$used_count,
						'__plugin_txtd'
					),
					$used_count
				)
			);
		}

		$check = [
			'id'       => 'sm-local-fonts',
			'group'    => 'integrations',
			'label'    => esc_html__( 'Fonts on Your Site', '__plugin_txtd' ),
			'status'   => $status,
			'value'    => $value,
			'expected' => esc_html__( 'Fonts served from your own site', '__plugin_txtd' ),
			'why'      => esc_html__( 'Fonts saved to your site load privately — visitors never connect to Pixelgrade — and they’re yours to keep, working forever, with or without us.', '__plugin_txtd' ),
			'action'   => null,
			'items'    => [],
		];

		if ( 'warning' === $status ) {
			$hub_url = $this->get_hub_fonts_url();
			if ( '' !== $hub_url ) {
				$check['action'] = [
					'label' => esc_html__( 'Review fonts', '__plugin_txtd' ),
					'url'   => $hub_url,
				];
			}
		}

		$checks[] = $check;

		return $checks;
	}

	/**
	 * Get the URL to the Fonts section of the Pixelgrade Design hub's Site
	 * Setup tab.
	 *
	 * Extracted as a seam over the shared namespaced helper -- the underlying
	 * `pixassist_get_hub_url()` function is defined by the Assistant plugin at
	 * runtime, which can't be toggled on/off mid test-suite the way an
	 * overridden method can.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_hub_fonts_url(): string {
		return \Pixelgrade\StyleManager\get_design_hub_fonts_url();
	}
}

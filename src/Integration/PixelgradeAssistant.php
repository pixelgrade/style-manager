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
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options        $options         Options.
	 * @param PluginSettings $plugin_settings Plugin settings.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings
	) {
		$this->options         = $options;
		$this->plugin_settings = $plugin_settings;
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
	 * Design hub's Styles tab.
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
}

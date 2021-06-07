<?php
/**
 * Plugin activation routines.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Capabilities;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Class to activate the plugin.
 *
 * @since 2.0.0
 */
class Activation extends AbstractHookProvider {

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
	 * @param Options         $options Options.
	 * @param PluginSettings  $plugin_settings
	 * @param LoggerInterface $logger  Logger.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->options = $options;
		$this->plugin_settings = $plugin_settings;
		$this->logger  = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		register_activation_hook( $this->plugin->get_file(), [ $this, 'activate' ] );
	}

	/**
	 * Activate the plugin.
	 *
	 * - Set default plugin settings.
	 * - Sets a flag to flush rewrite rules after plugin rewrite rules have been
	 *   registered.
	 * - Registers capabilities for the admin role.
	 * - Make sure the Customify plugin is deactivated upon activation.
	 *
	 * @since 2.0.0
	 * @see   \Pixelgrade\StyleManager\Provider\RewriteRules::maybe_flush_rewrite_rules()
	 *
	 */
	public function activate() {
		$this->install();

		$this->options->invalidate_all_caches();

		update_option( 'pixelgrade_style_manager_flush_rewrite_rules', 'yes' );

		Capabilities::register();

		$this->maybe_deactivate_customify();
	}

	/*
	 * Install everything needed.
	 */
	private function install() {

		$default_settings = [
			'values_store_mod'              => 'theme_mod',
			'disable_default_sections'      => [],
			'enable_reset_buttons'          => false,
			'enable_editor_style'           => true,
			'style_resources_location'      => 'wp_head',
			'enable_typography'             => true,
			'typography_system_fonts'       => true,
			'typography_google_fonts'       => true,
			'typography_group_google_fonts' => true,
			'typography_cloud_fonts'        => true,
		];

		$current_settings = $this->plugin_settings->get_all();

		if ( empty( $current_settings ) ) {
			// If the settings are empty, set them to the default value.
			$this->plugin_settings->set_all( $default_settings );
		} elseif ( count( array_diff_key( $default_settings, $current_settings ) ) > 0 ) {
			// If we have different keys (possibly new keys).
			$plugin_settings = array_merge( $default_settings, $current_settings );
			$this->plugin_settings->set_all( $plugin_settings );
		}
	}

	protected function maybe_deactivate_customify() {
		$deactivate = [];
		foreach ( get_plugins() as $plugin_filename => $plugin_data ) {
			// We will search all plugins by the Customify file name and deactivate any one of them that are active.
			// This way we account for modified directories, etc.
			if ( strrpos( $plugin_filename, 'customify.php' ) === ( strlen( $plugin_filename ) - strlen( 'customify.php' ) )
				&& is_plugin_active( $plugin_filename ) ) {
				$deactivate[] = $plugin_filename;
			}
		}

		if ( ! empty( $deactivate ) ) {
			deactivate_plugins( $deactivate );
		}
	}
}

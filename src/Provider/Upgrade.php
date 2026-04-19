<?php
/**
 * Upgrade routines.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Capabilities;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

use const Pixelgrade\StyleManager\VERSION;

/**
 * Class for upgrade routines.
 *
 * @since 2.0.0
 */
class Upgrade extends AbstractHookProvider {
	/**
	 * Version option name.
	 *
	 * @var string
	 */
	const VERSION_OPTION_NAME = 'style_manager_dbversion';

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
	 * @param Options         $options         Options.
	 * @param PluginSettings  $plugin_settings Plugin settings.
	 * @param LoggerInterface $logger          Logger.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->options         = $options;
		$this->plugin_settings = $plugin_settings;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		add_action( 'admin_init', [ $this, 'maybe_upgrade' ] );
	}

	/**
	 * Upgrade when the database version is outdated.
	 *
	 * @since 2.0.0
	 */
	public function maybe_upgrade() {
		$saved_version = get_option( self::VERSION_OPTION_NAME, '0' );

		// For versions, previous of version 2.0.0 (the rewrite release).
		if ( version_compare( $saved_version, '2.0.0', '<' ) ) {
			// Delete the option holding the fact that the user offered feedback.
			delete_option( 'style_manager_user_feedback_provided' );

			// Make sure that admins have the required capability to access the plugin settings page.
			Capabilities::register();

			// Migrate the old plugin settings to the new ones.
			$existing_settings = get_option( 'pixcustomify_settings' );
			if ( ! empty( $existing_settings ) && is_array( $existing_settings ) ) {
				$new_settings = [];
				if ( isset( $existing_settings['values_store_mod'] ) ) {
					$new_settings['values_store_mod'] = $existing_settings['values_store_mod'];
				}
				if ( isset( $existing_settings['disable_default_sections'] ) && is_array( $existing_settings['disable_default_sections'] ) ) {
					$new_settings['disable_default_sections'] = [];
					foreach ( $existing_settings['disable_default_sections'] as $section => $disabled ) {
						if ( 'on' === $disabled ) {
							$new_settings['disable_default_sections'][] = $section;
						}
					}
				}
				if ( isset( $existing_settings['enable_reset_buttons'] ) ) {
					$new_settings['enable_reset_buttons'] = ! empty ( $existing_settings['enable_reset_buttons'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['enable_editor_style'] ) ) {
					$new_settings['enable_editor_style'] = ! empty ( $existing_settings['enable_editor_style'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['style_resources_location'] ) ) {
					$new_settings['style_resources_location'] = $existing_settings['style_resources_location'];
				}
				if ( isset( $existing_settings['typography'] ) ) {
					$new_settings['enable_typography'] = ! empty ( $existing_settings['typography'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['typography_system_fonts'] ) ) {
					$new_settings['typography_system_fonts'] = ! empty ( $existing_settings['typography_system_fonts'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['typography_google_fonts'] ) ) {
					$new_settings['typography_google_fonts'] = ! empty ( $existing_settings['typography_google_fonts'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['typography_group_google_fonts'] ) ) {
					$new_settings['typography_group_google_fonts'] = ! empty ( $existing_settings['typography_group_google_fonts'] ) ? 'yes' : '';
				}
				if ( isset( $existing_settings['typography_cloud_fonts'] ) ) {
					$new_settings['typography_cloud_fonts'] = ! empty ( $existing_settings['typography_cloud_fonts'] ) ? 'yes' : '';
				}

				if ( ! empty( $new_settings ) ) {
					$this->plugin_settings->set_all( $new_settings );
					// Do not delete the old Customify settings just in case people revert back to it.
				}
			}
		}

		if ( version_compare( $saved_version, VERSION, '<' ) ) {
			// Always invalidate caches on upgrade, just to be sure.
			$this->options->invalidate_all_caches();

			update_option( self::VERSION_OPTION_NAME, VERSION );
		}

		// Migrate cache options away from autoload (issue #86).
		// Gated on a one-shot flag rather than version so it runs once per install regardless of release timing.
		if ( ! get_option( 'sm_perf_autoload_migrated_v1', false ) ) {
			$this->migrate_cache_options_off_autoload();
			add_option( 'sm_perf_autoload_migrated_v1', '1', '', false );
		}
	}

	/**
	 * Move SM cache options out of the wp_options autoload payload.
	 *
	 * On Pixelgrade-themed sites the SM cache entries can total 100+ KB of autoload payload
	 * loaded into PHP memory on every WordPress request — frontend included — even though
	 * these caches are only consumed in admin/Customizer code paths. Flipping autoload to
	 * false removes them from the autoload blob with no behavioural change. Reads happen
	 * via a dedicated query (or object cache hit, where present) the first time each option
	 * is requested in a given request lifecycle. See pixelgrade/style-manager#86.
	 *
	 * WordPress has no portable API for flipping an existing option's autoload flag across
	 * versions, so we delete + re-add. Idempotent: if the value is missing, the entry is
	 * simply skipped on the next iteration.
	 *
	 * @since 2.2.13
	 */
	protected function migrate_cache_options_off_autoload(): void {
		$keys = [
			Options::CUSTOMIZER_OPT_NAME_CACHE_KEY,
			Options::CUSTOMIZER_OPT_NAME_CACHE_TIMESTAMP_KEY,
			Options::MINIMAL_DETAILS_CACHE_KEY,
			Options::DETAILS_CACHE_TIMESTAMP_KEY,
			Options::CUSTOMIZER_CONFIG_CACHE_TIMESTAMP_KEY,
			DesignAssets::CACHE_KEY,
			DesignAssets::CACHE_TIMESTAMP_KEY,
		];

		foreach ( $keys as $key ) {
			$value = get_option( $key );
			if ( false === $value ) {
				continue;
			}

			delete_option( $key );
			add_option( $key, $value, '', false );
		}
	}
}

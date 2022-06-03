<?php
/**
 * Main plugin class
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\Plugin as BasePlugin;
use Pixelgrade\StyleManager\Vendor\Psr\Container\ContainerInterface;

/**
 * Main plugin class - composition root.
 *
 * @since 2.0.0
 */
class Plugin extends BasePlugin implements Composable {
	/**
	 * Compose the object graph.
	 *
	 * @since 2.0.0
	 */
	public function compose() {
		$container = $this->get_container();

		/**
		 * Start composing the object graph in Style Manager.
		 *
		 * @since 2.0.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'style_manager/compose', $this, $container );

		// Register hook providers.

		$this
			->register_hooks( $container->get( 'hooks.i18n' ) )
			->register_hooks( $container->get( 'hooks.capabilities' ) )
			->register_hooks( $container->get( 'hooks.rewrite_rules' ) )
			->register_hooks( $container->get( 'hooks.general_assets' ) )
			->register_hooks( $container->get( 'hooks.customizer_assets' ) )
			// @todo We should investigate if we could register these only in the Customizer.
			->register_hooks( $container->get( 'customize.cloud_fonts' ) )
			->register_hooks( $container->get( 'customize.color_palettes' ) )
			->register_hooks( $container->get( 'customize.design_assets' ) )
			->register_hooks( $container->get( 'customize.font_palettes' ) )
			->register_hooks( $container->get( 'customize.fonts' ) )
			->register_hooks( $container->get( 'customize.spacing_section' ) )
			->register_hooks( $container->get( 'customize.tweak_board_section' ) )
			->register_hooks( $container->get( 'customize.general' ) )
			->register_hooks( $container->get( 'customize.theme_configs' ) )
			->register_hooks( $container->get( 'screen.customizer' ) )
			->register_hooks( $container->get( 'options' ) );

		if ( is_admin() ) {
			$this
				->register_hooks( $container->get( 'hooks.upgrade' ) )
				->register_hooks( $container->get( 'hooks.general_assets' ) )
				->register_hooks( $container->get( 'hooks.admin_assets' ) )
				->register_hooks( $container->get( 'screen.general_admin' ) )
				->register_hooks( $container->get( 'screen.settings' ) )
				->register_hooks( $container->get( 'screen.edit_with_blocks' ) );

			if ( is_customizer() ) {
				$this->register_hooks( $container->get( 'screen.customizer.search' ) );
			}
		} else {
			$this->register_hooks( $container->get( 'hooks.frontend_output' ) );
		}

		// Only in the Customizer Preview.
		if ( is_customize_preview() ) {
			$this
				->register_hooks( $container->get( 'screen.customizer.preview' ) )
				->register_hooks( $container->get( 'hooks.customizer_preview_assets' ) );
		}

		if ( \defined( 'AUTOPTIMIZE_PLUGIN_VERSION' ) ) {
			$this->register_hooks( $container->get( 'integration.autoptimize' ) );
		}

		if ( \function_exists( 'PixelgradeAssistant' ) ) {
			$this->register_hooks( $container->get( 'integration.pixelgrade_assistant' ) );
		}

		if ( \function_exists( 'PixelgradeCare' ) ) {
			$this->register_hooks( $container->get( 'integration.pixelgrade_care' ) );
		}

		if ( \defined( 'TRIBE_EVENTS_FILE' ) ) {
			$this->register_hooks( $container->get( 'integration.the_events_calendar' ) );
		}

		if ( \class_exists( 'W3TC\Root_Loader' ) ) {
			$this->register_hooks( $container->get( 'integration.w3_total_cache' ) );
		}

		if ( \class_exists( 'WpFastestCache' ) ) {
			$this->register_hooks( $container->get( 'integration.wp_fastest_cache' ) );
		}

		if ( \defined( 'WP_ROCKET_VERSION' ) ) {
			$this->register_hooks( $container->get( 'integration.wp_rocket' ) );
		}

		/**
		 * Finished composing the object graph in Style Manager.
		 *
		 * @since 2.0.0
		 *
		 * @param Plugin             $plugin    Main plugin instance.
		 * @param ContainerInterface $container Dependency container.
		 */
		do_action( 'style_manager/composed', $this, $container );
	}
}

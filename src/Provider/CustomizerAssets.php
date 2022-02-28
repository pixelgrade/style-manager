<?php
/**
 * Customizer assets provider.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Customize\Fonts;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Customizer assets provider class.
 *
 * @since 2.0.0
 */
class CustomizerAssets extends AbstractHookProvider {
	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'customize_controls_init', [ $this, 'register_assets' ], 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 */
	public function register_assets() {
		$scripts_suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$rtl_suffix     = is_rtl() ? '-rtl' : '';

		/**
		 * GENERAL CUSTOMIZER RELATED
		 */
		wp_register_script( 'pixelgrade_style_manager-select2',
			$this->plugin->get_url( 'vendor_js/select2-4.0.13/dist/js/select2.full' . $scripts_suffix . '.js' ),
			[ 'jquery' ],
			VERSION );
		wp_register_script( 'jquery-react',
			$this->plugin->get_url( 'vendor_js/jquery-react' . $scripts_suffix . '.js' ),
			[ 'jquery' ],
			VERSION );
		wp_register_script( 'pixelgrade_style_manager-regression',
			$this->plugin->get_url( 'vendor_js/regression' . $scripts_suffix . '.js' ),
			[],
			VERSION );
		wp_register_script( 'pixelgrade_style_manager-chroma',
			$this->plugin->get_url( 'vendor_js/chroma' . $scripts_suffix . '.js' ),
			[],
			VERSION );
		wp_register_script( 'pixelgrade_style_manager-customizer',
			$this->plugin->get_url( 'dist/js/customizer.js' ),
			[
				'jquery',
				'jquery-react',
				'pixelgrade_style_manager-chroma',
				'pixelgrade_style_manager-select2',
				'pixelgrade_style_manager-regression',
				'react',
				'react-dom',
				'underscore',
				'customize-controls',
			],
			VERSION );
		wp_localize_script( 'pixelgrade_style_manager-customizer', 'WP_API_Settings', [
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );

		wp_register_style(
			'pixelgrade_style_manager-customizer',
			$this->plugin->get_url( 'dist/js/customizer' . $rtl_suffix . '.css' ),
			[],
			VERSION
		);

		/**
		 * CUSTOMIZER CONTROLS RELATED
		 */
		wp_register_script( 'pixelgrade_style_manager-ace-editor',
			$this->plugin->get_url( 'vendor_js/ace/ace.js' ),
			[ 'jquery' ],
			VERSION,
			true );

		/**
		 * COLOR PALETTES RELATED
		 */


		/**
		 * FONT PALETTES RELATED
		 */
		wp_register_script( 'pixelgrade_style_manager-previewer',
			$this->plugin->get_url( 'dist/js/customizer-preview.js' ),
			[
				'jquery',
				'lodash',
				'customize-preview',
				'underscore',
			],
			VERSION, true );

		/**
		 * CONTROLS SEARCH FIELD RELATED
		 */
		wp_register_script( 'pixelgrade_style_manager-fuse',
			$this->plugin->get_url( 'vendor_js/fuse-6.0.0/fuse.basic' . $scripts_suffix . '.js' ),
			[],
			null );

		wp_register_script( 'pixelgrade_style_manager-customizer-search',
			$this->plugin->get_url( 'dist/js/customizer-search.js' ),
			[ 'jquery', 'pixelgrade_style_manager-fuse', ],
			VERSION );


	}
}

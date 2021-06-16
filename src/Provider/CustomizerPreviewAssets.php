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
use const Pixelgrade\StyleManager\VERSION;

/**
 * Customizer Preview assets provider class.
 *
 * @since 2.0.0
 */
class CustomizerPreviewAssets extends AbstractHookProvider {
	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'customize_preview_init', [ $this, 'register_assets' ], 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 */
	public function register_assets() {
		wp_register_script( 'pixelgrade_style_manager-previewer',
			$this->plugin->get_url( 'dist/js/customizer-preview.js' ),
			[
				'jquery',
				'lodash',
				'customize-preview',
				'underscore',
			],
			VERSION, true );
	}
}

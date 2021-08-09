<?php
/**
 * Admin dashboard assets provider.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Utils\ScriptsEnqueue;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Admin dashboard assets provider class.
 *
 * @since 2.0.0
 */
class AdminAssets extends AbstractHookProvider {
	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ], 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 */
	public function register_assets() {
		$rtl_suffix = is_rtl() ? '-rtl' : '';

		wp_register_script(
			'pixelgrade_style_manager-settings',
			$this->plugin->get_url( 'dist/js/settings.js' ),
			[ 'jquery' ],
			VERSION,
			true
		);

		wp_add_inline_script( 'pixelgrade_style_manager-settings',
			ScriptsEnqueue::getlocalizeToWindowScript( 'styleManager',
				[
					'config' => [
						'ajax_url' => admin_url( 'admin-ajax.php' ),
						'wp_rest'  => [
							'root'                     => esc_url_raw( rest_url() ),
							'nonce'                    => wp_create_nonce( 'wp_rest' ),
							'style_manager_settings_nonce' => wp_create_nonce( 'style_manager_settings_nonce' ),
						],
					],
				]
			) );

		wp_register_style(
			'pixelgrade_style_manager-settings',
			$this->plugin->get_url( 'dist/css/settings' . $rtl_suffix . '.css' ),
			[],
			VERSION
		);

		/**
		 * BLOCK EDITOR RELATED
		 */
		wp_register_script(
			'pixelgrade_style_manager-web-font-loader',
			$this->plugin->get_url( 'vendor_js/webfontloader-1-6-28.min.js' ),
			[ 'wp-editor' ], null );
	}
}

<?php
/**
 * General assets provider.
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
 * General assets provider class.
 *
 * @since 2.0.0
 */
class GeneralAssets extends AbstractHookProvider {

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options         $options Options.
	 */
	public function __construct(
		Options $options
	) {
		$this->options = $options;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', [ $this, 'register_assets' ], 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 */
	public function register_assets() {

		wp_register_style(
			'pixelgrade_style_manager-sm-colors-custom-properties',
			$this->plugin->get_url( 'dist/css/sm-colors-custom-properties.css' ),
			[],
			VERSION
		);

		wp_add_inline_style( 'pixelgrade_style_manager-sm-colors-custom-properties',
			sm_get_palette_output_from_color_config( $this->options->get( 'sm_advanced_palette_output' ) )
		);
	}
}

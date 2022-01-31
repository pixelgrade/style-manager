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
use function Pixelgrade\StyleManager\is_customizer;
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
	 * Frontend output provider.
	 *
	 * @var FrontendOutput
	 */
	protected FrontendOutput $frontend_output;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options         $options Options.
	 * @param FrontendOutput  $frontend_output Frontend output.
	 */
	public function __construct(
		Options $options,
		FrontendOutput $frontend_output
	) {
		$this->options = $options;
		$this->frontend_output = $frontend_output;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'register_assets', 1 );
		$this->add_action( 'wp_print_scripts', 'print_inline_scripts', 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	protected function register_assets() {

		wp_register_style(
			'pixelgrade_style_manager-sm-colors-custom-properties',
			$this->plugin->get_url( 'dist/css/sm-colors-custom-properties.css' ),
			[],
			VERSION
		);

		// Allow the frontend style to be inlined by WP 5.8+
		// @see https://make.wordpress.org/core/2021/07/01/block-styles-loading-enhancements-in-wordpress-5-8/
		wp_style_add_data(
			'pixelgrade_style_manager-sm-colors-custom-properties',
			'path',
			$this->plugin->get_path( 'dist/css/sm-colors-custom-properties.css' )
		);

		wp_register_script(
			'pixelgrade_style_manager-dark-mode',
			$this->plugin->get_url( 'dist/js/dark-mode.js' ),
			[ 'jquery' ],
			VERSION
		);
	}

	/**
	 * @return void
	 */
	protected function print_inline_scripts() {
		$advanced_palettes_output = $this->options->get( 'sm_advanced_palette_output', [] );
		if ( function_exists( '\get_current_screen' ) ) {
			$screen = \get_current_screen();
		}

		ob_start(); ?>

<script id="style-manager-colors-config">
	window.styleManager = window.styleManager || {};
	window.styleManager.colorsConfig = JSON.parse( <?php echo '"' . json_encode( $advanced_palettes_output ) . '"'; ?> );
	window.styleManager.siteColorVariation = <?php echo $this->options->get( 'sm_site_color_variation', 1 ) ?>;
	window.styleManager.colorsCustomPropertiesUrl = "<?php echo $this->plugin->get_url( 'dist/css/sm-colors-custom-properties.css' ); ?>";
	<?php if ( ( ! empty( $screen ) && $screen->is_block_editor() ) || is_customizer()) { ?>
 	window.styleManager.frontendOutput = <?php echo json_encode( $this->frontend_output->get_dynamic_style() ); ?>;
	 <?php } ?>
</script>

		<?php echo ob_get_clean();
	}
}

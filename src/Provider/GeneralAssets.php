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
	 */
	public function register_hooks() {
		$this->add_action( 'init', 'register_assets', 1 );
		$this->add_action( 'wp_print_scripts', 'print_inline_scripts', 1 );
	}

	/**
	 * Register scripts and styles.
	 *
	 * @since 2.0.0
	 */
	protected function register_assets() {

		wp_register_style(
			'pixelgrade_style_manager-sm-colors-custom-properties',
			$this->plugin->get_url( 'dist/css/sm-colors-custom-properties.css' ),
			[],
			VERSION
		);

		wp_register_script(
			'pixelgrade_style_manager-dark-mode',
			$this->plugin->get_url( 'dist/js/dark-mode.js' ),
			[ 'jquery' ],
			VERSION
		);

		$advanced_palettes_output = $this->options->get( 'sm_advanced_palette_output' );

		if ( $advanced_palettes_output !== null ) {
			wp_add_inline_style(
				'pixelgrade_style_manager-sm-colors-custom-properties',
				sm_get_palette_output_from_color_config( $advanced_palettes_output )
			);
		}
	}

	protected function print_inline_scripts() {
		$advanced_palettes_output = $this->options->get( 'sm_advanced_palette_output' );

		ob_start(); ?>

		<script id="style-manager-colors-config">
			window.styleManager = window.styleManager || {};
			window.styleManager.colorsConfig = JSON.parse( <?php echo json_encode( $advanced_palettes_output ); ?> );
			window.styleManager.siteColorVariation = <?php echo $this->options->get( 'sm_site_color_variation' ) ?>;
			window.styleManager.colorsCustomPropertiesUrl = "<?php echo $this->plugin->get_url( 'dist/css/sm-colors-custom-properties.css' ); ?>";
			window.styleManager.frontendOutput = <?php echo json_encode( $this->frontend_output->get_dynamic_style() ); ?>;
			<?php if ( $advanced_palettes_output !== null ) { ?>
			window.styleManager.palettes = <?php echo $advanced_palettes_output; ?>;
			window.styleManager.smAdvancedPalettesOutput = <?php echo json_encode( sm_get_palette_output_from_color_config( $advanced_palettes_output ) ); ?>;
			<?php } ?>
		</script>

		<?php echo ob_get_clean();
	}
}

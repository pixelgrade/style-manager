<?php
/**
 * Private Style Manager Lab showcase route.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use const Pixelgrade\StyleManager\VERSION;

final class ShowcaseRoute extends AbstractHookProvider {
	private ShowcaseRenderer $renderer;
	private ContextualPalette $contextual_palette;
	private Config $config;

	public function __construct( ShowcaseRenderer $renderer, ContextualPalette $contextual_palette, Config $config ) {
		$this->renderer           = $renderer;
		$this->contextual_palette = $contextual_palette;
		$this->config             = $config;
	}

	public function register_hooks() {
		if ( ! Access::is_enabled() ) {
			return;
		}

		$this->add_action( 'template_redirect', 'maybe_render', 0 );
	}

	protected function maybe_render(): void {
		if ( empty( $_GET['sm_lab_showcase'] ) ) {
			return;
		}

		if ( ! Access::current_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to access the Style Manager Lab.', '__plugin_txtd' ), '', [ 'response' => 403 ] );
		}

		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'sm_lab_showcase' ) ) {
			wp_die( esc_html__( 'The Style Manager Lab link has expired.', '__plugin_txtd' ), '', [ 'response' => 403 ] );
		}

		$params = QueryParams::from_array( wp_unslash( $_GET ) );

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		status_header( 200 );
		add_filter( 'show_admin_bar', '__return_false' );

		$this->enqueue_showcase_assets();

		echo $this->render_document( $params ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	private function enqueue_showcase_assets(): void {
		$rtl_suffix = is_rtl() ? '-rtl' : '';

		wp_register_script(
			'pixelgrade_style_manager-lab-showcase',
			$this->plugin->get_url( 'dist/js/lab-showcase.js' ),
			[ 'wp-element' ],
			VERSION,
			true
		);

		wp_register_style(
			'pixelgrade_style_manager-lab-showcase',
			$this->plugin->get_url( 'dist/js/lab-showcase' . $rtl_suffix . '.css' ),
			[ 'pixelgrade_style_manager-sm-colors-custom-properties' ],
			VERSION
		);

		wp_enqueue_style( 'pixelgrade_style_manager-sm-colors-custom-properties' );
		wp_enqueue_style( 'pixelgrade_style_manager-lab-showcase' );
		wp_enqueue_script( 'pixelgrade_style_manager-lab-showcase' );
	}

	private function render_document( QueryParams $params ): string {
		ob_start();
		?>
		<!doctype html>
		<html <?php language_attributes(); ?> class="<?php echo esc_attr( $params->dark() ? 'is-dark' : '' ); ?>">
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1">
			<?php wp_head(); ?>
			<?php $this->render_contextual_palette_css( $params ); ?>
		</head>
		<body class="<?php echo esc_attr( implode( ' ', self::filter_showcase_body_classes( get_body_class( $params->body_classes() ) ) ) ); ?>" data-sm-lab-site-variation="<?php echo esc_attr( (string) \Pixelgrade\StyleManager\get_option( 'sm_site_color_variation', 1 ) ); ?>">
			<?php echo $this->renderer->render( $params ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<script id="style-manager-lab-color-system-config">
				window.styleManagerLabColorSystem = <?php echo wp_json_encode( $this->config->color_system_preview_config( $params ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			</script>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php

		return (string) ob_get_clean();
	}

	public static function filter_showcase_body_classes( array $classes ): array {
		$filtered = array_filter( $classes, static function( $class ): bool {
			$class = (string) $class;

			if ( 'is-loading' === $class ) {
				return false;
			}

			return 0 !== strpos( $class, 'has-intro-animations' );
		} );

		return array_values( array_unique( $filtered ) );
	}

	private function render_contextual_palette_css( QueryParams $params ): void {
		$css = $this->contextual_palette->css_for_color( $params->contextual() );

		if ( '' === $css ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dynamic CSS inside a <style> tag; wp_strip_all_tags prevents tag breakout and HTML-escaping would corrupt valid CSS.
		printf(
			'<style id="style-manager-lab-contextual-palette">%s</style>',
			wp_strip_all_tags( $css )
		);
	}
}

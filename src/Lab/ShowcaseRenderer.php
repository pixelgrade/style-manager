<?php
/**
 * Style Manager Lab showcase markup.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

final class ShowcaseRenderer {
	public function render( QueryParams $params ): string {
		ob_start();
		?>
		<main class="sm-lab-showcase__page" data-sm-lab-showcase>
			<?php
			$this->render_status_strip( $params );
			$this->render_typography_stack();
			$this->render_interactive_primitives();
			$this->render_color_system_preview();
			$this->render_contextual_palette_demo( $params );
			$this->render_nova_blocks_zone();
			$this->render_tosca_inspired_zone();
			?>
		</main>
		<?php

		return (string) ob_get_clean();
	}

	private function render_status_strip( QueryParams $params ): void {
		?>
		<section class="sm-lab-zone sm-lab-status" aria-label="<?php esc_attr_e( 'Style Manager Lab status', '__plugin_txtd' ); ?>">
			<div>
				<strong><?php esc_html_e( 'Palette', '__plugin_txtd' ); ?></strong>
				<span data-sm-lab-status-value="palette"><?php echo esc_html( $params->palette() ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Variation', '__plugin_txtd' ); ?></strong>
				<span data-sm-lab-status-value="variation"><?php echo esc_html( (string) $params->variation() ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Contextual', '__plugin_txtd' ); ?></strong>
				<span data-sm-lab-status-value="contextual"><?php echo esc_html( '' !== $params->contextual() ? $params->contextual() : 'off' ); ?></span>
			</div>
			<div>
				<strong><?php esc_html_e( 'Dark', '__plugin_txtd' ); ?></strong>
				<span data-sm-lab-status-value="dark"><?php echo esc_html( $params->dark() ? 'on' : 'off' ); ?></span>
			</div>
			<div class="sm-lab-swatches" data-sm-lab-readback>
				<?php foreach ( [ 'bg', 'accent', 'fg1', 'fg2' ] as $token ) : ?>
					<span class="sm-lab-swatch" data-token="<?php echo esc_attr( $token ); ?>">
						<span class="sm-lab-swatch__chip" style="background: var(--sm-current-<?php echo esc_attr( $token ); ?>-color);"></span>
						<code>--sm-current-<?php echo esc_html( $token ); ?>-color</code>
						<span class="sm-lab-swatch__value" data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span>
					</span>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_typography_stack(): void {
		?>
		<section class="sm-lab-zone sm-lab-typography">
			<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Typography stack', '__plugin_txtd' ); ?></p>
			<h1><?php esc_html_e( 'Heading One Validates the Display Scale', '__plugin_txtd' ); ?></h1>
			<h2><?php esc_html_e( 'Heading Two Carries Editorial Hierarchy', '__plugin_txtd' ); ?></h2>
			<h3><?php esc_html_e( 'Heading Three Handles Section Rhythm', '__plugin_txtd' ); ?></h3>
			<h4><?php esc_html_e( 'Heading Four Supports Dense Content', '__plugin_txtd' ); ?></h4>
			<h5><?php esc_html_e( 'Heading Five Stays Legible', '__plugin_txtd' ); ?></h5>
			<h6><?php esc_html_e( 'Heading Six Keeps Contrast', '__plugin_txtd' ); ?></h6>
			<p class="sm-lab-lead"><?php esc_html_e( 'A lead paragraph checks the bridge between Style Manager roles and theme-facing typography variables.', '__plugin_txtd' ); ?></p>
			<p><?php esc_html_e( 'Body copy should remain calm, readable, and correctly colored across every palette variation.', '__plugin_txtd' ); ?></p>
			<p class="sm-lab-caption"><?php esc_html_e( 'Caption, navigation, and small text samples expose weaker contrast pairings quickly.', '__plugin_txtd' ); ?></p>
		</section>
		<?php
	}

	private function render_interactive_primitives(): void {
		?>
		<section class="sm-lab-zone sm-lab-primitives">
			<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Interactive primitives', '__plugin_txtd' ); ?></p>
			<div class="sm-lab-button-row">
				<button class="sm-lab-button sm-lab-button--primary"><?php esc_html_e( 'Primary', '__plugin_txtd' ); ?></button>
				<button class="sm-lab-button sm-lab-button--outline"><?php esc_html_e( 'Outline', '__plugin_txtd' ); ?></button>
				<button class="sm-lab-button sm-lab-button--naked"><?php esc_html_e( 'Naked', '__plugin_txtd' ); ?></button>
			</div>
			<p>
				<a href="#sm-lab-link"><?php esc_html_e( 'Default link state', '__plugin_txtd' ); ?></a>
				<a class="sm-lab-link-hover" href="#sm-lab-hover"><?php esc_html_e( 'Hover-like link state', '__plugin_txtd' ); ?></a>
			</p>
			<form class="sm-lab-form" action="#" method="get">
				<label>
					<span><?php esc_html_e( 'Text input', '__plugin_txtd' ); ?></span>
					<input type="text" value="<?php esc_attr_e( 'Palette sample', '__plugin_txtd' ); ?>" />
				</label>
				<label><input type="checkbox" checked /> <?php esc_html_e( 'Checkbox', '__plugin_txtd' ); ?></label>
				<label><input type="radio" checked /> <?php esc_html_e( 'Radio', '__plugin_txtd' ); ?></label>
				<label>
					<span><?php esc_html_e( 'Select', '__plugin_txtd' ); ?></span>
					<select><option><?php esc_html_e( 'Option', '__plugin_txtd' ); ?></option></select>
				</label>
			</form>
		</section>
		<?php
	}

	private function render_color_system_preview(): void {
		?>
		<section class="sm-lab-zone sm-lab-color-system" data-sm-lab-color-system-zone="1">
			<div id="style-manager-lab-color-system-root"></div>
		</section>
		<?php
	}

	private function render_contextual_palette_demo( QueryParams $params ): void {
		?>
		<section class="sm-lab-zone sm-lab-contextual sm-palette-<?php echo esc_attr( ContextualPalette::ID ); ?> sm-variation-<?php echo esc_attr( (string) $params->variation() ); ?>" data-palette="<?php echo esc_attr( ContextualPalette::ID ); ?>">
			<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Contextual palette', '__plugin_txtd' ); ?></p>
			<h2><?php esc_html_e( 'Runtime Contextual Surface', '__plugin_txtd' ); ?></h2>
			<p><?php esc_html_e( 'This zone is intentionally generated from the sidebar source color and never writes to saved options.', '__plugin_txtd' ); ?></p>
			<a class="sm-lab-button sm-lab-button--primary" href="#contextual"><?php esc_html_e( 'Inspect contrast', '__plugin_txtd' ); ?></a>
		</section>
		<?php
	}

	private function render_nova_blocks_zone(): void {
		if ( ! function_exists( 'novablocks_get_plugin_path' ) ) {
			return;
		}

		?>
		<section class="sm-lab-zone sm-lab-nova-blocks">
			<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Nova Blocks', '__plugin_txtd' ); ?></p>
			<?php
			echo do_blocks( '<!-- wp:novablocks/headline {"content":"Nova Blocks Headline"} /-->' );
			echo do_blocks( '<!-- wp:novablocks/supernova {"postsToShow":3} /-->' );
			?>
		</section>
		<?php
	}

	/**
	 * Render a Tosca-inspired "Text Attributes" zone.
	 *
	 * The markup is a hand-authored approximation of Tosca starter's
	 * block-patterns/attributes/#text-features pattern, using core blocks only so
	 * it renders deterministically regardless of which starter (if any) is
	 * imported. Guarded on Anima being active because the value of this zone
	 * is watching `--theme-*` typography + `sm-variation-*` bindings applied to
	 * realistic editorial content.
	 */
	private function render_tosca_inspired_zone(): void {
		if ( ! function_exists( 'wp_get_theme' ) || 'anima' !== wp_get_theme()->get_stylesheet() ) {
			return;
		}

		$markup = <<<'MARKUP'
<!-- wp:group {"className":"sm-palette-1 sm-variation-1"} -->
<div class="wp-block-group sm-palette-1 sm-variation-1" data-palette="1" data-palette-variation="1" data-color-signal="0">
  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Text Attributes</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph {"className":"is-style-lead"} -->
  <p class="is-style-lead">Use this pattern to list text-based attributes for your service or products.</p>
  <!-- /wp:paragraph -->

  <!-- wp:columns -->
  <div class="wp-block-columns">
    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Free Delivery in Europe</h4><!-- /wp:heading -->
      <!-- wp:paragraph --><p>We love Europe, so we ship with no costs anywhere across the continent.</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Thoughtful Packaging</h4><!-- /wp:heading -->
      <!-- wp:paragraph --><p>We show care beyond the product and help Mother Nature save resources.</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Secure Payments</h4><!-- /wp:heading -->
      <!-- wp:paragraph --><p>Visa · Mastercard · Bitcoin</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:heading {"level":4} --><h4 class="wp-block-heading">Customer Care</h4><!-- /wp:heading -->
      <!-- wp:paragraph --><p>+33 (0)1 4439 800</p><!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
MARKUP;

		?>
		<section class="sm-lab-zone sm-lab-tosca-inspired">
			<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Tosca-inspired — Text Attributes', '__plugin_txtd' ); ?></p>
			<?php echo do_blocks( $markup ); ?>
		</section>
		<?php
	}
}

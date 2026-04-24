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
			$this->render_system_generator( $params );
			$this->render_contextual_palette_demo( $params );
			$this->render_context_matrix( $params );
			$this->render_semantic_contract();
			$this->render_typography_stack();
			$this->render_interactive_primitives();
			$this->render_color_system_preview();
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

	private function render_system_generator( QueryParams $params ): void {
		?>
		<section class="sm-lab-zone sm-lab-generator" data-sm-lab-proof="generator">
			<div class="sm-lab-generator__intro">
				<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'System generator', '__plugin_txtd' ); ?></p>
				<h1><?php esc_html_e( 'One brand input becomes a complete runtime system', '__plugin_txtd' ); ?></h1>
				<p><?php esc_html_e( 'Style Manager turns a selected palette and variation into surface, accent, and text roles that real theme and block UI can consume without knowing how the scale was generated.', '__plugin_txtd' ); ?></p>
			</div>
			<div class="sm-lab-flow" aria-label="<?php esc_attr_e( 'Style Manager design-system flow', '__plugin_txtd' ); ?>">
				<div class="sm-lab-flow__step">
					<span class="sm-lab-flow__index">1</span>
					<h2><?php esc_html_e( 'Brand input', '__plugin_txtd' ); ?></h2>
					<p><?php esc_html_e( 'Palette source and active color signal set the runtime context.', '__plugin_txtd' ); ?></p>
					<dl class="sm-lab-flow__facts">
						<div>
							<dt><?php esc_html_e( 'Palette', '__plugin_txtd' ); ?></dt>
							<dd><?php echo esc_html( $params->palette() ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Variation', '__plugin_txtd' ); ?></dt>
							<dd data-sm-lab-status-value="variation"><?php echo esc_html( (string) $params->variation() ); ?></dd>
						</div>
					</dl>
				</div>
				<div class="sm-lab-flow__step sm-lab-flow__step--roles">
					<span class="sm-lab-flow__index">2</span>
					<h2><?php esc_html_e( 'Generated roles', '__plugin_txtd' ); ?></h2>
					<p><?php esc_html_e( 'The palette resolves into the four runtime roles that drive the page.', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-role-grid">
						<?php
						foreach ( [
							'bg'     => __( 'Surface', '__plugin_txtd' ),
							'accent' => __( 'Accent', '__plugin_txtd' ),
							'fg1'    => __( 'Text primary', '__plugin_txtd' ),
							'fg2'    => __( 'Text secondary', '__plugin_txtd' ),
						] as $token => $label ) :
							?>
							<div class="sm-lab-role" data-token="<?php echo esc_attr( $token ); ?>">
								<span class="sm-lab-role__chip" style="background: var(--sm-current-<?php echo esc_attr( $token ); ?>-color);"></span>
								<span><?php echo esc_html( $label ); ?></span>
								<code data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></code>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="sm-lab-flow__step">
					<span class="sm-lab-flow__index">3</span>
					<h2><?php esc_html_e( 'Usable UI', '__plugin_txtd' ); ?></h2>
					<p><?php esc_html_e( 'The same roles immediately skin real headings, actions, inputs, and nested blocks.', '__plugin_txtd' ); ?></p>
					<?php $this->render_runtime_component_sample( __( 'Active runtime sample', '__plugin_txtd' ) ); ?>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_context_matrix( QueryParams $params ): void {
		$variation = (string) $params->variation();
		?>
		<section class="sm-lab-zone sm-lab-context-matrix" data-sm-lab-proof="context-matrix">
			<div class="sm-lab-section-heading">
				<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Context resilience', '__plugin_txtd' ); ?></p>
				<h2><?php esc_html_e( 'The same component survives different palette contexts', '__plugin_txtd' ); ?></h2>
				<p><?php esc_html_e( 'No component-specific color choices are needed here. Each sample inherits the nearest Style Manager palette scope and keeps its structure intact.', '__plugin_txtd' ); ?></p>
			</div>
			<div class="sm-lab-context-grid">
				<div class="sm-lab-context-cell">
					<p class="sm-lab-context-cell__label"><?php esc_html_e( 'Active site context', '__plugin_txtd' ); ?></p>
					<?php $this->render_runtime_component_sample( __( 'Inherited from body', '__plugin_txtd' ) ); ?>
				</div>
				<div class="sm-lab-context-cell sm-palette-<?php echo esc_attr( ContextualPalette::ID ); ?> sm-variation-<?php echo esc_attr( $variation ); ?>" data-palette-variation="<?php echo esc_attr( $variation ); ?>">
					<p class="sm-lab-context-cell__label"><?php esc_html_e( 'Runtime contextual palette', '__plugin_txtd' ); ?></p>
					<?php $this->render_runtime_component_sample( __( 'Synthesized from source', '__plugin_txtd' ) ); ?>
				</div>
				<div class="sm-lab-context-cell sm-palette-1 sm-variation-1 sm-color-signal-2" data-palette="1" data-palette-variation="1" data-color-signal="2">
					<p class="sm-lab-context-cell__label"><?php esc_html_e( 'Nested block signal', '__plugin_txtd' ); ?></p>
					<?php $this->render_runtime_component_sample( __( 'Shifted by block signal', '__plugin_txtd' ) ); ?>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_semantic_contract(): void {
		?>
		<section class="sm-lab-zone sm-lab-contract" data-sm-lab-proof="semantic-contract">
			<div class="sm-lab-section-heading">
				<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Consumer contract', '__plugin_txtd' ); ?></p>
				<h2><?php esc_html_e( 'Expose the engine through stable semantic roles', '__plugin_txtd' ); ?></h2>
				<p><?php esc_html_e( 'The raw palette machinery stays inside Style Manager. Themes, blocks, Site Editor bridges, and AI design tools should read the semantic layer.', '__plugin_txtd' ); ?></p>
			</div>
			<div class="sm-lab-contract__map">
				<div class="sm-lab-contract__column">
					<h3><?php esc_html_e( 'Internal runtime roles', '__plugin_txtd' ); ?></h3>
					<ul>
						<li data-token="bg"><code>--sm-current-bg-color</code><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></li>
						<li data-token="accent"><code>--sm-current-accent-color</code><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></li>
						<li data-token="fg1"><code>--sm-current-fg1-color</code><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></li>
						<li data-token="fg2"><code>--sm-current-fg2-color</code><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></li>
					</ul>
				</div>
				<div class="sm-lab-contract__column">
					<h3><?php esc_html_e( 'Proposed semantic tier', '__plugin_txtd' ); ?></h3>
					<ul>
						<li><code>--sm-surface</code><span><?php esc_html_e( 'page and section surfaces', '__plugin_txtd' ); ?></span></li>
						<li><code>--sm-text-primary</code><span><?php esc_html_e( 'body and heading text', '__plugin_txtd' ); ?></span></li>
						<li><code>--sm-text-secondary</code><span><?php esc_html_e( 'captions and metadata', '__plugin_txtd' ); ?></span></li>
						<li><code>--sm-accent</code><span><?php esc_html_e( 'links and actions', '__plugin_txtd' ); ?></span></li>
					</ul>
				</div>
				<div class="sm-lab-contract__column">
					<h3><?php esc_html_e( 'WordPress consumers', '__plugin_txtd' ); ?></h3>
					<ul>
						<li><code>theme.json</code><span><?php esc_html_e( 'Site Editor presets', '__plugin_txtd' ); ?></span></li>
						<li><code>Anima --theme-*</code><span><?php esc_html_e( 'theme typography and color roles', '__plugin_txtd' ); ?></span></li>
						<li><code>Nova Blocks --nb-*</code><span><?php esc_html_e( 'block-level color-signal behavior', '__plugin_txtd' ); ?></span></li>
					</ul>
				</div>
			</div>
		</section>
		<?php
	}

	private function render_runtime_component_sample( string $context_label ): void {
		?>
		<article class="sm-lab-component-sample">
			<div class="sm-lab-component-sample__meta">
				<p class="sm-lab-component-sample__kicker"><?php esc_html_e( 'Runtime brief', '__plugin_txtd' ); ?></p>
				<span><?php echo esc_html( $context_label ); ?></span>
			</div>
			<h3><?php esc_html_e( 'Design System Dispatch', '__plugin_txtd' ); ?></h3>
			<p><?php esc_html_e( 'A palette change becomes stable surface, text, action, and nested-block decisions without this component owning a color recipe.', '__plugin_txtd' ); ?></p>
			<dl class="sm-lab-component-sample__details">
				<div>
					<dt><?php esc_html_e( 'Surface', '__plugin_txtd' ); ?></dt>
					<dd data-token="bg"><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Action', '__plugin_txtd' ); ?></dt>
					<dd data-token="accent"><span data-token-value><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></span></dd>
				</div>
			</dl>
			<div class="sm-lab-component-sample__actions">
				<a class="sm-lab-button sm-lab-button--primary" href="#sm-lab-primary"><?php esc_html_e( 'View details', '__plugin_txtd' ); ?></a>
				<a class="sm-lab-button sm-lab-button--outline" href="#sm-lab-secondary"><?php esc_html_e( 'Open pattern', '__plugin_txtd' ); ?></a>
			</div>
		</article>
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
		$variation = (string) $params->variation();
		?>
		<section class="sm-lab-zone sm-lab-contextual sm-lab-contextual-proof sm-palette-<?php echo esc_attr( ContextualPalette::ID ); ?> sm-variation-<?php echo esc_attr( $variation ); ?>" data-palette="<?php echo esc_attr( ContextualPalette::ID ); ?>" data-palette-variation="<?php echo esc_attr( $variation ); ?>" data-sm-lab-proof="contextual-proof">
			<div class="sm-lab-section-heading">
				<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Contextual palette proof', '__plugin_txtd' ); ?></p>
				<h2><?php esc_html_e( 'A local source color becomes a usable design-system context', '__plugin_txtd' ); ?></h2>
				<p><?php esc_html_e( 'This proof synthesizes a palette at runtime, applies it to a scoped section, reads its generated roles, and exposes where future safe-token aliases should rescue contrast.', '__plugin_txtd' ); ?></p>
			</div>
			<div class="sm-lab-contextual-proof__grid">
				<div class="sm-lab-contextual-proof__panel sm-lab-contextual-proof__source">
					<p class="sm-lab-contextual-proof__label"><?php esc_html_e( 'Source color', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-contextual-proof__source-value">
						<span class="sm-lab-contextual-proof__chip" data-sm-lab-contextual-swatch="source"></span>
						<code data-sm-lab-contextual-value="source"><?php echo esc_html( '' !== $params->contextual() ? $params->contextual() : 'off' ); ?></code>
					</div>
					<p><?php esc_html_e( 'Picked in the Lab controls, generated only for this preview, and never written into saved options.', '__plugin_txtd' ); ?></p>
				</div>
				<div class="sm-lab-contextual-proof__panel">
					<p class="sm-lab-contextual-proof__label"><?php esc_html_e( 'Generated runtime roles', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-contextual-roles">
						<?php
						foreach ( [
							'surface' => __( 'Surface', '__plugin_txtd' ),
							'accent'  => __( 'Accent', '__plugin_txtd' ),
							'text'    => __( 'Text', '__plugin_txtd' ),
						] as $role => $label ) :
							?>
							<div class="sm-lab-contextual-role">
								<span class="sm-lab-contextual-proof__chip" data-sm-lab-contextual-swatch="<?php echo esc_attr( $role ); ?>"></span>
								<span><?php echo esc_html( $label ); ?></span>
								<code data-sm-lab-contextual-value="<?php echo esc_attr( $role ); ?>"><?php esc_html_e( 'pending', '__plugin_txtd' ); ?></code>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="sm-lab-contextual-proof__panel">
					<p class="sm-lab-contextual-proof__label"><?php esc_html_e( 'Contrast readout', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-contrast-readout">
						<div>
							<span><?php esc_html_e( 'Accent on surface', '__plugin_txtd' ); ?></span>
							<strong data-sm-lab-contextual-value="accent-ratio"><?php esc_html_e( 'n/a', '__plugin_txtd' ); ?></strong>
							<em data-sm-lab-contextual-value="accent-status"><?php esc_html_e( 'Set source color', '__plugin_txtd' ); ?></em>
						</div>
						<div>
							<span><?php esc_html_e( 'Text on surface', '__plugin_txtd' ); ?></span>
							<strong data-sm-lab-contextual-value="text-ratio"><?php esc_html_e( 'n/a', '__plugin_txtd' ); ?></strong>
							<em data-sm-lab-contextual-value="text-status"><?php esc_html_e( 'Set source color', '__plugin_txtd' ); ?></em>
						</div>
					</div>
				</div>
				<div class="sm-lab-contextual-proof__panel sm-lab-contextual-proof__safe">
					<p class="sm-lab-contextual-proof__label"><?php esc_html_e( 'Safe-token direction', '__plugin_txtd' ); ?></p>
					<p><?php esc_html_e( 'The next useful contract is not another raw swatch. It is a safe semantic alias that can resolve to adjacent generated roles when a local palette misses its contrast target.', '__plugin_txtd' ); ?></p>
					<ul>
						<li><code>--sm-accent-safe</code></li>
						<li><code>--sm-text-safe</code></li>
					</ul>
				</div>
			</div>
			<div class="sm-lab-contextual-proof__component">
				<?php $this->render_runtime_component_sample( __( 'Scoped contextual palette', '__plugin_txtd' ) ); ?>
			</div>
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

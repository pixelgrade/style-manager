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
			$this->render_runtime_contract_explorer( $params );
			$this->render_contextual_palette_demo( $params );
			$this->render_context_resilience_matrix( $params );
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

	private function render_runtime_contract_explorer( QueryParams $params ): void {
		?>
		<section class="sm-lab-zone sm-lab-contract-explorer" data-sm-lab-proof="runtime-contract-explorer">
			<div class="sm-lab-contract-explorer__intro">
				<p class="sm-lab-zone__eyebrow"><?php esc_html_e( 'Runtime contract explorer', '__plugin_txtd' ); ?></p>
				<h1><?php esc_html_e( 'Build on a live design-system runtime, not static swatches', '__plugin_txtd' ); ?></h1>
				<p><?php esc_html_e( 'Style Manager resolves brand inputs, contextual palettes, Color Signal, and dark mode into contracts that themes, blocks, tools, and Pixelgrade products can consume without reimplementing palette logic.', '__plugin_txtd' ); ?></p>
				<p class="sm-lab-contract-explorer__chain"><?php esc_html_e( 'Inputs -> Runtime -> Context scopes -> Consumer APIs -> Reference implementations', '__plugin_txtd' ); ?></p>
			</div>
			<?php $this->render_runtime_visual_strip( $params ); ?>
			<div class="sm-lab-contract-explorer__workspace">
				<?php $this->render_contract_matrix(); ?>
				<?php $this->render_selected_contract_panels(); ?>
			</div>
		</section>
		<?php
	}

	private function render_runtime_visual_strip( QueryParams $params ): void {
		$parent_variation = $params->parent_variation();
		$signal_variation = min( $parent_variation + $params->signal(), 12 );
		$signal_shifted   = $signal_variation !== $parent_variation;
		$label_grade      = max( 1, $signal_variation - 5 );
		$shadow_grade     = min( 12, $signal_variation + 2 );
		?>
		<div class="sm-lab-runtime-strip sm-lab-resolution-path" data-sm-lab-visual-strip data-sm-lab-resolution-path>
			<div class="sm-lab-resolution-path__header">
				<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Runtime resolution path', '__plugin_txtd' ); ?></p>
				<p><?php esc_html_e( 'Context, signal, palette grades, and component parts are one runtime decision chain. The cards below show what Style Manager resolves before a theme, block, product, or builder API consumes color.', '__plugin_txtd' ); ?></p>
			</div>
			<div class="sm-lab-resolution-path__stages" aria-label="<?php esc_attr_e( 'Style Manager runtime resolution path', '__plugin_txtd' ); ?>">
				<article class="sm-lab-resolution-stage sm-lab-context-stack" data-sm-lab-resolution-stage="context" data-sm-lab-proof="context-stack">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '01', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Context stack', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Context scope', '__plugin_txtd' ); ?></h2>
					<p class="sm-lab-resolution-stage__lead"><?php esc_html_e( 'Context decides where the component lives.', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-context-stack__layers">
						<span><strong><?php esc_html_e( 'Page palette', '__plugin_txtd' ); ?></strong><small data-sm-lab-status-value="palette"><?php echo esc_html( $params->palette() ); ?></small></span>
						<span><strong><?php esc_html_e( 'Section context', '__plugin_txtd' ); ?></strong><small><?php esc_html_e( 'inherit or override', '__plugin_txtd' ); ?></small></span>
						<span><strong><?php esc_html_e( 'Block signal', '__plugin_txtd' ); ?></strong><small><?php esc_html_e( 'local intensity', '__plugin_txtd' ); ?></small></span>
						<span><strong><?php esc_html_e( 'Nested component', '__plugin_txtd' ); ?></strong><small><?php esc_html_e( 'consumer scope', '__plugin_txtd' ); ?></small></span>
					</div>
				</article>

				<article class="sm-lab-resolution-stage sm-lab-resolution-stage--signal" data-sm-lab-resolution-stage="signal">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '02', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Color Signal', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Signal intensity', '__plugin_txtd' ); ?></h2>
					<p class="sm-lab-resolution-stage__lead"><?php esc_html_e( 'Signal shifts inherited variation.', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-signal-bars" data-sm-lab-signal-bars data-sm-lab-proof="signal-levels">
						<?php
						foreach ( [
							0 => [ __( 'None', '__plugin_txtd' ), __( 'keep parent', '__plugin_txtd' ) ],
							1 => [ __( 'Low', '__plugin_txtd' ), __( '+1 grade', '__plugin_txtd' ) ],
							2 => [ __( 'Medium', '__plugin_txtd' ), __( '+2 grades', '__plugin_txtd' ) ],
							3 => [ __( 'High', '__plugin_txtd' ), __( '+3 grades', '__plugin_txtd' ) ],
						] as $signal => $labels ) :
							?>
							<span
								class="sm-lab-signal-bars__item"
								data-sm-lab-signal-option="<?php echo esc_attr( (string) $signal ); ?>"
								data-active="<?php echo esc_attr( $signal === $params->signal() ? 'true' : 'false' ); ?>"
							>
								<span class="sm-lab-signal-bars__icon" aria-hidden="true">
									<?php for ( $bar = 1; $bar <= 3; $bar++ ) : ?>
										<span class="<?php echo esc_attr( $bar <= $signal ? 'is-active' : '' ); ?>"></span>
									<?php endfor; ?>
								</span>
								<span class="sm-lab-signal-bars__copy">
									<strong><?php echo esc_html( $labels[0] ); ?></strong>
									<small><?php echo esc_html( $labels[1] ); ?></small>
								</span>
							</span>
						<?php endforeach; ?>
					</div>
					<p class="sm-lab-signal-result">
						<?php esc_html_e( 'Signal', '__plugin_txtd' ); ?>
						<strong data-sm-lab-signal-result="signal"><?php echo esc_html( (string) $params->signal() ); ?></strong>
						<?php esc_html_e( 'turns parent', '__plugin_txtd' ); ?>
						<strong data-sm-lab-signal-result="parent"><?php echo esc_html( (string) $params->parent_variation() ); ?></strong>
						<?php esc_html_e( 'into grade', '__plugin_txtd' ); ?>
						<strong data-sm-lab-signal-result="variation"><?php echo esc_html( (string) $signal_variation ); ?></strong>
					</p>
				</article>

				<article class="sm-lab-resolution-stage" data-sm-lab-resolution-stage="roles">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '03', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Role rail', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Resolved role rail', '__plugin_txtd' ); ?></h2>
					<div
						class="sm-lab-runtime-strip__rail"
						data-sm-lab-grade-rail
						data-sm-lab-proof="grade-rail"
						data-sm-lab-parent-grade="<?php echo esc_attr( (string) $parent_variation ); ?>"
						data-sm-lab-resolved-grade="<?php echo esc_attr( (string) $signal_variation ); ?>"
						data-sm-lab-signal-shifted="<?php echo esc_attr( $signal_shifted ? 'true' : 'false' ); ?>"
					>
						<?php for ( $grade = 1; $grade <= 12; $grade++ ) : ?>
							<span
								class="sm-lab-runtime-strip__grade"
								data-sm-lab-grade-swatch="<?php echo esc_attr( (string) $grade ); ?>"
								style="background: var(--sm-bg-color-<?php echo esc_attr( (string) $grade ); ?>);"
								aria-label="<?php echo esc_attr( sprintf( __( 'Grade %d', '__plugin_txtd' ), $grade ) ); ?>"
								data-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
								data-parent-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
								data-signal-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
								data-resolved-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
							></span>
						<?php endfor; ?>
					</div>
					<div class="sm-lab-role-markers">
						<span data-sm-lab-role-marker="palette"><small><?php esc_html_e( 'Palette', '__plugin_txtd' ); ?></small><strong data-sm-lab-status-value="palette"><?php echo esc_html( $params->palette() ); ?></strong></span>
						<span data-sm-lab-role-marker="parent"><small><?php esc_html_e( 'Parent grade', '__plugin_txtd' ); ?></small><strong data-sm-lab-signal-result="parent"><?php echo esc_html( (string) $parent_variation ); ?></strong></span>
						<span data-sm-lab-role-marker="signal"><small><?php esc_html_e( 'Signal grade', '__plugin_txtd' ); ?></small><strong data-sm-lab-signal-result="variation"><?php echo esc_html( (string) $signal_variation ); ?></strong></span>
						<span data-sm-lab-role-marker="source"><small><?php esc_html_e( 'Source anchor', '__plugin_txtd' ); ?></small><strong><?php esc_html_e( 'grade 7', '__plugin_txtd' ); ?></strong></span>
					</div>
				</article>

				<article class="sm-lab-resolution-stage sm-lab-resolution-stage--wide sm-lab-block-map" data-sm-lab-resolution-stage="mapping" data-sm-lab-proof="block-mapping">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '04', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Block mapping', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Component anatomy', '__plugin_txtd' ); ?></h2>
					<div class="sm-lab-block-map__sample">
						<div
							class="sm-lab-block-map__component sm-palette-<?php echo esc_attr( $params->palette() ); ?> sm-variation-<?php echo esc_attr( (string) $signal_variation ); ?> sm-color-signal-<?php echo esc_attr( (string) $params->signal() ); ?>"
							data-sm-lab-button-token-map
							data-sm-lab-token-layout="horizontal"
							data-sm-lab-signal-preview
							data-palette="<?php echo esc_attr( $params->palette() ); ?>"
							data-palette-variation="<?php echo esc_attr( (string) $signal_variation ); ?>"
							data-color-signal="<?php echo esc_attr( (string) $params->signal() ); ?>"
						>
							<div class="sm-lab-button-token-map__rail" aria-hidden="true">
								<?php for ( $grade = 1; $grade <= 12; $grade++ ) : ?>
									<span
										class="sm-lab-button-token-map__grade"
										data-sm-lab-grade-swatch="<?php echo esc_attr( (string) $grade ); ?>"
										style="background: var(--sm-bg-color-<?php echo esc_attr( (string) $grade ); ?>);"
										data-parent-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
										data-resolved-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
										data-signal-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-token-label-active="<?php echo esc_attr( $grade === $label_grade ? 'true' : 'false' ); ?>"
										data-token-button-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-token-shadow-active="<?php echo esc_attr( $grade === $shadow_grade ? 'true' : 'false' ); ?>"
									><?php echo esc_html( (string) $grade ); ?></span>
								<?php endfor; ?>
							</div>
							<div class="sm-lab-button-token-map__canvas" data-sm-lab-token-circuit>
								<div class="sm-lab-button-token-map__brief">
									<h3><?php esc_html_e( 'Button block', '__plugin_txtd' ); ?></h3>
									<p><?php esc_html_e( 'Each visible part consumes one resolved role from the same runtime grade rail.', '__plugin_txtd' ); ?></p>
								</div>
								<div class="sm-lab-button-token-map__diagram">
									<svg class="sm-lab-button-token-map__wires" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
										<path data-sm-lab-token-wire="label" d="M 14 0 V 42 C 14 53 24 55 36 55" />
										<path data-sm-lab-token-wire="button" d="M 54 0 V 60" />
										<path data-sm-lab-token-wire="shadow" d="M 78 0 V 32 C 78 46 88 48 88 62 C 88 78 70 80 62 80" />
									</svg>
									<div class="sm-lab-button-token-map__labels" data-sm-lab-token-lane="labels">
										<span class="sm-lab-button-token-map__pin sm-lab-button-token-map__pin--label" data-sm-lab-token-pin="label" data-sm-lab-component-callout="label" data-sm-lab-token-target="surface">
											<strong data-sm-lab-component-grade="label"><?php echo esc_html( (string) $label_grade ); ?></strong>
											<span>
												<em><?php esc_html_e( 'Text label', '__plugin_txtd' ); ?></em>
												<code>--sm-current-bg-color</code>
											</span>
										</span>
										<span class="sm-lab-button-token-map__pin sm-lab-button-token-map__pin--button" data-sm-lab-token-pin="button" data-sm-lab-component-callout="button">
											<strong data-sm-lab-component-grade="button"><?php echo esc_html( (string) $signal_variation ); ?></strong>
											<span>
												<em><?php esc_html_e( 'Button fill', '__plugin_txtd' ); ?></em>
												<code>--sm-current-accent-color</code>
											</span>
										</span>
										<span class="sm-lab-button-token-map__pin sm-lab-button-token-map__pin--shadow" data-sm-lab-token-pin="shadow" data-sm-lab-component-callout="shadow">
											<strong data-sm-lab-component-grade="shadow"><?php echo esc_html( (string) $shadow_grade ); ?></strong>
											<span>
												<em><?php esc_html_e( 'Shadow', '__plugin_txtd' ); ?></em>
												<code>--sm-current-fg2-color</code>
											</span>
										</span>
									</div>
									<div class="sm-lab-button-token-map__component-stage" data-sm-lab-token-lane="component">
										<span class="sm-lab-button-token-map__shadow-shelf" data-sm-lab-token-target="shadow" aria-hidden="true"></span>
										<button type="button" class="sm-lab-button-token-map__button" data-sm-lab-component-callout="action" data-sm-lab-token-target="action">
											<span><?php esc_html_e( 'Make a reservation', '__plugin_txtd' ); ?></span>
										</button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</article>
			</div>
		</div>
		<?php
	}

	private function render_contract_matrix(): void {
		$rows = ContractCatalog::all();
		?>
		<div class="sm-lab-contract-matrix" aria-label="<?php esc_attr_e( 'Style Manager runtime contracts', '__plugin_txtd' ); ?>">
			<?php foreach ( $rows as $row ) : ?>
				<button
					type="button"
					class="sm-lab-contract-row sm-lab-contract-row--<?php echo esc_attr( $row['maturity'] ); ?>"
					data-sm-lab-contract-row="<?php echo esc_attr( $row['id'] ); ?>"
					aria-selected="<?php echo esc_attr( 'active-palette' === $row['id'] ? 'true' : 'false' ); ?>"
				>
					<span class="sm-lab-contract-row__label"><?php echo esc_html( $row['label'] ); ?></span>
					<span class="sm-lab-contract-row__maturity"><?php echo esc_html( ucfirst( $row['maturity'] ) ); ?></span>
					<span class="sm-lab-contract-row__behavior"><?php echo esc_html( $row['visual_behavior'] ); ?></span>
					<span class="sm-lab-contract-row__proof"><?php echo esc_html( $row['pixelgrade_proof'] ); ?></span>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
	}

	private function render_selected_contract_panels(): void {
		foreach ( ContractCatalog::all() as $row ) :
			$is_default = 'active-palette' === $row['id'];
			?>
			<article
				class="sm-lab-contract-panel"
				data-sm-lab-contract-panel="<?php echo esc_attr( $row['id'] ); ?>"
				<?php echo $is_default ? '' : 'hidden'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			>
				<p class="sm-lab-contract-panel__maturity"><?php echo esc_html( ucfirst( $row['maturity'] ) ); ?></p>
				<h2><?php echo esc_html( $row['label'] ); ?></h2>
				<p><?php echo esc_html( $row['visual_behavior'] ); ?></p>
				<dl class="sm-lab-contract-panel__facts">
					<div>
						<dt><?php esc_html_e( 'Consume today', '__plugin_txtd' ); ?></dt>
						<dd><?php echo esc_html( $row['consume_today'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Proposed API', '__plugin_txtd' ); ?></dt>
						<dd><?php echo esc_html( $row['proposed_api'] ); ?></dd>
					</div>
					<div>
						<dt><?php esc_html_e( 'Pixelgrade proof', '__plugin_txtd' ); ?></dt>
						<dd><?php echo esc_html( $row['pixelgrade_proof'] ); ?></dd>
					</div>
				</dl>
				<pre><code><?php echo esc_html( $row['snippet'] ); ?></code></pre>
			</article>
			<?php
		endforeach;
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

	private function render_context_resilience_matrix( QueryParams $params ): void {
		$variation = (string) $params->variation();
		?>
		<section class="sm-lab-zone sm-lab-context-matrix" data-sm-lab-proof="context-resilience">
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

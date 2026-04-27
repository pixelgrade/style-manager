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
	private const COLOR_SIGNAL_PRESETS = [ 1, 3, 8, 11 ];

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
		$parent_variation = $params->variation();
		$signal_variation = $this->get_signal_variation( $parent_variation, $params->signal() );
		$signal_shifted   = $signal_variation !== $parent_variation;
		$label_grade      = $this->get_contrast_grade( $signal_variation );
		$border_grade     = min( 12, $signal_variation + 2 );
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
					<p class="sm-lab-resolution-stage__lead"><?php esc_html_e( 'Signal shifts inherited variation through preset anchors.', '__plugin_txtd' ); ?></p>
					<div class="sm-lab-signal-bars" data-sm-lab-signal-bars data-sm-lab-proof="signal-levels">
						<?php
						foreach ( [
							0 => [ __( 'None', '__plugin_txtd' ), __( 'keep parent', '__plugin_txtd' ) ],
							1 => [ __( 'Low', '__plugin_txtd' ), __( 'nearest preset anchor', '__plugin_txtd' ) ],
							2 => [ __( 'Medium', '__plugin_txtd' ), __( 'next preset anchor', '__plugin_txtd' ) ],
							3 => [ __( 'High', '__plugin_txtd' ), __( 'outer preset anchor', '__plugin_txtd' ) ],
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
								style="<?php echo esc_attr( $this->get_reference_grade_background_style( $params, $grade ) ); ?>"
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

				<article class="sm-lab-resolution-stage sm-lab-resolution-stage--wide sm-lab-signal-cascade-stage" data-sm-lab-resolution-stage="cascade" data-sm-lab-proof="signal-cascade">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '04', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Color Signal cascade', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Color Signal cascade', '__plugin_txtd' ); ?></h2>
					<p class="sm-lab-resolution-stage__lead"><?php esc_html_e( 'A block resolves against its parent surface, so the same signal can land on a light or dark grade.', '__plugin_txtd' ); ?></p>
					<div
						class="sm-lab-signal-cascade"
						data-sm-lab-signal-cascade
						data-sm-lab-cascade-palette="<?php echo esc_attr( $params->palette() ); ?>"
						data-sm-lab-cascade-active-signal="<?php echo esc_attr( (string) $params->signal() ); ?>"
					>
						<div class="sm-lab-signal-cascade__brief">
							<h3><?php esc_html_e( 'Page stack', '__plugin_txtd' ); ?></h3>
							<p><?php esc_html_e( 'Each layer declares a Color Signal. Style Manager resolves it against the parent surface. The resolved block then becomes the parent for anything nested inside.', '__plugin_txtd' ); ?></p>
						</div>
						<?php $this->render_cascade_assembled_preview( $params, $parent_variation ); ?>
						<?php $this->render_cascade_inspector( $params, $parent_variation ); ?>
					</div>
				</article>

				<article class="sm-lab-resolution-stage sm-lab-resolution-stage--wide sm-lab-block-map" data-sm-lab-resolution-stage="mapping" data-sm-lab-proof="block-mapping">
					<div class="sm-lab-resolution-stage__topline">
						<span><?php esc_html_e( '05', '__plugin_txtd' ); ?></span>
						<p class="sm-lab-runtime-strip__label"><?php esc_html_e( 'Block mapping', '__plugin_txtd' ); ?></p>
					</div>
					<h2><?php esc_html_e( 'Component anatomy', '__plugin_txtd' ); ?></h2>
					<div class="sm-lab-block-map__sample">
						<div
							class="sm-lab-block-map__component sm-palette-<?php echo esc_attr( $params->palette() ); ?> sm-variation-<?php echo esc_attr( (string) $parent_variation ); ?>"
							style="<?php echo esc_attr( $this->get_component_token_style( $label_grade, $signal_variation, $border_grade ) ); ?>"
							data-sm-lab-button-token-map
							data-sm-lab-signal-preview-surface="stable"
							data-sm-lab-token-layout="horizontal"
							data-sm-lab-token-source-grade-label="<?php echo esc_attr( (string) $label_grade ); ?>"
							data-sm-lab-token-source-grade-button="<?php echo esc_attr( (string) $signal_variation ); ?>"
							data-sm-lab-token-source-grade-border="<?php echo esc_attr( (string) $border_grade ); ?>"
							data-sm-lab-signal-preview
							data-palette="<?php echo esc_attr( $params->palette() ); ?>"
							data-palette-variation="<?php echo esc_attr( (string) $parent_variation ); ?>"
							data-color-signal="<?php echo esc_attr( (string) $params->signal() ); ?>"
						>
							<svg class="sm-lab-button-token-map__source-wires" data-sm-lab-token-source-wires aria-hidden="true" focusable="false">
								<path data-sm-lab-token-source-wire="label" />
								<path data-sm-lab-token-source-wire="button" />
								<path data-sm-lab-token-source-wire="border" />
							</svg>
							<div class="sm-lab-button-token-map__brief">
								<h3><?php esc_html_e( 'Button block', '__plugin_txtd' ); ?></h3>
								<p><?php esc_html_e( 'Each visible part consumes one resolved role from the same runtime grade rail.', '__plugin_txtd' ); ?></p>
							</div>
							<div class="sm-lab-button-token-map__rail" aria-hidden="true">
								<?php for ( $grade = 1; $grade <= 12; $grade++ ) : ?>
									<span
										class="sm-lab-button-token-map__grade"
										data-sm-lab-grade-swatch="<?php echo esc_attr( (string) $grade ); ?>"
										style="<?php echo esc_attr( $this->get_reference_grade_background_style( $params, $grade ) ); ?>"
										data-parent-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
										data-resolved-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-active="<?php echo esc_attr( $grade === $parent_variation ? 'true' : 'false' ); ?>"
										data-signal-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-token-label-active="<?php echo esc_attr( $grade === $label_grade ? 'true' : 'false' ); ?>"
										data-token-button-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
										data-token-border-active="<?php echo esc_attr( $grade === $border_grade ? 'true' : 'false' ); ?>"
									>
										<span class="sm-lab-button-token-map__grade-value"><?php echo esc_html( (string) $grade ); ?></span>
										<span class="sm-lab-button-token-map__source-chips" aria-hidden="true">
											<span
												class="sm-lab-button-token-map__source-chip"
												data-sm-lab-token-source-chip="label"
												data-active="<?php echo esc_attr( $grade === $label_grade ? 'true' : 'false' ); ?>"
												style="<?php echo esc_attr( $this->get_source_chip_style( $grade ) ); ?>"
											></span>
											<span
												class="sm-lab-button-token-map__source-chip"
												data-sm-lab-token-source-chip="button"
												data-active="<?php echo esc_attr( $grade === $signal_variation ? 'true' : 'false' ); ?>"
												style="<?php echo esc_attr( $this->get_source_chip_style( $grade ) ); ?>"
											></span>
											<span
												class="sm-lab-button-token-map__source-chip"
												data-sm-lab-token-source-chip="border"
												data-active="<?php echo esc_attr( $grade === $border_grade ? 'true' : 'false' ); ?>"
												style="<?php echo esc_attr( $this->get_source_chip_style( $grade ) ); ?>"
											></span>
										</span>
									</span>
								<?php endfor; ?>
							</div>
							<div class="sm-lab-button-token-map__canvas" data-sm-lab-token-circuit data-sm-lab-token-canvas-variation="1">
								<div class="sm-lab-button-token-map__diagram">
									<div class="sm-lab-button-token-map__component-stage" data-sm-lab-token-lane="component">
										<span class="sm-lab-button-token-map__button-shell">
											<button type="button" class="sm-lab-button-token-map__button" data-sm-lab-component-callout="action">
												<span class="sm-lab-button-token-map__button-label"><?php esc_html_e( 'Make a reservation', '__plugin_txtd' ); ?></span>
												<span class="sm-lab-button-token-map__target-port sm-lab-button-token-map__target-port--label" data-sm-lab-token-target="label" aria-hidden="true"></span>
												<span class="sm-lab-button-token-map__target-port sm-lab-button-token-map__target-port--fill" data-sm-lab-token-target="action" aria-hidden="true"></span>
											</button>
											<span class="sm-lab-button-token-map__target-port sm-lab-button-token-map__target-port--border" data-sm-lab-token-target="border" aria-hidden="true"></span>
										</span>
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

	private function get_reference_grade_background_style( QueryParams $params, int $grade ): string {
		return sprintf(
			'background: var(--sm-lab-reference-bg-color-%1$d, var(--sm-color-palette-%2$s-color-%1$d, var(--sm-bg-color-%1$d)));',
			$grade,
			$params->palette()
		);
	}

	private function get_component_token_style( int $label_grade, int $button_grade, int $border_grade ): string {
		return sprintf(
			'--sm-lab-component-label-color: var(--sm-lab-reference-bg-color-%1$d, var(--sm-bg-color-%1$d, var(--sm-current-bg-color))); --sm-lab-component-button-color: var(--sm-lab-reference-bg-color-%2$d, var(--sm-bg-color-%2$d, var(--sm-current-accent-color))); --sm-lab-component-border-color: var(--sm-lab-reference-bg-color-%3$d, var(--sm-bg-color-%3$d, var(--sm-current-fg2-color)));',
			$label_grade,
			$button_grade,
			$border_grade
		);
	}

	private function render_cascade_assembled_preview( QueryParams $params, int $root_variation ): void {
		$nodes = $this->get_signal_cascade_nodes( $params, $root_variation );
		$by_id = [];

		foreach ( $nodes as $node ) {
			$by_id[ $node['id'] ] = $node;
		}
		?>
		<div class="sm-lab-signal-cascade__assembled" data-sm-lab-cascade-view="assembled" aria-label="<?php esc_attr_e( 'Assembled page preview with resolved block surfaces', '__plugin_txtd' ); ?>">
			<div
				class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--page"
				data-sm-lab-cascade-preview-node="page"
				style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['page']['resolved_grade'], $by_id['page']['text_grade'] ) ); ?>"
			>
				<div
					class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--header"
					data-sm-lab-cascade-preview-node="header"
					data-sm-lab-cascade-bridge="header"
					style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['header']['resolved_grade'], $by_id['header']['text_grade'] ) ); ?>"
				></div>
				<div
					class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--content"
					data-sm-lab-cascade-preview-node="content"
					style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['content']['resolved_grade'], $by_id['content']['text_grade'] ) ); ?>"
				>
					<div
						class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--inner"
						data-sm-lab-cascade-preview-node="inner"
						style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['inner']['resolved_grade'], $by_id['inner']['text_grade'] ) ); ?>"
					>
						<div
							class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--second-inner"
							data-sm-lab-cascade-preview-node="second-inner"
							style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['second-inner']['resolved_grade'], $by_id['second-inner']['text_grade'] ) ); ?>"
						></div>
					</div>
				</div>
				<div
					class="sm-lab-cascade-preview-node sm-lab-cascade-preview-node--footer"
					data-sm-lab-cascade-preview-node="footer"
					style="<?php echo esc_attr( $this->get_signal_cascade_style( $by_id['footer']['resolved_grade'], $by_id['footer']['text_grade'] ) ); ?>"
				></div>
			</div>
		</div>
		<?php
	}

	private function render_cascade_inspector( QueryParams $params, int $root_variation ): void {
		$nodes = $this->get_signal_cascade_nodes( $params, $root_variation );
		$by_parent = [];

		foreach ( $nodes as $node ) {
			$parent = (string) $node['parent'];
			$by_parent[ $parent ][] = $node;
		}
		?>
		<div class="sm-lab-signal-cascade__inspector" data-sm-lab-cascade-view="inspector" aria-label="<?php esc_attr_e( 'Exploded Color Signal contract inspector', '__plugin_txtd' ); ?>">
			<?php
			foreach ( $by_parent[''] ?? [] as $node ) {
				$this->render_cascade_inspector_node( $node, $by_parent );
			}
			?>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array<string, array<int, array<string, mixed>>> $by_parent
	 */
	private function render_cascade_inspector_node( array $node, array $by_parent ): void {
		?>
		<section
			class="sm-lab-signal-cascade__node sm-lab-signal-cascade__node--<?php echo esc_attr( $node['id'] ); ?>"
			data-sm-lab-cascade-node="<?php echo esc_attr( $node['id'] ); ?>"
			data-sm-lab-cascade-signal="<?php echo esc_attr( (string) $node['signal'] ); ?>"
			<?php if ( '' !== $node['parent'] ) : ?>
				data-sm-lab-cascade-parent="<?php echo esc_attr( $node['parent'] ); ?>"
			<?php endif; ?>
			data-sm-lab-cascade-parent-grade="<?php echo esc_attr( (string) $node['parent_grade'] ); ?>"
			data-sm-lab-cascade-resolved-grade="<?php echo esc_attr( (string) $node['resolved_grade'] ); ?>"
			data-sm-lab-cascade-text-grade="<?php echo esc_attr( (string) $node['text_grade'] ); ?>"
			data-sm-lab-cascade-active="<?php echo esc_attr( $node['active'] ? 'true' : 'false' ); ?>"
			style="<?php echo esc_attr( $node['style'] ); ?>"
		>
			<span class="sm-lab-signal-cascade__signal">
				<span class="sm-lab-signal-bars__icon" aria-hidden="true">
					<?php for ( $bar = 1; $bar <= 3; $bar++ ) : ?>
						<span class="<?php echo esc_attr( $bar <= $node['signal'] ? 'is-active' : '' ); ?>"></span>
					<?php endfor; ?>
				</span>
				<span data-sm-lab-cascade-value="signal"><?php echo esc_html( $node['signal_label'] ); ?></span>
			</span>
			<strong><?php echo esc_html( $node['label'] ); ?></strong>
			<small><?php echo esc_html( $node['caption'] ); ?></small>
			<span class="sm-lab-signal-cascade__grades">
				<?php esc_html_e( 'from', '__plugin_txtd' ); ?>
				<b data-sm-lab-cascade-value="parent"><?php echo esc_html( (string) $node['parent_grade'] ); ?></b>
				<?php esc_html_e( 'to grade', '__plugin_txtd' ); ?>
				<b data-sm-lab-cascade-value="resolved"><?php echo esc_html( (string) $node['resolved_grade'] ); ?></b>
			</span>
			<?php
			foreach ( $by_parent[ $node['id'] ] ?? [] as $child ) {
				$this->render_cascade_inspector_node( $child, $by_parent );
			}
			?>
		</section>
		<?php
	}

	private function get_source_chip_style( int $grade ): string {
		return sprintf(
			'--sm-lab-token-source-color: var(--sm-lab-reference-bg-color-%1$d, var(--sm-bg-color-%1$d, var(--sm-current-bg-color)));',
			$grade
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_signal_cascade_nodes( QueryParams $params, int $root_variation ): array {
		$definitions = [
			[
				'id'      => 'page',
				'parent'  => '',
				'signal'  => 0,
				'label'   => __( 'Page container', '__plugin_txtd' ),
				'caption' => __( 'Base runtime surface', '__plugin_txtd' ),
			],
			[
				'id'      => 'header',
				'parent'  => 'page',
				'signal'  => 3,
				'label'   => __( 'Header block', '__plugin_txtd' ),
				'caption' => __( 'High attention from page', '__plugin_txtd' ),
			],
			[
				'id'      => 'content',
				'parent'  => 'page',
				'signal'  => 1,
				'label'   => __( 'Content block', '__plugin_txtd' ),
				'caption' => __( 'Low contrast from page', '__plugin_txtd' ),
			],
			[
				'id'      => 'inner',
				'parent'  => 'content',
				'signal'  => 2,
				'label'   => __( 'First inner block', '__plugin_txtd' ),
				'caption' => __( 'Medium from content', '__plugin_txtd' ),
			],
			[
				'id'      => 'second-inner',
				'parent'  => 'inner',
				'signal'  => 3,
				'label'   => __( 'Second inner block', '__plugin_txtd' ),
				'caption' => __( 'High relative to inner', '__plugin_txtd' ),
			],
			[
				'id'      => 'footer',
				'parent'  => 'page',
				'signal'  => 3,
				'label'   => __( 'Footer block', '__plugin_txtd' ),
				'caption' => __( 'High attention from page', '__plugin_txtd' ),
			],
		];
		$resolved_by_id = [];
		$nodes          = [];

		foreach ( $definitions as $definition ) {
			$parent_id      = (string) $definition['parent'];
			$parent_grade   = '' !== $parent_id && isset( $resolved_by_id[ $parent_id ] )
				? $resolved_by_id[ $parent_id ]
				: $root_variation;
			$signal         = (int) $definition['signal'];
			$resolved_grade = $this->get_signal_variation( $parent_grade, $signal );
			$text_grade     = $this->get_contrast_grade( $resolved_grade );

			$resolved_by_id[ (string) $definition['id'] ] = $resolved_grade;
			$nodes[]                                     = [
				'id'             => (string) $definition['id'],
				'parent'         => $parent_id,
				'signal'         => $signal,
				'signal_label'   => $this->get_signal_label( $signal ),
				'label'          => (string) $definition['label'],
				'caption'        => (string) $definition['caption'],
				'parent_grade'   => $parent_grade,
				'resolved_grade' => $resolved_grade,
				'text_grade'     => $text_grade,
				'active'         => $signal === $params->signal(),
				'style'          => $this->get_signal_cascade_style( $resolved_grade, $text_grade ),
			];
		}

		return $nodes;
	}

	private function get_signal_label( int $signal ): string {
		return match ( max( 0, min( 3, $signal ) ) ) {
			1 => __( 'Low', '__plugin_txtd' ),
			2 => __( 'Medium', '__plugin_txtd' ),
			3 => __( 'High', '__plugin_txtd' ),
			default => __( 'None', '__plugin_txtd' ),
		};
	}

	private function get_signal_cascade_style( int $surface_grade, int $text_grade ): string {
		$border_grade = min( 12, $surface_grade + 1 );

		return sprintf(
			'--sm-lab-cascade-surface-color: var(--sm-lab-reference-bg-color-%1$d, var(--sm-bg-color-%1$d, var(--sm-current-bg-color))); --sm-lab-cascade-text-color: var(--sm-lab-reference-bg-color-%2$d, var(--sm-bg-color-%2$d, var(--sm-current-fg1-color))); --sm-lab-cascade-border-color: var(--sm-lab-reference-bg-color-%3$d, var(--sm-bg-color-%3$d, var(--sm-current-fg2-color)));',
			$surface_grade,
			$text_grade,
			$border_grade
		);
	}

	private function get_signal_variation( int $parent_variation, int $signal ): int {
		$parent_variation = max( 1, min( 12, $parent_variation ) );
		$signal           = max( 0, min( 3, $signal ) );
		$options          = [];

		foreach ( self::COLOR_SIGNAL_PRESETS as $index => $variation ) {
			$options[] = [
				'index'     => $index,
				'variation' => $variation,
			];
		}

		usort(
			$options,
			static function ( array $first, array $second ) use ( $parent_variation ): int {
				$distance = abs( $parent_variation - $first['variation'] ) <=> abs( $parent_variation - $second['variation'] );

				return 0 !== $distance ? $distance : $first['index'] <=> $second['index'];
			}
		);

		$options[0]['variation'] = $parent_variation;

		return (int) $options[ min( $signal, count( $options ) - 1 ) ]['variation'];
	}

	private function get_contrast_grade( int $grade ): int {
		return $grade >= 5 ? 1 : 12;
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

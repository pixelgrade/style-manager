<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Lab\QueryParams;
use Pixelgrade\StyleManager\Lab\ShowcaseRenderer;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class ShowcaseRendererTest extends TestCase {
	public function test_render_outputs_unique_value_showcase_sections(): void {
		Functions\when( 'esc_attr' )->alias( static fn( $text ) => (string) $text );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );
		Functions\when( '__' )->alias( static fn( $text, ...$args ) => (string) $text );
		Functions\when( 'esc_attr_e' )->alias( static function ( $text, ...$args ): void {
			echo (string) $text;
		} );
		Functions\when( 'esc_html_e' )->alias( static function ( $text, ...$args ): void {
			echo (string) $text;
		} );
		Functions\when( 'esc_html__' )->alias( static fn( $text, ...$args ) => (string) $text );
		Functions\when( 'do_blocks' )->alias( static fn( string $markup ): string => $markup );

		$html = ( new ShowcaseRenderer() )->render( QueryParams::from_array( [
			'palette'         => 'brand',
			'variation'       => '4',
			'parentVariation' => '5',
			'signal'          => '2',
		] ) );

		$this->assertStringContainsString( 'data-sm-lab-proof="runtime-contract-explorer"', $html );
		$this->assertStringContainsString( 'Build on a live design-system runtime', $html );
		$this->assertStringContainsString( 'Inputs -> Runtime -> Context scopes -> Consumer APIs -> Reference implementations', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="active-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="contextual-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="color-signal"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-panel="active-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-visual-strip', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolution-path', $html );
		$this->assertStringContainsString( 'Runtime resolution path', $html );
		$this->assertStringContainsString( 'Context decides where the component lives', $html );
		$this->assertStringContainsString( 'Signal shifts inherited variation', $html );
		$this->assertStringContainsString( 'Resolved role rail', $html );
		$this->assertStringContainsString( 'Component anatomy', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolution-stage="context"', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolution-stage="signal"', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolution-stage="roles"', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolution-stage="mapping"', $html );
		$this->assertStringContainsString( 'sm-lab-resolution-stage--wide', $html );
		$this->assertStringContainsString( 'data-sm-lab-signal-result="variation"', $html );
		$this->assertStringContainsString( 'data-sm-lab-component-callout="action"', $html );
		$this->assertStringContainsString( 'data-sm-lab-role-marker="parent"', $html );
		$this->assertStringContainsString( 'data-sm-lab-role-marker="signal"', $html );
		$this->assertStringContainsString( 'data-sm-lab-parent-grade="4"', $html );
		$this->assertStringContainsString( 'data-sm-lab-resolved-grade="6"', $html );
		$this->assertStringContainsString( 'data-sm-lab-signal-shifted="true"', $html );
		$this->assertStringContainsString( 'data-parent-active="true"', $html );
		$this->assertStringContainsString( 'data-resolved-active="true"', $html );
		$this->assertStringContainsString( 'data-signal-active="true"', $html );
		$this->assertStringContainsString( 'data-sm-lab-signal-preview', $html );
		$this->assertStringContainsString( 'data-sm-lab-button-token-map', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-layout="horizontal"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-circuit', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-lane="labels"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-lane="component"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-wire="label"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-wire="button"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-wire="shadow"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-grade-label="1"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-grade-button="6"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-grade-shadow="8"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-wires', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-wire="label"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-wire="button"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-wire="shadow"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-chip="label"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-chip="button"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-source-chip="shadow"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-target="label"', $html );
		$this->assertStringContainsString( 'data-token-label-active="true"', $html );
		$this->assertStringContainsString( 'data-token-button-active="true"', $html );
		$this->assertStringContainsString( 'data-token-shadow-active="true"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-pin="label"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-pin="button"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-pin="shadow"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-target="surface"', $html );
		$this->assertStringContainsString( 'data-sm-lab-token-target="action"', $html );
		$this->assertStringContainsString( 'data-sm-lab-component-grade="button"', $html );
		$this->assertStringContainsString( 'data-sm-lab-component-grade="label"', $html );
		$this->assertStringContainsString( 'data-sm-lab-component-grade="shadow"', $html );
		$this->assertStringContainsString( 'Button block', $html );
		$this->assertStringContainsString( 'Text label', $html );
		$this->assertStringContainsString( 'Button fill', $html );
		$this->assertStringContainsString( 'Contrast grade', $html );
		$this->assertStringContainsString( 'Fill grade', $html );
		$this->assertStringContainsString( 'Depth grade', $html );
		$this->assertStringContainsString( 'Make a reservation', $html );
		$this->assertStringContainsString( '--sm-current-accent-color', $html );
		$this->assertStringContainsString( '--sm-lab-reference-bg-color-1', $html );
		$this->assertStringContainsString( '--sm-lab-component-button-color', $html );
		$this->assertStringContainsString( 'data-sm-lab-grade-rail', $html );
		$this->assertStringContainsString( 'data-sm-lab-signal-bars', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="grade-rail"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="signal-levels"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="block-mapping"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="context-stack"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="context-resilience"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="contextual-proof"', $html );
		$this->assertStringContainsString( 'data-sm-lab-proof="semantic-contract"', $html );
		$this->assertStringContainsString( 'Contextual palette proof', $html );
		$this->assertStringContainsString( 'Source color', $html );
		$this->assertStringContainsString( 'Generated runtime roles', $html );
		$this->assertStringContainsString( 'Contrast readout', $html );
		$this->assertStringContainsString( 'Safe-token direction', $html );
		$this->assertStringContainsString( 'Design System Dispatch', $html );
		$this->assertStringContainsString( 'Runtime brief', $html );
		$this->assertStringContainsString( 'View details', $html );
		$this->assertStringContainsString( 'data-palette="1" data-palette-variation="1" data-color-signal="2"', $html );
		$this->assertStringContainsString( 'sm-color-signal-2', $html );
		$this->assertStringContainsString( 'Proposed semantic tier', $html );
		$this->assertStringContainsString( 'theme.json', $html );
		$this->assertStringContainsString( 'Anima', $html );
		$this->assertStringContainsString( 'Nova Blocks', $html );

		$block_map_start = strpos( $html, 'data-sm-lab-proof="block-mapping"' );
		$block_map_end   = strpos( $html, '</article>', (int) $block_map_start );
		$block_map_html  = substr( $html, (int) $block_map_start, (int) $block_map_end - (int) $block_map_start );

		$this->assertStringNotContainsString( '<code>--sm-current-bg-color</code>', $block_map_html );
		$this->assertStringNotContainsString( '<code>--sm-current-accent-color</code>', $block_map_html );
		$this->assertStringNotContainsString( '<code>--sm-current-fg2-color</code>', $block_map_html );
		$this->assertStringNotContainsString( 'sm-lab-button-token-map__shadow-shelf', $block_map_html );
	}
}

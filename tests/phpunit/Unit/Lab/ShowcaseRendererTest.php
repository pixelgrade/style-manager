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
			'palette'   => 'brand',
			'variation' => '4',
		] ) );

		$this->assertStringContainsString( 'data-sm-lab-proof="runtime-contract-explorer"', $html );
		$this->assertStringContainsString( 'Build on a live design-system runtime', $html );
		$this->assertStringContainsString( 'Inputs -> Runtime -> Context scopes -> Consumer APIs -> Reference implementations', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="active-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="contextual-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-row="color-signal"', $html );
		$this->assertStringContainsString( 'data-sm-lab-contract-panel="active-palette"', $html );
		$this->assertStringContainsString( 'data-sm-lab-visual-strip', $html );
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
	}
}

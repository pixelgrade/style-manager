<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Lab\ContextualPalette;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class ContextualPaletteTest extends TestCase {
	public function test_palette_for_color_builds_runtime_palette(): void {
		$palette = ( new ContextualPalette() )->palette_for_color( '#ff5500' );

		$this->assertNotNull( $palette );
		$this->assertSame( ContextualPalette::ID, $palette->id );
		$this->assertSame( [ '#ff5500' ], $palette->source );
		$this->assertCount( 12, $palette->variations );
		$this->assertCount( 12, $palette->darkVariations );
		$this->assertSame( '#fff1eb', $palette->variations[0]->bg );
	}

	public function test_css_for_color_outputs_palette_css(): void {
		Functions\when( 'sanitize_hex_color' )->alias( static function ( string $color ): string {
			return strtolower( $color );
		} );
		Functions\when( 'get_option' )->alias( static function ( string $name, $default = false ) {
			if ( 'sm_site_color_variation' === $name ) {
				return 1;
			}

			return $default;
		} );

		$css = ( new ContextualPalette() )->css_for_color( '#ff5500' );

		$this->assertStringContainsString( '.sm-palette-contextual-lab {', $css );
		$this->assertStringContainsString( '--sm-bg-color-1: #fff1eb;', $css );
		$this->assertStringContainsString( '.is-dark .sm-palette-contextual-lab {', $css );
	}
}

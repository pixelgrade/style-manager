<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

class SavedPalettesTest extends TestCase {
	public function tearDown(): void {
		unset( $GLOBALS['wp_current_filter'] );

		parent::tearDown();
	}

	public function test_saved_palettes_uses_bundled_fallback_during_filter_fields_build(): void {
		$GLOBALS['wp_current_filter'] = [ 'style_manager/filter_fields' ];

		Functions\when( 'get_option' )->alias( static function( string $name, $default = false ) {
			if ( 'sm_advanced_palette_output' === $name ) {
				return $default;
			}

			return $default;
		} );

		try {
			$palettes = \style_manager_get_saved_palettes();
		} catch ( \Throwable $exception ) {
			$this->fail( 'Palette fallback must not re-enter Style Manager option details while fields config is being built: ' . $exception->getMessage() );
		}

		$this->assertGreaterThanOrEqual( 3, count( $palettes ) );
		$this->assertSame( 1, $palettes[0]->id );
		$this->assertNotEmpty( $palettes[0]->colors );
	}
}

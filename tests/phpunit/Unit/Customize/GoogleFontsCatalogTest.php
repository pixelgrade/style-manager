<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class GoogleFontsCatalogTest extends TestCase {
	public function test_catalog_includes_recent_font_palette_families(): void {
		$catalog_path = dirname( __DIR__, 4 ) . '/resources/google.fonts.php';
		$source       = file_get_contents( $catalog_path );

		$this->assertStringContainsString( "\\defined( 'ABSPATH' ) || exit;", $source );

		if ( ! \defined( 'ABSPATH' ) ) {
			\define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
		}

		$catalog = require $catalog_path;

		foreach ( [ 'Faculty Glyphic', 'Martian Mono', 'Zalando Sans' ] as $family ) {
			$this->assertArrayHasKey( $family, $catalog );
			$this->assertNotEmpty( $catalog[ $family ]['variants'] ?? [] );
		}
	}
}

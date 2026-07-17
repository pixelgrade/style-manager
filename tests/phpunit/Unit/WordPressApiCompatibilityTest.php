<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use PHPUnit\Framework\TestCase;

class WordPressApiCompatibilityTest extends TestCase {
	/**
	 * Keep release-scanned runtime code on the WordPress URL and text APIs.
	 *
	 * @dataProvider runtime_source_provider
	 */
	public function test_runtime_sources_use_wordpress_api_equivalents( string $relative_path ): void {
		$source = file_get_contents( dirname( __DIR__, 3 ) . '/' . $relative_path );

		$this->assertIsString( $source );
		$this->assertDoesNotMatchRegularExpression(
			'/(?<!wp_)\\bparse_url\\s*\\(/',
			$source,
			$relative_path . ' must use wp_parse_url().'
		);
		$this->assertDoesNotMatchRegularExpression(
			'/(?<!wp_)\\bstrip_tags\\s*\\(/',
			$source,
			$relative_path . ' must use wp_strip_all_tags().'
		);
	}

	public static function runtime_source_provider(): array {
		return [
			[ 'src/Customize/LocalFontStore.php' ],
			[ 'src/Provider/DesignSystemPreviewEndpoint.php' ],
		];
	}
}

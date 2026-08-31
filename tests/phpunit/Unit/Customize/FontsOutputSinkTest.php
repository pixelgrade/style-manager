<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Sink hardening (internal maintenance pass): `Fonts::outputFontsDynamicStyle()`
 * echoes assembled font CSS into a `<style>` element on the frontend (`wp_head`)
 * and, in the Customizer preview, into a per-field `<style>` element too. A
 * stored `font_family` value survives `sanitizeFontFamilyCSSValue()`'s quote
 * stripping untouched for `< > /` characters, so this mirrors the editor-path
 * hardening test: both echoes must run through the same
 * `sanitize_dynamic_style_css()` the frontend/editor palette sinks use.
 *
 * `getFontStyle()` is stubbed so the test exercises exactly the two echo
 * sites this hardening pass touches, without re-deriving the font-family CSS
 * assembly pipeline (already covered elsewhere).
 */
class FontsOutputSinkTest extends TestCase {

	public function test_bulk_frontend_style_neutralizes_a_hostile_font_family(): void {
		Functions\when( 'is_customize_preview' )->justReturn( false );
		$this->mock_wp_strip_all_tags();

		$fonts = $this->create_fonts_with_stubbed_style(
			'body,h1,h2 { font-family: "x} </style><script>alert(document.cookie)</script>{y", sans-serif; }'
		);

		ob_start();
		$fonts->outputFontsDynamicStyle();
		$output = ob_get_clean();

		// Exactly one legitimate closing </style> (the wrapper's own) survives.
		$this->assertSame( 1, preg_match_all( '/<\/style/i', $output ) );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $output );
	}

	public function test_customizer_preview_per_field_style_neutralizes_a_hostile_font_family(): void {
		Functions\when( 'is_customize_preview' )->justReturn( true );
		Functions\when( 'sanitize_html_class' )->alias( static fn( string $class ): string => $class );
		$this->mock_wp_strip_all_tags();

		$fonts = $this->create_fonts_with_stubbed_style(
			'body { font-family: "x} </style><script>alert(document.cookie)</script>{y", sans-serif; }'
		);

		ob_start();
		$fonts->outputFontsDynamicStyle();
		$output = ob_get_clean();

		// Two legitimate closing </style> tags survive: the per-field block and the bulk block.
		$this->assertSame( 2, preg_match_all( '/<\/style/i', $output ) );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $output );
	}

	/**
	 * Real WP core `wp_strip_all_tags()` semantics (script/style block removal
	 * + strip_tags() + trim()) rather than a plain strip_tags() alias, so the
	 * sanitizer is exercised the same way it runs in production.
	 */
	private function mock_wp_strip_all_tags(): void {
		Functions\when( 'wp_strip_all_tags' )->alias(
			static function ( $string, $remove_breaks = false ): string {
				$string = (string) preg_replace( '@<(script|style)[^>]*?>.*?</\1>@si', '', (string) $string );
				$string = strip_tags( $string );
				if ( $remove_breaks ) {
					$string = (string) preg_replace( '/[\r\n\t ]+/', ' ', $string );
				}

				return trim( $string );
			}
		);
	}

	private function create_fonts_with_stubbed_style( string $hostile_font_output ): Fonts {
		$options = $this->createMock( Options::class );
		$options
			->method( 'get_details_all' )
			->willReturn(
				[
					'sm_body_font' => [
						'type'     => 'font',
						'selector' => 'body',
					],
				]
			);
		$options->method( 'get' )->willReturn( [ 'font_family' => 'Hostile Family' ] );

		$fonts = $this->getMockBuilder( Fonts::class )
			->setConstructorArgs(
				[
					$options,
					$this->createMock( PluginSettings::class ),
					$this->createMock( LoggerInterface::class ),
				]
			)
			->onlyMethods( [ 'getFontStyle' ] )
			->getMock();

		$fonts->method( 'getFontStyle' )->willReturn( $hostile_font_output );

		return $fonts;
	}
}

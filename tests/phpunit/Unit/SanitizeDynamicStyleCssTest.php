<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;
use function Pixelgrade\StyleManager\sanitize_dynamic_style_css;

/**
 * Tests for the shared `sanitize_dynamic_style_css()` last-line-of-defense
 * sink hardening (internal maintenance pass): the same sanitizer runs
 * immediately before a dynamic-CSS string is echoed into a `<style>`
 * element, on the frontend (`FrontendOutput`) and in the block editor
 * (`EditWithBlocks`) alike, so a hostile stored value can't break out of
 * either sink.
 *
 * @since 2.5.3
 */
class SanitizeDynamicStyleCssTest extends TestCase {

	public function test_removes_a_tag_based_breakout_when_tag_stripping_runs(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );

		$hostile = '--sm-bg-color-1: red;} </style><script>alert(document.cookie)</script><style>.x{color:red';
		$result  = sanitize_dynamic_style_css( $hostile );

		$this->assertStringNotContainsStringIgnoringCase( '</style', $result );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $result );
	}

	/**
	 * Isolates the second, belt-and-braces layer: even if tag stripping is a
	 * no-op (simulated here by aliasing wp_strip_all_tags() to identity),
	 * the </style neutralization on its own still closes the breakout.
	 */
	public function test_neutralizes_style_breakout_sequences_independent_of_tag_stripping(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ) => $text );

		foreach (
			[
				'</style><script>alert(1)</script>',
				'</STYLE><script>alert(1)</script>',
				'</  style  ><script>alert(1)</script>',
				"</\tstyle>",
			] as $hostile
		) {
			$result = sanitize_dynamic_style_css( $hostile );
			$this->assertStringNotContainsStringIgnoringCase( '</style', $result, "Failed for input: {$hostile}" );
		}
	}

	/**
	 * A non-tag CSS-breakout value (rule-closing injection, no HTML angle
	 * brackets involved) is not this sink's job to police -- that is the
	 * write-time color-grammar's responsibility (see the W5 hardening pass
	 * on validate_renderable()). The sink only guarantees it can't escalate
	 * into an HTML/script context, so the value must survive unmangled.
	 */
	public function test_leaves_non_tag_css_injection_payloads_unmangled(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );

		$hostile = 'x } html{display:none} .y{color:red';
		$result  = sanitize_dynamic_style_css( $hostile );

		$this->assertSame( $hostile, $result );
		$this->assertStringNotContainsString( '<', $result );
		$this->assertStringNotContainsString( '>', $result );
	}

	public function test_preserves_legitimate_css_syntax_byte_identical(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );

		$legit = ':root { --sm-bg-color-1: var(--sm-color-palette-1-bg-color-1); font-family: "Helvetica Neue", sans-serif; content: "a > b"; }';

		$this->assertSame( $legit, sanitize_dynamic_style_css( $legit ) );
	}

	public function test_strips_control_characters_but_keeps_tabs_and_newlines(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );

		$input = ":root {\n\t--sm-bg-color-1: red\x00\x1F;\n}";

		$result = sanitize_dynamic_style_css( $input );

		$this->assertSame( ":root {\n\t--sm-bg-color-1: red;\n}", $result );
	}

	/**
	 * P2-style byte-identity check: sanitizing a real, machine-generated
	 * palette's CSS (built from the shipped default fixture that also backs
	 * the W5 parity suite) must not change a single byte -- the sanitizer
	 * must be a strict no-op on well-formed generator output.
	 */
	public function test_real_generated_palette_css_passes_through_byte_identical(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );
		Functions\when( 'get_option' )->alias(
			static fn( string $name, $default = false ) => 'sm_site_color_variation' === $name ? 1 : $default
		);

		$palettes = \style_manager_get_bundled_fallback_palettes();
		$this->assertNotEmpty( $palettes, 'Fixture precondition: the shipped default palette JSON must decode to a non-empty array.' );

		$css = \style_manager_palettes_output( $palettes );
		$this->assertNotSame( '', $css, 'Fixture precondition: the shipped default palette must render non-empty CSS.' );

		$this->assertSame( $css, sanitize_dynamic_style_css( $css ) );
	}
}

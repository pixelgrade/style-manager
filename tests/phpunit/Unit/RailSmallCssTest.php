<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * The Small-only rail (nova-blocks#655, H-S6): `sm_rail_small` sets the Small
 * rail while the Rail Scale (Base + Pitch) is untouched, and leaves Medium and
 * Large on the consumer's defaults (Nova: 330 / 400). It sits exactly where the
 * saved Content Inset used to sit before the Small rail was decoupled from it.
 */
class RailSmallCssTest extends TestCase {
	private function with_options( array $options ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);
	}

	private function emitted( $base ): string {
		$css = '';
		foreach ( [ '--sm-rail-small', '--sm-rail-medium', '--sm-rail-large' ] as $property ) {
			$css .= style_manager_rail_scale_css_cb( $base, ':root', $property, '' );
		}

		return $css;
	}

	public function test_untouched_emits_nothing(): void {
		$this->with_options( [] );

		$this->assertNull( style_manager_rail_widths( '', '', '' ) );
		$this->assertSame( '', $this->emitted( '' ) );
	}

	public function test_small_alone_emits_only_the_small_token(): void {
		$this->assertSame( [ 'small' => 180, 'medium' => null, 'large' => null ], style_manager_rail_widths( '', '', '180' ) );

		$this->with_options( [ 'sm_rail_small' => '180' ] );
		$this->assertSame( ':root { --sm-rail-small: 180; }' . PHP_EOL, $this->emitted( '' ) );
	}

	public function test_small_keeps_a_fractional_inset_value(): void {
		$this->assertSame( 187.5, style_manager_rail_widths( '', '', 187.5 )['small'] );
	}

	public function test_invalid_small_values_are_ignored(): void {
		foreach ( [ '', null, false, 0, '0', -10, 'wide' ] as $raw ) {
			$this->assertNull( style_manager_rail_widths( '', '', $raw ), var_export( $raw, true ) );
		}
	}

	public function test_a_touched_rail_scale_owns_all_three_sizes(): void {
		// v1 compatibility (base only) and v2 (pitch) ignore the Small-only value.
		$this->assertSame( style_manager_rail_widths( 288, '' ), style_manager_rail_widths( 288, '', 180 ) );
		$this->assertSame( style_manager_rail_widths( 250, 16 ), style_manager_rail_widths( 250, 16, 180 ) );
		$this->assertSame( style_manager_rail_widths( '', 0 ), style_manager_rail_widths( '', 0, 180 ) );

		$this->with_options( [ 'sm_rail_small' => '180', 'sm_rail_pitch' => '' ] );
		$this->assertSame(
			':root { --sm-rail-small: 288; }' . PHP_EOL . ':root { --sm-rail-medium: 330; }' . PHP_EOL . ':root { --sm-rail-large: 400; }' . PHP_EOL,
			$this->emitted( 288 )
		);
	}

	public function test_a_legacy_inset_still_sets_the_small_rail(): void {
		// style-manager#220: an inset saved before 2.7.0 or written by a starter
		// import keeps its 2.6 role — it is the Small rail, Medium/Large stay default.
		$this->with_options( [ 'sm_content_inset' => '180' ] );
		$this->assertSame( ':root { --sm-rail-small: 180; }' . PHP_EOL, $this->emitted( '' ) );

		$this->with_options( [ 'sm_content_inset' => '187.5' ] );
		$this->assertSame( ':root { --sm-rail-small: 187.5; }' . PHP_EOL, $this->emitted( '' ) );
	}

	public function test_an_explicit_inset_no_longer_sets_the_small_rail(): void {
		$this->with_options( [ 'sm_content_inset' => '180', 'style_manager_content_inset_explicit' => '180' ] );
		$this->assertSame( '', $this->emitted( '' ) );
	}

	public function test_saved_rail_settings_win_over_a_legacy_inset(): void {
		$this->with_options( [ 'sm_content_inset' => '180', 'sm_rail_small' => '200' ] );
		$this->assertSame( ':root { --sm-rail-small: 200; }' . PHP_EOL, $this->emitted( '' ) );

		$this->with_options( [ 'sm_content_inset' => '180', 'sm_rail_pitch' => '' ] );
		$this->assertSame(
			':root { --sm-rail-small: 288; }' . PHP_EOL . ':root { --sm-rail-medium: 330; }' . PHP_EOL . ':root { --sm-rail-large: 400; }' . PHP_EOL,
			$this->emitted( 288 )
		);
	}

	public function test_a_customizer_preview_keeps_the_small_rail_a_publish_would_pin(): void {
		// Stored legacy 180, pending 150: publishing pins 180, so the preview shows 180.
		$GLOBALS['wp_customize'] = new class() {
			public array $pending = [ 'sm_content_inset' => 150 ];

			public function is_preview(): bool {
				return true;
			}

			public function unsanitized_post_values(): array {
				return $this->pending;
			}
		};
		$GLOBALS['wpdb'] = new class() {
			public string $options = 'wp_options';

			public function prepare( $query, ...$args ) {
				return str_replace( '%s', "'" . $args[0] . "'", $query );
			}

			public function get_var( $query ) {
				return false !== strpos( $query, "'sm_content_inset'" ) ? '180' : null;
			}
		};
		Functions\when( 'maybe_unserialize' )->returnArg( 1 );

		// The preview filter makes get_option() answer the pending value.
		$this->with_options( [ 'sm_content_inset' => 150 ] );
		$this->assertSame( ':root { --sm-rail-small: 180; }' . PHP_EOL, $this->emitted( '' ) );

		// A pending reset goes back to the default Small rail.
		$GLOBALS['wp_customize']->pending = [ 'sm_content_inset' => '' ];
		$this->with_options( [ 'sm_content_inset' => '' ] );
		$this->assertSame( '', $this->emitted( '' ) );

		unset( $GLOBALS['wp_customize'], $GLOBALS['wpdb'] );
	}

	public function test_the_small_setting_callback_is_inert(): void {
		$this->assertSame( '', style_manager_rail_small_css_cb( 180, ':root', '--sm-rail-small-sync' ) );
		$this->assertSame( '', sm_rail_small_css_cb( 180, ':root', '--sm-rail-small-sync' ) );
	}
}

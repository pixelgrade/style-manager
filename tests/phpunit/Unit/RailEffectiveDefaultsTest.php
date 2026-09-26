<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * pixelgrade/style-manager#215 (follow-up): while sm_rail_small, sm_rail_scale
 * ("Rail Base (Small)") and sm_rail_pitch are unset, their range controls must
 * show the width the site ACTUALLY renders — not an arbitrary (min+max)/2
 * midpoint (260 / 23), which advertises a rail width nothing applies. The
 * effective-default functions derive that number from the exact same contract
 * as style_manager_rail_widths() / style_manager_rail_scale_css_cb(), so the
 * displayed value can never drift from the frontend.
 */
class RailEffectiveDefaultsTest extends TestCase {
	private function with_options( array $options ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);
	}

	// --- style_manager_effective_rail_small() -------------------------------

	public function test_nothing_saved_resolves_to_the_content_inset_default(): void {
		$this->with_options( [] );

		// 230: Style Manager's registered Content Inset default (nova-blocks#655
		// decision 2) — the Small rail's own budget once decoupled from the
		// (possibly different) saved inset value. Matches the JS twin
		// (contract-geometry.js RAIL_SMALL_DEFAULT) used by the Layout board.
		$this->assertSame( 230, style_manager_effective_rail_small() );
	}

	public function test_a_saved_small_only_value_is_the_effective_small(): void {
		$this->with_options( [ 'sm_rail_small' => '175' ] );

		$this->assertSame( 175, style_manager_effective_rail_small() );
	}

	public function test_a_migrated_fractional_small_only_value_rounds_for_display(): void {
		// style_manager_rail_widths() keeps this exact for CSS output (187.5);
		// the range control's step is 1, so display rounds to the nearest step.
		$this->with_options( [ 'sm_rail_small' => '187.5' ] );

		$this->assertSame( 188, style_manager_effective_rail_small() );
	}

	public function test_a_touched_base_under_v1_compat_is_its_own_effective_small(): void {
		// Base set, Pitch unset -> v1-compat: Small == Base exactly.
		$this->with_options( [ 'sm_rail_scale' => '342', 'sm_rail_pitch' => '' ] );

		$this->assertSame( 342, style_manager_effective_rail_small() );
	}

	public function test_a_touched_pitch_with_no_base_uses_the_v2_default_base(): void {
		// Pitch set, Base unset -> v2 math defaults the base to 300 internally
		// (style_manager_rail_widths()); the soft ceiling barely touches it.
		$this->with_options( [ 'sm_rail_scale' => '', 'sm_rail_pitch' => '20' ] );

		$this->assertSame( 300, style_manager_effective_rail_small() );
	}

	public function test_a_touched_pitch_ignores_a_saved_small_only_value(): void {
		// Once the Rail Scale is touched (Pitch here), it owns all three
		// sizes and the Small-only value no longer applies (style_manager_rail_widths()).
		$this->with_options( [ 'sm_rail_scale' => '', 'sm_rail_pitch' => '20', 'sm_rail_small' => '175' ] );

		$this->assertSame( 300, style_manager_effective_rail_small() );
	}

	public function test_an_invalid_small_only_value_falls_back_to_the_default(): void {
		foreach ( [ '', null, false, 0, '0', -10, 'wide' ] as $raw ) {
			$this->with_options( [ 'sm_rail_small' => $raw ] );
			$this->assertSame( 230, style_manager_effective_rail_small(), var_export( $raw, true ) );
		}
	}

	// --- style_manager_effective_rail_pitch() -------------------------------

	public function test_no_pitch_reproduces_the_default_medium_and_large(): void {
		// The v2 model requires a geometric progression: Medium/Small ==
		// Large/Medium, i.e. Medium^2 == Small * Large. The shipped defaults
		// (Small 288, Medium 330, Large 400 — the v1-compat fixed ratios) are
		// an ARITHMETIC progression instead, so no base/pitch combination in
		// the v2 model can ever reproduce them. This is a standing fact about
		// the constants, guarded here so a future change to the defaults is
		// forced to revisit style_manager_effective_rail_pitch()'s contract.
		$this->assertNotSame( 330 * 330, 288 * 400 );
	}

	public function test_effective_pitch_is_the_documented_neutral_flat(): void {
		// No exact value exists (see the previous test) and there is no
		// "v1-compat equivalent" either (v1-compat is DEFINED by Pitch being
		// unset). 0 (Flat) is the deliberate, documented placeholder: it is
		// the only value that does not fabricate a specific curvature.
		$this->with_options( [] );
		$this->assertSame( 0, style_manager_effective_rail_pitch() );
	}

	public function test_effective_pitch_stays_flat_regardless_of_other_rail_settings(): void {
		foreach ( [
			[],
			[ 'sm_rail_small' => '175' ],
			[ 'sm_rail_scale' => '342' ],
			[ 'sm_content_inset' => '180' ],
		] as $options ) {
			$this->with_options( $options );
			$this->assertSame( 0, style_manager_effective_rail_pitch(), var_export( $options, true ) );
		}
	}
}

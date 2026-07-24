<?php
/**
 * Standalone contract for the Rail Scale (Base + Pitch) token math and its
 * migration behaviour.
 *
 * Run with: php tests/rail-scale-contract.php
 *
 * Covers:
 *  - untouched (both settings unset) -> no widths (byte-identical rendering)
 *  - v1 compatibility (base set, pitch unset) -> fixed 330/288, 400/288 ratios
 *  - v2 math (pitch set) incl. the soft-ceiling sanity points
 *  - S <= M <= L for every input (inversion impossible)
 */

namespace {

	$GLOBALS['sm_rail_contract_options'] = array();

	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['sm_rail_contract_options'] )
			? $GLOBALS['sm_rail_contract_options'][ $name ]
			: $default;
	}

	require __DIR__ . '/../src/sm-functions.php';

	$failures = 0;

	$assert_same = function ( $expected, $actual, $message ) use ( &$failures ) {
		if ( $expected !== $actual ) {
			$failures++;
			fwrite( STDERR, "FAIL: $message\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
		}
	};
	$assert_true = function ( $cond, $message ) use ( &$failures ) {
		if ( ! $cond ) {
			$failures++;
			fwrite( STDERR, "FAIL: $message\n" );
		}
	};
	$near = function ( $a, $b, $tol = 1 ) {
		return abs( $a - $b ) <= $tol;
	};

	// --- untouched: both unset -> null (legacy-until-touched) ---
	$assert_same( null, style_manager_rail_widths( '', '' ), 'both unset emits nothing' );
	$assert_same( null, style_manager_rail_widths( null, null ), 'both null emits nothing' );
	$assert_same( null, style_manager_rail_widths( 0, '' ), 'zero base + unset pitch emits nothing' );

	// The css callback returns an empty string when unset.
	$assert_same( '', style_manager_rail_scale_css_cb( '', ':root', '--sm-rail-medium', '' ), 'callback empty when unset' );

	// --- v1 compatibility: base set, pitch UNSET -> fixed ratios ---
	$v1 = style_manager_rail_widths( 288, '' );
	$assert_same( array( 'small' => 288, 'medium' => 330, 'large' => 400 ), $v1, 'v1 anchor 288 -> 288/330/400' );
	$v1b = style_manager_rail_widths( 240, '' );
	$assert_same( array( 'small' => 240, 'medium' => 275, 'large' => 333 ), $v1b, 'v1 base 240 -> 240/275/333' );

	// The css callback reflects v1 compat by reading the (unset) pitch option.
	$GLOBALS['sm_rail_contract_options'] = array(); // pitch unset
	$assert_same(
		':root { --sm-rail-medium: 330; }' . PHP_EOL,
		style_manager_rail_scale_css_cb( 288, ':root', '--sm-rail-medium', '' ),
		'callback v1 compat medium 330 for base 288, pitch unset'
	);

	// --- v2: pitch set owns emission ---
	// Flat (pitch 0): multiplier x1 -> S = M = L = base (soft barely bites at 300).
	$flat = style_manager_rail_widths( 300, 0 );
	$assert_true( $near( $flat['small'], 300 ) && $flat['small'] === $flat['medium'] && $flat['medium'] === $flat['large'], 'flat pitch 0 -> equal S/M/L' );

	// Sanity: base 100, pitch 45 -> ~100 / 173 / 300.
	$s1 = style_manager_rail_widths( 100, 45 );
	$assert_true( $near( $s1['small'], 100 ), 'v2 100/45 small ~100 (got ' . $s1['small'] . ')' );
	$assert_true( $near( $s1['medium'], 173, 2 ), 'v2 100/45 medium ~173 (got ' . $s1['medium'] . ')' );
	$assert_true( $near( $s1['large'], 300, 2 ), 'v2 100/45 large ~300 (got ' . $s1['large'] . ')' );

	// Soft ceiling: base 420, pitch 45 -> L <= 600 (eased, not 1260).
	$s2 = style_manager_rail_widths( 420, 45 );
	$assert_true( $s2['large'] <= 600, 'v2 420/45 large <= 600 (got ' . $s2['large'] . ')' );
	$assert_true( $s2['large'] >= 580, 'v2 420/45 large near the 600 ceiling (got ' . $s2['large'] . ')' );

	// Pitch-only touch (base unset) -> default base 300.
	$s3 = style_manager_rail_widths( '', 22 );
	$assert_true( $near( $s3['small'], 300 ), 'v2 pitch-only defaults base to 300 (got ' . $s3['small'] . ')' );

	// --- S <= M <= L across a full sweep (both models) ---
	$ok_order = true;
	for ( $base = 100; $base <= 420; $base += 20 ) {
		$w = style_manager_rail_widths( $base, '' ); // v1
		if ( $w['small'] > $w['medium'] || $w['medium'] > $w['large'] ) {
			$ok_order = false;
		}
		for ( $pitch = 0; $pitch <= 45; $pitch += 3 ) {
			$w2 = style_manager_rail_widths( $base, $pitch ); // v2
			if ( $w2['small'] > $w2['medium'] || $w2['medium'] > $w2['large'] ) {
				$ok_order = false;
			}
		}
	}
	$assert_true( $ok_order, 'S <= M <= L holds across the full base x pitch sweep' );

	if ( $failures > 0 ) {
		fwrite( STDERR, "\n$failures rail-scale contract assertion(s) failed\n" );
		exit( 1 );
	}

	echo "Style Manager rail-scale contract OK\n";
}

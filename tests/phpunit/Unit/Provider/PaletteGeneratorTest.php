<?php
/**
 * Unit coverage for the headless palette generator's PHP half.
 *
 * The math is proven by PaletteGeneratorParityTest against browser-produced fixtures. What
 * is pinned here is everything *around* it — option resolution (the whole risk surface per
 * the W5 spike), runtime discovery, output validation, and the §5 canonicalizer.
 *
 * @package Style Manager
 */

declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			private string $code;
			private string $message;
			private $data;

			public function __construct( string $code = '', string $message = '', $data = null ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			public function get_error_code(): string {
				return $this->code;
			}

			public function get_error_message(): string {
				return $this->message;
			}

			public function get_error_data() {
				return $this->data;
			}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PaletteGenerator;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class PaletteGeneratorTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, int $flags = 0 ) => json_encode( $value, $flags )
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
	}

	/*
	 * ------------------------------------------------------------------
	 * Option resolution — the spike's only real divergence surface.
	 * ------------------------------------------------------------------
	 */

	public function test_the_option_inventory_mirrors_the_javascript_one(): void {
		// A PHP mirror of getColorOptionsIDs() in src/_js/shared/color-generator/colors.js.
		// If the two drift, the generated output silently stops matching the Customizer.
		$this->assertSame(
			[
				'sm_color_grades_number',
				'sm_potential_color_contrast',
				'sm_color_grade_balancer',
				'sm_site_color_variation',
				'sm_elements_color_contrast',
				'sm_color_promotion_brand',
				'sm_color_promotion_white',
				'sm_color_promotion_black',
			],
			PaletteGenerator::option_ids()
		);
	}

	public function test_options_resolve_through_the_three_store_resolver(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get' )->willReturnCallback(
			static function ( string $id ) {
				return [
					'sm_color_grades_number'      => 12,
					'sm_potential_color_contrast' => 1,
					'sm_color_grade_balancer'     => 0,
					'sm_site_color_variation'     => 1,
					'sm_elements_color_contrast'  => 'normal',
					'sm_color_promotion_white'    => true,
					'sm_color_promotion_black'    => true,
				][ $id ] ?? null;
			}
		);

		$resolved = ( new PaletteGenerator( $options ) )->resolve_options();

		$this->assertSame( 12, $resolved['sm_color_grades_number'] );
		$this->assertSame( 'normal', $resolved['sm_elements_color_contrast'] );
		$this->assertTrue( $resolved['sm_color_promotion_black'] );
	}

	/**
	 * The W5 spike's headline finding: `sm_color_promotion_brand` is declared without a
	 * `default`, so it resolves to null. The JS fallback for a missing config is the literal
	 * `'#000'` — a *truthy* string that switches brand promotion on — which is why the naive
	 * end-to-end run reproduced 0 of 9 corpus fixtures with channel deltas up to 119/255.
	 * The registered Customizer setting holds `''`, and `''` is falsy.
	 */
	public function test_the_undeclared_brand_promotion_default_resolves_to_an_empty_string(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get' )->willReturn( null );

		$resolved = ( new PaletteGenerator( $options ) )->resolve_options();

		$this->assertSame( '', $resolved[ PaletteGenerator::BRAND_PROMOTION_SETTING_ID ] );
		$this->assertFalse( (bool) $resolved[ PaletteGenerator::BRAND_PROMOTION_SETTING_ID ] );
	}

	public function test_overrides_win_over_the_resolved_value(): void {
		$options = $this->createMock( Options::class );
		$options->method( 'get' )->willReturn( 1 );

		$resolved = ( new PaletteGenerator( $options ) )
			->resolve_options( [ PaletteGenerator::VARIATION_SETTING_ID => 8 ] );

		$this->assertSame( 8, $resolved[ PaletteGenerator::VARIATION_SETTING_ID ] );
	}

	/*
	 * ------------------------------------------------------------------
	 * §3.11 — declared runtime, graceful absence.
	 * ------------------------------------------------------------------
	 */

	public function test_a_missing_artifact_is_reported_not_fatal(): void {
		$generator = new PaletteGenerator( $this->createMock( Options::class ), '/nowhere/at/all' );

		$this->assertFalse( $generator->is_available() );
		$this->assertStringContainsString(
			PaletteGenerator::ARTIFACT_RELATIVE_PATH,
			implode( ' ', $generator->looked_for() )
		);

		$result = $generator->generate( '[]', [] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'style_manager_generator_unavailable', $result->get_error_code() );
	}

	public function test_the_shipped_artifact_path_is_the_one_the_contract_pins(): void {
		$generator = new PaletteGenerator( $this->createMock( Options::class ), '/plugins/style-manager' );

		$this->assertSame( '/plugins/style-manager/dist/node/palette-generator.js', $generator->artifact_path() );
	}

	/*
	 * ------------------------------------------------------------------
	 * Source validation — nothing malformed reaches the write path.
	 * ------------------------------------------------------------------
	 */

	public function test_a_valid_source_parses(): void {
		$parsed = PaletteGenerator::parse_source( '[{"sources":[{"value":"#722F37","label":"MOLD Burgundy"}]}]' );

		$this->assertIsArray( $parsed );
		$this->assertCount( 1, $parsed );
	}

	/**
	 * @dataProvider provide_invalid_sources
	 */
	public function test_an_invalid_source_is_rejected( string $raw ): void {
		$this->assertInstanceOf( \WP_Error::class, PaletteGenerator::parse_source( $raw ) );
	}

	public static function provide_invalid_sources(): array {
		return [
			'not json'          => [ 'not json at all' ],
			'empty array'       => [ '[]' ],
			'an object'         => [ '{"sources":[]}' ],
			'group without any' => [ '[{"uid":"g1"}]' ],
			'source w/o value'  => [ '[{"sources":[{"label":"No color"}]}]' ],
		];
	}

	/*
	 * ------------------------------------------------------------------
	 * Reporting helpers.
	 * ------------------------------------------------------------------
	 */

	/**
	 * `data.grades` is the count of ramp entries actually produced, not the echoed
	 * `sm_color_grades_number`: promoting brand colors evicts entries and pushes the sources
	 * back in, so a custom palette can land on 11 where the option says 12 (laws #9).
	 */
	public function test_the_grade_count_is_the_produced_ramp_not_the_requested_number(): void {
		$palettes = [
			[
				'id'      => 1,
				'options' => [ 'sm_color_grades_number' => 12 ],
				'colors'  => array_fill( 0, 11, '#000000' ),
			],
			[
				'id'      => '_info',
				'options' => [],
				'colors'  => array_fill( 0, 12, '#000000' ),
			],
		];

		$this->assertSame( 11, PaletteGenerator::grade_count( $palettes ) );
	}

	/**
	 * The `--generator=none` bar is exactly what PHP's CSS generation consumes. The grist run
	 * wrote its palette output by hand: 2 palettes, 12 variations each, no `colors` ramp and no
	 * echoed `options`. It renders correctly, so it must validate — a generator-shaped check
	 * would reject the very artifact that path exists to apply.
	 */
	public function test_a_hand_authored_blob_is_renderable_even_without_a_ramp(): void {
		$json = (string) file_get_contents( __DIR__ . '/../../fixtures/palette-parity/footer-grist.applied-output.json' );

		$validated = PaletteGenerator::validate_renderable( $json );

		$this->assertIsArray( $validated );
		$this->assertCount( 2, $validated['palettes'] );
		$this->assertSame( $json, $validated['json'] );
		$this->assertArrayNotHasKey( 'colors', $validated['palettes'][0] );
		// No ramp means no grades. Zero is the honest answer, and the signal.
		$this->assertSame( 0, PaletteGenerator::grade_count( $validated['palettes'] ) );
	}

	/**
	 * @dataProvider provide_unrenderable_outputs
	 */
	public function test_an_unrenderable_output_is_rejected( string $json ): void {
		$this->assertInstanceOf( \WP_Error::class, PaletteGenerator::validate_renderable( $json ) );
	}

	public static function provide_unrenderable_outputs(): array {
		return [
			'not json'             => [ 'not json' ],
			'empty list'           => [ '[]' ],
			'an object'            => [ '{"id":1}' ],
			'no variations'        => [ '[{"id":1,"sourceIndex":0,"darkVariations":[]}]' ],
			'wrong variation count' => [ '[{"id":1,"sourceIndex":0,"variations":[{}],"darkVariations":[{}]}]' ],
			'no sourceIndex'       => [ '[{"id":1,"variations":[{},{},{},{},{},{},{},{},{},{},{},{}],"darkVariations":[{},{},{},{},{},{},{},{},{},{},{},{}]}]' ],
		];
	}

	public function test_a_hand_authored_palette_blob_is_recognised(): void {
		// Some gene-migration runs write sm_advanced_palette_output directly: bespoke labels,
		// no `options` block. Regenerating over one silently replaces hand-authored work,
		// which is what --dry-run exists to surface.
		$hand_authored = '[{"id":1,"label":"Neutral study base","variations":[]}]';
		$generated     = '[{"id":1,"options":{"mode":"lch"},"variations":[]}]';

		$this->assertFalse( PaletteGenerator::is_generator_produced( $hand_authored ) );
		$this->assertTrue( PaletteGenerator::is_generator_produced( $generated ) );
		$this->assertFalse( PaletteGenerator::is_generator_produced( 'not json' ) );
	}

	/*
	 * ------------------------------------------------------------------
	 * §5 numeric normalizer — the one comparison both lanes must share.
	 * ------------------------------------------------------------------
	 */

	public function test_the_canonicalizer_sorts_keys_and_coerces_numeric_strings(): void {
		// The shipped default writes "12"/"0.9" where the run fixtures write 12/0.9 for the
		// same keys; without the coercion every fixture fails spuriously.
		$this->assertSame(
			PaletteGenerator::canonical_json( '{"b":"0.9","a":"12"}' ),
			PaletteGenerator::canonical_json( '{"a":12,"b":0.9}' )
		);
	}

	public function test_the_canonicalizer_preserves_array_order(): void {
		// Ramp order is meaning, not presentation: sorting a list would make two different
		// palettes compare equal.
		$this->assertNotSame(
			PaletteGenerator::canonical_json( '["#fff","#000"]' ),
			PaletteGenerator::canonical_json( '["#000","#fff"]' )
		);
	}

	public function test_the_canonicalizer_does_not_confuse_a_color_with_a_number(): void {
		$this->assertSame( '["#722F37"]', PaletteGenerator::canonical_json( '["#722F37"]' ) );
	}
}

}

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

	/**
	 * `PaletteGenerator::option_ids()` is a PHP mirror of `getColorOptionsIDs()` in
	 * `src/_js/shared/color-generator/colors.js`. Comparing it against a hardcoded copy of the
	 * same list would prove nothing — both PHP copies would drift together and stay green. So the
	 * list is parsed out of the JavaScript, which is the thing that must not diverge.
	 */
	public function test_the_option_inventory_mirrors_the_javascript_one(): void {
		$js = (string) file_get_contents( __DIR__ . '/../../../../src/_js/shared/color-generator/colors.js' );

		$this->assertSame(
			1,
			preg_match( '/getColorOptionsIDs\s*=\s*\(\)\s*=>\s*\{\s*return\s*\[(.*?)\]/s', $js, $block ),
			'Could not find getColorOptionsIDs() in colors.js — the mirror test must be repointed.'
		);

		preg_match_all( "/'([a-z0-9_]+)'/", $block[1], $ids );

		$this->assertNotEmpty( $ids[1] );
		$this->assertSame( $ids[1], PaletteGenerator::option_ids() );
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
		$ramp = static function ( array $variation ): string {
			return (string) json_encode(
				[
					[
						'id'             => 1,
						'sourceIndex'    => 0,
						'variations'     => array_fill( 0, 12, $variation ),
						'darkVariations' => array_fill( 0, 12, $variation ),
					],
				]
			);
		};

		$ok = [
			'bg'     => '#ffffff',
			'accent' => '#722F37',
			'fg1'    => '#301d1f',
			'fg2'    => '#4c2e31',
		];

		return [
			'not json'              => [ 'not json' ],
			'empty list'            => [ '[]' ],
			'an object'             => [ '{"id":1}' ],
			'no variations'         => [ '[{"id":1,"sourceIndex":0,"darkVariations":[]}]' ],
			'wrong variation count' => [ '[{"id":1,"sourceIndex":0,"variations":[{}],"darkVariations":[{}]}]' ],
			'no sourceIndex'        => [ '[{"id":1,"variations":[{},{},{},{},{},{},{},{},{},{},{},{}],"darkVariations":[{},{},{},{},{},{},{},{},{},{},{},{}]}]' ],

			// M3: a blob of empty variation objects used to pass and render broken custom
			// properties — the exact outcome validation exists to stop.
			'empty variations'      => [ $ramp( [] ) ],
			'missing fg2'           => [ $ramp( [ 'bg' => '#fff', 'accent' => '#000', 'fg1' => '#111' ] ) ],

			// The palette id becomes a CSS selector: `.sm-palette-<id>`.
			'hostile palette id'    => [ (string) str_replace( '"id":1', '"id":"1 {} html{display:none}"', $ramp( $ok ) ) ],
			'no palette id'         => [ (string) str_replace( '"id":1,', '', $ramp( $ok ) ) ],
		];
	}

	/*
	 * ------------------------------------------------------------------
	 * The CSS-injection boundary (security review F1).
	 *
	 * Variation keys and values are printed RAW into a stylesheet by
	 * sm_get_variation_css_variables(), and the block-editor sink adds them via
	 * wp_add_inline_style() without even stripping tags. `--generator=none` is the
	 * sanctioned path for machine-authored blobs, so value shape is checked at the
	 * persistence boundary rather than trusted from the pipeline that produced it.
	 * ------------------------------------------------------------------
	 */

	/**
	 * @dataProvider provide_hostile_colors
	 */
	public function test_a_hostile_variation_value_is_rejected( string $value ): void {
		$result = PaletteGenerator::validate_renderable( $this->blob_with_color( 'bg', $value ) );

		$this->assertInstanceOf( \WP_Error::class, $result, sprintf( 'Accepted a non-color value: %s', $value ) );
		$this->assertSame( 'style_manager_palette_output_invalid', $result->get_error_code() );
	}

	public static function provide_hostile_colors(): array {
		return [
			// Closes the declaration and the rule, then injects its own — defacement, content
			// hiding, a background:url() beacon. Survives wp_strip_all_tags() on the frontend.
			'css rule break-out'  => [ 'red } html{display:none} .x{color:red' ],
			// Breaks out of <style> entirely in the block editor, where tags are not stripped:
			// stored XSS against the next admin who opens the editor.
			'style tag break-out' => [ '</style><script>alert(1)</script>' ],
			'url beacon'          => [ 'url(https://evil.example/x.png)' ],
			'expression'          => [ 'expression(alert(1))' ],
			'import'              => [ '#fff; } @import url(//evil.example/x.css); a{color:#fff' ],
			'bare keyword'        => [ 'red' ],
			'css variable'        => [ 'var(--sm-current-bg-color)' ],
			'empty'               => [ '' ],
			'almost hex'          => [ '#12345' ],
			'hex with suffix'     => [ '#ffffff;color:red' ],
		];
	}

	/**
	 * @dataProvider provide_valid_colors
	 */
	public function test_a_real_color_is_accepted( string $value ): void {
		$this->assertIsArray( PaletteGenerator::validate_renderable( $this->blob_with_color( 'bg', $value ) ) );
	}

	public static function provide_valid_colors(): array {
		return [
			'#rrggbb'   => [ '#722F37' ],
			'#rgb'      => [ '#fff' ],
			'#rgba'     => [ '#fff8' ],
			'#rrggbbaa' => [ '#722F3780' ],
			'rgb()'     => [ 'rgb(114, 47, 55)' ],
			'rgba()'    => [ 'rgba(114, 47, 55, 0.5)' ],
			'hsl()'     => [ 'hsl(352, 42%, 32%)' ],
			'hsla()'    => [ 'hsla(352, 42%, 32%, 0.5)' ],
			'slash rgb' => [ 'rgb(114 47 55 / 50%)' ],
		];
	}

	/**
	 * The key is interpolated into the custom-property NAME (`--sm-<key>-color-N`), so it is a
	 * sink in its own right — a valid color value does not make a hostile key safe.
	 */
	public function test_a_hostile_variation_key_is_rejected(): void {
		$blob = (string) json_encode(
			[
				[
					'id'             => 1,
					'sourceIndex'    => 0,
					'variations'     => array_fill(
						0,
						12,
						[
							'bg'                       => '#ffffff',
							'accent'                   => '#722F37',
							'fg1'                      => '#301d1f',
							'fg2'                      => '#4c2e31',
							'x: red; } html{display:none' => '#ffffff',
						]
					),
					'darkVariations' => array_fill( 0, 12, [ 'bg' => '#000', 'accent' => '#fff', 'fg1' => '#fff', 'fg2' => '#fff' ] ),
				],
			]
		);

		$this->assertInstanceOf( \WP_Error::class, PaletteGenerator::validate_renderable( $blob ) );
	}

	/**
	 * The grammar must not have been tightened past the real corpus: every checked-in fixture and
	 * the plugin's own shipped default have to keep validating, or the guard is breaking the
	 * artifacts it exists to protect.
	 */
	public function test_every_shipped_and_fixture_palette_still_validates(): void {
		$files = glob( __DIR__ . '/../../fixtures/palette-parity/*output.json' );
		$files[] = __DIR__ . '/../../../../src/Customize/sm_advanced_palette_output.json';

		$this->assertGreaterThanOrEqual( 5, count( $files ) );

		foreach ( $files as $file ) {
			$result = PaletteGenerator::validate_renderable( (string) file_get_contents( $file ) );

			$this->assertIsArray( $result, sprintf( 'Real palette output rejected: %s', basename( $file ) ) );
		}
	}

	/**
	 * Build a valid blob with one variation value replaced.
	 */
	private function blob_with_color( string $key, string $value ): string {
		$variation = [
			'bg'     => '#ffffff',
			'accent' => '#722F37',
			'fg1'    => '#301d1f',
			'fg2'    => '#4c2e31',
		];

		$variation[ $key ] = $value;

		return (string) json_encode(
			[
				[
					'id'             => 1,
					'sourceIndex'    => 0,
					'variations'     => array_fill( 0, 12, $variation ),
					'darkVariations' => array_fill( 0, 12, $variation ),
				],
			]
		);
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

<?php
/**
 * The build-time drift guard for the headless color-ramp generator.
 *
 * Agent-surface contract §5 P2 and the swarm plan's §8 drift guard: the Node build artifact
 * and the Customizer bundle share one module, so a regenerated `sm_advanced_palette_output`
 * must be **canonical-JSON byte-identical** to browser-produced ground truth. There is no
 * epsilon on color values — a hex divergence is a real drift finding, not a tolerance to
 * relax. If this test ever fails, the artifact has drifted from the browser (a chroma-js
 * bump, a stale `dist/node/palette-generator.js`, a change to the shared module), and the
 * CLI must not be shipped until it passes again.
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

class PaletteGeneratorParityTest extends TestCase {

	/**
	 * The plugin root — the artifact lives under it.
	 */
	private const PLUGIN_DIR = __DIR__ . '/../../../..';

	/**
	 * Browser-produced source→output pairs checked in from the lab corpus.
	 *
	 * Deliberately spanning the shapes contract §5 P2 names — 1 group, 3 groups, and a group
	 * carrying several source colors. **`part-footer-grist` (the contract's P2-b candidate) is
	 * not here on purpose**: that run wrote `sm_advanced_palette_output` by hand rather than
	 * generating it (2 palettes, bespoke labels, no `options`/`colors` keys at all), so it is
	 * not a generator artifact and cannot be a parity oracle. `hive` replaces it as the
	 * multi-hue case and additionally exercises the §5 numeric normalizer: it echoes
	 * `sm_color_grades_number` as the string `"12"`.
	 */
	private const PAIRS = [
		'mold-dining-interface' => 'P2-a — 1 group, 1 color; stored pretty-printed, so raw bytes cannot match and only the canonical comparison can pass it',
		'alexia-ponce'          => 'P2-c — 3 groups, the largest generated ramp in the corpus',
		'hive'                  => 'multi-hue — 2 groups / 4 source colors, with string-typed echoed options',
	];

	public function setUp(): void {
		parent::setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, int $flags = 0 ) => json_encode( $value, $flags )
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
	}

	/**
	 * Contract §5 P2-d — the CI-resident fixture.
	 *
	 * The plugin's own shipped `src/Customize/sm_advanced_palette_output.json` is
	 * self-describing: its first palette records the `source` it was generated from and an
	 * `options` block recording the exact generator inputs. Reconstruct both, regenerate, and
	 * require canonical byte-identity. This is the one fixture that ships with the plugin, so
	 * it is the guard that runs everywhere the suite runs.
	 */
	public function test_the_shipped_default_palette_output_regenerates_byte_identically(): void {
		$generator = $this->generator();

		$shipped_json = (string) file_get_contents( self::PLUGIN_DIR . '/src/Customize/sm_advanced_palette_output.json' );
		$shipped      = json_decode( $shipped_json, true );

		$this->assertIsArray( $shipped, 'The shipped palette output must be readable JSON.' );

		$result = $generator->generate(
			(string) wp_json_encode( $this->reconstruct_source( $shipped ) ),
			(array) $shipped[0]['options']
		);

		$this->assertNotWPError( $result );
		$this->assertSame(
			PaletteGenerator::canonical_json( $shipped_json ),
			PaletteGenerator::canonical_json( $result['json'] ),
			'The shipped default palette output no longer regenerates from its own recorded source and options — the Node artifact has drifted from the Customizer.'
		);
	}

	/**
	 * @dataProvider provide_pairs
	 */
	public function test_browser_produced_fixtures_regenerate_byte_identically( string $name, string $why ): void {
		$generator = $this->generator();

		$directory = __DIR__ . '/../../fixtures/palette-parity';
		$source    = (string) file_get_contents( $directory . '/' . $name . '.source.json' );
		$expected  = (string) file_get_contents( $directory . '/' . $name . '.output.json' );

		$stored = json_decode( $expected, true );
		$this->assertIsArray( $stored, 'Fixture output must be readable JSON.' );

		$result = $generator->generate( $source, (array) $stored[0]['options'] );

		$this->assertNotWPError( $result );
		$this->assertSame(
			PaletteGenerator::canonical_json( $expected ),
			PaletteGenerator::canonical_json( $result['json'] ),
			sprintf( 'Parity lost against the browser-produced fixture "%s" (%s).', $name, $why )
		);
	}

	public static function provide_pairs(): array {
		$cases = [];
		foreach ( self::PAIRS as $name => $why ) {
			$cases[ $name ] = [ $name, $why ];
		}

		return $cases;
	}

	/**
	 * The whole point of the parity suite is running the real artifact, so it is never
	 * silently substituted; it is skipped, loudly, only where Node is genuinely absent.
	 */
	private function generator(): PaletteGenerator {
		$generator = new PaletteGenerator( $this->createMock( Options::class ), self::PLUGIN_DIR );

		if ( ! is_readable( $generator->artifact_path() ) ) {
			$this->markTestSkipped(
				'Build the Node palette generator first: `npm run compile:production` writes ' . PaletteGenerator::ARTIFACT_RELATIVE_PATH . '.'
			);
		}

		if ( '' === $generator->node_binary() ) {
			$this->markTestSkipped( 'No Node binary found; the palette parity guard needs one.' );
		}

		return $generator;
	}

	/**
	 * Rebuild the `sm_advanced_palette_source` a generated output was produced from.
	 *
	 * Only the brand groups are reconstructed: the `_info` / `_error` / `_warning` /
	 * `_success` palettes are *derived* from the first brand color by `getFunctionalColors()`,
	 * so feeding them back in as sources would generate them twice.
	 *
	 * @param array $palettes The decoded palette output.
	 *
	 * @return array
	 */
	private function reconstruct_source( array $palettes ): array {
		$groups = [];

		foreach ( $palettes as $palette ) {
			if ( 0 === strpos( (string) ( $palette['id'] ?? '' ), '_' ) ) {
				continue;
			}

			$sources = [];
			foreach ( (array) $palette['source'] as $index => $value ) {
				$source = [ 'value' => $value ];

				// `mapColorToPalette()` reads the label and id off the first source only.
				if ( 0 === $index ) {
					$source['label'] = $palette['label'] ?? '';
					$source['id']    = $palette['id'] ?? '';
				}

				$sources[] = $source;
			}

			$groups[] = [ 'sources' => $sources ];
		}

		return $groups;
	}

	private function assertNotWPError( $result ): void {
		if ( $result instanceof \WP_Error ) {
			$this->fail( 'The generator failed: ' . $result->get_error_message() );
		}

		$this->assertIsArray( $result );
	}
}

}

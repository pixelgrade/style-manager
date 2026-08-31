<?php
/**
 * The headless Color System palette generator.
 *
 * Runs the *same* `getPalettesFromColors()` module the Customizer bundle runs — bundled by
 * webpack (`target: 'node'`) into the committed build artifact `dist/node/palette-generator.js`
 * — so a CLI-regenerated `sm_advanced_palette_output` is byte-identical to what a browser
 * session would have produced. That shared module is the drift guard the agent-surface
 * contract §3.11 asks for; a PHP port of chroma-js's `correctLightness()` and the HPLuv
 * round-trip would be a parity coin flip maintained in lockstep forever.
 *
 * @since   2.6.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

/**
 * Invokes the Node palette generator and owns the option resolution it depends on.
 *
 * **Option resolution is the whole risk surface** and the reason this class exists rather
 * than a few helpers on the CLI. The W5 spike measured it: feeding the generator raw
 * `wp option get` values reproduced **0 of 9** corpus fixtures, several with visible color
 * shifts, because `sm_color_promotion_brand` is declared without a `default` and the JS
 * fallback (`'#000'`, a truthy string) turns brand promotion on when the Customizer has it
 * off. Resolving through `Options::get()` — the three-store resolver behind
 * `\Pixelgrade\StyleManager\get_option()`, contract §3.1 — and mapping the one remaining
 * null to `''` reproduces them.
 *
 * @since 2.6.0
 */
class PaletteGenerator {

	/**
	 * Where the webpack `target: 'node'` build artifact lands, relative to the plugin root.
	 *
	 * Pinned by contract F13: the CLI probes this path, and the build ships to it.
	 */
	public const ARTIFACT_RELATIVE_PATH = 'dist/node/palette-generator.js';

	/**
	 * The Color System settings this command reads and writes.
	 */
	public const SOURCE_SETTING_ID    = 'sm_advanced_palette_source';
	public const OUTPUT_SETTING_ID    = 'sm_advanced_palette_output';
	public const IS_CUSTOM_SETTING_ID = 'sm_is_custom_color_palette';
	public const VARIATION_SETTING_ID = 'sm_site_color_variation';

	/**
	 * The one generator option declared without a `default` (type `html` in
	 * `Customize\ColorPalettes`), so `Options::get()` resolves it to null. The registered
	 * Customizer setting holds `''` at runtime, and `''` is falsy where `'#000'` is not —
	 * which is exactly the difference between brand promotion off and on.
	 */
	public const BRAND_PROMOTION_SETTING_ID = 'sm_color_promotion_brand';

	/**
	 * How long the Node process may run before we give up on it, in seconds. The spike
	 * measured ~450ms end to end including cold start; anything near this ceiling is a hang.
	 */
	public const TIMEOUT_SECONDS = 30;

	/**
	 * Filter name for pointing the plugin at a Node binary (contract §3.11).
	 */
	public const NODE_BINARY_FILTER = 'style_manager/node_binary';

	/**
	 * Constant name for pointing the plugin at a Node binary (contract §3.11).
	 */
	public const NODE_BINARY_CONSTANT = 'PIXELGRADE_NODE_BINARY';

	/**
	 * Options provider — the three-store resolver of contract §3.1.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Plugin root directory, without a trailing slash.
	 *
	 * @var string
	 */
	protected string $plugin_dir;

	/**
	 * @param Options     $options    Options provider.
	 * @param string|null $plugin_dir Plugin root. Defaults to this file's plugin.
	 */
	public function __construct( Options $options, ?string $plugin_dir = null ) {
		$this->options    = $options;
		$this->plugin_dir = rtrim( $plugin_dir ?? dirname( __DIR__, 2 ), '/' );
	}

	/*
	|--------------------------------------------------------------------------
	| Availability (contract §3.11 — declared, bundled, fails gracefully)
	|--------------------------------------------------------------------------
	*/

	/**
	 * The committed build artifact's absolute path.
	 *
	 * @return string
	 */
	public function artifact_path(): string {
		return $this->plugin_dir . '/' . self::ARTIFACT_RELATIVE_PATH;
	}

	/**
	 * Resolve the Node binary: the `PIXELGRADE_NODE_BINARY` constant, else the
	 * `style_manager/node_binary` filter, else `node` on PATH.
	 *
	 * PATH is walked in PHP rather than shelled out to, so no `command -v` subshell and no
	 * quoting surface.
	 *
	 * @return string The executable path, or '' when none was found.
	 */
	public function node_binary(): string {
		if ( defined( self::NODE_BINARY_CONSTANT ) ) {
			$candidate = (string) constant( self::NODE_BINARY_CONSTANT );
			if ( '' !== $candidate && @is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		if ( function_exists( 'apply_filters' ) ) {
			$candidate = apply_filters( self::NODE_BINARY_FILTER, '' );
			if ( is_string( $candidate ) && '' !== $candidate && @is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		foreach ( $this->path_directories() as $directory ) {
			$candidate = rtrim( $directory, '/' ) . '/node';
			if ( @is_executable( $candidate ) ) {
				return $candidate;
			}
		}

		return '';
	}

	/**
	 * The PATH directories to search for `node`.
	 *
	 * WP-CLI under a GUI-launched PHP (Studio, MAMP) often inherits a minimal PATH, so the
	 * usual installation prefixes are appended — a missing binary must be a clear
	 * `generator_unavailable`, not a mystery.
	 *
	 * @return string[]
	 */
	protected function path_directories(): array {
		$path = (string) getenv( 'PATH' );
		$dirs = '' === $path ? [] : explode( PATH_SEPARATOR, $path );

		return array_values(
			array_unique(
				array_filter(
					array_merge(
						$dirs,
						[
							'/opt/homebrew/bin',
							'/usr/local/bin',
							'/usr/bin',
						]
					)
				)
			)
		);
	}

	/**
	 * Everything that was probed, for the `generator_unavailable` envelope's `data.looked_for`.
	 *
	 * @return string[]
	 */
	public function looked_for(): array {
		$binary = $this->node_binary();

		return [
			$this->artifact_path(),
			'' !== $binary ? $binary : sprintf(
				'node (%s constant, %s filter, then PATH)',
				self::NODE_BINARY_CONSTANT,
				self::NODE_BINARY_FILTER
			),
		];
	}

	/**
	 * Whether both halves of the runtime are present.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return is_readable( $this->artifact_path() ) && '' !== $this->node_binary();
	}

	/*
	|--------------------------------------------------------------------------
	| Option resolution
	|--------------------------------------------------------------------------
	*/

	/**
	 * The generator's option inventory — a PHP mirror of `getColorOptionsIDs()` in
	 * `src/_js/shared/color-generator/colors.js`. Keep the two in lockstep.
	 *
	 * `sm_site_color_variation` is in the list because the browser reads it into the options
	 * blob and echoes it into the output; no math consumes it (verified in the spike: pairs
	 * differing only on this key are color-identical, max channel delta 0).
	 *
	 * @return string[]
	 */
	public static function option_ids(): array {
		return [
			'sm_color_grades_number',
			'sm_potential_color_contrast',
			'sm_color_grade_balancer',
			self::VARIATION_SETTING_ID,
			'sm_elements_color_contrast',
			self::BRAND_PROMOTION_SETTING_ID,
			'sm_color_promotion_white',
			'sm_color_promotion_black',
		];
	}

	/**
	 * Resolve every generator input through the three-store resolver (contract §3.1).
	 *
	 * `Options::get()` already falls back to the config default, so the only null left is
	 * `sm_color_promotion_brand` — normalized to `''`, the value the registered Customizer
	 * setting actually holds. Feeding the JS-side `'#000'` fallback instead is what made the
	 * spike's naive run reproduce 0 of 9 fixtures.
	 *
	 * @param array $overrides setting_id => value, highest precedence (e.g. `--variation`).
	 *
	 * @return array The fully resolved option map handed to the generator.
	 */
	public function resolve_options( array $overrides = [] ): array {
		$resolved = [];

		foreach ( self::option_ids() as $option_id ) {
			if ( array_key_exists( $option_id, $overrides ) ) {
				$resolved[ $option_id ] = $overrides[ $option_id ];
				continue;
			}

			$value = $this->options->get( $option_id );

			$resolved[ $option_id ] = ( null === $value ) ? '' : $value;
		}

		return $resolved;
	}

	/**
	 * Read a Color System setting through the three-store resolver.
	 *
	 * @param string $option_id Setting id.
	 *
	 * @return mixed
	 */
	public function current_value( string $option_id ) {
		return $this->options->get( $option_id );
	}

	/*
	|--------------------------------------------------------------------------
	| Generation
	|--------------------------------------------------------------------------
	*/

	/**
	 * Validate a `sm_advanced_palette_source` document.
	 *
	 * @param string $raw The raw JSON.
	 *
	 * @return array|\WP_Error The decoded groups, or an error naming what is wrong.
	 */
	public static function parse_source( string $raw ) {
		$decoded = json_decode( trim( $raw ), true );

		if ( ! is_array( $decoded ) || empty( $decoded ) || ! array_is_list( $decoded ) ) {
			return new \WP_Error(
				'style_manager_palette_source_invalid',
				__( 'The palette source must be a non-empty JSON array of color groups.', '__plugin_txtd' )
			);
		}

		foreach ( $decoded as $index => $group ) {
			if ( ! is_array( $group ) || empty( $group['sources'] ) || ! is_array( $group['sources'] ) ) {
				return new \WP_Error(
					'style_manager_palette_source_invalid',
					sprintf(
						/* translators: %d: zero-based index of the offending color group. */
						__( 'Color group %d has no `sources` array.', '__plugin_txtd' ),
						$index
					)
				);
			}

			foreach ( $group['sources'] as $source ) {
				if ( ! is_array( $source ) || empty( $source['value'] ) || ! is_string( $source['value'] ) ) {
					return new \WP_Error(
						'style_manager_palette_source_invalid',
						sprintf(
							/* translators: %d: zero-based index of the offending color group. */
							__( 'Color group %d has a source without a `value` color.', '__plugin_txtd' ),
							$index
						)
					);
				}
			}
		}

		return $decoded;
	}

	/**
	 * Run the generator.
	 *
	 * @param string $source_json The raw `sm_advanced_palette_source` JSON.
	 * @param array  $options     The resolved generator options.
	 *
	 * @return array|\WP_Error { @type string $json The raw output JSON, exactly as persisted.
	 *                           @type array  $palettes The decoded palettes. }
	 */
	public function generate( string $source_json, array $options ) {
		$artifact = $this->artifact_path();
		$binary   = $this->node_binary();

		if ( ! is_readable( $artifact ) || '' === $binary ) {
			return new \WP_Error(
				'style_manager_generator_unavailable',
				__( 'The bundled Node palette generator is not available.', '__plugin_txtd' ),
				[ 'looked_for' => $this->looked_for() ]
			);
		}

		$request = wp_json_encode(
			[
				'source'  => $source_json,
				'options' => $options,
			],
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		$result = $this->run( $binary, $artifact, (string) $request );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->validate_output( $result );
	}

	/**
	 * Shell out to Node with the request on STDIN.
	 *
	 * `proc_open` with an argument array — never a shell string — so no path or JSON payload
	 * can ever be interpreted as shell syntax.
	 *
	 * @param string $binary   Node executable.
	 * @param string $artifact Generator script.
	 * @param string $request  The JSON request.
	 *
	 * @return string|\WP_Error Raw STDOUT.
	 */
	protected function run( string $binary, string $artifact, string $request ) {
		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		$pipes   = [];
		$process = @proc_open( [ $binary, $artifact ], $descriptors, $pipes );

		if ( ! is_resource( $process ) ) {
			return new \WP_Error(
				'style_manager_generator_unavailable',
				sprintf(
					/* translators: %s: node binary path. */
					__( 'Could not start the Node palette generator (%s).', '__plugin_txtd' ),
					$binary
				),
				[ 'looked_for' => $this->looked_for() ]
			);
		}

		fwrite( $pipes[0], $request );
		fclose( $pipes[0] );

		stream_set_timeout( $pipes[1], self::TIMEOUT_SECONDS );
		stream_set_timeout( $pipes[2], self::TIMEOUT_SECONDS );

		$stdout = (string) stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		$stderr = trim( (string) stream_get_contents( $pipes[2] ) );
		fclose( $pipes[2] );

		$exit_code = proc_close( $process );

		if ( 0 !== $exit_code || '' === trim( $stdout ) ) {
			return new \WP_Error(
				'style_manager_generator_failed',
				sprintf(
					/* translators: 1: exit code, 2: the generator's stderr. */
					__( 'The Node palette generator failed (exit %1$d): %2$s', '__plugin_txtd' ),
					$exit_code,
					'' !== $stderr ? $stderr : __( 'no output', '__plugin_txtd' )
				),
				[ 'exit_code' => $exit_code ]
			);
		}

		return $stdout;
	}

	/**
	 * Assert the generator returned a usable palette output before anything is persisted.
	 *
	 * The point is that a malformed blob must never reach `sm_advanced_palette_output`: PHP's
	 * CSS generation reads `variations` / `darkVariations` / `sourceIndex` and would render a
	 * broken site rather than fail loudly.
	 *
	 * @param string $json Raw generator STDOUT.
	 *
	 * @return array|\WP_Error
	 */
	protected function validate_output( string $json ) {
		$palettes = json_decode( $json, true );

		if ( ! is_array( $palettes ) || empty( $palettes ) || ! array_is_list( $palettes ) ) {
			return new \WP_Error(
				'style_manager_generator_output_invalid',
				__( 'The generator did not return a non-empty array of palettes.', '__plugin_txtd' )
			);
		}

		foreach ( $palettes as $index => $palette ) {
			if ( ! is_array( $palette ) ) {
				return new \WP_Error(
					'style_manager_generator_output_invalid',
					sprintf(
						/* translators: %d: palette index. */
						__( 'Generated palette %d is not an object.', '__plugin_txtd' ),
						$index
					)
				);
			}

			foreach ( [ 'variations', 'darkVariations' ] as $key ) {
				if ( empty( $palette[ $key ] ) || ! is_array( $palette[ $key ] ) || 12 !== count( $palette[ $key ] ) ) {
					return new \WP_Error(
						'style_manager_generator_output_invalid',
						sprintf(
							/* translators: 1: palette index, 2: the missing key. */
							__( 'Generated palette %1$d does not carry 12 `%2$s`.', '__plugin_txtd' ),
							$index,
							$key
						)
					);
				}
			}

			if ( ! array_key_exists( 'sourceIndex', $palette ) || ! is_int( $palette['sourceIndex'] ) ) {
				return new \WP_Error(
					'style_manager_generator_output_invalid',
					sprintf(
						/* translators: %d: palette index. */
						__( 'Generated palette %d has no integer `sourceIndex`.', '__plugin_txtd' ),
						$index
					)
				);
			}

			if ( empty( $palette['colors'] ) || ! is_array( $palette['colors'] ) ) {
				return new \WP_Error(
					'style_manager_generator_output_invalid',
					sprintf(
						/* translators: %d: palette index. */
						__( 'Generated palette %d has no color ramp.', '__plugin_txtd' ),
						$index
					)
				);
			}
		}

		return [
			'json'     => $json,
			'palettes' => $palettes,
		];
	}

	/*
	|--------------------------------------------------------------------------
	| Reporting helpers
	|--------------------------------------------------------------------------
	*/

	/**
	 * The real grade count produced (contract §1.1's `data.grades`).
	 *
	 * Read off the first brand palette's ramp rather than echoed from
	 * `sm_color_grades_number`: `mapForceColors()` evicts a ramp entry per promoted brand
	 * color and pushes the sources back in, so a custom palette can land on 11 where the
	 * option says 12 (gene-migration laws #9). Functional palettes (`_info`, `_error`, …)
	 * are derived, so they are not the answer to "how many grades did I get".
	 *
	 * @param array $palettes Decoded palettes.
	 *
	 * @return int
	 */
	public static function grade_count( array $palettes ): int {
		foreach ( $palettes as $palette ) {
			if ( ! is_array( $palette ) || empty( $palette['colors'] ) || ! is_array( $palette['colors'] ) ) {
				continue;
			}

			if ( 0 === strpos( (string) ( $palette['id'] ?? '' ), '_' ) ) {
				continue;
			}

			return count( $palette['colors'] );
		}

		return 0;
	}

	/**
	 * Whether a stored `sm_advanced_palette_output` blob looks generator-produced.
	 *
	 * The reliable signal is the echoed `options` block: the gene-migration runs sometimes
	 * write this option by hand, and those blobs carry neither `options` nor `colors`.
	 * Regenerating over one silently replaces bespoke work — which is why `--dry-run` exists
	 * and why it reports this flag (spike risk 4).
	 *
	 * @param mixed $stored The stored value (raw JSON string or decoded array).
	 *
	 * @return bool
	 */
	public static function is_generator_produced( $stored ): bool {
		$decoded = is_string( $stored ) ? json_decode( $stored, true ) : $stored;

		if ( ! is_array( $decoded ) || empty( $decoded ) || ! array_is_list( $decoded ) ) {
			return false;
		}

		foreach ( $decoded as $palette ) {
			if ( ! is_array( $palette ) || ! isset( $palette['options'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The contract §5 canonicalizer: recursively sort object keys and coerce numeric-looking
	 * strings to numbers.
	 *
	 * Both halves are load-bearing. Key order is not stable across producers (PHP
	 * `json_encode` does not preserve the fixture's authoring order), and the shipped default
	 * writes its echoed options as strings (`"12"`, `"0.9"`) where the run fixtures write them
	 * as numbers (`12`, `1`) — so a comparison that skips either fails every fixture
	 * spuriously. This is the single normalizer the implementer and the verifier both use.
	 *
	 * @param mixed $value Value.
	 *
	 * @return mixed
	 */
	public static function canonicalize( $value ) {
		if ( is_object( $value ) ) {
			$value = (array) $value;
		}

		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $item ) {
				$out[ $key ] = self::canonicalize( $item );
			}

			if ( ! array_is_list( $out ) ) {
				ksort( $out );
			}

			return $out;
		}

		if ( is_string( $value ) && is_numeric( $value ) ) {
			return 0 + $value;
		}

		return $value;
	}

	/**
	 * Canonical JSON for the contract §5 P2 comparison: parse, canonicalize, re-serialize
	 * with fixed separators. Byte-equal canonical JSON is the pass criterion — no epsilon on
	 * color values, because both sides run the same module.
	 *
	 * @param mixed $value Raw JSON string or a decoded value.
	 *
	 * @return string
	 */
	public static function canonical_json( $value ): string {
		$decoded = is_string( $value ) ? json_decode( $value, true ) : $value;

		return (string) wp_json_encode( self::canonicalize( $decoded ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}

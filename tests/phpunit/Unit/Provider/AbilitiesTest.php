<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'StyleManagerCliHalt', false ) ) {
		class StyleManagerCliHalt extends \Exception {}
	}

	if ( ! class_exists( 'WP_CLI', false ) ) {
		class WP_CLI {
			public static array $commands = [];
			public static array $success_messages = [];
			public static array $warnings = [];
			public static array $logs = [];
			public static array $lines = [];

			public static function add_command( string $name, $callback, array $args = [] ): void {
				self::$commands[ $name ] = $callback;
			}

			public static function success( string $message ): void {
				self::$success_messages[] = $message;
			}

			public static function warning( string $message ): void {
				self::$warnings[] = $message;
			}

			public static function log( string $message ): void {
				self::$logs[] = $message;
			}

			public static function line( string $message = '' ): void {
				self::$lines[] = $message;
			}

			public static function confirm( string $question, array $assoc_args = [] ): void {
			}

			public static function halt( int $code ): void {
				throw new \StyleManagerCliHalt( 'halt', $code );
			}
		}
	}

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

	/**
	 * Stands in for the WordPress 6.9 Abilities registry. Declared unconditionally so
	 * `function_exists( 'wp_register_ability' )` is deterministic for the whole suite —
	 * a Brain\Monkey stub defined inside one test would leak into the ordering of the next.
	 */
	if ( ! class_exists( 'StyleManagerAbilitiesRegistry', false ) ) {
		class StyleManagerAbilitiesRegistry {
			public static array $abilities  = [];
			public static array $categories = [];

			public static function reset(): void {
				self::$abilities  = [];
				self::$categories = [];
			}
		}
	}

	if ( ! function_exists( 'wp_register_ability' ) ) {
		function wp_register_ability( string $name, array $args ) {
			\StyleManagerAbilitiesRegistry::$abilities[ $name ] = $args;

			return null;
		}
	}

	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		function wp_register_ability_category( string $slug, array $args ) {
			\StyleManagerAbilitiesRegistry::$categories[ $slug ] = $args;

			return null;
		}
	}

	if ( ! function_exists( 'wp_has_ability_category' ) ) {
		function wp_has_ability_category( string $slug ): bool {
			return isset( \StyleManagerAbilitiesRegistry::$categories[ $slug ] );
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Brain\Monkey\Filters;
	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\FontPalettes;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Provider\Abilities;
	use Pixelgrade\StyleManager\Provider\AgentCommands;
	use Pixelgrade\StyleManager\Provider\CliCommands;
	use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Provider\Options;
	use Pixelgrade\StyleManager\Provider\PaletteGenerator;
	use Pixelgrade\StyleManager\Provider\SettingsWriter;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	class AbilitiesTest extends TestCase {

		/**
		 * The eight verbs contract §4 assigns to Style Manager, with their pinned annotations.
		 */
		private const EXPECTED = [
			'pixelgrade/get-design-system'   => [ true, false, true ],
			'pixelgrade/get-design-settings' => [ true, false, true ],
			'pixelgrade/get-design-structure' => [ true, false, true ],
			'pixelgrade/export-design-system' => [ true, false, true ],
			'pixelgrade/set-design-settings' => [ false, false, true ],
			'pixelgrade/apply-font-palette'  => [ false, true, true ],
			'pixelgrade/apply-color-palette' => [ false, true, true ],
			'pixelgrade/flush-design-cache'  => [ true, false, true ],
		];

		public function setUp(): void {
			parent::setUp();

			\StyleManagerAbilitiesRegistry::reset();
			\WP_CLI::$lines = [];

			Functions\when( '__' )->returnArg( 1 );
			Functions\when( '_n' )->alias(
				static function ( string $single, string $plural, int $number ) {
					return 1 === $number ? $single : $plural;
				}
			);
			Functions\when( 'wp_json_encode' )->alias(
				static function ( $value, int $flags = 0 ) {
					return json_encode( $value, $flags );
				}
			);
			Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
			Functions\when( 'get_current_user_id' )->justReturn( 1 );
			Functions\when( 'current_user_can' )->justReturn( true );
			Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.1 — registration presence and shape.
		 * ------------------------------------------------------------------
		 */

		public function test_every_owned_ability_registers_with_the_full_shape(): void {
			$this->build()->register_abilities();

			$this->assertSame(
				array_keys( self::EXPECTED ),
				array_keys( \StyleManagerAbilitiesRegistry::$abilities ),
				'The ability names are exact and final — the curated server keys off them.'
			);

			foreach ( \StyleManagerAbilitiesRegistry::$abilities as $name => $args ) {
				foreach ( [ 'label', 'description', 'category', 'input_schema', 'output_schema', 'execute_callback', 'permission_callback', 'meta' ] as $key ) {
					$this->assertArrayHasKey( $key, $args, sprintf( '%s is missing %s.', $name, $key ) );
				}

				$this->assertSame( 'pixelgrade', $args['category'] );
				$this->assertIsCallable( $args['execute_callback'] );
				$this->assertIsCallable( $args['permission_callback'] );

				// An empty input_schema would change the execute_callback's arity, so every
				// ability declares one — even the ones that take nothing.
				$this->assertSame( 'object', $args['input_schema']['type'] );
				$this->assertNotEmpty( $args['description'] );
				$this->assertArrayHasKey( 'ok', $args['output_schema']['properties'] );
				$this->assertArrayHasKey( 'data', $args['output_schema']['properties'] );
				$this->assertArrayHasKey( 'warnings', $args['output_schema']['properties'] );
			}
		}

		public function test_the_writes_declare_the_stripped_diff_in_their_output_schema(): void {
			$this->build()->register_abilities();

			foreach ( [ 'pixelgrade/set-design-settings', 'pixelgrade/apply-font-palette', 'pixelgrade/apply-color-palette' ] as $name ) {
				$properties = \StyleManagerAbilitiesRegistry::$abilities[ $name ]['output_schema']['properties'];

				$this->assertArrayHasKey( 'persisted', $properties, $name );
				$this->assertArrayHasKey( 'unchanged', $properties, $name );
				$this->assertArrayHasKey( 'stripped', $properties, $name );
			}
		}

		public function test_the_shared_category_registers_once_and_idempotently(): void {
			$abilities = $this->build();

			$abilities->register_category();
			$this->assertArrayHasKey( 'pixelgrade', \StyleManagerAbilitiesRegistry::$categories );
			$this->assertSame( 'Pixelgrade', \StyleManagerAbilitiesRegistry::$categories['pixelgrade']['label'] );

			// A second plugin registering the same category must not clobber the first.
			\StyleManagerAbilitiesRegistry::$categories['pixelgrade']['label'] = 'Already there';
			$abilities->register_category();
			$this->assertSame( 'Already there', \StyleManagerAbilitiesRegistry::$categories['pixelgrade']['label'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.2 — annotations match contract §4, as data.
		 * ------------------------------------------------------------------
		 */

		public function test_annotations_match_the_contract_table(): void {
			$this->build()->register_abilities();

			foreach ( self::EXPECTED as $name => [ $readonly, $destructive, $idempotent ] ) {
				$annotations = \StyleManagerAbilitiesRegistry::$abilities[ $name ]['meta']['annotations'];

				$this->assertSame( $readonly, $annotations['readonly'], $name );
				$this->assertSame( $destructive, $annotations['destructive'], $name );
				$this->assertSame( $idempotent, $annotations['idempotent'], $name );
			}
		}

		public function test_set_design_settings_discloses_the_master_slot_caveat_it_cannot_annotate(): void {
			// §4 pins destructive:false, so the caveat has to live in the description an LLM reads.
			$this->build()->register_abilities();

			$description = \StyleManagerAbilitiesRegistry::$abilities['pixelgrade/set-design-settings']['description'];

			$this->assertStringContainsString( 'ordering_conflict', $description );
			$this->assertStringContainsString( 'confirm: true', $description );
			$this->assertStringContainsString( 'stripped', $description );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.3 — private by default; the whitelist is the only way out.
		 * ------------------------------------------------------------------
		 */

		public function test_every_ability_is_private_without_the_whitelist_filter(): void {
			$this->build()->register_abilities();

			foreach ( \StyleManagerAbilitiesRegistry::$abilities as $name => $args ) {
				$this->assertFalse( $args['meta']['mcp']['public'], sprintf( '%s must be private by default.', $name ) );
			}
		}

		public function test_the_whitelist_filter_flips_exactly_the_named_ability(): void {
			Filters\expectApplied( 'pixelgrade/mcp/public_abilities' )
				->once()
				->andReturn( [ 'pixelgrade/get-design-system' ] );

			$this->build()->register_abilities();

			$this->assertTrue( \StyleManagerAbilitiesRegistry::$abilities['pixelgrade/get-design-system']['meta']['mcp']['public'] );
			$this->assertFalse( \StyleManagerAbilitiesRegistry::$abilities['pixelgrade/get-design-settings']['meta']['mcp']['public'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.4 — permission callbacks deny without the capability (§3.0/§4).
		 * ------------------------------------------------------------------
		 */

		public function test_a_user_without_edit_theme_options_is_denied_on_every_ability(): void {
			Functions\when( 'current_user_can' )->justReturn( false );

			$this->build()->register_abilities();

			foreach ( \StyleManagerAbilitiesRegistry::$abilities as $name => $args ) {
				$verdict = ( $args['permission_callback'] )();

				$this->assertInstanceOf( \WP_Error::class, $verdict, $name );
				$this->assertSame( 'permission_denied', $verdict->get_error_code() );
				$this->assertStringContainsString( 'edit_theme_options', $verdict->get_error_message() );
			}
		}

		public function test_the_capability_is_exactly_the_one_the_cli_requires(): void {
			// §4: an ability may never be more permissive than its command.
			$this->assertSame( CliCommands::CAPABILITY, AgentCommands::CAPABILITY );

			$this->build()->register_abilities();

			$this->assertTrue( ( \StyleManagerAbilitiesRegistry::$abilities['pixelgrade/flush-design-cache']['permission_callback'] )() );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.5 — execute parity: the ability and the command share one core.
		 * ------------------------------------------------------------------
		 */

		public function test_get_design_settings_produces_exactly_what_the_command_does(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn(
				[
					'sm_font_sizing' => 'smaller',
					'sm_font_body'   => [ 'font_family' => 'Lato' ],
				]
			);

			$core = $this->core( $headless );

			$ability = $this->execute( $core, 'pixelgrade/get-design-settings', [ 'ids' => [ 'sm_font_sizing' ] ] );
			$command = $this->run_command( $core, $headless, static fn( CliCommands $c ) => $c->get( [ 'sm_font_sizing' ], [ 'format' => 'json' ] ) );

			$this->assertSame( 0, $command['exit'] );
			$this->assertTrue( $ability['ok'] );
			$this->assertSame( $command['envelope']['code'], $ability['code'] );
			$this->assertSame( $command['envelope']['summary'], $ability['summary'] );
			$this->assertSame( $command['envelope']['data'], $ability['data'] );
		}

		public function test_get_design_settings_fails_the_whole_call_on_an_unknown_id_like_the_command(): void {
			// F6: no partial read, on either surface.
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'smaller' ] );

			$core    = $this->core( $headless );
			$ability = $this->execute( $core, 'pixelgrade/get-design-settings', [ 'ids' => [ 'sm_font_sizing', 'sm_nope' ] ] );
			$command = $this->run_command( $core, $headless, static fn( CliCommands $c ) => $c->get( [ 'sm_font_sizing', 'sm_nope' ], [ 'format' => 'json' ] ) );

			$this->assertInstanceOf( \WP_Error::class, $ability );
			$this->assertSame( 'invalid_params', $ability->get_error_code() );
			$this->assertSame( [ 'sm_nope' ], $ability->get_error_data()['data']['unknown'] );

			$this->assertSame( 1, $command['exit'] );
			$this->assertSame( 'invalid_params', $command['envelope']['code'] );
			$this->assertSame( $command['envelope']['summary'], $ability->get_error_message() );
		}

		public function test_apply_font_palette_produces_exactly_what_the_command_does(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes->method( 'get_palettes_for_control' )->willReturn( [ 'julia' => [ 'label' => 'Julia' ] ] );

			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [ 'sm_font_palette' ],
					'skipped'          => [],
					'stripped'         => [],
					'connected_fields' => [ 'anima_options[body_font]' ],
					'persisted'        => [ 'sm_font_palette' => 'julia' ],
					'unchanged'        => [],
				]
			);

			$core = $this->core( null, $writer, $font_palettes );

			$ability = $this->execute(
				$core,
				'pixelgrade/apply-font-palette',
				[
					'palette_id' => 'julia',
					'confirm'    => true,
				]
			);
			$command = $this->run_command(
				$core,
				null,
				static fn( CliCommands $c ) => $c->apply_font_palette( [ 'julia' ], [ 'format' => 'json', 'yes' => true ] ),
				$writer,
				$font_palettes
			);

			$this->assertSame( 0, $command['exit'] );
			$this->assertSame( $command['envelope']['data'], $ability['data'] );
			$this->assertSame( $command['envelope']['persisted'], $ability['persisted'] );
			$this->assertSame( [ 'anima_options[body_font]' ], $ability['data']['connected_fields'] );
		}

		public function test_a_tier_locked_palette_completes_with_findings_rather_than_failing(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes->method( 'get_palettes_for_control' )->willReturn( [ 'pro-one' => [] ] );

			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [],
					'skipped'          => [],
					'stripped'         => [
						[
							'id'        => 'sm_font_palette',
							'reason'    => SettingsWriter::REASON_TIER_LOCKED_PALETTE,
							'requested' => 'pro-one',
							'current'   => 'julia',
						],
					],
					'connected_fields' => [],
					'persisted'        => [],
					'unchanged'        => [],
				]
			);

			$result = $this->execute(
				$this->core( null, $writer, $font_palettes ),
				'pixelgrade/apply-font-palette',
				[
					'palette_id' => 'pro-one',
					'confirm'    => true,
				]
			);

			// Exit 2 is ok:true — the machinery completed, the finding is in `stripped`.
			$this->assertTrue( $result['ok'] );
			$this->assertSame( 'plus_stripped', $result['code'] );
			$this->assertSame( 'tier_locked_palette', $result['stripped'][0]['reason'] );
			$this->assertSame( 'plus_stripped', $result['warnings'][0]['code'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.6 — the §3.4 ordering law; §3.6's confirmation rule.
		 * ------------------------------------------------------------------
		 */

		public function test_set_design_settings_rejects_an_ordering_conflict(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );
			$writer->method( 'find_ordering_conflict' )->willReturn(
				[
					'master_slots'       => [ 'sm_font_primary' ],
					'per_element_fields' => [ 'anima_options[body_font]' ],
				]
			);

			$result = $this->execute(
				$this->core( null, $writer ),
				'pixelgrade/set-design-settings',
				[
					'settings' => [
						'sm_font_primary'          => [ 'font_family' => 'Lato' ],
						'anima_options[body_font]' => [ 'font_family' => 'Lato' ],
					],
					'confirm'  => true,
				]
			);

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'ordering_conflict', $result->get_error_code() );
			$this->assertSame( [ 'sm_font_primary' ], $result->get_error_data()['data']['master_slots'] );
			$this->assertStringContainsString( 'two steps', $result->get_error_message() );
		}

		public function test_a_master_font_slot_payload_needs_confirmation(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			$result = $this->execute(
				$this->core( null, $writer ),
				'pixelgrade/set-design-settings',
				[ 'settings' => [ 'sm_font_body' => [ 'font_family' => 'Lato' ] ] ]
			);

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'confirmation_required', $result->get_error_code() );
			$this->assertStringContainsString( 'confirm: true', $result->get_error_message() );
		}

		public function test_a_dry_run_never_needs_confirmation_and_never_writes(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );
			$writer->expects( $this->once() )->method( 'preview' )->willReturn(
				[
					'saved'     => [ 'sm_font_body' ],
					'stripped'  => [],
					'persisted' => [ 'sm_font_body' => [ 'font_family' => 'Lato' ] ],
					'unchanged' => [],
				]
			);

			$result = $this->execute(
				$this->core( null, $writer ),
				'pixelgrade/set-design-settings',
				[
					'settings' => [ 'sm_font_body' => [ 'font_family' => 'Lato' ] ],
					'dry_run'  => true,
				]
			);

			$this->assertTrue( $result['ok'] );
			$this->assertTrue( $result['data']['dry_run'] );
		}

		public function test_a_non_slot_payload_needs_no_confirmation(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'     => [ 'sm_font_sizing' ],
					'stripped'  => [],
					'persisted' => [ 'sm_font_sizing' => 'smaller' ],
					'unchanged' => [],
				]
			);

			$result = $this->execute(
				$this->core( null, $writer ),
				'pixelgrade/set-design-settings',
				[ 'settings' => [ 'sm_font_sizing' => 'smaller' ] ]
			);

			$this->assertTrue( $result['ok'] );
			$this->assertSame( [ 'sm_font_sizing' => 'smaller' ], $result['persisted'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.7 — one ability call is exactly one save (§3.12).
		 * ------------------------------------------------------------------
		 */

		public function test_one_set_call_performs_exactly_one_save(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->once() )->method( 'save' )->willReturn(
				[
					'saved'     => [ 'sm_font_sizing', 'sm_color_grades_number' ],
					'stripped'  => [],
					'persisted' => [
						'sm_font_sizing'         => 'smaller',
						'sm_color_grades_number' => 12,
					],
					'unchanged' => [],
				]
			);

			$result = $this->execute(
				$this->core( null, $writer ),
				'pixelgrade/set-design-settings',
				[
					'settings' => [
						'sm_font_sizing'         => 'smaller',
						'sm_color_grades_number' => 12,
					],
				]
			);

			$this->assertTrue( $result['ok'] );
			// Typed input, not CLI string parsing (F12): the integer stays an integer.
			$this->assertSame( 12, $result['persisted']['sm_color_grades_number'] );
		}

		public function test_one_color_palette_call_performs_exactly_one_save(): void {
			$captured = null;

			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->once() )->method( 'save' )->willReturnCallback(
				function ( array $values ) use ( &$captured ): array {
					$captured = $values;

					return [
						'saved'     => array_keys( $values ),
						'stripped'  => [],
						'persisted' => $values,
						'unchanged' => [],
					];
				}
			);

			$result = $this->execute(
				$this->core( null, $writer, null, $this->available_palette_generator() ),
				'pixelgrade/apply-color-palette',
				[
					'source'  => [
						[
							'uid'     => 'color_group_1',
							'sources' => [
								[
									'uid'   => 'color_11',
									'label' => 'MOLD Burgundy',
									'value' => '#722F37',
								],
							],
						],
					],
					'confirm' => true,
				]
			);

			$this->assertTrue( $result['ok'] );
			$this->assertSame(
				[ 'sm_advanced_palette_source', 'sm_advanced_palette_output', 'sm_is_custom_color_palette' ],
				array_keys( (array) $captured ),
				'F-W5-1: the Plus-gated variation must not ride along unasked, or the gate strips the output too.'
			);
			// F-W5-3: an arbitrary source IS a custom palette; there is no flag to forget.
			$this->assertSame( 1, $captured['sm_is_custom_color_palette'] );
			// No filesystem for an MCP client: the generated palettes come back inline.
			$this->assertIsArray( $result['data']['output'] );
		}

		public function test_the_variation_rides_along_only_when_supplied(): void {
			$captured = null;

			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturnCallback(
				function ( array $values ) use ( &$captured ): array {
					$captured = $values;

					return [
						'saved'     => array_keys( $values ),
						'stripped'  => [],
						'persisted' => $values,
						'unchanged' => [],
					];
				}
			);

			$this->execute(
				$this->core( null, $writer, null, $this->available_palette_generator() ),
				'pixelgrade/apply-color-palette',
				[
					'source'    => [ [ 'uid' => 'g', 'sources' => [ [ 'uid' => 'c', 'label' => 'B', 'value' => '#722F37' ] ] ] ],
					'variation' => 8,
					'confirm'   => true,
				]
			);

			$this->assertSame( 8, $captured['sm_site_color_variation'] );
		}

		public function test_generator_none_without_an_output_is_invalid_params(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			$result = $this->execute(
				$this->core( null, $writer, null, $this->available_palette_generator() ),
				'pixelgrade/apply-color-palette',
				[
					'source'    => [ [ 'uid' => 'g', 'sources' => [ [ 'uid' => 'c', 'label' => 'B', 'value' => '#722F37' ] ] ] ],
					'generator' => 'none',
					'confirm'   => true,
				]
			);

			// F-W5-2: "regenerate but don't generate, and don't say with what" has no success story.
			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'invalid_params', $result->get_error_code() );
			$this->assertStringContainsString( 'Nothing was written', $result->get_error_message() );
		}

		public function test_generator_none_applies_an_inline_output_verbatim(): void {
			$fixtures = __DIR__ . '/../../fixtures/palette-parity';
			$expected = (string) file_get_contents( $fixtures . '/footer-grist.applied-output.json' );

			$captured = null;
			$writer   = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->once() )->method( 'save' )->willReturnCallback(
				function ( array $values ) use ( &$captured ): array {
					$captured = $values;

					return [
						'saved'     => array_keys( $values ),
						'stripped'  => [],
						'persisted' => $values,
						'unchanged' => [],
					];
				}
			);

			// Node is never consulted on this path.
			$generator = $this->createMock( PaletteGenerator::class );
			$generator->expects( $this->never() )->method( 'generate' );
			$generator->expects( $this->never() )->method( 'is_available' );
			$generator->method( 'current_value' )->willReturn( null );

			$result = $this->execute(
				$this->core( null, $writer, null, $generator ),
				'pixelgrade/apply-color-palette',
				[
					'source'    => json_decode( (string) file_get_contents( $fixtures . '/footer-grist.source.json' ), true ),
					'output'    => json_decode( $expected, true ),
					'generator' => 'none',
					'confirm'   => true,
				]
			);

			$this->assertTrue( $result['ok'] );
			$this->assertTrue( $result['data']['verbatim'] );
			$this->assertSame(
				json_decode( $expected, true ),
				json_decode( (string) $captured['sm_advanced_palette_output'], true )
			);
		}

		public function test_a_missing_generator_fails_without_writing_a_stale_ramp(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			$generator = $this->createMock( PaletteGenerator::class );
			$generator->method( 'is_available' )->willReturn( false );
			$generator->method( 'looked_for' )->willReturn( [ '/plugin/dist/node/palette-generator.js', 'node' ] );

			$result = $this->execute(
				$this->core( null, $writer, null, $generator ),
				'pixelgrade/apply-color-palette',
				[
					'source'  => [ [ 'uid' => 'g', 'sources' => [ [ 'uid' => 'c', 'label' => 'B', 'value' => '#722F37' ] ] ] ],
					'confirm' => true,
				]
			);

			$this->assertInstanceOf( \WP_Error::class, $result );
			$this->assertSame( 'generator_unavailable', $result->get_error_code() );
			$this->assertNotEmpty( $result->get_error_data()['data']['looked_for'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * The remaining reads.
		 * ------------------------------------------------------------------
		 */

		public function test_export_returns_the_payload_inline_and_never_writes_a_file(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn(
				[
					'sm_font_sizing' => 'normal',
					'blogname'       => 'A site',
				]
			);
			$headless->method( 'get_sm_section_ids' )->willReturn( [] );
			$headless->method( 'get_section_setting_ids' )->willReturn( [] );

			Functions\when( 'wp_get_theme' )->justReturn( new FakeAbilitiesTheme() );

			$result = $this->execute( $this->core( $headless ), 'pixelgrade/export-design-system', [] );

			$this->assertTrue( $result['ok'] );
			$this->assertSame( 'style_manager', $result['data']['scope'] );
			$this->assertSame( [ 'sm_font_sizing' ], array_keys( $result['data']['settings'] ) );
			$this->assertArrayNotHasKey( 'file', $result['data'], 'An MCP client has no filesystem on the server.' );
		}

		public function test_structure_drops_the_control_markup_by_default(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_structure' )->willReturn(
				[
					'panels'   => [],
					'sections' => [
						[
							'id'       => 'sm_font_palettes_section',
							'controls' => [
								[
									'id'   => 'sm_font_palette',
									'type' => 'font_palette',
									'html' => '<div>heavy</div>',
								],
							],
						],
					],
				]
			);

			$core = $this->core( $headless );

			$without = $this->execute( $core, 'pixelgrade/get-design-structure', [] );
			$with    = $this->execute( $core, 'pixelgrade/get-design-structure', [ 'with_html' => true ] );

			$this->assertArrayNotHasKey( 'html', $without['data']['sections'][0]['controls'][0] );
			$this->assertArrayHasKey( 'html', $with['data']['sections'][0]['controls'][0] );
		}

		public function test_get_design_system_wraps_the_rest_payload_builder(): void {
			// The endpoint is final, so this is the real object — which is the point: the
			// ability must serve the SAME payload the REST GET does, not a copy of it.
			$endpoint = new DesignSystemPreviewEndpoint(
				$this->createMock( Options::class ),
				$this->createMock( Fonts::class ),
				static fn(): array => [ 'palettes' => [] ]
			);

			$result   = $this->execute( $this->core( null, null, null, null, $endpoint ), 'pixelgrade/get-design-system', [] );
			$expected = $endpoint->get_payload();

			$this->assertTrue( $result['ok'] );
			$this->assertSame( $expected, $result['data'] );
			$this->assertNotEmpty( $result['data']['revision'] );
			$this->assertStringContainsString( (string) $result['data']['revision'], $result['summary'] );
		}

		public function test_flush_design_cache_invalidates_the_caches(): void {
			$options = $this->createMock( Options::class );
			$options->expects( $this->once() )->method( 'invalidate_all_caches' );

			$result = $this->execute( $this->core( null, null, null, null, null, $options ), 'pixelgrade/flush-design-cache', [] );

			$this->assertTrue( $result['ok'] );
			$this->assertSame( 'ok', $result['code'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §10.8 — the entitlement seam. No shipped ability declares one (§4:
		 * the gated set is deliberately empty), so the mechanism is proven on a
		 * descriptor that does.
		 * ------------------------------------------------------------------
		 */

		public function test_no_shipped_ability_declares_an_entitlement(): void {
			foreach ( $this->build()->descriptors() as $descriptor ) {
				$this->assertArrayNotHasKey(
					'entitlement',
					$descriptor,
					'§4: no ability is Plus-gated today — gating happens inside the write, as stripping.'
				);
			}
		}

		public function test_a_denied_entitlement_keeps_the_ability_out_of_the_registry(): void {
			Filters\expectApplied( 'pixelgrade/has_entitlement' )
				->atLeast()
				->once()
				->andReturn( false );

			( new EntitledAbilities( $this->core() ) )->register_abilities();

			$this->assertSame( [], \StyleManagerAbilitiesRegistry::$abilities );
		}

		public function test_an_entitlement_lost_after_registration_is_denied_in_the_permission_callback(): void {
			// Registration happens at init; entitlement state can change afterwards.
			Filters\expectApplied( 'pixelgrade/has_entitlement' )
				->atLeast()
				->once()
				->andReturnValues( [ true, false ] );

			( new EntitledAbilities( $this->core() ) )->register_abilities();

			$this->assertArrayHasKey( 'pixelgrade/test-entitled', \StyleManagerAbilitiesRegistry::$abilities );

			$verdict = ( \StyleManagerAbilitiesRegistry::$abilities['pixelgrade/test-entitled']['permission_callback'] )();

			$this->assertInstanceOf( \WP_Error::class, $verdict );
			$this->assertSame( 'permission_denied', $verdict->get_error_code() );
			$this->assertStringContainsString( 'plus', $verdict->get_error_message() );
		}

		/*
		 * ------------------------------------------------------------------
		 * Helpers.
		 * ------------------------------------------------------------------
		 */

		private function build( ?AgentCommands $core = null ): Abilities {
			return new Abilities( $core ?: $this->core() );
		}

		private function core(
			?HeadlessCustomizer $headless = null,
			?SettingsWriter $writer = null,
			?FontPalettes $font_palettes = null,
			?PaletteGenerator $palette_generator = null,
			?DesignSystemPreviewEndpoint $endpoint = null,
			?Options $options = null
		): AgentCommands {
			return new AgentCommands(
				$options ?: $this->createMock( Options::class ),
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$writer ?: $this->createMock( SettingsWriter::class ),
				$font_palettes ?: $this->createMock( FontPalettes::class ),
				$palette_generator ?: $this->createMock( PaletteGenerator::class ),
				$endpoint
			);
		}

		/**
		 * Register the abilities against the fake registry and run one of them.
		 *
		 * @return array|\WP_Error
		 */
		private function execute( AgentCommands $core, string $name, array $input ) {
			\StyleManagerAbilitiesRegistry::reset();
			( new Abilities( $core ) )->register_abilities();

			$this->assertArrayHasKey( $name, \StyleManagerAbilitiesRegistry::$abilities );

			return ( \StyleManagerAbilitiesRegistry::$abilities[ $name ]['execute_callback'] )( $input );
		}

		/**
		 * Run the equivalent WP-CLI command against the SAME core object.
		 *
		 * @return array{exit:int, envelope:array}
		 */
		private function run_command(
			AgentCommands $core,
			?HeadlessCustomizer $headless,
			callable $command,
			?SettingsWriter $writer = null,
			?FontPalettes $font_palettes = null
		): array {
			\WP_CLI::$lines = [];

			$commands = new CliCommands(
				$this->createMock( Options::class ),
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$writer ?: $this->createMock( SettingsWriter::class ),
				$font_palettes ?: $this->createMock( FontPalettes::class ),
				$this->createMock( PaletteGenerator::class ),
				$core
			);

			$exit = 0;
			try {
				$command( $commands );
			} catch ( \StyleManagerCliHalt $halt ) {
				$exit = (int) $halt->getCode();
			}

			return [
				'exit'     => $exit,
				'envelope' => (array) json_decode( (string) end( \WP_CLI::$lines ), true ),
			];
		}

		private function available_palette_generator(): PaletteGenerator {
			$palettes = [
				[
					'id'             => 1,
					'label'          => 'Brand',
					'sourceIndex'    => 4,
					'source'         => [ '#722F37' ],
					'options'        => [ 'mode' => 'lch' ],
					'colors'         => array_fill( 0, 12, '#722F37' ),
					'variations'     => array_fill( 0, 12, [ 'bg' => '#ffffff' ] ),
					'darkVariations' => array_fill( 0, 12, [ 'bg' => '#000000' ] ),
				],
			];

			$generator = $this->createMock( PaletteGenerator::class );
			$generator->method( 'is_available' )->willReturn( true );
			$generator->method( 'resolve_options' )->willReturnCallback(
				static fn( array $overrides = [] ): array => array_merge( [ 'sm_color_grades_number' => 12 ], $overrides )
			);
			$generator->method( 'current_value' )->willReturn( null );
			$generator->method( 'generate' )->willReturn(
				[
					'json'     => (string) json_encode( $palettes ),
					'palettes' => $palettes,
				]
			);

			return $generator;
		}
	}

	/**
	 * Stands in for WP_Theme in the export stamp — the Unit suite never loads WordPress.
	 */
	class FakeAbilitiesTheme {
		public function get( string $header ) {
			return 'Version' === $header ? '2.0.49' : '';
		}
	}

	/**
	 * Proves the §4 entitlement seam on a descriptor that declares one. Nothing Style
	 * Manager ships does — that is the point of the "gated set is empty" test above.
	 */
	class EntitledAbilities extends Abilities {
		public function descriptors(): array {
			return [
				[
					'name'             => 'pixelgrade/test-entitled',
					'label'            => 'Test entitled',
					'description'      => 'Only registered when the entitlement is held.',
					'annotations'      => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
					'input_schema'     => $this->empty_input_schema(),
					'output_schema'    => $this->envelope_schema( $this->permissive_object_schema() ),
					'execute_callback' => static fn(): array => [],
					'entitlement'      => 'plus',
				],
			];
		}
	}
}

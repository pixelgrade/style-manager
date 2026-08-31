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
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\FontPalettes;
	use Pixelgrade\StyleManager\Provider\CliCommands;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Provider\Options;
	use Pixelgrade\StyleManager\Provider\SettingsWriter;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	class CliCommandsTest extends TestCase {

		public function setUp(): void {
			parent::setUp();

			\WP_CLI::$commands         = [];
			\WP_CLI::$success_messages = [];
			\WP_CLI::$warnings         = [];
			\WP_CLI::$logs             = [];
			\WP_CLI::$lines            = [];

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
		}

		/*
		 * ------------------------------------------------------------------
		 * Registration (contract §1: the `pixelgrade sm` subtree + the alias).
		 * ------------------------------------------------------------------
		 */

		public function test_register_hooks_registers_the_pixelgrade_sm_subtree(): void {
			$commands = $this->create_commands();

			$commands->register_hooks();

			$this->assertSame(
				[
					'pixelgrade sm get',
					'pixelgrade sm set',
					'pixelgrade sm export',
					'pixelgrade sm structure',
					'pixelgrade sm apply-font-palette',
					'pixelgrade sm apply-color-palette',
					'pixelgrade sm flush-cache',
					'style-manager flush-cache',
				],
				array_keys( \WP_CLI::$commands )
			);

			$this->assertSame( [ $commands, 'flush_cache' ], \WP_CLI::$commands['pixelgrade sm flush-cache'] );
			$this->assertSame( [ $commands, 'flush_cache_deprecated' ], \WP_CLI::$commands['style-manager flush-cache'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.0 — resolve the user first; never auto-elevate.
		 * ------------------------------------------------------------------
		 */

		public function test_no_resolved_user_exits_three_naming_the_capability_and_the_fix(): void {
			Functions\when( 'get_current_user_id' )->justReturn( 0 );

			[ $exit, $envelope ] = $this->invoke( fn( CliCommands $c ) => $c->get( [], [ '--all' => true, 'all' => true, 'format' => 'json' ] ) );

			$this->assertSame( 3, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'permission_denied', $envelope['code'] );
			$this->assertStringContainsString( 'edit_theme_options', $envelope['summary'] );
			$this->assertStringContainsString( '--user=<admin>', $envelope['summary'] );
		}

		public function test_user_without_the_capability_exits_three(): void {
			Functions\when( 'current_user_can' )->justReturn( false );

			[ $exit, $envelope ] = $this->invoke( fn( CliCommands $c ) => $c->flush_cache( [], [ 'format' => 'json' ] ) );

			$this->assertSame( 3, $exit );
			$this->assertSame( 'permission_denied', $envelope['code'] );
			$this->assertStringContainsString( 'edit_theme_options', $envelope['summary'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §2 — the envelope.
		 * ------------------------------------------------------------------
		 */

		public function test_json_format_emits_only_the_envelope_on_stdout(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'smaller' ] );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->get( [], [ 'all' => true, 'format' => 'json' ] ),
				$headless
			);

			$this->assertCount( 1, \WP_CLI::$lines );
			$this->assertSame( 0, $exit );
			$this->assertTrue( $envelope['ok'] );
			$this->assertSame( 'ok', $envelope['code'] );
			$this->assertSame( [], $envelope['warnings'] );
			$this->assertSame( [ 'sm_font_sizing' => 'smaller' ], $envelope['data']['settings'] );
			$this->assertArrayHasKey( 'summary', $envelope );
		}

		public function test_table_is_the_default_format(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'smaller' ] );

			$this->invoke( fn( CliCommands $c ) => $c->get( [], [ 'all' => true ] ), $headless );

			$this->assertSame( [], \WP_CLI::$lines );
			$this->assertNotEmpty( \WP_CLI::$success_messages );
		}

		public function test_get_rejects_unknown_setting_ids(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'smaller' ] );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->get( [ 'sm_nope' ], [ 'format' => 'json' ] ),
				$headless
			);

			$this->assertSame( 1, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'invalid_params', $envelope['code'] );
			$this->assertSame( [ 'sm_nope' ], $envelope['data']['unknown'] );
		}

		public function test_get_without_ids_all_or_section_is_invalid(): void {
			[ $exit, $envelope ] = $this->invoke( fn( CliCommands $c ) => $c->get( [], [ 'format' => 'json' ] ) );

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.4 — ordering conflict + letter-spacing units.
		 * ------------------------------------------------------------------
		 */

		public function test_master_slot_and_connected_field_in_one_write_is_an_ordering_conflict(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'sm_font_primary={"font_family":"Lato"}', 'anima_options[body_font]={"font_family":"Lato"}' ],
					[ 'format' => 'json', 'yes' => true ]
				)
			);

			$this->assertSame( 1, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'ordering_conflict', $envelope['code'] );
			$this->assertSame( [ 'sm_font_primary' ], $envelope['data']['master_slots'] );
			$this->assertSame( [ 'anima_options[body_font]' ], $envelope['data']['per_element_fields'] );
			$this->assertStringContainsString( 'two steps', $envelope['summary'] );
		}

		public function test_master_slot_alone_is_not_an_ordering_conflict(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn( $this->write_result( [ 'sm_font_primary' => [ 'font_family' => 'Lato' ] ] ) );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'sm_font_primary={"font_family":"Lato"}' ],
					[ 'format' => 'json', 'yes' => true ]
				),
				null,
				$writer
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'ok', $envelope['code'] );
		}

		public function test_letter_spacing_without_em_units_is_rejected(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'anima_options[body_font]={"font_family":"Lato","letter_spacing":{"value":0.02,"unit":false}}' ],
					[ 'format' => 'json' ]
				)
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
			$this->assertSame( [ 'anima_options[body_font]' ], $envelope['data']['invalid_letter_spacing'] );
		}

		public function test_letter_spacing_with_em_units_passes_validation(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn( $this->write_result( [ 'anima_options[body_font]' => [ 'font_family' => 'Lato' ] ] ) );

			[ $exit ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'anima_options[body_font]={"font_family":"Lato","letter_spacing":{"value":0.02,"unit":"em"}}' ],
					[ 'format' => 'json' ]
				),
				null,
				$writer
			);

			$this->assertSame( 0, $exit );
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.5/§2 — read-back diff, stripping forces exit 2, idempotence.
		 * ------------------------------------------------------------------
		 */

		public function test_stripped_settings_surface_as_a_warning_and_force_exit_two(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [],
					'skipped'          => [],
					'stripped'         => [
						[
							'id'        => 'sm_color_grades_number',
							'reason'    => SettingsWriter::REASON_PLUS_LOCKED,
							'requested' => 8,
							'current'   => 12,
						],
					],
					'connected_fields' => [],
					'persisted'        => [],
					'unchanged'        => [],
				]
			);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_color_grades_number=8' ], [ 'format' => 'json' ] ),
				null,
				$writer
			);

			$this->assertSame( 2, $exit );
			$this->assertTrue( $envelope['ok'], 'ok is bound to the exit code: exit 2 is ok:true.' );
			$this->assertSame( 'plus_stripped', $envelope['code'] );
			$this->assertSame( 'plus_stripped', $envelope['warnings'][0]['code'] );
			$this->assertSame( [ 'sm_color_grades_number' ], $envelope['warnings'][0]['ids'] );
			$this->assertSame( 'plus_locked', $envelope['stripped'][0]['reason'] );
			$this->assertSame( [], (array) $envelope['persisted'] );
		}

		public function test_an_identical_write_reports_noop_and_exits_zero(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [ 'sm_font_sizing' ],
					'skipped'          => [],
					'stripped'         => [],
					'connected_fields' => [],
					'persisted'        => [ 'sm_font_sizing' => 'smaller' ],
					'unchanged'        => [ 'sm_font_sizing' ],
				]
			);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_sizing=smaller' ], [ 'format' => 'json' ] ),
				null,
				$writer
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'noop', $envelope['code'] );
			$this->assertSame( [ 'sm_font_sizing' ], $envelope['unchanged'] );
			$this->assertSame( [ 'sm_font_sizing' => 'smaller' ], (array) $envelope['persisted'] );
		}

		public function test_a_real_write_reports_the_persisted_diff(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer
				->expects( $this->once() )
				->method( 'save' )
				->with( [ 'sm_font_sizing' => 'smaller' ], true )
				->willReturn( $this->write_result( [ 'sm_font_sizing' => 'smaller' ] ) );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_sizing=smaller' ], [ 'format' => 'json' ] ),
				null,
				$writer
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'ok', $envelope['code'] );
			$this->assertSame( [ 'sm_font_sizing' => 'smaller' ], (array) $envelope['persisted'] );
			$this->assertSame( [], $envelope['unchanged'] );
			$this->assertSame( [], $envelope['stripped'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.6 — --yes / --dry-run.
		 * ------------------------------------------------------------------
		 */

		public function test_a_master_slot_write_without_yes_is_refused_in_a_non_interactive_context(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [ 'format' => 'json' ] ),
				null,
				$writer
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
			$this->assertStringContainsString( '--yes', $envelope['summary'] );
		}

		public function test_dry_run_never_writes_and_never_needs_yes(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );
			$writer
				->expects( $this->once() )
				->method( 'preview' )
				->willReturn(
					[
						'saved'            => [ 'sm_font_body' ],
						'skipped'          => [],
						'stripped'         => [],
						'connected_fields' => [],
						'persisted'        => [ 'sm_font_body' => [ 'font_family' => 'Lato' ] ],
						'unchanged'        => [],
						'dry_run'          => true,
					]
				);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [ 'format' => 'json', 'dry-run' => true ] ),
				null,
				$writer
			);

			$this->assertSame( 0, $exit );
			$this->assertTrue( $envelope['data']['dry_run'] );
			$this->assertStringContainsString( 'Nothing was written', $envelope['summary'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * apply-font-palette + apply-color-palette.
		 * ------------------------------------------------------------------
		 */

		public function test_apply_font_palette_reports_the_connected_fields_the_fan_out_rewrote(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes->method( 'get_palettes_for_control' )->willReturn( [ 'julia' => [ 'label' => 'Julia' ] ] );

			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [ 'sm_font_palette' ],
					'skipped'          => [],
					'stripped'         => [],
					'connected_fields' => [ 'anima_options[body_font]', 'anima_options[heading_1_font]' ],
					'persisted'        => [ 'sm_font_palette' => 'julia' ],
					'unchanged'        => [],
				]
			);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_font_palette( [ 'julia' ], [ 'format' => 'json', 'yes' => true ] ),
				null,
				$writer,
				$font_palettes
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'julia', $envelope['data']['palette'] );
			$this->assertSame(
				[ 'anima_options[body_font]', 'anima_options[heading_1_font]' ],
				$envelope['data']['connected_fields']
			);
		}

		public function test_apply_font_palette_rejects_an_unknown_palette(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes->method( 'get_palettes_for_control' )->willReturn( [ 'julia' => [] ] );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_font_palette( [ 'nope' ], [ 'format' => 'json', 'yes' => true ] ),
				null,
				null,
				$font_palettes
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
		}

		public function test_apply_font_palette_surfaces_a_tier_locked_palette_as_exit_two(): void {
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

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_font_palette( [ 'pro-one' ], [ 'format' => 'json', 'yes' => true ] ),
				null,
				$writer,
				$font_palettes
			);

			$this->assertSame( 2, $exit );
			$this->assertTrue( $envelope['ok'] );
			$this->assertSame( 'tier_locked_palette', $envelope['stripped'][0]['reason'] );
		}

		public function test_apply_color_palette_fails_gracefully_while_the_generator_is_missing(): void {
			Functions\when( 'apply_filters' )->returnArg( 2 );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => '[]' ] )
			);

			$this->assertSame( 1, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'generator_unavailable', $envelope['code'] );
			$this->assertNotEmpty( $envelope['data']['looked_for'] );
			$this->assertStringContainsString( 'Nothing was written', $envelope['summary'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * flush-cache retrofit + deprecated alias.
		 * ------------------------------------------------------------------
		 */

		public function test_flush_cache_invalidates_all_caches_and_reports_the_envelope(): void {
			$options = $this->createMock( Options::class );
			$options->expects( $this->once() )->method( 'invalidate_all_caches' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->flush_cache( [], [ 'format' => 'json' ] ),
				null,
				null,
				null,
				$options
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'ok', $envelope['code'] );
			$this->assertSame(
				'Style Manager caches flushed (Customizer config, option details, opt-name).',
				$envelope['summary']
			);
		}

		public function test_flush_cache_keeps_its_shipped_success_line_in_table_mode(): void {
			$this->invoke( fn( CliCommands $c ) => $c->flush_cache( [], [] ) );

			$this->assertSame(
				[ 'Style Manager caches flushed (Customizer config, option details, opt-name).' ],
				\WP_CLI::$success_messages
			);
		}

		public function test_the_deprecated_alias_still_works_and_warns_on_stderr(): void {
			$options = $this->createMock( Options::class );
			$options->expects( $this->once() )->method( 'invalidate_all_caches' );

			[ $exit ] = $this->invoke(
				fn( CliCommands $c ) => $c->flush_cache_deprecated( [], [ 'format' => 'json' ] ),
				null,
				null,
				null,
				$options
			);

			$this->assertSame( 0, $exit );
			$this->assertStringContainsString( 'deprecated', \WP_CLI::$warnings[0] );
			$this->assertCount( 1, \WP_CLI::$lines, 'The deprecation notice must not pollute the JSON on STDOUT.' );
		}

		/*
		 * ------------------------------------------------------------------
		 * Helpers.
		 * ------------------------------------------------------------------
		 */

		/**
		 * Run a command and capture its exit code and (JSON) envelope.
		 *
		 * @return array{0:int,1:array}
		 */
		private function invoke(
			callable $command,
			?HeadlessCustomizer $headless = null,
			?SettingsWriter $writer = null,
			?FontPalettes $font_palettes = null,
			?Options $options = null
		): array {
			$commands = $this->create_commands( $headless, $writer, $font_palettes, $options );

			$exit = 0;
			try {
				$command( $commands );
			} catch ( \StyleManagerCliHalt $halt ) {
				$exit = (int) $halt->getCode();
			}

			$envelope = [];
			if ( ! empty( \WP_CLI::$lines ) ) {
				$envelope = (array) json_decode( (string) end( \WP_CLI::$lines ), true );
			}

			return [ $exit, $envelope ];
		}

		private function create_commands(
			?HeadlessCustomizer $headless = null,
			?SettingsWriter $writer = null,
			?FontPalettes $font_palettes = null,
			?Options $options = null
		): CliCommands {
			return new TestCliCommands(
				$options ?: $this->createMock( Options::class ),
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$writer ?: $this->createMock( SettingsWriter::class ),
				$font_palettes ?: $this->createMock( FontPalettes::class )
			);
		}

		private function write_result( array $persisted ): array {
			return [
				'saved'            => array_keys( $persisted ),
				'skipped'          => [],
				'stripped'         => [],
				'connected_fields' => [],
				'persisted'        => $persisted,
				'unchanged'        => [],
			];
		}
	}

	/**
	 * Pins the non-interactive branch of §3.6 so the suite never depends on whether
	 * the runner happens to have a TTY on STDIN.
	 */
	class TestCliCommands extends CliCommands {
		protected function is_interactive(): bool {
			return false;
		}
	}
}

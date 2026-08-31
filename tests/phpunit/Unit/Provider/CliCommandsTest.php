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
	use Pixelgrade\StyleManager\Provider\PaletteGenerator;
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
			Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );
			Functions\when( 'wp_get_theme' )->justReturn( new FakeTheme() );
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

			[ $exit, $envelope ] = $this->invoke( fn( CliCommands $c ) => $c->set( [ 'sm_font_sizing=smaller' ], [ 'format' => 'json' ] ) );

			$this->assertSame( 3, $exit );
			$this->assertSame( 'permission_denied', $envelope['code'] );
			$this->assertStringContainsString( 'edit_theme_options', $envelope['summary'] );
		}

		public function test_flush_cache_is_exempt_from_the_user_rule(): void {
			// Contract v0.3.3 §3.0: flush-cache keeps its historic no-user behavior; it is
			// a shipped command and cache invalidation discloses nothing.
			Functions\when( 'get_current_user_id' )->justReturn( 0 );

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
		}

		public function test_flush_cache_still_denies_a_resolved_user_lacking_the_capability(): void {
			Functions\when( 'current_user_can' )->justReturn( false );

			$options = $this->createMock( Options::class );
			$options->expects( $this->never() )->method( 'invalidate_all_caches' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->flush_cache( [], [ 'format' => 'json' ] ),
				null,
				null,
				null,
				$options
			);

			$this->assertSame( 3, $exit );
			$this->assertSame( 'permission_denied', $envelope['code'] );
		}

		public function test_the_deprecated_alias_is_exempt_from_the_user_rule_too(): void {
			Functions\when( 'get_current_user_id' )->justReturn( 0 );

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
		 * §1.1 v0.3.3 — export scope.
		 * ------------------------------------------------------------------
		 */

		public function test_export_defaults_to_the_style_manager_surface(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->export( [], [ 'format' => 'json' ] ),
				$this->headless_with_a_mixed_surface()
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'style_manager', $envelope['data']['scope'] );
			$this->assertSame(
				[ 'sm_font_sizing', 'anima_options[body_font]' ],
				array_keys( $envelope['data']['settings'] ),
				'Core Customizer settings must not ride along in a design-system export.'
			);
		}

		public function test_export_all_returns_the_full_customizer_map(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->export( [], [ 'format' => 'json', 'all' => true ] ),
				$this->headless_with_a_mixed_surface()
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 'all', $envelope['data']['scope'] );
			$this->assertArrayHasKey( 'blogname', $envelope['data']['settings'] );
		}

		public function test_export_payload_written_to_file_carries_no_unpinned_keys(): void {
			$file = tempnam( sys_get_temp_dir(), 'w1export' );

			$this->invoke(
				fn( CliCommands $c ) => $c->export( [], [ 'format' => 'json', 'file' => $file ] ),
				$this->headless_with_a_mixed_surface()
			);

			$written = json_decode( (string) file_get_contents( $file ), true );
			@unlink( $file );

			$this->assertSame( [ 'meta', 'settings' ], array_keys( $written ) );
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.4 — ordering conflict.
		 * ------------------------------------------------------------------
		 */

		public function test_master_slot_and_connected_field_in_one_write_is_an_ordering_conflict(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );
			$writer
				->method( 'find_ordering_conflict' )
				->willReturn(
					[
						'master_slots'       => [ 'sm_font_primary' ],
						'per_element_fields' => [ 'anima_options[body_font]' ],
					]
				);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'sm_font_primary={"font_family":"Lato"}', 'anima_options[body_font]={"font_family":"Lato"}' ],
					[ 'format' => 'json', 'yes' => true ]
				),
				null,
				$writer
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

		public function test_an_invalid_value_strip_is_reported_as_exit_two_not_a_hard_failure(): void {
			// The rule lives in SettingsWriter: the CLI no longer pre-rejects, it reports the
			// writer's invalid_value strip as exit 2. (v0.3.6: only a non-numeric VALUE gets
			// here — a bad unit is normalized, never stripped.)
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturn(
				[
					'saved'            => [],
					'skipped'          => [],
					'stripped'         => [
						[
							'id'        => 'anima_options[body_font]',
							'reason'    => SettingsWriter::REASON_INVALID_VALUE,
							'requested' => [ 'font_family' => 'Lato' ],
							'current'   => null,
						],
					],
					'connected_fields' => [],
					'persisted'        => [],
					'unchanged'        => [],
				]
			);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set(
					[ 'anima_options[body_font]={"font_family":"Lato","letter_spacing":{"value":"wide","unit":false}}' ],
					[ 'format' => 'json' ]
				),
				null,
				$writer
			);

			$this->assertSame( 2, $exit );
			$this->assertTrue( $envelope['ok'] );
			$this->assertSame( 'invalid_value', $envelope['stripped'][0]['reason'] );
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

		public function test_json_format_strictly_requires_yes_even_on_an_interactive_terminal(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			// Interactive terminal — but the binding is the FORMAT, not the TTY: a prompt
			// here would corrupt the envelope-only STDOUT guarantee.
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [ 'format' => 'json' ] ),
				null,
				$writer,
				null,
				null,
				true,
				true
			);

			$this->assertSame( 1, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'confirmation_required', $envelope['code'] );
			$this->assertStringContainsString( '--yes', $envelope['summary'] );
			$this->assertCount( 1, \WP_CLI::$lines, 'STDOUT must carry the envelope and nothing else.' );
		}

		public function test_yaml_format_strictly_requires_yes_too(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [ 'format' => 'yaml' ] ),
				null,
				$writer,
				null,
				null,
				true,
				true
			);

			$this->assertSame( 1, $exit );
		}

		public function test_table_format_may_prompt_and_proceeds_when_confirmed(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer
				->expects( $this->once() )
				->method( 'save' )
				->willReturn( $this->write_result( [ 'sm_font_body' => [ 'font_family' => 'Lato' ] ] ) );

			[ $exit ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [] ),
				null,
				$writer,
				null,
				null,
				true,
				true
			);

			$this->assertSame( 0, $exit );
		}

		public function test_a_declined_table_confirm_never_exits_zero_silently(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [ 'format' => 'json' ] ),
				null,
				$writer,
				null,
				null,
				true,
				false
			);

			$this->assertSame( 1, $exit, 'A refused destructive operation must never report success.' );
		}

		public function test_table_format_without_a_terminal_still_requires_yes(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->set( [ 'sm_font_body={"font_family":"Lato"}' ], [] ),
				null,
				$writer
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( [], \WP_CLI::$lines );
			$this->assertNotEmpty( \WP_CLI::$warnings );
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

		/*
		 * ------------------------------------------------------------------
		 * apply-color-palette (contract §1.1, §3.11, §3.12).
		 * ------------------------------------------------------------------
		 */

		private const SOURCE = '[{"uid":"color_group_1","sources":[{"uid":"color_11","label":"MOLD Burgundy","value":"#722F37"}]}]';

		public function test_apply_color_palette_fails_gracefully_when_the_runtime_is_absent(): void {
			// §3.11: a missing shim or binary is never fatal and never writes a stale output.
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE ] ),
				null,
				$writer
			);

			$this->assertSame( 1, $exit );
			$this->assertFalse( $envelope['ok'] );
			$this->assertSame( 'generator_unavailable', $envelope['code'] );
			$this->assertNotEmpty( $envelope['data']['looked_for'] );
			$this->assertStringContainsString( 'Nothing was written', $envelope['summary'] );
		}

		public function test_apply_color_palette_requires_a_source(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true ] )
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
			$this->assertStringContainsString( '--source', $envelope['summary'] );
		}

		public function test_apply_color_palette_rejects_a_malformed_source_before_probing_anything(): void {
			$generator = $this->createMock( PaletteGenerator::class );
			$generator->expects( $this->never() )->method( 'generate' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => '[]' ] ),
				null,
				null,
				null,
				null,
				false,
				false,
				$generator
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
		}

		public function test_apply_color_palette_batches_every_setting_into_one_save(): void {
			// §3.12: the Customizer manager holds ONE changeset uuid, so a verb that writes
			// settings and then writes more settings is invalid by construction.
			$captured = null;

			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->once() )
				->method( 'save' )
				->willReturnCallback(
					function ( array $values ) use ( &$captured ) {
						$captured = $values;

						return $this->write_result( $values );
					}
				);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE, 'custom' => true ] ),
				null,
				$writer,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertSame( 0, $exit );
			$this->assertTrue( $envelope['ok'] );
			$this->assertSame(
				[ 'sm_advanced_palette_source', 'sm_advanced_palette_output', 'sm_is_custom_color_palette' ],
				array_keys( (array) $captured )
			);
			$this->assertTrue( $captured['sm_is_custom_color_palette'] );
		}

		public function test_apply_color_palette_only_writes_the_plus_gated_variation_when_asked(): void {
			// sm_site_color_variation is a Fine-tune (Plus-gated) setting, and the save gate
			// drops sm_advanced_palette_output whenever a premium id rides along — so sending
			// it unconditionally would make the command strip its own output on a free site.
			$captured = null;

			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturnCallback(
				function ( array $values ) use ( &$captured ) {
					$captured = $values;

					return $this->write_result( $values );
				}
			);

			$this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE, 'variation' => '8' ] ),
				null,
				$writer,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertArrayHasKey( 'sm_site_color_variation', (array) $captured );
			$this->assertSame( 8, $captured['sm_site_color_variation'] );
		}

		public function test_apply_color_palette_rejects_an_out_of_range_variation(): void {
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE, 'variation' => '99' ] ),
				null,
				null,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'invalid_params', $envelope['code'] );
		}

		public function test_apply_color_palette_reports_the_produced_grade_count(): void {
			// laws #9: promoting brand colors can leave 11 grades where the option says 12,
			// so data.grades is counted off the produced ramp, never echoed from the option.
			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE ] ),
				null,
				$this->passthrough_writer(),
				null,
				null,
				false,
				false,
				$this->available_palette_generator( 11 )
			);

			$this->assertSame( 0, $exit );
			$this->assertSame( 11, $envelope['data']['grades'] );
			$this->assertSame( 'node', $envelope['data']['generator'] );
		}

		public function test_apply_color_palette_dry_run_writes_nothing_and_reports_the_output_diff(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );
			$writer->method( 'preview' )->willReturn(
				[
					'saved'     => [ 'sm_advanced_palette_output' ],
					'stripped'  => [],
					'persisted' => [ 'sm_advanced_palette_output' => '[]' ],
					'unchanged' => [],
				]
			);

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'source' => self::SOURCE, 'dry-run' => true ] ),
				null,
				$writer,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertSame( 0, $exit );
			$this->assertTrue( $envelope['data']['dry_run'] );
			$this->assertTrue( $envelope['data']['diff']['changed'] );
			// A hand-authored blob (no `options` key) is what the operator must see before
			// committing — regeneration would silently replace it.
			$this->assertFalse( $envelope['data']['diff']['stored_generator_produced'] );
			$this->assertStringContainsString( 'Nothing was written', $envelope['summary'] );
		}

		public function test_apply_color_palette_requires_yes_under_json(): void {
			// §3.6: destructive, and a prompt would corrupt the machine contract.
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'source' => self::SOURCE ] ),
				null,
				$writer,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'confirmation_required', $envelope['code'] );
		}

		public function test_apply_color_palette_with_generator_none_refuses_rather_than_writing_a_stale_output(): void {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->expects( $this->never() )->method( 'save' );

			[ $exit, $envelope ] = $this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE, 'generator' => 'none' ] ),
				null,
				$writer,
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertSame( 1, $exit );
			$this->assertSame( 'generator_unavailable', $envelope['code'] );
			$this->assertStringContainsString( 'Nothing was written', $envelope['summary'] );
		}

		public function test_apply_color_palette_can_write_the_generated_output_to_a_file(): void {
			$path = tempnam( sys_get_temp_dir(), 'sm-palette-' );

			$this->invoke(
				fn( CliCommands $c ) => $c->apply_color_palette( [], [ 'format' => 'json', 'yes' => true, 'source' => self::SOURCE, 'output' => '@' . $path ] ),
				null,
				$this->passthrough_writer(),
				null,
				null,
				false,
				false,
				$this->available_palette_generator()
			);

			$this->assertJson( (string) file_get_contents( $path ) );
			unlink( $path );
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
			?Options $options = null,
			bool $interactive = false,
			bool $prompt_answer = false,
			?PaletteGenerator $palette_generator = null
		): array {
			$commands = $this->create_commands( $headless, $writer, $font_palettes, $options, $palette_generator );
			$commands->interactive   = $interactive;
			$commands->prompt_answer = $prompt_answer;

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
			?Options $options = null,
			?PaletteGenerator $palette_generator = null
		): TestCliCommands {
			return new TestCliCommands(
				$options ?: $this->createMock( Options::class ),
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$writer ?: $this->createMock( SettingsWriter::class ),
				$font_palettes ?: $this->createMock( FontPalettes::class ),
				$palette_generator ?: $this->unavailable_palette_generator()
			);
		}

		/**
		 * A generator that reports itself absent — the default for every command test that
		 * is not specifically about apply-color-palette, so the suite never shells out to
		 * Node by accident.
		 */
		private function unavailable_palette_generator(): PaletteGenerator {
			$generator = $this->createMock( PaletteGenerator::class );
			$generator->method( 'is_available' )->willReturn( false );
			$generator->method( 'looked_for' )->willReturn( [ '/plugin/dist/node/palette-generator.js', 'node' ] );

			return $generator;
		}

		/**
		 * A generator that is present and returns a minimal but structurally valid output.
		 *
		 * @param int $grades How many ramp entries the brand palette should carry.
		 */
		private function available_palette_generator( int $grades = 12 ): PaletteGenerator {
			$palettes = [
				[
					'id'             => 1,
					'label'          => 'Brand',
					'sourceIndex'    => 4,
					'source'         => [ '#722F37' ],
					'options'        => [ 'mode' => 'lch' ],
					'colors'         => array_fill( 0, $grades, '#722F37' ),
					'variations'     => array_fill( 0, 12, [ 'bg' => '#ffffff' ] ),
					'darkVariations' => array_fill( 0, 12, [ 'bg' => '#000000' ] ),
				],
			];

			$generator = $this->createMock( PaletteGenerator::class );
			$generator->method( 'is_available' )->willReturn( true );
			$generator->method( 'resolve_options' )->willReturnCallback(
				static function ( array $overrides = [] ): array {
					return array_merge(
						[
							'sm_color_grades_number'   => 12,
							'sm_site_color_variation'  => 1,
							'sm_color_promotion_brand' => '',
						],
						$overrides
					);
				}
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

		private function headless_with_a_mixed_surface(): HeadlessCustomizer {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn(
				[
					'sm_font_sizing'           => 'normal',
					'anima_options[body_font]' => [ 'font_family' => 'Lato' ],
					'blogname'                 => 'A site',
					'blogdescription'          => 'A tagline',
				]
			);
			$headless->method( 'get_sm_section_ids' )->willReturn( [ 'anima_options[fonts_section]' ] );
			$headless->method( 'get_section_setting_ids' )->willReturn( [ 'anima_options[body_font]' ] );

			return $headless;
		}

		/**
		 * A writer that persists whatever it is handed — for the tests that are about the
		 * command's reporting rather than about the gate.
		 */
		private function passthrough_writer(): SettingsWriter {
			$writer = $this->createMock( SettingsWriter::class );
			$writer->method( 'save' )->willReturnCallback( fn( array $values ) => $this->write_result( $values ) );

			return $writer;
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
	 * Stands in for WP_Theme in the export stamp — the Unit suite never loads WordPress.
	 */
	class FakeTheme {
		public function get( string $header ) {
			return 'Version' === $header ? '2.0.49' : '';
		}
	}

	/**
	 * Pins the non-interactive branch of §3.6 so the suite never depends on whether
	 * the runner happens to have a TTY on STDIN.
	 */
	class TestCliCommands extends CliCommands {
		public bool $interactive = false;
		public bool $prompt_answer = false;
		public array $prompts = [];

		protected function is_interactive(): bool {
			return $this->interactive;
		}

		protected function prompt_for_confirmation( string $question ): bool {
			$this->prompts[] = $question;

			return $this->prompt_answer;
		}
	}
}

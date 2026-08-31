<?php
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

	use Brain\Monkey\Actions;
	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\FontPalettes;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Provider\SettingsWriter;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	class SettingsWriterTest extends TestCase {

		public function setUp(): void {
			parent::setUp();

			Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
			Functions\when( 'esc_html__' )->returnArg( 1 );
			Functions\when( '__' )->returnArg( 1 );
		}

		/*
		 * ------------------------------------------------------------------
		 * The gate, moved verbatim out of SiteEditorEndpoints.
		 * ------------------------------------------------------------------
		 */

		public function test_locked_plus_strips_trial_only_palette_output(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$this->assertSame(
				[],
				$this->create_writer()->strip_locked_premium_settings(
					[
						'sm_advanced_palette_output' => '[{"options":{"sm_color_grades_number":8}}]',
					]
				)
			);
		}

		public function test_locked_plus_keeps_free_palette_output_when_source_changed_without_premium_tuning(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$values = [
				'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
				'sm_advanced_palette_output' => '[{"source":["#123456"]}]',
			];

			$this->assertSame( $values, $this->create_writer()->strip_locked_premium_settings( $values ) );
		}

		public function test_locked_plus_strips_palette_output_when_premium_tuning_is_submitted(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$this->assertSame(
				[
					'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
				],
				$this->create_writer()->strip_locked_premium_settings(
					[
						'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
						'sm_color_grades_number'     => 8,
						'sm_advanced_palette_output' => '[{"source":["#123456"],"options":{"sm_color_grades_number":8}}]',
					]
				)
			);
		}

		public function test_unlocked_plus_keeps_palette_output_and_premium_tuning(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$values = [
				'sm_color_grades_number'     => 8,
				'sm_advanced_palette_output' => '[{"options":{"sm_color_grades_number":8}}]',
			];

			$this->assertSame( $values, $this->create_writer()->strip_locked_premium_settings( $values ) );
		}

		public function test_locked_plus_keeps_font_sizing_state_but_strips_its_client_derivatives(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$safe_baseline = [
				'version' => 1,
				'scales'  => [
					'sm_font_body' => [
						'interval' => [ 14, 24 ],
						'sizes'    => [ 'body_font' => 20 ],
					],
				],
			];

			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->once() )
				->method( 'prepare_locked_font_sizing_baseline' )
				->willReturn( $safe_baseline );

			$this->assertSame(
				[
					'sm_font_sizing'                                 => 'smaller',
					FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => $safe_baseline,
				],
				$this->create_writer( null, $font_palettes )->strip_locked_premium_settings(
					[
						'sm_font_sizing'                                 => 'smaller',
						FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => [
							'version' => 1,
							'scales'  => [ 'sm_font_body' => [ 'interval' => [ 1, 1000 ], 'sizes' => [ 'body_font' => 999 ] ] ],
						],
						'sm_font_body_pitch'                             => 45,
					]
				)
			);
		}

		public function test_locked_plus_rejects_font_sizing_when_no_server_baseline_can_be_prepared(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->once() )
				->method( 'prepare_locked_font_sizing_baseline' )
				->willReturn( [] );

			$this->assertSame(
				[],
				$this->create_writer( null, $font_palettes )->strip_locked_premium_settings(
					[
						'sm_font_sizing'                                 => 'smaller',
						FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => [
							'version' => 1,
							'scales'  => [ 'sm_font_body' => [ 'interval' => [ 1, 1000 ], 'sizes' => [ 'body_font' => 999 ] ] ],
						],
					]
				)
			);
		}

		public function test_tier_locked_font_palette_pointer_is_dropped(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->method( 'is_palette_tier_locked' )
				->with( 'pro-palette' )
				->willReturn( true );

			$this->assertSame(
				[],
				$this->create_writer( null, $font_palettes )->strip_locked_premium_font_palette(
					[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'pro-palette' ]
				)
			);
		}

		/*
		 * ------------------------------------------------------------------
		 * Post-save fan-out, moved verbatim (return type widened).
		 * ------------------------------------------------------------------
		 */

		public function test_saving_font_palette_applies_and_reports_connected_font_fields(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->once() )
				->method( 'apply_current_font_palette_to_connected_fields' )
				->willReturn( [ 'anima_options[body_font]', 'anima_options[heading_1_font]' ] );

			$this->assertSame(
				[ 'anima_options[body_font]', 'anima_options[heading_1_font]' ],
				$this->create_writer( null, $font_palettes )->apply_post_save_side_effects(
					[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY ]
				)
			);
		}

		public function test_locked_font_sizing_rebuilds_its_stripped_connected_outputs(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->once() )
				->method( 'apply_current_font_sizing_to_connected_fields' );

			$this->create_writer( null, $font_palettes )->apply_post_save_side_effects( [ 'sm_font_sizing' ] );
		}

		public function test_unlocked_font_sizing_keeps_the_client_saved_outputs(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->never() )
				->method( 'apply_current_font_sizing_to_connected_fields' );
			$font_palettes
				->expects( $this->once() )
				->method( 'trust_current_font_sizing_baseline' );

			$this->create_writer( null, $font_palettes )->apply_post_save_side_effects( [ 'sm_font_sizing' ] );
		}

		public function test_saving_other_settings_does_not_apply_font_palette_connected_fields(): void {
			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes
				->expects( $this->never() )
				->method( 'apply_current_font_palette_to_connected_fields' );

			$this->assertSame(
				[],
				$this->create_writer( null, $font_palettes )->apply_post_save_side_effects( [ 'sm_color_palette_in_use' ] )
			);
		}

		/*
		 * ------------------------------------------------------------------
		 * save(): short-circuit, reason vocabulary, the single settings_saved.
		 * ------------------------------------------------------------------
		 */

		public function test_save_fires_settings_saved_exactly_once(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->expects( $this->once() )
				->method( 'save' )
				->with( [ 'sm_page_transitions_enable' => true ] )
				->willReturn(
					[
						'saved'              => [ 'sm_page_transitions_enable' ],
						'skipped'            => [],
						'setting_validities' => [ 'sm_page_transitions_enable' => true ],
					]
				);

			Actions\expectDone( 'style_manager/settings_saved' )
				->once()
				->with( [ 'sm_page_transitions_enable' ] );

			$result = $this->create_writer( $headless )->save( [ 'sm_page_transitions_enable' => true ] );

			$this->assertSame( [ 'sm_page_transitions_enable' ], $result['saved'] );
			$this->assertSame( [], $result['stripped'] );
		}

		public function test_all_stripped_write_never_reaches_the_changeset_and_is_not_an_error(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->expects( $this->never() )->method( 'save' );

			Actions\expectDone( 'style_manager/settings_saved' )->never();

			$result = $this->create_writer( $headless )->save( [ 'sm_color_grades_number' => 8 ] );

			$this->assertIsArray( $result );
			$this->assertSame( [], $result['saved'] );
			$this->assertCount( 1, $result['stripped'] );
			$this->assertSame( 'sm_color_grades_number', $result['stripped'][0]['id'] );
			$this->assertSame( SettingsWriter::REASON_PLUS_LOCKED, $result['stripped'][0]['reason'] );
			$this->assertSame( 8, $result['stripped'][0]['requested'] );
		}

		public function test_tier_locked_palette_short_circuits_with_its_own_reason(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->expects( $this->never() )->method( 'save' );

			$font_palettes = $this->createMock( FontPalettes::class );
			$font_palettes->method( 'is_palette_tier_locked' )->willReturn( true );

			$result = $this->create_writer( $headless, $font_palettes )->save(
				[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'pro-palette' ]
			);

			$this->assertSame( [], $result['saved'] );
			$this->assertSame( SettingsWriter::REASON_TIER_LOCKED_PALETTE, $result['stripped'][0]['reason'] );
		}

		public function test_save_maps_skipped_and_invalid_ids_to_the_closed_reason_vocabulary(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'save' )
				->willReturn(
					[
						'saved'              => [ 'sm_font_sizing', 'sm_bogus_value' ],
						'skipped'            => [ 'sm_not_a_setting' ],
						'setting_validities' => [
							'sm_font_sizing'  => true,
							'sm_bogus_value'  => false,
						],
					]
				);

			$result = $this->create_writer( $headless )->save(
				[
					'sm_font_sizing'   => 'smaller',
					'sm_bogus_value'   => 'nope',
					'sm_not_a_setting' => 1,
				]
			);

			$reasons = array_combine( array_column( $result['stripped'], 'id' ), array_column( $result['stripped'], 'reason' ) );

			$this->assertSame(
				[
					'sm_not_a_setting' => SettingsWriter::REASON_UNKNOWN_SETTING,
					'sm_bogus_value'   => SettingsWriter::REASON_INVALID_VALUE,
				],
				$reasons
			);
		}

		/*
		 * ------------------------------------------------------------------
		 * Read-back diff (contract §3.5).
		 * ------------------------------------------------------------------
		 */

		public function test_read_back_diff_separates_persisted_from_unchanged(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'get_settings_values' )
				->willReturnOnConsecutiveCalls(
					[ 'sm_font_sizing' => 'default', 'sm_content_inset' => 10 ],
					[ 'sm_font_sizing' => 'smaller', 'sm_content_inset' => 10 ]
				);
			$headless
				->method( 'save' )
				->willReturn(
					[
						'saved'              => [ 'sm_font_sizing', 'sm_content_inset' ],
						'skipped'            => [],
						'setting_validities' => [],
					]
				);

			$result = $this->create_writer( $headless )->save(
				[
					'sm_font_sizing'   => 'smaller',
					'sm_content_inset' => 10,
				],
				true
			);

			$this->assertSame(
				[ 'sm_font_sizing' => 'smaller', 'sm_content_inset' => 10 ],
				$result['persisted']
			);
			$this->assertSame( [ 'sm_content_inset' ], $result['unchanged'] );
		}

		public function test_read_back_diff_ignores_numeric_type_drift(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'get_settings_values' )
				->willReturn( [ 'sm_color_grades_number' => '12' ] );
			$headless
				->method( 'save' )
				->willReturn( [ 'saved' => [ 'sm_color_grades_number' ], 'skipped' => [], 'setting_validities' => [] ] );

			$result = $this->create_writer( $headless )->save( [ 'sm_color_grades_number' => 12 ], true );

			$this->assertSame( [ 'sm_color_grades_number' ], $result['unchanged'] );
		}

		/*
		 * ------------------------------------------------------------------
		 * preview() — the --dry-run engine.
		 * ------------------------------------------------------------------
		 */

		public function test_preview_never_writes_and_flags_unknown_settings(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->expects( $this->never() )->method( 'save' );
			$headless
				->method( 'get_settings_values' )
				->willReturn( [ 'sm_font_sizing' => 'default' ] );

			$result = $this->create_writer( $headless )->preview(
				[
					'sm_font_sizing'   => 'smaller',
					'sm_not_a_setting' => 'x',
				]
			);

			$this->assertTrue( $result['dry_run'] );
			$this->assertSame( [ 'sm_font_sizing' => 'smaller' ], $result['persisted'] );
			$this->assertSame( [], $result['unchanged'] );
			$this->assertSame( 'sm_not_a_setting', $result['stripped'][0]['id'] );
			$this->assertSame( SettingsWriter::REASON_UNKNOWN_SETTING, $result['stripped'][0]['reason'] );
		}

		public function test_preview_reports_an_identical_write_as_unchanged(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'smaller' ] );

			$result = $this->create_writer( $headless )->preview( [ 'sm_font_sizing' => 'smaller' ] );

			$this->assertSame( [ 'sm_font_sizing' ], $result['unchanged'] );
			$this->assertSame( [], $result['stripped'] );
		}

		private function create_writer( ?HeadlessCustomizer $headless = null, ?FontPalettes $font_palettes = null ): SettingsWriter {
			return new SettingsWriter(
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$font_palettes ?: $this->createMock( FontPalettes::class )
			);
		}

		private function mock_plus_entitlement_bridge( bool $bridge_available, bool $entitled ): void {
			Functions\when( 'has_filter' )->alias( static function ( string $hook ) use ( $bridge_available ) {
				if ( 'pixelgrade/has_entitlement' === $hook ) {
					return $bridge_available ? 10 : false;
				}

				return false;
			} );

			Functions\when( 'apply_filters' )->alias( static function ( string $hook, $value, ...$args ) use ( $entitled ) {
				if ( 'pixelgrade/has_entitlement' === $hook ) {
					return $entitled;
				}

				return $value;
			} );
		}
	}
}

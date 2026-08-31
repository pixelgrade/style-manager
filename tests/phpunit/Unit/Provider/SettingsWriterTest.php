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

		/*
		 * ------------------------------------------------------------------
		 * H1 — persisted / unchanged / stripped are DISJOINT.
		 * ------------------------------------------------------------------
		 */

		public function test_a_mixed_write_keeps_persisted_unchanged_and_stripped_disjoint(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'get_settings_values' )
				->willReturnOnConsecutiveCalls(
					[
						'sm_page_transitions_enable' => false,
						'sm_content_inset'       => 10,
						'sm_color_grades_number' => 12,
					],
					[
						'sm_page_transitions_enable' => true,
						'sm_content_inset'       => 10,
						'sm_color_grades_number' => 12,
					]
				);
			$headless
				->method( 'save' )
				->willReturn(
					[
						'saved'              => [ 'sm_page_transitions_enable', 'sm_content_inset' ],
						'skipped'            => [],
						'setting_validities' => [],
					]
				);

			$requested = [
				'sm_page_transitions_enable' => true,
				'sm_content_inset'       => 10,
				// Premium on a locked site: the gate drops it, so it is stripped — and its
				// before == after is trivially true, which must NOT make it "unchanged".
				'sm_color_grades_number' => 8,
			];

			$result = $this->create_writer( $headless )->save( $requested, true );

			$stripped_ids  = array_column( $result['stripped'], 'id' );
			$persisted_ids = array_keys( $result['persisted'] );

			$this->assertSame( [ 'sm_color_grades_number' ], $stripped_ids );
			$this->assertSame( [ 'sm_page_transitions_enable', 'sm_content_inset' ], $persisted_ids );
			$this->assertSame( [ 'sm_content_inset' ], $result['unchanged'] );

			$this->assertSame( [], array_intersect( $stripped_ids, $result['unchanged'] ), 'stripped ∩ unchanged must be empty' );
			$this->assertSame( [], array_intersect( $stripped_ids, $persisted_ids ), 'stripped ∩ persisted must be empty' );
		}

		public function test_an_all_stripped_write_reports_nothing_as_unchanged(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->expects( $this->never() )->method( 'save' );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_color_grades_number' => 12 ] );

			$result = $this->create_writer( $headless )->save( [ 'sm_color_grades_number' => 8 ], true );

			$this->assertSame( [], $result['unchanged'] );
			$this->assertSame( [], $result['persisted'] );
			$this->assertCount( 1, $result['stripped'] );
		}

		public function test_an_invalid_value_is_never_also_reported_as_persisted(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( [ 'sm_font_sizing' => 'default', 'sm_bogus' => 'old' ] );
			$headless
				->method( 'save' )
				->willReturn(
					[
						// The changeset lists an id in `saved` even when its own validation
						// later reports the value invalid.
						'saved'              => [ 'sm_font_sizing', 'sm_bogus' ],
						'skipped'            => [],
						'setting_validities' => [ 'sm_font_sizing' => true, 'sm_bogus' => false ],
					]
				);

			$result = $this->create_writer( $headless )->save(
				[ 'sm_font_sizing' => 'smaller', 'sm_bogus' => 'nope' ],
				true
			);

			$this->assertArrayNotHasKey( 'sm_bogus', $result['persisted'] );
			$this->assertNotContains( 'sm_bogus', $result['unchanged'] );
			$this->assertSame( 'sm_bogus', $result['stripped'][0]['id'] );
			$this->assertSame( SettingsWriter::REASON_INVALID_VALUE, $result['stripped'][0]['reason'] );
		}

		public function test_preview_and_a_real_run_classify_a_mixed_payload_identically(): void {
			$this->mock_plus_entitlement_bridge( true, false );

			$stored = [
				'sm_page_transitions_enable' => false,
				'sm_content_inset'       => 10,
				'sm_color_grades_number' => 12,
			];

			$requested = [
				'sm_page_transitions_enable' => true,
				'sm_content_inset'       => 10,
				'sm_color_grades_number' => 8,
			];

			$preview_headless = $this->createMock( HeadlessCustomizer::class );
			$preview_headless->method( 'get_settings_values' )->willReturn( $stored );
			$preview = $this->create_writer( $preview_headless )->preview( $requested );

			$run_headless = $this->createMock( HeadlessCustomizer::class );
			$run_headless
				->method( 'get_settings_values' )
				->willReturnOnConsecutiveCalls( $stored, array_merge( $stored, [ 'sm_page_transitions_enable' => true ] ) );
			$run_headless
				->method( 'save' )
				->willReturn(
					[
						'saved'              => [ 'sm_page_transitions_enable', 'sm_content_inset' ],
						'skipped'            => [],
						'setting_validities' => [],
					]
				);
			$run = $this->create_writer( $run_headless )->save( $requested, true );

			$this->assertSame( array_keys( $preview['persisted'] ), array_keys( $run['persisted'] ) );
			$this->assertSame( $preview['unchanged'], $run['unchanged'] );
			$this->assertSame(
				array_column( $preview['stripped'], 'reason' ),
				array_column( $run['stripped'], 'reason' )
			);
		}

		/*
		 * ------------------------------------------------------------------
		 * §3.4 v0.3.3 — letter-spacing normalization + ordering-conflict policy.
		 * ------------------------------------------------------------------
		 */

		public function test_a_zero_unitless_letter_spacing_is_normalized_not_rejected(): void {
			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy(
				[
					'anima_options[body_font]' => [
						'font_family'    => 'PT Serif',
						'letter_spacing' => [ 'value' => 0, 'unit' => false ],
					],
				]
			);

			$this->assertSame( [], $stripped );
			$this->assertSame(
				[ 'value' => 0, 'unit' => 'em' ],
				$normalized['anima_options[body_font]']['letter_spacing']
			);
		}

		/**
		 * v0.3.6: unit normalization is UNCONDITIONAL. The P1-a grist fixture — real
		 * browser-authored state — carries nonzero unitless letter-spacing on 7 of its 17
		 * roles, so rejecting that shape made the reference fixture unreproducible.
		 *
		 * @dataProvider nonzero_unitless_letter_spacings
		 */
		public function test_a_nonzero_unitless_letter_spacing_is_normalized_not_stripped( $value ): void {
			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy(
				[
					'anima_options[super_display_font]' => [
						'font_family'    => 'DM Sans',
						'letter_spacing' => [ 'value' => $value, 'unit' => false ],
					],
				]
			);

			$this->assertSame( [], $stripped );
			$this->assertSame(
				[ 'value' => $value, 'unit' => 'em' ],
				$normalized['anima_options[super_display_font]']['letter_spacing']
			);
		}

		public static function nonzero_unitless_letter_spacings(): array {
			// Every distinct letter-spacing value the grist fixture actually carries.
			return [
				'super_display -0.04' => [ -0.04 ],
				'display -0.02'       => [ -0.02 ],
				'heading_2 -0.01'     => [ -0.01 ],
				'heading_5 0.02'      => [ 0.02 ],
			];
		}

		public function test_an_unrecognised_unit_is_normalized_to_em(): void {
			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy(
				[
					'anima_options[body_font]' => [
						'letter_spacing' => [ 'value' => 0.5, 'unit' => 'px' ],
					],
				]
			);

			$this->assertSame( [], $stripped );
			$this->assertSame( 'em', $normalized['anima_options[body_font]']['letter_spacing']['unit'] );
		}

		public function test_a_missing_unit_key_is_normalized_to_em(): void {
			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy(
				[
					'anima_options[body_font]' => [
						'letter_spacing' => [ 'value' => -0.03 ],
					],
				]
			);

			$this->assertSame( [], $stripped );
			$this->assertSame( [ 'value' => -0.03, 'unit' => 'em' ], $normalized['anima_options[body_font]']['letter_spacing'] );
		}

		public function test_a_non_numeric_letter_spacing_value_is_still_stripped_as_invalid_value(): void {
			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy(
				[
					'anima_options[body_font]' => [
						'letter_spacing' => [ 'value' => 'wide', 'unit' => false ],
					],
					'sm_font_sizing'           => 'smaller',
				]
			);

			$this->assertSame( [ 'sm_font_sizing' ], array_keys( $normalized ) );
			$this->assertCount( 1, $stripped );
			$this->assertSame( 'anima_options[body_font]', $stripped[0]['id'] );
			$this->assertSame( SettingsWriter::REASON_INVALID_VALUE, $stripped[0]['reason'] );
		}

		public function test_the_whole_grist_letter_spacing_table_survives_normalization(): void {
			// The exact shapes of P1-a's 17-role table: 10 zero-unitless, 7 nonzero-unitless.
			$roles = [
				'body_font' => 0, 'lead_font' => 0, 'small_body_font' => 0, 'heading_3_font' => 0,
				'heading_4_font' => 0, 'navigation_font' => 0, 'buttons_font' => 0, 'input_font' => 0,
				'meta_font' => 0,
				'super_display_font' => -0.04, 'display_font' => -0.02, 'heading_1_font' => -0.02,
				'heading_2_font' => -0.01, 'heading_5_font' => 0.02, 'heading_6_font' => 0.02,
				'site_title_font' => -0.02,
			];

			$values = [];
			foreach ( $roles as $role => $spacing ) {
				$values[ 'anima_options[' . $role . ']' ] = [
					'font_family'    => 'DM Sans',
					'letter_spacing' => [ 'value' => $spacing, 'unit' => false ],
				];
			}

			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy( $values );

			$this->assertSame( [], $stripped, 'No grist role may be stripped — they are all real browser output.' );
			$this->assertCount( count( $roles ), $normalized );
			foreach ( $normalized as $id => $value ) {
				$this->assertSame( 'em', $value['letter_spacing']['unit'], $id );
			}
		}

		public function test_a_valid_em_letter_spacing_passes_through_untouched(): void {
			$values = [
				'anima_options[body_font]' => [
					'letter_spacing' => [ 'value' => 0.02, 'unit' => 'em' ],
				],
			];

			[ $normalized, $stripped ] = $this->create_writer()->apply_letter_spacing_policy( $values );

			$this->assertSame( $values, $normalized );
			$this->assertSame( [], $stripped );
		}

		public function test_the_shipped_anima_default_round_trips_through_save(): void {
			$this->mock_plus_entitlement_bridge( true, true );

			$shipped = [
				'font_family'    => 'PT Serif',
				'font_size'      => [ 'value' => 16, 'unit' => false ],
				'letter_spacing' => [ 'value' => 0, 'unit' => false ],
			];

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->expects( $this->once() )
				->method( 'save' )
				->with(
					$this->callback(
						static function ( array $values ): bool {
							return 'em' === $values['anima_options[body_font]']['letter_spacing']['unit'];
						}
					)
				)
				->willReturn( [ 'saved' => [ 'anima_options[body_font]' ], 'skipped' => [], 'setting_validities' => [] ] );

			$result = $this->create_writer( $headless )->save( [ 'anima_options[body_font]' => $shipped ] );

			$this->assertSame( [], $result['stripped'] );
		}

		public function test_ordering_conflict_uses_the_derived_field_set_not_a_name_pattern(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'get_theme_font_target_setting_ids' )
				->willReturn( [ 'anima_options[body_font]', 'anima_options[headline_lines_spacings]' ] );

			// `headline_lines_spacings` is a real connected font field that a `_font]`
			// suffix regex would miss — the exact clobber §3.4 exists to prevent.
			$conflict = $this->create_writer( $headless )->find_ordering_conflict(
				[
					'sm_font_palette'                        => 'julia',
					'anima_options[headline_lines_spacings]' => 1.2,
				]
			);

			$this->assertNotNull( $conflict );
			$this->assertSame( [ 'sm_font_palette' ], $conflict['master_slots'] );
			$this->assertSame( [ 'anima_options[headline_lines_spacings]' ], $conflict['per_element_fields'] );
		}

		public function test_no_ordering_conflict_without_a_master_slot(): void {
			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_theme_font_target_setting_ids' )->willReturn( [ 'anima_options[body_font]' ] );

			$this->assertNull(
				$this->create_writer( $headless )->find_ordering_conflict( [ 'anima_options[body_font]' => [] ] )
			);
		}

		public function test_save_does_not_enforce_the_ordering_law_so_the_site_editor_keeps_working(): void {
			// The Site Editor PUTs its entire dirty set; auto-rejecting here would refuse a
			// legitimate editor save. The law is offered to callers, not enforced in save().
			$this->mock_plus_entitlement_bridge( true, true );

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_theme_font_target_setting_ids' )->willReturn( [ 'anima_options[body_font]' ] );
			$headless
				->expects( $this->once() )
				->method( 'save' )
				->willReturn( [ 'saved' => [ 'sm_font_palette', 'anima_options[body_font]' ], 'skipped' => [], 'setting_validities' => [] ] );

			$result = $this->create_writer( $headless )->save(
				[
					'sm_font_palette'          => 'julia',
					'anima_options[body_font]' => [ 'font_family' => 'Lato' ],
				]
			);

			$this->assertNotInstanceOf( \WP_Error::class, $result );
		}

		public function test_master_font_slots_in_is_shared_policy(): void {
			$this->assertSame(
				[ 'sm_font_body' ],
				SettingsWriter::master_font_slots_in( [ 'sm_font_body' => [], 'sm_font_sizing' => 'x' ] )
			);
			$this->assertSame( [], SettingsWriter::master_font_slots_in( [ 'sm_font_sizing' => 'x' ] ) );
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

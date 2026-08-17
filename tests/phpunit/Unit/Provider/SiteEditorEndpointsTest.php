<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
use Pixelgrade\StyleManager\Provider\SiteEditorEndpoints;
use Pixelgrade\StyleManager\Screen\EditWithBlocks;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class SiteEditorEndpointsTest extends TestCase {
	public function test_site_editor_endpoints_require_edit_theme_options_capability(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_theme_options' )
			->andReturn( false );

		$this->assertFalse( $this->create_endpoints()->check_permissions() );
	}

	public function test_locked_plus_strips_trial_only_palette_output(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$endpoints = $this->create_endpoints();

		$this->assertSame(
			[],
			$endpoints->expose_strip_locked_premium_settings(
				[
					'sm_advanced_palette_output' => '[{"options":{"sm_color_grades_number":8}}]',
				]
			)
		);
	}

	public function test_locked_plus_keeps_free_palette_output_when_source_changed_without_premium_tuning(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$endpoints = $this->create_endpoints();
		$values    = [
			'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
			'sm_advanced_palette_output' => '[{"source":["#123456"]}]',
		];

		$this->assertSame(
			$values,
			$endpoints->expose_strip_locked_premium_settings( $values )
		);
	}

	public function test_locked_plus_strips_palette_output_when_premium_tuning_is_submitted(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$endpoints = $this->create_endpoints();

		$this->assertSame(
			[
				'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
			],
			$endpoints->expose_strip_locked_premium_settings(
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

		$endpoints = $this->create_endpoints();
		$values    = [
			'sm_color_grades_number'     => 8,
			'sm_advanced_palette_output' => '[{"options":{"sm_color_grades_number":8}}]',
		];

		$this->assertSame(
			$values,
			$endpoints->expose_strip_locked_premium_settings( $values )
		);
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
		$submitted_baseline = [
			'version' => 1,
			'scales'  => [
				'sm_font_body' => [
					'interval' => [ 1, 1000 ],
					'sizes'    => [ 'body_font' => 999 ],
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
			$this->create_endpoints( $font_palettes )->expose_strip_locked_premium_settings(
				[
					'sm_font_sizing'                                 => 'smaller',
					FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => $submitted_baseline,
					'sm_font_body_pitch'                              => 45,
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
			$this->create_endpoints( $font_palettes )->expose_strip_locked_premium_settings(
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

	public function test_saving_font_palette_applies_connected_font_fields(): void {
		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->once() )
			->method( 'apply_current_font_palette_to_connected_fields' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY ]
		);
	}

	public function test_saving_font_sizing_rebuilds_its_stripped_connected_outputs(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->once() )
			->method( 'apply_current_font_sizing_to_connected_fields' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects( [ 'sm_font_sizing' ] );
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

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects( [ 'sm_font_sizing' ] );
	}

	public function test_unlocked_direct_pitch_save_trusts_the_saved_public_baseline(): void {
		$this->mock_plus_entitlement_bridge( true, true );

		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->once() )
			->method( 'trust_current_font_sizing_baseline' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects(
			[
				FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY,
				'sm_font_body_pitch',
			]
		);
	}

	public function test_saving_other_settings_does_not_apply_font_palette_connected_fields(): void {
		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->never() )
			->method( 'apply_current_font_palette_to_connected_fields' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects(
			[ 'sm_color_palette_in_use' ]
		);
	}

	private function create_endpoints( ?FontPalettes $font_palettes = null ): TestSiteEditorEndpoints {
		return new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes ?: $this->createMock( FontPalettes::class ),
			$this->createMock( FrontendOutput::class )
		);
	}

	private function mock_plus_entitlement_bridge( bool $bridge_available, bool $entitled ): void {
		Functions\when( 'has_filter' )->alias( static function( string $hook ) use ( $bridge_available ) {
			if ( 'pixelgrade/has_entitlement' === $hook ) {
				return $bridge_available ? 10 : false;
			}

			return false;
		} );

		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$args ) use ( $entitled ) {
			if ( 'pixelgrade/has_entitlement' === $hook ) {
				return $entitled;
			}

			return $value;
		} );
	}
}

class TestSiteEditorEndpoints extends SiteEditorEndpoints {
	public function expose_apply_post_save_side_effects( array $values ): void {
		$this->apply_post_save_side_effects( $values );
	}

	public function expose_strip_locked_premium_settings( array $values ): array {
		return $this->strip_locked_premium_settings( $values );
	}
}

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

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * The advanced Color System Fine-tune tab is ALWAYS registered, regardless of the Pixelgrade Plus
 * entitlement. Free (non-entitled) users get a fully interactive live trial — the controls move and
 * the preview recomputes — and the trial framing + the real save-strip gate live elsewhere
 * (Screen\SiteEditor::apply_plus_gating() + the server-side strip). This pins that the COLOR
 * Fine-tune section registration is no longer conditional on the entitlement, and that the premium
 * setting-id accessors return the persisted subsets (the single source of truth for the strip).
 *
 * The FONT Fine-tune section still uses the legacy remove-when-locked model; that is out of scope
 * here and asserted as-is so this test reflects current behavior.
 */
class FineTuneEntitlementGateTest extends TestCase {
	public function test_color_fine_tune_section_registers_when_plus_entitlement_bridge_is_absent(): void {
		$this->mock_plus_entitlement_bridge( false, false );

		$config = $this->apply_fine_tune_sections( $this->base_config() );

		$this->assertArrayHasKey(
			'sm_fine_tune_color_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
		$this->assertArrayHasKey(
			'sm_fine_tune_font_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
	}

	public function test_color_fine_tune_section_stays_registered_when_plus_bridge_denies_advanced_controls(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$config = $this->apply_fine_tune_sections( $this->base_config() );

		// COLOR Fine-tune is always registered: the editor presents it as an interactive live trial
		// with a gentle banner; saving the premium settings is what is gated (server-side strip).
		$this->assertArrayHasKey(
			'sm_fine_tune_color_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
		// FONT Fine-tune keeps the legacy remove-when-locked behavior (unchanged, out of scope).
		$this->assertArrayNotHasKey(
			'sm_fine_tune_font_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
	}

	public function test_fine_tune_sections_register_when_plus_bridge_grants_advanced_controls(): void {
		$this->mock_plus_entitlement_bridge( true, true );

		$config = $this->apply_fine_tune_sections( $this->base_config() );

		$this->assertArrayHasKey(
			'sm_fine_tune_color_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
		$this->assertArrayHasKey(
			'sm_fine_tune_font_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
	}

	public function test_premium_fine_tune_setting_ids_exclude_presentational_fields(): void {
		$ids = ColorPalettes::get_premium_setting_ids();

		$this->assertContains( 'sm_color_grades_number', $ids );
		$this->assertContains( 'sm_color_promotion_white', $ids );
		// Intros and separators are never persisted settings — they must not be in the strip set.
		$this->assertNotContains( 'sm_color_fine_tune_intro', $ids );
		foreach ( $ids as $id ) {
			$this->assertStringStartsNotWith( 'sm_separator_', $id );
			$this->assertStringNotContainsString( '_intro', $id );
		}
	}

	public function test_premium_usage_setting_ids_cover_the_whole_usage_tab(): void {
		$ids = ColorPalettes::get_usage_premium_setting_ids();

		// The persisted, premium SM-owned Usage settings (master switches, coloration, dark mode).
		$this->assertContains( 'sm_text_color_switch_master', $ids );
		$this->assertContains( 'sm_accent_color_switch_master', $ids );
		$this->assertContains( 'sm_coloration_level', $ids );
		$this->assertContains( 'sm_dark_mode_advanced', $ids );

		// Non-persisted proxies/affordances and presentational fields stay out of the strip set:
		// `sm_dark_mode` is a proxy replaced by `sm_dark_mode_advanced`; the colorize button is a
		// navigation affordance tracked separately; intros/separators are presentational.
		$this->assertNotContains( 'sm_dark_mode', $ids );
		$this->assertNotContains( 'sm_colorize_elements_button', $ids );
		$this->assertNotContains( 'sm_description_color_usage_intro', $ids );
		foreach ( $ids as $id ) {
			$this->assertStringStartsNotWith( 'sm_separator_', $id );
			$this->assertStringNotContainsString( '_intro', $id );
		}
	}

	private function mock_plus_entitlement_bridge( bool $bridge_available, bool $entitled ): void {
		Functions\when( 'esc_html__' )->alias( static function( string $text ): string {
			return $text;
		} );

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

	private function apply_fine_tune_sections( array $config ): array {
		$color_palettes = new ColorPalettes(
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class )
		);
		$font_palettes  = new FontPalettes(
			$this->createMock( Options::class ),
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class )
		);

		$config = PHPUnitUtil::getProtectedMethod( $color_palettes, 'add_fine_tune_palette_section' )
			->invoke( $color_palettes, $config );

		return PHPUnitUtil::getProtectedMethod( $font_palettes, 'add_fine_tune_palette_section' )
			->invoke( $font_palettes, $config );
	}

	private function base_config(): array {
		return [
			'panels' => [
				'style_manager_panel' => [
					'sections' => [
						'sm_color_palettes_section' => [
							'options' => [
								'sm_color_fine_tune_intro' => [
									'type' => 'html',
								],
							],
						],
						'sm_font_palettes_section' => [
							'options' => [
								'sm_fine_tune_intro' => [
									'type' => 'html',
								],
							],
						],
					],
				],
				'theme_options_panel' => [
					'sections' => [],
				],
			],
		];
	}
}

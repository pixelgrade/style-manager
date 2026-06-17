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

class FineTuneEntitlementGateTest extends TestCase {
	public function test_fine_tune_sections_stay_available_when_plus_entitlement_bridge_is_absent(): void {
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

	public function test_fine_tune_sections_lock_when_plus_bridge_denies_advanced_controls(): void {
		$this->mock_plus_entitlement_bridge( true, false );

		$config = $this->apply_fine_tune_sections( $this->base_config() );

		$this->assertArrayNotHasKey(
			'sm_fine_tune_color_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
		$this->assertArrayNotHasKey(
			'sm_fine_tune_font_palette_section',
			$config['panels']['theme_options_panel']['sections']
		);
	}

	public function test_fine_tune_sections_unlock_when_plus_bridge_grants_advanced_controls(): void {
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

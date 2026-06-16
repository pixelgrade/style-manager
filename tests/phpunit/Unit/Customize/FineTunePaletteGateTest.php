<?php
/**
 * Verifies that the Fine-tune palette sections are gated behind the Pixelgrade
 * Plus advanced-controls entitlement, read through the shared, absence-safe
 * `pixelgrade/has_entitlement` bridge filter.
 *
 * Style Manager owns the controls; Pixelgrade Plus owns the gate. When Plus is
 * absent / inactive / unlicensed nothing answers the filter and the locked
 * default (false) stands, so the advanced Fine-tune sections stay hidden.
 *
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FineTunePaletteGateTest extends TestCase {

	/**
	 * Fake the WordPress functions the gated methods touch, with the entitlement
	 * bridge answering a controllable boolean and every other filter passing the
	 * value through unchanged.
	 */
	private function fake_wp( bool $entitled ): void {
		Functions\when( 'esc_html__' )->alias( static fn( string $text, string $domain = 'default' ) => $text );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $hook, $value, ...$rest ) use ( $entitled ) {
				if ( 'pixelgrade/has_entitlement' === $hook ) {
					return $entitled;
				}

				return $value;
			}
		);
	}

	private function font_config_with_source(): array {
		return [
			'panels' => [
				'style_manager_panel' => [
					'sections' => [
						'sm_font_palettes_section' => [
							'options' => [
								'sm_fine_tune_intro'        => [ 'type' => 'html' ],
								'sm_font_primary'           => [ 'type' => 'font' ],
								'sm_font_primary_elevation' => [ 'type' => 'range' ],
							],
						],
					],
				],
			],
		];
	}

	private function color_config_with_source(): array {
		return [
			'panels' => [
				'style_manager_panel' => [
					'sections' => [
						'sm_color_palettes_section' => [
							'options' => [
								'sm_color_fine_tune_intro'   => [ 'type' => 'html' ],
								'sm_color_fine_tune_presets' => [ 'type' => 'preset' ],
								'sm_color_grades_number'     => [ 'type' => 'range' ],
							],
						],
					],
				],
			],
		];
	}

	private function call_protected( object $obj, array $config ): array {
		$ref = new \ReflectionMethod( $obj, 'add_fine_tune_palette_section' );
		$ref->setAccessible( true );

		return $ref->invoke( $obj, $config );
	}

	private function font_palettes(): FontPalettes {
		return new FontPalettes(
			$this->createMock( Options::class ),
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class )
		);
	}

	private function color_palettes(): ColorPalettes {
		return new ColorPalettes(
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class )
		);
	}

	public function test_font_fine_tune_section_hidden_when_not_entitled(): void {
		$this->fake_wp( false );

		$result = $this->call_protected( $this->font_palettes(), $this->font_config_with_source() );

		$this->assertArrayNotHasKey(
			'sm_fine_tune_font_palette_section',
			$result['panels']['theme_options_panel']['sections'] ?? [],
			'The font Fine-tune section must be hidden when the advanced-controls entitlement is locked.'
		);
	}

	public function test_font_fine_tune_section_shown_when_entitled(): void {
		$this->fake_wp( true );

		$result = $this->call_protected( $this->font_palettes(), $this->font_config_with_source() );

		$this->assertArrayHasKey(
			'sm_fine_tune_font_palette_section',
			$result['panels']['theme_options_panel']['sections'],
			'The font Fine-tune section must be available when entitled.'
		);
		$this->assertNotEmpty(
			$result['panels']['theme_options_panel']['sections']['sm_fine_tune_font_palette_section']['options']
		);
	}

	public function test_color_fine_tune_section_hidden_when_not_entitled(): void {
		$this->fake_wp( false );

		$result = $this->call_protected( $this->color_palettes(), $this->color_config_with_source() );

		$this->assertArrayNotHasKey(
			'sm_fine_tune_color_palette_section',
			$result['panels']['theme_options_panel']['sections'] ?? [],
			'The color Fine-tune section must be hidden when the advanced-controls entitlement is locked.'
		);
	}

	public function test_color_fine_tune_section_shown_when_entitled(): void {
		$this->fake_wp( true );

		$result = $this->call_protected( $this->color_palettes(), $this->color_config_with_source() );

		$this->assertArrayHasKey(
			'sm_fine_tune_color_palette_section',
			$result['panels']['theme_options_panel']['sections'],
			'The color Fine-tune section must be available when entitled.'
		);
		$this->assertNotEmpty(
			$result['panels']['theme_options_panel']['sections']['sm_fine_tune_color_palette_section']['options']
		);
	}
}

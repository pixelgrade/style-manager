<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FontPalettesMobileScaleTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		Functions\when( 'current_theme_supports' )->alias(
			static fn( string $feature ): bool => 'style_manager_font_palettes' === $feature
		);
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_kses_allowed_html' )->justReturn( [] );
	}

	public function test_phone_heading_scale_is_a_registered_css_owned_range(): void {
		$config  = $this->create_font_palettes()->expose_add_style_manager_section_master_fonts_config( [] );
		$control = $config['sections']['style_manager_section']['options']['sm_font_mobile_scale'];

		$this->assertSame( 'range', $control['type'] );
		$this->assertSame( 'option', $control['setting_type'] );
		$this->assertSame( 'sm_font_mobile_scale', $control['setting_id'] );
		$this->assertTrue( $control['live'] );
		// Unset until touched: the theme's own slope stays byte-identical.
		$this->assertSame( '', $control['default'] );
		$this->assertSame( 0, $control['input_attrs']['min'] );
		$this->assertSame( 100, $control['input_attrs']['max'] );
		$this->assertSame(
			[
				[
					'property'        => '--theme-font-size-slope-adjust',
					'selector'        => ':root',
					'unit'            => '',
					'callback_filter' => 'sm_font_mobile_scale_css_cb',
				],
			],
			$control['css']
		);
	}

	public function test_phone_heading_scale_follows_font_sizing_in_the_typography_section(): void {
		$palettes = $this->create_font_palettes();
		$config   = $palettes->expose_add_style_manager_section_master_fonts_config( [] );
		$panel    = $palettes->expose_reorganize_customizer_controls( [], $config['sections']['style_manager_section'] );
		$keys     = array_keys( $panel['sections']['sm_font_palettes_section']['options'] );

		$this->assertSame( 'sm_font_sizing', $keys[ array_search( 'sm_font_mobile_scale', $keys, true ) - 1 ] );
	}

	public function test_phone_heading_scale_is_not_plus_gated(): void {
		$this->assertNotContains( 'sm_font_mobile_scale', FontPalettes::get_premium_setting_ids() );
	}

	private function create_font_palettes(): TestMobileScaleFontPalettes {
		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets->method( 'get_entry' )->willReturn( [] );

		return new TestMobileScaleFontPalettes(
			$this->createMock( Options::class ),
			$design_assets,
			$this->createMock( LoggerInterface::class )
		);
	}
}

class TestMobileScaleFontPalettes extends FontPalettes {
	public function expose_add_style_manager_section_master_fonts_config( array $config ): array {
		return $this->add_style_manager_section_master_fonts_config( $config );
	}

	public function expose_reorganize_customizer_controls( array $panel_config, array $section_config ): array {
		return $this->reorganize_customizer_controls( $panel_config, $section_config );
	}
}

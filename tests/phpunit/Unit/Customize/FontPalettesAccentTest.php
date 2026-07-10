<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FontPalettesAccentTest extends TestCase {
	public function test_accent_font_fine_tune_control_is_available_with_expected_defaults(): void {
		Functions\when( 'current_theme_supports' )->alias(
			static fn( string $feature ): bool => 'style_manager_font_palettes' === $feature
		);
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_kses' )->returnArg( 1 );

		$config = $this->create_font_palettes()->expose_add_style_manager_section_master_fonts_config( [] );

		$accent = $config['sections']['style_manager_section']['options']['sm_font_accent'];

		$this->assertSame( 'font', $accent['type'] );
		$this->assertSame( 'option', $accent['setting_type'] );
		$this->assertSame( 'sm_font_accent', $accent['setting_id'] );
		$this->assertTrue( $accent['live'] );
		$this->assertSame( 'Montserrat', $accent['default']['font-family'] );
		$this->assertSame( 'uppercase', $accent['default']['text-transform'] );
		$this->assertSame(
			[
				'font-size',
				'font-weight',
				'line-height',
				'letter-spacing',
				'text-align',
				'text-transform',
				'text-decoration',
			],
			array_keys( array_filter( $accent['fields'], static fn( $enabled ): bool => false === $enabled ) )
		);
	}

	private function create_font_palettes(): TestFontPalettes {
		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets
			->method( 'get_entry' )
			->with( 'font_palettes' )
			->willReturn( [] );

		return new TestFontPalettes(
			$this->createMock( Options::class ),
			$design_assets,
			$this->createMock( LoggerInterface::class )
		);
	}
}

class TestFontPalettes extends FontPalettes {
	public function expose_add_style_manager_section_master_fonts_config( array $config ): array {
		return $this->add_style_manager_section_master_fonts_config( $config );
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FontPalettesServerApplyTest extends TestCase {
	public function test_get_palettes_uses_default_config_when_design_assets_have_no_font_palettes(): void {
		Functions\when( 'esc_html__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$args ) {
			return $value;
		} );
		Functions\when( 'wp_parse_args' )->alias( static function( $args, $defaults = [] ) {
			return array_merge( (array) $defaults, (array) $args );
		} );
		Functions\when( 'sanitize_title' )->alias( static function( string $text ): string {
			return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $text ) ) ?? '' );
		} );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( string $text ): string => strip_tags( $text ) );

		$design_assets = $this->createMock( DesignAssets::class );
		$design_assets
			->expects( $this->once() )
			->method( 'get_entry' )
			->with( 'font_palettes', true )
			->willReturn( null );

		$font_palettes = new FontPalettes(
			$this->createMock( Options::class ),
			$design_assets,
			$this->createMock( LoggerInterface::class )
		);

		$palettes = $font_palettes->get_palettes( true );

		$this->assertArrayHasKey( 'gema', $palettes );
		$this->assertSame( 'Gema', $palettes['gema']['label'] );
		$this->assertArrayHasKey( 'fonts_logic', $palettes['gema'] );
		$this->assertNotEmpty( $palettes['gema']['fonts_logic'] );
	}

	public function test_apply_current_font_palette_to_connected_fields_writes_theme_font_values(): void {
		$options = $this->createMock( Options::class );
		$options
			->method( 'get_options_key' )
			->willReturn( 'anima_options' );
		$options
			->method( 'get_details_all' )
			->willReturn( $this->font_details() );
		$options
			->expects( $this->atLeastOnce() )
			->method( 'invalidate_all_caches' );

		$selected_options = [
			FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'l4k864',
		];
		$updated_options  = [];
		$theme_mods       = [
			'anima_options' => [
				'body_font' => [
					'font_family' => 'System Sans-Serif Clear',
					'font_size'   => [ 'value' => 16, 'unit' => 'px' ],
				],
			],
		];

		Functions\when( 'get_option' )->alias( static function( string $option_name, $default = false ) use ( &$selected_options ) {
			return $selected_options[ $option_name ] ?? $default;
		} );
		Functions\when( 'update_option' )->alias( static function( string $option_name, $value ) use ( &$updated_options, &$selected_options ) {
			$updated_options[ $option_name ]  = $value;
			$selected_options[ $option_name ] = $value;

			return true;
		} );
		Functions\when( 'get_theme_mod' )->alias( static function( string $name, $default = false ) use ( &$theme_mods ) {
			return $theme_mods[ $name ] ?? $default;
		} );
		Functions\when( 'set_theme_mod' )->alias( static function( string $name, $value ) use ( &$theme_mods ) {
			$theme_mods[ $name ] = $value;
		} );

		$font_palettes = new TestServerApplyFontPalettes(
			$options,
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class ),
			[
				'l4k864' => [
					'fonts_logic' => [
						'sm_font_body'                 => [
							'font_family'                  => 'Reforma1969',
							'font_size_multiplier'         => 0.75,
							'font_styles_intervals'        => [
								[
									'start'        => 0,
									'font_variant' => 'regular',
								],
							],
							'font_size_to_line_height_points' => [
								[ 14, 1.4 ],
								[ 32, 1.1 ],
							],
						],
						'connected_fields_preset'      => 'preset-2',
					],
				],
			]
		);

		$applied = $font_palettes->apply_current_font_palette_to_connected_fields();

		$this->assertSame( [ 'body_font' ], $applied );
		$this->assertArrayNotHasKey( 'sm_fonts_connected_fields_preset', $updated_options );
		$this->assertSame( 'Reforma1969', $updated_options['sm_font_body']['font_family'] ?? null );
		$this->assertSame( 'Reforma1969', $theme_mods['anima_options']['body_font']['font_family'] ?? null );
		$this->assertEquals(
			16,
			$theme_mods['anima_options']['body_font']['font_size']['value'] ?? null,
			'Palette-authored multipliers must not compound into the user-owned typography scale.'
		);
		$this->assertNotSame(
			'System Sans-Serif Clear',
			$theme_mods['anima_options']['body_font']['font_family'] ?? null
		);
	}

	private function font_details(): array {
		$body_default = [
			'font_family' => 'System Sans-Serif Clear',
			'font_size'   => [ 'value' => 16, 'unit' => 'px' ],
			'line_height' => [ 'value' => 1.5, 'unit' => false ],
		];

		return [
			'sm_font_body'           => [
				'type'             => 'font',
				'value'            => [
					'font_family' => 'Reforma1969',
				],
				'connected_fields' => [ 'body_font' ],
			],
			'sm_font_body_elevation' => [
				'value'   => 24,
				'default' => 24,
			],
			'sm_font_body_pitch'     => [
				'value'   => 45,
				'default' => 45,
			],
			'body_font'              => [
				'type'    => 'font',
				'default' => $body_default,
				'value'   => $body_default,
			],
		];
	}
}

class TestServerApplyFontPalettes extends FontPalettes {
	private array $palettes;

	public function __construct( Options $options, DesignAssets $design_assets, LoggerInterface $logger, array $palettes ) {
		parent::__construct( $options, $design_assets, $logger );

		$this->palettes = $palettes;
	}

	public function is_supported(): bool {
		return true;
	}

	public function get_palettes( bool $skip_cache = false ): array {
		return $this->palettes;
	}
}

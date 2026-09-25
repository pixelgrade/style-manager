<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * #204: a font palette applies its own hierarchy (connected-fields) preset only while
 * the site's preset is not user-set; a user-set preset is kept.
 */
class FontPalettesHierarchyPresetTest extends TestCase {
	private const PRESET = FontPalettes::SM_FONTS_CONNECTED_FIELDS_PRESET_OPTION_KEY;
	private const SOURCE = FontPalettes::SM_FONTS_CONNECTED_FIELDS_PRESET_SOURCE_OPTION_KEY;

	private array $stored  = [];
	private array $updated = [];
	private array $theme_mods = [];

	public function test_an_explicit_source_decides_and_a_saved_preset_without_one_counts_as_the_users(): void {
		$this->assertTrue( FontPalettes::classify_connected_fields_preset_user_set( 'user', false ) );
		$this->assertFalse( FontPalettes::classify_connected_fields_preset_user_set( 'palette', true ) );
		$this->assertTrue( FontPalettes::classify_connected_fields_preset_user_set( '', true ), 'A legacy saved preset keeps rendering as today.' );
		$this->assertFalse( FontPalettes::classify_connected_fields_preset_user_set( '', false ), 'A never-saved preset follows the palette.' );
	}

	public function test_the_palette_preset_falls_back_to_the_default_and_ignores_unknown_presets(): void {
		$details = $this->preset_details();

		$this->assertSame( 'preset-2-5', FontPalettes::resolve_palette_connected_fields_preset( 'preset-2-5', $details ) );
		$this->assertSame( 'preset-2', FontPalettes::resolve_palette_connected_fields_preset( '', $details ), 'A palette without a hierarchy restores the default.' );
		$this->assertSame( '', FontPalettes::resolve_palette_connected_fields_preset( 'preset-9', $details ) );
		$this->assertSame( '', FontPalettes::resolve_palette_connected_fields_preset( '', [] ) );
	}

	public function test_only_a_non_default_known_preset_is_the_palettes_own_hierarchy(): void {
		$details = $this->preset_details();

		$this->assertTrue( FontPalettes::palette_carries_own_hierarchy( 'preset-2-5', $details ) );
		$this->assertFalse( FontPalettes::palette_carries_own_hierarchy( 'preset-2', $details ), 'The default hierarchy is not the palette\'s own.' );
		$this->assertFalse( FontPalettes::palette_carries_own_hierarchy( '', $details ) );
		$this->assertFalse( FontPalettes::palette_carries_own_hierarchy( 'preset-9', $details ) );
	}

	public function test_an_untouched_site_takes_the_palettes_hierarchy(): void {
		$this->apply_palette( [], 'preset-2-5' );

		$this->assertSame( 'preset-2-5', $this->updated[ self::PRESET ] ?? null );
		$this->assertSame( 'palette', $this->updated[ self::SOURCE ] ?? null );
	}

	public function test_a_user_set_preset_is_kept(): void {
		$this->apply_palette( [ self::PRESET => 'preset-1', self::SOURCE => 'user' ], 'preset-2-5' );

		$this->assertArrayNotHasKey( self::PRESET, $this->updated );
		$this->assertArrayNotHasKey( self::SOURCE, $this->updated );
		$this->assertSame( 'Hive Sans', $this->updated['sm_font_body']['font_family'] ?? null, 'The palette fonts still apply.' );
	}

	public function test_a_legacy_saved_preset_without_a_source_is_kept(): void {
		$this->apply_palette( [ self::PRESET => 'preset-3' ], 'preset-2-5' );

		$this->assertArrayNotHasKey( self::PRESET, $this->updated );
		$this->assertArrayNotHasKey( self::SOURCE, $this->updated );
	}

	public function test_a_palette_owned_preset_follows_the_next_palette_back_to_the_default(): void {
		$this->apply_palette( [ self::PRESET => 'preset-2-5', self::SOURCE => 'palette' ], '' );

		$this->assertSame( 'preset-2', $this->updated[ self::PRESET ] ?? null );
		$this->assertSame( 'palette', $this->updated[ self::SOURCE ] ?? null );
	}

	public function test_a_preset_reassigns_master_connected_fields_and_unlisted_masters_keep_theirs(): void {
		$details = [
			'sm_font_primary' => [ 'connected_fields' => [ 'heading_1_font' ] ],
			'sm_font_body'    => [ 'connected_fields' => [ 'body_font' ] ],
			self::PRESET      => [
				'choices' => [
					'preset-1' => [ 'config' => [ 'sm_font_primary' => [ 'heading_1_font', 'heading_5_font' ] ] ],
				],
			],
		];

		$applied = FontPalettes::apply_connected_fields_preset_to_details( $details, 'preset-1' );

		$this->assertSame( [ 'heading_1_font', 'heading_5_font' ], $applied['sm_font_primary']['connected_fields'] );
		$this->assertSame( [ 'body_font' ], $applied['sm_font_body']['connected_fields'] );
		$this->assertSame( $details, FontPalettes::apply_connected_fields_preset_to_details( $details, 'preset-9' ) );
	}

	public function test_the_fan_out_follows_the_kept_user_preset(): void {
		$this->apply_palette( [ self::PRESET => 'preset-1', self::SOURCE => 'user' ], 'preset-2-5' );

		$this->assertSame( 'Hive Sans', $this->theme_mods['anima_options']['heading_5_font']['font_family'] ?? null, 'preset-1 routes heading 5 through the body master in this fixture.' );
	}

	private function apply_palette( array $stored, string $declared_preset ): void {
		$this->stored  = $stored;
		$this->updated = [];

		Functions\when( 'get_option' )->alias( function ( string $name, $default = false ) {
			return array_key_exists( $name, $this->stored ) ? $this->stored[ $name ] : $default;
		} );
		Functions\when( 'update_option' )->alias( function ( string $name, $value ) {
			$this->updated[ $name ] = $value;
			$this->stored[ $name ]  = $value;

			return true;
		} );
		$this->theme_mods = [];
		Functions\when( 'get_theme_mod' )->alias( function ( string $name, $default = false ) {
			return $this->theme_mods[ $name ] ?? $default;
		} );
		Functions\when( 'set_theme_mod' )->alias( function ( string $name, $value ) {
			$this->theme_mods[ $name ] = $value;
		} );

		$options = $this->createMock( Options::class );
		$options->method( 'get_options_key' )->willReturn( 'anima_options' );
		$options->method( 'get_details_all' )->willReturn(
			[
				'sm_font_body'   => [
					'type'             => 'font',
					'value'            => [ 'font_family' => 'Hive Sans' ],
					'connected_fields' => [],
				],
				'heading_5_font' => [
					'type'    => 'font',
					'default' => [ 'font_family' => 'Theme Default', 'font_size' => [ 'value' => 18, 'unit' => 'px' ] ],
					'value'   => [ 'font_family' => 'Theme Default', 'font_size' => [ 'value' => 18, 'unit' => 'px' ] ],
				],
				self::PRESET     => $this->preset_details(),
			]
		);

		$fonts_logic = [
			'sm_font_body' => [
				'font_family'           => 'Hive Sans',
				'font_styles_intervals' => [ [ 'start' => 0, 'font_variant' => 'regular' ] ],
			],
		];
		if ( '' !== $declared_preset ) {
			$fonts_logic['connected_fields_preset'] = $declared_preset;
		}

		$font_palettes = new TestHierarchyPresetFontPalettes(
			$options,
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class ),
			[ 'hive' => [ 'fonts_logic' => $fonts_logic ] ]
		);

		$this->stored[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY ] = 'hive';

		$font_palettes->apply_current_font_palette_to_connected_fields();
	}

	private function preset_details(): array {
		return [
			'default' => 'preset-2',
			'choices' => [
				'preset-1'   => [ 'config' => [ 'sm_font_body' => [ 'heading_5_font' ] ] ],
				'preset-2'   => [ 'config' => [] ],
				'preset-2-5' => [ 'config' => [] ],
				'preset-3'   => [ 'config' => [] ],
			],
		];
	}
}

class TestHierarchyPresetFontPalettes extends FontPalettes {
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

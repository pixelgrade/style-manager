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
 * Font palettes change the voice; sizes belong to the user (issue #203).
 *
 * Palette application must be size-neutral: connected fields keep their
 * current font sizes even when legacy catalog data carries a multiplier.
 * Font Sizing owns scale; a palette owns typographic voice.
 */
class FontPalettesSizeNeutralApplyTest extends TestCase {

	private array $theme_mods = [];
	private array $updated_options = [];

	private function mock_wp_state( array $selected_options, array $theme_mods ): void {
		$this->theme_mods      = $theme_mods;
		$this->updated_options = [];

		$selected = $selected_options;
		Functions\when( 'get_option' )->alias( function( string $option_name, $default = false ) use ( &$selected ) {
			return $selected[ $option_name ] ?? $default;
		} );
		Functions\when( 'update_option' )->alias( function( string $option_name, $value ) use ( &$selected ) {
			$this->updated_options[ $option_name ] = $value;
			$selected[ $option_name ]              = $value;

			return true;
		} );
		Functions\when( 'get_theme_mod' )->alias( function( string $name, $default = false ) {
			return $this->theme_mods[ $name ] ?? $default;
		} );
		Functions\when( 'set_theme_mod' )->alias( function( string $name, $value ) {
			$this->theme_mods[ $name ] = $value;
		} );
	}

	private function make_font_palettes( array $palettes, array $details ): FontPalettes {
		$options = $this->createMock( Options::class );
		$options->method( 'get_options_key' )->willReturn( 'anima_options' );
		$options->method( 'get_details_all' )->willReturn( $details );
		$options->method( 'invalidate_all_caches' );

		return new TestSizeNeutralFontPalettes(
			$options,
			$this->createMock( DesignAssets::class ),
			$this->createMock( LoggerInterface::class ),
			$palettes
		);
	}

	public function test_apply_preserves_fine_tuned_connected_font_size(): void {
		$this->mock_wp_state(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'test-voice' ],
			[
				'anima_options' => [
					'body_font' => [
						'font_family' => 'System Sans-Serif Clear',
						// The user fine-tuned the body away from the 16px default.
						'font_size'   => [ 'value' => 17, 'unit' => 'px' ],
					],
				],
			]
		);

		$font_palettes = $this->make_font_palettes(
			[
				'test-voice' => [
					'fonts_logic' => [
						'sm_font_body' => [ 'font_family' => 'Reforma1969' ],
					],
				],
			],
			$this->body_details( [ 'value' => 17, 'unit' => 'px' ] )
		);

		$applied = $font_palettes->apply_current_font_palette_to_connected_fields();

		$this->assertSame( [ 'body_font' ], $applied );
		$this->assertSame( 'Reforma1969', $this->theme_mods['anima_options']['body_font']['font_family'] ?? null );
		$this->assertEquals(
			17,
			$this->theme_mods['anima_options']['body_font']['font_size']['value'] ?? null,
			'A size-less palette must keep the user\'s fine-tuned font size.'
		);
	}

	public function test_apply_is_size_identity_across_a_display_hierarchy(): void {
		$heading_default = [
			'font_family' => 'Space Grotesk',
			'font_size'   => [ 'value' => 66, 'unit' => 'px' ],
		];
		$display_default = [
			'font_family' => 'Space Grotesk',
			'font_size'   => [ 'value' => 115, 'unit' => 'px' ],
		];

		$this->mock_wp_state(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'same-voice' ],
			[
				'anima_options' => [
					'heading_1_font' => $heading_default,
					'display_font'   => $display_default,
				],
			]
		);

		$details = [
			'sm_font_primary'           => [
				'type'             => 'font',
				'value'            => [ 'font_family' => 'Space Grotesk' ],
				'connected_fields' => [ 'heading_1_font', 'display_font' ],
			],
			// The shipped knob state must not rescale anything by itself.
			'sm_font_primary_elevation' => [ 'value' => 24, 'default' => 24 ],
			'sm_font_primary_pitch'     => [ 'value' => 141, 'default' => 141 ],
			'heading_1_font'            => [ 'type' => 'font', 'default' => $heading_default, 'value' => $heading_default ],
			'display_font'              => [ 'type' => 'font', 'default' => $display_default, 'value' => $display_default ],
		];

		$font_palettes = $this->make_font_palettes(
			[
				'same-voice' => [
					'fonts_logic' => [
						'sm_font_primary' => [ 'font_family' => 'Space Grotesk' ],
					],
				],
			],
			$details
		);

		$font_palettes->apply_current_font_palette_to_connected_fields();

		$this->assertEquals(
			66,
			$this->theme_mods['anima_options']['heading_1_font']['font_size']['value'] ?? null,
			'Applying a palette with the same family must not change heading sizes.'
		);
		$this->assertEquals(
			115,
			$this->theme_mods['anima_options']['display_font']['font_size']['value'] ?? null,
			'Applying a palette with the same family must not change display sizes.'
		);
	}

	public function test_authored_font_size_multiplier_does_not_change_the_user_scale(): void {
		$this->mock_wp_state(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'normalized-voice' ],
			[
				'anima_options' => [
					'body_font' => [
						'font_family' => 'System Sans-Serif Clear',
						'font_size'   => [ 'value' => 20, 'unit' => 'px' ],
					],
				],
			]
		);

		$font_palettes = $this->make_font_palettes(
			[
				'normalized-voice' => [
					'fonts_logic' => [
						'sm_font_body' => [
							'font_family'          => 'Reforma1969',
							'font_size_multiplier' => 0.95,
						],
					],
				],
			],
			$this->body_details( [ 'value' => 20, 'unit' => 'px' ] )
		);

		$font_palettes->apply_current_font_palette_to_connected_fields();

		$this->assertEquals(
			20,
			$this->theme_mods['anima_options']['body_font']['font_size']['value'] ?? null,
			'Font palettes may change voice properties, but numeric size remains user-owned and round-trippable.'
		);
	}

	public function test_palette_does_not_apply_size_dependent_styles_to_an_inherited_size(): void {
		$this->mock_wp_state(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY => 'inherit-safe' ],
			[
				'anima_options' => [
					'body_font' => [
						'font_family' => 'System Sans-Serif Clear',
						'font_size'   => [ 'value' => false, 'unit' => false ],
					],
				],
			]
		);

		$font_palettes = $this->make_font_palettes(
			[
				'inherit-safe' => [
					'fonts_logic' => [
						'sm_font_body' => [
							'font_family'                  => 'Reforma1969',
							'font_styles_intervals'        => [
								[
									'start'          => 0,
									'font_variant'   => '700',
									'letter_spacing' => [ 'value' => 0.1, 'unit' => 'em' ],
									'text_transform' => 'uppercase',
								],
							],
							'font_size_to_line_height_points' => [ [ 16, 1.5 ], [ 32, 1.2 ] ],
						],
					],
				],
			],
			$this->body_details( [ 'value' => false, 'unit' => false ] )
		);

		$font_palettes->apply_current_font_palette_to_connected_fields();

		$body = $this->theme_mods['anima_options']['body_font'];
		$this->assertSame( false, $body['font_size']['value'] );
		$this->assertArrayNotHasKey( 'font_variant', $body );
		$this->assertArrayNotHasKey( 'letter_spacing', $body );
		$this->assertArrayNotHasKey( 'text_transform', $body );
		$this->assertArrayNotHasKey( 'line_height', $body );
	}

	public function test_font_sizing_rebuilds_the_locked_plus_outputs_from_the_persisted_baseline(): void {
		$this->mock_wp_state(
			[
				'sm_font_sizing' => 'smaller',
				FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 1, 1000 ],
							'sizes'    => [ 'body_font' => 999 ],
						],
					],
				],
				'sm_font_sizing_trusted_baseline_v1' => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 14, 24 ],
							'sizes'    => [ 'body_font' => 20 ],
						],
					],
				],
				'sm_font_body' => [ 'font_family' => 'Reforma1969' ],
			],
			[
				'anima_options' => [
					'body_font' => [
						'font_family' => 'Reforma1969',
						'font_size'   => [ 'value' => 20, 'unit' => 'px' ],
					],
				],
			]
		);

		$font_palettes = $this->make_font_palettes(
			[],
			$this->body_details( [ 'value' => 20, 'unit' => 'px' ] )
		);

		$updated = $font_palettes->apply_current_font_sizing_to_connected_fields();

		$this->assertSame( [ 'body_font' ], $updated );
		$this->assertSame( 6, $this->updated_options['sm_font_primary_elevation'] ?? null );
		$this->assertSame( 40, $this->updated_options['sm_font_primary_pitch'] ?? null );
		$this->assertSame( 0, $this->updated_options['sm_font_body_elevation'] ?? null );
		$this->assertSame( 45, $this->updated_options['sm_font_body_pitch'] ?? null );
		$this->assertEquals( 16.7, $this->theme_mods['anima_options']['body_font']['font_size']['value'] ?? null );
	}

	public function test_locked_font_sizing_prepares_a_server_owned_baseline_and_ignores_the_public_copy(): void {
		$this->mock_wp_state(
			[
				FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 1, 1000 ],
							'sizes'    => [ 'body_font' => 999 ],
						],
					],
				],
				'sm_font_body_elevation' => 0,
				'sm_font_body_pitch'     => 100,
			],
			[]
		);

		$font_palettes = $this->make_font_palettes(
			[],
			$this->body_details( [ 'value' => 20, 'unit' => 'px' ] )
		);

		$baseline = $font_palettes->prepare_locked_font_sizing_baseline();

		$this->assertSame( [ 20.0, 20.0 ], $baseline['scales']['sm_font_body']['interval'] ?? null );
		$this->assertSame( 20.0, $baseline['scales']['sm_font_body']['sizes']['body_font'] ?? null );
		$this->assertSame( $baseline, $this->updated_options['sm_font_sizing_trusted_baseline_v1'] ?? null );
	}

	public function test_locked_font_sizing_adds_new_connected_fields_to_the_trusted_baseline(): void {
		$this->mock_wp_state(
			[
				'sm_font_sizing_trusted_baseline_v1' => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 10, 20 ],
							'sizes'    => [
								'body_font'           => 15,
								'inherited_body_font' => 17,
							],
						],
					],
				],
				'sm_font_body_elevation' => 0,
				'sm_font_body_pitch'     => 100,
			],
			[]
		);

		$details = [
			'sm_font_body' => [
				'type'             => 'font',
				'value'            => [ 'font_family' => 'System Sans-Serif Clear' ],
				'connected_fields' => [ 'body_font', 'new_body_font', 'inherited_body_font' ],
			],
			'body_font' => [
				'type'    => 'font',
				'default' => [ 'font_size' => [ 'value' => 15, 'unit' => 'px' ] ],
				'value'   => [ 'font_size' => [ 'value' => 15, 'unit' => 'px' ] ],
			],
			'new_body_font' => [
				'type'    => 'font',
				'default' => [ 'font_size' => [ 'value' => 18, 'unit' => 'px' ] ],
				'value'   => [ 'font_size' => [ 'value' => 18, 'unit' => 'px' ] ],
			],
			'inherited_body_font' => [
				'type'    => 'font',
				'default' => [ 'font_size' => [ 'value' => 17, 'unit' => 'px' ] ],
				'value'   => [ 'font_size' => [ 'value' => false, 'unit' => false ] ],
			],
		];

		$baseline = $this->make_font_palettes( [], $details )->prepare_locked_font_sizing_baseline();

		$this->assertSame( 15.0, $baseline['scales']['sm_font_body']['sizes']['body_font'] ?? null );
		$this->assertSame( 18.0, $baseline['scales']['sm_font_body']['sizes']['new_body_font'] ?? null );
		$this->assertArrayNotHasKey( 'inherited_body_font', $baseline['scales']['sm_font_body']['sizes'] ?? [] );
	}

	public function test_locked_font_sizing_does_not_resurrect_an_inherited_size_from_a_stale_baseline(): void {
		$this->mock_wp_state(
			[
				'sm_font_sizing' => 'smaller',
				'sm_font_sizing_trusted_baseline_v1' => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 14, 24 ],
							'sizes'    => [ 'body_font' => 20 ],
						],
					],
				],
				'sm_font_body' => [ 'font_family' => 'Reforma1969' ],
			],
			[
				'anima_options' => [
					'body_font' => [
						'font_family' => 'Reforma1969',
						'font_size'   => [ 'value' => false, 'unit' => false ],
					],
				],
			]
		);

		$font_palettes = $this->make_font_palettes(
			[],
			$this->body_details( [ 'value' => false, 'unit' => false ] )
		);

		$font_palettes->apply_current_font_sizing_to_connected_fields();

		$this->assertSame( false, $this->theme_mods['anima_options']['body_font']['font_size']['value'] ?? null );
	}

	public function test_locked_font_sizing_rejects_a_mixed_unit_scale_like_the_client(): void {
		$this->mock_wp_state(
			[
				'sm_font_body_elevation' => 0,
				'sm_font_body_pitch'     => 100,
			],
			[]
		);

		$details = [
			'sm_font_body' => [
				'type'             => 'font',
				'value'            => [ 'font_family' => 'System Sans-Serif Clear' ],
				'connected_fields' => [ 'body_font', 'lead_font' ],
			],
			'body_font' => [
				'type'    => 'font',
				'default' => [ 'font_size' => [ 'value' => 16, 'unit' => 'px' ] ],
				'value'   => [ 'font_size' => [ 'value' => 16, 'unit' => 'px' ] ],
			],
			'lead_font' => [
				'type'    => 'font',
				'default' => [ 'font_size' => [ 'value' => 1.5, 'unit' => 'rem' ] ],
				'value'   => [ 'font_size' => [ 'value' => 1.5, 'unit' => 'rem' ] ],
			],
		];

		$baseline = $this->make_font_palettes( [], $details )->prepare_locked_font_sizing_baseline();

		$this->assertSame( [], $baseline );
		$this->assertArrayNotHasKey( 'sm_font_sizing_trusted_baseline_v1', $this->updated_options );
	}

	public function test_font_sizing_knob_defaults_stay_within_their_declared_ranges(): void {
		Functions\when( 'esc_html__' )->alias( static fn( string $text ): string => $text );
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'wp_kses' )->alias( static fn( $text ) => $text );
		Functions\when( 'wp_kses_post' )->alias( static fn( $text ) => $text );
		Functions\when( 'esc_attr__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'esc_url' )->alias( static fn( string $url ): string => $url );
		Functions\when( 'wp_parse_args' )->alias( static function( $args, $defaults = [] ) {
			return array_merge( (array) $defaults, (array) $args );
		} );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => $text );
		Functions\when( 'esc_attr' )->alias( static fn( $text ) => $text );
		Functions\when( 'sanitize_title' )->alias( static function( string $text ): string {
			return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', trim( $text ) ) ?? '' );
		} );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( string $text ): string => strip_tags( $text ) );
		Functions\when( 'wp_json_encode' )->alias( static fn( $data ) => json_encode( $data ) );
		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$args ) {
			return $value;
		} );
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'get_theme_mod' )->justReturn( false );

		$font_palettes = $this->make_font_palettes( [], [] );
		$config        = $font_palettes->expose_master_fonts_config( [] );

		$checked = 0;
		array_walk_recursive_ref( $config, $checked, function( array $field, string $field_id, int &$checked ) {
			if ( ! isset( $field['input_attrs']['min'], $field['input_attrs']['max'] ) || ! isset( $field['default'] ) || ! is_numeric( $field['default'] ) ) {
				return;
			}
			$checked ++;
			$this->assertGreaterThanOrEqual(
				$field['input_attrs']['min'],
				$field['default'],
				sprintf( '"%s" default is below its own range.', $field_id )
			);
			$this->assertLessThanOrEqual(
				$field['input_attrs']['max'],
				$field['default'],
				sprintf( '"%s" default exceeds its own range (issue #203: pitch default 141 vs max 100).', $field_id )
			);
		} );

		$this->assertGreaterThanOrEqual( 6, $checked, 'Expected elevation/pitch knobs for primary/secondary/body.' );
	}

	public function test_entitled_customizer_save_trusts_the_public_font_sizing_baseline(): void {
		$baseline = [
			'version' => 1,
			'scales'  => [
				'sm_font_body' => [
					'interval' => [ 14.0, 24.0 ],
					'sizes'    => [ 'body_font' => 18.0 ],
				],
			],
		];
		$this->mock_wp_state(
			[ FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => $baseline ],
			[]
		);
		$this->mock_plus_entitlement_bridge( true, true );

		$font_palettes = $this->make_font_palettes( [], [] );
		$font_palettes->trust_customizer_font_sizing_baseline();

		$this->assertSame( $baseline, $this->updated_options['sm_font_sizing_trusted_baseline_v1'] ?? null );
	}

	public function test_locked_customizer_save_does_not_trust_the_public_font_sizing_baseline(): void {
		$this->mock_wp_state(
			[
				FontPalettes::SM_FONT_SIZING_BASELINE_OPTION_KEY => [
					'version' => 1,
					'scales'  => [
						'sm_font_body' => [
							'interval' => [ 1, 1000 ],
							'sizes'    => [ 'body_font' => 999 ],
						],
					],
				],
			],
			[]
		);
		$this->mock_plus_entitlement_bridge( true, false );

		$font_palettes = $this->make_font_palettes( [], [] );
		$font_palettes->trust_customizer_font_sizing_baseline();

		$this->assertArrayNotHasKey( 'sm_font_sizing_trusted_baseline_v1', $this->updated_options );
	}

	private function body_details( array $current_font_size ): array {
		$body_default = [
			'font_family' => 'System Sans-Serif Clear',
			'font_size'   => [ 'value' => 16, 'unit' => 'px' ],
		];
		$body_current              = $body_default;
		$body_current['font_size'] = $current_font_size;

		return [
			'sm_font_body'           => [
				'type'             => 'font',
				'value'            => [ 'font_family' => 'System Sans-Serif Clear' ],
				'connected_fields' => [ 'body_font' ],
			],
			'sm_font_body_elevation' => [ 'value' => 24, 'default' => 24 ],
			'sm_font_body_pitch'     => [ 'value' => 45, 'default' => 45 ],
			'body_font'              => [ 'type' => 'font', 'default' => $body_default, 'value' => $body_current ],
		];
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

/**
 * Walk a config tree and invoke the callback for every array node that looks
 * like a field definition, passing a by-reference counter.
 */
function array_walk_recursive_ref( array $tree, int &$checked, callable $callback ): void {
	foreach ( $tree as $key => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( isset( $node['input_attrs'] ) || isset( $node['default'] ) ) {
			$callback( $node, (string) $key, $checked );
		}
		array_walk_recursive_ref( $node, $checked, $callback );
	}
}

class TestSizeNeutralFontPalettes extends FontPalettes {
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

	public function expose_master_fonts_config( array $config ): array {
		return $this->add_style_manager_section_master_fonts_config( $config );
	}
}

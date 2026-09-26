<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\LayoutSection;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class LayoutSectionTest extends TestCase {
	private TestLayoutSection $section;

	public function setUp(): void {
		parent::setUp();

		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		// Nothing saved: style_manager_effective_rail_small()/_pitch() (called
		// while building the field config, style-manager#215) resolve to their
		// "nothing saved" defaults (230 / 0 — see RailEffectiveDefaultsTest).
		Functions\when( 'get_option' )->justReturn( '' );

		$this->section = new TestLayoutSection();
	}

	public function test_layout_defines_an_independent_rail_gap_token_control(): void {
		$config  = $this->section->expose_add_style_manager_section_layout_config( [] );
		$options = $config['sections']['style_manager_section']['options'];
		$control = $options['sm_rail_gap'];

		$this->assertSame( 'range', $control['type'] );
		$this->assertSame( 'option', $control['setting_type'] );
		$this->assertSame( 'sm_rail_gap', $control['setting_id'] );
		$this->assertTrue( $control['live'] );
		$this->assertSame( 'Rail Gap', $control['label'] );
		$this->assertSame( 2, $control['default'] );
		// Rail Gap always has a real default (2) — no effective-default plumbing.
		$this->assertSame(
			[ 'min' => 1, 'max' => 5, 'step' => 0.25, 'data-preview' => true ],
			$control['input_attrs']
		);
		$this->assertSame( '--sm-rail-gap', $control['css'][0]['property'] );
		$this->assertSame( ':root', $control['css'][0]['selector'] );
		$this->assertSame( '', $control['css'][0]['unit'] );
	}

	public function test_layout_places_rail_gap_between_rail_pitch_and_global_spacing(): void {
		$config  = $this->section->expose_add_style_manager_section_layout_config( [] );
		$options = $config['sections']['style_manager_section']['options'];
		$panel   = $this->section->expose_reorganize_customizer_controls( [], $config['sections']['style_manager_section'] );
		$keys    = array_keys( $panel['sections']['sm_layout_section']['options'] );

		$this->assertArrayHasKey( 'sm_rail_gap', $options );
		$this->assertSame( 'sm_rail_pitch', $keys[ array_search( 'sm_rail_gap', $keys, true ) - 1 ] );
		$this->assertSame( 'sm_spacing_level', $keys[ array_search( 'sm_rail_gap', $keys, true ) + 1 ] );
	}

	public function test_layout_defines_a_small_only_rail_control_that_emits_through_the_rail_scale(): void {
		$config  = $this->section->expose_add_style_manager_section_layout_config( [] );
		$options = $config['sections']['style_manager_section']['options'];
		$control = $options['sm_rail_small'];

		$this->assertSame( 'range', $control['type'] );
		$this->assertSame( 'option', $control['setting_type'] );
		$this->assertSame( 'sm_rail_small', $control['setting_id'] );
		$this->assertTrue( $control['live'] );
		// Unset by default: untouched sites emit nothing new.
		$this->assertSame( '', $control['default'] );
		// style-manager#215: carries the effective Small (230 while nothing is
		// saved) so the box/slider never show a fabricated (min+max)/2 midpoint.
		$this->assertSame(
			[ 'min' => 100, 'max' => 420, 'step' => 1, 'data-preview' => true, 'data-effective-default' => 230 ],
			$control['input_attrs']
		);
		// Inert own CSS (live-preview binding only); sm_rail_scale emits the token.
		$this->assertCount( 1, $control['css'] );
		$this->assertSame( 'sm_rail_small_css_cb', $control['css'][0]['callback_filter'] );
		$this->assertNotSame( '--sm-rail-small', $control['css'][0]['property'] );
	}

	public function test_layout_carries_the_effective_small_on_rail_base(): void {
		// style-manager#215: "Rail Base (Small)" (sm_rail_scale) shows the SAME
		// effective value as the Small Rail control above — Base's effective
		// value IS the effective Small, by definition.
		$config  = $this->section->expose_add_style_manager_section_layout_config( [] );
		$options = $config['sections']['style_manager_section']['options'];
		$control = $options['sm_rail_scale'];

		$this->assertSame( 230, $control['input_attrs']['data-effective-default'] );
	}

	public function test_layout_carries_the_effective_pitch_on_rail_pitch(): void {
		// style-manager#215: no pitch reproduces the default Medium/Large — 0
		// (Flat) is the documented placeholder (style_manager_effective_rail_pitch()).
		$config  = $this->section->expose_add_style_manager_section_layout_config( [] );
		$options = $config['sections']['style_manager_section']['options'];
		$control = $options['sm_rail_pitch'];

		$this->assertSame( 0, $control['input_attrs']['data-effective-default'] );
	}

	public function test_layout_places_the_small_only_rail_before_the_rail_scale(): void {
		$config = $this->section->expose_add_style_manager_section_layout_config( [] );
		$panel  = $this->section->expose_reorganize_customizer_controls( [], $config['sections']['style_manager_section'] );
		$keys   = array_keys( $panel['sections']['sm_layout_section']['options'] );

		$this->assertSame( 'sm_content_inset', $keys[ array_search( 'sm_rail_small', $keys, true ) - 1 ] );
		$this->assertSame( 'sm_rail_scale_preset', $keys[ array_search( 'sm_rail_small', $keys, true ) + 1 ] );
	}
}

class TestLayoutSection extends LayoutSection {
	public function expose_add_style_manager_section_layout_config( array $config ): array {
		return $this->add_style_manager_section_layout_config( $config );
	}

	public function expose_reorganize_customizer_controls( array $panel_config, array $section_config ): array {
		return $this->reorganize_customizer_controls( $panel_config, $section_config );
	}
}

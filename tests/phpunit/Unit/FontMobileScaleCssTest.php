<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

/**
 * The Phone Heading Scale owner (issue #211): one absolute value that sets only
 * the theme's small-screen slope, never a desktop size or a connected field.
 */
class FontMobileScaleCssTest extends TestCase {
	public function test_unset_value_emits_nothing_so_the_theme_slope_stays(): void {
		$this->assertNull( style_manager_font_mobile_scale_slope( '' ) );
		$this->assertNull( style_manager_font_mobile_scale_slope( null ) );
		$this->assertNull( style_manager_font_mobile_scale_slope( 'large' ) );
		$this->assertSame( '', style_manager_font_mobile_scale_css_cb( '', ':root', '--theme-font-size-slope-adjust' ) );
	}

	public function test_value_maps_to_the_share_of_desktop_size_phones_keep(): void {
		$this->assertSame( 0.6, style_manager_font_mobile_scale_slope( 40 ) );
		$this->assertSame( 0.0, style_manager_font_mobile_scale_slope( 100 ) );
		$this->assertSame( 1.0, style_manager_font_mobile_scale_slope( 0 ) );
		$this->assertSame( 0.25, style_manager_font_mobile_scale_slope( '75' ) );
	}

	public function test_value_is_clamped_to_the_zero_to_one_slope_range(): void {
		$this->assertSame( 1.0, style_manager_font_mobile_scale_slope( -20 ) );
		$this->assertSame( 0.0, style_manager_font_mobile_scale_slope( 140 ) );
	}

	public function test_css_only_applies_below_the_desktop_breakpoint(): void {
		$this->assertSame(
			'@media not screen and (min-width: 1440px) { :root { --theme-font-size-slope-adjust: 0.35; } }' . PHP_EOL,
			style_manager_font_mobile_scale_css_cb( 65, ':root', '--theme-font-size-slope-adjust' )
		);
	}

	public function test_repeated_output_is_a_fixed_point(): void {
		$first  = style_manager_font_mobile_scale_css_cb( 55, ':root', '--theme-font-size-slope-adjust' );
		$second = style_manager_font_mobile_scale_css_cb( 55, ':root', '--theme-font-size-slope-adjust' );

		$this->assertSame( $first, $second );
	}
}

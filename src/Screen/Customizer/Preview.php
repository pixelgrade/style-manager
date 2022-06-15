<?php
/**
 * Customizer screen preview functionality provider.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen\Customizer;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Customizer screen preview provider class.
 *
 * @since 2.0.0
 */
class Preview extends AbstractHookProvider {

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_action( 'customize_preview_init', 'enqueue_assets', 99999 );

		$this->add_action( 'wp_footer', 'output_color_palettes_preview_overlay' );

		// Register hooks related to Style Manager controls callbacks in sm-functions.php

		$this->add_action( 'customize_preview_init', 'sm_advanced_palette_output_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_site_color_variation_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_color_select_dark_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_color_select_darker_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_color_switch_dark_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_color_switch_darker_cb_customizer_preview', 20 );
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 2.0.0
	 */
	protected function enqueue_assets() {
		wp_enqueue_script( 'pixelgrade_style_manager-previewer' );
	}

	/**
	 * Output a wrapper for the color palettes preview overlay.
	 *
	 * @since 2.0.0
	 */
	protected function output_color_palettes_preview_overlay() {
		if ( is_customize_preview() ) {
			echo '<div id="sm-color-palettes-preview"></div>';
		}
	}

	protected function sm_advanced_palette_output_cb_customizer_preview() {
		$fallback_palettes  = sm_get_fallback_palettes();
		$variation = intval( get_option( 'sm_site_color_variation', 1 ) );
		$palettes = json_decode( get_option( 'sm_advanced_palette_output', '[]' ) );
		$user_palettes = array_filter( $palettes, 'sm_filter_user_palettes' );
		$palettes_count = count( $user_palettes );

		$js = "";

		$js .= "
function sm_advanced_palette_output_cb( value, selector, property ) {
    var palettes = JSON.parse( value ),
        variation = parseInt( wp.customize( 'sm_site_color_variation' )(), 10 ),
        fallbackPalettes = JSON.parse('" . json_encode( $fallback_palettes ) . "');
        
      
        
    if ( ! palettes.length ) {
        palettes = fallbackPalettes;
    }
    
    window.parent.sm.customizer.maybeFillPalettesArray( palettes, " . $palettes_count . " );
    return window.parent.sm.customizer.getCSSFromPalettes( palettes, variation );
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	// site_color_variation callback should update the same style tag as advanced_palette_output
	// to avoid cascading and overwriting a previously set value
	protected function sm_site_color_variation_cb_customizer_preview() {
		$fallback_palettes  = sm_get_fallback_palettes();
		$advanced_palette_output = get_option( 'sm_advanced_palette_output', '[]' );
		$palettes = json_decode( $advanced_palette_output );
		$user_palettes = array_filter( $palettes, 'sm_filter_user_palettes' );
		$palettes_count = count( $user_palettes );

		$js = "";

		$js .= "
function sm_site_color_variation_cb( value, selector, property ) {
    var palettes = JSON.parse( wp.customize( 'sm_advanced_palette_output' )() ),
        variation = parseInt( value, 10 ),
        fallbackPalettes = JSON.parse('" . json_encode( $fallback_palettes ) . "'),
        styleTag = document.querySelector( '#dynamic_style_sm_advanced_palette_output' );
        
    if ( ! palettes.length ) {
        palettes = fallbackPalettes;
    }
    
    window.parent.sm.customizer.maybeFillPalettesArray( palettes, " . $palettes_count . " );
    var newCSS = window.parent.sm.customizer.getCSSFromPalettes( palettes, variation );
    
    if ( styleTag ) {
        styleTag.innerHTML = newCSS;
    }
    
    return '';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	protected function sm_color_select_dark_cb_customizer_preview() {
		$js = "
function sm_color_select_dark_cb(value, selector, property) {
    return selector + ' {' + property + ': var(--sm-current-' + value + '-color);' + '}';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	protected function sm_color_select_darker_cb_customizer_preview() {
		$js = "
function sm_color_select_darker_cb(value, selector, property) {
    return selector + ' {' + property + ': var(--sm-current-' + value + '-color);' + '}';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	protected function sm_color_switch_dark_cb_customizer_preview() {
		$js = "
function sm_color_switch_dark_cb(value, selector, property) {
    var color = value === true ? 'accent' : 'fg1';
    return selector + ' { ' + property + ': var(--sm-current-' + color + '-color); }';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	protected function sm_color_switch_darker_cb_customizer_preview() {
		$js = "
function sm_color_switch_darker_cb(value, selector, property) {
	var color = value === true ? 'accent' : 'fg2';
	return selector + ' { ' + property + ': var(--sm-current-' + color + '-color); }';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}
}

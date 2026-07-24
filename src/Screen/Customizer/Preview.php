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
		$this->add_action( 'customize_preview_init', 'sm_rail_scale_css_cb_customizer_preview', 20 );
		$this->add_action( 'customize_preview_init', 'sm_rail_pitch_css_cb_customizer_preview', 20 );
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
        fallbackPalettes = JSON.parse('" . (string) json_encode( $fallback_palettes ) . "');
    var smCustomizer = null;

    try {
        if ( window.parent && window.parent.sm && window.parent.sm.customizer ) {
            smCustomizer = window.parent.sm.customizer;
        }
    } catch (e) {}

    if ( ! smCustomizer && window.sm && window.sm.customizer ) {
        smCustomizer = window.sm.customizer;
    }

    if ( ! palettes.length ) {
        palettes = fallbackPalettes;
    }

    if ( ! smCustomizer || typeof smCustomizer.getCSSFromPalettes !== 'function' ) {
        return '';
    }

    if ( typeof smCustomizer.maybeFillPalettesArray === 'function' ) {
        smCustomizer.maybeFillPalettesArray( palettes, " . $palettes_count . " );
    }

    return smCustomizer.getCSSFromPalettes( palettes, variation );
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
        fallbackPalettes = JSON.parse('" . (string) json_encode( $fallback_palettes ) . "'),
        styleTag = document.querySelector( '#dynamic_style_sm_advanced_palette_output' );
    var smCustomizer = null;

    try {
        if ( window.parent && window.parent.sm && window.parent.sm.customizer ) {
            smCustomizer = window.parent.sm.customizer;
        }
    } catch (e) {}

    if ( ! smCustomizer && window.sm && window.sm.customizer ) {
        smCustomizer = window.sm.customizer;
    }
        
    if ( ! palettes.length ) {
        palettes = fallbackPalettes;
    }
    
    if ( ! smCustomizer || typeof smCustomizer.getCSSFromPalettes !== 'function' ) {
        return '';
    }

    if ( typeof smCustomizer.maybeFillPalettesArray === 'function' ) {
        smCustomizer.maybeFillPalettesArray( palettes, " . $palettes_count . " );
    }

    var newCSS = smCustomizer.getCSSFromPalettes( palettes, variation );
    
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

	// Rail-scale tokens: JS twin of style_manager_rail_scale_css_cb() +
	// style_manager_rail_widths(). Base + Pitch soft-ceiling math, with the
	// both-unset / v1-compat / v2 migration contract. Shared helpers are defined
	// once; the pitch callback recomputes the sm_rail_scale style tag so moving
	// pitch previews live too.
	protected function sm_rail_scale_css_cb_customizer_preview() {
		$js = "
window.__smRailSoft = function(x){ return x / Math.pow(1 + Math.pow(x/600, 12), 1/12); };
window.__smRailWidths = function(baseRaw, pitchRaw){
	var baseSet = baseRaw !== '' && baseRaw != null && !isNaN(parseFloat(baseRaw)) && parseFloat(baseRaw) > 0;
	var pitchSet = pitchRaw !== '' && pitchRaw != null && !isNaN(parseFloat(pitchRaw));
	if (!baseSet && !pitchSet) return null;
	var s, m, l;
	if (pitchSet) {
		var base = baseSet ? parseFloat(baseRaw) : 300;
		var f = parseFloat(pitchRaw) / 45;
		var mult = 1 + (Math.sqrt(3) - 1) * f * f;
		s = window.__smRailSoft(base); m = window.__smRailSoft(base * mult); l = window.__smRailSoft(base * mult * mult);
	} else {
		var b = parseFloat(baseRaw);
		s = b; m = b * 330 / 288; l = b * 400 / 288;
	}
	return { small: Math.round(s), medium: Math.round(m), large: Math.round(l) };
};
window.__smRailRead = function(id){ var s = wp.customize(id); return s ? s() : ''; };
function sm_rail_scale_css_cb(value, selector, property, unit) {
	var w = window.__smRailWidths(window.__smRailRead('sm_rail_scale'), window.__smRailRead('sm_rail_pitch'));
	if (!w) return '';
	var v = property === '--sm-rail-small' ? w.small : property === '--sm-rail-medium' ? w.medium : property === '--sm-rail-large' ? w.large : null;
	if (v == null) return '';
	return selector + ' { ' + property + ': ' + v + (unit || '') + '; }';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}

	// Pitch carries no CSS of its own; on change it recomputes and rewrites the
	// sm_rail_scale style tag (same pattern as sm_site_color_variation_cb).
	protected function sm_rail_pitch_css_cb_customizer_preview() {
		$js = "
function sm_rail_pitch_css_cb(value, selector, property, unit) {
	var w = window.__smRailWidths(window.__smRailRead('sm_rail_scale'), window.__smRailRead('sm_rail_pitch'));
	var css = '';
	if (w) {
		css = ':root { --sm-rail-small: ' + w.small + '; }\\n'
			+ ':root { --sm-rail-medium: ' + w.medium + '; }\\n'
			+ ':root { --sm-rail-large: ' + w.large + '; }\\n';
	}
	var tag = document.getElementById('dynamic_style_sm_rail_scale');
	if (tag) tag.innerHTML = css;
	return '';
}" . PHP_EOL;

		wp_add_inline_script( 'pixelgrade_style_manager-previewer', $js );
	}
}

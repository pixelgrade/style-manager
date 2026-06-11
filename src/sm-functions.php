<?php
/**
 * Style Manager functions to be used by themes mainly.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

\defined( 'ABSPATH' ) || exit;

/**
 * @since   2.0.0
 *
 * @param          $label
 * @param          $selector
 * @param          $default
 * @param string[] $properties
 *
 * @return array
 */
function style_manager_get_color_select_darker_config( $label, $selector, $default, $properties = [ 'color' ] ): array {
	return sm_get_color_select_dark_config( $label, $selector, $default, $properties, true );
}

/**
 * @since   2.0.0
 *
 * @param          $label
 * @param          $selector
 * @param          $default
 * @param string[] $properties
 * @param false    $isDarker
 *
 * @return array
 */
function style_manager_get_color_select_dark_config( $label, $selector, $default, $properties = [ 'color' ], $isDarker = false ): array {

	$callback = 'sm_color_select_dark_cb';
	$choices  = [
		'background' => esc_html__( 'Background', '__plugin_txtd' ),
		'dark'       => esc_html__( 'Dark', '__plugin_txtd' ),
		'accent'     => esc_html__( 'Accent', '__plugin_txtd' ),
	];

	if ( $isDarker ) {
		$callback = 'sm_color_select_darker_cb';
		$choices  = [
			'background' => esc_html__( 'Background', '__plugin_txtd' ),
			'darker'     => esc_html__( 'Dark', '__plugin_txtd' ),
			'accent'     => esc_html__( 'Accent', '__plugin_txtd' ),
		];
	}

	$css = [];

	if ( ! is_array( $properties ) ) {
		$properties = [ $properties ];
	}

	foreach ( $properties as $property ) {
		$css[] = [
			'property'        => $property,
			'selector'        => $selector,
			'callback_filter' => $callback,
		];
	}

	return [
		'type'    => 'select_color',
		'label'   => $label,
		'live'    => true,
		'default' => $default,
		'css'     => $css,
		'choices' => $choices,
	];
}

/**
 * @since   2.0.0
 *
 * @param string $value
 * @param string $selector
 * @param string $property
 *
 * @return string
 */
function style_manager_color_select_dark_cb( string $value, string $selector, string $property ): string {
	return $selector . ' { ' . $property . ': var(--sm-current-' . $value . '-color); }' . PHP_EOL;
}

/**
 * @since   2.0.0
 *
 * @param string $value
 * @param string $selector
 * @param string $property
 *
 * @return string
 */
function style_manager_color_select_darker_cb( string $value, string $selector, string $property ): string {
	return $selector . ' { ' . $property . ': var(--sm-current-' . $value . '-color); }' . PHP_EOL;
}

/**
 * @since   2.0.0
 *
 * @param          $label
 * @param          $selector
 * @param          $default
 * @param string[] $properties
 *
 * @return array
 */
function style_manager_get_color_switch_darker_config( $label, $selector, $default, $coloration = 2, $properties = [ 'color' ] ): array {
	return sm_get_color_switch_dark_config( $label, $selector, $default, $coloration, $properties, true );
}

/**
 * @since   2.0.0
 *
 * @param          $label
 * @param          $selector
 * @param          $default
 * @param string[] $properties
 * @param false    $isDarker
 *
 * @return array
 */
function style_manager_get_color_switch_dark_config( $label, $selector, $default, $coloration = 2, $properties = [ 'color' ], $isDarker = false ): array {

	$css      = [];
	$callback = 'sm_color_switch_dark_cb';

	if ( $isDarker ) {
		$callback = 'sm_color_switch_darker_cb';
	}

	if ( ! is_array( $properties ) ) {
		$properties = [ $properties ];
	}

	foreach ( $properties as $property ) {
		$css[] = [
			'property'        => $property,
			'selector'        => $selector,
			'callback_filter' => $callback,
		];
	}

	return [
		'type'       => 'sm_toggle',
		'label'      => $label,
		'live'       => true,
		'default'    => $default,
		'css'        => $css,
		'coloration' => $coloration,
	];
}

/**
 * @since   2.0.0
 *
 * @param bool $value
 * @param string $selector
 * @param string $property
 *
 * @return string
 */
function style_manager_color_switch_dark_cb( bool $value, string $selector, string $property ): string {
	$color = 'fg1';

	if ( $value === true ) {
		$color = 'accent';
	}

	return $selector . ' {' . $property . ': var(--sm-current-' . $color . '-color); }' . PHP_EOL;
}

/**
 * @since   2.0.0
 *
 * @param bool $value
 * @param string $selector
 * @param string $property
 *
 * @return string
 */
function style_manager_color_switch_darker_cb( bool $value, string $selector, string $property ): string {
	$color = 'fg2';

	if ( $value === true ) {
		$color = 'accent';
	}

	return $selector . ' {' . $property . ': var(--sm-current-' . $color . '-color); }' . PHP_EOL;
}

/**
 * @since   2.0.0
 *
 * @param string $value
 *
 * @return string
 */
function style_manager_get_palette_output_from_color_config( string $value ): string {
	$output = '';

	$palettes = json_decode( $value );

	if ( empty( $palettes ) ) {
		$palettes = sm_get_fallback_palettes();
	}

	$output .= sm_palettes_output( $palettes );

	return $output;
}

/**
 * Get saved palettes from the Style Manager option or fallback palettes.
 *
 * @since 2.0.0
 *
 * @return array
 */
function style_manager_get_saved_palettes(): array {
	$palettes = json_decode( (string) get_option( 'sm_advanced_palette_output', '[]' ) );

	if ( empty( $palettes ) ) {
		$palettes = sm_get_fallback_palettes();
	}

	return is_array( $palettes ) ? $palettes : [];
}

/**
 * Get transient runtime palettes for the current request.
 *
 * @since 2.0.0
 *
 * @param array $saved_palettes Saved palettes already resolved for the request.
 * @param array $context        Optional runtime context used by request-scoped palette providers.
 *
 * @return array
 */
function style_manager_get_runtime_palettes( array $saved_palettes = [], array $context = [] ): array {
	$runtime_palettes = apply_filters( 'style_manager/runtime_palettes', [], $saved_palettes, $context );

	return is_array( $runtime_palettes ) ? array_values( array_filter( $runtime_palettes ) ) : [];
}

/**
 * Build the effective runtime palette payload for a given request context.
 *
 * @since 2.0.0
 *
 * @param array $context Optional runtime context used by request-scoped palette providers.
 *
 * @return array
 */
function style_manager_get_palette_runtime_payload( array $context = [] ): array {
	$saved_palettes   = sm_get_saved_palettes();
	$runtime_palettes = sm_get_runtime_palettes( $saved_palettes, $context );
	$palettes         = sm_merge_palettes_by_id( $saved_palettes, $runtime_palettes );

	return [
		'palettes'        => $palettes,
		'runtimePalettes' => $runtime_palettes,
		'runtimeCss'      => empty( $runtime_palettes ) ? '' : sm_palettes_output( $runtime_palettes ),
	];
}

/**
 * Merge saved and runtime palettes by id.
 *
 * @since 2.0.0
 *
 * @param array $palettes Base palettes.
 * @param array $runtime_palettes Runtime palettes.
 *
 * @return array
 */
function style_manager_merge_palettes_by_id( array $palettes, array $runtime_palettes = [] ): array {
	$by_id = [];

	foreach ( array_merge( $palettes, $runtime_palettes ) as $palette ) {
		if ( ! is_object( $palette ) || ! isset( $palette->id ) ) {
			continue;
		}

		$by_id[ (string) $palette->id ] = $palette;
	}

	return array_values( $by_id );
}

/**
 * Get the merged palette list for the current request.
 *
 * @since 2.0.0
 *
 * @param array $context Optional runtime context used by request-scoped palette providers.
 *
 * @return array
 */
function style_manager_get_palettes_for_runtime_context( array $context = [] ): array {
	$payload = sm_get_palette_runtime_payload( $context );

	return $payload['palettes'];
}

/**
 * Get the merged palette list for the current request.
 *
 * @since 2.0.0
 *
 * @return array
 */
function style_manager_get_palettes_for_current_request(): array {
	return sm_get_palettes_for_runtime_context();
}

/**
 * Get palette CSS output for the current request, including runtime palettes.
 *
 * @since 2.0.0
 *
 * @param array $context Optional runtime context used by request-scoped palette providers.
 *
 * @return string
 */
function style_manager_get_palette_output_for_runtime_context( array $context = [] ): string {
	$payload = sm_get_palette_runtime_payload( $context );

	return sm_palettes_output( $payload['palettes'] );
}

/**
 * Get palette CSS output for the current request, including runtime palettes.
 *
 * @since 2.0.0
 *
 * @return string
 */
function style_manager_get_palette_output_for_current_request(): string {
	return sm_get_palette_output_for_runtime_context();
}

/**
 * Build a preview payload for runtime palettes without mutating saved options.
 *
 * @since 2.0.0
 *
 * @param array $context Optional runtime context used by request-scoped palette providers.
 *
 * @return array
 */
function style_manager_get_palette_runtime_preview_payload( array $context = [] ): array {
	return sm_get_palette_runtime_payload( $context );
}

/**
 * Build a transient contextual palette from a single source color.
 *
 * The returned shape matches the modern Style Manager palette structure
 * consumed by Nova Blocks and the palette CSS serializer.
 *
 * @since 2.0.0
 *
 * @param string $color Hex color.
 * @param string $id Palette id.
 * @param string $label Palette label.
 *
 * @return object|null
 */
function style_manager_build_contextual_palette_from_color( string $color, string $id = 'contextual-post', string $label = 'Contextual Post' ) {
	$color = strtolower( (string) sanitize_hex_color( $color ) );

	if ( empty( $color ) ) {
		return null;
	}

	$mixes = [
		[ '#ffffff', 0.92 ],
		[ '#ffffff', 0.84 ],
		[ '#ffffff', 0.72 ],
		[ '#ffffff', 0.60 ],
		[ '#ffffff', 0.40 ],
		[ '#ffffff', 0.20 ],
		[ null, 0.00 ],
		[ '#000000', 0.18 ],
		[ '#000000', 0.34 ],
		[ '#000000', 0.50 ],
		[ '#000000', 0.66 ],
		[ '#000000', 0.82 ],
	];

	$variations      = [];
	$dark_variations = [];

	foreach ( $mixes as $mix ) {
		[ $reference, $ratio ] = $mix;

		$background      = $reference ? sm_mix_hex_colors( $color, $reference, $ratio ) : $color;
		$dark_background = sm_mix_hex_colors( $background, '#000000', 0.18 );

		$variations[]      = sm_build_contextual_palette_variation( $background, $color );
		$dark_variations[] = sm_build_contextual_palette_variation( $dark_background, $color );
	}

	return (object) [
		'id'             => $id,
		'label'          => $label,
		'source'         => [ $color ],
		'sourceIndex'    => 6,
		'variations'     => $variations,
		'darkVariations' => $dark_variations,
	];
}

/**
 * Build a single contextual palette variation.
 *
 * @since 2.0.0
 *
 * @param string $background Background hex color.
 * @param string $source Source hex color.
 *
 * @return object
 */
function style_manager_build_contextual_palette_variation( string $background, string $source ): object {
	$foreground = sm_pick_contextual_text_color( $background );
	$accent     = sm_get_accessible_contextual_accent( $background, $source, $foreground );

	return (object) [
		'bg'     => $background,
		'accent' => $accent,
		'fg1'    => $foreground,
		'fg2'    => $foreground,
	];
}

/**
 * Pick the best foreground between black and white for a background color.
 *
 * @since 2.0.0
 *
 * @param string $background Background hex color.
 *
 * @return string
 */
function style_manager_pick_contextual_text_color( string $background ): string {
	$black_contrast = sm_hex_color_contrast_ratio( $background, '#111111' );
	$white_contrast = sm_hex_color_contrast_ratio( $background, '#ffffff' );

	return $white_contrast >= $black_contrast ? '#ffffff' : '#111111';
}

/**
 * Pick an accessible accent color for a contextual variation.
 *
 * @since 2.0.0
 *
 * @param string $background Background hex color.
 * @param string $source Source hex color.
 * @param string $fallback Fallback foreground color.
 *
 * @return string
 */
function style_manager_get_accessible_contextual_accent( string $background, string $source, string $fallback ): string {
	if ( sm_hex_color_contrast_ratio( $background, $source ) >= 2.5 ) {
		return $source;
	}

	$enhanced_source = strtolower( $fallback ) === '#ffffff'
		? sm_mix_hex_colors( $source, '#ffffff', 0.35 )
		: sm_mix_hex_colors( $source, '#000000', 0.35 );

	if ( sm_hex_color_contrast_ratio( $background, $enhanced_source ) >= 2.5 ) {
		return $enhanced_source;
	}

	return $fallback;
}

/**
 * Blend two hex colors using linear RGB interpolation.
 *
 * @since 2.0.0
 *
 * @param string $base Base color.
 * @param string $mix Mix color.
 * @param float  $ratio Mix ratio between 0 and 1.
 *
 * @return string
 */
function style_manager_mix_hex_colors( string $base, string $mix, float $ratio ): string {
	$ratio = max( 0, min( 1, $ratio ) );
	$base_rgb = sm_hex_to_rgb_channels( $base );
	$mix_rgb  = sm_hex_to_rgb_channels( $mix );

	$red   = (int) round( $base_rgb[0] * ( 1 - $ratio ) + $mix_rgb[0] * $ratio );
	$green = (int) round( $base_rgb[1] * ( 1 - $ratio ) + $mix_rgb[1] * $ratio );
	$blue  = (int) round( $base_rgb[2] * ( 1 - $ratio ) + $mix_rgb[2] * $ratio );

	return sprintf( '#%02x%02x%02x', $red, $green, $blue );
}

/**
 * Compute WCAG contrast ratio between two hex colors.
 *
 * @since 2.0.0
 *
 * @param string $color_a First color.
 * @param string $color_b Second color.
 *
 * @return float
 */
function style_manager_hex_color_contrast_ratio( string $color_a, string $color_b ): float {
	$luminance_a = sm_hex_color_relative_luminance( $color_a );
	$luminance_b = sm_hex_color_relative_luminance( $color_b );
	$light       = max( $luminance_a, $luminance_b );
	$dark        = min( $luminance_a, $luminance_b );

	return ( $light + 0.05 ) / ( $dark + 0.05 );
}

/**
 * Compute relative luminance for a hex color.
 *
 * @since 2.0.0
 *
 * @param string $color Hex color.
 *
 * @return float
 */
function style_manager_hex_color_relative_luminance( string $color ): float {
	$channels = sm_hex_to_rgb_channels( $color );
	$linear   = array_map(
		static function ( int $channel ): float {
			$channel = $channel / 255;

			if ( $channel <= 0.03928 ) {
				return $channel / 12.92;
			}

			return pow( ( $channel + 0.055 ) / 1.055, 2.4 );
		},
		$channels
	);

	return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

/**
 * Convert a hex color into integer RGB channels.
 *
 * @since 2.0.0
 *
 * @param string $color Hex color.
 *
 * @return int[]
 */
function style_manager_hex_to_rgb_channels( string $color ): array {
	$color = ltrim( strtolower( $color ), '#' );

	if ( strlen( $color ) === 3 ) {
		$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
	}

	return [
		hexdec( substr( $color, 0, 2 ) ),
		hexdec( substr( $color, 2, 2 ) ),
		hexdec( substr( $color, 4, 2 ) ),
	];
}

/**
 * @since   2.0.0
 *
 * @param object[] $palettes
 *
 * @return string
 */
function style_manager_palettes_output( array $palettes ): string {
	$output = '';

	foreach ( $palettes as $palette ) {

		if ( ! empty( $palette->variations ) ) {
			$output .= sm_get_palette_css( $palette );
		} else {
			$output .= sm_get_legacy_palette_css( $palette );
		}
	}

	return $output;
}

/**
 * @param object $palette
 *
 * @return string
 */
function style_manager_get_palette_css( $palette ): string {
	$output = '';
	$id = $palette->id;
	$variation = intval( get_option( 'sm_site_color_variation', 1 ) );

	$paletteSelector = '.sm-palette-' . $id;
	$darkPaletteSelector = '.is-dark .sm-palette-' . $id;
	$paletteShiftedSelector = '.sm-palette-' . $id . '.sm-palette--shifted';

	if ( ( string ) $id === '1' ) {
		$paletteSelector = 'html, ' . $paletteSelector;
		$darkPaletteSelector = 'html.is-dark, ' . $darkPaletteSelector;
	}

	$output .= $paletteSelector . ' { ' . PHP_EOL;
	for ( $i = 0; $i < 12; $i++ ) {
		$output .= sm_get_variation_css_variables( $palette->variations, $i, $variation - 1 );
	}
	$output .= '}' . PHP_EOL;

	$output .= $darkPaletteSelector . ' { ' . PHP_EOL;
	for ( $i = 0; $i < 12; $i++ ) {
		$output .= sm_get_variation_css_variables( $palette->darkVariations, $i, $variation - 1 );
	}
	$output .= '}' . PHP_EOL;

	$output .= $paletteShiftedSelector . ' { ' . PHP_EOL;
	for ( $i = 0; $i < 12; $i++ ) {
		$output .= sm_get_variation_css_variables( $palette->variations, $i, $palette->sourceIndex );
	}
	$output .= '}' . PHP_EOL;

	return $output;
}

function style_manager_get_variation_css_variables( $variations, $index, $offset = 0 ): string {
	$output = '';

	$variation = $variations[ ( $index + $offset ) % 12 ];

	foreach ( $variation as $key => $value ) {
		$output .= '--sm-' . $key . '-color-' . ( $index + 1 ) . ': ' . $value . '; ';
	}

	return $output;
}

/**
 * @param object $palette
 *
 * @return string
 */
function style_manager_get_legacy_palette_css( $palette ): string {
	$output = '';

	$variation = intval( get_option( 'sm_site_color_variation', 1 ) );
	$sourceIndex = $palette->sourceIndex;

	$output .= 'html { ' . PHP_EOL;
	$output .= sm_get_initial_color_variables( $palette );
	$output .= sm_get_variables_css( $palette, $variation - 1 );
	$output .= sm_get_variables_css( $palette, $sourceIndex, false, true );
	$output .= '}' . PHP_EOL;

	$output .= '.is-dark { ' . PHP_EOL;
	$output .= sm_get_variables_css( $palette, $variation - 1, true );
	$output .= sm_get_variables_css( $palette, $sourceIndex, true, true );
	$output .= '}' . PHP_EOL;

	$selector = '.sm-palette-' . $palette->id;

	if ( ( string ) $palette->id === '1' ) {
		$selector = 'html, .sm-palette-' . $palette->id;
	}

	$output .= $selector . ' { ' . PHP_EOL;
	$output .= sm_get_apply_palette_variables( $palette->id );
	$output .= '}' . PHP_EOL;

	$output .= '.sm-palette-' . $palette->id . '.sm-palette--shifted { ' . PHP_EOL;
	$output .= sm_get_apply_palette_variables( $palette->id, '-shifted' );
	$output .= '}' . PHP_EOL;

	return $output;
}

function style_manager_get_apply_palette_variables( $id, $suffix = '' ): string {
	$output = '';

	for ( $i = 1; $i <= 12; $i++ ) {
		$output .= '--sm-bg-color-' . $i . ': var(--sm-color-palette-' . $id . '-bg-color-' . $i . $suffix . ');' . PHP_EOL;
		$output .= '--sm-accent-color-' . $i . ': var(--sm-color-palette-' . $id . '-accent-color-' . $i . $suffix . ');' . PHP_EOL;
		$output .= '--sm-fg1-color-' . $i . ': var(--sm-color-palette-' . $id . '-fg1-color-' . $i . $suffix . ');' . PHP_EOL;
		$output .= '--sm-fg2-color-' . $i . ': var(--sm-color-palette-' . $id . '-fg2-color-' . $i . $suffix . ');' . PHP_EOL;
	}

	return $output;
}

/**
 * @since   2.0.0
 *
 * @param object $palette
 *
 * @return string
 */
function style_manager_get_initial_color_variables( $palette ): string {
	$colors = $palette->colors;
	$textColors = $palette->textColors;
	$id = $palette->id;
	$prefix = '--sm-color-palette-';

	$output = '';

	foreach ( $colors as $index => $color ) {
		$output .= $prefix . $id . '-color-' . ( $index + 1 ) . ': ' . $color->value . ';' . PHP_EOL;
	}

	foreach ( $textColors as $index => $color ) {
		$output .= $prefix . $id . '-text-color-' . ( $index + 1 ) . ': ' . $color->value . ';' . PHP_EOL;
	}

	return $output;
}

/**
 * @since   2.0.0
 *
 * @param object $palette
 * @param int    $offset
 * @param bool   $isDark
 * @param bool   $isShifted
 *
 * @return string
 */
function style_manager_get_variables_css( $palette, int $offset = 0, bool $isDark = false, bool $isShifted = false ): string {
	$colors = $palette->colors;
	$count = count( $colors );

	$output = '';

	foreach ( $colors as $index => $color ) {
		$oldColorIndex = ( $index + $offset ) % $count;

		if ( $isDark ) {
			if ( $oldColorIndex < $count / 2 ) {
				$oldColorIndex = 11 - $oldColorIndex;
			} else {
				continue;
			}
		}

		$output .= sm_get_color_variables( $palette, $index, $oldColorIndex, $isShifted );
	}

	return $output;
}

/**
 * @since   2.0.0
 *
 * @param object $palette
 * @param int    $newColorIndex
 * @param int    $oldColorIndex
 * @param bool   $isShifted
 *
 * @return string
 */
function style_manager_get_color_variables( $palette, int $newColorIndex, int $oldColorIndex, bool $isShifted ): string {
	$colors = $palette->colors;
	$id = $palette->id;
	$count = count( $colors );
	$lightColorsCount = $palette->lightColorsCount ?? $count / 2;

	$accentColorIndex = ( $oldColorIndex + $count / 2 ) % $count;
	$prefix = '--sm-color-palette-';
	$suffix = $isShifted ? '-shifted' : '';

	$output = '';

	$output .= $prefix . $id . '-bg-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-color-' . ( $oldColorIndex + 1 ) . ');' . PHP_EOL;
	$output .= $prefix . $id . '-accent-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-color-' . ( $accentColorIndex + 1 ) . ');' . PHP_EOL;

	if ( $oldColorIndex < $lightColorsCount ) {
		$output .= $prefix . $id . '-fg1-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-text-color-1);' . PHP_EOL;
		$output .= $prefix . $id . '-fg2-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-text-color-2);' . PHP_EOL;
	} else {
		$output .= $prefix . $id . '-fg1-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-color-1);' . PHP_EOL;
		$output .= $prefix . $id . '-fg2-color-' . ( $newColorIndex + 1 ) . $suffix . ': var(' . $prefix . $id . '-color-1);' . PHP_EOL;
	}

	return $output;
}

/**
 * @since   2.0.0
 *
 * @return array
 */
function style_manager_get_fallback_palettes(): array {

	$order = [
		'primary',
		'secondary',
		'tertiary',
		'quinary',
		'senary',
		'septenary',
		'octonary',
		'nonary',
		'denary'
	];

	$options_details = \Pixelgrade\StyleManager\get_option_details_all();

	$color_control_ids = [
		'sm_color_primary',
		'sm_color_secondary',
		'sm_color_tertiary',
	];

	$lighter = sm_get_fallback_color_value( 'sm_light_primary' );
	$light = sm_get_fallback_color_value( 'sm_light_tertiary' );
	$text_color = sm_get_fallback_color_value( 'sm_dark_secondary' );
	$dark = sm_get_fallback_color_value( 'sm_dark_primary' );
	$darker = sm_get_fallback_color_value( 'sm_dark_tertiary' );

	$palettes = [];

	foreach ( $color_control_ids as $index => $control_id ) {

		if ( empty( $options_details[ $control_id ] ) ) {
			continue;
		}

		$color = sm_get_fallback_color_value( $control_id );

		$colors = [
			$lighter,
			$light,
			$light,
			$light,
			$color,
			$color,
			$color,
			$dark,
			$dark,
			$dark,
			$darker,
			'#000000',
		];

		$color_objects = [];

		foreach ( $colors as $color ) {
			$obj = ( object ) [
				'value' => $color
			];

			$color_objects[] = $obj;
		}

		$textColors = [
			$text_color,
			$text_color,
		];

		$textColor_objects = [];

		foreach ( $textColors as $color ) {
			$obj = ( object ) [
				'value' => $color
			];

			$textColor_objects[] = $obj;
		}

		$label = $order[ $index + 1 ];

		if ( $index === 0 ) {
			$label = __( 'Brand', '__plugin_txtd' ) . ' ' . $label;
		} else {
			$label = ucfirst( $label );
		}

		$palettes[] = ( object ) [
			'colors'      => $color_objects,
			'textColors'  => $textColor_objects,
			'source'      => $color,
			'sourceIndex' => 6,
			'label'       => $label,
			'id'          => $index + 1
		];
	}

	return $palettes;
}

function style_manager_get_fallback_color_value( $id ) {

	$color = \Pixelgrade\StyleManager\get_option( $id . '_final' );

//	if ( empty( $color ) ) {
//		$color = PixCustomifyPlugin()->get_option( $id );
//	}
//
//	if ( empty( $color ) ) {
//		$config = PixCustomifyPlugin()->get_option_details( $id );
//
//		if ( isset( $config['default'] ) ) {
//			$color = $config['default'];
//		}
//	}

	return $color;
}

if ( ! function_exists( 'pixelgrade_option' ) ) {
	/**
	 * Get option value from the database
	 *
	 * @param string $option_id           The option name.
	 * @param mixed  $default             Optional. The default value to return when the option was not found or saved.
	 * @param bool   $force_given_default Optional. Ignored.
	 *
	 * @return mixed
	 */
	function pixelgrade_option( $option_id, $default = null, $force_given_default = false ) {
		return \Pixelgrade\StyleManager\get_option( $option_id, $default );
	}
}

if ( ! function_exists( 'sm_filter_user_palettes' ) ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- intentionally theme-overridable legacy function name.
	function sm_filter_user_palettes( $palette ): bool {
		$id = (string) $palette->id;
		return substr( $id, 0, 1 ) !== '_';
	}
}

function style_manager_advanced_palette_output_cb( string $value, string $selector, string $property ): string {
	$palettes = json_decode( $value );

	if ( empty( $palettes ) ) {
		$palettes = sm_get_fallback_palettes();
	}

	if ( ! is_array( $palettes ) ) {
		$palettes = [];
	}

	return sm_palettes_output(
		sm_merge_palettes_by_id(
			$palettes,
			sm_get_runtime_palettes( $palettes )
		)
	);
}

// No strict type on $value: the saved option can be an int (e.g. saved through
// a changeset with a numeric JS value) and this callback ignores it anyway.
function style_manager_site_color_variation_cb( $value, string $selector, string $property ): string {
	return '';
}

/*
 * Back-compat aliases for the renamed style_manager_* functions.
 * Kept so existing themes (Anima) and Nova Blocks that call the legacy
 * sm_* names keep working. The canonical, prefixed API is style_manager_*.
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_color_select_darker_config().
function sm_get_color_select_darker_config( ...$args ) { return style_manager_get_color_select_darker_config( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_color_select_dark_config().
function sm_get_color_select_dark_config( ...$args ) { return style_manager_get_color_select_dark_config( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_color_select_dark_cb().
function sm_color_select_dark_cb( ...$args ) { return style_manager_color_select_dark_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_color_select_darker_cb().
function sm_color_select_darker_cb( ...$args ) { return style_manager_color_select_darker_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_color_switch_darker_config().
function sm_get_color_switch_darker_config( ...$args ) { return style_manager_get_color_switch_darker_config( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_color_switch_dark_config().
function sm_get_color_switch_dark_config( ...$args ) { return style_manager_get_color_switch_dark_config( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_color_switch_dark_cb().
function sm_color_switch_dark_cb( ...$args ) { return style_manager_color_switch_dark_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_color_switch_darker_cb().
function sm_color_switch_darker_cb( ...$args ) { return style_manager_color_switch_darker_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_output_from_color_config().
function sm_get_palette_output_from_color_config( ...$args ) { return style_manager_get_palette_output_from_color_config( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_saved_palettes().
function sm_get_saved_palettes( ...$args ) { return style_manager_get_saved_palettes( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_runtime_palettes().
function sm_get_runtime_palettes( ...$args ) { return style_manager_get_runtime_palettes( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_runtime_payload().
function sm_get_palette_runtime_payload( ...$args ) { return style_manager_get_palette_runtime_payload( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_merge_palettes_by_id().
function sm_merge_palettes_by_id( ...$args ) { return style_manager_merge_palettes_by_id( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palettes_for_runtime_context().
function sm_get_palettes_for_runtime_context( ...$args ) { return style_manager_get_palettes_for_runtime_context( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palettes_for_current_request().
function sm_get_palettes_for_current_request( ...$args ) { return style_manager_get_palettes_for_current_request( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_output_for_runtime_context().
function sm_get_palette_output_for_runtime_context( ...$args ) { return style_manager_get_palette_output_for_runtime_context( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_output_for_current_request().
function sm_get_palette_output_for_current_request( ...$args ) { return style_manager_get_palette_output_for_current_request( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_runtime_preview_payload().
function sm_get_palette_runtime_preview_payload( ...$args ) { return style_manager_get_palette_runtime_preview_payload( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_build_contextual_palette_from_color().
function sm_build_contextual_palette_from_color( ...$args ) { return style_manager_build_contextual_palette_from_color( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_build_contextual_palette_variation().
function sm_build_contextual_palette_variation( ...$args ) { return style_manager_build_contextual_palette_variation( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_pick_contextual_text_color().
function sm_pick_contextual_text_color( ...$args ) { return style_manager_pick_contextual_text_color( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_accessible_contextual_accent().
function sm_get_accessible_contextual_accent( ...$args ) { return style_manager_get_accessible_contextual_accent( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_mix_hex_colors().
function sm_mix_hex_colors( ...$args ) { return style_manager_mix_hex_colors( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_hex_color_contrast_ratio().
function sm_hex_color_contrast_ratio( ...$args ) { return style_manager_hex_color_contrast_ratio( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_hex_color_relative_luminance().
function sm_hex_color_relative_luminance( ...$args ) { return style_manager_hex_color_relative_luminance( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_hex_to_rgb_channels().
function sm_hex_to_rgb_channels( ...$args ) { return style_manager_hex_to_rgb_channels( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_palettes_output().
function sm_palettes_output( ...$args ) { return style_manager_palettes_output( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_palette_css().
function sm_get_palette_css( ...$args ) { return style_manager_get_palette_css( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_variation_css_variables().
function sm_get_variation_css_variables( ...$args ) { return style_manager_get_variation_css_variables( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_legacy_palette_css().
function sm_get_legacy_palette_css( ...$args ) { return style_manager_get_legacy_palette_css( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_apply_palette_variables().
function sm_get_apply_palette_variables( ...$args ) { return style_manager_get_apply_palette_variables( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_initial_color_variables().
function sm_get_initial_color_variables( ...$args ) { return style_manager_get_initial_color_variables( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_variables_css().
function sm_get_variables_css( ...$args ) { return style_manager_get_variables_css( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_color_variables().
function sm_get_color_variables( ...$args ) { return style_manager_get_color_variables( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_fallback_palettes().
function sm_get_fallback_palettes( ...$args ) { return style_manager_get_fallback_palettes( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_get_fallback_color_value().
function sm_get_fallback_color_value( ...$args ) { return style_manager_get_fallback_color_value( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_advanced_palette_output_cb().
function sm_advanced_palette_output_cb( ...$args ) { return style_manager_advanced_palette_output_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- back-compat alias for style_manager_site_color_variation_cb().
function sm_site_color_variation_cb( ...$args ) { return style_manager_site_color_variation_cb( ...$args ); }

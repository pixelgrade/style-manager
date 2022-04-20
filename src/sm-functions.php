<?php
/**
 * Style Manager functions to be used by themes mainly.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

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
function sm_get_color_select_darker_config( $label, $selector, $default, $properties = [ 'color' ] ): array {
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
function sm_get_color_select_dark_config( $label, $selector, $default, $properties = [ 'color' ], $isDarker = false ): array {

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
function sm_color_select_dark_cb( string $value, string $selector, string $property ): string {
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
function sm_color_select_darker_cb( string $value, string $selector, string $property ): string {
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
function sm_get_color_switch_darker_config( $label, $selector, $default, $coloration = 2, $properties = [ 'color' ] ): array {
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
function sm_get_color_switch_dark_config( $label, $selector, $default, $coloration = 2, $properties = [ 'color' ], $isDarker = false ): array {

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
function sm_color_switch_dark_cb( bool $value, string $selector, string $property ): string {
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
function sm_color_switch_darker_cb( bool $value, string $selector, string $property ): string {
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
function sm_get_palette_output_from_color_config( string $value ): string {
	$output = '';

	$palettes = json_decode( $value );

	if ( empty( $palettes ) ) {
		$palettes = sm_get_fallback_palettes();
	}

	$output .= sm_palettes_output( $palettes );

	return $output;
}

/**
 * @since   2.0.0
 *
 * @param object[] $palettes
 *
 * @return string
 */
function sm_palettes_output( array $palettes ): string {
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
function sm_get_palette_css( $palette ): string {
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

function sm_get_variation_css_variables( $variations, $index, $offset = 0 ): string {
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
function sm_get_legacy_palette_css( $palette ): string {
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

function sm_get_apply_palette_variables( $id, $suffix = '' ): string {
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
function sm_get_initial_color_variables( $palette ): string {
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
function sm_get_variables_css( $palette, int $offset = 0, bool $isDark = false, bool $isShifted = false ): string {
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
function sm_get_color_variables( $palette, int $newColorIndex, int $oldColorIndex, bool $isShifted ): string {
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
function sm_get_fallback_palettes(): array {

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

function sm_get_fallback_color_value( $id ) {

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
	function sm_filter_user_palettes( $palette ): bool {
		$id = (string) $palette->id;
		return substr( $id, 0, 1 ) !== '_';
	}
}

function sm_advanced_palette_output_cb( string $value, string $selector, string $property ): string {
	return sm_get_palette_output_from_color_config( $value );
}

<?php
/**
 * Style Manager functions to be used by themes mainly.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

if ( ! \in_array( \PHP_SAPI, [ 'cli', 'phpdbg' ], true ) ) {
	defined( 'ABSPATH' ) || exit;
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
 * Rational soft-clamp used by the Base + Pitch rail scale.
 *
 * A smooth asymptote toward 600px (knee sharpness 12): small values pass through
 * almost untouched, large values ease toward the ceiling instead of ballooning.
 *
 * @since 2.4.0
 *
 * @param float $x
 *
 * @return float
 */
function style_manager_rail_soft( float $x ): float {
	$ceil  = 600.0;
	$sharp = 12.0;

	return $x / pow( 1.0 + pow( $x / $ceil, $sharp ), 1.0 / $sharp );
}

/**
 * Resolve the Small/Medium/Large rail widths from the two rail settings.
 *
 * Migration contract (see also the JS twins and the standalone contract test):
 *
 *  - BOTH unset  -> null (emit nothing; legacy-until-touched, byte-identical),
 *    unless the Small-only value (sm_rail_small) is saved: then ONLY Small is
 *    set ({ small, medium: null, large: null }) and Medium/Large keep the
 *    consumer's defaults (Nova: 330 / 400). This is the slot the saved Content
 *    Inset used to fill before Nova decoupled the Small rail from it
 *    (nova-blocks#655), so a pinned Small rail changes nothing else.
 *  - base set, pitch UNSET -> v1 compatibility: the shipped fixed ratios
 *    (Medium = base*330/288, Large = base*400/288). Keeps the handful of
 *    v1-touched sites (dev/lab only — v1 never reached starters) byte-stable.
 *  - pitch SET (v2 owns emission): Small = base, Medium = base*mult,
 *    Large = base*mult^2, each passed through the soft ceiling. The multiplier is
 *    a quadratic map of pitch (0-45 deg) onto x1 .. x sqrt(3). When only pitch is
 *    touched the base defaults to 300. mult >= 1 so S <= M <= L always holds;
 *    inversion is structurally impossible.
 *
 * @since 2.4.0
 *
 * @param mixed $base_raw  The sm_rail_scale value.
 * @param mixed $pitch_raw The sm_rail_pitch value.
 * @param mixed $small_raw The sm_rail_small value (Small-only; used only while
 *                         Base and Pitch are both unset).
 *
 * @return array|null { small, medium, large } (integers; Medium/Large null in
 *                    Small-only mode) or null when unset.
 */
function style_manager_rail_widths( $base_raw, $pitch_raw, $small_raw = '' ): ?array {
	$base_set  = is_numeric( $base_raw ) && (float) $base_raw > 0;
	// Pitch 0 (Flat) is a valid, deliberate value — only '' / non-numeric is unset.
	$pitch_set = is_numeric( $pitch_raw );

	if ( ! $base_set && ! $pitch_set ) {
		$small = style_manager_rail_small_value( $small_raw );

		return null === $small ? null : [
			'small'  => $small,
			'medium' => null,
			'large'  => null,
		];
	}

	if ( $pitch_set ) {
		$base  = $base_set ? (float) $base_raw : 300.0;
		$f     = (float) $pitch_raw / 45.0;
		$mult  = 1.0 + ( sqrt( 3.0 ) - 1.0 ) * $f * $f;
		$small  = style_manager_rail_soft( $base );
		$medium = style_manager_rail_soft( $base * $mult );
		$large  = style_manager_rail_soft( $base * $mult * $mult );
	} else {
		$base   = (float) $base_raw;
		$small  = $base;
		$medium = $base * 330.0 / 288.0;
		$large  = $base * 400.0 / 288.0;
	}

	return [
		'small'  => (int) round( $small ),
		'medium' => (int) round( $medium ),
		'large'  => (int) round( $large ),
	];
}

/**
 * Normalize a Small-only rail value (sm_rail_small).
 *
 * Kept exact (not rounded) so a pin written from a saved Content Inset renders
 * the very width the old inset coupling produced.
 *
 * @since 2.6.1
 *
 * @param mixed $raw The sm_rail_small value.
 *
 * @return int|float|null The width in rail tokens, or null when unset/invalid.
 */
function style_manager_rail_small_value( $raw ) {
	if ( ! is_numeric( $raw ) || (float) $raw <= 0 ) {
		return null;
	}

	$value = (float) $raw;

	return floor( $value ) === $value ? (int) $value : $value;
}

/**
 * CSS callback for the rail-scale (sidebar/rail) tokens.
 *
 * Emits the per-side-ready Small/Medium/Large rail tokens. The migration/compat
 * contract lives in style_manager_rail_widths(). The JS twins live in
 * `src/Screen/Customizer/Preview.php` (Customizer preview) and
 * `src/_js/site-editor/preview.js` (Site Editor preview) — keep them in sync.
 *
 * The base value arrives as $value; the pitch value is read from its option so a
 * single callback resolves the whole contract (pitch has no CSS of its own).
 *
 * @since   2.4.0
 *
 * @param mixed  $value    The rail-scale base value.
 * @param string $selector The CSS selector (`:root`).
 * @param string $property The rail token (`--sm-rail-small|medium|large`).
 * @param string $unit     The CSS unit (empty for the unitless rail tokens).
 *
 * @return string
 */
function style_manager_rail_scale_css_cb( $value, string $selector, string $property, string $unit = '' ): string {
	$widths = style_manager_rail_widths( $value, get_option( 'sm_rail_pitch', '' ), get_option( 'sm_rail_small', '' ) );

	if ( null === $widths ) {
		return '';
	}

	switch ( $property ) {
		case '--sm-rail-small':
			$out = $widths['small'];
			break;
		case '--sm-rail-medium':
			$out = $widths['medium'];
			break;
		case '--sm-rail-large':
			$out = $widths['large'];
			break;
		default:
			return '';
	}

	// Small-only mode leaves Medium/Large unset (the consumer's defaults).
	if ( null === $out ) {
		return '';
	}

	return $selector . ' { ' . $property . ': ' . (string) $out . $unit . '; }' . PHP_EOL;
}

/**
 * CSS callback for the rail Pitch setting.
 *
 * Pitch carries no CSS of its own — the Small/Medium/Large tokens are emitted by
 * sm_rail_scale (which reads the pitch value). This exists only so the pitch
 * setting is bound in the live preview, where its JS twin recomputes the
 * sm_rail_scale style tag on change. On the frontend it is inert.
 *
 * @since 2.4.0
 *
 * @return string Always empty.
 */
function style_manager_rail_pitch_css_cb( $value, string $selector, string $property, string $unit = '' ): string {
	return '';
}

/**
 * CSS callback for the Small-only rail setting (sm_rail_small).
 *
 * Like Pitch it carries no CSS of its own — sm_rail_scale reads it and emits
 * `--sm-rail-small`. It exists only so the setting is bound in the live
 * preview, where its JS twin recomputes the sm_rail_scale style tag.
 *
 * @since 2.6.1
 *
 * @return string Always empty.
 */
function style_manager_rail_small_css_cb( $value, string $selector, string $property, string $unit = '' ): string {
	return '';
}

/**
 * CSS callback: the Content Inset opt-in signal.
 *
 * `sm_content_inset` always emits `--sm-content-inset` (its registered default
 * is 230), so a consumer cannot tell a saved value from the default. Nova
 * Blocks' layout engine applies the Layout board contract (content lines inset
 * by Content Inset) only once the user has saved a Content Inset, so sites that
 * never touched it render byte-identically. This emits
 * `--sm-content-inset-explicit: 1` only when the option exists in the database
 * (including a Customizer changeset preview, which filters get_option()).
 *
 * @since 2.6.1
 *
 * @param mixed  $value    The resolved Content Inset value (ignored).
 * @param string $selector The CSS selector (`:root`).
 * @param string $property The signal property (`--sm-content-inset-explicit`).
 * @param string $unit     Ignored.
 *
 * @return string
 */
function style_manager_content_inset_explicit_css_cb( $value, string $selector, string $property, string $unit = '' ): string {
	if ( ! style_manager_content_inset_is_explicit() ) {
		return '';
	}

	return $selector . ' { ' . $property . ': 1; }' . PHP_EOL;
}

/**
 * Whether the user has saved a Content Inset value.
 *
 * @since 2.6.1
 *
 * @return bool
 */
function style_manager_content_inset_is_explicit(): bool {
	$saved = get_option( 'sm_content_inset', null );

	return null !== $saved && false !== $saved && '' !== $saved && is_numeric( $saved );
}

/**
 * Resolve the theme's small-screen font-size slope from the Phone Heading Scale.
 *
 * The value is the share (0-100) of their desktop size that large roles keep on
 * the narrowest phones: 100 keeps desktop sizes, 0 shrinks them down to the
 * theme's minimum font size. Anima interpolates every role between its desktop
 * size at 1440px and `desktop - (desktop - minimum) * slope` at 320px, so the
 * slope is `1 - value / 100`, clamped to 0-1. Roles near the minimum barely
 * move, so in practice this owns the heading hierarchy on phones.
 *
 * An unset ('' / non-numeric) value returns null: emit nothing and keep the
 * theme's own slope (legacy-until-touched, byte-identical rendering).
 *
 * @since 2.6.1
 *
 * @param mixed $value The sm_font_mobile_scale value.
 *
 * @return float|null
 */
function style_manager_font_mobile_scale_slope( $value ): ?float {
	if ( ! is_numeric( $value ) ) {
		return null;
	}

	$share = max( 0.0, min( 100.0, (float) $value ) );

	return round( ( 100.0 - $share ) / 100.0, 4 );
}

/**
 * CSS callback for the Phone Heading Scale (sm_font_mobile_scale).
 *
 * Sets only the theme's slope below the 1440px typography breakpoint, where
 * desktop role sizes are reached, so no desktop size and no connected field
 * changes. The JS twins live in `src/_js/utils/font-mobile-scale.js` (Site
 * Editor preview) and `src/Screen/Customizer/Preview.php` (Customizer
 * preview) — keep them in sync.
 *
 * @since 2.6.1
 *
 * @param mixed  $value    The sm_font_mobile_scale value.
 * @param string $selector The CSS selector (`:root`).
 * @param string $property The slope custom property.
 * @param string $unit     Unused; the slope is unitless.
 *
 * @return string
 */
function style_manager_font_mobile_scale_css_cb( $value, string $selector, string $property, string $unit = '' ): string {
	$slope = style_manager_font_mobile_scale_slope( $value );

	if ( null === $slope ) {
		return '';
	}

	return '@media not screen and (min-width: 1440px) { ' . $selector . ' { ' . $property . ': ' . (string) $slope . '; } }' . PHP_EOL;
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
function style_manager_color_switch_dark_cb( $value, string $selector, string $property ): string {
	$color = 'fg1';

	// Tolerate loose toggle values: the saved option can arrive as '' / '1' / 0 (e.g. from an
	// imported changeset or JS) instead of a strict bool. Under the caller's strict_types a
	// non-bool would TypeError here and blank the whole front end during wp_head.
	if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
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
function style_manager_color_switch_darker_cb( $value, string $selector, string $property ): string {
	$color = 'fg2';

	// Tolerate loose toggle values: the saved option can arrive as '' / '1' / 0 (e.g. from an
	// imported changeset or JS) instead of a strict bool. Under the caller's strict_types a
	// non-bool would TypeError here and blank the whole front end during wp_head.
	if ( filter_var( $value, FILTER_VALIDATE_BOOLEAN ) ) {
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
 * Determine if the Style Manager fields config is currently being filtered.
 *
 * @since 2.3.0
 *
 * @return bool
 */
function style_manager_is_filtering_fields_config(): bool {
	if ( function_exists( 'doing_filter' ) && doing_filter( 'style_manager/filter_fields' ) ) {
		return true;
	}

	$current_filters = $GLOBALS['wp_current_filter'] ?? [];
	if ( ! is_array( $current_filters ) ) {
		return false;
	}

	return in_array( 'style_manager/filter_fields', $current_filters, true );
}

/**
 * Get bundled palette defaults without consulting option details.
 *
 * @since 2.3.0
 *
 * @return array
 */
function style_manager_get_bundled_fallback_palettes(): array {
	static $palettes = null;

	if ( null !== $palettes ) {
		return $palettes;
	}

	$palettes = [];
	$file     = __DIR__ . '/Customize/sm_advanced_palette_output.json';
	if ( is_readable( $file ) ) {
		$decoded = json_decode( (string) file_get_contents( $file ) );
		if ( is_array( $decoded ) ) {
			$palettes = $decoded;
		}
	}

	return $palettes;
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
 * The contrast-floor colour roles every palette variation carries.
 *
 * Each role is derived from the variation's own colours and guaranteed a minimum WCAG contrast
 * against that variation's bg, so it works on every surface; the key becomes
 * `--sm-<key>-color-N`, and the SCSS `apply-variation` mixin maps it to `--sm-current-<key>-color`.
 * The first role is quiet text (`fg-muted`, style-manager#214). A later role (for example the
 * primary / secondary rule colours of nova-blocks#668) is one more entry here AND in the JS twin
 * `CONTRAST_FLOOR_ROLES` (`src/_js/shared/contrast-floor.js`); both are pinned to
 * `tests/phpunit/fixtures/quiet-text/corpus.json`, so there is no filter: a server-only change
 * would drift from the Customizer and Site Editor previews.
 *
 * - source:      the variation colour the role starts from.
 * - minContrast: the floor against the variation's bg.
 * - maxMix:      how far toward bg the role may soften (0 = the source, 1 = all the way).
 *
 * @since 2.7.0
 *
 * @return array<string, array{source: string, minContrast: float, maxMix: float}>
 */
function style_manager_get_contrast_floor_roles(): array {
	return [
		'fg-muted' => [
			'source'      => 'fg1',
			'minContrast' => 4.5,
			'maxMix'      => 1.0,
		],
	];
}

/**
 * The softest colour on the source → background line that keeps a contrast floor.
 *
 * Mirrors `getContrastFloorColor()` in `src/_js/shared/contrast-floor.js` byte for byte:
 *
 * 1. Walk from `$source` toward `$background` in 1/200 steps, at most `$max_mix` of the way, and
 *    keep the last step that still measures >= `$min_contrast` against the background.
 * 2. When `$source` itself is below the floor, walk from it away from the background (toward
 *    black or white, whichever contrasts more with it) and keep the first step that passes.
 *    Black or white always clears 4.58:1, so any floor up to that is guaranteed.
 * 3. A value that is not a plain hex colour is passed through unchanged.
 *
 * @since 2.7.0
 *
 * @param string $background   The variation's ground (bg).
 * @param string $source       The colour the role starts from (e.g. fg1).
 * @param float  $min_contrast The contrast floor against `$background`.
 * @param float  $max_mix      How far toward `$background` the colour may soften, 0–1.
 *
 * @return string A lowercase #rrggbb colour, or `$source` unchanged when either input is not hex.
 */
function style_manager_get_contrast_floor_color( string $background, string $source, float $min_contrast, float $max_mix = 1.0 ): string {
	static $cache = [];

	$steps      = 200;
	$bg_hex     = style_manager_normalize_short_hex( $background );
	$source_hex = style_manager_normalize_short_hex( $source );

	if ( null === $bg_hex || null === $source_hex ) {
		return $source;
	}

	$max_steps = min( $steps - 1, (int) round( max( 0.0, min( 1.0, $max_mix ) ) * $steps ) );
	$key       = $bg_hex . '|' . $source_hex . '|' . $min_contrast . '|' . $max_steps;

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$bg           = sm_hex_to_rgb_channels( $bg_hex );
	$from         = sm_hex_to_rgb_channels( $source_hex );
	$bg_luminance = style_manager_rgb_channels_relative_luminance( $bg );

	/*
	 * `$step` / `$steps` of the way from `$from` to a target, rounded half up in exact integer
	 * math (PHP round() pre-rounds floats, JS Math.round() does not), then tested against the
	 * floor. Inlined, with a luminance lookup table, because this runs per palette variation on
	 * every uncached stylesheet render.
	 */
	$walk = static function ( array $target, int $step ) use ( $from, $steps, $bg_luminance, $min_contrast ): ?array {
		$mixed = [
			intdiv( 2 * ( $from[0] * ( $steps - $step ) + $target[0] * $step ) + $steps, 2 * $steps ),
			intdiv( 2 * ( $from[1] * ( $steps - $step ) + $target[1] * $step ) + $steps, 2 * $steps ),
			intdiv( 2 * ( $from[2] * ( $steps - $step ) + $target[2] * $step ) + $steps, 2 * $steps ),
		];

		$luminance = style_manager_rgb_channels_relative_luminance( $mixed );
		$ratio     = ( max( $luminance, $bg_luminance ) + 0.05 ) / ( min( $luminance, $bg_luminance ) + 0.05 );

		return $ratio >= $min_contrast ? $mixed : null;
	};

	$found = null;

	if ( null !== $walk( $from, 0 ) ) {
		$found = $from;

		// When every channel moves the same way, luminance (and so the contrast against bg)
		// changes monotonically along the walk, the passing steps form a prefix, and a binary
		// search finds the same last passing step the linear walk would.
		$monotonic = ( $bg[0] >= $from[0] && $bg[1] >= $from[1] && $bg[2] >= $from[2] )
			|| ( $bg[0] <= $from[0] && $bg[1] <= $from[1] && $bg[2] <= $from[2] );

		if ( $monotonic ) {
			$low  = 0;
			$high = $max_steps + 1;
			while ( $high - $low > 1 ) {
				$middle    = intdiv( $low + $high, 2 );
				$candidate = $walk( $bg, $middle );
				if ( null === $candidate ) {
					$high = $middle;
				} else {
					$low = $middle;
				}
			}
			$found = $low > 0 ? $walk( $bg, $low ) : $from;
		} else {
			for ( $step = 1; $step <= $max_steps; $step++ ) {
				$candidate = $walk( $bg, $step );

				if ( null === $candidate ) {
					break;
				}

				$found = $candidate;
			}
		}
	} else {
		$black_ratio = ( $bg_luminance + 0.05 ) / 0.05;
		$white_ratio = 1.05 / ( $bg_luminance + 0.05 );
		$extreme     = $black_ratio >= $white_ratio ? [ 0, 0, 0 ] : [ 255, 255, 255 ];

		for ( $step = 1; $step <= $steps; $step++ ) {
			$found = $walk( $extreme, $step );

			if ( null !== $found ) {
				break;
			}
		}
	}

	$result = null === $found ? $source_hex : sprintf( '#%02x%02x%02x', $found[0], $found[1], $found[2] );

	$cache[ $key ] = $result;

	return $result;
}

/**
 * Every contrast-floor role colour for one palette variation.
 *
 * @since 2.7.0
 *
 * @param object|array $variation A palette variation ({ bg, fg1, fg2, accent, … }).
 *
 * @return array<string, string> Role key => colour. Roles whose source or bg is missing are left out.
 */
function style_manager_get_contrast_floor_role_colors( $variation ): array {
	$variation = (array) $variation;
	$colors    = [];

	if ( empty( $variation['bg'] ) || ! is_string( $variation['bg'] ) ) {
		return $colors;
	}

	foreach ( style_manager_get_contrast_floor_roles() as $key => $role ) {
		$source = $variation[ $role['source'] ] ?? null;

		if ( ! empty( $source ) && is_string( $source ) ) {
			$colors[ $key ] = style_manager_get_contrast_floor_color( $variation['bg'], $source, (float) $role['minContrast'], (float) $role['maxMix'] );
		}
	}

	return $colors;
}

/**
 * The quiet-text colour (style-manager#214) for one ground and text colour: softer than the text,
 * never below 4.5:1 on the ground.
 *
 * @since 2.7.0
 *
 * @param string $background The variation's bg.
 * @param string $foreground The variation's fg1.
 *
 * @return string
 */
function style_manager_get_quiet_text_color( string $background, string $foreground ): string {
	$role = style_manager_get_contrast_floor_roles()['fg-muted'];

	return style_manager_get_contrast_floor_color( $background, $foreground, (float) $role['minContrast'], (float) $role['maxMix'] );
}

/**
 * A plain #rgb / #rrggbb colour as lowercase #rrggbb, or null.
 *
 * @since 2.7.0
 *
 * @param string $color Colour.
 *
 * @return string|null
 */
function style_manager_normalize_short_hex( string $color ): ?string {
	$color = strtolower( trim( $color ) );

	if ( 1 !== preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $color ) ) {
		return null;
	}

	if ( 4 === strlen( $color ) ) {
		$color = '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3];
	}

	return $color;
}

/**
 * WCAG relative luminance of integer RGB channels — the formula of
 * style_manager_hex_color_relative_luminance(), without the hex round-trip.
 *
 * @since 2.7.0
 *
 * @param int[] $channels RGB channels, 0–255.
 *
 * @return float
 */
function style_manager_rgb_channels_relative_luminance( array $channels ): float {
	static $linear = null;

	if ( null === $linear ) {
		$linear = [];
		for ( $channel = 0; $channel <= 255; $channel++ ) {
			$value              = $channel / 255;
			$linear[ $channel ] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
		}
	}

	return 0.2126 * $linear[ $channels[0] ] + 0.7152 * $linear[ $channels[1] ] + 0.0722 * $linear[ $channels[2] ];
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
	$roles     = style_manager_get_contrast_floor_roles();

	foreach ( $variation as $key => $value ) {
		// Contrast-floor roles are always derived below, so a stored value can never bypass the floor.
		if ( isset( $roles[ $key ] ) ) {
			continue;
		}

		$output .= '--sm-' . $key . '-color-' . ( $index + 1 ) . ': ' . $value . '; ';
	}

	// The quiet-text role (style-manager#214) and any later contrast-floor role.
	foreach ( style_manager_get_contrast_floor_role_colors( $variation ) as $key => $value ) {
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
	if ( style_manager_is_filtering_fields_config() ) {
		return style_manager_get_bundled_fallback_palettes();
	}

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
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- alias for style_manager_rail_scale_css_cb().
function sm_rail_scale_css_cb( ...$args ) { return style_manager_rail_scale_css_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- alias for style_manager_rail_pitch_css_cb().
function sm_rail_pitch_css_cb( ...$args ) { return style_manager_rail_pitch_css_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- alias for style_manager_rail_small_css_cb(); the name matches its JS preview twin.
function sm_rail_small_css_cb( ...$args ) { return style_manager_rail_small_css_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- alias for style_manager_content_inset_explicit_css_cb(); the name matches its JS preview twin.
function sm_content_inset_explicit_css_cb( ...$args ) { return style_manager_content_inset_explicit_css_cb( ...$args ); }
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- alias for style_manager_font_mobile_scale_css_cb(); the name matches its JS preview twin.
function sm_font_mobile_scale_css_cb( ...$args ) { return style_manager_font_mobile_scale_css_cb( ...$args ); }

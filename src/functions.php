<?php
/**
 * Helper functions
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager;

if ( ! \in_array( \PHP_SAPI, [ 'cli', 'phpdbg' ], true ) ) {
	defined( 'ABSPATH' ) || exit;
}

/**
 * Retrieve the main plugin instance.
 *
 * @since 2.0.0
 *
 * @return Plugin
 */
function plugin(): Plugin {
	static $instance;
	$instance = $instance ?: new Plugin();

	return $instance;
}

/**
 * Autoload mapped classes.
 *
 * @since 2.0.0
 *
 * @param string $class Class name.
 */
function autoloader_classmap( string $class ) {
	$class_map = [
		'PclZip' => ABSPATH . 'wp-admin/includes/class-pclzip.php',
	];

	if ( isset( $class_map[ $class ] ) ) {
		require_once $class_map[ $class ];
	}
}

/**
 * Generate a random string.
 *
 * @since 2.0.0
 *
 * @param int $length Length of the string to generate.
 *
 * @throws \Exception
 * @return string
 */
function generate_random_string( int $length = 12 ): string {
	$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

	$str = '';
	$max = \strlen( $chars ) - 1;
	for ( $i = 0; $i < $length; $i ++ ) {
		$str .= $chars[ random_int( 0, $max ) ];
	}

	return $str;
}

/**
 * Whether a plugin identifier is the main plugin file.
 *
 * Plugins can be identified by their plugin file (relative path to the main
 * plugin file from the root plugin directory) or their slug.
 *
 * This doesn't validate whether or not the plugin actually exists.
 *
 * @since 2.0.0
 *
 * @param string $plugin_file Plugin slug or relative path to the main plugin file.
 *
 * @return bool
 */
function is_plugin_file( string $plugin_file ): bool {
	return '.php' === substr( $plugin_file, - 4 );
}

/**
 * Display a notice about missing dependencies.
 *
 * @since 2.0.0
 */
function display_missing_dependencies_notice() {
	$message = sprintf(
	/* translators: %s: documentation URL */
		__( 'Style Manager is missing required dependencies. <a href="%s" target="_blank" rel="noopener noreferrer">Learn more.</a>', '__plugin_txtd' ),
		'https://github.com/pixelgrade/style-manager'
	);

	printf(
		'<div class="style-manager-compatibility-notice notice notice-error"><p>%s</p></div>',
		wp_kses(
			$message,
			[
				'a' => [
					'href'   => true,
					'rel'    => true,
					'target' => true,
				],
			]
		)
	);
}

/**
 * Determine if we are looking at the Customize screen.
 *
 * @return bool
 */
function is_customizer(): bool {
	global $pagenow;
	return ( is_admin() && 'customize.php' === $pagenow );
}

/**
 * Determine if the Style Manager functionality is supported.
 *
 * @return bool
 */
function is_sm_supported(): bool {
	return apply_filters( 'style_manager/is_supported', current_theme_supports( 'customizer_style_manager' ) );
}

/**
 * Get the URL to the Fonts section of the Pixelgrade Design hub's Site Setup tab.
 *
 * Returns an empty string when the Assistant-provided hub helper isn't
 * available (Assistant inactive or shadowed by Pixelgrade Care, or an older
 * Assistant version that doesn't expose the hub yet). Consumers use this to
 * decide whether to surface a "Manage in Pixelgrade Design" link alongside
 * their own local-fonts controls.
 *
 * @since 2.4.0
 *
 * @return string
 */
function get_design_hub_fonts_url(): string {
	if ( ! \function_exists( 'pixassist_get_hub_url' ) ) {
		return '';
	}

	return (string) \pixassist_get_hub_url( 'plugins', 'fonts' );
}

const PIXELGRADE_PLUS_ADVANCED_CONTROLS_ENTITLEMENT = 'advanced_style_manager_controls';

/**
 * Determine if the shared Pixelgrade Plus entitlement bridge can answer.
 *
 * Existing Style Manager controls must stay available while Plus is absent or
 * dormant. The entitlement bridge is considered available only after a plugin
 * hooks the shared filter.
 *
 * @since 2.2.14
 *
 * @return bool
 */
function pixelgrade_plus_entitlement_bridge_is_available(): bool {
	$available = \function_exists( 'has_filter' ) && false !== \has_filter( 'pixelgrade/has_entitlement' );

	if ( \function_exists( 'apply_filters' ) ) {
		$available = (bool) \apply_filters( 'style_manager/pixelgrade_plus_entitlement_bridge_is_available', $available );
	}

	return $available;
}

/**
 * Check a Pixelgrade Plus entitlement without hard-depending on Plus.
 *
 * @since 2.2.14
 *
 * @param string $entitlement                       Entitlement key.
 * @param bool   $default_when_bridge_unavailable  Result when Plus cannot answer.
 * @return bool
 */
function has_pixelgrade_plus_entitlement( string $entitlement, bool $default_when_bridge_unavailable = false ): bool {
	if ( ! pixelgrade_plus_entitlement_bridge_is_available() ) {
		return $default_when_bridge_unavailable;
	}

	if ( ! \function_exists( 'apply_filters' ) ) {
		return false;
	}

	return (bool) \apply_filters( 'pixelgrade/has_entitlement', false, $entitlement );
}

/**
 * Determine if advanced Style Manager controls are available.
 *
 * @since 2.2.14
 *
 * @return bool
 */
function advanced_style_manager_controls_are_unlocked(): bool {
	return has_pixelgrade_plus_entitlement( PIXELGRADE_PLUS_ADVANCED_CONTROLS_ENTITLEMENT, true );
}

/**
 * Whether the advanced (Plus) controls should be presented as LOCKED in the editor.
 *
 * Distinct from advanced_style_manager_controls_are_unlocked(): the editor keeps the premium
 * controls fully INTERACTIVE — free users get a live sandbox (sliders move, the preview recomputes)
 * — and only adds a gentle trial banner / Plus pill. The real gate is server-side: a locked save
 * strips the premium palette-structure settings (see plus_gated_setting_ids()), so trial changes
 * never persist and revert on reload. This asks the inverse question to the unlocked check and is
 * deliberately fail-CLOSED — locked unless Plus explicitly grants the entitlement. (Clean start: the
 * advanced layer belongs to Pixelgrade Plus.)
 *
 * @since 2.2.15
 *
 * @return bool
 */
function plus_advanced_controls_locked(): bool {
	return ! has_pixelgrade_plus_entitlement( PIXELGRADE_PLUS_ADVANCED_CONTROLS_ENTITLEMENT, false );
}

/**
 * The canonical set of premium (Plus) Style Manager setting ids.
 *
 * Single source of truth shared by the UI gating (Screen\SiteEditor::apply_plus_gating()) and the
 * server-side save strip (Provider\SiteEditorEndpoints). The whole advanced Color System layer is
 * premium: the Fine-tune palette-structure settings, the entire Color Usage tab (master color
 * switches, coloration level, dark mode) and the theme's per-element color targets folded into it,
 * plus the colorize-elements affordance. The advanced Typography layer is premium the same way: the
 * Fine-tune font settings (per-category master fonts, elevation/pitch sizing, connected-fields
 * preset). When a non-entitled user saves, these ids are dropped so everything else persists while
 * the premium settings revert to last-saved.
 *
 * @since 2.2.15
 *
 * @return string[] Premium setting ids.
 */
function plus_gated_setting_ids(): array {
	// Fine-tune palette structure.
	$ids = Customize\ColorPalettes::get_premium_setting_ids();

	// The whole Color Usage tab: the SM-owned master switches, coloration level and dark mode.
	$ids = array_merge( $ids, Customize\ColorPalettes::get_usage_premium_setting_ids() );

	// Fine-tune typography: the per-category master fonts, their elevation/pitch sizing and the
	// connected-fields preset. The whole advanced typography layer is premium, mirroring color.
	$ids = array_merge( $ids, Customize\FontPalettes::get_premium_setting_ids() );

	// The colorize-elements control is a navigation/affordance, not itself a persisted setting, but
	// we keep it in the gated set as the single source of truth (harmless to the strip).
	$ids[] = 'sm_colorize_elements_button';

	// The theme's per-element color targets folded into the Usage tab ("Color targets" group).
	// Derived theme-agnostically from the live Customize registry so this stays maintainable and
	// works for any theme that registers a Style Manager `colors_section`.
	$ids = array_merge( $ids, plus_gated_theme_color_target_setting_ids() );

	// The theme's per-element font targets surfaced in the Font Usage tab (the entire tab — Body,
	// Lead Paragraphs, Heading 1–6, Accent, Headline Lines Spacing, …). Derived theme-agnostically
	// from the live Customize registry, mirroring the color targets, so it works for any theme that
	// registers a Style Manager `fonts_section`.
	$ids = array_merge( $ids, plus_gated_theme_font_target_setting_ids() );

	/**
	 * Filter the canonical list of premium Style Manager setting ids.
	 *
	 * Companions/themes can extend the set of color/typography settings gated behind Pixelgrade Plus.
	 *
	 * @since 2.2.15
	 *
	 * @param string[] $ids Premium setting ids.
	 */
	$ids = (array) \apply_filters( 'style_manager/plus_gated_setting_ids', $ids );

	return array_values( array_unique( array_filter( array_map( 'strval', $ids ) ) ) );
}

/**
 * The theme's per-element color-target setting ids gated behind Pixelgrade Plus.
 *
 * Bridges plus_gated_setting_ids() to the Headless Customizer's theme-agnostic derivation, guarded
 * so contexts without the container (e.g. unit tests) degrade to an empty list rather than fatal.
 *
 * @since 2.2.16
 *
 * @return string[]
 */
function plus_gated_theme_color_target_setting_ids(): array {
	try {
		$container = plugin()->get_container();
		if ( ! $container->has( 'provider.headless_customizer' ) ) {
			return [];
		}

		$headless_customizer = $container->get( 'provider.headless_customizer' );
		if ( ! $headless_customizer instanceof Provider\HeadlessCustomizer ) {
			return [];
		}

		return $headless_customizer->get_theme_color_target_setting_ids();
	} catch ( \Throwable $e ) {
		return [];
	}
}

/**
 * The theme's per-element font-target setting ids gated behind Pixelgrade Plus.
 *
 * Bridges plus_gated_setting_ids() to the Headless Customizer's theme-agnostic derivation, guarded
 * so contexts without the container (e.g. unit tests) degrade to an empty list rather than fatal.
 * The mirror of plus_gated_theme_color_target_setting_ids() for the Font Usage tab.
 *
 * @since 2.2.17
 *
 * @return string[]
 */
function plus_gated_theme_font_target_setting_ids(): array {
	try {
		$container = plugin()->get_container();
		if ( ! $container->has( 'provider.headless_customizer' ) ) {
			return [];
		}

		$headless_customizer = $container->get( 'provider.headless_customizer' );
		if ( ! $headless_customizer instanceof Provider\HeadlessCustomizer ) {
			return [];
		}

		return $headless_customizer->get_theme_font_target_setting_ids();
	} catch ( \Throwable $e ) {
		return [];
	}
}

/**
 * The destination for the gentle "Learn more" upsell link shown beside locked controls.
 *
 * @since 2.2.15
 *
 * @return string
 */
function plus_upsell_url(): string {
	return (string) \apply_filters( 'style_manager/plus_upsell_url', 'https://pixelgrade.com/plus' );
}

/**
 * Get a Style Manager option's value, if there is a value, and return it.
 * Otherwise, try to get the default parameter or the default from config.
 *
 * @since 2.0.0
 *
 * @param string     $option_id
 * @param mixed|null $default        Optional.
 * @param array|null $option_details Optional.
 *
 * @return mixed
 */
function get_option( string $option_id, $default = null, $option_details = null ) {
	return plugin()->get_container()->get('options')->get( $option_id, $default, $option_details );
}

/**
 * Get the Style Manager configuration (and value, hence "details") of a certain option.
 *
 * @since 2.0.0
 *
 * @param string $option_id
 * @param bool   $minimal_details Optional. Whether to return only the minimum amount of details (mainly what is needed on the frontend).
 *                                The advantage is that these details are cached, thus skipping the customizer_config!
 * @param bool   $skip_cache      Optional.
 *
 * @return array|false The option config or false on failure.
 */
function get_option_details( string $option_id, $minimal_details = false, $skip_cache = false ) {
	return plugin()->get_container()->get('options')->get_details( $option_id, $minimal_details, $skip_cache );
}

/**
 * Get all Style Manager options' details.
 *
 * @since 2.0.0
 *
 * @param bool $only_minimal_details Optional. Whether to return only the minimal details.
 *                                   Defaults to returning all details.
 * @param bool $skip_cache           Optional. Whether to skip the options cache and regenerate.
 *                                   Defaults to using the cache.
 * @return array
 */
function get_option_details_all( $only_minimal_details = false, $skip_cache = false ): array {
	return plugin()->get_container()->get('options')->get_details_all( $only_minimal_details, $skip_cache );
}

/**
 * Determine if a certain option exists.
 *
 * @since 2.0.0
 *
 * @param string $key The option key.
 *
 * @return bool
 */
function has_option( string $key ): bool {
	return plugin()->get_container()->get('options')->has_option( $key );
}

/**
 * Get the key under which all Style Manager options are saved.
 *
 * @since 2.0.0
 *
 * @param bool $skip_cache Optional. Whether to skip the options cache and regenerate.
 *                         Defaults to using the cache.
 *
 * @return string
 */
function get_options_key( bool $skip_cache = false ): string {
	return plugin()->get_container()->get('options')->get_options_key( $skip_cache );
}

/**
 * Get the entire Style Manager Customizer fields config or a certain entry key.
 *
 * @since 2.0.0
 *
 * @param bool|string $key
 *
 * @return array|mixed|null
 */
function get_customizer_config( $key = false ) {
	return plugin()->get_container()->get('options')->get_customizer_config( $key );
}

/**
 * Sanitize dynamic CSS immediately before it is echoed inside a `<style>`
 * element, on the frontend and in the block editor alike.
 *
 * This is a last-line-of-defense, HTML-context escape — not a CSS validator.
 * Persisted option values (e.g. a palette's per-variation color pairs) are
 * expected to already be shape-checked at write time; this function's job is
 * only to guarantee that whatever string ends up here cannot break out of
 * the surrounding `<style>` tag, regardless of which code path produced it.
 * It intentionally does not touch CSS syntax: `var(--sm-*)` references,
 * quoted font-family names, and any other well-formed CSS text pass through
 * unchanged.
 *
 * @since 2.5.3
 *
 * @param string $css Raw dynamic CSS.
 *
 * @return string CSS safe to echo inside a `<style>` tag.
 */
function sanitize_dynamic_style_css( string $css ): string {
	// Strip HTML/script/style tags and their content (matches the
	// frontend's historical behavior; stronger than plain strip_tags()
	// because it also drops <script>/<style> block content, not just tags).
	$css = wp_strip_all_tags( $css );

	// Belt-and-braces: neutralize any "</style" sequence that could still
	// close the surrounding <style> element (case-insensitive, tolerant of
	// stray whitespace between the characters) even if it somehow survived
	// tag stripping above. This never fires on legitimate CSS.
	$css = (string) preg_replace( '#</\s*style#i', '<\\/style', $css );

	// Drop control characters (everything except tab \x09 and newline
	// \x0A, which are harmless formatting whitespace) that have no
	// legitimate place in CSS text and only serve to obfuscate a payload
	// past naive filters.
	$css = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css );

	return $css;
}

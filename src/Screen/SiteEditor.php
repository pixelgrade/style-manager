<?php
/**
 * Provider for the Site Editor screen.
 *
 * Surfaces the Style Manager controls inside the WordPress Site Editor with
 * the same markup, data and JS engine as the Customizer, minus the Customizer
 * chrome.
 *
 * @since   2.3.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Utils\ScriptsEnqueue;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;
use function Pixelgrade\StyleManager\is_sm_supported;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Site Editor screen provider class.
 *
 * @since 2.3.0
 */
class SiteEditor extends AbstractHookProvider {

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Plugin settings.
	 *
	 * @var PluginSettings
	 */
	protected PluginSettings $plugin_settings;

	/**
	 * Style Manager Fonts.
	 *
	 * @var Fonts
	 */
	protected Fonts $sm_fonts;

	/**
	 * Headless Customizer provider.
	 *
	 * @var HeadlessCustomizer
	 */
	protected HeadlessCustomizer $headless_customizer;

	/**
	 * Customizer screen (for the localized styleManager config).
	 *
	 * @var Customizer
	 */
	protected Customizer $customizer_screen;

	/**
	 * Edit with blocks screen (for the editor CSS selector transforms).
	 *
	 * @var EditWithBlocks
	 */
	protected EditWithBlocks $edit_with_blocks;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * @param Options            $options             Options.
	 * @param PluginSettings     $plugin_settings     Plugin settings.
	 * @param Fonts              $sm_fonts            Style Manager Fonts.
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param Customizer         $customizer_screen   Customizer screen.
	 * @param EditWithBlocks     $edit_with_blocks    Edit with blocks screen.
	 * @param LoggerInterface    $logger              Logger.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		Fonts $sm_fonts,
		HeadlessCustomizer $headless_customizer,
		Customizer $customizer_screen,
		EditWithBlocks $edit_with_blocks,
		LoggerInterface $logger
	) {
		$this->options             = $options;
		$this->plugin_settings     = $plugin_settings;
		$this->sm_fonts            = $sm_fonts;
		$this->headless_customizer = $headless_customizer;
		$this->customizer_screen   = $customizer_screen;
		$this->edit_with_blocks    = $edit_with_blocks;
		$this->logger              = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.3.0
	 */
	public function register_hooks() {
		$this->add_action( 'enqueue_block_editor_assets', 'enqueue_assets', 12 );
		// After the theme's own Site Editor handoff (Anima enqueues at 20).
		$this->add_action( 'enqueue_block_editor_assets', 'align_theme_styles_handoff', 30 );
	}

	/**
	 * Align the theme's Site Editor "Styles" handoff with the in-editor controls.
	 *
	 * Anima (and LT siblings) take over the Site Editor Styles route with a
	 * card that hands users off to the Customizer. Now that the Style Manager
	 * controls live inside the Site Editor, re-point that card to open the
	 * Style Manager sidebar instead (deep links handled by the site-editor JS
	 * via the `sm-section` query param).
	 */
	protected function align_theme_styles_handoff() {
		if ( ! $this->is_site_editor_screen() || ! is_sm_supported() ) {
			return;
		}

		if ( ! wp_script_is( 'anima-site-editor-style-manager', 'enqueued' ) ) {
			return;
		}

		$editor_url = admin_url( 'site-editor.php?canvas=edit&sm-sidebar=1' );

		// Re-localizing the same object name on the same handle: the later
		// assignment wins, so the takeover card picks up the new destinations.
		wp_localize_script( 'anima-site-editor-style-manager', 'animaSiteEditorStyleManager', [
			'customizerUrl'    => esc_url_raw( $editor_url ),
			'eyebrow'          => esc_html__( 'Style Manager', '__plugin_txtd' ),
			'title'            => esc_html__( 'Your design system lives in Style Manager', '__plugin_txtd' ),
			'description'      => esc_html__( 'Colors, typography, spacing, and the rest of your design system are managed through Style Manager — right here in the Site Editor. Open the Style Manager sidebar to make handy color changes, balance fonts, and adjust spacing, each step bringing you closer to a striking result.', '__plugin_txtd' ),
			'buttonLabel'      => esc_html__( 'Open Style Manager', '__plugin_txtd' ),
			'resourcesEyebrow' => esc_html__( 'Jump right in', '__plugin_txtd' ),
			'resources'        => [
				[
					'title'       => esc_html__( 'The Color System', '__plugin_txtd' ),
					'description' => esc_html__( 'Set the overall mood of your site with a palette that feels calm, bold, playful, or anywhere in between.', '__plugin_txtd' ),
					'buttonLabel' => esc_html__( 'Set Up Colors', '__plugin_txtd' ),
					'url'         => esc_url_raw( add_query_arg( 'sm-section', 'sm_color_palettes_section', $editor_url ) ),
				],
				[
					'title'       => esc_html__( 'Managing Typography', '__plugin_txtd' ),
					'description' => esc_html__( 'Choose a small set of fonts that work well together so headings, interface text, and longer reads stay balanced.', '__plugin_txtd' ),
					'buttonLabel' => esc_html__( 'Change Fonts', '__plugin_txtd' ),
					'url'         => esc_url_raw( add_query_arg( 'sm-section', 'sm_font_palettes_section', $editor_url ) ),
				],
			],
		] );
	}

	/**
	 * Determine whether the current admin screen is the Site Editor.
	 *
	 * @return bool
	 */
	protected function is_site_editor_screen(): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return (bool) $screen && in_array( $screen->id, [ 'site-editor', 'site-editor-v2' ], true );
	}

	/**
	 * Enqueue the Site Editor integration assets and localize all the data the
	 * Style Manager engine needs (same shapes as in the Customizer pane).
	 *
	 * @since 2.3.0
	 */
	protected function enqueue_assets() {
		if ( ! $this->is_site_editor_screen() || ! is_sm_supported() ) {
			return;
		}

		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return;
		}

		$this->register_vendor_assets();

		// Boot the headless Customizer: registers every panel/section/setting/control.
		try {
			$this->headless_customizer->get_manager();
		} catch ( \Throwable $e ) {
			$this->logger->error( 'Style Manager could not boot the headless Customizer for the Site Editor: ' . $e->getMessage() );

			return;
		}

		// Let the rendered controls enqueue their own assets (color picker, media, etc.).
		$this->headless_customizer->enqueue_controls_assets();

		wp_register_script(
			'pixelgrade_style_manager-site-editor',
			$this->plugin->get_url( 'dist/js/site-editor.js' ),
			[
				'jquery',
				'jquery-react',
				'lodash',
				'underscore',
				'pixelgrade_style_manager-chroma',
				'pixelgrade_style_manager-select2',
				'pixelgrade_style_manager-regression',
				// The engine and the font palette preview scripts load webfonts
				// through the WebFont global; outside the Customizer nothing
				// else puts it on the page.
				'pixelgrade_style_manager-web-font-loader',
				'react',
				'react-dom',
				'wp-element',
				'wp-components',
				'wp-i18n',
				'wp-plugins',
				'wp-editor',
				'wp-data',
				'wp-api-fetch',
				'wp-dom-ready',
			],
			VERSION,
			true
		);

		$rtl_suffix = is_rtl() ? '-rtl' : '';
		if ( ! wp_style_is( 'pixelgrade_style_manager-customizer', 'registered' ) ) {
			wp_register_style(
				'pixelgrade_style_manager-customizer',
				$this->plugin->get_url( 'dist/js/customizer' . $rtl_suffix . '.css' ),
				[],
				VERSION
			);
		}

		wp_register_style(
			'pixelgrade_style_manager-site-editor',
			$this->plugin->get_url( 'dist/js/site-editor' . $rtl_suffix . '.css' ),
			[ 'pixelgrade_style_manager-customizer' ],
			VERSION
		);

		// The same `styleManager` global the Customizer pane receives. It MUST
		// keep its frontend-truth selectors: the Live Site preview iframe's JS
		// reads `window.top.styleManager.config` (this page) to compute the CSS
		// it injects into the FRONTEND document. The editor-canvas pipeline gets
		// its canvas-ready selectors through the payload's `editorCssSelectors`
		// override map instead. See issues #132/#133.
		$localized = apply_filters( 'style_manager/localized_js_settings', $this->customizer_screen->get_localized_data() );

		wp_enqueue_script( 'pixelgrade_style_manager-site-editor' );
		wp_add_inline_script(
			'pixelgrade_style_manager-site-editor',
			ScriptsEnqueue::getlocalizeToWindowScript( 'styleManager', $localized ),
			'before'
		);
		wp_add_inline_script(
			'pixelgrade_style_manager-site-editor',
			ScriptsEnqueue::getlocalizeToWindowScript( '_styleManagerSiteEditor', $this->get_site_editor_payload( $localized ) ),
			'before'
		);

		wp_enqueue_style( 'pixelgrade_style_manager-sm-colors-custom-properties' );
		wp_enqueue_style( 'pixelgrade_style_manager-customizer' );
		wp_enqueue_style( 'pixelgrade_style_manager-site-editor' );

		// Webfonts for the font previews inside the controls (pane document).
		$this->sm_fonts->add_webfont_loader_inline_scripts();
		$this->sm_fonts->enqueue_frontend_scripts_styles();
		add_action( 'admin_print_styles', [ $this, 'output_fonts_dynamic_style' ], 100 );
	}

	/**
	 * Print the fonts dynamic style in the Site Editor admin document, like the
	 * Customizer pane does, so font controls can preview actual font stacks.
	 */
	public function output_fonts_dynamic_style() {
		$this->sm_fonts->outputFontsDynamicStyle();
	}

	/**
	 * Build the canvas-ready selector overrides for every setting whose `css`
	 * config carries selectors — the same transformation the server applies
	 * when generating the saved-state editor CSS (EditWithBlocks).
	 *
	 * Shape: setting_id => [ css index => transformed selector ].
	 *
	 * @since 2.3.0
	 *
	 * @param array $localized The `styleManager` localized data.
	 *
	 * @return array
	 */
	protected function get_editor_css_selector_overrides( array $localized ): array {
		$overrides = [];

		if ( empty( $localized['config']['settings'] ) || ! is_array( $localized['config']['settings'] ) ) {
			return $overrides;
		}

		foreach ( $localized['config']['settings'] as $setting_id => $config ) {
			if ( ! is_array( $config ) || empty( $config['css'] ) || ! is_array( $config['css'] ) ) {
				continue;
			}

			foreach ( $config['css'] as $idx => $css_property ) {
				if ( empty( $css_property['selector'] ) || ! is_string( $css_property['selector'] ) ) {
					continue;
				}

				$overrides[ $setting_id ][ $idx ] =
					$this->edit_with_blocks->gutenbergify_css_selectors( $css_property['selector'], (array) $css_property );
			}
		}

		return $overrides;
	}

	/**
	 * Build the Site Editor integration payload.
	 *
	 * @param array $localized The `styleManager` localized data (for the editor CSS selector overrides).
	 *
	 * @return array
	 */
	protected function get_site_editor_payload( array $localized = [] ): array {
		// Same data the Customizer preview JS callbacks receive (see Screen\Customizer\Preview).
		$fallback_palettes         = function_exists( 'sm_get_fallback_palettes' ) ? sm_get_fallback_palettes() : [];
		$palettes                  = json_decode( get_option( 'sm_advanced_palette_output', '[]' ) );
		$user_palettes             = is_array( $palettes ) && function_exists( 'sm_filter_user_palettes' )
			? array_filter( $palettes, 'sm_filter_user_palettes' )
			: [];
		$structure                 = $this->headless_customizer->get_structure();
		$theme_colors_group_header = $this->merge_theme_colors_section_into_usage_section( $structure );
		$section_group_headers     = $this->get_site_editor_section_group_headers( $theme_colors_group_header );

		// Tag Plus-gated sections/controls so the sidebar can present them inline as a live trial
		// (controls stay interactive) with a gentle upsell instead of hiding them.
		$structure = $this->apply_plus_gating( $structure );

		return [
			'structure'          => $structure,
			'plus'               => $this->get_plus_payload(),
			// Mirrors the parts of _wpCustomizeSettings the SM engine reads.
			// Note: `google_fonts_opts` is deliberately NOT shipped — the ~96 KB
			// of <option> markup is synthesized client-side from the
			// styleManager.fonts catalog (see site-editor/customize-api.js).
			'customizeSettings'  => [
				'settings' => $this->headless_customizer->get_settings_data(),
			],
			'preview'            => [
				'fallbackPalettes'  => $fallback_palettes,
				'userPalettesCount' => count( $user_palettes ),
			],
			'rest'               => [
				'settingsPath'          => '/style_manager/v1/site-editor/settings',
				'activeStatesPath'      => '/style_manager/v1/site-editor/active-states',
				'previewChangesetPath'  => '/style_manager/v1/site-editor/preview-changeset',
				'cssPath'               => '/style_manager/v1/site-editor/css',
			],
			'homeUrl'            => esc_url_raw( home_url( '/' ) ),
			/**
			 * Granular sections folded into their parent section as tabs
			 * (high-level -> low-level, the Nova Blocks inspector pattern).
			 * The Customizer relocated these to a Theme Options panel as a
			 * workaround. Filter: parent section id => ordered tabs
			 * [ [ id, label ] ] where the parent lists itself first.
			 */
			'sectionTabs'        => apply_filters(
				'style_manager/site_editor_section_tabs',
				$this->get_site_editor_section_tabs()
			),
			/**
			 * Group headers injected before specific controls in a section —
			 * gives sibling controls a shared identity (e.g. the Collections
			 * pair in the Tweak Board). A header may carry an optional
			 * `preview => [ 'mode' => ... ]` to expose a per-group Preview
			 * affordance (the section-level Preview is one-per-section; this
			 * lets the Tweak Board preview Site Frame and Fancy Titles
			 * independently). Anchors for theme-appended controls are no-ops
			 * when the theme is absent. Filter: section id => [ [ before
			 * (setting id), label, preview? ] ].
			 */
			'sectionGroupHeaders' => apply_filters(
				'style_manager/site_editor_section_group_headers',
				$section_group_headers
			),
			/**
			 * Toggle-driven control visibility (the Customizer gets this from
			 * theme-shipped JS, e.g. Anima's motion controls script).
			 * Filter: setting_id => [ dependent setting_ids shown when truthy ].
			 */
			'controlDependencies' => apply_filters( 'style_manager/site_editor_control_dependencies', [
				'sm_page_transitions_enable' => [
					'sm_page_transition_style',
					'sm_logo_loading_style',
					'sm_transition_symbol',
				],
				'sm_intro_animations_enable' => [
					'sm_intro_animations_style',
					'sm_intro_animations_speed',
				],
			] ),
			'editorDynamicStyleHandle' => 'style-manager-editor-dynamic',
			/*
			 * Canvas-ready selector overrides for the live preview: frontend
			 * selectors (`.page-title`) match nothing in editor markup, so the
			 * canvas pipeline swaps them for the same gutenbergified selectors
			 * the saved-state editor CSS uses. Kept OUT of styleManager.config —
			 * the Live Site iframe reads that for frontend rules. See #132/#133.
			 */
			'editorCssSelectors' => $this->get_editor_css_selector_overrides( $localized ),
		];
	}

	/**
	 * Tag Plus-gated sections/controls in the structure with `gate` + `locked` so the Site Editor
	 * sidebar can present them inline as a live trial (controls stay interactive) with a gentle
	 * upsell. Fail-closed: locked unless Pixelgrade Plus grants the
	 * `advanced_style_manager_controls` entitlement.
	 *
	 * @param array $structure
	 *
	 * @return array
	 */
	protected function apply_plus_gating( array $structure ): array {
		$locked = \Pixelgrade\StyleManager\plus_advanced_controls_locked();

		/**
		 * Map of section_id / control_id => gate key (currently only 'plus'). Companions/themes can
		 * extend the set of color/typography objects presented behind Pixelgrade Plus.
		 *
		 * @param array $gated
		 */
		$gated = apply_filters( 'style_manager/plus_gated_objects', [
			'sm_fine_tune_color_palette_section' => 'plus', // the whole Fine-tune tab
			'sm_color_usage_section'             => 'plus', // the whole Usage tab
			'sm_colorize_elements_button'        => 'plus', // colorize elements one by one
		] );

		if ( empty( $gated ) || empty( $structure['sections'] ) || ! is_array( $structure['sections'] ) ) {
			return $structure;
		}

		foreach ( $structure['sections'] as &$section ) {
			if ( isset( $section['id'], $gated[ $section['id'] ] ) ) {
				$section['gate']   = $gated[ $section['id'] ];
				$section['locked'] = $locked;
			}

			if ( empty( $section['controls'] ) || ! is_array( $section['controls'] ) ) {
				continue;
			}

			foreach ( $section['controls'] as &$control ) {
				if ( empty( $control['id'] ) ) {
					continue;
				}

				// Control ids carry a `_control` suffix (and theme settings are bracket-wrapped, e.g.
				// `anima_options[sm_x]_control`); the gated map keys on bare setting ids. Match either
				// the literal control id or its underlying setting id.
				$control_id = (string) $control['id'];
				$setting_id = $this->control_id_to_setting_id( $control_id );

				$gate_key = $gated[ $control_id ] ?? ( $gated[ $setting_id ] ?? null );
				if ( null !== $gate_key ) {
					$control['gate']   = $gate_key;
					$control['locked'] = $locked;
				}
			}
			unset( $control );
		}
		unset( $section );

		return $structure;
	}

	/**
	 * Reduce a rendered Customizer control id to its underlying setting id.
	 *
	 * Strips the trailing `_control` and unwraps a `prefix[setting]` shape to the bare `setting`,
	 * so gating that keys on setting ids matches controls regardless of theme prefixing.
	 *
	 * @param string $control_id
	 *
	 * @return string
	 */
	protected function control_id_to_setting_id( string $control_id ): string {
		$setting_id = preg_replace( '/_control$/', '', $control_id );
		$setting_id = is_string( $setting_id ) ? $setting_id : $control_id;

		if ( preg_match( '/\[([^\]]+)\]$/', $setting_id, $matches ) ) {
			return $matches[1];
		}

		return $setting_id;
	}

	/**
	 * Gentle, translatable TRIAL copy + destination shown beside locked Plus controls.
	 *
	 * When locked, the controls stay fully interactive — free users get a live sandbox to feel what
	 * they do. The copy invites trying them live and frames saving as part of Pixelgrade Plus, never
	 * implying the free tool is incomplete. Presentation-only; entitlement truth is the Plus bridge.
	 *
	 * @return array
	 */
	protected function get_plus_payload(): array {
		return [
			'locked'      => \Pixelgrade\StyleManager\plus_advanced_controls_locked(),
			'upsellUrl'   => \Pixelgrade\StyleManager\plus_upsell_url(),
			'badge'       => esc_html__( 'Plus', '__plugin_txtd' ),
			'learnMore'   => esc_html__( 'Learn more', '__plugin_txtd' ),
			'productName' => esc_html__( 'Pixelgrade Plus', '__plugin_txtd' ),
			'notes'       => [
				'sm_fine_tune_color_palette_section' => esc_html__( 'Take the Fine-tune controls for a spin — grades, contrast, and color promotion update live as you go. Changes here aren\'t saved; fine-tuning your palette\'s structure is part of Pixelgrade Plus.', '__plugin_txtd' ),
				'sm_color_usage_section'             => esc_html__( 'Try the Usage controls live — shift how colors apply across your site, dial coloration up or down, and preview dark mode. Changes here aren\'t saved; saving how your palette is applied is part of Pixelgrade Plus.', '__plugin_txtd' ),
				'sm_colorize_elements_button'        => esc_html__( 'Try colorizing elements one by one — it works live. Saving is part of Pixelgrade Plus.', '__plugin_txtd' ),
			],
		];
	}

	/**
	 * Fold the active theme's per-element color controls into the Color Usage section.
	 *
	 * @param array $structure Headless Customizer structure.
	 *
	 * @return array|null Group header marker for the folded controls.
	 */
	protected function merge_theme_colors_section_into_usage_section( array &$structure ): ?array {
		$theme_colors_section_id = $this->get_theme_colors_section_id();
		if (
			'' === $theme_colors_section_id
			|| empty( $structure['sections'] )
			|| ! is_array( $structure['sections'] )
		) {
			return null;
		}

		$usage_section_index        = null;
		$theme_colors_section_index = null;
		foreach ( $structure['sections'] as $section_index => $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}

			if ( 'sm_color_usage_section' === ( $section['id'] ?? '' ) ) {
				$usage_section_index = $section_index;
			}

			if ( $theme_colors_section_id === ( $section['id'] ?? '' ) ) {
				$theme_colors_section_index = $section_index;
			}
		}

		if ( null === $usage_section_index || null === $theme_colors_section_index ) {
			return null;
		}

		$theme_colors_controls = $structure['sections'][ $theme_colors_section_index ]['controls'] ?? [];
		array_splice( $structure['sections'], $theme_colors_section_index, 1 );
		if ( $theme_colors_section_index < $usage_section_index ) {
			$usage_section_index--;
		}

		if ( ! is_array( $theme_colors_controls ) || [] === $theme_colors_controls ) {
			return null;
		}

		if ( empty( $structure['sections'][ $usage_section_index ]['controls'] ) || ! is_array( $structure['sections'][ $usage_section_index ]['controls'] ) ) {
			$structure['sections'][ $usage_section_index ]['controls'] = [];
		}

		$insert_index = count( $structure['sections'][ $usage_section_index ]['controls'] );
		foreach ( $structure['sections'][ $usage_section_index ]['controls'] as $control_index => $control ) {
			if ( is_array( $control ) && 'sm_coloration_level_control' === ( $control['id'] ?? '' ) ) {
				$insert_index = $control_index + 1;
				break;
			}
		}

		array_splice( $structure['sections'][ $usage_section_index ]['controls'], $insert_index, 0, $theme_colors_controls );

		$first_control = reset( $theme_colors_controls );
		if ( ! is_array( $first_control ) || empty( $first_control['id'] ) ) {
			return null;
		}

		return [
			'before'      => $this->get_group_header_anchor_from_control_id( (string) $first_control['id'] ),
			'label'       => esc_html__( 'Color targets', '__plugin_txtd' ),
			'collapsible' => true,
			'group'       => 'color-targets',
			'defaultOpen' => true,
		];
	}

	/**
	 * Build the section tab map consumed by the Site Editor sidebar.
	 *
	 * @return array
	 */
	protected function get_site_editor_section_tabs(): array {
		return [
			'sm_color_palettes_section' => [
				[ 'id' => 'sm_color_palettes_section', 'label' => esc_html__( 'Palette', '__plugin_txtd' ) ],
				[ 'id' => 'sm_fine_tune_color_palette_section', 'label' => esc_html__( 'Fine-tune', '__plugin_txtd' ) ],
				[ 'id' => 'sm_color_usage_section', 'label' => esc_html__( 'Usage', '__plugin_txtd' ) ],
			],
			'sm_font_palettes_section'  => [
				[ 'id' => 'sm_font_palettes_section', 'label' => esc_html__( 'Palettes', '__plugin_txtd' ) ],
				[ 'id' => 'sm_fine_tune_font_palette_section', 'label' => esc_html__( 'Fine-tune', '__plugin_txtd' ) ],
			],
		];
	}

	/**
	 * Build the section group headers consumed by the Site Editor sidebar.
	 *
	 * @param array|null $theme_colors_group_header Dynamic marker for folded theme color controls.
	 *
	 * @return array
	 */
	protected function get_site_editor_section_group_headers( ?array $theme_colors_group_header = null ): array {
		$section_group_headers = [
			'sm_tweak_board_section' => [
				// Each entry marks a group start (drawn with a divider).
				// Cards Collections has no intro of its own — inject a label.
				[
					'before' => 'sm_collection_title_position',
					'label'  => esc_html__( 'Cards Collections', '__plugin_txtd' ),
				],
				// Site Frame and Fancy Titles already render an html intro
				// title; attach the Preview affordance to it (no label).
				// Site Frame's None/Editorial style IS its on/off — drive it
				// from a master switch on the title row (on = editorial).
				[
					'before'  => 'sm_site_frame_intro',
					'preview' => [ 'mode' => 'site-frame' ],
					'toggle'  => [
						'setting' => 'sm_site_frame_style',
						'on'      => 'editorial',
						'off'     => 'none',
					],
				],
				[
					'before'  => 'sm_decorative_titles_style_intro',
					'preview' => [ 'mode' => 'fancy-titles' ],
				],
				// Content Colors: its intro is the title; the Preview opens
				// the (premium) per-item colour board, shown only while the
				// feature's master toggle is on.
				[
					'before'  => 'sm_contextual_entry_colors_intro',
					'preview' => [ 'mode' => 'contextual-colors' ],
				],
			],
			// Motion: its two behaviours each get a titled, divider-separated
			// section. The intros already title them; keeping them standing
			// (group-head) makes the toggles read "Enabled" under the title.
			// The section's Live Site Preview stays in the page header.
			'sm_motion_section' => [
				[
					'before' => 'sm_page_transitions_intro',
				],
				[
					'before' => 'sm_intro_animations_intro',
				],
			],
			// Color fine-tune: presets lead (their own label), then the
			// granular controls grouped by what they shape. Color promotion's
			// html intro heads two toggles, so it stays a plain header (no
			// master switch) — see markGroupSections / native-controls.
			'sm_fine_tune_color_palette_section' => [
				[
					'before' => 'sm_color_grades_number',
					'label'  => esc_html__( 'Structure', '__plugin_txtd' ),
				],
				[
					'before' => 'sm_potential_color_contrast',
					'label'  => esc_html__( 'Contrast & balance', '__plugin_txtd' ),
				],
				[
					'before' => 'sm_color_promotion_brand',
				],
			],
		];

		$usage_group_headers = [
			[
				'before' => 'sm_dark_mode_advanced',
				'label'  => esc_html__( 'Appearance', '__plugin_txtd' ),
			],
		];
		if ( null !== $theme_colors_group_header ) {
			array_unshift( $usage_group_headers, $theme_colors_group_header );
		}

		$section_group_headers = array_merge(
			[ 'sm_color_usage_section' => $usage_group_headers ],
			$section_group_headers
		);

		return $section_group_headers;
	}

	/**
	 * Build the final Customizer section ID used by themes for per-element
	 * color controls when they register the `colors_section` Style Manager
	 * config section.
	 *
	 * @return string
	 */
	protected function get_theme_colors_section_id(): string {
		$options_key = $this->options->get_options_key();
		if ( '' === $options_key ) {
			return '';
		}

		return $options_key . '[colors_section]';
	}

	/**
	 * Convert a rendered Customizer control ID to the marker anchor expected by JS.
	 *
	 * @param string $control_id Rendered Customizer control ID.
	 *
	 * @return string
	 */
	protected function get_group_header_anchor_from_control_id( string $control_id ): string {
		$anchor = preg_replace( '/_control$/', '', $control_id );

		return is_string( $anchor ) && '' !== $anchor ? $anchor : $control_id;
	}

	/**
	 * Register the vendor script handles the Customizer assets provider only
	 * registers on customize_controls_init.
	 */
	protected function register_vendor_assets() {
		$scripts_suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		if ( ! wp_script_is( 'pixelgrade_style_manager-select2', 'registered' ) ) {
			wp_register_script(
				'pixelgrade_style_manager-select2',
				$this->plugin->get_url( 'vendor_js/select2-4.0.13/dist/js/select2.full' . $scripts_suffix . '.js' ),
				[ 'jquery' ],
				VERSION,
				true
			);
		}

		if ( ! wp_script_is( 'jquery-react', 'registered' ) ) {
			wp_register_script(
				'jquery-react',
				$this->plugin->get_url( 'vendor_js/jquery-react' . $scripts_suffix . '.js' ),
				[ 'jquery' ],
				VERSION,
				true
			);
		}

		if ( ! wp_script_is( 'pixelgrade_style_manager-regression', 'registered' ) ) {
			wp_register_script(
				'pixelgrade_style_manager-regression',
				$this->plugin->get_url( 'vendor_js/regression' . $scripts_suffix . '.js' ),
				[],
				VERSION,
				true
			);
		}

		if ( ! wp_script_is( 'pixelgrade_style_manager-chroma', 'registered' ) ) {
			wp_register_script(
				'pixelgrade_style_manager-chroma',
				$this->plugin->get_url( 'vendor_js/chroma' . $scripts_suffix . '.js' ),
				[],
				VERSION,
				true
			);
		}
	}
}

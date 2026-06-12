<?php
/**
 * Provider for the Site Editor screen.
 *
 * Surfaces the Style Manager controls inside the WordPress Site Editor with
 * the same markup, data and JS engine as the Customizer, minus the Customizer
 * chrome. See plans/2026-06-11-site-editor-controls.md.
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
	 * @param LoggerInterface    $logger              Logger.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		Fonts $sm_fonts,
		HeadlessCustomizer $headless_customizer,
		Customizer $customizer_screen,
		LoggerInterface $logger
	) {
		$this->options             = $options;
		$this->plugin_settings     = $plugin_settings;
		$this->sm_fonts            = $sm_fonts;
		$this->headless_customizer = $headless_customizer;
		$this->customizer_screen   = $customizer_screen;
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

		// The same `styleManager` global the Customizer pane receives.
		$localized = apply_filters( 'style_manager/localized_js_settings', $this->customizer_screen->get_localized_data() );

		wp_enqueue_script( 'pixelgrade_style_manager-site-editor' );
		wp_add_inline_script(
			'pixelgrade_style_manager-site-editor',
			ScriptsEnqueue::getlocalizeToWindowScript( 'styleManager', $localized ),
			'before'
		);
		wp_add_inline_script(
			'pixelgrade_style_manager-site-editor',
			ScriptsEnqueue::getlocalizeToWindowScript( '_styleManagerSiteEditor', $this->get_site_editor_payload() ),
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
	 * Build the Site Editor integration payload.
	 *
	 * @return array
	 */
	protected function get_site_editor_payload(): array {
		// Same data the Customizer preview JS callbacks receive (see Screen\Customizer\Preview).
		$fallback_palettes = function_exists( 'sm_get_fallback_palettes' ) ? sm_get_fallback_palettes() : [];
		$palettes          = json_decode( get_option( 'sm_advanced_palette_output', '[]' ) );
		$user_palettes     = is_array( $palettes ) && function_exists( 'sm_filter_user_palettes' )
			? array_filter( $palettes, 'sm_filter_user_palettes' )
			: [];

		return [
			'structure'          => $this->headless_customizer->get_structure(),
			// Mirrors the parts of _wpCustomizeSettings the SM engine reads.
			'customizeSettings'  => [
				'settings'          => $this->headless_customizer->get_settings_data(),
				'google_fonts_opts' => $this->sm_fonts->get_google_fonts_select_options(),
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
			'sectionTabs'        => apply_filters( 'style_manager/site_editor_section_tabs', [
				'sm_color_palettes_section' => [
					[ 'id' => 'sm_color_palettes_section', 'label' => esc_html__( 'Palette', '__plugin_txtd' ) ],
					[ 'id' => 'sm_color_usage_section', 'label' => esc_html__( 'Usage', '__plugin_txtd' ) ],
					[ 'id' => 'sm_fine_tune_color_palette_section', 'label' => esc_html__( 'Fine-tune', '__plugin_txtd' ) ],
				],
				'sm_font_palettes_section'  => [
					[ 'id' => 'sm_font_palettes_section', 'label' => esc_html__( 'Palettes', '__plugin_txtd' ) ],
					[ 'id' => 'sm_fine_tune_font_palette_section', 'label' => esc_html__( 'Fine-tune', '__plugin_txtd' ) ],
				],
			] ),
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
		];
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

<?php
/**
 * Provider for screens when editing posts/pages with the block editor.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen;

use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Utils\ScriptsEnqueue;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;
use function Pixelgrade\StyleManager\is_sm_supported;
use const Pixelgrade\StyleManager\VERSION;

/**
 * Provider class for screens when editing posts/pages with the block editor.
 *
 * This is the class that handles the overall logic for integration with the new Gutenberg Editor (WordPress 5.0+).
 *
 * @since 2.0.0
 */
class EditWithBlocks extends AbstractHookProvider {

	/**
	 * Selectors that we will use to constrain CSS rules to certain scopes.
	 */
	public static string $editor_namespace_selector = '.editor-styles-wrapper';
	// Updated for WP 6.x+: .editor-post-title__block was replaced by .editor-post-title
	public static string $title_namespace_selector = '.editor-styles-wrapper .editor-post-title';
	public static string $title_input_namespace_selector = '.editor-styles-wrapper .editor-post-title';

	/**
	 * Get the block namespace CSS selector according to the WP version in use.
	 *
	 * @return string
	 * @global string $wp_version
	 *
	 */
	public static function get_block_namespace_selector(): string {
		global $wp_version;

		$is_old_wp_version = version_compare( $wp_version, '5.4', '<' );

		if ( $is_old_wp_version ) {
			return '.editor-styles-wrapper .editor-block-list__block';
		}

		return '.editor-styles-wrapper .block-editor-block-list__block';
	}

	/**
	 * Regexes
	 */
	// Updated for WP 6.x+: .edit-post-visual-editor is now .editor-visual-editor
	public static $gutenbergy_selector_regex = '/^(\.edit-post-visual-editor|\.editor-visual-editor|\.editor-block-list__block).*$/';
	public static $root_regex = '/^(body|html).*$/';
	public static $title_regex = '/^(h1|h1\s+.*|\.single\s*\.entry-title.*|\.entry-title.*|\.page-title.*|\.article__?title.*)$/';
	/* Regexes based on which we will ignore selectors = do not include them in the selector list for a certain rule. */
	public static array $excluded_selectors_regex = [
		// We don't want to mess with buttons as we have a high likelihood of messing with the Gutenberg toolbar.
		'/^\s*button/',
		'/^\s*\.button/',
		'/^\s*input/',
		'/^\s*select/',
		'/^\s*#/',    // ignore all ids
		'/^\s*div#/', // ignore all ids

		//		'/\.u-/',
		//		'/\.c-/',
		//		'/\.o-/',
		//		'/\.site-/',
		//		'/\.card/',
		//
		//		'/^\s*\.archive/',
		//		'/^\s*\.search/',
		//		'/^\s*\.no-results/',
		//		'/^\s*\.home/',
		//		'/^\s*\.blog/',
		//		'/^\s*\.site-/',
		//		'/\.search/',
		//		'/\.page/',
		//		'/\.mce-content-body/',
		//		'/\.attachment/',
		//		'/\.mobile/',
		//
		//		'/\.sticky/',
		//		'/\.custom-logo-link/',
		//
		//		'/\.entry-meta/',
		//		'/\.entry-footer/',
		//		'/\.header-meta/',
		//		'/\.nav/',
		//		'/\.main-navigation/',
		//		'/navbar/',
		//		'/comment/',
		//		'/\.dummy/',
		//		'/\.back-to-top/',
		//		'/\.page-numbers/',
		//		'/\.featured/',
		//		'/\.widget/',
		//		'/\.edit-link/',
		//		'/\.posted-on/',
		//		'/\.cat-links/',
		//		'/\.posted-by/',
		//		'/\.more-link/',
		//
		//		'/jetpack/',
		//		'/wpforms/',
		//		'/contact-form/',
		//		'/sharedaddy/',
	];

	/**
	 * User messages to display in the WP admin.
	 *
	 * @var array
	 */
	protected array $user_messages = [
		'error'   => [],
		'warning' => [],
		'info'    => [],
	];

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
	 * Frontend output provider.
	 *
	 * @var FrontendOutput
	 */
	protected FrontendOutput $frontend_output;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Create the edit with blocks screen.
	 *
	 * @since 2.0.0
	 *
	 * @param Options         $options         Options.
	 * @param PluginSettings  $plugin_settings Plugin settings.
	 * @param Fonts           $sm_fonts        Style Manager Fonts.
	 * @param FrontendOutput  $frontend_output Frontend output.
	 * @param LoggerInterface $logger          Logger.
	 */
	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		Fonts $sm_fonts,
		FrontendOutput $frontend_output,
		LoggerInterface $logger
	) {
		$this->options         = $options;
		$this->plugin_settings = $plugin_settings;
		$this->sm_fonts        = $sm_fonts;
		$this->frontend_output = $frontend_output;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		// Styles and scripts when editing.
		$this->add_action( 'enqueue_block_editor_assets', 'enqueue_style_manager_scripts', 10 );
		$this->add_action( 'enqueue_block_assets', 'enqueue_carbon_fields_editor_styles', 10 );
		$this->add_action( 'enqueue_block_assets', 'enqueue_editor_dynamic_css', 999 );

		$this->add_filter( 'admin_body_class', 'add_sm_dark_classname_to_body' );
		$this->add_action( 'admin_enqueue_scripts', 'print_script_to_move_dark_classname_to_html' );

		// Deregister WP Font Library collections when SM is managing fonts,
		// to prevent duplicate font UI alongside Style Manager's font controls.
		$this->add_action( 'init', 'maybe_deregister_font_collections', 20 );
	}

	/**
	 * Enqueue Carbon Fields styles while WordPress resolves block iframe assets.
	 *
	 * Carbon Fields otherwise enqueues these styles from
	 * `admin_print_footer_scripts`, which is too late for the WordPress 7.1
	 * iframe asset pipeline and triggers compatibility-copy diagnostics.
	 *
	 * @since 2.5.1
	 */
	public function enqueue_carbon_fields_editor_styles() {
		if ( ! $this->is_admin_block_editor_screen() ) {
			return;
		}

		if ( ! defined( 'Carbon_Fields\\URL' ) || ! defined( 'Carbon_Fields\\VERSION' ) ) {
			return;
		}

		$suffix   = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$base_url = \Carbon_Fields\URL . '/build/gutenberg/';

		wp_enqueue_style(
			'carbon-fields-core',
			$base_url . 'core' . $suffix . '.css',
			[],
			\Carbon_Fields\VERSION
		);
		wp_enqueue_style(
			'carbon-fields-blocks',
			$base_url . 'blocks' . $suffix . '.css',
			[],
			\Carbon_Fields\VERSION
		);
	}

	/**
	 * Deregister WP Font Library font collections when Style Manager manages fonts.
	 *
	 * Prevents the WP Font Library UI from showing alongside SM's font controls.
	 * Only deregisters when SM is active and has font palettes configured.
	 */
	public function maybe_deregister_font_collections() {
		if ( ! function_exists( 'wp_unregister_font_collection' ) ) {
			return;
		}

		// Only deregister if SM is managing fonts (has font palettes).
		$sm_fonts_config = $this->options->get( 'sm_font_palette', false );
		if ( empty( $sm_fonts_config ) ) {
			return;
		}

		if ( function_exists( 'wp_unregister_font_collection' ) ) {
			wp_unregister_font_collection( 'google-fonts' );
		}
	}

	/**
	 * Enqueue SM dynamic CSS (colors + fonts) into the block editor via inline style.
	 *
	 * Replaces the previous __unstableResolvedAssets injection which is removed in WP 7.0.
	 */
	public function enqueue_editor_dynamic_css() {
		if ( ! $this->is_admin_block_editor_screen() ) {
			return;
		}

		if ( ! $this->plugin_settings->get( 'enable_editor_style', true ) ) {
			return;
		}

		$this->sm_fonts->enqueue_frontend_scripts_styles();

		add_filter( 'style_manager/font_css_selector', [ $this, 'gutenbergify_font_css_selectors' ], 10, 2 );
		$fonts_css = $this->sm_fonts->getFontsDynamicStyle();
		remove_filter( 'style_manager/font_css_selector', [ $this, 'gutenbergify_font_css_selectors' ], 10 );

		add_filter( 'style_manager/css_selector', [ $this, 'gutenbergify_css_selectors' ], 10, 2 );
		$dynamic_css = $this->frontend_output->get_dynamic_style();
		remove_filter( 'style_manager/css_selector', [ $this, 'gutenbergify_css_selectors' ], 10 );

		$css = $dynamic_css . $fonts_css;

		if ( empty( trim( $css ) ) ) {
			return;
		}

		wp_register_style( 'style-manager-editor-dynamic', false, [], \Pixelgrade\StyleManager\VERSION );
		wp_enqueue_style( 'style-manager-editor-dynamic' );
		wp_add_inline_style( 'style-manager-editor-dynamic', $css );
	}

	/**
	 * Determine whether the current request is for an admin block editor canvas.
	 *
	 * @return bool
	 */
	protected function is_admin_block_editor_screen(): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return false;
		}

		if ( method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor() ) {
			return true;
		}

		return in_array( $current_screen->id, [ 'site-editor', 'site-editor-v2' ], true );
	}

	/**
	 * Determine if Gutenberg is supported.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		$gutenberg = false;

		// Determine if the block editor is active for the frontend.
		if ( has_action( 'enqueue_block_assets' ) ) {
			// Gutenberg is installed and activated.
			$gutenberg = true;
		}

		// Determine if the block editor is being used in the WP admin.
		$current_screen = get_current_screen();
		if ( is_admin() && method_exists( $current_screen, 'is_block_editor' ) && get_current_screen()->is_block_editor() ) {
			$gutenberg = true;
		}

		return apply_filters( 'style_manager/gutenberg_is_supported', $gutenberg );
	}

	/**
	 * Retrieve the editor CSS file handle.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_editor_style_handle(): string {
		global $wp_styles;
		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return '';
		}

		// We need to look into the registered theme stylesheets and get the one most likely to be used for Gutenberg.
		// Thus, we can attach inline styles to it.
		$theme_dir_uri = get_template_directory_uri();
		$theme_slug    = get_template();

		$handle   = 'wp-edit-post'; // this is better than nothing as it is the main editor style.
		$reversed = array_reverse( $wp_styles->registered );
		/** @var \_WP_Dependency $style */
		foreach ( $reversed as $style ) {
			// This is the most precise.
			if ( is_string( $style->src ) && 0 === strpos( $style->src, $theme_dir_uri ) ) {
				$handle = $style->handle;
				break;
			}

			// If it is prefixed with the theme slug, it is good also.
			if ( is_string( $style->handle )
			     && ( 0 === strpos( $style->handle, $theme_slug . '-' ) || 0 === strpos( $style->handle, $theme_slug . '_' ) ) ) {

				$handle = $style->handle;
				break;
			}
		}

		return $handle;
	}

	/**
	 * Return the frontend CSS file handle.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_frontend_style_handle(): string {
		global $wp_styles;
		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return '';
		}

		// We need to look into the registered theme stylesheets and get the one most likely to be used for Gutenberg.
		// Thus, we can attach inline styles to it.
		$style_css_uri = get_template_directory_uri() . '/style.css';
		$theme_slug    = get_template();

		$handle   = 'wp-block-library'; // this is better than nothing as it is the main editor frontend style.
		$reversed = array_reverse( $wp_styles->registered );
		/** @var \_WP_Dependency $style */
		foreach ( $reversed as $style ) {
			// This is the most precise.
			if ( is_string( $style->src ) && 0 === strpos( $style->src, $style_css_uri ) ) {
				$handle = $style->handle;
				break;
			}

			// If it is prefixed with the theme slug, it is good also.
			if ( is_string( $style->handle )
			     && ( 0 === strpos( $style->handle, $theme_slug . '-' ) || 0 === strpos( $style->handle, $theme_slug . '_' ) )
			     && is_string( $style->src )
			     && false !== strpos( $style->src, '.css' ) ) {

				$handle = $style->handle;
				break;
			}
		}

		return $handle;
	}

	protected function enqueue_style_manager_scripts() {
		wp_enqueue_style( 'pixelgrade_style_manager-sm-colors-custom-properties' );
		$this->enqueue_style_manager_launcher();
	}

	/**
	 * Enqueue the lightweight Style Manager launcher for post editor screens.
	 *
	 * @since 2.3.0
	 */
	protected function enqueue_style_manager_launcher() {
		if ( ! $this->should_enqueue_style_manager_launcher() ) {
			return;
		}

		$context = $this->get_current_style_manager_launcher_context();
		$handle  = 'pixelgrade_style_manager-editor-launcher';

		wp_register_script(
			$handle,
			$this->plugin->get_url( 'dist/js/editor-launcher.js' ),
			[
				'wp-components',
				'wp-element',
				'wp-plugins',
				'wp-editor',
				'wp-data',
			],
			VERSION,
			true
		);

		wp_add_inline_script(
			$handle,
			ScriptsEnqueue::getlocalizeToWindowScript(
				'_styleManagerEditorLauncher',
				$this->get_style_manager_launcher_payload( $context['post_id'], $context['post_type'] )
			),
			'before'
		);

		wp_enqueue_script( $handle );

		$rtl_suffix = is_rtl() ? '-rtl' : '';
		wp_register_style(
			$handle,
			$this->plugin->get_url( 'dist/js/editor-launcher' . $rtl_suffix . '.css' ),
			[ 'wp-components' ],
			VERSION
		);
		wp_enqueue_style( $handle );
	}

	/**
	 * Determine whether the lightweight launcher belongs on the current screen.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function should_enqueue_style_manager_launcher(): bool {
		if ( ! $this->is_admin_post_block_editor_screen() ) {
			return false;
		}

		if ( ! is_sm_supported() ) {
			return false;
		}

		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Determine whether the current admin screen is a post editor block editor.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function is_admin_post_block_editor_screen(): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$current_screen = get_current_screen();
		if ( ! $current_screen ) {
			return false;
		}

		if ( $this->is_site_editor_screen( $current_screen ) ) {
			return false;
		}

		return method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor();
	}

	/**
	 * Determine whether a given screen is the Site Editor.
	 *
	 * @since 2.3.0
	 *
	 * @param object|null $screen Admin screen object.
	 *
	 * @return bool
	 */
	protected function is_site_editor_screen( $screen = null ): bool {
		if ( null === $screen ) {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();
		}

		return (bool) $screen && isset( $screen->id ) && in_array( $screen->id, [ 'site-editor', 'site-editor-v2' ], true );
	}

	/**
	 * Get the post editor context needed by the launcher.
	 *
	 * @since 2.3.0
	 *
	 * @return array{post_id:int,post_type:string}
	 */
	protected function get_current_style_manager_launcher_context(): array {
		$post_id   = 0;
		$post_type = '';
		$is_new    = $this->is_new_post_screen();

		if ( function_exists( 'get_current_screen' ) ) {
			$current_screen = get_current_screen();
			if ( $current_screen && ! empty( $current_screen->post_type ) ) {
				$post_type = $this->normalize_post_type( (string) $current_screen->post_type );
			}
		}

		if ( ! $is_new && isset( $GLOBALS['post'] ) && is_object( $GLOBALS['post'] ) ) {
			if ( ! empty( $GLOBALS['post']->ID ) ) {
				$post_id = absint( $GLOBALS['post']->ID );
			}

			if ( '' === $post_type && ! empty( $GLOBALS['post']->post_type ) ) {
				$post_type = $this->normalize_post_type( (string) $GLOBALS['post']->post_type );
			}
		}

		if ( ! $is_new && 0 === $post_id && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor context.
			$post_id = absint( wp_unslash( $_GET['post'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by absint().
		}

		if ( '' === $post_type && 0 < $post_id && function_exists( 'get_post_type' ) ) {
			$post_type = $this->normalize_post_type( (string) get_post_type( $post_id ) );
		}

		if ( '' === $post_type && isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only editor context.
			$post_type = $this->normalize_post_type( (string) wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by normalize_post_type().
		}

		return [
			'post_id'   => $post_id,
			'post_type' => $post_type,
		];
	}

	/**
	 * Determine whether the request is for a new post editor screen.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	protected function is_new_post_screen(): bool {
		global $pagenow;

		return isset( $pagenow ) && 'post-new.php' === $pagenow;
	}

	/**
	 * Build the launcher payload localized to the editor bundle.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id   Current post ID, or zero for unsaved entries.
	 * @param string $post_type Current post type.
	 *
	 * @return array
	 */
	protected function get_style_manager_launcher_payload( int $post_id = 0, string $post_type = '' ): array {
		return [
			'targetUrl' => esc_url_raw( $this->resolve_style_manager_launcher_target_url( $post_id, $post_type ) ),
			'icon'      => 'admin-customizer',
			'copy'      => [
				'title'       => esc_html__( 'Style Manager', '__plugin_txtd' ),
				'menuLabel'   => esc_html__( 'Style Manager', '__plugin_txtd' ),
				'eyebrow'     => esc_html__( 'Site-wide design', '__plugin_txtd' ),
				'heading'     => esc_html__( 'Open Style Manager', '__plugin_txtd' ),
				'description' => esc_html__( 'Colors, typography and spacing are global — edited in the Site Editor.', '__plugin_txtd' ),
				'buttonLabel' => esc_html__( 'Open Style Manager', '__plugin_txtd' ),
				'savingLabel' => esc_html__( 'Saving changes...', '__plugin_txtd' ),
				'saveError'   => esc_html__( 'Save the current entry, then open Style Manager again.', '__plugin_txtd' ),
			],
		];
	}

	/**
	 * Resolve the Site Editor URL the launcher should navigate to.
	 *
	 * @since 2.3.0
	 *
	 * @param int    $post_id   Current post ID, or zero for unsaved entries.
	 * @param string $post_type Current post type.
	 *
	 * @return string
	 */
	protected function resolve_style_manager_launcher_target_url( int $post_id = 0, string $post_type = '' ): string {
		$post_type = $this->normalize_post_type( $post_type );

		if ( 0 < $post_id && $this->is_site_editor_editable_post_type( $post_type ) ) {
			return $this->build_site_editor_url( [
				'postType'   => $post_type,
				'postId'     => $post_id,
				'canvas'     => 'edit',
				'sm-sidebar' => '1',
			] );
		}

		$template_slug = $this->get_rendering_template_slug( $post_type );
		if ( '' !== $template_slug ) {
			$template_post_id = $this->get_site_editor_template_post_id( $template_slug );

			if ( '' !== $template_post_id ) {
				return $this->build_site_editor_url( [
					'postType'   => 'wp_template',
					'postId'     => $template_post_id,
					'canvas'     => 'edit',
					'sm-sidebar' => '1',
				] );
			}
		}

		return $this->build_site_editor_url( [
			'canvas'     => 'edit',
			'sm-sidebar' => '1',
		] );
	}

	/**
	 * Determine whether a post type can be opened as current content in the Site Editor.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Post type.
	 *
	 * @return bool
	 */
	protected function is_site_editor_editable_post_type( string $post_type ): bool {
		if ( '' === $post_type || ! function_exists( 'get_post_type_object' ) || ! function_exists( 'post_type_supports' ) ) {
			return false;
		}

		// WordPress 7.0 Site Editor content routes only resolve pages and posts.
		if ( ! in_array( $post_type, [ 'page', 'post' ], true ) ) {
			return false;
		}

		$post_type_object = get_post_type_object( $post_type );
		if ( ! is_object( $post_type_object ) ) {
			return false;
		}

		return ! empty( $post_type_object->public )
		       && ! empty( $post_type_object->show_in_rest )
		       && post_type_supports( $post_type, 'editor' );
	}

	/**
	 * Get the template slug that normally renders a post type.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string
	 */
	protected function get_rendering_template_slug( string $post_type ): string {
		if ( '' === $post_type ) {
			return '';
		}

		if ( 'page' === $post_type ) {
			return 'page';
		}

		if ( 'post' === $post_type ) {
			return 'single';
		}

		return 'single-' . $post_type;
	}

	/**
	 * Build a Site Editor template post ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $template_slug Template slug.
	 *
	 * @return string
	 */
	protected function get_site_editor_template_post_id( string $template_slug ): string {
		if ( '' === $template_slug || ! function_exists( 'get_stylesheet' ) ) {
			return '';
		}

		$stylesheet = $this->normalize_post_type( (string) get_stylesheet() );
		if ( '' === $stylesheet ) {
			return '';
		}

		$template_slugs = [ $template_slug ];
		if ( 0 === strpos( $template_slug, 'single-' ) ) {
			$template_slugs[] = 'single';
		}

		foreach ( array_unique( $template_slugs ) as $candidate_slug ) {
			$template_post_id = $stylesheet . '//' . $candidate_slug;
			if ( $this->is_site_editor_template_available( $template_post_id ) ) {
				return $template_post_id;
			}
		}

		return '';
	}

	/**
	 * Determine whether the Site Editor can load a template post ID.
	 *
	 * @since 2.3.0
	 *
	 * @param string $template_post_id Template post ID.
	 *
	 * @return bool
	 */
	protected function is_site_editor_template_available( string $template_post_id ): bool {
		if ( ! function_exists( 'get_block_template' ) ) {
			return true;
		}

		return (bool) get_block_template( $template_post_id, 'wp_template' );
	}

	/**
	 * Build a Site Editor admin URL.
	 *
	 * @since 2.3.0
	 *
	 * @param array $args Query arguments.
	 *
	 * @return string
	 */
	protected function build_site_editor_url( array $args ): string {
		return admin_url( 'site-editor.php?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
	}

	/**
	 * Normalize a post type or template owner key.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string
	 */
	protected function normalize_post_type( string $post_type ): string {
		$post_type = strtolower( trim( $post_type ) );

		return preg_replace( '/[^a-z0-9_-]/', '', $post_type ) ?? '';
	}

	public function add_sm_dark_classname_to_body( $classname ) {
		// Read the setting directly from wp_options to avoid stale cached option-details values.
		$dark_mode = \get_option( 'sm_dark_mode_advanced', 'off' );

		if ( $dark_mode === 'on' ) {
			$classname = $classname . ' dark-mode-advanced';
		}

		return $classname;
	}

	public function print_script_to_move_dark_classname_to_html() {
		$data = 'wp.domReady( function() {
					var iframeSelector = "iframe[name=editor-canvas]";
					var syncDarkClass = function() {
						var isDark = document.body.classList.contains( "dark-mode-advanced" );
						document.documentElement.classList.toggle( "is-dark", isDark );

						var iframe = document.querySelector( iframeSelector );
						if ( iframe ) {
							if ( ! iframe.hasAttribute( "data-sm-dark-bound" ) ) {
								iframe.setAttribute( "data-sm-dark-bound", "1" );
								iframe.addEventListener( "load", syncDarkClass );
							}

							if ( iframe.contentDocument && iframe.contentDocument.documentElement ) {
								try {
									iframe.contentDocument.documentElement.classList.toggle( "is-dark", isDark );
								} catch (e) {}
							}
						}
					};

					syncDarkClass();

					if ( window.MutationObserver ) {
						var observer = new MutationObserver( function() {
							syncDarkClass();
						} );
						observer.observe( document.body, {
							childList: true,
							subtree: true,
							attributes: true,
							attributeFilter: [ "class" ]
						} );
					}
				} );';

		wp_enqueue_script( 'wp-dom-ready' );
		wp_add_inline_script( 'wp-dom-ready', $data );
	}

	/**
	 * Transform a set of selectors to target the Gutenberg editor.
	 *
	 * @since 2.0.0
	 *
	 * @param string $selectors
	 * @param array  $css_property
	 *
	 * @return string
	 */
	public function gutenbergify_css_selectors( string $selectors, array $css_property ): string {

		// Treat the selector(s) as an array.
		$selectors = $this->maybeExplodeSelectors( $selectors );

		$new_selectors = [];
		foreach ( $selectors as $selector ) {
			// Clean up
			$selector = trim( (string) $selector );

			// If the selector matches the excluded, skip it.
			if ( $this->preg_match_any( self::$excluded_selectors_regex, $selector ) ) {
				continue;
			}

			// If the selector is already Gutenbergy, we will not do anything to it
			if ( preg_match( self::$gutenbergy_selector_regex, $selector ) ) {
				$new_selectors[] = $selector;
				continue;
			}

			// We will let :root selectors be
			if ( ':root' === $selector ) {
				$new_selectors[] = $selector;
				continue;
			}

			// For root html elements, we will not prefix them, but replace them with the block and title namespace.
			if ( preg_match( self::$root_regex, $selector ) ) {
				// We will ignore pseudo-selectors
				if ( preg_match( '/^(body|html)[\:\+]+.*$/', $selector ) ) {
					continue;
				}

				// When it comes to background properties applied at the body level, we need to scope to the editor namespace
				if ( isset( $css_property['property'] ) && 0 === strpos( $css_property['property'], 'background' ) ) {
					$new_selectors[] = preg_replace( '/^(html body|body|html)/', self::$editor_namespace_selector, $selector ) ?? $selector;
				} else {
					$new_selectors[] = preg_replace( '/^(html body|body|html)/', self::get_block_namespace_selector(), $selector ) ?? $selector;
					$new_selectors[] = preg_replace( '/^(html body|body|html)/', self::$title_namespace_selector, $selector ) ?? $selector;
				}
				continue;
			}

			// If we encounter selectors that seem that they could target the post title,
			// we will add selectors for the Gutenberg title also.
			if ( preg_match( self::$title_regex, $selector ) ) {
				$new_selectors[] = preg_replace( self::$title_regex, self::$title_input_namespace_selector, $selector ) ?? $selector;
			}

			$new_selectors[] = self::get_block_namespace_selector() . ' ' . $selector;
		}

		return implode( ', ', $new_selectors );
	}

	/**
	 * Transform a set of font selectors to target the Gutenberg editor.
	 *
	 * @since 2.0.0
	 *
	 * @param array $selectors An array of standardized, cleaned selectors where the key is the selector and the value is possible details array.
	 *
	 * @return array
	 */
	public function gutenbergify_font_css_selectors( array $selectors ): array {

		$new_selectors = [];
		foreach ( $selectors as $selector => $selector_details ) {
			// If the selector matches the excluded, skip it.
			if ( $this->preg_match_any( self::$excluded_selectors_regex, $selector ) ) {
				continue;
			}

			// If the selector is already Gutenbergy, we will not do anything to it
			if ( preg_match( self::$gutenbergy_selector_regex, $selector ) ) {
				$new_selectors[ $selector ] = $selector_details;
				continue;
			}

			// We will let :root selectors be
			if ( ':root' === $selector ) {
				$new_selectors[ $selector ] = $selector_details;
				continue;
			}

			// For root html elements, we will not prefix them, but replace them with the block and title namespace.
			if ( preg_match( self::$root_regex, $selector ) ) {
				$new_selector                   = preg_replace( '/^(html body|body|html|)/', self::get_block_namespace_selector(), $selector ) ?? $selector;
				$new_selectors[ $new_selector ] = $selector_details;
				$new_selector                   = preg_replace( '/^(html body|body|html)/', self::$title_namespace_selector, $selector ) ?? $selector;
				$new_selectors[ $new_selector ] = $selector_details;
				continue;
			}

			// If we encounter selectors that seem that they could target the post title,
			// we will add selectors for the Gutenberg title also.
			if ( preg_match( self::$title_regex, $selector ) ) {
				$new_selector                   = preg_replace( self::$title_regex, self::$title_input_namespace_selector, $selector ) ?? $selector;
				$new_selectors[ $new_selector ] = $selector_details;
			}

			$selector                   = self::get_block_namespace_selector() . ' ' . $selector;
			$new_selectors[ $selector ] = $selector_details;
		}

		return $new_selectors;
	}

	/**
	 * Preg_match a series of regex against a subject.
	 *
	 * @since 2.0.0
	 *
	 * @param string|array $regexes
	 * @param string       $subject
	 *
	 * @return bool Returns true if at least one of the regex matches, false otherwise.
	 */
	public function preg_match_any( $regexes, string $subject ): bool {
		if ( is_string( $regexes ) ) {
			$regexes = [ $regexes ];
		}

		if ( ! is_array( $regexes ) ) {
			return false;
		}

		foreach ( $regexes as $regex ) {
			if ( preg_match( $regex, $subject ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Attempt to split a string with selectors and return the parts as an array.
	 * If not a string or no comma present, just returns the value.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $value
	 *
	 * @return array|false|string[]
	 */
	public function maybeExplodeSelectors( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		return preg_split( '#[\s]*,[\s]*#', $value, - 1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
	}
}

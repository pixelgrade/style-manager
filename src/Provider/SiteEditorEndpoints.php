<?php
/**
 * REST endpoints for the Site Editor integration.
 *
 * @since   2.3.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Screen\EditWithBlocks;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Site Editor REST endpoints provider class.
 *
 * @since 2.3.0
 */
class SiteEditorEndpoints extends AbstractHookProvider {

	/**
	 * Headless Customizer.
	 *
	 * @var HeadlessCustomizer
	 */
	protected HeadlessCustomizer $headless_customizer;

	/**
	 * Edit with blocks screen (for the editor CSS selector transforms).
	 *
	 * @var EditWithBlocks
	 */
	protected EditWithBlocks $edit_with_blocks;

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
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param EditWithBlocks     $edit_with_blocks    Edit with blocks screen.
	 * @param Fonts              $sm_fonts            Style Manager Fonts.
	 * @param FrontendOutput     $frontend_output     Frontend output.
	 */
	public function __construct(
		HeadlessCustomizer $headless_customizer,
		EditWithBlocks $edit_with_blocks,
		Fonts $sm_fonts,
		FrontendOutput $frontend_output
	) {
		$this->headless_customizer = $headless_customizer;
		$this->edit_with_blocks    = $edit_with_blocks;
		$this->sm_fonts            = $sm_fonts;
		$this->frontend_output     = $frontend_output;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.3.0
	 */
	public function register_hooks() {
		$this->add_action( 'rest_api_init', 'register_routes' );
	}

	/**
	 * Register the REST routes.
	 *
	 * @since 2.3.0
	 */
	protected function register_routes() {
		register_rest_route(
			'style_manager/v1',
			'/site-editor/save',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_save' ],
				'permission_callback' => [ $this, 'check_permissions' ],
				'args'                => [
					'settings' => [
						'required'          => true,
						'validate_callback' => static function ( $value ) {
							return is_array( $value ) && ! empty( $value );
						},
					],
				],
			]
		);

		register_rest_route(
			'style_manager/v1',
			'/site-editor/css',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_css' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}

	/**
	 * Permissions check: same capability the Customizer requires.
	 *
	 * @return bool
	 */
	public function check_permissions(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Save a map of setting_id => value through a published changeset.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_save( \WP_REST_Request $request ) {
		$values = $request->get_param( 'settings' );

		$result = $this->headless_customizer->save( (array) $values );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// A CSS regeneration hiccup must never make a successful save look
		// like a failure — the editor falls back to its live-preview CSS.
		try {
			$result['css'] = $this->get_css_payload();
		} catch ( \Throwable $e ) {
			$result['css'] = null;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Return freshly generated CSS payloads for the block editor.
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_css() {
		return rest_ensure_response( [ 'css' => $this->get_css_payload() ] );
	}

	/**
	 * Generate the same CSS EditWithBlocks inlines into the block editor
	 * (`style-manager-editor-dynamic`), plus the frontend dynamic CSS.
	 *
	 * @return array
	 */
	protected function get_css_payload(): array {
		add_filter( 'style_manager/font_css_selector', [ $this->edit_with_blocks, 'gutenbergify_font_css_selectors' ], 10, 2 );
		$editor_fonts_css = $this->sm_fonts->getFontsDynamicStyle();
		remove_filter( 'style_manager/font_css_selector', [ $this->edit_with_blocks, 'gutenbergify_font_css_selectors' ], 10 );

		add_filter( 'style_manager/css_selector', [ $this->edit_with_blocks, 'gutenbergify_css_selectors' ], 10, 2 );
		$editor_dynamic_css = $this->frontend_output->get_dynamic_style();
		remove_filter( 'style_manager/css_selector', [ $this->edit_with_blocks, 'gutenbergify_css_selectors' ], 10 );

		return [
			'editor'   => $editor_dynamic_css . $editor_fonts_css,
			'frontend' => $this->frontend_output->get_dynamic_style() . $this->sm_fonts->getFontsDynamicStyle(),
		];
	}
}

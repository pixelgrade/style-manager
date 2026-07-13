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

use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\FontPalettes;
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
	 * Font palettes.
	 *
	 * @var FontPalettes
	 */
	protected FontPalettes $font_palettes;

	/**
	 * Frontend output provider.
	 *
	 * @var FrontendOutput
	 */
	protected FrontendOutput $frontend_output;

	/**
	 * The generated palette output setting produced by the Color System builder.
	 */
	protected const PALETTE_OUTPUT_SETTING_ID = 'sm_advanced_palette_output';

	/**
	 * Free Color System settings that justify saving a generated palette output.
	 */
	protected const FREE_PALETTE_SETTING_IDS = [
		'sm_advanced_palette_source',
		ColorPalettes::SM_COLOR_PALETTE_OPTION_KEY,
		ColorPalettes::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY,
	];

	/**
	 * @param HeadlessCustomizer $headless_customizer Headless Customizer.
	 * @param EditWithBlocks     $edit_with_blocks    Edit with blocks screen.
	 * @param Fonts              $sm_fonts            Style Manager Fonts.
	 * @param FontPalettes       $font_palettes       Font palettes.
	 * @param FrontendOutput     $frontend_output     Frontend output.
	 */
	public function __construct(
		HeadlessCustomizer $headless_customizer,
		EditWithBlocks $edit_with_blocks,
		Fonts $sm_fonts,
		FontPalettes $font_palettes,
		FrontendOutput $frontend_output
	) {
		$this->headless_customizer = $headless_customizer;
		$this->edit_with_blocks    = $edit_with_blocks;
		$this->sm_fonts            = $sm_fonts;
		$this->font_palettes       = $font_palettes;
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
		// The Site Editor settings entity record (consumed through the
		// core-data entities API: GET to resolve, PUT to save edits).
		register_rest_route(
			'style_manager/v1',
			'/site-editor/settings/(?P<id>[\w-]+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'handle_get_settings_record' ],
					'permission_callback' => [ $this, 'check_permissions' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'handle_save_settings_record' ],
					'permission_callback' => [ $this, 'check_permissions' ],
					'args'                => [
						'settings' => [
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_array( $value ) && ! empty( $value );
							},
						],
					],
				],
			]
		);

		// Re-evaluate control active states with unsaved values previewed —
		// the Customizer does the equivalent on every preview refresh.
		register_rest_route(
			'style_manager/v1',
			'/site-editor/active-states',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_active_states' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		// The Live Site preview changeset (frontend preview of unsaved values).
		register_rest_route(
			'style_manager/v1',
			'/site-editor/preview-changeset',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_preview_changeset' ],
				'permission_callback' => [ $this, 'check_permissions' ],
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
	 * Resolve the Site Editor settings entity record.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get_settings_record( \WP_REST_Request $request ) {
		return rest_ensure_response( $this->get_settings_record( (string) $request->get_param( 'id' ) ) );
	}

	/**
	 * Save the entity record's edited `settings` through a published changeset
	 * and return the updated record.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_save_settings_record( \WP_REST_Request $request ) {
		$values = $this->strip_locked_premium_settings( (array) $request->get_param( 'settings' ) );
		$values = $this->strip_locked_premium_font_palette( $values );

		$result = $this->headless_customizer->save( $values );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->apply_post_save_side_effects( $result['saved'] );

		/**
		 * Fires after Site Editor settings have been successfully saved.
		 *
		 * Mirrors the Customizer's `customize_save_after` for consumers that need
		 * a "settings were just saved" signal outside the Customizer (e.g. local
		 * font mirroring, see Provider\LocalFonts).
		 *
		 * @since 2.4.0
		 *
		 * @param string[] $saved_setting_ids Setting IDs saved by the Site Editor endpoint.
		 */
		do_action( 'style_manager/settings_saved', $result['saved'] );

		$record          = $this->get_settings_record( (string) $request->get_param( 'id' ) );
		$record['saved'] = $result['saved'];

		// A CSS regeneration hiccup must never make a successful save look
		// like a failure — the editor falls back to its live-preview CSS.
		try {
			$record['css'] = $this->get_css_payload();
		} catch ( \Throwable $e ) {
			$record['css'] = null;
		}

		return rest_ensure_response( $record );
	}

	/**
	 * The real Plus gate: when the advanced controls are locked (no Pixelgrade Plus entitlement),
	 * strip the premium palette-structure settings from what gets persisted, keeping every other
	 * setting. Free users can fully USE the controls as a live trial — they just can't SAVE the
	 * premium settings, so trial changes revert to last-saved on reload.
	 *
	 * Server-side enforcement is intrinsic — never client-trusted. The premium id list is the single
	 * source of truth shared with the UI gating (\Pixelgrade\StyleManager\plus_gated_setting_ids()).
	 *
	 * @since 2.2.15
	 *
	 * @param array $values Setting id => value map submitted by the editor.
	 *
	 * @return array The filtered values to persist.
	 */
	protected function strip_locked_premium_settings( array $values ): array {
		if ( ! \Pixelgrade\StyleManager\plus_advanced_controls_locked() ) {
			return $values;
		}

		$original_values             = $values;
		$premium_ids                 = \Pixelgrade\StyleManager\plus_gated_setting_ids();
		$has_premium_setting_change = false;

		foreach ( $premium_ids as $premium_id ) {
			if ( array_key_exists( $premium_id, $values ) ) {
				$has_premium_setting_change = true;
			}
			unset( $values[ $premium_id ] );
		}

		if ( array_key_exists( self::PALETTE_OUTPUT_SETTING_ID, $values )
			&& ( $has_premium_setting_change || ! $this->has_free_palette_setting_change( $original_values ) ) ) {
			unset( $values[ self::PALETTE_OUTPUT_SETTING_ID ] );
		}

		return $values;
	}

	protected function has_free_palette_setting_change( array $values ): bool {
		foreach ( self::FREE_PALETTE_SETTING_IDS as $setting_id ) {
			if ( array_key_exists( $setting_id, $values ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The save gate for pro font palettes: the control lets free users SELECT a
	 * pro palette and watch the live preview change, but the selection must not
	 * persist. When the submitted `sm_font_palette` pointer is a tier-locked
	 * (pro) palette, drop the pointer so the pick reverts to last-saved on reload.
	 *
	 * Dropping the pointer alone is sufficient: the per-element font values a
	 * palette diffuses into are already in plus_gated_setting_ids() and stripped
	 * by strip_locked_premium_settings(), and apply_post_save_side_effects()
	 * only re-applies a palette when its pointer is in the saved ids — which it
	 * no longer is here. Intrinsic server-side enforcement, never client-trusted.
	 *
	 * @since 2.4.0
	 *
	 * @param array $values Setting id => value map submitted by the editor.
	 *
	 * @return array The filtered values to persist.
	 */
	protected function strip_locked_premium_font_palette( array $values ): array {
		$key = FontPalettes::SM_FONT_PALETTE_OPTION_KEY;

		if ( ! array_key_exists( $key, $values ) ) {
			return $values;
		}

		if ( $this->font_palettes->is_palette_tier_locked( (string) $values[ $key ] ) ) {
			unset( $values[ $key ] );
		}

		return $values;
	}

	/**
	 * Applies server-side effects that the Customizer UI used to perform.
	 *
	 * @since 2.3.0
	 *
	 * @param string[] $saved_setting_ids Setting IDs saved by the Site Editor endpoint.
	 */
	protected function apply_post_save_side_effects( array $saved_setting_ids ): void {
		if ( ! in_array( FontPalettes::SM_FONT_PALETTE_OPTION_KEY, $saved_setting_ids, true ) ) {
			return;
		}

		$this->font_palettes->apply_current_font_palette_to_connected_fields();
	}

	/**
	 * Build the entity record.
	 *
	 * @param string $id
	 *
	 * @return array
	 */
	protected function get_settings_record( string $id ): array {
		return [
			'id'       => $id,
			'title'    => esc_html__( 'Design system settings', '__plugin_txtd' ),
			'settings' => $this->headless_customizer->get_settings_values(),
		];
	}

	/**
	 * Re-evaluate control active states with the client's unsaved values.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_active_states( \WP_REST_Request $request ) {
		$values = $request->get_param( 'settings' );

		// get_active_states() previews the values, so the body classes below
		// are computed against the same previewed state.
		$active_states = $this->headless_customizer->get_active_states( is_array( $values ) ? $values : [] );

		return rest_ensure_response(
			[
				'activeStates'      => $active_states,
				'bodyClasses'       => $this->headless_customizer->get_preview_body_classes(),
				'bodyClassPrefixes' => $this->headless_customizer->get_preview_body_class_prefixes(),
			]
		);
	}

	/**
	 * Create/update the Live Site preview changeset with unsaved values.
	 *
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_preview_changeset( \WP_REST_Request $request ) {
		$values = $request->get_param( 'settings' );
		$uuid   = $request->get_param( 'uuid' );

		$result = $this->headless_customizer->upsert_preview_changeset(
			is_array( $values ) ? $values : [],
			is_string( $uuid ) ? $uuid : null
		);

		if ( is_wp_error( $result ) ) {
			return $result;
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

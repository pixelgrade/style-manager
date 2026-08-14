<?php
/**
 * This is the class that handles the logic for Layout.
 *
 * The Layout section is the design system's page-anatomy + rhythm contract:
 * the site container width, the content inset (reading measure), the rail scale
 * (sidebar widths), the rail gap (content-to-rail gutter), and the spacing
 * level (vertical rhythm). It absorbed the former Spacing section on
 * 2026-07-23 — container and inset are one page
 * anatomy, so a single section reads as one coherent story.
 *
 * @since   2.4.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Customize;

use Pixelgrade\StyleManager\Utils\ArrayHelpers;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Provides the Layout logic.
 *
 * @since 2.4.0
 */
class LayoutSection extends AbstractHookProvider {

	/**
	 * The rail-scale reference base.
	 *
	 * The Small/Medium/Large rail widths derive from the base value at fixed
	 * ratios. This anchor is chosen so a base of 288 reproduces today's exact
	 * hardcoded rail widths (Small 288 / Medium 330 / Large 400), keeping the
	 * token model byte-identical to the pre-token defaults at the reference.
	 *
	 * @since 2.4.0
	 */
	public const RAIL_SCALE_BASE = 288;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.4.0
	 */
	public function register_hooks() {
		/*
		 * Handle the Customizer Style Manager section config.
		 */
		$this->add_filter( 'style_manager/filter_fields', 'add_style_manager_section_layout_config', 12, 1 );
		$this->add_filter( 'style_manager/sm_panel_config', 'reorganize_customizer_controls', 20, 2 );
	}

	/**
	 * Determine if the Layout Section is supported.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		return apply_filters( 'style_manager/layout_is_supported', true );
	}

	/**
	 * Set up the Style Manager Customizer section layout config.
	 *
	 * This handles the base configuration for the controls in the Style Manager section. We expect other parties (e.g. the theme),
	 * to come and fill up the missing details (e.g. connected fields).
	 *
	 * @since 2.4.0
	 *
	 * @param array $config This holds required keys for the plugin config like 'opt-name', 'panels', 'settings'.
	 *
	 * @return array
	 */
	protected function add_style_manager_section_layout_config( array $config ): array {
		// If there is no Layout support, bail early.
		if ( ! $this->is_supported() ) {
			return $config;
		}

		if ( ! isset( $config['sections']['style_manager_section'] ) ) {
			$config['sections']['style_manager_section'] = [];
		}

		// The section might be already defined, thus we merge, not replace the entire section config.
		$config['sections']['style_manager_section'] = ArrayHelpers::array_merge_recursive_distinct( $config['sections']['style_manager_section'], [
			'options' => [
				'sm_site_container_width' => [
					'type'         => 'range',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => 'sm_site_container_width',
					'live'         => true,
					'label'        => esc_html__( 'Site Container', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Adjust the maximum amount of width your site content extends to.', '__plugin_txtd' ),
					'default'      => 75,
					'input_attrs'  => [
						'min'          => 60,
						'max'          => 100,
						'step'         => 1,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property' => '--sm-site-container-width',
							'selector' => ':root',
							'unit'     => '',
						],
					],
				],
				// The reading measure: how far content is inset within the Site
				// Container. Part of the one page-anatomy story (moved here when the
				// Spacing section merged into Layout — config is byte-identical).
				'sm_content_inset'        => [
					'type'         => 'range',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => 'sm_content_inset',
					'live'         => true,
					'label'        => esc_html__( 'Content Inset', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Adjust how much the content is visually inset within the Site Container.', '__plugin_txtd' ),
					'default'      => 230,
					'input_attrs'  => [
						'min'          => 100,
						'max'          => 300,
						'step'         => 10,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property' => '--sm-content-inset',
							'selector' => ':root',
							'unit'     => '',
						],
					],
				],
				// The rail-scale presets (the "face"): named {base, pitch} points that
				// each write BOTH sm_rail_scale and sm_rail_pitch. The active preset is
				// DERIVED from those two values by the preset field JS (Custom
				// off-preset, never stored as provenance).
				'sm_rail_scale_preset'    => [
					'type'         => 'preset',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_scale_preset',
					'live'         => true,
					'label'        => esc_html__( 'Rail Scale', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Choose how wide sidebars and rails are site-wide. Base sets the Small rail; Pitch sets how steeply Medium and Large rise from it.', '__plugin_txtd' ),
					'default'      => 'custom',
					'choices_type' => 'buttons',
					'choices'      => [
						'flat'      => [
							'label'   => esc_html__( 'Flat', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 300, 'sm_rail_pitch' => 0 ],
						],
						'compact'   => [
							'label'   => esc_html__( 'Compact', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 250, 'sm_rail_pitch' => 16 ],
						],
						'normal'    => [
							'label'   => esc_html__( 'Normal', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 300, 'sm_rail_pitch' => 22 ],
						],
						'roomy'     => [
							'label'   => esc_html__( 'Roomy', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 340, 'sm_rail_pitch' => 28 ],
						],
						'editorial' => [
							'label'   => esc_html__( 'Editorial', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 380, 'sm_rail_pitch' => 36 ],
						],
						'custom'    => [
							'label'   => esc_html__( 'Custom', '__plugin_txtd' ),
							'options' => [],
						],
					],
				],
				// The rail-scale base (elevation): a continuous slider setting the Small
				// rail. Source of truth that emits the per-side-ready Small/Medium/Large
				// tokens through sm_rail_scale_css_cb (which also reads the pitch).
				//
				// Legacy-until-touched: the default is an empty sentinel. While BOTH
				// base and pitch are unset the callback emits NOTHING, so consumers keep
				// their built-in fallbacks and rendering stays byte-identical to the
				// pre-token behaviour. See style_manager_rail_widths() for the full
				// migration contract (both-unset / v1-compat / v2).
				'sm_rail_scale'           => [
					'type'         => 'range',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_scale',
					'live'         => true,
					'label'        => esc_html__( 'Rail Base (Small)', '__plugin_txtd' ),
					'desc'         => esc_html__( 'The base rail width — the Small rail equals this value; Medium and Large rise from it according to the Pitch.', '__plugin_txtd' ),
					'default'      => '',
					'input_attrs'  => [
						'min'          => 100,
						'max'          => 420,
						'step'         => 1,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property'        => '--sm-rail-small',
							'selector'        => ':root',
							'unit'            => '',
							'callback_filter' => 'sm_rail_scale_css_cb',
						],
						[
							'property'        => '--sm-rail-medium',
							'selector'        => ':root',
							'unit'            => '',
							'callback_filter' => 'sm_rail_scale_css_cb',
						],
						[
							'property'        => '--sm-rail-large',
							'selector'        => ':root',
							'unit'            => '',
							'callback_filter' => 'sm_rail_scale_css_cb',
						],
					],
				],
				// The rail-scale pitch (rising angle), echoing Typography's Pitch: a
				// per-step multiplier. At 0 degrees Small = Medium = Large (no
				// hierarchy); it rises to about x1.73 at full pitch, softly capped near
				// 600px. Touching pitch switches emission from v1-compat to the v2 math.
				//
				// It carries CSS only so the live preview recomputes the rail tokens
				// when pitch changes (sm_rail_pitch_css_cb is inert on the frontend —
				// sm_rail_scale reads the pitch and emits).
				'sm_rail_pitch'           => [
					'type'         => 'range',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_pitch',
					'live'         => true,
					'label'        => esc_html__( 'Rail Pitch', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Adjust the rising angle of the rail sizes. A flat ‘0’ degree Pitch makes Small, Medium, and Large equal, removing the hierarchy between them.', '__plugin_txtd' ),
					'default'      => '',
					'input_attrs'  => [
						'min'          => 0,
						'max'          => 45,
						'step'         => 1,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property'        => '--sm-rail-pitch-sync',
							'selector'        => ':root',
							'unit'            => '',
							'callback_filter' => 'sm_rail_pitch_css_cb',
						],
					],
				],
				// Content-to-rail gutter: independent from both the rail widths and
				// the global rhythm. The value is a multiplier consumed by Nova Blocks
				// against its fluid spacing token. A default of 2 preserves the
				// pre-control Sidecar geometry byte-for-byte.
				'sm_rail_gap'             => [
					'type'         => 'range',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_gap',
					'live'         => true,
					'label'        => esc_html__( 'Rail Gap', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Adjust the distance between the main content and adjacent sidebars without changing their widths or the site rhythm.', '__plugin_txtd' ),
					'default'      => 2,
					'input_attrs'  => [
						'min'          => 1,
						'max'          => 5,
						'step'         => 0.25,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property' => '--sm-rail-gap',
							'selector' => ':root',
							'unit'     => '',
						],
					],
				],
				// Vertical rhythm: the multiplication factor for the distance between
				// elements. Moved here from the merged Spacing section (config is
				// byte-identical).
				'sm_spacing_level'        => [
					'type'         => 'range',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => 'sm_spacing_level',
					'live'         => true,
					'label'        => esc_html__( 'Spacing Level', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Adjust the multiplication factor of the distance between elements.', '__plugin_txtd' ),
					'default'      => 1,
					'input_attrs'  => [
						'min'          => 0,
						'max'          => 2,
						'step'         => 0.1,
						'data-preview' => true,
					],
					'css'          => [
						[
							'property' => '--sm-spacing-level',
							'selector' => ':root',
							'unit'     => '',
						],
					],
				],
			],
		] );

		return $config;
	}

	/**
	 * Reorganize the Customizer controls.
	 *
	 * @since 2.4.0
	 *
	 * @param array $sm_panel_config
	 * @param array $sm_section_config
	 *
	 * @return array
	 */
	protected function reorganize_customizer_controls( array $sm_panel_config, array $sm_section_config ): array {

		// The page-anatomy story, top to bottom: how wide the container is, how far
		// content is inset, how wide the rails are (presets face + base slider span),
		// the content-to-rail gutter, then the vertical rhythm between elements.
		$layout_section_fields = [
			'sm_site_container_width',
			'sm_content_inset',
			'sm_rail_scale_preset',
			'sm_rail_scale',
			'sm_rail_pitch',
			'sm_rail_gap',
			'sm_spacing_level',
		];

		$layout_section_config = [
			'title'      => esc_html__( 'Layout', '__plugin_txtd' ),
			'section_id' => 'sm_layout_section',
			'priority'   => 28,
			'options'    => [],
		];

		foreach ( $layout_section_fields as $field_id ) {
			if ( ! isset( $sm_section_config['options'][ $field_id ] ) ) {
				continue;
			}

			if ( empty( $layout_section_config['options'] ) ) {
				$layout_section_config['options'] = [ $field_id => $sm_section_config['options'][ $field_id ] ];
			} else {
				$layout_section_config['options'] = array_merge( $layout_section_config['options'], [ $field_id => $sm_section_config['options'][ $field_id ] ] );
			}
		}

		$sm_panel_config['sections']['sm_layout_section'] = $layout_section_config;

		return $sm_panel_config;
	}
}

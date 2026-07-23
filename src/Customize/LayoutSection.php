<?php
/**
 * This is the class that handles the logic for Layout.
 *
 * The Layout section is the geometry contract of the design system (the site
 * container width and the sidebar/rail scale). It is a sibling of the Spacing
 * section (which owns rhythm). Each panel section maps 1:1 to a design-system
 * contract.
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
				// The rail-scale presets (the "face"): named segmented options that
				// each write a base value into sm_rail_scale. The active preset is
				// DERIVED from sm_rail_scale by the preset field JS (Custom off-preset,
				// never stored as provenance).
				'sm_rail_scale_preset'    => [
					'type'         => 'preset',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_scale_preset',
					'live'         => true,
					'label'        => esc_html__( 'Rail Scale', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Choose how wide sidebars and rails are site-wide. Small, Medium, and Large rail sizes scale together from the base value.', '__plugin_txtd' ),
					'default'      => 'custom',
					'choices_type' => 'buttons',
					'choices'      => [
						'compact'   => [
							'label'   => esc_html__( 'Compact', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 250 ],
						],
						'normal'    => [
							'label'   => esc_html__( 'Normal', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 300 ],
						],
						'roomy'     => [
							'label'   => esc_html__( 'Roomy', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 340 ],
						],
						'editorial' => [
							'label'   => esc_html__( 'Editorial', '__plugin_txtd' ),
							'options' => [ 'sm_rail_scale' => 380 ],
						],
						'custom'    => [
							'label'   => esc_html__( 'Custom', '__plugin_txtd' ),
							'options' => [],
						],
					],
				],
				// The rail-scale base (the "span"): a continuous slider. This is the
				// single source of truth for the rail scale. It emits the per-side-ready
				// Small/Medium/Large tokens (derived at fixed ratios) through
				// sm_rail_scale_css_cb.
				//
				// Legacy-until-touched: the default is an empty sentinel. While the
				// value is unset the callback emits NOTHING, so consumers keep their
				// built-in fallbacks and rendering stays byte-identical to the
				// pre-token behaviour (Small content-inset-derived, Medium 330,
				// Large 400). The token model takes over the moment the value is
				// first touched.
				'sm_rail_scale'           => [
					'type'         => 'range',
					'setting_type' => 'option',
					'setting_id'   => 'sm_rail_scale',
					'live'         => true,
					'label'        => esc_html__( 'Rail Base Width', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Fine-tune the base rail width. Small equals this value; Medium and Large scale up from it.', '__plugin_txtd' ),
					'default'      => '',
					'input_attrs'  => [
						'min'          => 240,
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

		// Order: container width, then the rail-scale presets (the face), then the
		// rail-scale base slider (the span beneath).
		$layout_section_fields = [
			'sm_site_container_width',
			'sm_rail_scale_preset',
			'sm_rail_scale',
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

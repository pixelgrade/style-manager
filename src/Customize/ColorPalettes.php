<?php
/**
 * This is the class that handles the logic for Color Palettes.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Customize;

use Pixelgrade\StyleManager\Utils\ArrayHelpers;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;
use function Pixelgrade\StyleManager\is_sm_supported;

/**
 * Provides the color palettes logic.
 *
 * @since 2.0.0
 */
class ColorPalettes extends AbstractHookProvider {

	const SM_COLOR_PALETTE_OPTION_KEY = 'sm_color_palette_in_use';
	const SM_COLOR_PALETTE_VARIATION_OPTION_KEY = 'sm_color_palette_variation';
	const SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY = 'sm_is_custom_color_palette';

	/**
	 * Design assets.
	 *
	 * @var DesignAssets
	 */
	protected DesignAssets $design_assets;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param DesignAssets    $design_assets Design assets.
	 * @param LoggerInterface $logger        Logger.
	 */
	public function __construct(
		DesignAssets $design_assets,
		LoggerInterface $logger
	) {

		$this->design_assets = $design_assets;
		$this->logger        = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		/*
		 * Handle the Customizer Style Manager section config.
		 */
		$this->add_filter( 'style_manager/filter_fields', 'add_style_manager_new_section_master_colors_config', 13, 1 );
		$this->add_filter( 'style_manager/sm_panel_config', 'reorganize_customizer_controls', 10, 2 );

		// This needs to come after the external theme config has been applied
		$this->add_filter( 'style_manager/filter_fields', 'maybe_enhance_dark_mode_control', 120, 1 );

		$this->add_filter( 'style_manager/final_config', 'alter_master_controls_connected_fields', 100, 1 );
		$this->add_filter( 'style_manager/final_config', 'add_color_usage_section', 110, 1 );

		$this->add_filter( 'novablocks_block_editor_settings', 'add_color_palettes_to_novablocks_settings' );

		/**
		 * Add color palettes usage to site data.
		 */
		$this->add_filter( 'style_manager/get_site_data', 'add_palettes_to_site_data', 10, 1 );

		// Add data to be passed to JS.
		$this->add_filter( 'style_manager/localized_js_settings', 'add_to_localized_data', 10, 1 );

		$this->add_filter( 'language_attributes', 'add_dark_mode_data_attribute', 10, 2 );

		$this->add_action( 'admin_init', 'editor_color_palettes', 20 );
	}

	/**
	 * Determine if Color Palettes are supported.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		// For now we will only use the fact that Style Manager is supported.
		return apply_filters( 'style_manager/color_palettes_are_supported', is_sm_supported() );
	}

	/**
	 * Add the SM Color Palettes to the editor sidebar.
	 *
	 * @since 2.0.0
	 */
	public function editor_color_palettes() {

		// Bail if Color Palettes are not supported
		if ( ! $this->is_supported() ) {
			return;
		}

		$editor_color_palettes = [];

		if ( ! empty( $editor_color_palettes ) ) {
			/**
			 * Custom colors for use in the editor.
			 *
			 * @link https://wordpress.org/gutenberg/handbook/reference/theme-support/
			 */
			add_theme_support(
				'editor-color-palette',
				$editor_color_palettes
			);
		}
	}

	/**
	 * Get the color palettes configuration.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $skip_cache Optional. Whether to use the cached config or fetch a new one.
	 *
	 * @return array
	 */
	public function get_palettes( bool $skip_cache = false ): array {
		$config = $this->design_assets->get_entry( 'color_palettes_v2', $skip_cache );
		if ( is_null( $config ) ) {
			$config = $this->get_default_config();
		}

		return apply_filters( 'style_manager/get_color_palettes', $config );
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	protected function alter_master_controls_connected_fields( array $config ): array {

		$switch_foreground_connected_fields = [];
		$switch_accent_connected_fields     = [];

		if ( ! isset( $config['panels']['theme_options_panel']['sections']['colors_section']['options'] ) ) {
			return $config;
		}

		foreach ( $config['panels']['theme_options_panel']['sections']['colors_section']['options'] as $id => $option_config ) {

			if ( $option_config['type'] === 'sm_toggle' ) {
				if ( $option_config['default'] === 'on' ) {
					$switch_accent_connected_fields[] = $id;
				} else {
					$switch_foreground_connected_fields[] = $id;
				}
			}
		}

		if ( ! isset( $config['panels']['style_manager_panel']['sections']['sm_color_palettes_section']['options'] ) ) {
			return $config;
		}

		$options = $config['panels']['style_manager_panel']['sections']['sm_color_palettes_section']['options'];

		$switch_dark_count = count( $switch_foreground_connected_fields );

		// Avoid division by zero.
		if ( empty( $switch_dark_count ) ) {
			$switch_dark_count = 1;
		}

		$switch_accent_count = count( $switch_accent_connected_fields );

		if ( isset( $options['sm_coloration_level'] ) ) {
			$average                                   = round( $switch_accent_count * 100 / $switch_dark_count );
			$default                                   = $average > 87.5 ? '100' : ( $average > 62.5 ? '75' : ( $average > 25 ? '50' : '0' ) );
			$options['sm_coloration_level']['default'] = $default;
		}

		$config['panels']['style_manager_panel']['sections']['sm_color_palettes_section']['options'] = $options;

		return $config;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	protected function add_color_usage_section( array $config ): array {

		$color_usage_fields = [
			'sm_text_color_switch_master',
			'sm_accent_color_switch_master',
			'sm_site_color_variation',
			'sm_coloration_level',
			'sm_colorize_elements_button',
			'sm_dark_mode',
			'sm_dark_mode_advanced',
		];

		$color_usage_section = [
			'title'      => esc_html__( 'Color Usage', '__plugin_txtd' ),
			'section_id' => 'sm_color_usage_section',
			'priority'   => 10,
			'options'    => [],
		];

		if ( ! isset( $config['panels']['style_manager_panel']['sections']['sm_color_palettes_section']['options'] ) ) {
			return $config;
		}

		$sm_colors_options = $config['panels']['style_manager_panel']['sections']['sm_color_palettes_section']['options'];

		foreach ( $color_usage_fields as $field_id ) {

			if ( ! isset( $sm_colors_options[ $field_id ] ) ) {
				continue;
			}

			if ( empty( $color_usage_section['options'] ) ) {
				$color_usage_section['options'] = [ $field_id => $sm_colors_options[ $field_id ] ];
			} else {
				$color_usage_section['options'] = array_merge( $color_usage_section['options'], [ $field_id => $sm_colors_options[ $field_id ] ] );
			}
		}

		$config['panels']['theme_options_panel']['sections']['sm_color_usage_section'] = $color_usage_section;

		return $config;
	}

	protected function add_color_palettes_to_novablocks_settings( array $settings ): array {
		$palette_output_value = \Pixelgrade\StyleManager\get_option( 'sm_advanced_palette_output' );
		$palettes             = [];

		if ( ! empty( $palette_output_value ) ) {
			$palettes = json_decode( $palette_output_value );
		}

		$settings['palettes'] = $palettes;

		return $settings;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	protected function add_style_manager_new_section_master_colors_config( array $config ): array {
		// If there is no style manager support, bail early.
		if ( ! $this->is_supported() ) {
			return $config;
		}

		if ( ! isset( $config['sections']['style_manager_section'] ) ) {
			$config['sections']['style_manager_section'] = [];
		}

		// The section might be already defined, thus we merge, not replace the entire section config.
		$config['sections']['style_manager_section']['options'] =
			[
				'sm_advanced_palette_source'                => [
					'type'         => 'text',
					'live'         => true,
					'default'      => '[
					{
						"uid": "color_group_1",
						"sources": [
							{ 
								"uid": "color_11", 
								"showPicker": true,
								"label": "Color",
								"value": "#ddaa61"
							}
						]
					},
					{
						"uid": "color_group_2",
						"sources": [
							{ 
								"uid": "color_21", 
								"showPicker": true,
								"label": "Color",
								"value": "#39497C"
							}
						]
					},
					{
						"uid": "color_group_3",
						"sources": [
							{ 
								"uid": "color_31", 
								"showPicker": true,
								"label": "Color",
								"value": "#B12C4A"
							}
						]
					}
					]',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => 'sm_advanced_palette_source',
					'label'        => esc_html__( 'Palette Source', '__plugin_txtd' ),
				],
				// This is just a setting to hold the currently selected color palette (its hashid).
				self::SM_COLOR_PALETTE_OPTION_KEY           => [
					'type'         => 'hidden_control',
					'live'         => true,
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => self::SM_COLOR_PALETTE_OPTION_KEY,
				],
				// This is just a setting to hold the currently selected color palette (its hashid).
				self::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY => [
					'type'         => 'hidden_control',
					'live'         => true,
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => self::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY,
				],
				'sm_advanced_palette_output'                => [
					'type'         => 'text',
					'live'         => true,
					'default'      => '[
					  {
					    "sourceIndex": 5,
					    "id": 1,
					    "lightColorsCount": 5,
					    "label": "Color",
					    "source": {
					      "0": "#DDAB5D"
					    },
					    "colors": [
					      { "value": "#FFFFFF" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#DDAB5D", "isSource": true },
					      { "value": "#DDAB5D" },
					      { "value": "#DDAB5D" },
					      { "value": "#212B49" },
					      { "value": "#212B49" },
					      { "value": "#141928" },
					      { "value": "#141928" }
					    ],
					    "textColors": [
					      { "value": "#34394B" },
					      { "value": "#34394B" }
					    ]
					  },
					  {
					    "sourceIndex": 5,
					    "id": 2,
					    "lightColorsCount": 5,
					    "label": "Color",
					    "source": {
					      "0": "#39497C"
					    },
					    "colors": [
					      { "value": "#FFFFFF" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#39497C", "isSource": true },
					      { "value": "#39497C" },
					      { "value": "#39497C" },
					      { "value": "#212B49" },
					      { "value": "#212B49" },
					      { "value": "#141928" },
					      { "value": "#141928" }
					    ],
					    "textColors": [
					      { "value": "#34394B" },
					      { "value": "#34394B" }
					    ]
					  },
					  {
					    "sourceIndex": 5,
					    "id": 3,
					    "lightColorsCount": 5,
					    "label": "Color",
					    "source": {
					      "0": "#B12C4A"
					    },
					    "colors": [
					      { "value": "#FFFFFF" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#EEEFF2" },
					      { "value": "#B12C4A", "isSource": true },
					      { "value": "#B12C4A" },
					      { "value": "#B12C4A" },
					      { "value": "#212B49" },
					      { "value": "#212B49" },
					      { "value": "#141928" },
					      { "value": "#141928" }
					    ],
					    "textColors": [
					      { "value": "#34394B" },
					      { "value": "#34394B" }
					    ]
					  },
					  {
					    "sourceIndex": 6,
					    "id": "_info",
					    "lightColorsCount": 5,
					    "label": "Info",
					    "source": [ "#2E72D2" ],
					    "colors": [
					      { "value": "#ffffff" },
					      { "value": "#f6f7fd" },
					      { "value": "#e1e5f8" },
					      { "value": "#b2c0ec" },
					      { "value": "#859ee2" },
					      { "value": "#527ed3" },
					      { "value": "#2E72D2", "isSource": true },
					      { "value": "#0758b0" },
					      { "value": "#0c4496" },
					      { "value": "#0e317b" },
					      { "value": "#0c1861" },
					      { "value": "#101010" }
				        ],
				        "textColors": [
				          { "value": "#30354c" },
				          { "value": "#202132" }
			            ]
		              },
					  {
					    "sourceIndex": 6,
					    "id": "_error",
					    "lightColorsCount": 5,
					    "label": "Error",
					    "source": [ "#D82C0D" ],
					    "colors": [
					      { "value": "#ffffff" },
					      { "value": "#fff5f2" },
					      { "value": "#ffdfd6" },
					      { "value": "#fbaf98" },
					      { "value": "#f18061" },
					      { "value": "#de4f2e" },
					      { "value": "#D82C0D", "isSource": true },
					      { "value": "#b50f0f" },
					      { "value": "#901313" },
					      { "value": "#6c1212" },
					      { "value": "#4d0000" },
					      { "value": "#101010" }
				        ],
				        "textColors": [
				          { "value": "#4c2e2e" },
				          { "value": "#311c1c" }
			            ]
		              },
					  {
					    "sourceIndex": 3,
					    "id": "_warning",
					    "lightColorsCount": 5,
					    "label": "Warning",
					    "source": [ "#FFCC00" ],
					    "colors": [
					      { "value": "#ffffff" },
					      { "value": "#fff7df" },
					      { "value": "#fce690" },
					      { "value": "#FFCC00", "isSource": true },
					      { "value": "#c39b10" },
					      { "value": "#9f7a00" },
					      { "value": "#896701" },
					      { "value": "#735507" },
					      { "value": "#60430a" },
					      { "value": "#4e2f0d" },
					      { "value": "#40140b" },
					      { "value": "#101010" }
				        ],
				        "textColors": [
				          { "value": "#473222" },
				          { "value": "#311d1b" }
			            ]
		              },
					  {
					    "sourceIndex": 7,
					    "id": "_success",
					    "lightColorsCount": 5,
					    "label": "Success",
					    "source": [ "#00703c" ],
					    "colors": [
					      { "value": "#ffffff" },
					      { "value": "#f4f8f5" },
					      { "value": "#dce9e0" },
					      { "value": "#a9c9b2" },
					      { "value": "#7aab89" },
					      { "value": "#4c8c63" },
					      { "value": "#257b4a" },
					      { "value": "#00703c", "isSource": true },
					      { "value": "#0b5425" },
					      { "value": "#0d3f12" },
					      { "value": "#092809" },
					      { "value": "#101010" }
				        ],
				        "textColors": [
				          { "value": "#223c23" },
				          { "value": "#142614" }
			            ]
		              }
					]',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => 'sm_advanced_palette_output',
					'label'        => esc_html__( 'Palette Output', '__plugin_txtd' ),
					'css'          => [
						[
							'selector'        => ':root',
							'property'        => 'dummy-property',
							'callback_filter' => 'sm_advanced_palette_output_cb',
						],
					],
				],
				'sm_site_color_variation'                   => [
					'type'         => 'range',
					'desc'         => wp_kses( __( 'Shift the <strong>start position</strong> of the color palette. Use 1 for white, 2-3 for subtle shades, 4-7 for colorful, above 8 for darker shades.', '__plugin_txtd' ), [ 'strong' => [] ] ),
					'live'         => true,
					'setting_type' => 'option',
					'setting_id'   => 'sm_site_color_variation',
					'label'        => esc_html__( 'Palette Basis Offset', '__plugin_txtd' ),
					'default'      => 1,
					'input_attrs'  => [
						'min'  => 1,
						'max'  => 12,
						'step' => 1,
					],
					'css'          => [
						[
							'selector'        => ':root',
							'property'        => 'dummy-property',
							'callback_filter' => 'sm_variation_range_cb',
						],
					],
				],
				'sm_text_color_switch_master'               => [
					'type'             => 'sm_toggle',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type'     => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'       => 'sm_text_color_switch_master',
					'label'            => esc_html__( 'Text Master', '__plugin_txtd' ),
					'live'             => true,
					'default'          => false,
					'connected_fields' => [],
					'css'              => [],
				],
				'sm_accent_color_switch_master'             => [
					'type'             => 'sm_toggle',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type'     => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'       => 'sm_accent_color_switch_master',
					'label'            => esc_html__( 'Accent Master', '__plugin_txtd' ),
					'live'             => true,
					'default'          => true,
					'connected_fields' => [],
					'css'              => [],
				],
				'sm_coloration_level'                       => [
					'type'         => 'sm_radio',
					'desc'         => wp_kses( __( 'Adjust <strong>how much color</strong> you want to add to your site. For more control over elements, you can edit them individually.', '__plugin_txtd' ), [ 'strong' => [] ] ),
					'setting_type' => 'option',
					'setting_id'   => 'sm_coloration_level',
					'label'        => esc_html__( 'Coloration Level', '__plugin_txtd' ),
					'default'      => 0,
					'live'         => true,
					'choices'      => [
						'0'   => esc_html__( 'Low', '__plugin_txtd' ),
						'50'  => esc_html__( 'Medium', '__plugin_txtd' ),
						'75'  => esc_html__( 'High', '__plugin_txtd' ),
						'100' => esc_html__( 'Striking', '__plugin_txtd' ),
					],
				],
			] + $config['sections']['style_manager_section']['options'];

		return $config;
	}

	/**
	 * Reorganize the Customizer controls.
	 *
	 * @since 2.0.0
	 *
	 * @param array $sm_panel_config
	 * @param array $sm_section_config
	 *
	 * @return array
	 */
	protected function reorganize_customizer_controls( array $sm_panel_config, array $sm_section_config ): array {
		// We need to split the fields in the Style Manager section into two: color palettes and fonts.
		$color_palettes_fields = [
			'sm_advanced_palette_source',
			self::SM_COLOR_PALETTE_OPTION_KEY,
			self::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY,
			'sm_advanced_palette_output',

			'sm_text_color_switch_master',
			'sm_accent_color_switch_master',
			'sm_site_color_variation',
			'sm_coloration_level',
			'sm_colorize_elements_button',
			'sm_dark_mode',
			'sm_dark_mode_advanced',
		];

		$color_palettes_section_config = [
			'title'      => esc_html__( 'Colors', '__plugin_txtd' ),
			'section_id' => 'sm_color_palettes_section',
			'priority'   => 10,
			'options'    => [],
		];

		foreach ( $color_palettes_fields as $field_id ) {
			if ( ! isset( $sm_section_config['options'][ $field_id ] ) ) {
				continue;
			}

			if ( empty( $color_palettes_section_config['options'] ) ) {
				$color_palettes_section_config['options'] = [ $field_id => $sm_section_config['options'][ $field_id ] ];
			} else {
				$color_palettes_section_config['options'] = array_merge( $color_palettes_section_config['options'], [ $field_id => $sm_section_config['options'][ $field_id ] ] );
			}
		}

		$sm_panel_config['sections']['sm_color_palettes_section'] = $color_palettes_section_config;

		return $sm_panel_config;
	}

	/**
	 *
	 * @since 2.0.0
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	protected function maybe_enhance_dark_mode_control( array $config ): array {

		if ( ! current_theme_supports( 'style_manager_advanced_dark_mode' )
		     || ! isset( $config['sections']['style_manager_section'] ) ) {

			return $config;
		}

		unset( $config['sections']['style_manager_section']['options']['sm_dark_mode'] );

		$config['sections']['style_manager_section'] = ArrayHelpers::array_merge_recursive_distinct( $config['sections']['style_manager_section'], [
			'options' => [
				'sm_dark_mode_advanced' => [
					'type'         => 'sm_radio',
					'setting_id'   => 'sm_dark_mode_advanced',
					'setting_type' => 'option',
					'label'        => esc_html__( 'Appearance', '__plugin_txtd' ),
					'live'         => true,
					'default'      => 'off',
					'desc'         => wp_kses( __( "<strong>Auto</strong> activates dark mode automatically, according to the visitor's system-wide setting", '__plugin_txtd' ), [ 'strong' => [] ] ),
					'choices'      => [
						'off'  => esc_html__( 'Light', '__plugin_txtd' ),
						'on'   => esc_html__( 'Dark', '__plugin_txtd' ),
						'auto' => esc_html__( 'Auto', '__plugin_txtd' ),
					],
				],
			],
		] );

		return $config;
	}

	/**
	 * Get the current font palette ID or false if none is selected.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	protected function get_current_palette() {
		return get_option( self::SM_COLOR_PALETTE_OPTION_KEY, false );
	}

	/**
	 * Get the current font palette variation ID or false if none is selected.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	protected function get_current_palette_variation() {
		return get_option( self::SM_COLOR_PALETTE_VARIATION_OPTION_KEY, false );
	}

	/**
	 * Determine if a custom font palette is in use.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_using_custom_palette(): bool {
		return (bool) get_option( self::SM_IS_CUSTOM_COLOR_PALETTE_OPTION_KEY, false );
	}

	/**
	 * Get the default (hard-coded) color palettes configuration.
	 *
	 * This is only a fallback config in case we can't communicate with the cloud, the first time.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_default_config(): array {
		return apply_filters( 'style_manager/default_color_palettes', [] );
	}

	/**
	 * Add color palettes usage data to the site data sent to the cloud.
	 *
	 * @since 2.0.0
	 *
	 * @param array $site_data
	 *
	 * @return array
	 */
	protected function add_palettes_to_site_data( array $site_data ): array {

		if ( empty( $site_data['color_palettes_v2'] ) ) {
			$site_data['color_palettes_v2'] = [];
		}

		// If others have added data before us, we will merge with it.
		$site_data['color_palettes_v2'] = array_merge( $site_data['color_palettes_v2'], [
			'current'   => $this->get_current_palette(),
			'variation' => $this->get_current_palette_variation(),
			'custom'    => $this->is_using_custom_palette(),
		] );

		return $site_data;
	}

	/**
	 * Add data to be available to JS.
	 *
	 * @since 2.0.0
	 *
	 * @param array $localized
	 *
	 * @return array
	 */
	protected function add_to_localized_data( array $localized ): array {
		if ( empty( $localized['colorPalettes'] ) ) {
			$localized['colorPalettes'] = [];
		}

		$localized['colorPalettes']['palettes'] = $this->get_palettes();

		if ( empty( $localized['l10n'] ) ) {
			$localized['l10n'] = [];
		}
		$localized['l10n']['colorPalettes'] = [
			'colorizeElementsPanelLabel'         => esc_html__( 'Colorize elements one by one', '__plugin_txtd' ),
			'builderColorUsagePanelLabel'        => esc_html__( 'Customize colors usage', '__plugin_txtd' ),
			'builderBrandColorsLabel'            => esc_html__( 'Brand Colors', '__plugin_txtd' ),
			'builderColorPresetsTitle'           => esc_html__( 'Explore colors', '__plugin_txtd' ),
			'builderColorPresetsDesc'            => esc_html__( 'Curated color presets to help you lay the foundations of the color system and make it easy to get started.', '__plugin_txtd' ),
			'builderImageExtractTitle'           => esc_html__( 'Extract from Image', '__plugin_txtd' ),
			'dropzoneInterpolatedColorLabel'     => esc_html__( 'Interpolated Color', '__plugin_txtd' ),
			'dropzoneDesc'                       => esc_html__( 'Extract colors from an image and generate a color palette for your design system.', '__plugin_txtd' ),
			'dropzoneTitle'                      => esc_html__( 'Drag and drop your image', '__plugin_txtd' ),
			'dropzoneSubtitle'                   => sprintf(
				wp_kses(
				/* translators 1: open span, 2: close span */
					__( 'or %1$s select a file %2$s from your computer', '__plugin_txtd' ),
					wp_kses_allowed_html()
				),
				'<span class="dropzone-info-anchor">',
				'</span>'
			),
			'previewTabLiveSiteLabel'            => esc_html__( 'Live site', '__plugin_txtd' ),
			'previewTabColorSystemLabel'         => esc_html__( 'Color system', '__plugin_txtd' ),
			'palettePreviewTitle'                => esc_html__( 'The color system', '__plugin_txtd' ),
			'palettePreviewDesc'                 => wp_kses( __( 'The color system presented below is designed based on your brand colors. Hover over a color grade to see a preview of how you will be able to use colors with your content blocks.', '__plugin_txtd' ), wp_kses_allowed_html() ),
			'palettePreviewListDesc'             => wp_kses( __( 'Each column from the color palette below represent a state where a component could be. The first row is the main surface or background color, while the other two rows are for the content.', '__plugin_txtd' ), wp_kses_allowed_html() ),
			'palettePreviewSwatchSurfaceText'    => esc_html__( 'Surface', '__plugin_txtd' ),
			'palettePreviewSwatchAccentText'     => esc_html__( 'Accent', '__plugin_txtd' ),
			'palettePreviewSwatchForegroundText' => esc_html__( 'Text', '__plugin_txtd' ),
			'sourceColorsDefaultLabel'           => esc_html__( 'Color', '__plugin_txtd' ),
			'sourceColorsInterpolatedLabel'      => esc_html__( 'Interpolated Color', '__plugin_txtd' ),
		];

		return $localized;
	}

	/**
	 * Add Color Scheme attribute to <html> tag.
	 *
	 * @since 2.0.0
	 *
	 * @param string $output  A space-separated list of language attributes.
	 * @param string $doctype The type of html document (xhtml|html).
	 *
	 * @return string $output A space-separated list of language attributes.
	 */
	protected function add_dark_mode_data_attribute( string $output, string $doctype ): string {

		if ( is_admin() || 'html' !== $doctype ) {
			return $output;
		}

		$output .= ' data-dark-mode-advanced=' . \Pixelgrade\StyleManager\get_option( 'sm_dark_mode_advanced', 'off' );

		return $output;
	}
}

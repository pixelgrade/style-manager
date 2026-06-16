<?php
/**
 * This is the class that handles the logic for Font Palettes.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Customize;

use Pixelgrade\StyleManager\Utils\Fonts as FontsHelper;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Utils\ArrayHelpers;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Provides the font palettes logic.
 *
 * @since 2.0.0
 */
class FontPalettes extends AbstractHookProvider {

	const SM_FONT_PALETTE_OPTION_KEY = 'sm_font_palette';
	const SM_FONT_PALETTE_VARIATION_OPTION_KEY = 'sm_font_palette_variation';
	const SM_IS_CUSTOM_FONT_PALETTE_OPTION_KEY = 'sm_is_custom_font_palette';

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

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
	 * @param Options         $options Options.
	 * @param DesignAssets    $design_assets Design assets.
	 * @param LoggerInterface $logger  Logger.
	 */
	public function __construct(
		Options $options,
		DesignAssets $design_assets,
		LoggerInterface $logger
	) {

		$this->options = $options;
		$this->design_assets = $design_assets;
		$this->logger  = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		/*
		 * Handle the font palettes preprocessing.
		 */
		add_filter( 'style_manager/get_font_palettes', [ $this, 'preprocess_config' ], 5, 1 );

		/*
		 * Handle the Customizer Style Manager section config.
		 */
		$this->add_filter( 'style_manager/filter_fields', 'add_style_manager_section_master_fonts_config', 12, 1 );
		$this->add_filter( 'style_manager/sm_panel_config', 'reorganize_customizer_controls', 20, 2 );

		$this->add_filter( 'style_manager/final_config', 'add_fine_tune_palette_section', 120, 1 );

		/*
		 * Handle the logic on settings update/save.
		 */
		$this->add_action( 'customize_save_after', 'update_custom_palette_in_use', 10, 1 );

		/**
		 * Add font palettes usage to site data.
		 */
		$this->add_filter( 'style_manager/get_site_data', 'add_palettes_to_site_data', 10, 1 );

		// Add data to be passed to JS.
		$this->add_filter( 'style_manager/localized_js_settings', 'add_to_localized_data', 10, 1 );
	}

	/**
	 * Determine if Font Palettes are supported.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_supported(): bool {
		return apply_filters( 'style_manager/font_palettes_are_supported', current_theme_supports( 'style_manager_font_palettes' ) );
	}

	protected function add_fine_tune_palette_section( array $config ): array {

		// Advanced controls (the Fine-tune palette) are a Pixelgrade Plus premium
		// capability. Ask Plus through the shared, absence-safe entitlement bridge:
		// when Plus is absent, inactive, or unlicensed nothing answers the filter
		// and the locked default (false) stands, so the section stays hidden. Plus
		// owns the gate, Style Manager owns the controls — we never inspect
		// license/account internals here. See the Plus entitlement bridge contract.
		if ( ! apply_filters( 'pixelgrade/has_entitlement', false, 'advanced_style_manager_controls' ) ) {
			return $config;
		}

		$fine_tune_palette_fields = [
			'sm_fine_tune_intro',
			'sm_font_primary_intro',
			'sm_font_primary',
			'sm_font_primary_elevation',
			'sm_font_primary_pitch',
			'sm_separator_0_1',
			'sm_font_secondary_intro',
			'sm_font_secondary',
			'sm_font_secondary_elevation',
			'sm_font_secondary_pitch',
			'sm_separator_0_2',
			'sm_font_body_intro',
			'sm_font_body',
			'sm_font_body_elevation',
			'sm_font_body_pitch',
			'sm_separator_0_3',
			'sm_font_accent_intro',
			'sm_font_accent',
			'sm_separator_0_4',
			'sm_fonts_connected_fields_preset',
		];

		$fine_tune_palette_section = [
			'title'      => esc_html__( 'Fine-tune font palette', '__plugin_txtd' ),
			'section_id' => 'sm_fine_tune_font_palette_section',
			'priority'   => 20,
			'options'    => [],
		];

		if ( ! isset( $config['panels']['style_manager_panel']['sections']['sm_font_palettes_section']['options'] ) ) {
			return $config;
		}

		$sm_colors_options = $config['panels']['style_manager_panel']['sections']['sm_font_palettes_section']['options'];

		foreach ( $fine_tune_palette_fields as $field_id ) {

			if ( ! isset( $sm_colors_options[ $field_id ] ) ) {
				continue;
			}

			if ( empty( $fine_tune_palette_section['options'] ) ) {
				$fine_tune_palette_section['options'] = [ $field_id => $sm_colors_options[ $field_id ] ];
			} else {
				$fine_tune_palette_section['options'] = array_merge( $fine_tune_palette_section['options'], [ $field_id => $sm_colors_options[ $field_id ] ] );
			}
		}

		$config['panels']['theme_options_panel']['sections']['sm_fine_tune_font_palette_section'] = $fine_tune_palette_section;

		return $config;
	}

	/**
	 * Preprocess the font palettes configuration.
	 *
	 * Things like transforming font_size_line_height_points to a polynomial function for easy use client side,
	 * or processing the styles intervals and making sure that we get to a state where there are no overlaps and the order is right.
	 *
	 * @since 2.0.0
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	public function preprocess_config( array $config ): array {
		if ( empty( $config ) ) {
			return $config;
		}

		foreach ( $config as $palette_id => $palette_config ) {
			$config[ $palette_id ] = $this->preprocess_palette_config( $palette_config, (string) $palette_id );
		}

		return $config;
	}

	/**
	 * Preprocess a font palette config before using it.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $palette_config
	 * @param string $palette_id
	 *
	 * @return array
	 */
	protected function preprocess_palette_config( array $palette_config, string $palette_id = '' ): array {
		if ( empty( $palette_config ) ) {
			return $palette_config;
		}

		$palette_config = $this->normalize_palette_personality( $palette_config, $palette_id );

		// Ensure preview fields have defaults for live preview rendering.
		if ( ! isset( $palette_config['preview'] ) ) {
			$palette_config['preview'] = [];
		}
		if ( empty( $palette_config['preview']['title'] ) && ! empty( $palette_config['label'] ) ) {
			$palette_config['preview']['title'] = $palette_config['label'];
		}
		if ( empty( $palette_config['preview']['description'] ) ) {
			$palette_config['preview']['description'] = '';
		}

		global $wp_customize;
		// We only need to do the fonts logic preprocess when we are in the Customizer.
		if ( ! empty( $wp_customize ) && $wp_customize instanceof \WP_Customize_Manager && ! empty( $palette_config['fonts_logic'] ) ) {
			$connected_fields_preset = '';
			if ( ! empty( $palette_config['fonts_logic']['connected_fields_preset'] ) ) {
				$connected_fields_preset = (string) $palette_config['fonts_logic']['connected_fields_preset'];
				unset( $palette_config['fonts_logic']['connected_fields_preset'] );
			}

			$palette_config['fonts_logic'] = $this->preprocess_fonts_logic_config( $palette_config['fonts_logic'] );

			if ( '' !== $connected_fields_preset ) {
				$palette_config['fonts_logic']['connected_fields_preset'] = $connected_fields_preset;
			}
		}

		return $palette_config;
	}

	/**
	 * Normalize a palette personality vector for Voice Tuner scoring.
	 *
	 * @since 2.2.10
	 *
	 * @param array $palette_config
	 *
	 * @return array
	 */
	protected function normalize_palette_personality( array $palette_config, string $palette_id = '' ): array {
		$known_personality = $this->get_known_palette_personality( $palette_id, $palette_config );

		if ( ! isset( $palette_config['personality'] ) || ! is_array( $palette_config['personality'] ) ) {
			$palette_config['personality'] = [];
		}

		$palette_config['personality'] = wp_parse_args( $palette_config['personality'], wp_parse_args( $known_personality, [
			'formality' => 0.5,
			'energy'    => 0.5,
			'warmth'    => 0.5,
			'tradition' => 0.5,
		] ) );

		return $palette_config;
	}

	/**
	 * Backfill personality vectors for shipped palettes when older design assets
	 * or local overrides omit Voice Tuner metadata.
	 *
	 * @since 2.2.11
	 *
	 * @param string $palette_id
	 * @param array  $palette_config
	 *
	 * @return array
	 */
	protected function get_known_palette_personality( string $palette_id, array $palette_config ): array {
		$known_personalities = $this->get_known_palette_personality_map();
		$lookup_keys         = array_filter( array_unique( [
			sanitize_title( $palette_id ),
			$this->get_palette_personality_lookup_key( $palette_config['label'] ?? '' ),
			$this->get_palette_personality_lookup_key( $palette_config['preview']['title'] ?? '' ),
		] ) );

		foreach ( $lookup_keys as $lookup_key ) {
			if ( isset( $known_personalities[ $lookup_key ] ) ) {
				return $known_personalities[ $lookup_key ];
			}
		}

		return [];
	}

	/**
	 * Build a stable lookup key for known palette personalities.
	 *
	 * @since 2.2.11
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	protected function get_palette_personality_lookup_key( string $value ): string {
		return sanitize_title( wp_strip_all_tags( $value ) );
	}

	/**
	 * Known personality vectors for currently shipped palettes.
	 *
	 * This lets Voice Tuner work across current installs even when a cached cloud
	 * palette or a local override does not yet carry explicit personality data.
	 *
	 * @since 2.2.11
	 *
	 * @return array<string, array<string, float>>
	 */
	protected function get_known_palette_personality_map(): array {
		return [
			'gema'      => [ 'formality' => 0.65, 'energy' => 0.3, 'warmth' => 0.4, 'tradition' => 0.5 ],
			'julia'     => [ 'formality' => 0.55, 'energy' => 0.4, 'warmth' => 0.65, 'tradition' => 0.6 ],
			'patch'     => [ 'formality' => 0.25, 'energy' => 0.7, 'warmth' => 0.8, 'tradition' => 0.2 ],
			'hive'      => [ 'formality' => 0.7, 'energy' => 0.5, 'warmth' => 0.45, 'tradition' => 0.7 ],
			'pile'      => [ 'formality' => 0.7, 'energy' => 0.45, 'warmth' => 0.35, 'tradition' => 0.6 ],
			'atlas'     => [ 'formality' => 0.45, 'energy' => 0.75, 'warmth' => 0.55, 'tradition' => 0.2 ],
			'capitol'   => [ 'formality' => 0.55, 'energy' => 0.8, 'warmth' => 0.35, 'tradition' => 0.2 ],
			'felt'      => [ 'formality' => 0.55, 'energy' => 0.4, 'warmth' => 0.7, 'tradition' => 0.65 ],
			'system'    => [ 'formality' => 0.45, 'energy' => 0.4, 'warmth' => 0.55, 'tradition' => 0.35 ],
			'archie'    => [ 'formality' => 0.55, 'energy' => 0.35, 'warmth' => 0.75, 'tradition' => 0.65 ],
			'swedish'   => [ 'formality' => 0.75, 'energy' => 0.45, 'warmth' => 0.45, 'tradition' => 0.85 ],
			'lacrima'   => [ 'formality' => 0.6, 'energy' => 0.25, 'warmth' => 0.8, 'tradition' => 0.75 ],
			'blair'     => [ 'formality' => 0.6, 'energy' => 0.65, 'warmth' => 0.55, 'tradition' => 0.4 ],
			'patrick'   => [ 'formality' => 0.2, 'energy' => 0.8, 'warmth' => 0.45, 'tradition' => 0.15 ],
			'alpina'    => [ 'formality' => 0.55, 'energy' => 0.6, 'warmth' => 0.35, 'tradition' => 0.45 ],
			'edward'    => [ 'formality' => 0.85, 'energy' => 0.3, 'warmth' => 0.55, 'tradition' => 0.9 ],
			'self'      => [ 'formality' => 0.45, 'energy' => 0.75, 'warmth' => 0.6, 'tradition' => 0.45 ],
			'voltage'   => [ 'formality' => 0.35, 'energy' => 0.85, 'warmth' => 0.3, 'tradition' => 0.15 ],
			'smith'     => [ 'formality' => 0.45, 'energy' => 0.7, 'warmth' => 0.35, 'tradition' => 0.25 ],
			'lively'    => [ 'formality' => 0.55, 'energy' => 0.75, 'warmth' => 0.55, 'tradition' => 0.45 ],
			'pandora'   => [ 'formality' => 0.55, 'energy' => 0.45, 'warmth' => 0.65, 'tradition' => 0.6 ],
			'local'     => [ 'formality' => 0.65, 'energy' => 0.25, 'warmth' => 0.2, 'tradition' => 0.2 ],
			'exquisite' => [ 'formality' => 0.7, 'energy' => 0.35, 'warmth' => 0.75, 'tradition' => 0.7 ],
			'wireframe' => [ 'formality' => 0.35, 'energy' => 0.15, 'warmth' => 0.15, 'tradition' => 0.15 ],
		];
	}

	/**
	 * Extract the font styling for the preview card at a given font size.
	 *
	 * This evaluates font_styles_intervals to determine the correct
	 * weight, letter-spacing, and text-transform for a specific size.
	 *
	 * @since 2.2.11
	 *
	 * @param array $font_logic A single font entry from fonts_logic (e.g., sm_font_primary).
	 * @param int   $size       The font size in px to evaluate intervals for.
	 *
	 * @return array{font_family: string, font_variant: string, font_weight: string|int, letter_spacing: string, text_transform: string}
	 */
	public static function get_preview_font_style( array $font_logic, int $size ): array {
		$style = [
			'font_family'    => $font_logic['font_family'] ?? '',
			'font_variant'   => 'regular',
			'font_weight'    => 400,
			'letter_spacing' => '0',
			'text_transform' => 'none',
		];

		if ( empty( $font_logic['font_styles_intervals'] ) || ! is_array( $font_logic['font_styles_intervals'] ) ) {
			// Fallback: use the first available font_weight from the palette.
			if ( ! empty( $font_logic['font_weights'] ) ) {
				$first_weight = $font_logic['font_weights'][0];
				$style['font_variant'] = (string) $first_weight;
				if ( is_numeric( $first_weight ) ) {
					$style['font_weight'] = (int) $first_weight;
				}
			}

			return $style;
		}

		// Walk through intervals to find the one that applies at this size.
		// Intervals are sorted by 'start' (ascending) and non-overlapping after preprocessing.
		foreach ( $font_logic['font_styles_intervals'] as $interval ) {
			$start = $interval['start'] ?? 0;
			$end   = $interval['end'] ?? PHP_INT_MAX;

			if ( $size >= $start && $size < $end ) {
				$variant = $interval['font_variant'] ?? $interval['font_weight'] ?? null;
				if ( $variant !== null ) {
					$style['font_variant'] = (string) $variant;
				}

				// Cloud palettes use 'font_variant' instead of 'font_weight'.
				$weight = $interval['font_weight'] ?? $interval['font_variant'] ?? null;
				if ( $weight !== null ) {
					// Normalize 'regular' to 400.
					$style['font_weight'] = ( $weight === 'regular' ) ? 400 : $weight;
				}
				if ( isset( $interval['letter_spacing'] ) ) {
					$ls = $interval['letter_spacing'];
					// Cloud palettes may provide letter_spacing as {"value": 0.04, "unit": "em"}.
					if ( is_array( $ls ) && isset( $ls['value'] ) ) {
						$style['letter_spacing'] = $ls['value'] . ( $ls['unit'] ?? 'em' );
					} elseif ( is_numeric( $ls ) ) {
						$style['letter_spacing'] = $ls . 'em';
					} elseif ( is_string( $ls ) ) {
						$style['letter_spacing'] = $ls;
					} else {
						$style['letter_spacing'] = '0';
					}
				}
				if ( isset( $interval['text_transform'] ) ) {
					$style['text_transform'] = $interval['text_transform'];
				}
				break;
			}
		}

		// If no interval matched and we still have the default weight,
		// use the first available font_weight from the palette.
		if ( $style['font_weight'] === 400 && ! empty( $font_logic['font_weights'] ) ) {
			$first_weight = $font_logic['font_weights'][0];
			$style['font_variant'] = (string) $first_weight;
			if ( is_numeric( $first_weight ) ) {
				$style['font_weight'] = (int) $first_weight;
			}
		}

		return $style;
	}

	/**
	 * Before using a font logic config, preprocess it to allow for standardization, fill up of missing info, etc.
	 *
	 * @since 2.0.0
	 *
	 * @param array $fonts_logic_config
	 *
	 * @return array
	 */
	private function preprocess_fonts_logic_config( array $fonts_logic_config ): array {
		if ( empty( $fonts_logic_config ) ) {
			return $fonts_logic_config;
		}

		foreach ( $fonts_logic_config as $font_setting_id => $font_logic ) {
			if ( ! is_array( $font_logic ) ) {
				unset( $fonts_logic_config[ $font_setting_id ] );
				continue;
			}

			if ( ! empty( $font_logic['reset'] ) ) {
				$fonts_logic_config[ $font_setting_id ]['reset'] = true;
				continue;
			}

			if ( empty( $font_logic['font_family'] ) ) {
				// If we don't have a font family we can't do much with this config - remove it.
				unset( $fonts_logic_config[ $font_setting_id ] );
				continue;
			}

			// We don't need font types as we will determine them dynamically.
			unset( $fonts_logic_config[ $font_setting_id ]['type'] );
			unset( $fonts_logic_config[ $font_setting_id ]['font_type'] );

			// If we have been given a `font_size_multiplier` value, make sure it is a float.
			if ( isset( $fonts_logic_config[ $font_setting_id ]['font_size_multiplier'] ) ) {
				$fonts_logic_config[ $font_setting_id ]['font_size_multiplier'] = (float) $fonts_logic_config[ $font_setting_id ]['font_size_multiplier'];
				if ( $fonts_logic_config[ $font_setting_id ]['font_size_multiplier'] <= 0 ) {
					// We reject negative or 0 values.
					$fonts_logic_config[ $font_setting_id ]['font_size_multiplier'] = 1.0;
				}
			} else {
				// By default, we use 1.
				$fonts_logic_config[ $font_setting_id ]['font_size_multiplier'] = 1.0;
			}

			// Process the font_styles_intervals and make sure that they are in the right order and not overlapping.
			if ( ! empty( $font_logic['font_styles_intervals'] ) && is_array( $font_logic['font_styles_intervals'] ) ) {
				// Initialize the list with the first one found.
				$font_styles_intervals = [ array_shift( $font_logic['font_styles_intervals'] ) ];
				// Make sure that this interval has a start
				if ( ! isset( $font_styles_intervals[0]['start'] ) ) {
					$font_styles_intervals[0]['start'] = 0;
				}

				foreach ( $font_logic['font_styles_intervals'] as $font_styles_interval ) {
					// Make sure that the interval has a start
					if ( ! isset( $font_styles_interval['start'] ) ) {
						$font_styles_interval['start'] = 0;
					}
					// Go through the current font_styles and determine the place where this interval should fit in.
					for ( $i = 0; $i < count( $font_styles_intervals ); $i ++ ) {
						// Determine if the new interval overlaps with this existing one.
						if ( ! isset( $font_styles_intervals[ $i ]['end'] ) ) {
							// Since this interval is without end, there is nothing after it.
							// We need to adjust the old interval end.
							if ( $font_styles_intervals[ $i ]['start'] < $font_styles_interval['start'] ) {
								$font_styles_intervals[ $i ]['end'] = $font_styles_interval['start'];
							} else {
								if ( ! isset( $font_styles_interval['end'] ) ) {
									// We need to delete the old interval altogether.
									unset( $font_styles_intervals[ $i ] );
									$i --;
									continue;
								} else {
									// Adjust the old interval and insert in front of it.
									$font_styles_intervals[ $i ]['end'] = $font_styles_interval['end'];
									$font_styles_intervals              = array_slice( $font_styles_intervals, 0, $i ) + [ $font_styles_interval ];
									break;
								}
							}
						} else {
							if ( $font_styles_intervals[ $i ]['end'] > $font_styles_interval['start'] ) {
								// We need to shrink this interval and make room for the new interval.
								$font_styles_intervals[ $i ]['end'] = $font_styles_interval['start'];
							} else {
								// There is not overlap. Move to the next one.
								continue;
							}

							if ( ! isset( $font_styles_interval['end'] ) ) {
								// Everything after the existing interval is gone and the new one takes precedence.
								array_splice( $font_styles_intervals, $i + 1, count( $font_styles_intervals ), [ $font_styles_interval ] );
								break;
							} else {
								// Now go forward and see where the end of the new interval fits in.
								for ( $j = $i + 1; $j < count( $font_styles_intervals ); $j ++ ) {
									if ( $font_styles_intervals[ $j ]['start'] < $font_styles_interval['end'] ) {
										// We have an overlapping after-interval.
										if ( ! isset( $font_styles_intervals[ $j ]['end'] ) ) {
											// Since this interval is without end, there is nothing after it.
											$font_styles_intervals[ $j ]['start'] = $font_styles_interval['end'];
											break;
										} elseif ( $font_styles_intervals[ $j ]['end'] <= $font_styles_interval['end'] ) {
											// We need to delete this interval since it is completely overwritten by the new one.
											unset( $font_styles_intervals[ $j ] );
											$j --;
											continue;
										} else {
											// The new interval partially overlaps with the old one. Adjust.
											$font_styles_intervals[ $j ]['end'] = $font_styles_interval['end'];
											break;
										}
									} else {
										// We can insert the new interval since this interval is after it
										break;
									}
								}

								// Insert the new interval.
								array_splice( $font_styles_intervals, $j, 0, [ $font_styles_interval ] );
								break;
							}
						}
					}

					// If we have reached the end of the list, we will insert it at the end.
					if ( $i === count( $font_styles_intervals ) ) {
						$font_styles_intervals[] = $font_styles_interval;
					}
				}

				// We need to do a last pass and ensure no breaks in the intervals. We need them to be continuous.
				// We will extend intervals to their next (right-hand) neighbour to achieve continuity.
				if ( count( $font_styles_intervals ) > 1 ) {
					// The first interval should start at zero, just in case.
					$font_styles_intervals[0]['start'] = 0;
					for ( $i = 1; $i < count( $font_styles_intervals ); $i ++ ) {
						// Extend the previous interval, just in case.
						$font_styles_intervals[ $i - 1 ]['end'] = $font_styles_intervals[ $i ]['start'];
					}
				}

				// The last interval should not have an end.
				unset( $font_styles_intervals[ count( $font_styles_intervals ) - 1 ]['end'] );

				// Finally, go through each font style and standardize it.
				foreach ( $font_styles_intervals as $key => $value ) {

					// Since there is no font value "font_weight", but "font_variant", treat it as such.
					// Font weight is only a CSS value.
					// Font variant "splits" into font-weight and (maybe) "font-style".
					if ( isset( $value['font_weight'] ) && ! isset( $value['font_variant'] ) ) {
						$font_styles_intervals[ $key ]['font_variant'] = $value['font_weight'];
						unset( $font_styles_intervals[ $key ]['font_weight'] );
					}

					// Standardize the font variant.
					if ( isset( $font_styles_intervals[ $key ]['font_variant'] ) ) {
						$font_styles_intervals[ $key ]['font_variant'] = FontsHelper::standardizeFontVariant( $font_styles_intervals[ $key ]['font_variant'] );
					}

					if ( isset( $value['letter_spacing'] ) ) {
						// We have some special values for letter-spacing that need to taken care of.
						if ( 'normal' === $value['letter_spacing'] ) {
							$value['letter_spacing'] = 0;
						}
						$font_styles_intervals[ $key ]['letter_spacing'] = FontsHelper::standardizeNumericalValue( $value['letter_spacing'] );
					}

					// If we have been given a `font_size_multiplier` value, make sure it is a positive float.
					if ( isset( $font_styles_intervals[ $key ]['font_size_multiplier'] ) ) {
						$font_styles_intervals[ $key ]['font_size_multiplier'] = (float) $font_styles_intervals[ $key ]['font_size_multiplier'];
						if ( $font_styles_intervals[ $key ]['font_size_multiplier'] <= 0 ) {
							// We reject negative or 0 values.
							$font_styles_intervals[ $key ]['font_size_multiplier'] = 1.0;
						}
					} else {
						// By default, we use 1, meaning no effect.
						$font_styles_intervals[ $key ]['font_size_multiplier'] = 1.0;
					}

					// We really don't want font_size or line_height in here,
					// since line_height is determined through the curve that matches it to a font_size;
					// and also font_size is the main driving force behind the font palettes logic; so it is absurd to have it here.
					unset( $font_styles_intervals[ $key ]['font_size'] );
					unset( $font_styles_intervals[ $key ]['line_height'] );
				}

				$fonts_logic_config[ $font_setting_id ]['font_styles_intervals'] = $font_styles_intervals;
			}
		}

		return $fonts_logic_config;
	}

	/**
	 * Get the font palettes configuration.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $skip_cache Optional. Whether to use the cached config or fetch a new one.
	 *
	 * @return array
	 */
	public function get_palettes( bool $skip_cache = false ): array {
		$config = $this->design_assets->get_entry( 'font_palettes', $skip_cache );
		if ( is_null( $config ) ) {
			$config = $this->get_default_config();
		}

		$preprocessed_config = $this->preprocess_config( $config );
		$filtered_config     = apply_filters( 'style_manager/get_font_palettes', $config );

		foreach ( $filtered_config as $palette_id => $palette_config ) {
			if ( ! isset( $preprocessed_config[ $palette_id ] ) || ! is_array( $preprocessed_config[ $palette_id ] ) || ! is_array( $palette_config ) ) {
				continue;
			}

			$source_palette_config = $preprocessed_config[ $palette_id ];

			// Preserve source metadata when site filters provide partial palette overrides.
			$filtered_config[ $palette_id ] = wp_parse_args( $palette_config, $source_palette_config );

			if ( isset( $source_palette_config['fonts_logic'] ) && is_array( $source_palette_config['fonts_logic'] ) ) {
				$filtered_config[ $palette_id ]['fonts_logic'] = wp_parse_args(
					isset( $palette_config['fonts_logic'] ) && is_array( $palette_config['fonts_logic'] ) ? $palette_config['fonts_logic'] : [],
					$source_palette_config['fonts_logic']
				);
			}

			if ( isset( $source_palette_config['personality'] ) && is_array( $source_palette_config['personality'] ) ) {
				$filtered_config[ $palette_id ]['personality'] = wp_parse_args(
					isset( $palette_config['personality'] ) && is_array( $palette_config['personality'] ) ? $palette_config['personality'] : [],
					$source_palette_config['personality']
				);
			}
		}

		return $filtered_config;
	}

	/**
	 * Set up the Style Manager Customizer section master fonts config.
	 *
	 * This handles the base configuration for the controls in the Style Manager section. We expect other parties (e.g. the theme),
	 * to come and fill up the missing details (e.g. connected fields).
	 *
	 * @since 2.0.0
	 *
	 * @param array $config This holds required keys for the plugin config like 'opt-name', 'panels', 'settings'.
	 *
	 * @return array
	 */
	protected function add_style_manager_section_master_fonts_config( array $config ): array {
		// If there is no Style Manager support, bail early.
		if ( ! $this->is_supported() ) {
			return $config;
		}

		if ( ! isset( $config['sections']['style_manager_section'] ) ) {
			$config['sections']['style_manager_section'] = [];
		}

		// The section might be already defined, thus we merge, not replace the entire section config.
		$config['sections']['style_manager_section'] = ArrayHelpers::array_merge_recursive_distinct( $config['sections']['style_manager_section'], [
			'options' => [
				'sm_voice_tuner_label'    => [
					'type'         => 'html',
					'html'         => '<span class="customize-control-title">' . esc_html__( 'Tune your project\'s voice:', '__plugin_txtd' ) . '</span>' .
					                  '<span class="description customize-control-description">' . esc_html__( 'Set the personality of your project. Font palettes will be sorted by how well they match.', '__plugin_txtd' ) . '</span>',
					'setting_type' => 'option',
					'setting_id'   => 'sm_voice_tuner_label',
					'priority'     => 0.5,
				],
				'sm_voice_formality'      => [
					'type'         => 'sm_radio',
					'setting_type' => 'option',
					'setting_id'   => 'sm_voice_formality',
					'label'        => esc_html__( 'Formality', '__plugin_txtd' ),
					'default'      => 'balanced',
					'live'         => true,
					'priority'     => 0.6,
					'choices'      => [
						'low'      => esc_html__( 'Casual', '__plugin_txtd' ),
						'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
						'high'     => esc_html__( 'Formal', '__plugin_txtd' ),
					],
				],
				'sm_voice_energy'         => [
					'type'         => 'sm_radio',
					'setting_type' => 'option',
					'setting_id'   => 'sm_voice_energy',
					'label'        => esc_html__( 'Energy', '__plugin_txtd' ),
					'default'      => 'balanced',
					'live'         => true,
					'priority'     => 0.7,
					'choices'      => [
						'low'      => esc_html__( 'Calm', '__plugin_txtd' ),
						'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
						'high'     => esc_html__( 'Energetic', '__plugin_txtd' ),
					],
				],
				'sm_voice_warmth'         => [
					'type'         => 'sm_radio',
					'setting_type' => 'option',
					'setting_id'   => 'sm_voice_warmth',
					'label'        => esc_html__( 'Warmth', '__plugin_txtd' ),
					'default'      => 'balanced',
					'live'         => true,
					'priority'     => 0.8,
					'choices'      => [
						'low'      => esc_html__( 'Cool', '__plugin_txtd' ),
						'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
						'high'     => esc_html__( 'Warm', '__plugin_txtd' ),
					],
				],
				'sm_voice_tradition'      => [
					'type'         => 'sm_radio',
					'setting_type' => 'option',
					'setting_id'   => 'sm_voice_tradition',
					'label'        => esc_html__( 'Style', '__plugin_txtd' ),
					'default'      => 'balanced',
					'live'         => true,
					'priority'     => 0.9,
					'choices'      => [
						'low'      => esc_html__( 'Modern', '__plugin_txtd' ),
						'balanced' => esc_html__( 'Balanced', '__plugin_txtd' ),
						'high'     => esc_html__( 'Classic', '__plugin_txtd' ),
					],
				],
				'sm_font_sizing'              => [
					'type'         => 'sm_radio',
					'desc'         => wp_kses( __( 'Adjust the overall font sizing you want to use on your site. For advanced controls over individual elements, open the section below.', '__plugin_txtd' ), [ 'strong' => [] ] ),
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_sizing',
					'label'        => esc_html__( 'Font Sizing', '__plugin_txtd' ),
					'default'      => 'normal',
					'live'         => true,
					'priority'     => 3,
					'choices' => [
						'smallest' => esc_html__( 'Smallest', '__plugin_txtd' ),
						'smaller' => esc_html__( 'Smaller', '__plugin_txtd' ),
						'normal' => esc_html__( 'Normal', '__plugin_txtd' ),
						'larger'  => esc_html__( 'Larger', '__plugin_txtd' ),
						'largest'  => esc_html__( 'Largest', '__plugin_txtd' ),
					],
				],
				'sm_separator_0_0' => [ 'type' => 'html', 'html' => '', 'priority' => 4 ],
				self::SM_FONT_PALETTE_OPTION_KEY => [
					'type'         => 'preset',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type' => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'   => self::SM_FONT_PALETTE_OPTION_KEY,
					// We don't want to refresh the preview window, even though we have no direct effect on it through this field.
					'live'         => true,
					'priority'     => 40,
					'label'        => esc_html__( 'Select a font palette:', '__plugin_txtd' ),
					'desc'         => esc_html__( 'Conveniently change the design of your site with font palettes. Easy as pie.', '__plugin_txtd' ),
					'default'      => 'julia',
					'choices_type' => 'font_palette',
					'choices'      => $this->get_palettes(),
				],
				'sm_font_palettes_spacing_bottom' => [
					'type'       => 'html',
					'html'       => '',
					'setting_id' => 'sm_font_palettes_spacing_bottom',
					'priority'   => 31,
				],
				'sm_fine_tune_intro' => [
					'type'         => 'html',
					'priority'     => 10,
					'setting_type' => 'option',
					'setting_id'   => 'sm_fine_tune_intro',
					'html'         => '<span class="description customize-control-description">' . esc_html__( 'Adjust the font family and sizing for each category of fonts while maintaining the hierarchy relation between them.', '__plugin_txtd' ) . '</span>',
				],
				'sm_font_primary_intro' => [
					'type'         => 'html',
					'priority'     => 21.1,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_primary_intro',
					'html'         => '<div class="customize-control-title font_primary">' . esc_html__( 'Font Primary', '__plugin_txtd' ) . '</div>' .
					                  '<span class="description customize-control-description">' . esc_html__( 'Adjust the font family and sizing of all the elements from the Font Primary category.', '__plugin_txtd' ) . '</span>',
				],
				'sm_font_primary'                 => [
					'type'             => 'font',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type'     => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'       => 'sm_font_primary',
					// We don't want to refresh the preview window, even though we have no direct effect on it through this field.
					'live'             => true,
					'priority'         => 21.2,
					'label'            => esc_html__( 'Font Primary', '__plugin_txtd' ),
					'default'          => [
						'font-family'    => 'Montserrat',
						'font-weight'    => 'regular',
						'font-size'      => 20,
						'line-height'    => 1.25,
						'letter-spacing' => 0.029,
						'text-transform' => 'uppercase',
					],
					// Sub Fields Configuration
					'fields'           => [
						// These subfields are disabled because they are calculated through the font palette logic.
						'font-size'       => false,
						'font-weight'     => false,
						'line-height'     => false,
						'letter-spacing'  => false,
						'text-align'      => false,
						'text-transform'  => false,
						'text-decoration' => false,
					],
					'connected_fields' => [],
				],
				'sm_font_primary_elevation' => [
					'type'         => 'range',
					'desc'         => __( 'Change all the font sizes of the elements in this category by a fixed amount.', '__plugin_txtd' ),
					'live'         => true,
					'priority'     => 21.3,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_primary_elevation',
					'label'        => esc_html__( 'Font Sizing: Elevation ↑↓', '__plugin_txtd' ),
					'default'      => 24,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_font_primary_pitch'      => [
					'type'         => 'range',
					'desc'         => __( 'Adjust the rising angle for elements in this category. A flat ‘0’ degree Pitch makes all elements equal to the Elevation, removing the hierarchy between them.', '__plugin_txtd' ),
					'live'         => true,
					'priority'     => 21.4,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_primary_pitch',
					'label'        => esc_html__( 'Font Sizing: Pitch', '__plugin_txtd' ),
					'default'      => 141,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_separator_0_1' => [ 'type' => 'html', 'html' => '', 'priority' => 22 ],
				'sm_font_secondary_intro' => [
					'type'         => 'html',
					'priority'     => 22.1,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_secondary_intro',
					'html'         => '<div class="customize-control-title font_secondary">' . esc_html__( 'Font Secondary', '__plugin_txtd' ) . '</div>',
				],
				'sm_font_secondary'               => [
					'type'             => 'font',
					'setting_type'     => 'option',
					'setting_id'       => 'sm_font_secondary',
					'live'             => true,
					'priority'         => 22.2,
					'label'            => esc_html__( 'Font Secondary', '__plugin_txtd' ),
					'default'          => [
						'font-family'    => 'Montserrat',
						'font-weight'    => '300',
						'font-size'      => 10,
						'line-height'    => 1.625,
						'letter-spacing' => 0.029,
						'text-transform' => 'uppercase',
					],
					// Sub Fields Configuration
					'fields'           => [
						// These subfields are disabled because they are calculated through the font palette logic.
						'font-size'       => false,
						'font-weight'     => false,
						'line-height'     => false,
						'letter-spacing'  => false,
						'text-align'      => false,
						'text-transform'  => false,
						'text-decoration' => false,
					],
					'connected_fields' => [],
				],
				'sm_font_secondary_elevation' => [
					'type'         => 'range',
					'live'         => true,
					'priority'     => 22.3,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_secondary_elevation',
					'label'        => esc_html__( 'Elevation ↑↓', '__plugin_txtd' ),
					'default'      => 16,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_font_secondary_pitch'      => [
					'type'         => 'range',
					'live'         => true,
					'priority'     => 22.4,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_secondary_pitch',
					'label'        => esc_html__( 'Pitch', '__plugin_txtd' ),
					'default'      => 2,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_separator_0_2' => [ 'type' => 'html', 'html' => '', 'priority' => 23 ],
				'sm_font_body_intro' => [
					'type'         => 'html',
					'priority'     => 23.1,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_body_intro',
					'html'         => '<div class="customize-control-title font_body">' . esc_html__( 'Font Body', '__plugin_txtd' ) . '</div>',
				],
				'sm_font_body'                    => [
					'type'             => 'font',
					'setting_type'     => 'option',
					'setting_id'       => 'sm_font_body',
					'live'             => true,
					'priority'         => 23.2,
					'label'            => esc_html__( 'Font Body', '__plugin_txtd' ),
					'default'          => [
						'font-family'    => 'Montserrat',
						'font-weight'    => '300',
						'font-size'      => 14,
						'line-height'    => 1.6,
						'letter-spacing' => 0.029,
						'text-transform' => 'uppercase',
					],
					// Sub Fields Configuration
					'fields'           => [
						// These subfields are disabled because they are calculated through the font palette logic.
						'font-size'       => false,
						'font-weight'     => false,
						'line-height'     => false,
						'letter-spacing'  => false,
						'text-align'      => false,
						'text-transform'  => false,
						'text-decoration' => false,
					],
					'connected_fields' => [],
				],
				'sm_font_body_elevation' => [
					'type'         => 'range',
					'live'         => true,
					'priority'     => 23.3,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_body_elevation',
					'label'        => esc_html__( 'Elevation ↑↓', '__plugin_txtd' ),
					'default'      => 17,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_font_body_pitch'      => [
					'type'         => 'range',
					'live'         => true,
					'priority'     => 23.4,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_body_pitch',
					'label'        => esc_html__( 'Pitch', '__plugin_txtd' ),
					'default'      => 7,
					'input_attrs'  => [
						'min'  => 0,
						'max'  => 100,
						'step' => 1,
					],
				],
				'sm_separator_0_3' => [ 'type' => 'html', 'html' => '', 'priority' => 24 ],
				'sm_font_accent_intro' => [
					'type'         => 'html',
					'priority'     => 24.1,
					'setting_type' => 'option',
					'setting_id'   => 'sm_font_accent_intro',
					'html'         => '<div class="customize-control-title font_accent">' . esc_html__( 'Font Accent', '__plugin_txtd' ) . '</div>',
				],
				'sm_font_accent' => [
					'type'             => 'font',
					// We will bypass the plugin setting regarding where to store - we will store it cross-theme in wp_options
					'setting_type'     => 'option',
					// We will force this setting id preventing prefixing and other regular processing.
					'setting_id'       => 'sm_font_accent',
					// We don't want to refresh the preview window, even though we have no direct effect on it through this field.
					'live'             => true,
					'priority'         => 24.2,
					'label'            => esc_html__( 'Font Accent', '__plugin_txtd' ),
					'default'          => [
						'font-family'    => 'Montserrat',
						'font-weight'    => 'regular',
						'font-size'      => 20,
						'line-height'    => 1.25,
						'letter-spacing' => 0.029,
						'text-transform' => 'uppercase',
					],
					// Sub Fields Configuration
					'fields'           => [
						// These subfields are disabled because they are calculated through the font palette logic.
						'font-size'       => false,
						'font-weight'     => false,
						'line-height'     => false,
						'letter-spacing'  => false,
						'text-align'      => false,
						'text-transform'  => false,
						'text-decoration' => false,
					],
					'connected_fields' => [],
				],
				'sm_separator_0_4' => [ 'type' => 'html', 'html' => '', 'priority' => 25 ],
				'sm_fonts_connected_fields_preset' => [
					'type'         => 'preset',
					'label'        => __( 'Connected Fields Presets', '__plugin_txtd' ),
					'live'         => true,
					'priority'     => 25,
					'setting_type' => 'option',
					'setting_id'   => 'sm_fonts_connected_fields_preset',
					'choices_type' => 'radio',
				],
				'sm_separator_0_5' => [ 'type' => 'html', 'html' => '', 'priority' => 29.9 ],
			],
		] );

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
		$font_palettes_fields = [
			'sm_font_sizing',
			'sm_separator_0_0',
			'sm_current_font_palette',
			'sm_fine_tune_intro',
			'sm_font_primary_intro',
			'sm_font_primary',
			'sm_font_primary_elevation',
			'sm_font_primary_pitch',
			'sm_separator_0_1',
			'sm_font_secondary_intro',
			'sm_font_secondary',
			'sm_font_secondary_elevation',
			'sm_font_secondary_pitch',
			'sm_separator_0_2',
			'sm_font_body_intro',
			'sm_font_body',
			'sm_font_body_elevation',
			'sm_font_body_pitch',
			'sm_separator_0_3',
			'sm_font_accent_intro',
			'sm_font_accent',
			'sm_separator_0_4',
			'sm_fonts_connected_fields_preset',
			'sm_separator_0_5',
			'sm_voice_tuner_label',
			'sm_voice_formality',
			'sm_voice_energy',
			'sm_voice_warmth',
			'sm_voice_tradition',
			self::SM_FONT_PALETTE_OPTION_KEY,
			self::SM_FONT_PALETTE_VARIATION_OPTION_KEY,
			'sm_font_palettes_spacing_bottom',
		];

		$font_palettes_section_config = [
			'title'       => esc_html__( 'Typography', '__plugin_txtd' ),
			'description' => wp_kses( __( 'Set up the <a href="https://pixelgrade.com/docs/design-and-style/typography-system/">Typography system</a> of your website using the tools below.', '__plugin_txtd' ), wp_kses_allowed_html( 'post' ) ),
			'section_id'  => 'sm_font_palettes_section',
			'priority'    => 20,
			'options'     => [],
		];

		foreach ( $font_palettes_fields as $field_id ) {
			if ( ! isset( $sm_section_config['options'][ $field_id ] ) ) {
				continue;
			}

			$field_config = $sm_section_config['options'][ $field_id ];
			$priority_overrides = [
				'sm_current_font_palette'                  => 4.5,
				'sm_separator_0_5'                         => 29.9,
				'sm_voice_tuner_label'                     => 30.0,
				'sm_voice_formality'                       => 30.1,
				'sm_voice_energy'                          => 30.2,
				'sm_voice_warmth'                          => 30.3,
				'sm_voice_tradition'                       => 30.4,
				self::SM_FONT_PALETTE_OPTION_KEY           => 40,
				self::SM_FONT_PALETTE_VARIATION_OPTION_KEY => 40.1,
				'sm_font_palettes_spacing_bottom'          => 40.2,
			];
			if ( isset( $priority_overrides[ $field_id ] ) ) {
				$field_config['priority'] = $priority_overrides[ $field_id ];
			}

			if ( empty( $font_palettes_section_config['options'] ) ) {
				$font_palettes_section_config['options'] = [ $field_id => $field_config ];
			} else {
				$font_palettes_section_config['options'] = array_merge( $font_palettes_section_config['options'], [ $field_id => $field_config ] );
			}
		}

		$sm_panel_config['sections']['sm_font_palettes_section'] = $font_palettes_section_config;

		return $sm_panel_config;
	}

	/**
	 * Get the Style Manager configuration of a certain option.
	 *
	 * @param string $option_id
	 * @param array  $config
	 *
	 * @return array|false The option config or false on failure.
	 */
	private function get_option_config( string $option_id, array $config ) {
		// We need to search for the option configured under the given id (the array key)
		if ( isset ( $config['panels'] ) ) {
			foreach ( $config['panels'] as $panel_id => $panel_settings ) {
				if ( isset( $panel_settings['sections'] ) ) {
					foreach ( $panel_settings['sections'] as $section_id => $section_settings ) {
						if ( isset( $section_settings['options'] ) ) {
							foreach ( $section_settings['options'] as $id => $option_config ) {
								if ( $id === $option_id ) {
									return $option_config;
								}
							}
						}
					}
				}
			}
		}

		if ( isset ( $config['sections'] ) ) {
			foreach ( $config['sections'] as $section_id => $section_settings ) {
				if ( isset( $section_settings['options'] ) ) {
					foreach ( $section_settings['options'] as $id => $option_config ) {
						if ( $id === $option_id ) {
							return $option_config;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get the default (hard-coded) font palettes configuration.
	 *
	 * This is only a fallback config in case we can't communicate with the cloud, the first time.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	protected function get_default_config(): array {
		$default_config = [
			'gema'  => [
				'label'   => esc_html__( 'Gema', '__plugin_txtd' ),
				'preview' => [
					// Font Palette Name
					'title'                => esc_html__( 'Gema', '__plugin_txtd' ),
					'description'          => esc_html__( 'A graceful nature, truly tasteful and polished.', '__plugin_txtd' ),
					'background_image_url' => 'https://cloud.pixelgrade.com/wp-content/uploads/2018/09/font-palette-thin.png',

					// Use the following options to style the preview card fonts
					// Including font-family, size, line-height, weight, letter-spacing and text transform
					'title_font'           => [
						'font' => 'font_primary',
						'size' => 32,
					],
					'description_font'     => [
						'font' => 'font_body',
						'size' => 16,
					],
				],

				// Personality vector used by voice tuning.
				'personality' => [
					'formality' => 0.65,
					'energy'    => 0.3,
					'warmth'    => 0.4,
					'tradition' => 0.5,
				],

				'fonts_logic' => [
					// Primary is used for main headings [Display, H1, H2, H3]
					'sm_font_primary'   => [
						// Font loaded when a palette is selected
						'font_family'                     => 'Montserrat',
						// Load all these fonts weights.
						'font_weights'                    => [ 100, 300, 700 ],
						// "Generate" the graph to be used for font-size and line-height.
						'font_size_to_line_height_points' => [
							[ 17, 1.7 ],
							[ 48, 1.2 ],
						],

						// Define how fonts will look based on the font size.
						'font_styles_intervals'           => [
							[
								'start'          => 10,
								'font_weight'    => 300,
								'letter_spacing' => '0.03em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 12,
								'font_weight'    => 700,
								'letter_spacing' => '0em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 18,
								'font_weight'    => 100,
								'letter_spacing' => '0.03em',
								'text_transform' => 'uppercase',
							],
						],
					],

					// Secondary font is used for smaller headings [H4, H5, H6], including meta details
					'sm_font_secondary' => [
						'font_family'                     => 'Montserrat',
						'font_weights'                    => [ 200, 400 ],
						'font_size_to_line_height_points' => [
							[ 10, 1.6 ],
							[ 18, 1.5 ],
						],
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 200,
								'letter_spacing' => '0.03em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 13,
								'font_weight'    => 'regular',
								'letter_spacing' => '0.015em',
								'text_transform' => 'uppercase',
							],
						],
					],

					// Used for Body Font [eg. entry-content]
					'sm_font_body'      => [
						'font_family'                     => 'Montserrat',
						'font_weights'                    => [ 200, '200italic', 700, '700italic' ],
						'font_size_to_line_height_points' => [
							[ 15, 1.8 ],
							[ 18, 1.7 ],
						],

						// Define how fonts will look based on their size
						'font_styles_intervals'           => [
							[
								'font_weight'    => 200,
								'letter_spacing' => 0,
								'text_transform' => 'none',
							],
						],
					],
				],
			],
			'julia' => [
				'label'   => esc_html__( 'Julia', '__plugin_txtd' ),
				'preview' => [
					// Font Palette Name
					'title'                => esc_html__( 'Julia', '__plugin_txtd' ),
					'description'          => esc_html__( 'A graceful nature, truly tasteful and polished.', '__plugin_txtd' ),
					'background_image_url' => 'https://cloud.pixelgrade.com/wp-content/uploads/2018/09/font-palette-serif.png',

					// Use the following options to style the preview card fonts
					// Including font-family, size, line-height, weight, letter-spacing and text transform
					'title_font'           => [
						'font' => 'font_primary',
						'size' => 30,
					],
					'description_font'     => [
						'font' => 'font_body',
						'size' => 17,
					],
				],

				// Personality vector used by voice tuning.
				'personality' => [
					'formality' => 0.55,
					'energy'    => 0.4,
					'warmth'    => 0.65,
					'tradition' => 0.6,
				],

				'fonts_logic' => [
					// Primary is used for main headings [Display, H1, H2, H3]
					'sm_font_primary'   => [
						// Font loaded when a palette is selected
						'font_family'                     => 'Lora',
						// Load all these fonts weights.
						'font_weights'                    => [ 700 ],
						// "Generate" the graph to be used for font-size and line-height.
						'font_size_to_line_height_points' => [
							[ 24, 1.25 ],
							[ 66, 1.15 ],
						],

						// Define how fonts will look based on the font size.
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 700,
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
						],
					],

					// Secondary font is used for smaller headings [H4, H5, H6], including meta details
					'sm_font_secondary' => [
						'font_family'                     => 'Montserrat',
						'font_weights'                    => [ 'regular', 600 ],
						'font_size_to_line_height_points' => [
							[ 14, 1.3 ],
							[ 16, 1.2 ],
						],
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 600,
								'letter_spacing' => '0.154em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 13,
								'font_weight'    => 600,
								'letter_spacing' => '0em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 14,
								'font_weight'    => 600,
								'letter_spacing' => '0.1em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 16,
								'font_weight'    => 600,
								'letter_spacing' => '0em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 17,
								'font_weight'    => 600,
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
						],
					],

					// Used for Body Font [eg. entry-content]
					'sm_font_body'      => [
						'font_family'                     => 'PT Serif',
						'font_weights'                    => [ 400, '400italic', 700, '700italic' ],
						'font_size_to_line_height_points' => [
							[ 15, 1.7 ],
							[ 18, 1.5 ],
						],

						// Define how fonts will look based on their size
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 'regular',
								'letter_spacing' => 0,
								'text_transform' => 'none',
							],
						],
					],
				],
			],
			'patch' => [
				'label'   => esc_html__( 'Patch', '__plugin_txtd' ),
				'preview' => [
					// Font Palette Name
					'title'                => esc_html__( 'Patch', '__plugin_txtd' ),
					'description'          => esc_html__( 'A graceful nature, truly tasteful and polished.', '__plugin_txtd' ),
					'background_image_url' => 'https://cloud.pixelgrade.com/wp-content/uploads/2018/09/font-palette-lofty.png',

					// Use the following options to style the preview card fonts
					// Including font-family, size, line-height, weight, letter-spacing and text transform
					'title_font'           => [
						'font' => 'font_primary',
						'size' => 26,
					],
					'description_font'     => [
						'font' => 'font_body',
						'size' => 16,
					],
				],

				// Personality vector used by voice tuning.
				'personality' => [
					'formality' => 0.25,
					'energy'    => 0.7,
					'warmth'    => 0.8,
					'tradition' => 0.2,
				],

				'fonts_logic' => [
					// Primary is used for main headings [Display, H1, H2, H3]
					'sm_font_primary'   => [
						// Font loaded when a palette is selected
						'font_family'                     => 'Oswald',
						// Load all these fonts weights.
						'font_weights'                    => [ 300, 400, 500 ],
						// "Generate" the graph to be used for font-size and line-height.
						'font_size_to_line_height_points' => [
							[ 20, 1.55 ],
							[ 56, 1.25 ],
						],

						// Define how fonts will look based on the font size.
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 500,
								'letter_spacing' => '0.04em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 24,
								'font_weight'    => 300,
								'letter_spacing' => '0.06em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 25,
								'font_weight'    => 'regular',
								'letter_spacing' => '0.04em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 26,
								'font_weight'    => 500,
								'letter_spacing' => '0.04em',
								'text_transform' => 'uppercase',
							],
						],
					],

					// Secondary font is used for smaller headings [H4, H5, H6], including meta details
					'sm_font_secondary' => [
						'font_family'                     => 'Oswald',
						'font_weights'                    => [ 200, '200italic', 500, '500italic' ],
						'font_size_to_line_height_points' => [
							[ 14, 1.625 ],
							[ 24, 1.5 ],
						],
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 500,
								'letter_spacing' => '0.01em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 20,
								'font_weight'    => 500,
								'letter_spacing' => '0em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 24,
								'font_weight'    => 200,
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
						],
					],

					// Used for Body Font [eg. entry-content]
					'sm_font_body'      => [
						'font_family'                     => 'Roboto',
						'font_weights'                    => [
							300,
							'300italic',
							400,
							'400italic',
							500,
							'500italic',
						],
						'font_size_to_line_height_points' => [
							[ 14, 1.5 ],
							[ 24, 1.45 ],
						],

						// Define how fonts will look based on their size
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'end'            => 10.9,
								'font_weight'    => 500,
								'letter_spacing' => '0.03em',
								'text_transform' => 'none',
							],
							[
								'start'          => 10.9,
								'end'            => 12,
								'font_weight'    => 500,
								'letter_spacing' => '0.02em',
								'text_transform' => 'uppercase',
							],
							[
								'start'          => 12,
								'font_weight'    => 300,
								'letter_spacing' => 0,
								'text_transform' => 'none',
							],
						],
					],
				],
			],
			'hive'  => [
				'label'   => esc_html__( 'Hive', '__plugin_txtd' ),
				'preview' => [
					// Font Palette Name
					'title'                => esc_html__( 'Hive', '__plugin_txtd' ),
					'description'          => esc_html__( 'A graceful nature, truly tasteful and polished.', '__plugin_txtd' ),
					'background_image_url' => 'https://cloud.pixelgrade.com/wp-content/uploads/2018/09/font-palette-classic.png',

					// Use the following options to style the preview card fonts
					// Including font-family, size, line-height, weight, letter-spacing and text transform
					'title_font'           => [
						'font' => 'font_primary',
						'size' => 36,
					],
					'description_font'     => [
						'font' => 'font_body',
						'size' => 18,
					],
				],

				// Personality vector used by voice tuning.
				'personality' => [
					'formality' => 0.7,
					'energy'    => 0.5,
					'warmth'    => 0.45,
					'tradition' => 0.7,
				],

				'fonts_logic' => [
					// Primary is used for main headings [Display, H1, H2, H3]
					'sm_font_primary'   => [
						// Font loaded when a palette is selected
						'font_family'                     => 'Playfair Display',
						// Load all these fonts weights.
						'font_weights'                    => [
							400,
							'400italic',
							700,
							'700italic',
							900,
							'900italic',
						],
						// "Generate" the graph to be used for font-size and line-height.
						'font_size_to_line_height_points' => [
							[ 20, 1.55 ],
							[ 65, 1.15 ],
						],

						// Define how fonts will look based on the font size.
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 'regular',
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
						],
					],

					// Secondary font is used for smaller headings [H4, H5, H6], including meta details
					'sm_font_secondary' => [
						'font_family'                     => 'Noto Serif',
						'font_weights'                    => [ 400, '400italic', 700, '700italic' ],
						'font_size_to_line_height_points' => [
							[ 13, 1.33 ],
							[ 18, 1.5 ],
						],
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'end'            => 15,
								'font_weight'    => 'regular',
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
							[
								'start'          => 15,
								'font_weight'    => 700,
								'letter_spacing' => '0em',
								'text_transform' => 'none',
							],
						],
					],

					// Used for Body Font [eg. entry-content]
					'sm_font_body'      => [
						'font_family'                     => 'Noto Serif',
						'font_weights'                    => [ 400, '400italic', 700, '700italic' ],
						'font_size_to_line_height_points' => [
							[ 13, 1.4 ],
							[ 18, 1.5 ],
						],

						// Define how fonts will look based on their size
						'font_styles_intervals'           => [
							[
								'start'          => 0,
								'font_weight'    => 'regular',
								'letter_spacing' => 0,
								'text_transform' => 'none',
							],
						],
					],
				],
			],
		];

		return apply_filters( 'style_manager/default_font_palettes', $default_config );
	}

	/**
	 * Get the current font palette ID or false if none is selected.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	protected function get_current_palette() {
		return get_option( self::SM_FONT_PALETTE_OPTION_KEY, false );
	}

	/**
	 * Get the current font palette variation ID or false if none is selected.
	 *
	 * @since 2.0.0
	 *
	 * @return string|false
	 */
	protected function get_current_palette_variation() {
		return get_option( self::SM_FONT_PALETTE_VARIATION_OPTION_KEY, false );
	}

	/**
	 * Determine if the selected font palette has been customized and remember this in an option.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function update_custom_palette_in_use(): bool {
		// If there is no Font Palettes support, bail early.
		if ( ! $this->is_supported() ) {
			return false;
		}

		$current_palette = $this->get_current_palette();
		if ( empty( $current_palette ) ) {
			return false;
		}

		$font_palettes = $this->get_palettes();
		if ( ! isset( $font_palettes[ $current_palette ] ) || empty( $font_palettes[ $current_palette ]['options'] ) ) {
			return false;
		}

		$is_custom_palette = false;
		// If any of the current master fonts has a different value than the one provided by the font palette,
		// it means a custom font palette is in use.
		$current_palette_options = $font_palettes[ $current_palette ]['options'];
		foreach ( $current_palette_options as $setting_id => $value ) {
			if ( $value != get_option( $setting_id ) ) {
				$is_custom_palette = true;
				break;
			}
		}

		update_option( self::SM_IS_CUSTOM_FONT_PALETTE_OPTION_KEY, $is_custom_palette, true );

		do_action( 'style_manager/updated_custom_font_palette_in_use', $is_custom_palette );

		return true;
	}

	/**
	 * Determine if a custom font palette is in use.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_using_custom_palette(): bool {
		return (bool) get_option( self::SM_IS_CUSTOM_FONT_PALETTE_OPTION_KEY, false );
	}

	/**
	 * Get all the defined Style Manager master font field ids.
	 *
	 * @since 2.0.0
	 *
	 * @param array|null $options_details Optional.
	 *
	 * @return array
	 */
	public function get_all_master_font_controls_ids( ?array $options_details = null ): array {
		$control_ids = [];

		if ( empty( $options_details ) ) {
			$options_details = $this->options->get_details_all( true );
		}

		if ( empty( $options_details ) ) {
			return $control_ids;
		}

		foreach ( $options_details as $option_id => $option_details ) {
			if ( ! empty( $option_details['type'] ) && 'font' === $option_details['type'] && 0 === strpos( $option_id, 'sm_' ) ) {
				$control_ids[] = $option_id;
			}
		}

		return $control_ids;
	}

	/**
	 * Add font palettes usage data to the site data sent to the cloud.
	 *
	 * @since 2.0.0
	 *
	 * @param array $site_data
	 *
	 * @return array
	 */
	protected function add_palettes_to_site_data( array $site_data ): array {
		if ( empty( $site_data['font_palettes'] ) ) {
			$site_data['font_palettes'] = [];
		}

		// If others have added data before us, we will merge with it.
		$site_data['font_palettes'] = array_merge( $site_data['font_palettes'], [
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
		if ( empty( $localized['fontPalettes'] ) ) {
			$localized['fontPalettes'] = [];
		}

		$localized['fontPalettes']['masterSettingIds'] = $this->get_all_master_font_controls_ids();

		$localized['fontPalettes']['variations'] = [
			'light'   => [],
			'regular' => [],
			'big'     => [],
		];

		$personality_map = [];
		foreach ( $this->get_palettes() as $palette_id => $palette_config ) {
			$palette_config = $this->normalize_palette_personality( $palette_config, (string) $palette_id );
			$personality_map[ $palette_id ] = $palette_config['personality'];
		}
		$localized['fontPalettes']['personalityMap'] = $personality_map;

		if ( empty( $localized['l10n'] ) ) {
			$localized['l10n'] = [];
		}
		$localized['l10n']['fontPalettes'] = [
		];

		return $localized;
	}
}

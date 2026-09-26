<?php
/**
 * WordPress Font Library font source.
 *
 * @since   2.7.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Customize;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Feeds the fonts installed through the core Font Library (the `wp_font_family`
 * and `wp_font_face` post types, WordPress 6.5+) into Style Manager's font
 * fields, and makes sure a picked family actually loads.
 *
 * Loading contract, one loader per face:
 * - WordPress prints `@font-face` for every face that is active in Global
 *   Styles (`settings.typography.fontFamilies`), on the frontend and in the
 *   editor canvas. Style Manager never prints those faces again.
 * - For the families picked in Style Manager font fields, Style Manager prints
 *   (through core's own `wp_print_font_faces()`) only the installed faces that
 *   WordPress does not already print.
 *
 * With no installed Font Library fonts, this provider adds nothing: no font
 * options and no output.
 *
 * @since 2.7.0
 */
class FontLibraryFonts extends AbstractHookProvider {

	/**
	 * The font type Style Manager uses for Font Library fonts.
	 */
	const FONT_TYPE = 'font_library_font';

	/**
	 * The Fonts service, used to know which families the font fields use.
	 *
	 * @var Fonts
	 */
	protected Fonts $fonts;

	/**
	 * Per-request cache of the installed fonts list.
	 *
	 * @var array|null
	 */
	protected ?array $installed_fonts = null;

	/**
	 * Constructor.
	 *
	 * @param Fonts $fonts The fonts service.
	 */
	public function __construct( Fonts $fonts ) {
		$this->fonts = $fonts;
	}

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_filter( 'style_manager/font_library_fonts', [ $this, 'add_installed_fonts' ], 10, 1 );

		// After core's wp_print_font_faces() (wp_head, 50).
		add_action( 'wp_head', [ $this, 'print_font_faces' ], 51 );
		// The editor canvas resolves its styles from enqueue_block_assets.
		add_action( 'enqueue_block_assets', [ $this, 'enqueue_editor_font_faces' ], 20 );
	}

	/**
	 * Add the installed Font Library fonts to the Style Manager font source.
	 *
	 * @param mixed $fonts The fonts other providers added.
	 *
	 * @return array
	 */
	public function add_installed_fonts( $fonts ): array {
		if ( ! is_array( $fonts ) ) {
			$fonts = [];
		}

		foreach ( $this->get_installed_fonts() as $family => $font ) {
			if ( ! isset( $fonts[ $family ] ) ) {
				$fonts[ $family ] = $font;
			}
		}

		return $fonts;
	}

	/**
	 * Get the fonts installed in the Font Library, normalized into the shape
	 * Style Manager font sources use, keyed by font family.
	 *
	 * @return array
	 */
	public function get_installed_fonts(): array {
		if ( null !== $this->installed_fonts ) {
			return $this->installed_fonts;
		}

		$this->installed_fonts = [];

		if ( ! function_exists( 'post_type_exists' ) || ! post_type_exists( 'wp_font_family' ) ) {
			return $this->installed_fonts;
		}

		$family_posts = get_posts( [
			'post_type'              => 'wp_font_family',
			'post_status'            => 'publish',
			'posts_per_page'         => 100,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		] );

		if ( empty( $family_posts ) ) {
			return $this->installed_fonts;
		}

		$face_posts = get_posts( [
			'post_type'              => 'wp_font_face',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'post_parent__in'        => wp_list_pluck( $family_posts, 'ID' ),
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		] );

		$faces_by_family = [];
		foreach ( $face_posts as $face_post ) {
			$settings = json_decode( (string) $face_post->post_content, true );
			if ( is_array( $settings ) ) {
				$faces_by_family[ (int) $face_post->post_parent ][] = $settings;
			}
		}

		foreach ( $family_posts as $family_post ) {
			$settings = json_decode( (string) $family_post->post_content, true );
			$settings = is_array( $settings ) ? $settings : [];

			$font = self::normalize_family(
				[
					'name'       => (string) $family_post->post_title,
					'slug'       => (string) $family_post->post_name,
					'fontFamily' => isset( $settings['fontFamily'] ) ? (string) $settings['fontFamily'] : '',
				],
				$faces_by_family[ (int) $family_post->ID ] ?? []
			);

			if ( null !== $font && ! isset( $this->installed_fonts[ $font['family'] ] ) ) {
				$this->installed_fonts[ $font['family'] ] = $font;
			}
		}

		return $this->installed_fonts;
	}

	/**
	 * Normalize a Font Library family (and its faces) into a Style Manager font entry.
	 *
	 * @param array $family_settings The family `name`, `slug`, and `fontFamily` (a CSS font stack).
	 * @param array $faces_settings  The face settings (theme.json `fontFace` shape).
	 *
	 * @return array|null Null when the family has no usable name or no loadable face.
	 */
	public static function normalize_family( array $family_settings, array $faces_settings ): ?array {
		$stack  = self::split_font_stack( (string) ( $family_settings['fontFamily'] ?? '' ) );
		$family = array_shift( $stack );
		if ( empty( $family ) ) {
			$family = trim( (string) ( $family_settings['name'] ?? '' ), " \"'" );
		}
		if ( '' === $family ) {
			return null;
		}

		$faces    = [];
		$variants = [];
		foreach ( $faces_settings as $face ) {
			$face = self::normalize_face( $face, $family );
			if ( null === $face ) {
				continue;
			}

			$faces[]  = $face;
			$variants = array_merge( $variants, self::face_variants( $face ) );
		}

		if ( empty( $faces ) ) {
			return null;
		}

		$variants = array_values( array_unique( $variants ) );
		sort( $variants, SORT_STRING );

		$fallback_stack = implode( ', ', $stack );
		$generic        = self::generic_family( $stack );

		return [
			'family'         => $family,
			'family_display' => ! empty( $family_settings['name'] ) ? (string) $family_settings['name'] : $family,
			'slug'           => (string) ( $family_settings['slug'] ?? '' ),
			'category'       => $generic ?: 'other',
			'fallback_stack' => $fallback_stack,
			'variants'       => $variants,
			'font_faces'     => $faces,
			'source'         => 'font_library',
		];
	}

	/**
	 * Print the `@font-face` rules for the Font Library faces the Style Manager
	 * font fields use and WordPress does not print itself.
	 */
	public function print_font_faces() {
		$fonts = $this->get_font_faces_to_print();
		if ( empty( $fonts ) || ! function_exists( 'wp_print_font_faces' ) ) {
			return;
		}

		wp_print_font_faces( $fonts );
	}

	/**
	 * Add the same `@font-face` rules to the editor canvas.
	 */
	public function enqueue_editor_font_faces() {
		if ( ! is_admin() ) {
			return;
		}

		$fonts = $this->get_font_faces_to_print();
		if ( empty( $fonts ) || ! function_exists( 'wp_print_font_faces' ) ) {
			return;
		}

		ob_start();
		wp_print_font_faces( $fonts );
		$css = trim( wp_strip_all_tags( (string) ob_get_clean() ) );
		if ( '' === $css ) {
			return;
		}

		$handle = 'pixelgrade_style_manager-font-library-faces';
		wp_register_style( $handle, false, [], \Pixelgrade\StyleManager\VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $css );
	}

	/**
	 * Get the faces Style Manager must print: installed faces of the families
	 * used in font fields, minus the faces WordPress already prints.
	 *
	 * @return array Font families (keyed by family) of `wp_print_font_faces()` face arrays.
	 */
	public function get_font_faces_to_print(): array {
		$installed = $this->get_installed_fonts();
		if ( empty( $installed ) ) {
			return [];
		}

		$used = array_intersect( $this->fonts->get_used_font_families_of_type( self::FONT_TYPE ), array_keys( $installed ) );
		if ( empty( $used ) ) {
			return [];
		}

		$core_printed = self::get_face_keys_from_font_families( $this->get_core_font_families() );

		$fonts = [];
		foreach ( $used as $family ) {
			$faces = self::filter_faces_not_in( $installed[ $family ]['font_faces'], $core_printed );
			if ( ! empty( $faces ) ) {
				$fonts[ $family ] = array_map( [ self::class, 'to_font_face_properties' ], $faces );
			}
		}

		return $fonts;
	}

	/**
	 * The font families WordPress prints `@font-face` rules for (all Global Styles origins).
	 *
	 * @return array
	 */
	protected function get_core_font_families(): array {
		if ( ! function_exists( 'wp_get_global_settings' ) ) {
			return [];
		}

		$families = wp_get_global_settings( [ 'typography', 'fontFamilies' ] );

		return is_array( $families ) ? $families : [];
	}

	/**
	 * Build `family|weight|style` keys for every face declared in theme.json font families.
	 *
	 * @param array $font_families theme.json `typography.fontFamilies`, by origin or as a flat list.
	 *
	 * @return string[]
	 */
	public static function get_face_keys_from_font_families( array $font_families ): array {
		$definitions = [];
		foreach ( $font_families as $key => $value ) {
			if ( is_array( $value ) && isset( $value['fontFamily'] ) ) {
				$definitions[] = $value;
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $definition ) {
					if ( is_array( $definition ) ) {
						$definitions[] = $definition;
					}
				}
			}
		}

		$keys = [];
		foreach ( $definitions as $definition ) {
			if ( empty( $definition['fontFace'] ) || ! is_array( $definition['fontFace'] ) ) {
				continue;
			}

			$stack  = self::split_font_stack( (string) ( $definition['fontFamily'] ?? '' ) );
			$family = (string) array_shift( $stack );
			foreach ( $definition['fontFace'] as $face ) {
				if ( ! is_array( $face ) ) {
					continue;
				}
				$face_family = self::split_font_stack( (string) ( $face['fontFamily'] ?? '' ) );
				$keys[]      = self::face_key( $face_family[0] ?? $family, $face );
			}
		}

		return array_values( array_unique( $keys ) );
	}

	/**
	 * Keep only the faces whose key is not in the given list.
	 *
	 * @param array    $faces Normalized faces.
	 * @param string[] $keys  Face keys to exclude.
	 *
	 * @return array
	 */
	public static function filter_faces_not_in( array $faces, array $keys ): array {
		return array_values( array_filter( $faces, static function ( $face ) use ( $keys ) {
			return ! in_array( self::face_key( (string) $face['fontFamily'], $face ), $keys, true );
		} ) );
	}

	/**
	 * Convert a theme.json-shaped face into the `wp_print_font_faces()` shape.
	 *
	 * @param array $face Normalized face (camelCase keys).
	 *
	 * @return array
	 */
	public static function to_font_face_properties( array $face ): array {
		$properties = [];
		foreach ( $face as $key => $value ) {
			$properties[ strtolower( (string) preg_replace( '/(?<!^)[A-Z]/', '-$0', (string) $key ) ) ] = $value;
		}

		return $properties;
	}

	/**
	 * Normalize a single face.
	 *
	 * @param mixed  $face   Face settings.
	 * @param string $family The family name the face belongs to.
	 *
	 * @return array|null Null when the face has no source.
	 */
	protected static function normalize_face( $face, string $family ): ?array {
		if ( ! is_array( $face ) ) {
			return null;
		}

		$src = $face['src'] ?? [];
		$src = array_values( array_filter( array_map( 'strval', is_array( $src ) ? $src : [ $src ] ) ) );
		if ( empty( $src ) ) {
			return null;
		}

		$normalized = [
			'fontFamily'  => $family,
			'fontStyle'   => ! empty( $face['fontStyle'] ) ? (string) $face['fontStyle'] : 'normal',
			'fontWeight'  => ! empty( $face['fontWeight'] ) ? (string) $face['fontWeight'] : '400',
			'src'         => $src,
			'fontDisplay' => ! empty( $face['fontDisplay'] ) ? (string) $face['fontDisplay'] : 'fallback',
		];

		foreach ( [ 'fontStretch', 'unicodeRange', 'fontVariant', 'fontFeatureSettings', 'fontVariationSettings', 'ascentOverride', 'descentOverride', 'lineGapOverride', 'sizeAdjust' ] as $optional ) {
			if ( ! empty( $face[ $optional ] ) && is_scalar( $face[ $optional ] ) ) {
				$normalized[ $optional ] = (string) $face[ $optional ];
			}
		}

		return $normalized;
	}

	/**
	 * The Style Manager variants a face covers (`400`, `700italic`, ...).
	 * A variable weight range (`100 900`) covers every hundred in the range.
	 *
	 * @param array $face Normalized face.
	 *
	 * @return string[]
	 */
	protected static function face_variants( array $face ): array {
		$suffix = in_array( strtolower( $face['fontStyle'] ), [ 'italic', 'oblique' ], true ) ? 'italic' : '';

		$weights = [];
		$weight  = strtolower( trim( $face['fontWeight'] ) );
		if ( preg_match( '/^(\d{1,4})\s+(\d{1,4})$/', $weight, $range ) ) {
			$min = (int) max( 100, ceil( (int) $range[1] / 100 ) * 100 );
			$max = (int) min( 900, floor( (int) $range[2] / 100 ) * 100 );
			for ( $w = $min; $w <= $max; $w += 100 ) {
				$weights[] = (string) $w;
			}
		} elseif ( 'bold' === $weight ) {
			$weights[] = '700';
		} elseif ( preg_match( '/^\d{3}$/', $weight ) ) {
			$weights[] = $weight;
		} else {
			$weights[] = '400';
		}

		return array_map( static function ( $w ) use ( $suffix ) {
			return $w . $suffix;
		}, $weights );
	}

	/**
	 * Identify a face by family, weight, and style.
	 *
	 * @param string $family Family name.
	 * @param array  $face   Face settings (camelCase keys).
	 *
	 * @return string
	 */
	protected static function face_key( string $family, array $face ): string {
		$weight = ! empty( $face['fontWeight'] ) ? (string) $face['fontWeight'] : '400';
		$style  = ! empty( $face['fontStyle'] ) ? (string) $face['fontStyle'] : 'normal';

		return strtolower( trim( $family, " \"'" ) ) . '|' . preg_replace( '/\s+/', ' ', strtolower( trim( $weight ) ) ) . '|' . strtolower( trim( $style ) );
	}

	/**
	 * Split a CSS font stack into unquoted names.
	 *
	 * @param string $stack E.g. `"Playfair Display", serif`.
	 *
	 * @return string[]
	 */
	protected static function split_font_stack( string $stack ): array {
		$names = array_map( static function ( $name ) {
			return trim( $name, " \t\n\r\0\x0B\"'" );
		}, explode( ',', $stack ) );

		return array_values( array_filter( $names, 'strlen' ) );
	}

	/**
	 * The generic CSS family in a fallback stack, if any.
	 *
	 * @param string[] $stack Fallback names.
	 *
	 * @return string
	 */
	protected static function generic_family( array $stack ): string {
		foreach ( $stack as $name ) {
			$name = strtolower( $name );
			if ( in_array( $name, [ 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui' ], true ) ) {
				return $name;
			}
		}

		return '';
	}
}

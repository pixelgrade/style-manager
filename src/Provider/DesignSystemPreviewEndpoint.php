<?php
/**
 * REST facade for compact saved design-system previews.
 *
 * @since   2.4.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Utils\Fonts as FontsHelper;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Publishes a small, presentation-safe snapshot of the saved design system.
 *
 * @since 2.4.0
 */
final class DesignSystemPreviewEndpoint extends AbstractHookProvider {

	public const SCHEMA_VERSION = 1;
	public const REST_NAMESPACE = 'style_manager/v1';
	public const REST_PATH = '/design-system-preview';

	/**
	 * Canonical Style Manager font roles and representative preview sizes.
	 */
	private const TYPOGRAPHY_ROLES = [
		'primary'   => [ 'option' => 'sm_font_primary', 'size' => 64.0 ],
		'body'      => [ 'option' => 'sm_font_body', 'size' => 16.0 ],
		'secondary' => [ 'option' => 'sm_font_secondary', 'size' => 16.0 ],
	];

	/**
	 * Spacing options exposed through the compact preview contract.
	 */
	private const SPACING_METRICS = [
		'container' => [ 'option' => 'sm_site_container_width', 'suffix' => '' ],
		'inset'     => [ 'option' => 'sm_content_inset', 'suffix' => '' ],
		'rhythm'    => [ 'option' => 'sm_spacing_level', 'suffix' => '×' ],
	];

	/**
	 * Style Manager options.
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Style Manager fonts.
	 *
	 * @var Fonts
	 */
	private Fonts $fonts;

	/**
	 * Runtime palette payload reader.
	 *
	 * @var callable
	 */
	private $palette_payload;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 *
	 * @param Options       $options         Style Manager options.
	 * @param Fonts         $fonts           Style Manager fonts.
	 * @param callable|null $palette_payload Optional runtime palette payload reader.
	 */
	public function __construct( Options $options, Fonts $fonts, ?callable $palette_payload = null ) {
		$this->options = $options;
		$this->fonts = $fonts;
		$this->palette_payload = $palette_payload ?? static function(): array {
			return \function_exists( 'sm_get_palette_runtime_payload' )
				? (array) \sm_get_palette_runtime_payload()
				: [ 'palettes' => [] ];
		};
	}

	/**
	 * Registers hooks.
	 *
	 * @since 2.4.0
	 */
	public function register_hooks() {
		$this->add_action( 'rest_api_init', 'register_routes' );
	}

	/**
	 * Registers the saved design-system preview route.
	 *
	 * @since 2.4.0
	 */
	protected function register_routes(): void {
		\register_rest_route(
			self::REST_NAMESPACE,
			self::REST_PATH,
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_get' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}

	/**
	 * Checks the same capability required by Assistant's Design System tab.
	 *
	 * @since 2.4.0
	 */
	public function check_permissions(): bool {
		return \current_user_can( 'edit_theme_options' );
	}

	/**
	 * Returns the saved design-system preview response.
	 *
	 * @since 2.4.0
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_get() {
		return \rest_ensure_response( $this->get_payload() );
	}

	/**
	 * Builds the normalized preview contract.
	 *
	 * Each section is independent so one malformed subsystem never prevents the
	 * other cards from rendering.
	 *
	 * @since 2.4.0
	 */
	public function get_payload(): array {
		$details = $this->options->get_details_all();
		$details = \is_array( $details ) ? $details : [];

		$payload = [
			'schemaVersion' => self::SCHEMA_VERSION,
			'colors'        => $this->safely_build( fn() => $this->build_colors() ),
			'typography'    => $this->safely_build( fn() => $this->build_typography( $details ) ),
			'spacing'       => $this->safely_build( fn() => $this->build_spacing( $details ) ),
		];

		$encoded = \json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$payload['revision'] = \substr( \hash( 'sha256', false === $encoded ? '' : $encoded ), 0, 16 );

		return $payload;
	}

	/**
	 * Builds one section without allowing a malformed subsystem to break the route.
	 *
	 * @param callable $builder Section builder.
	 *
	 * @return array|null
	 */
	private function safely_build( callable $builder ): ?array {
		try {
			$result = $builder();

			return \is_array( $result ) && ! empty( $result ) ? $result : null;
		} catch ( \Throwable $exception ) {
			return null;
		}
	}

	/**
	 * Builds the representative palette section.
	 */
	private function build_colors(): ?array {
		$payload = ( $this->palette_payload )();
		$palettes = \is_array( $payload ) && isset( $payload['palettes'] ) ? (array) $payload['palettes'] : [];
		$palette = null;

		foreach ( $palettes as $candidate ) {
			$record = $this->record( $candidate );
			$id = isset( $record['id'] ) ? (string) $record['id'] : '';

			if ( '' !== $id && ! \str_starts_with( $id, '_' ) ) {
				$palette = $record;
				break;
			}
		}

		if ( null === $palette ) {
			return null;
		}

		$grade_colors = isset( $palette['colors'] ) ? (array) $palette['colors'] : [];
		$variation_records = isset( $palette['variations'] ) ? (array) $palette['variations'] : [];
		$variations = \array_map( [ $this, 'record' ], $variation_records );

		if ( 2 > \count( $grade_colors ) || \count( $variations ) < \count( $grade_colors ) ) {
			return null;
		}

		$stride = \max( 1, (int) \floor( \count( $variations ) / \count( $grade_colors ) ) );
		$source_index = isset( $palette['sourceIndex'] ) && \is_numeric( $palette['sourceIndex'] )
			? (int) $palette['sourceIndex']
			: 0;
		$source_grade = \min( \count( $grade_colors ) - 1, \max( 0, (int) \floor( $source_index / $stride ) ) );
		$sample_indexes = $this->representative_grade_indexes( \count( $grade_colors ), $source_grade );
		$samples = [];

		foreach ( $sample_indexes as $grade_index ) {
			$variation = $this->normalize_variation( $variations[ $grade_index * $stride ] ?? [] );

			if ( null === $variation ) {
				continue;
			}

			$is_source = $grade_index === $source_grade;
			$samples[] = [
				'id'        => 'grade-' . ( $grade_index + 1 ),
				'label'     => $this->grade_label( $grade_index, \count( $grade_colors ), $source_grade ),
				'position'  => \round( $grade_index / ( \count( $grade_colors ) - 1 ), 3 ),
				'isSource'  => $is_source,
				'surface'   => $variation['surface'],
				'text'      => $variation['text'],
				'mutedText' => $variation['mutedText'],
				'accent'    => $variation['accent'],
			];
		}

		if ( 2 > \count( $samples ) ) {
			return null;
		}

		$site_variation = $this->options->get( 'sm_site_color_variation', 1 );
		$site_variation = \is_numeric( $site_variation ) ? (int) $site_variation : 1;
		$site_variation = \min( \count( $variations ), \max( 1, $site_variation ) );
		$current = $this->normalize_variation( $variations[ $site_variation - 1 ] ?? [] );

		if ( null === $current ) {
			return null;
		}

		return [
			'palette' => [
				'id'    => (string) $palette['id'],
				'label' => $this->plain_text( $palette['label'] ?? '' ),
			],
			'current' => [
				'variation' => $site_variation,
				'surface'   => $current['surface'],
				'text'      => $current['text'],
				'mutedText' => $current['mutedText'],
				'accent'    => $current['accent'],
			],
			'samples' => $samples,
		];
	}

	/**
	 * Selects up to four grades while preserving the light, source, and deep anchors.
	 */
	private function representative_grade_indexes( int $grade_count, int $source_grade ): array {
		$last = $grade_count - 1;
		$indexes = [ 0, (int) \round( $source_grade / 2 ), $source_grade, $last ];
		$fillers = [
			(int) \round( ( $source_grade + $last ) / 2 ),
			(int) \round( $last / 3 ),
			(int) \round( 2 * $last / 3 ),
		];

		foreach ( $fillers as $filler ) {
			if ( 4 <= \count( \array_unique( $indexes ) ) ) {
				break;
			}

			$indexes[] = $filler;
		}

		$indexes = \array_values( \array_unique( \array_map(
			static fn( int $index ): int => \min( $last, \max( 0, $index ) ),
			$indexes
		) ) );
		\sort( $indexes, SORT_NUMERIC );

		return \array_slice( $indexes, 0, \min( 4, $grade_count ) );
	}

	/**
	 * Returns the translated representative grade label.
	 */
	private function grade_label( int $grade, int $grade_count, int $source_grade ): string {
		if ( $grade === $source_grade ) {
			return \__( 'Brand', '__plugin_txtd' );
		}

		if ( 0 === $grade ) {
			return \__( 'Light', '__plugin_txtd' );
		}

		if ( $grade === $grade_count - 1 ) {
			return \__( 'Deep', '__plugin_txtd' );
		}

		return $grade < $source_grade
			? \__( 'Soft', '__plugin_txtd' )
			: \__( 'Rich', '__plugin_txtd' );
	}

	/**
	 * Builds the canonical typography section.
	 */
	private function build_typography( array $details ): ?array {
		$roles = [];
		$role_labels = [
			'primary'   => \__( 'Primary', '__plugin_txtd' ),
			'body'      => \__( 'Body', '__plugin_txtd' ),
			'secondary' => \__( 'Secondary', '__plugin_txtd' ),
		];

		foreach ( self::TYPOGRAPHY_ROLES as $role_id => $config ) {
			$role = $this->build_typography_role(
				$role_id,
				$role_labels[ $role_id ],
				$config['option'],
				(float) $config['size'],
				$details
			);

			if ( null === $role ) {
				return null;
			}

			$roles[] = $role;
		}

		$stylesheet_urls = [];
		foreach ( $this->fonts->getFontsStylesheetUrls() as $url ) {
			$url = \trim( (string) $url );
			$scheme = (string) \wp_parse_url( $url, PHP_URL_SCHEME );

			if ( \str_starts_with( $url, '//' ) || \in_array( \strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
				$stylesheet_urls[] = $url;
			}
		}

		return [
			'roles'          => $roles,
			'stylesheetUrls' => \array_values( \array_unique( $stylesheet_urls ) ),
		];
	}

	/**
	 * Builds one canonical font role from its closest connected field.
	 */
	private function build_typography_role(
		string $role_id,
		string $label,
		string $master_id,
		float $target_size,
		array $details
	): ?array {
		if ( empty( $details[ $master_id ] ) || ! \is_array( $details[ $master_id ] ) ) {
			return null;
		}

		$master = $details[ $master_id ];
		$value = $this->font_value( $master['value'] ?? [], $master );
		$closest_distance = INF;

		foreach ( (array) ( $master['connected_fields'] ?? [] ) as $field_id ) {
			if ( empty( $details[ $field_id ] ) || ! \is_array( $details[ $field_id ] ) ) {
				continue;
			}

			$candidate = $this->font_value( $details[ $field_id ]['value'] ?? [], $details[ $field_id ] );
			$font_size = $candidate['font_size']['value'] ?? null;

			if ( ! \is_numeric( $font_size ) ) {
				continue;
			}

			$distance = \abs( (float) $font_size - $target_size );
			if ( $distance < $closest_distance ) {
				$closest_distance = $distance;
				$value = $candidate;
			}
		}

		$family = $this->plain_text( $value['font_family'] ?? '' );
		if ( '' === $family ) {
			return null;
		}

		$font_details = $this->fonts->getFontDetails( $family, $this->fonts->determineFontType( $family ) );
		$fallback = isset( $font_details['category'] ) ? (string) $font_details['category'] : 'sans-serif';
		if ( ! \in_array( $fallback, [ 'serif', 'sans-serif', 'monospace', 'cursive', 'fantasy', 'system-ui' ], true ) ) {
			$fallback = 'sans-serif';
		}

		$variant = \is_scalar( $value['font_variant'] ?? null ) ? (string) $value['font_variant'] : '';
		\preg_match( '/([1-9]00)/', $variant, $weight_match );
		$weight = isset( $weight_match[1] ) ? (int) $weight_match[1] : 400;
		$style = \str_contains( $variant, 'italic' ) ? 'italic' : ( \str_contains( $variant, 'oblique' ) ? 'oblique' : 'normal' );
		$text_transform = (string) ( $value['text_transform'] ?? 'none' );
		if ( ! \in_array( $text_transform, [ 'none', 'uppercase', 'lowercase', 'capitalize' ], true ) ) {
			$text_transform = 'none';
		}

		$letter_spacing = $this->format_font_measure( $value['letter_spacing'] ?? null );
		$line_height = isset( $value['line_height']['value'] ) && \is_numeric( $value['line_height']['value'] )
			? (float) $value['line_height']['value']
			: null;

		return [
			'id'             => $role_id,
			'label'          => $label,
			'family'         => $family,
			'fallback'       => $fallback,
			'weight'         => $weight,
			'style'          => $style,
			'letterSpacing'  => $letter_spacing,
			'textTransform'  => $text_transform,
			'lineHeight'     => $line_height,
		];
	}

	/**
	 * Builds the normalized spacing section.
	 */
	private function build_spacing( array $details ): ?array {
		$labels = [
			'container' => \__( 'Container', '__plugin_txtd' ),
			'inset'     => \__( 'Inset', '__plugin_txtd' ),
			'rhythm'    => \__( 'Rhythm', '__plugin_txtd' ),
		];
		$metrics = [];

		foreach ( self::SPACING_METRICS as $metric_id => $config ) {
			$option = $details[ $config['option'] ] ?? null;
			if ( ! \is_array( $option ) ) {
				return null;
			}

			$value = $option['value'] ?? $option['default'] ?? null;
			$minimum = $option['input_attrs']['min'] ?? null;
			$maximum = $option['input_attrs']['max'] ?? null;

			if ( ! \is_numeric( $value ) || ! \is_numeric( $minimum ) || ! \is_numeric( $maximum ) ) {
				return null;
			}

			$value = (float) $value;
			$minimum = (float) $minimum;
			$maximum = (float) $maximum;
			$normalized = $maximum > $minimum ? ( $value - $minimum ) / ( $maximum - $minimum ) : 0.0;
			$normalized = \min( 1.0, \max( 0.0, $normalized ) );

			$metrics[] = [
				'id'         => $metric_id,
				'label'      => $labels[ $metric_id ],
				'value'      => $value,
				'formatted'  => $this->format_number( $value ) . $config['suffix'],
				'normalized' => \round( $normalized, 3 ),
			];
		}

		return [ 'metrics' => $metrics ];
	}

	/**
	 * Normalizes a palette variation to the public color-role names.
	 */
	private function normalize_variation( array $variation ): ?array {
		$normalized = [
			'surface'   => $this->normalize_color( $variation['bg'] ?? '' ),
			'text'      => $this->normalize_color( $variation['fg1'] ?? '' ),
			'mutedText' => $this->normalize_color( $variation['fg2'] ?? '' ),
			'accent'    => $this->normalize_color( $variation['accent'] ?? '' ),
		];

		return \in_array( '', $normalized, true ) ? null : $normalized;
	}

	/**
	 * Normalizes a six-digit hexadecimal color.
	 */
	private function normalize_color( $value ): string {
		$value = \strtolower( \trim( (string) $value ) );

		return 1 === \preg_match( '/^#[0-9a-f]{6}$/', $value ) ? $value : '';
	}

	/**
	 * Normalizes an object-or-array record.
	 */
	private function record( $value ): array {
		return \is_object( $value ) || \is_array( $value ) ? (array) $value : [];
	}

	/**
	 * Normalizes a stored font value.
	 */
	private function font_value( $value, array $font_config = [] ): array {
		$standardized = $this->fonts->standardizeFontValue(
			FontsHelper::maybeDecodeValue( $value ),
			$font_config
		);

		if ( empty( $standardized ) && \is_string( $value ) ) {
			return $this->fonts->getFontDefaultsValue( \str_replace( '"', '', $value ) );
		}

		return $standardized;
	}

	/**
	 * Formats a numeric font measure and its safe CSS unit.
	 */
	private function format_font_measure( $measure ): ?string {
		if ( ! \is_array( $measure ) || ! isset( $measure['value'] ) || ! \is_numeric( $measure['value'] ) ) {
			return null;
		}

		$value = (float) $measure['value'];
		if ( 0.0 === $value ) {
			return '0';
		}

		$unit = isset( $measure['unit'] ) ? (string) $measure['unit'] : '';
		if ( ! \in_array( $unit, [ 'em', 'rem', 'px', '%' ], true ) ) {
			$unit = '';
		}

		return $this->format_number( $value ) . $unit;
	}

	/**
	 * Formats a contract number without insignificant trailing zeros.
	 */
	private function format_number( float $value ): string {
		return \rtrim( \rtrim( \number_format( $value, 3, '.', '' ), '0' ), '.' );
	}

	/**
	 * Produces a plain display string without exposing markup.
	 */
	private function plain_text( $value ): string {
		return \trim( \wp_strip_all_tags( (string) $value ) );
	}
}

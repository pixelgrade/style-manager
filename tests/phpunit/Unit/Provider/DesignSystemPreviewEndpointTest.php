<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class DesignSystemPreviewEndpointTest extends TestCase {
	public function setUp(): void {
		parent::setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ) => parse_url( $url, $component )
		);
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn( $text ): string => strip_tags( (string) $text )
		);
	}

	public function test_endpoint_requires_edit_theme_options_capability(): void {
		Functions\expect( 'current_user_can' )
			->twice()
			->with( 'edit_theme_options' )
			->andReturn( false, true );

		$endpoint = $this->create_endpoint();

		$this->assertFalse( $endpoint->check_permissions() );
		$this->assertTrue( $endpoint->check_permissions() );
	}

	public function test_payload_uses_representative_colors_canonical_font_roles_and_normalized_spacing(): void {
		$payload = $this->create_endpoint()->get_payload();

		$this->assertSame( 1, $payload['schemaVersion'] );
		$this->assertSame( '1', $payload['colors']['palette']['id'] );
		$this->assertSame( 'Brand Primary', $payload['colors']['palette']['label'] );
		$this->assertSame( [ 'Light', 'Soft', 'Brand', 'Deep' ], array_column( $payload['colors']['samples'], 'label' ) );
		$this->assertSame( [ '#f7f8f3', '#b2eca1', '#00825a', '#004e42' ], array_column( $payload['colors']['samples'], 'surface' ) );
		$this->assertSame( [ false, false, true, false ], array_column( $payload['colors']['samples'], 'isSource' ) );
		$this->assertSame( [ 'primary', 'body', 'secondary' ], array_column( $payload['typography']['roles'], 'id' ) );
		$this->assertSame( [ 'Primary', 'Body', 'Secondary' ], array_column( $payload['typography']['roles'], 'label' ) );
		$this->assertSame( [ 'Space Grotesk', 'Rubik', 'Space Mono' ], array_column( $payload['typography']['roles'], 'family' ) );
		$this->assertSame( [ 700, 400, 400 ], array_column( $payload['typography']['roles'], 'weight' ) );
		$this->assertSame( [ 0.25, 0.4, 0.5 ], array_column( $payload['spacing']['metrics'], 'normalized' ) );
		$this->assertSame( [ '70', '180', '1×' ], array_column( $payload['spacing']['metrics'], 'formatted' ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $payload['revision'] );
		$this->assertSame( $payload['revision'], $this->create_endpoint()->get_payload()['revision'] );
	}

	public function test_eight_grade_palette_keeps_light_source_deep_and_one_supporting_grade(): void {
		$palette = $this->palette_fixture( 8, 12 );
		$payload = $this->create_endpoint( null, [ 'palettes' => [ $palette ] ] )->get_payload();

		$this->assertSame( [ 'grade-1', 'grade-3', 'grade-5', 'grade-8' ], array_column( $payload['colors']['samples'], 'id' ) );
		$this->assertSame( [ 0.0, 0.286, 0.571, 1.0 ], array_column( $payload['colors']['samples'], 'position' ) );
		$this->assertSame( [ false, false, true, false ], array_column( $payload['colors']['samples'], 'isSource' ) );
	}

	public function test_current_color_uses_the_saved_site_variation(): void {
		$options = $this->options_mock( $this->option_details_fixture(), 4 );
		$payload = $this->create_endpoint( $options )->get_payload();

		$this->assertSame(
			[
				'variation' => 4,
				'surface'   => '#b2eca1',
				'text'      => '#0f261d',
				'mutedText' => '#173d2d',
				'accent'    => '#00825a',
			],
			$payload['colors']['current']
		);
	}

	public function test_typography_uses_the_connected_field_nearest_each_preview_size(): void {
		$details = $this->option_details_fixture();
		$details['display_font'] = $this->font_field( 'Display', 'Space Grotesk', 90, '600', -0.05, 0.95 );
		$details['heading_1_font'] = $this->font_field( 'Heading 1', 'Space Grotesk', 64.8, '700italic', -0.04, 1.01 );
		$details['sm_font_primary']['connected_fields'] = [ 'display_font', 'heading_1_font' ];

		$payload = $this->create_endpoint( $this->options_mock( $details ) )->get_payload();
		$primary = $payload['typography']['roles'][0];

		$this->assertSame( 700, $primary['weight'] );
		$this->assertSame( 'italic', $primary['style'] );
		$this->assertSame( '-0.04em', $primary['letterSpacing'] );
		$this->assertSame( 1.01, $primary['lineHeight'] );
	}

	public function test_typography_falls_back_to_the_master_font_when_no_connected_field_is_available(): void {
		$details = $this->option_details_fixture();
		$details['sm_font_secondary']['connected_fields'] = [ 'missing_font' ];
		$details['sm_font_secondary']['value'] = [
			'font_family'   => 'IBM Plex Mono',
			'font_variant'  => '500',
			'text_transform' => 'uppercase',
		];

		$payload   = $this->create_endpoint( $this->options_mock( $details ) )->get_payload();
		$secondary = $payload['typography']['roles'][2];

		$this->assertSame( 'IBM Plex Mono', $secondary['family'] );
		$this->assertSame( 500, $secondary['weight'] );
		$this->assertSame( 'uppercase', $secondary['textTransform'] );
	}

	public function test_typography_decodes_associative_and_legacy_positional_font_values(): void {
		$details = $this->option_details_fixture();
		$details['heading_1_font']['value'] = rawurlencode(
			json_encode(
				[
					'font-family'    => 'Encoded Display',
					'font-size'      => [ 'value' => 64, 'unit' => 'px' ],
					'font-weight'    => '800italic',
					'letter-spacing' => [ 'value' => -0.03, 'unit' => 'em' ],
					'line-height'    => [ 'value' => 0.98, 'unit' => false ],
				]
			)
		);
		$details['sm_font_body']['connected_fields'] = [];
		$details['sm_font_body']['value'] = rawurlencode( json_encode( [ 'Legacy Sans', '500' ] ) );

		$payload = $this->create_endpoint( $this->options_mock( $details ) )->get_payload();
		$primary = $payload['typography']['roles'][0];
		$body = $payload['typography']['roles'][1];

		$this->assertSame( 'Encoded Display', $primary['family'] );
		$this->assertSame( 800, $primary['weight'] );
		$this->assertSame( 'italic', $primary['style'] );
		$this->assertSame( 'Legacy Sans', $body['family'] );
		$this->assertSame( 500, $body['weight'] );
	}

	public function test_invalid_sections_are_null_without_discarding_valid_sections(): void {
		$details = $this->option_details_fixture();
		unset( $details['sm_font_secondary'], $details['sm_content_inset'] );

		$payload = $this->create_endpoint(
			$this->options_mock( $details ),
			[ 'palettes' => [ (object) [ 'id' => '_info' ] ] ]
		)->get_payload();

		$this->assertNull( $payload['colors'] );
		$this->assertNull( $payload['typography'] );
		$this->assertNull( $payload['spacing'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{16}$/', $payload['revision'] );
	}

	public function test_font_stylesheet_urls_are_limited_to_http_and_https(): void {
		$fonts = $this->fonts_mock(
			[
				'javascript:alert(1)',
				'data:text/css,body{}',
				'//pxgcdn.com/fonts/quentin/stylesheet.css',
				'https://fonts.googleapis.com/css2?family=Rubik&display=swap',
			]
		);

		$payload = $this->create_endpoint( null, null, $fonts )->get_payload();

		$this->assertSame(
			[
				'//pxgcdn.com/fonts/quentin/stylesheet.css',
				'https://fonts.googleapis.com/css2?family=Rubik&display=swap',
			],
			$payload['typography']['stylesheetUrls']
		);
	}

	private function create_endpoint(
		?Options $options = null,
		?array $palette_payload = null,
		?Fonts $fonts = null
	): DesignSystemPreviewEndpoint {
		$palette_payload = $palette_payload ?? [ 'palettes' => [ $this->palette_fixture() ] ];

		return new DesignSystemPreviewEndpoint(
			$options ?? $this->options_mock(),
			$fonts ?? $this->fonts_mock(),
			static fn(): array => $palette_payload
		);
	}

	private function options_mock( ?array $details = null, int $site_variation = 1 ): Options {
		$options = $this->createMock( Options::class );
		$options->method( 'get_details_all' )->willReturn( $details ?? $this->option_details_fixture() );
		$options->method( 'get' )->willReturnCallback(
			static function( string $id, $default = null ) use ( $site_variation ) {
				return 'sm_site_color_variation' === $id ? $site_variation : $default;
			}
		);

		return $options;
	}

	private function fonts_mock( ?array $stylesheet_urls = null ): Fonts {
		$fonts = $this->createMock( Fonts::class );
		$fonts->method( 'standardizeFontValue' )->willReturnCallback(
			static function( $value ): array {
				if ( is_object( $value ) ) {
					$value = (array) $value;
				}
				if ( is_string( $value ) ) {
					$value = [ $value ];
				}
				if ( ! is_array( $value ) ) {
					return [];
				}
				if ( array_is_list( $value ) ) {
					return array_filter(
						[
							'font_family'  => $value[0] ?? '',
							'font_variant' => $value[1] ?? '',
						]
					);
				}

				$standardized = [];
				foreach ( $value as $key => $entry ) {
					$standardized[ str_replace( '-', '_', (string) $key ) ] = $entry;
				}
				if ( isset( $standardized['font_weight'] ) && ! isset( $standardized['font_variant'] ) ) {
					$standardized['font_variant'] = $standardized['font_weight'];
				}

				return $standardized;
			}
		);
		$fonts->method( 'getFontDefaultsValue' )->willReturnCallback(
			static fn( string $family ): array => [ 'font_family' => $family ]
		);
		$fonts->method( 'getFontsStylesheetUrls' )->willReturn(
			$stylesheet_urls ?? [ 'https://fonts.googleapis.com/css2?family=Rubik&display=swap' ]
		);
		$fonts->method( 'determineFontType' )->willReturn( 'google_font' );
		$fonts->method( 'getFontDetails' )->willReturnCallback(
			static function( string $family ): array {
				return [
					'family'   => $family,
					'category' => str_contains( $family, 'Mono' ) ? 'monospace' : 'sans-serif',
				];
			}
		);

		return $fonts;
	}

	private function option_details_fixture(): array {
		return [
			'sm_font_primary' => [
				'type'             => 'font',
				'label'            => 'Font Primary',
				'value'            => [ 'font_family' => 'Space Grotesk' ],
				'connected_fields' => [ 'heading_1_font' ],
			],
			'sm_font_body' => [
				'type'             => 'font',
				'label'            => 'Font Body',
				'value'            => [ 'font_family' => 'Rubik' ],
				'connected_fields' => [ 'body_font' ],
			],
			'sm_font_secondary' => [
				'type'             => 'font',
				'label'            => 'Font Secondary',
				'value'            => [ 'font_family' => 'Space Mono' ],
				'connected_fields' => [ 'navigation_font', 'buttons_font' ],
			],
			'heading_1_font' => $this->font_field( 'Heading 1', 'Space Grotesk', 64.8, '700', -0.04, 1.01 ),
			'body_font'      => $this->font_field( 'Body', 'Rubik', 16.2, '400', 0, 1.68 ),
			'navigation_font' => $this->font_field( 'Navigation', 'Space Mono', 16.6, '400', 0.03, 1.49, 'uppercase' ),
			'buttons_font'    => $this->font_field( 'Buttons', 'Space Mono', 16.6, '400', 0.03, 1.49, 'uppercase' ),
			'sm_site_container_width' => $this->spacing_field( 'Site Container', 70, 60, 100, 1 ),
			'sm_content_inset'         => $this->spacing_field( 'Content Inset', 180, 100, 300, 10 ),
			'sm_spacing_level'         => $this->spacing_field( 'Spacing Level', 1, 0, 2, 0.1 ),
		];
	}

	private function font_field(
		string $label,
		string $family,
		float $size,
		string $variant,
		float $letter_spacing,
		float $line_height,
		string $text_transform = 'none'
	): array {
		return [
			'type'  => 'font',
			'label' => $label,
			'value' => [
				'font_family'    => $family,
				'font_size'      => [ 'value' => $size, 'unit' => false ],
				'font_variant'   => $variant,
				'letter_spacing' => [ 'value' => $letter_spacing, 'unit' => 'em' ],
				'text_transform' => $text_transform,
				'line_height'    => [ 'value' => $line_height, 'unit' => false ],
			],
		];
	}

	private function spacing_field( string $label, float $value, float $min, float $max, float $step ): array {
		return [
			'label'       => $label,
			'value'       => $value,
			'input_attrs' => [
				'min'  => $min,
				'max'  => $max,
				'step' => $step,
			],
		];
	}

	private function palette_fixture( int $grades = 4, int $source_index = 6 ): object {
		$surfaces = 4 === $grades
			? [ '#F7F8F3', '#B2ECA1', '#00825A', '#004E42' ]
			: [ '#ffffff', '#e8f7ef', '#b2eca1', '#63c99b', '#00825a', '#006d51', '#004e42', '#001c18' ];
		$variations = [];

		foreach ( $surfaces as $grade => $surface ) {
			for ( $offset = 0; $offset < 3; $offset++ ) {
				$is_dark = $grade >= (int) ceil( count( $surfaces ) / 2 );
				$variations[] = (object) [
					'bg'     => strtolower( $surface ),
					'accent' => $is_dark ? '#F7F8F3' : '#00825A',
					'fg1'    => $is_dark ? '#ffffff' : '#0f261d',
					'fg2'    => $is_dark ? '#ffffff' : '#173d2d',
				];
			}
		}

		return (object) [
			'id'          => 1,
			'label'       => 'Brand Primary',
			'sourceIndex' => $source_index,
			'colors'      => $surfaces,
			'variations'  => $variations,
		];
	}
}

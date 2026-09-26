<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\FontLibraryFonts;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * The core Font Library as a Style Manager font source (style-manager#57):
 * installed fonts are listed, a picked family loads exactly once (WordPress
 * prints the faces active in Global Styles, Style Manager prints only the
 * rest), and a site without Font Library fonts gets no new output.
 */
class FontLibraryFontsTest extends TestCase {

	private const UPLOADS = 'https://example.test/wp-content/uploads/fonts/';

	/**
	 * Google Fonts stay off during init (no catalog file in unit tests); a test
	 * that needs the Google options flips this after init.
	 *
	 * @var bool
	 */
	private bool $google_fonts_enabled = false;

	// -------------------------------------------------------------------------
	// Normalization.
	// -------------------------------------------------------------------------

	public function test_normalizes_a_library_family_into_a_style_manager_font(): void {
		$font = FontLibraryFonts::normalize_family(
			[ 'name' => 'Playfair Display', 'slug' => 'playfair-display', 'fontFamily' => '"Playfair Display", serif' ],
			[
				[ 'fontFamily' => '"Playfair Display"', 'fontStyle' => 'normal', 'fontWeight' => '400', 'src' => self::UPLOADS . 'pd-400.woff2' ],
				[ 'fontFamily' => '"Playfair Display"', 'fontStyle' => 'italic', 'fontWeight' => '700', 'src' => [ self::UPLOADS . 'pd-700i.woff2' ] ],
			]
		);

		$this->assertSame( 'Playfair Display', $font['family'] );
		$this->assertSame( 'Playfair Display', $font['family_display'] );
		$this->assertSame( 'serif', $font['category'] );
		$this->assertSame( 'serif', $font['fallback_stack'] );
		$this->assertSame( [ '400', '700italic' ], $font['variants'] );
		$this->assertSame( 'font_library', $font['source'] );
		$this->assertSame(
			[
				'fontFamily'  => 'Playfair Display',
				'fontStyle'   => 'normal',
				'fontWeight'  => '400',
				'src'         => [ self::UPLOADS . 'pd-400.woff2' ],
				'fontDisplay' => 'fallback',
			],
			$font['font_faces'][0]
		);
	}

	public function test_a_variable_weight_range_covers_every_hundred_in_the_range(): void {
		$font = FontLibraryFonts::normalize_family(
			[ 'name' => 'Inter', 'slug' => 'inter', 'fontFamily' => 'Inter, sans-serif' ],
			[ [ 'fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => '100 900', 'src' => self::UPLOADS . 'inter.woff2' ] ]
		);

		$this->assertSame( [ '100', '200', '300', '400', '500', '600', '700', '800', '900' ], $font['variants'] );
	}

	public function test_a_family_without_any_loadable_face_is_skipped(): void {
		$this->assertNull( FontLibraryFonts::normalize_family( [ 'name' => 'Ghost', 'fontFamily' => 'Ghost' ], [] ) );
		$this->assertNull( FontLibraryFonts::normalize_family( [ 'name' => 'Ghost', 'fontFamily' => 'Ghost' ], [ [ 'fontWeight' => '400', 'src' => [] ] ] ) );
	}

	// -------------------------------------------------------------------------
	// The provider lists installed fonts.
	// -------------------------------------------------------------------------

	public function test_provider_lists_the_installed_font_library_fonts(): void {
		$this->mock_installed_library( $this->inter_library() );

		$provider = new FontLibraryFonts( $this->createMock( Fonts::class ) );
		$fonts    = $provider->add_installed_fonts( [] );

		$this->assertSame( [ 'Inter' ], array_keys( $fonts ) );
		$this->assertSame( [ '400', '700' ], $fonts['Inter']['variants'] );
		$this->assertCount( 2, $fonts['Inter']['font_faces'] );
	}

	public function test_provider_adds_nothing_when_the_font_library_is_empty(): void {
		$this->mock_installed_library( [] );

		$provider = new FontLibraryFonts( $this->createMock( Fonts::class ) );

		$this->assertSame( [], $provider->add_installed_fonts( [] ) );
	}

	// -------------------------------------------------------------------------
	// Style Manager treats a library font as its own, local source.
	// -------------------------------------------------------------------------

	public function test_a_library_font_wins_over_a_same_named_font_from_another_source(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );

		$this->assertSame( 'font_library_font', $fonts->determineFontType( 'Inter' ) );
		$this->assertSame( 'Inter', $fonts->getFontDetails( 'Inter' )['family'] );
		$this->assertSame( [ 'Inter' ], array_keys( $fonts->get_font_library_fonts() ) );
	}

	public function test_a_picked_library_font_requests_no_remote_stylesheet(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );

		$this->assertSame( [], $fonts->getFontsStylesheetUrls() );

		$loader = $fonts->getFontFamiliesDetailsForWebfontloader();
		$this->assertSame( [ "'Inter:n4,n7'" ], array_values( $loader['custom_families'] ) );
		$this->assertSame( [], array_values( $loader['custom_srcs'] ) );
	}

	public function test_a_library_family_is_not_listed_again_among_google_fonts(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );

		$this->google_fonts_enabled = true;
		$google = new \ReflectionProperty( Fonts::class, 'google_fonts' );
		$google->setValue( $fonts, [
			'Inter'   => [ 'family' => 'Inter', 'category' => 'sans-serif' ],
			'Roboto'  => [ 'family' => 'Roboto', 'category' => 'sans-serif' ],
		] );
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();

		$options = $fonts->get_google_fonts_select_options();

		$this->assertStringContainsString( 'value="Roboto"', $options );
		$this->assertStringNotContainsString( 'value="Inter"', $options );
	}

	// -------------------------------------------------------------------------
	// @font-face output: one loader per face.
	// -------------------------------------------------------------------------

	public function test_prints_the_picked_family_faces_when_wordpress_prints_none(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );
		Functions\when( 'wp_get_global_settings' )->justReturn( [ 'theme' => [] ] );

		$faces = ( new FontLibraryFonts( $fonts ) )->get_font_faces_to_print();

		$this->assertSame( [ 'Inter' ], array_keys( $faces ) );
		$this->assertSame(
			[
				'font-family'  => 'Inter',
				'font-style'   => 'normal',
				'font-weight'  => '400',
				'src'          => [ self::UPLOADS . 'inter-400.woff2' ],
				'font-display' => 'fallback',
			],
			$faces['Inter'][0]
		);
		$this->assertCount( 2, $faces['Inter'] );
	}

	public function test_skips_every_face_wordpress_already_prints(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );
		// The user activated only the regular face in the Font Library.
		Functions\when( 'wp_get_global_settings' )->justReturn( [
			'custom' => [
				[
					'name'       => 'Inter',
					'slug'       => 'inter',
					'fontFamily' => 'Inter, sans-serif',
					'fontFace'   => [
						[ 'fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => 400, 'src' => [ self::UPLOADS . 'inter-400.woff2' ] ],
					],
				],
			],
		] );

		$faces = ( new FontLibraryFonts( $fonts ) )->get_font_faces_to_print();

		$this->assertCount( 1, $faces['Inter'] );
		$this->assertSame( '700', $faces['Inter'][0]['font-weight'] );
	}

	public function test_prints_nothing_when_every_face_is_already_printed_by_wordpress(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );
		Functions\when( 'wp_get_global_settings' )->justReturn( [
			'custom' => [
				[
					'fontFamily' => 'Inter, sans-serif',
					'fontFace'   => [
						[ 'fontFamily' => 'Inter', 'fontWeight' => '400', 'src' => 'a' ],
						[ 'fontFamily' => 'Inter', 'fontWeight' => '700', 'src' => 'b' ],
					],
				],
			],
		] );
		Functions\expect( 'wp_print_font_faces' )->never();

		$provider = new FontLibraryFonts( $fonts );
		$this->assertSame( [], $provider->get_font_faces_to_print() );
		$provider->print_font_faces();
	}

	public function test_an_installed_font_that_no_field_uses_is_not_printed(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'System Sans' ] ] );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$this->assertSame( [], ( new FontLibraryFonts( $fonts ) )->get_font_faces_to_print() );
	}

	public function test_an_untouched_site_gets_no_font_library_output(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'System Sans' ] ], [] );
		Functions\expect( 'wp_print_font_faces' )->never();
		Functions\expect( 'wp_add_inline_style' )->never();
		Functions\when( 'is_admin' )->justReturn( true );

		$provider = new FontLibraryFonts( $fonts );
		$provider->print_font_faces();
		$provider->enqueue_editor_font_faces();

		$this->assertSame( [], $fonts->get_font_library_fonts() );
		// Inter keeps resolving to the source it had before (no Font Library precedence).
		$this->assertSame( 'third_party_font', $fonts->determineFontType( 'Inter' ) );
	}

	public function test_prints_through_the_core_font_face_api(): void {
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );
		Functions\when( 'wp_get_global_settings' )->justReturn( [] );

		$printed = null;
		Functions\when( 'wp_print_font_faces' )->alias( static function ( $fonts ) use ( &$printed ) {
			$printed = $fonts;
		} );

		( new FontLibraryFonts( $fonts ) )->print_font_faces();

		$this->assertSame( [ 'Inter' ], array_keys( $printed ) );
		$this->assertSame( [ '400', '700' ], array_column( $printed['Inter'], 'font-weight' ) );
	}

	// -------------------------------------------------------------------------
	// Ownership gate.
	// -------------------------------------------------------------------------

	public function test_style_manager_owns_font_families_when_the_theme_declares_font_fields(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );

		$this->assertTrue( $fonts->owns_font_families() );
	}

	public function test_style_manager_does_not_own_font_families_without_theme_support(): void {
		Functions\when( 'current_theme_supports' )->justReturn( false );
		$fonts = $this->create_fonts( [ 'heading_font' => [ 'font_family' => 'Inter' ] ] );

		$this->assertFalse( $fonts->owns_font_families() );
	}

	public function test_style_manager_does_not_own_font_families_when_the_theme_declares_no_font_fields(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		$fonts = $this->create_fonts( [] );

		$this->assertFalse( $fonts->owns_font_families() );
	}

	// -------------------------------------------------------------------------
	// Helpers.
	// -------------------------------------------------------------------------

	private function inter_library(): array {
		return [
			'families' => [
				(object) [ 'ID' => 11, 'post_title' => 'Inter', 'post_name' => 'inter', 'post_content' => '{"fontFamily":"Inter, sans-serif"}' ],
			],
			'faces'    => [
				(object) [ 'ID' => 12, 'post_parent' => 11, 'post_content' => wp_json_encode_for_tests( [ 'fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => '400', 'src' => self::UPLOADS . 'inter-400.woff2' ] ) ],
				(object) [ 'ID' => 13, 'post_parent' => 11, 'post_content' => wp_json_encode_for_tests( [ 'fontFamily' => 'Inter', 'fontStyle' => 'normal', 'fontWeight' => '700', 'src' => self::UPLOADS . 'inter-700.woff2' ] ) ],
			],
		];
	}

	private function mock_installed_library( array $library ): void {
		Functions\when( 'post_type_exists' )->justReturn( true );
		Functions\when( 'wp_list_pluck' )->alias( static function ( array $list, string $field ): array {
			return array_map( static function ( $item ) use ( $field ) {
				return $item->$field;
			}, $list );
		} );
		Functions\when( 'get_posts' )->alias( static function ( array $args ) use ( $library ): array {
			return 'wp_font_family' === $args['post_type'] ? ( $library['families'] ?? [] ) : ( $library['faces'] ?? [] );
		} );
	}

	/**
	 * Build a Fonts service whose Font Library source is fed by the real
	 * provider over a mocked library, with a third-party font of the same name
	 * to prove precedence.
	 */
	private function create_fonts( array $values, ?array $library = null ): Fonts {
		$this->mock_installed_library( $library ?? $this->inter_library() );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnCallback( function ( string $key, $default = null ) {
			return 'typography_google_fonts' === $key ? $this->google_fonts_enabled : $default;
		} );

		$details = [];
		foreach ( array_keys( $values ) as $field_id ) {
			$details[ $field_id ] = [ 'type' => 'font' ];
		}

		$options = $this->createMock( Options::class );
		$options->method( 'get_details_all' )->willReturn( $details );
		$options->method( 'get' )->willReturnCallback( static function ( string $key, $default = null ) use ( $values ) {
			return $values[ $key ] ?? $default;
		} );

		$fonts    = new Fonts( $options, $plugin_settings, $this->createMock( LoggerInterface::class ) );
		$provider = new FontLibraryFonts( $fonts );

		$plugin = $this->createMock( PluginInterface::class );
		$plugin->method( 'get_url' )->willReturn( 'http://example.test/webfontloader.js' );
		$fonts->set_plugin( $plugin );

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'esc_js' )->returnArg();
		Functions\when( 'wp_parse_args' )->alias( static function ( array $args, array $defaults ): array {
			return array_merge( $defaults, $args );
		} );
		Functions\when( 'apply_filters' )->alias( static function ( string $tag, $value ) use ( $provider ) {
			switch ( $tag ) {
				case 'style_manager/font_library_fonts':
					return $provider->add_installed_fonts( $value );
				case 'style_manager/third_party_fonts':
					return [ 'Inter' => [ 'family' => 'Inter', 'src' => 'https://third-party.example.test/inter.css' ] ];
				case 'style_manager/cloud_fonts':
				case 'style_manager/font_categories':
					return [];
				case 'style_manager/theme_fonts':
					return [];
				case 'style_manager/system_fonts':
					return [ 'System Sans' => [ 'family' => 'System Sans' ] ];
				case 'style_manager/is_supported':
					return current_theme_supports( 'customizer_style_manager' );
			}

			return $value;
		} );

		PHPUnitUtil::getProtectedMethod( $fonts, 'init' )->invoke( $fonts );

		return $fonts;
	}
}

/**
 * JSON-encode without WordPress.
 */
function wp_json_encode_for_tests( array $data ): string {
	return (string) json_encode( $data, JSON_UNESCAPED_SLASHES );
}

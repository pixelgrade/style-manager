<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FontsUsedCloudFontFamiliesTest extends TestCase {
	public function test_returns_deduped_cloud_font_families_with_a_stylesheet_src(): void {
		$this->mock_font_filters();

		$values = [
			'heading_font' => [ 'font_family' => 'Cloud Sans' ],
			'body_font'    => [ 'font_family' => 'Cloud Sans' ], // Same family, must be deduped.
			'accent_font'  => [ 'font_family' => 'Theme Sans' ], // Theme font, not a cloud font.
			'quote_font'   => [ 'font_family' => 'System Sans' ], // System font, not a cloud font.
		];

		$fonts = $this->create_fonts_with_used_fields( $values, [ 'heading_font', 'body_font', 'accent_font', 'quote_font' ] );

		$this->assertSame( [ 'Cloud Sans' ], $fonts->get_used_cloud_font_families() );
	}

	public function test_cloud_font_without_a_stylesheet_src_is_excluded(): void {
		$this->mock_font_filters( [
			'Cloud Sans'    => [ 'family' => 'Cloud Sans', 'src' => 'https://cloud.example.test/cloud-sans/stylesheet.css' ],
			'No Src Cloud'  => [ 'family' => 'No Src Cloud' ],
		] );

		$values = [
			'heading_font' => [ 'font_family' => 'Cloud Sans' ],
			'body_font'    => [ 'font_family' => 'No Src Cloud' ],
		];

		$fonts = $this->create_fonts_with_used_fields( $values, [ 'heading_font', 'body_font' ] );

		$this->assertSame( [ 'Cloud Sans' ], $fonts->get_used_cloud_font_families() );
	}

	public function test_does_not_fatal_and_returns_empty_array_when_no_font_values_are_saved(): void {
		$this->mock_font_filters();

		$fonts = $this->create_fonts_with_used_fields( [], [] );

		$this->assertSame( [], $fonts->get_used_cloud_font_families() );
	}

	public function test_does_not_fatal_and_returns_empty_array_when_there_are_no_font_fields_at_all(): void {
		$this->mock_font_filters();

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnCallback( [ $this, 'default_settings_with_google_fonts_disabled' ] );

		$options = $this->createMock( Options::class );
		$options->method( 'get_details_all' )->willReturn( [] );

		$fonts = new Fonts( $options, $plugin_settings, $this->createMock( LoggerInterface::class ) );
		$this->set_up_plugin( $fonts );

		PHPUnitUtil::getProtectedMethod( $fonts, 'init' )->invoke( $fonts );

		$this->assertSame( [], $fonts->get_used_cloud_font_families() );
	}

	/**
	 * @param array<string, array> $values      Font field id => raw font value (e.g. [ 'font_family' => '...' ]).
	 * @param string[]             $field_ids   Font field ids to expose via Options::get_details_all().
	 */
	private function create_fonts_with_used_fields( array $values, array $field_ids ): Fonts {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnCallback( [ $this, 'default_settings_with_google_fonts_disabled' ] );

		$details = [];
		foreach ( $field_ids as $field_id ) {
			$details[ $field_id ] = [ 'type' => 'font' ];
		}

		$options = $this->createMock( Options::class );
		$options->method( 'get_details_all' )->willReturn( $details );
		$options->method( 'get' )->willReturnCallback(
			static function ( string $key, $default = null ) use ( $values ) {
				return $values[ $key ] ?? $default;
			}
		);

		$fonts = new Fonts( $options, $plugin_settings, $this->createMock( LoggerInterface::class ) );
		$this->set_up_plugin( $fonts );

		PHPUnitUtil::getProtectedMethod( $fonts, 'init' )->invoke( $fonts );

		return $fonts;
	}

	/**
	 * Every setting falls back to its default, except Google Fonts which is
	 * disabled -- avoids touching the filesystem via the mocked plugin's
	 * (null) get_path() when maybe_load_google_fonts() is not what's under test.
	 *
	 * @param mixed ...$args ( $key, $default = null ), passed as an array so this
	 *                       can be used directly as a PHPUnit willReturnCallback.
	 *
	 * @return mixed
	 */
	public function default_settings_with_google_fonts_disabled( string $key, $default = null ) {
		if ( 'typography_google_fonts' === $key ) {
			return false;
		}

		return $default;
	}

	private function set_up_plugin( Fonts $fonts ): void {
		$plugin = $this->createMock( PluginInterface::class );
		$plugin->method( 'get_url' )->willReturn( 'http://example.test/webfontloader.js' );
		$fonts->set_plugin( $plugin );
	}

	private function mock_font_filters( ?array $cloud_fonts = null ): void {
		if ( null === $cloud_fonts ) {
			$cloud_fonts = [
				'Cloud Sans' => [ 'family' => 'Cloud Sans', 'src' => 'https://cloud.example.test/cloud-sans/stylesheet.css' ],
			];
		}

		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( array $args, array $defaults ): array {
				return array_merge( $defaults, $args );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) use ( $cloud_fonts ) {
				switch ( $tag ) {
					case 'style_manager/font_categories':
						return [];
					case 'style_manager/third_party_fonts':
						return [];
					case 'style_manager/cloud_fonts':
						return $cloud_fonts;
					case 'style_manager/theme_fonts':
						return [ 'Theme Sans' => [ 'family' => 'Theme Sans' ] ];
					case 'customify_theme_fonts':
						return $value;
					case 'style_manager/system_fonts':
						return [ 'System Sans' => [ 'family' => 'System Sans' ] ];
					case 'style_manager/standardized_fonts_list':
						return $value;
				}

				return $value;
			}
		);
	}
}

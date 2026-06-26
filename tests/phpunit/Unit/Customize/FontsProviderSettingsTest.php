<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FontsProviderSettingsTest extends TestCase {
	public function test_disabled_font_sources_are_not_loaded_during_init(): void {
		$this->mock_font_filters();

		$fonts = $this->create_fonts( [
			'typography_cloud_fonts'  => false,
			'typography_system_fonts' => false,
			'typography_google_fonts' => false,
		] );

		PHPUnitUtil::getProtectedMethod( $fonts, 'init' )->invoke( $fonts );

		$this->assertArrayHasKey( 'Third Sans', $fonts->get_third_party_fonts() );
		$this->assertArrayHasKey( 'Theme Sans', $fonts->get_theme_fonts() );
		$this->assertSame( [], $fonts->get_cloud_fonts() );
		$this->assertSame( [], $fonts->get_system_fonts() );
		$this->assertSame( [], $fonts->get_google_fonts() );
	}

	public function test_disabling_typography_disables_webfont_loader_script(): void {
		$fonts = $this->create_fonts( [ 'enable_typography' => false ] );

		$this->assertSame( '', $fonts->get_webfontloader_dynamic_script() );
	}

	private function create_fonts( array $settings ): Fonts {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings
			->method( 'get' )
			->willReturnCallback(
				static function ( string $key, $default = null ) use ( $settings ) {
					return $settings[ $key ] ?? $default;
				}
			);

		$fonts = new Fonts(
			$this->createMock( Options::class ),
			$plugin_settings,
			$this->createMock( LoggerInterface::class )
		);

		$plugin = $this->createMock( PluginInterface::class );
		$plugin
			->method( 'get_url' )
			->willReturn( 'http://example.test/webfontloader.js' );
		$fonts->set_plugin( $plugin );

		return $fonts;
	}

	private function mock_font_filters(): void {
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\when( 'wp_register_script' )->justReturn( true );
		Functions\when( 'wp_parse_args' )->alias(
			static function ( array $args, array $defaults ): array {
				return array_merge( $defaults, $args );
			}
		);
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				switch ( $tag ) {
					case 'style_manager/font_categories':
						return [];
					case 'style_manager/third_party_fonts':
						return [ 'Third Sans' => [ 'family' => 'Third Sans' ] ];
					case 'style_manager/cloud_fonts':
						return [ 'Cloud Sans' => [ 'family' => 'Cloud Sans' ] ];
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

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Pixelgrade\StyleManager\Customize\CloudFonts;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class CloudFontsLocalSrcTest extends TestCase {

	/*
	 * preprocess_font_config() src swap.
	 */

	public function test_preprocess_font_config_swaps_src_when_local_hosting_enabled_and_store_healthy(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store
			->expects( $this->once() )
			->method( 'get_local_src' )
			->with( 'Trueno' )
			->willReturn( 'https://example.test/wp-content/uploads/style-manager/fonts/trueno/stylesheet.css' );

		$cloud_fonts = $this->create_cloud_fonts( [ 'typography_host_cloud_fonts_locally' => 'yes' ], $local_font_store );

		$result = $this->invoke_preprocess_font_config( $cloud_fonts, [
			'font_family' => 'Trueno',
			'stylesheet'  => '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
			'variants'    => [ '400' ],
			'category'    => 'sans-serif',
		] );

		$this->assertSame(
			'https://example.test/wp-content/uploads/style-manager/fonts/trueno/stylesheet.css',
			$result['src']
		);
		$this->assertSame(
			'//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
			$result['remote_src']
		);
	}

	public function test_preprocess_font_config_keeps_remote_src_when_setting_disabled(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store
			->expects( $this->never() )
			->method( 'get_local_src' );

		$cloud_fonts = $this->create_cloud_fonts( [ 'typography_host_cloud_fonts_locally' => '' ], $local_font_store );

		$result = $this->invoke_preprocess_font_config( $cloud_fonts, [
			'font_family' => 'Trueno',
			'stylesheet'  => '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
		] );

		$this->assertSame(
			'//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
			$result['src']
		);
		$this->assertArrayNotHasKey( 'remote_src', $result );
	}

	public function test_preprocess_font_config_keeps_remote_src_when_store_unhealthy(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store
			->expects( $this->once() )
			->method( 'get_local_src' )
			->with( 'Trueno' )
			->willReturn( null );

		$cloud_fonts = $this->create_cloud_fonts( [ 'typography_host_cloud_fonts_locally' => 'yes' ], $local_font_store );

		$result = $this->invoke_preprocess_font_config( $cloud_fonts, [
			'font_family' => 'Trueno',
			'stylesheet'  => '//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
		] );

		$this->assertSame(
			'//cloud.pixelgrade.com/wp-content/uploads/cloud-fonts-v2/trueno/stylesheet.css',
			$result['src']
		);
		$this->assertArrayNotHasKey( 'remote_src', $result );
	}

	/*
	 * add_mirrored_fonts_missing_from_cloud() delisted-font persistence.
	 */

	public function test_add_mirrored_fonts_missing_from_cloud_readds_delisted_healthy_font(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store
			->method( 'get_manifest' )
			->willReturn( [
				'Delisted Font' => [
					'status'         => 'ok',
					'family_display' => 'Delisted Font',
					'variants'       => [ '400', '700' ],
					'category'       => 'sans-serif',
					'fallback_stack' => 'sans-serif',
					'stylesheet_path' => 'style-manager/fonts/delisted-font/stylesheet.css',
				],
			] );
		$local_font_store
			->method( 'is_healthy' )
			->with( 'Delisted Font' )
			->willReturn( true );
		$local_font_store
			->method( 'get_local_src' )
			->with( 'Delisted Font' )
			->willReturn( 'https://example.test/wp-content/uploads/style-manager/fonts/delisted-font/stylesheet.css' );

		$cloud_fonts = $this->create_cloud_fonts( [ 'typography_host_cloud_fonts_locally' => 'yes' ], $local_font_store );

		$result = $this->invoke_add_mirrored_fonts_missing_from_cloud( $cloud_fonts, [
			'Still Present Font' => [ 'family' => 'Still Present Font' ],
		] );

		$this->assertArrayHasKey( 'Still Present Font', $result );
		$this->assertArrayHasKey( 'Delisted Font', $result );
		$this->assertSame(
			'https://example.test/wp-content/uploads/style-manager/fonts/delisted-font/stylesheet.css',
			$result['Delisted Font']['src']
		);
		$this->assertSame( 'Delisted Font', $result['Delisted Font']['family'] );
		$this->assertSame( [ '400', '700' ], $result['Delisted Font']['variants'] );
	}

	public function test_add_mirrored_fonts_missing_from_cloud_does_not_readd_delisted_unhealthy_font(): void {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store
			->method( 'get_manifest' )
			->willReturn( [
				'Unhealthy Font' => [
					'status'         => 'failed',
					'family_display' => 'Unhealthy Font',
					'variants'       => [ '400' ],
					'category'       => 'sans-serif',
					'fallback_stack' => 'sans-serif',
					'stylesheet_path' => 'style-manager/fonts/unhealthy-font/stylesheet.css',
				],
			] );
		$local_font_store
			->method( 'is_healthy' )
			->with( 'Unhealthy Font' )
			->willReturn( false );
		$local_font_store
			->expects( $this->never() )
			->method( 'get_local_src' );

		$cloud_fonts = $this->create_cloud_fonts( [ 'typography_host_cloud_fonts_locally' => 'yes' ], $local_font_store );

		$result = $this->invoke_add_mirrored_fonts_missing_from_cloud( $cloud_fonts, [] );

		$this->assertArrayNotHasKey( 'Unhealthy Font', $result );
	}

	/*
	 * Helpers.
	 */

	private function create_cloud_fonts( array $settings, LocalFontStore $local_font_store ): CloudFonts {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings
			->method( 'get' )
			->willReturnCallback(
				static function ( string $key, $default = null ) use ( $settings ) {
					return $settings[ $key ] ?? $default;
				}
			);

		return new CloudFonts(
			$this->createMock( DesignAssets::class ),
			$local_font_store,
			$plugin_settings,
			$this->createMock( LoggerInterface::class )
		);
	}

	private function invoke_preprocess_font_config( CloudFonts $cloud_fonts, array $font_config ): array {
		return PHPUnitUtil::getProtectedMethod( $cloud_fonts, 'preprocess_font_config' )
			->invoke( $cloud_fonts, $font_config );
	}

	private function invoke_add_mirrored_fonts_missing_from_cloud( CloudFonts $cloud_fonts, array $fonts ): array {
		return PHPUnitUtil::getProtectedMethod( $cloud_fonts, 'add_mirrored_fonts_missing_from_cloud' )
			->invoke( $cloud_fonts, $fonts );
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Pixelgrade\StyleManager\Lab\Config;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class ConfigTest extends TestCase {
	public function test_admin_config_returns_live_bootstrap_payload(): void {
		$config = ( new Config(
			static function (): array {
				return [
					'palettes' => [
						(object) [
							'id'    => 'brand',
							'label' => 'Brand',
						],
					],
				];
			},
			static function ( string $option, $default = null ) {
				if ( 'sm_color_palette_in_use' === $option ) {
					return 'brand';
				}

				if ( 'sm_site_color_variation' === $option ) {
					return 6;
				}

				return $default;
			},
			static function ( string $option, $default = false ) {
				if ( 'sm_dark_mode_advanced' === $option ) {
					return 'on';
				}

				return $default;
			},
			static function ( string $path = '' ): string {
				return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
			},
			static function ( string $path = '/' ): string {
				return 'https://example.test/';
			},
			static function ( string $action ): string {
				return $action . '-nonce';
			}
		) )->admin_config();

		$this->assertSame( 'https://example.test/wp-admin/tools.php?page=sm-lab', $config['adminUrl'] );
		$this->assertSame( 'https://example.test/', $config['showcaseBaseUrl'] );
		$this->assertSame( 'sm_lab_showcase-nonce', $config['showcaseNonce'] );
		$this->assertSame( 'https://example.test/wp-admin/admin-ajax.php', $config['ajaxUrl'] );
		$this->assertSame( 'sm_lab_refresh-nonce', $config['ajaxNonce'] );
		$this->assertSame( 6, $config['siteVariation'] );
		$this->assertCount( 1, $config['palettes'] );
		$this->assertSame( 'brand', $config['palettes'][0]->id );
		$this->assertSame( 'brand', $config['defaultState']['palette'] );
		$this->assertSame( 6, $config['defaultState']['variation'] );
		$this->assertTrue( $config['defaultState']['dark'] );
	}

	public function test_color_system_preview_config_returns_palette_payload_and_strings(): void {
		$params = \Pixelgrade\StyleManager\Lab\QueryParams::from_array( [
			'dark' => '1',
		] );

		$config = ( new Config(
			static function (): array {
				return [
					'palettes' => [
						(object) [
							'id'    => 'brand',
							'label' => 'Brand',
						],
					],
				];
			},
			static function ( string $option, $default = null ) {
				if ( 'sm_site_color_variation' === $option ) {
					return 4;
				}

				return $default;
			}
		) )->color_system_preview_config( $params );

		$this->assertSame( 4, $config['siteVariation'] );
		$this->assertTrue( $config['isDark'] );
		$this->assertCount( 1, $config['palettes'] );
		$this->assertSame( 'brand', $config['palettes'][0]->id );
		$this->assertSame( 'The color system', $config['strings']['palettePreviewTitle'] );
		$this->assertSame( 'Surface', $config['strings']['palettePreviewSwatchSurfaceText'] );
		$this->assertSame( 'Accent', $config['strings']['palettePreviewSwatchAccentText'] );
		$this->assertSame( 'Text', $config['strings']['palettePreviewSwatchForegroundText'] );
	}
}

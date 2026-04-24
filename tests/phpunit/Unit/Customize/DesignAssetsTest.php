<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Client\CloudInterface;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class DesignAssetsTest extends TestCase {
	public function test_get_preserves_cached_font_palettes_when_fresh_data_omits_them(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$cloud_client = $this->createMock( CloudInterface::class );
		$cloud_client
			->expects( $this->once() )
			->method( 'fetch_design_assets' )
			->willReturn( [
				'cloud_fonts' => [
					'trueno' => [
						'font_family' => 'Trueno',
					],
				],
				'system_fonts' => [],
				'font_categories' => [],
				'theme_configs' => [],
				'version' => [
					'cloud_version' => '1.5.3',
				],
			] );

		Functions\when( 'get_option' )->alias( static function( string $option_name ) {
			if ( DesignAssets::CACHE_KEY === $option_name ) {
				return [
					'font_palettes' => [
						'gmvw9d' => [
							'label' => 'Smith',
						],
					],
					'version' => [
						'cloud_version' => '1.5.2',
					],
				];
			}

			return false;
		} );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$rest ) {
			return $value;
		} );

		$design_assets = new DesignAssets( $cloud_client, $this->createMock( LoggerInterface::class ) );

		$result = $design_assets->get( true );

		$this->assertSame(
			[
				'gmvw9d' => [
					'label' => 'Smith',
				],
			],
			$result['font_palettes']
		);
		$this->assertSame( 'Trueno', $result['cloud_fonts']['trueno']['font_family'] );
	}
}

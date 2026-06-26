<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Client\CloudInterface;
use Pixelgrade\StyleManager\Customize\DesignAssets;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class DesignAssetsTest extends TestCase {
	public function test_get_does_not_fetch_remote_assets_on_frontend_requests(): void {
		$cached_assets = [
			'cloud_fonts' => [
				'cached' => [
					'font_family' => 'Cached Font',
				],
			],
		];

		$cloud_client = $this->createMock( CloudInterface::class );
		$cloud_client
			->expects( $this->never() )
			->method( 'fetch_design_assets' );

		Functions\when( 'get_option' )->alias( static function( string $option_name ) use ( $cached_assets ) {
			return DesignAssets::CACHE_KEY === $option_name ? $cached_assets : false;
		} );
		Functions\when( 'is_admin' )->justReturn( false );
		$this->mock_passthrough_filters();

		$design_assets = new DesignAssets( $cloud_client, $this->createMock( LoggerInterface::class ) );

		$this->assertSame( $cached_assets, $design_assets->get( true ) );
	}

	public function test_failed_fetch_without_previous_cache_sets_short_backoff_and_returns_empty_assets(): void {
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$cloud_client = $this->createMock( CloudInterface::class );
		$cloud_client
			->expects( $this->once() )
			->method( 'fetch_design_assets' )
			->willReturn( null );

		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		$this->mock_passthrough_filters();

		$updates = [];
		Functions\when( 'update_option' )->alias( static function( string $option_name, $value ) use ( &$updates ) {
			$updates[ $option_name ] = $value;

			return true;
		} );

		$design_assets = new DesignAssets( $cloud_client, $this->createMock( LoggerInterface::class ) );

		$this->assertSame( [], $design_assets->get() );
		$this->assertSame( [], $updates[ DesignAssets::CACHE_KEY ] ?? null );
		$this->assertArrayHasKey( DesignAssets::CACHE_TIMESTAMP_KEY, $updates );
		$this->assertGreaterThan( time(), $updates[ DesignAssets::CACHE_TIMESTAMP_KEY ] );
		$this->assertLessThanOrEqual( time() + 5 * MINUTE_IN_SECONDS, $updates[ DesignAssets::CACHE_TIMESTAMP_KEY ] );
	}

	public function test_failed_refresh_preserves_previous_assets_and_only_backs_off_timestamp(): void {
		if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
			define( 'MINUTE_IN_SECONDS', 60 );
		}

		$previous_assets = [
			'cloud_fonts' => [
				'cached' => [
					'font_family' => 'Cached Font',
				],
			],
		];

		$cloud_client = $this->createMock( CloudInterface::class );
		$cloud_client
			->expects( $this->once() )
			->method( 'fetch_design_assets' )
			->willReturn( null );

		Functions\when( 'get_option' )->alias( static function( string $option_name ) use ( $previous_assets ) {
			return DesignAssets::CACHE_KEY === $option_name ? $previous_assets : false;
		} );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		$this->mock_passthrough_filters();

		$updates = [];
		Functions\when( 'update_option' )->alias( static function( string $option_name, $value ) use ( &$updates ) {
			$updates[ $option_name ] = $value;

			return true;
		} );

		$design_assets = new DesignAssets( $cloud_client, $this->createMock( LoggerInterface::class ) );

		$this->assertSame( $previous_assets, $design_assets->get( true ) );
		$this->assertArrayNotHasKey( DesignAssets::CACHE_KEY, $updates );
		$this->assertArrayHasKey( DesignAssets::CACHE_TIMESTAMP_KEY, $updates );
	}

	public function test_successful_changed_fetch_caches_assets_and_invalidates_options_caches(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$previous_assets = [
			'version' => [
				'cloud_version' => '1.5.2',
			],
		];
		$fetched_assets  = [
			'version' => [
				'cloud_version' => '1.5.3',
			],
		];

		$cloud_client = $this->createMock( CloudInterface::class );
		$cloud_client
			->expects( $this->once() )
			->method( 'fetch_design_assets' )
			->willReturn( $fetched_assets );

		Functions\when( 'get_option' )->alias( static function( string $option_name ) use ( $previous_assets ) {
			return DesignAssets::CACHE_KEY === $option_name ? $previous_assets : false;
		} );
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		$this->mock_passthrough_filters();

		$updates = [];
		Functions\when( 'update_option' )->alias( static function( string $option_name, $value ) use ( &$updates ) {
			$updates[ $option_name ] = $value;

			return true;
		} );

		$design_assets = new DesignAssets( $cloud_client, $this->createMock( LoggerInterface::class ) );

		$this->assertSame( $fetched_assets, $design_assets->get( true ) );
		$this->assertSame( $fetched_assets, $updates[ DesignAssets::CACHE_KEY ] ?? null );
		$this->assertArrayHasKey( DesignAssets::CACHE_TIMESTAMP_KEY, $updates );
		$this->assertArrayHasKey( Options::CUSTOMIZER_CONFIG_CACHE_TIMESTAMP_KEY, $updates );
		$this->assertArrayHasKey( Options::DETAILS_CACHE_TIMESTAMP_KEY, $updates );
	}

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

	public function test_theme_root_config_replaces_cloud_theme_configs_when_valid(): void {
		$theme_dir = sys_get_temp_dir() . '/sm-theme-root-' . str_replace( '.', '-', uniqid( '', true ) );
		mkdir( $theme_dir );
		file_put_contents(
			$theme_dir . '/sm_theme_root.php',
			'<?php $config = ["sections" => ["style_manager_section" => ["options" => ["from_theme_root" => ["type" => "text"]]]]];'
		);

		$theme = new class( $theme_dir ) {
			private string $theme_dir;

			public function __construct( string $theme_dir ) {
				$this->theme_dir = $theme_dir;
			}

			public function exists(): bool {
				return true;
			}

			public function get_template_directory(): string {
				return $this->theme_dir;
			}

			public function get( string $key ): string {
				return [
					'Name'       => 'Theme Root Test',
					'TextDomain' => 'theme-root-test',
				][ $key ] ?? '';
			}

			public function get_stylesheet(): string {
				return 'theme-root-test';
			}
		};

		Functions\when( 'get_template' )->justReturn( 'theme-root-test' );
		Functions\when( 'wp_get_theme' )->alias( static fn(): object => $theme );
		Functions\when( 'trailingslashit' )->alias( static fn( string $path ): string => rtrim( $path, '/\\' ) . '/' );

		try {
			$result = ( new TestDesignAssets(
				$this->createMock( CloudInterface::class ),
				$this->createMock( LoggerInterface::class )
			) )->expose_maybe_load_theme_config_from_theme_root( [
				'theme_configs' => [
					'cloud' => [
						'name' => 'Cloud Config',
					],
				],
			] );
		} finally {
			unlink( $theme_dir . '/sm_theme_root.php' );
			rmdir( $theme_dir );
		}

		$this->assertSame( [ 'theme_root' ], array_keys( $result['theme_configs'] ) );
		$this->assertSame( 'Theme Root Test', $result['theme_configs']['theme_root']['name'] );
		$this->assertSame( 'theme-root-test', $result['theme_configs']['theme_root']['slug'] );
		$this->assertSame( 'theme-root-test', $result['theme_configs']['theme_root']['txtd'] );
		$this->assertSame(
			[ 'type' => 'text' ],
			$result['theme_configs']['theme_root']['config']['sections']['style_manager_section']['options']['from_theme_root']
		);
	}

	private function mock_passthrough_filters(): void {
		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value ) {
			return $value;
		} );
	}
}

class TestDesignAssets extends DesignAssets {
	public function expose_maybe_load_theme_config_from_theme_root( array $design_assets ): array {
		return $this->maybe_load_theme_config_from_theme_root( $design_assets );
	}
}

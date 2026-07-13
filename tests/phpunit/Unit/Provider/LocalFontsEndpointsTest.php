<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_REST_Request', false ) ) {
		class WP_REST_Request {
			private array $params;

			public function __construct( array $params = [] ) {
				$this->params = $params;
			}

			public function get_param( string $param ) {
				return $this->params[ $param ] ?? null;
			}
		}
	}

	if ( ! class_exists( 'WP_REST_Server', false ) ) {
		class WP_REST_Server {
			const READABLE = 'GET';
			const CREATABLE = 'POST';
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Customize\LocalFontStore;
	use Pixelgrade\StyleManager\Provider\LocalFonts;
	use Pixelgrade\StyleManager\Provider\LocalFontsEndpoints;
	use Pixelgrade\StyleManager\Provider\PluginSettings;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

	class LocalFontsEndpointsTest extends TestCase {

		public function setUp(): void {
			parent::setUp();

			Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path );
			Functions\when( 'is_network_admin' )->justReturn( false );
			Functions\when( 'rest_ensure_response' )->alias( static fn( $response ) => $response );
			Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
			Functions\when( 'current_theme_supports' )->justReturn( true );
		}

		public function test_permission_callback_requires_manage_options(): void {
			Functions\expect( 'current_user_can' )
				->twice()
				->with( 'manage_options' )
				->andReturn( false, true );

			$endpoints = $this->create_endpoints();

			$this->assertFalse( $endpoints->check_permissions() );
			$this->assertTrue( $endpoints->check_permissions() );
		}

		public function test_get_payload_unions_manifest_and_used_families_and_counts_unhealthy_used(): void {
			$manifest = [
				'Uncut Sans' => [
					'status'         => 'ok',
					'family_display' => 'Uncut Sans',
				],
				'Broken Font' => [
					'status'         => 'failed',
					'family_display' => 'Broken Font',
				],
				'Unused Font' => [
					'status'         => 'ok',
					'family_display' => 'Unused Font',
				],
			];

			$local_font_store = $this->createMock( LocalFontStore::class );
			$local_font_store->method( 'get_manifest' )->willReturn( $manifest );
			$local_font_store->method( 'is_healthy' )->willReturnMap(
				[
					[ 'Uncut Sans', true ],
					[ 'Broken Font', false ],
					[ 'Unused Font', true ],
				]
			);

			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn(
				[ 'Uncut Sans', 'Broken Font', 'Never Mirrored Font' ]
			);

			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->method( 'get' )->willReturnMap(
				[
					[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
					[ 'typography_cloud_fonts', 'yes', 'yes' ],
				]
			);

			$endpoints = $this->create_endpoints( $local_font_store, $sm_fonts, null, $plugin_settings );

			$payload = $endpoints->get_payload();

			$this->assertTrue( $payload['enabled'] );
			$this->assertTrue( $payload['cloudFontsEnabled'] );
			$this->assertTrue( $payload['supported'] );

			$families_by_family = [];
			foreach ( $payload['families'] as $family_entry ) {
				$families_by_family[ $family_entry['family'] ] = $family_entry;
			}

			$this->assertSame(
				[ 'Uncut Sans', 'Broken Font', 'Unused Font', 'Never Mirrored Font' ],
				array_keys( $families_by_family )
			);

			$this->assertSame(
				[
					'family'  => 'Uncut Sans',
					'display' => 'Uncut Sans',
					'status'  => 'ok',
					'healthy' => true,
					'used'    => true,
				],
				$families_by_family['Uncut Sans']
			);

			$this->assertSame(
				[
					'family'  => 'Broken Font',
					'display' => 'Broken Font',
					'status'  => 'failed',
					'healthy' => false,
					'used'    => true,
				],
				$families_by_family['Broken Font']
			);

			$this->assertSame(
				[
					'family'  => 'Unused Font',
					'display' => 'Unused Font',
					'status'  => 'ok',
					'healthy' => true,
					'used'    => false,
				],
				$families_by_family['Unused Font']
			);

			$this->assertSame(
				[
					'family'  => 'Never Mirrored Font',
					'display' => 'Never Mirrored Font',
					'status'  => '',
					'healthy' => false,
					'used'    => true,
				],
				$families_by_family['Never Mirrored Font']
			);

			// Used and unhealthy: "Broken Font" and "Never Mirrored Font" -- "Uncut Sans" is
			// used but healthy, "Unused Font" is unhealthy... no, healthy but unused.
			$this->assertSame( 2, $payload['usedUnhealthyCount'] );
			$this->assertSame( 'https://example.test/wp-admin/options-general.php?page=style-manager', $payload['settingsUrl'] );
		}

		public function test_save_settings_persists_yes_when_enabled(): void {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->expects( $this->once() )
				->method( 'set' )
				->with( 'typography_host_cloud_fonts_locally', 'yes' );
			$plugin_settings->method( 'get' )->willReturn( 'yes' );

			$endpoints = $this->create_endpoints( null, null, null, $plugin_settings );

			$response = $endpoints->handle_save_settings( new \WP_REST_Request( [ 'enabled' => true ] ) );

			$this->assertIsArray( $response );
			$this->assertArrayHasKey( 'enabled', $response );
		}

		public function test_save_settings_persists_empty_string_when_disabled(): void {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->expects( $this->once() )
				->method( 'set' )
				->with( 'typography_host_cloud_fonts_locally', '' );
			$plugin_settings->method( 'get' )->willReturn( '' );

			$endpoints = $this->create_endpoints( null, null, null, $plugin_settings );

			$endpoints->handle_save_settings( new \WP_REST_Request( [ 'enabled' => false ] ) );
		}

		public function test_mirror_delegates_to_mirror_used_fonts_when_enabled(): void {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->method( 'get' )->willReturnMap(
				[
					[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
					[ 'typography_cloud_fonts', 'yes', 'yes' ],
				]
			);

			$local_fonts = $this->createMock( LocalFonts::class );
			$local_fonts->expects( $this->once() )->method( 'mirror_used_fonts' );

			$local_font_store = $this->createMock( LocalFontStore::class );
			$local_font_store->method( 'get_manifest' )->willReturn( [] );

			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

			$endpoints = $this->create_endpoints( $local_font_store, $sm_fonts, $local_fonts, $plugin_settings );

			$response = $endpoints->handle_mirror();

			$this->assertIsArray( $response );
		}

		public function test_mirror_is_a_noop_without_error_when_local_hosting_disabled(): void {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->method( 'get' )->willReturnMap(
				[
					[ 'typography_host_cloud_fonts_locally', 'yes', '' ],
					[ 'typography_cloud_fonts', 'yes', 'yes' ],
				]
			);

			$local_fonts = $this->createMock( LocalFonts::class );
			$local_fonts->expects( $this->never() )->method( 'mirror_used_fonts' );

			$local_font_store = $this->createMock( LocalFontStore::class );
			$local_font_store->method( 'get_manifest' )->willReturn( [] );

			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

			$endpoints = $this->create_endpoints( $local_font_store, $sm_fonts, $local_fonts, $plugin_settings );

			$response = $endpoints->handle_mirror();

			$this->assertIsArray( $response );
		}

		public function test_mirror_is_a_noop_without_error_when_cloud_fonts_disabled(): void {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->method( 'get' )->willReturnMap(
				[
					[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
					[ 'typography_cloud_fonts', 'yes', '' ],
				]
			);

			$local_fonts = $this->createMock( LocalFonts::class );
			$local_fonts->expects( $this->never() )->method( 'mirror_used_fonts' );

			$local_font_store = $this->createMock( LocalFontStore::class );
			$local_font_store->method( 'get_manifest' )->willReturn( [] );

			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

			$endpoints = $this->create_endpoints( $local_font_store, $sm_fonts, $local_fonts, $plugin_settings );

			$response = $endpoints->handle_mirror();

			$this->assertIsArray( $response );
		}

		private function create_endpoints(
			?LocalFontStore $local_font_store = null,
			?Fonts $sm_fonts = null,
			?LocalFonts $local_fonts = null,
			?PluginSettings $plugin_settings = null
		): LocalFontsEndpoints {
			$local_font_store = $local_font_store ?? $this->default_local_font_store();
			$sm_fonts         = $sm_fonts ?? $this->default_sm_fonts();
			$local_fonts      = $local_fonts ?? $this->createMock( LocalFonts::class );
			$plugin_settings  = $plugin_settings ?? $this->default_plugin_settings();

			return new LocalFontsEndpoints(
				$local_font_store,
				$sm_fonts,
				$local_fonts,
				$plugin_settings,
				$this->createMock( LoggerInterface::class )
			);
		}

		private function default_local_font_store(): LocalFontStore {
			$local_font_store = $this->createMock( LocalFontStore::class );
			$local_font_store->method( 'get_manifest' )->willReturn( [] );
			$local_font_store->method( 'is_healthy' )->willReturn( false );

			return $local_font_store;
		}

		private function default_sm_fonts(): Fonts {
			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

			return $sm_fonts;
		}

		private function default_plugin_settings(): PluginSettings {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings->method( 'get' )->willReturn( 'yes' );

			return $plugin_settings;
		}
	}
}

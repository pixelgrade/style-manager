<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Integration\PixelgradeAssistant;
use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;

class PixelgradeAssistantTest extends TestCase {
	public function test_register_hooks_invalidates_cache_when_pixassist_license_theme_mod_changes(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		Filters\expectAdded( 'pre_set_theme_mod_pixassist_license' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );
		Filters\expectAdded( 'pixassist_styles_data' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );
		Filters\expectAdded( 'admin_enqueue_scripts' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );
		Filters\expectAdded( 'pixassist_setup_readiness_checks' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 2 );

		( new PixelgradeAssistant( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ), $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) )->register_hooks();

		$this->addToAssertionCount( 1 );
	}

	public function test_pixassist_license_filter_invalidates_all_caches_and_preserves_value(): void {
		$options = $this->createMock( Options::class );
		$options->expects( $this->once() )->method( 'invalidate_all_caches' );

		$integration = new class( $options, $this->createMock( PluginSettings::class ), $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_invalidate_all_caches( $value ) {
				return $this->invalidate_all_caches( $value );
			}
		};

		$this->assertSame( 'license-key', $integration->expose_invalidate_all_caches( 'license-key' ) );
	}

	public function test_styles_payload_advertises_the_versioned_preview_contract(): void {
		$integration = new class( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ), $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_add_design_system_preview( $data ) {
				return $this->add_design_system_preview( $data );
			}
		};

		$this->assertSame(
			[
				'copy'                => [ 'title' => 'Design System' ],
				'designSystemPreview' => [
					'schemaVersion' => DesignSystemPreviewEndpoint::SCHEMA_VERSION,
					'path'          => '/' . DesignSystemPreviewEndpoint::REST_NAMESPACE . DesignSystemPreviewEndpoint::REST_PATH,
				],
			],
			$integration->expose_add_design_system_preview( [ 'copy' => [ 'title' => 'Design System' ] ] )
		);
	}

	public function test_preview_contract_filter_preserves_non_array_payloads(): void {
		$integration = new class( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ), $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_add_design_system_preview( $data ) {
				return $this->add_design_system_preview( $data );
			}
		};

		$this->assertSame( 'keep-me', $integration->expose_add_design_system_preview( 'keep-me' ) );
	}

	public function test_design_hub_assets_bail_on_wrong_hook_suffix(): void {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->never() )->method( 'get' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings, $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_maybe_enqueue_design_hub_assets( $hook_suffix ): void {
				$this->maybe_enqueue_design_hub_assets( $hook_suffix );
			}

			protected function assistant_hub_is_available(): bool {
				return true;
			}
		};

		Functions\expect( 'wp_enqueue_script' )->never();

		$integration->expose_maybe_enqueue_design_hub_assets( 'options-general.php' );

		$this->addToAssertionCount( 1 );
	}

	public function test_design_hub_assets_bail_when_hub_unavailable(): void {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->never() )->method( 'get' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings, $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_maybe_enqueue_design_hub_assets( $hook_suffix ): void {
				$this->maybe_enqueue_design_hub_assets( $hook_suffix );
			}

			protected function assistant_hub_is_available(): bool {
				return false;
			}
		};

		Functions\expect( 'wp_enqueue_script' )->never();

		$integration->expose_maybe_enqueue_design_hub_assets( 'toplevel_page_pixelgrade' );

		$this->addToAssertionCount( 1 );
	}

	public function test_design_hub_assets_bail_when_sm_unsupported(): void {
		Functions\when( 'current_theme_supports' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->never() )->method( 'get' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings, $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_maybe_enqueue_design_hub_assets( $hook_suffix ): void {
				$this->maybe_enqueue_design_hub_assets( $hook_suffix );
			}

			protected function assistant_hub_is_available(): bool {
				return true;
			}
		};

		Functions\expect( 'wp_enqueue_script' )->never();

		$integration->expose_maybe_enqueue_design_hub_assets( 'toplevel_page_pixelgrade' );

		$this->addToAssertionCount( 1 );
	}

	public function test_design_hub_assets_bail_when_cloud_fonts_setting_off(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->once() )
			->method( 'get' )
			->with( 'typography_cloud_fonts', 'yes' )
			->willReturn( '' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings, $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_maybe_enqueue_design_hub_assets( $hook_suffix ): void {
				$this->maybe_enqueue_design_hub_assets( $hook_suffix );
			}

			protected function assistant_hub_is_available(): bool {
				return true;
			}
		};

		Functions\expect( 'wp_enqueue_script' )->never();

		$integration->expose_maybe_enqueue_design_hub_assets( 'toplevel_page_pixelgrade' );

		$this->addToAssertionCount( 1 );
	}

	public function test_design_hub_assets_enqueue_when_all_gates_pass(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->once() )
			->method( 'get' )
			->with( 'typography_cloud_fonts', 'yes' )
			->willReturn( 'yes' );

		$plugin = $this->createMock( PluginInterface::class );
		$plugin->method( 'get_url' )
			->with( 'dist/js/design-hub.js' )
			->willReturn( 'https://example.test/wp-content/plugins/style-manager/dist/js/design-hub.js' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings, $this->createMock( LocalFontStore::class ), $this->createMock( Fonts::class ) ) extends PixelgradeAssistant {
			public function expose_maybe_enqueue_design_hub_assets( $hook_suffix ): void {
				$this->maybe_enqueue_design_hub_assets( $hook_suffix );
			}

			protected function assistant_hub_is_available(): bool {
				return true;
			}
		};
		$integration->set_plugin( $plugin );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'style-manager-design-hub',
				'https://example.test/wp-content/plugins/style-manager/dist/js/design-hub.js',
				[ 'wp-hooks', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ],
				Mockery::type( 'string' ),
				true
			);

		$integration->expose_maybe_enqueue_design_hub_assets( 'toplevel_page_pixelgrade' );

		$this->addToAssertionCount( 1 );
	}

	// -----------------------------------------------------------------
	// add_local_fonts_readiness_check()
	// -----------------------------------------------------------------

	public function test_readiness_check_not_added_when_sm_unsupported(): void {
		Functions\when( 'current_theme_supports' )->justReturn( false );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->never() )->method( 'get' );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->expects( $this->never() )->method( 'get_used_cloud_font_families' );

		$integration = $this->make_readiness_integration( $plugin_settings, $this->createMock( LocalFontStore::class ), $sm_fonts );

		$this->assertSame( [ 'existing' ], $integration->expose_add_local_fonts_readiness_check( [ 'existing' ], [] ) );
	}

	public function test_readiness_check_not_added_when_cloud_fonts_setting_is_disabled(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->once() )
			->method( 'get' )
			->with( 'typography_cloud_fonts', 'yes' )
			->willReturn( '' );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->expects( $this->never() )->method( 'get_used_cloud_font_families' );

		$integration = $this->make_readiness_integration( $plugin_settings, $this->createMock( LocalFontStore::class ), $sm_fonts );

		$this->assertSame( [], $integration->expose_add_local_fonts_readiness_check( [], [] ) );
	}

	public function test_readiness_check_is_ok_with_no_cloud_fonts_in_use(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnMap( [
			[ 'typography_cloud_fonts', 'yes', 'yes' ],
			[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
		] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->expects( $this->never() )->method( 'is_healthy' );

		$integration = $this->make_readiness_integration( $plugin_settings, $local_font_store, $sm_fonts );

		$checks = $integration->expose_add_local_fonts_readiness_check( [], [] );

		$this->assertCount( 1, $checks );
		$this->assertSame( 'sm-local-fonts', $checks[0]['id'] );
		$this->assertSame( 'integrations', $checks[0]['group'] );
		$this->assertSame( 'ok', $checks[0]['status'] );
		$this->assertSame( 'No cloud fonts in use yet.', $checks[0]['value'] );
		$this->assertNull( $checks[0]['action'] );
	}

	public function test_readiness_check_is_ok_when_hosting_is_on_and_every_used_family_is_healthy(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnMap( [
			[ 'typography_cloud_fonts', 'yes', 'yes' ],
			[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
		] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans', 'Quentin' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( true );

		$integration = $this->make_readiness_integration( $plugin_settings, $local_font_store, $sm_fonts );

		$checks = $integration->expose_add_local_fonts_readiness_check( [], [] );

		$this->assertSame( 'ok', $checks[0]['status'] );
		$this->assertSame( '2 fonts served from your site', $checks[0]['value'] );
		$this->assertNull( $checks[0]['action'] );
	}

	public function test_readiness_check_is_warning_when_hosting_is_off_while_cloud_fonts_are_in_use(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnMap( [
			[ 'typography_cloud_fonts', 'yes', 'yes' ],
			[ 'typography_host_cloud_fonts_locally', 'yes', '' ],
		] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans' ] );

		// Healthy locally, but hosting is off -- the toggle itself keeps
		// visitors served straight from Pixelgrade Cloud regardless.
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( true );

		$integration = $this->make_readiness_integration(
			$plugin_settings,
			$local_font_store,
			$sm_fonts,
			'https://example.test/wp-admin/admin.php?page=pixelgrade&tab=plugins&section=fonts'
		);

		$checks = $integration->expose_add_local_fonts_readiness_check( [], [] );

		$this->assertSame( 'warning', $checks[0]['status'] );
		$this->assertSame( '1 font still loads from Pixelgrade Cloud', $checks[0]['value'] );
		$this->assertSame(
			[
				'label' => 'Review fonts',
				'url'   => 'https://example.test/wp-admin/admin.php?page=pixelgrade&tab=plugins&section=fonts',
			],
			$checks[0]['action']
		);
	}

	public function test_readiness_check_is_warning_when_a_used_family_is_unhealthy(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnMap( [
			[ 'typography_cloud_fonts', 'yes', 'yes' ],
			[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
		] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );

		$integration = $this->make_readiness_integration(
			$plugin_settings,
			$local_font_store,
			$sm_fonts,
			'https://example.test/wp-admin/admin.php?page=pixelgrade&tab=plugins&section=fonts'
		);

		$checks = $integration->expose_add_local_fonts_readiness_check( [], [] );

		$this->assertSame( 'warning', $checks[0]['status'] );
		$this->assertSame( '1 font still loads from Pixelgrade Cloud', $checks[0]['value'] );
		$this->assertNotNull( $checks[0]['action'] );
	}

	public function test_readiness_check_warning_omits_action_when_hub_url_is_unavailable(): void {
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->alias( static fn( $text ) => (string) $text );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnMap( [
			[ 'typography_cloud_fonts', 'yes', 'yes' ],
			[ 'typography_host_cloud_fonts_locally', 'yes', 'yes' ],
		] );

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( [ 'Uncut Sans' ] );

		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturn( false );

		$integration = $this->make_readiness_integration( $plugin_settings, $local_font_store, $sm_fonts, '' );

		$checks = $integration->expose_add_local_fonts_readiness_check( [], [] );

		$this->assertSame( 'warning', $checks[0]['status'] );
		$this->assertNull( $checks[0]['action'] );
	}

	/**
	 * Build a PixelgradeAssistant exposing add_local_fonts_readiness_check(),
	 * with get_hub_fonts_url() overridden to a fixed value -- the underlying
	 * `pixassist_get_hub_url()` function is defined by the Assistant plugin at
	 * runtime and can't be toggled on/off mid test-suite.
	 */
	private function make_readiness_integration(
		PluginSettings $plugin_settings,
		LocalFontStore $local_font_store,
		Fonts $sm_fonts,
		string $hub_url = ''
	): PixelgradeAssistant {
		return new class( $this->createMock( Options::class ), $plugin_settings, $local_font_store, $sm_fonts, $hub_url ) extends PixelgradeAssistant {
			private string $hub_url;

			public function __construct( Options $options, PluginSettings $plugin_settings, LocalFontStore $local_font_store, Fonts $sm_fonts, string $hub_url ) {
				parent::__construct( $options, $plugin_settings, $local_font_store, $sm_fonts );
				$this->hub_url = $hub_url;
			}

			public function expose_add_local_fonts_readiness_check( $checks, $facts ) {
				return $this->add_local_fonts_readiness_check( $checks, $facts );
			}

			protected function get_hub_fonts_url(): string {
				return $this->hub_url;
			}
		};
	}
}

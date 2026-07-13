<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
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

		( new PixelgradeAssistant( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ) ) )->register_hooks();

		$this->addToAssertionCount( 1 );
	}

	public function test_pixassist_license_filter_invalidates_all_caches_and_preserves_value(): void {
		$options = $this->createMock( Options::class );
		$options->expects( $this->once() )->method( 'invalidate_all_caches' );

		$integration = new class( $options, $this->createMock( PluginSettings::class ) ) extends PixelgradeAssistant {
			public function expose_invalidate_all_caches( $value ) {
				return $this->invalidate_all_caches( $value );
			}
		};

		$this->assertSame( 'license-key', $integration->expose_invalidate_all_caches( 'license-key' ) );
	}

	public function test_styles_payload_advertises_the_versioned_preview_contract(): void {
		$integration = new class( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ) ) extends PixelgradeAssistant {
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
		$integration = new class( $this->createMock( Options::class ), $this->createMock( PluginSettings::class ) ) extends PixelgradeAssistant {
			public function expose_add_design_system_preview( $data ) {
				return $this->add_design_system_preview( $data );
			}
		};

		$this->assertSame( 'keep-me', $integration->expose_add_design_system_preview( 'keep-me' ) );
	}

	public function test_design_hub_assets_bail_on_wrong_hook_suffix(): void {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->expects( $this->never() )->method( 'get' );

		$integration = new class( $this->createMock( Options::class ), $plugin_settings ) extends PixelgradeAssistant {
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

		$integration = new class( $this->createMock( Options::class ), $plugin_settings ) extends PixelgradeAssistant {
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

		$integration = new class( $this->createMock( Options::class ), $plugin_settings ) extends PixelgradeAssistant {
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

		$integration = new class( $this->createMock( Options::class ), $plugin_settings ) extends PixelgradeAssistant {
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

		$integration = new class( $this->createMock( Options::class ), $plugin_settings ) extends PixelgradeAssistant {
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
}

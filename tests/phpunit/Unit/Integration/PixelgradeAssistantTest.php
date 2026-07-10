<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pixelgrade\StyleManager\Integration\PixelgradeAssistant;
use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

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

		( new PixelgradeAssistant( $this->createMock( Options::class ) ) )->register_hooks();

		$this->addToAssertionCount( 1 );
	}

	public function test_pixassist_license_filter_invalidates_all_caches_and_preserves_value(): void {
		$options = $this->createMock( Options::class );
		$options->expects( $this->once() )->method( 'invalidate_all_caches' );

		$integration = new class( $options ) extends PixelgradeAssistant {
			public function expose_invalidate_all_caches( $value ) {
				return $this->invalidate_all_caches( $value );
			}
		};

		$this->assertSame( 'license-key', $integration->expose_invalidate_all_caches( 'license-key' ) );
	}

	public function test_styles_payload_advertises_the_versioned_preview_contract(): void {
		$integration = new class( $this->createMock( Options::class ) ) extends PixelgradeAssistant {
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
		$integration = new class( $this->createMock( Options::class ) ) extends PixelgradeAssistant {
			public function expose_add_design_system_preview( $data ) {
				return $this->add_design_system_preview( $data );
			}
		};

		$this->assertSame( 'keep-me', $integration->expose_add_design_system_preview( 'keep-me' ) );
	}
}

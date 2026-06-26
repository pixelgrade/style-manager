<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Integration;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pixelgrade\StyleManager\Integration\PixelgradeCare;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class PixelgradeCareTest extends TestCase {
	public function test_register_hooks_invalidates_cache_when_pixcare_license_theme_mod_changes(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		Filters\expectAdded( 'pre_set_theme_mod_pixcare_license' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );

		( new PixelgradeCare( $this->createMock( Options::class ) ) )->register_hooks();

		$this->addToAssertionCount( 1 );
	}

	public function test_pixcare_license_filter_invalidates_all_caches_and_preserves_value(): void {
		$options = $this->createMock( Options::class );
		$options->expects( $this->once() )->method( 'invalidate_all_caches' );

		$integration = new class( $options ) extends PixelgradeCare {
			public function expose_invalidate_all_caches( $value ) {
				return $this->invalidate_all_caches( $value );
			}
		};

		$this->assertSame( 'license-key', $integration->expose_invalidate_all_caches( 'license-key' ) );
	}
}

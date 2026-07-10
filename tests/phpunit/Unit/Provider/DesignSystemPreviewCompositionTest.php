<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\ServiceProvider;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Pimple\Container;

class DesignSystemPreviewCompositionTest extends TestCase {
	public function test_service_provider_builds_the_preview_endpoint_from_shared_options_and_fonts(): void {
		$container = new Container();
		( new ServiceProvider() )->register( $container );

		$options = $this->createMock( Options::class );
		$fonts = $this->createMock( Fonts::class );
		$container['options'] = $options;
		$container['customize.fonts'] = $fonts;

		$this->assertInstanceOf(
			DesignSystemPreviewEndpoint::class,
			$container['provider.design_system_preview_endpoint']
		);
	}

	public function test_plugin_composes_the_preview_route_outside_the_admin_only_branch(): void {
		$source = file_get_contents( dirname( __DIR__, 4 ) . '/src/Plugin.php' );

		$this->assertIsString( $source );
		$this->assertStringContainsString( "provider.design_system_preview_endpoint", $source );
		$this->assertLessThan(
			strpos( $source, 'if ( is_admin() )' ),
			strpos( $source, "provider.design_system_preview_endpoint" )
		);
	}
}

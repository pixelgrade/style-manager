<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Pixelgrade\StyleManager\Lab\Config;
use Pixelgrade\StyleManager\Lab\ContextualPalette;
use Pixelgrade\StyleManager\Lab\ShowcaseRoute;
use Pixelgrade\StyleManager\Lab\ShowcaseRenderer;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class ShowcaseRouteTest extends TestCase {
	public function test_showcase_body_classes_drop_theme_intro_animation_classes(): void {
		$classes = ShowcaseRoute::filter_showcase_body_classes( [
			'home',
			'wp-theme-anima',
			'sm-lab-showcase',
			'is-loading',
			'has-intro-animations',
			'has-intro-animations--kinetic',
			'has-intro-animations--medium',
			'sm-palette-2',
		] );

		$this->assertContains( 'home', $classes );
		$this->assertContains( 'wp-theme-anima', $classes );
		$this->assertContains( 'sm-lab-showcase', $classes );
		$this->assertContains( 'sm-palette-2', $classes );
		$this->assertNotContains( 'is-loading', $classes );
		$this->assertNotContains( 'has-intro-animations', $classes );
		$this->assertNotContains( 'has-intro-animations--kinetic', $classes );
		$this->assertNotContains( 'has-intro-animations--medium', $classes );
	}

	public function test_contextual_palette_css_neutralizes_a_hostile_dynamic_value(): void {
		$hostile = '--sm-bg-color-1:red;} </style><script>window.compromised=true</script><style>.x{color:red';

		\Brain\Monkey\Functions\when( 'wp_strip_all_tags' )->returnArg( 1 );

		$route  = new ShowcaseRoute( new ShowcaseRenderer(), new ContextualPalette(), new Config() );
		$method = new \ReflectionMethod( ShowcaseRoute::class, 'render_contextual_palette_style' );

		ob_start();
		$method->invoke( $route, $hostile );
		$output = (string) ob_get_clean();

		// Only the wrapper's legitimate closing tag may survive.
		$this->assertSame( 1, preg_match_all( '/<\/style/i', $output ) );
		$this->assertStringContainsString( '<\\/style', $output );
	}
}

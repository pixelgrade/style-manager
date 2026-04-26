<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Pixelgrade\StyleManager\Lab\ShowcaseRoute;
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
}

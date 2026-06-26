<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Integration;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Integration\TheEventsCalendar;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class TheEventsCalendarTest extends TestCase {
	public function test_select2_assets_are_left_alone_outside_customizer_preview(): void {
		Functions\when( 'is_customize_preview' )->justReturn( false );
		Functions\expect( 'wp_deregister_script' )->never();
		Functions\expect( 'wp_register_script' )->never();
		Functions\expect( 'wp_deregister_style' )->never();
		Functions\expect( 'wp_register_style' )->never();

		( new TestTheEventsCalendar() )->expose_deregister_select2();

		$this->addToAssertionCount( 1 );
	}

	public function test_select2_assets_are_replaced_in_customizer_preview(): void {
		Functions\when( 'is_customize_preview' )->justReturn( true );
		Functions\expect( 'wp_deregister_script' )->once()->with( 'tribe-select2' );
		Functions\expect( 'wp_register_script' )
			->once()
			->with( 'tribe-select2', '', [], \Pixelgrade\StyleManager\VERSION, true );
		Functions\expect( 'wp_deregister_style' )->once()->with( 'tribe-select2-css' );
		Functions\expect( 'wp_register_style' )
			->once()
			->with( 'tribe-select2-css', '', [], \Pixelgrade\StyleManager\VERSION );

		( new TestTheEventsCalendar() )->expose_deregister_select2();

		$this->addToAssertionCount( 1 );
	}
}

class TestTheEventsCalendar extends TheEventsCalendar {
	public function expose_deregister_select2(): void {
		$this->deregister_select2();
	}
}

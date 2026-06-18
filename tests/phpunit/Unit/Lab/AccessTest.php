<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Lab\Access;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class AccessTest extends TestCase {
	public function test_lab_is_disabled_by_default(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertFalse( Access::is_enabled() );
	}

	public function test_lab_can_be_enabled_by_filter(): void {
		Functions\expect( 'apply_filters' )
			->once()
			->with( 'style_manager/enable_lab', false )
			->andReturn( true );

		$this->assertTrue( Access::is_enabled() );
	}

	public function test_lab_requires_manage_options_capability(): void {
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );

		$this->assertTrue( Access::current_user_can_access() );
	}
}

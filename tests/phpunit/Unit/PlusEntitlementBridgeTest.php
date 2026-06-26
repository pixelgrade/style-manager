<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

class PlusEntitlementBridgeTest extends TestCase {
	public function test_absent_plus_bridge_keeps_legacy_controls_available_but_editor_trial_locked(): void {
		$this->mock_plus_bridge( false, false );

		$this->assertFalse( \Pixelgrade\StyleManager\pixelgrade_plus_entitlement_bridge_is_available() );
		$this->assertTrue( \Pixelgrade\StyleManager\advanced_style_manager_controls_are_unlocked() );
		$this->assertTrue( \Pixelgrade\StyleManager\plus_advanced_controls_locked() );
		$this->assertTrue( \Pixelgrade\StyleManager\has_pixelgrade_plus_entitlement( 'anything', true ) );
		$this->assertFalse( \Pixelgrade\StyleManager\has_pixelgrade_plus_entitlement( 'anything', false ) );
	}

	public function test_available_plus_bridge_grants_advanced_controls_when_entitled(): void {
		$this->mock_plus_bridge( true, true );

		$this->assertTrue( \Pixelgrade\StyleManager\pixelgrade_plus_entitlement_bridge_is_available() );
		$this->assertTrue( \Pixelgrade\StyleManager\advanced_style_manager_controls_are_unlocked() );
		$this->assertFalse( \Pixelgrade\StyleManager\plus_advanced_controls_locked() );
	}

	public function test_available_plus_bridge_locks_advanced_controls_when_not_entitled(): void {
		$this->mock_plus_bridge( true, false );

		$this->assertTrue( \Pixelgrade\StyleManager\pixelgrade_plus_entitlement_bridge_is_available() );
		$this->assertFalse( \Pixelgrade\StyleManager\advanced_style_manager_controls_are_unlocked() );
		$this->assertTrue( \Pixelgrade\StyleManager\plus_advanced_controls_locked() );
	}

	private function mock_plus_bridge( bool $bridge_available, bool $entitled ): void {
		Functions\when( 'has_filter' )->alias( static function( string $hook ) use ( $bridge_available ) {
			if ( 'pixelgrade/has_entitlement' === $hook ) {
				return $bridge_available ? 10 : false;
			}

			return false;
		} );

		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$args ) use ( $entitled ) {
			if ( 'style_manager/pixelgrade_plus_entitlement_bridge_is_available' === $hook ) {
				return $value;
			}

			if ( 'pixelgrade/has_entitlement' === $hook ) {
				return $entitled;
			}

			return $value;
		} );
	}
}

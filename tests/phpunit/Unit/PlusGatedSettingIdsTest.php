<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\ColorPalettes;
use Pixelgrade\StyleManager\Customize\FontPalettes;

class PlusGatedSettingIdsTest extends TestCase {
	public function test_canonical_plus_gated_setting_ids_include_persisted_color_system_subsets(): void {
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$ids = \Pixelgrade\StyleManager\plus_gated_setting_ids();

		foreach ( ColorPalettes::get_premium_setting_ids() as $id ) {
			$this->assertContains( $id, $ids );
		}

		foreach ( ColorPalettes::get_usage_premium_setting_ids() as $id ) {
			$this->assertContains( $id, $ids );
		}

		// The advanced Typography layer is gated the same way as color.
		foreach ( FontPalettes::get_premium_setting_ids() as $id ) {
			$this->assertContains( $id, $ids );
		}

		$this->assertContains( 'sm_colorize_elements_button', $ids );
		$this->assertNotContains( 'sm_color_fine_tune_intro', $ids );
		$this->assertNotContains( 'sm_description_color_usage_intro', $ids );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );

		foreach ( $ids as $id ) {
			$this->assertStringStartsNotWith( 'sm_separator_', $id );
			$this->assertStringNotContainsString( '_intro', $id );
		}
	}

	public function test_canonical_plus_gated_setting_ids_are_filterable_and_normalized(): void {
		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value ) {
			if ( 'style_manager/plus_gated_setting_ids' !== $hook ) {
				return $value;
			}

			return array_merge(
				(array) $value,
				[
					'companion_premium_setting',
					'companion_premium_setting',
					123,
					'',
					false,
				]
			);
		} );

		$ids = \Pixelgrade\StyleManager\plus_gated_setting_ids();

		$this->assertContains( 'companion_premium_setting', $ids );
		$this->assertContains( '123', $ids );
		$this->assertNotContains( '', $ids );
		$this->assertSame( 1, count( array_keys( $ids, 'companion_premium_setting', true ) ) );
		$this->assertSame( $ids, array_values( array_unique( $ids ) ) );
	}
}

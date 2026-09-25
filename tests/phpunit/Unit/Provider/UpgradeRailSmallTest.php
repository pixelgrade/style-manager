<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Provider\Upgrade;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * nova-blocks#655: the Small rail used to fall back to a saved Content Inset
 * (while no Rail Scale was set). Nova decoupled them, so an upgraded site pins
 * its previously effective Small rail (= the saved inset) through
 * `sm_rail_small`, keeping Medium and Large on their defaults.
 */
class UpgradeRailSmallTest extends TestCase {
	private array $options = [];
	private array $updated = [];

	private function run_upgrade( array $options ): void {
		$this->options = array_merge(
			[
				Upgrade::VERSION_OPTION_NAME          => '999.0.0',
				'sm_perf_autoload_migrated_v1'        => '1',
				'sm_font_sizing_relative_migrated_v1' => '1',
				'sm_font_sizing_absolute_migrated_v2' => '1',
			],
			$options
		);
		$this->updated = [];

		Functions\when( 'get_option' )->alias( function ( string $key, $default = false ) {
			return array_key_exists( $key, $this->options ) ? $this->options[ $key ] : $default;
		} );
		Functions\when( 'update_option' )->alias( function ( string $key, $value ) {
			$this->updated[ $key ] = $value;
			$this->options[ $key ] = $value;

			return true;
		} );
		Functions\when( 'add_option' )->alias( function ( string $key, $value ) {
			if ( array_key_exists( $key, $this->options ) ) {
				return false;
			}
			$this->options[ $key ] = $value;

			return true;
		} );
		Functions\when( 'delete_option' )->justReturn( true );

		$upgrade = new Upgrade(
			$this->createMock( Options::class ),
			$this->createMock( PluginSettings::class ),
			$this->createMock( LoggerInterface::class )
		);
		$upgrade->maybe_upgrade();
	}

	public function test_saved_inset_without_a_rail_scale_pins_the_old_small_rail(): void {
		$this->run_upgrade( [ 'sm_content_inset' => '180' ] );

		$this->assertSame( [ 'sm_rail_small' => 180 ], $this->updated );
		$this->assertSame( '1', $this->options[ Upgrade::RAIL_SMALL_MIGRATION_FLAG ] );
		$this->assertNull( style_manager_rail_widths( '', '', '' ) );
		$this->assertSame( [ 'small' => 180, 'medium' => null, 'large' => null ], style_manager_rail_widths( '', '', $this->options['sm_rail_small'] ) );
	}

	public function test_a_fractional_inset_is_kept_exactly(): void {
		$this->run_upgrade( [ 'sm_content_inset' => '187.5' ] );

		$this->assertSame( [ 'sm_rail_small' => 187.5 ], $this->updated );
	}

	public function test_it_runs_once(): void {
		$this->run_upgrade( [ 'sm_content_inset' => '180' ] );
		$this->assertCount( 1, $this->updated );

		// Second run with the flag already stored (and the user later clearing the pin).
		$options = $this->options;
		unset( $options['sm_rail_small'] );
		$this->run_upgrade( $options );
		$this->assertSame( [], $this->updated );
	}

	public function test_no_saved_inset_changes_nothing(): void {
		$this->run_upgrade( [] );
		$this->assertSame( [], $this->updated );
		$this->assertSame( '1', $this->options[ Upgrade::RAIL_SMALL_MIGRATION_FLAG ] );

		$this->run_upgrade( [ 'sm_content_inset' => '' ] );
		$this->assertSame( [], $this->updated );
	}

	public function test_saved_rail_settings_are_never_overwritten(): void {
		foreach ( [
			[ 'sm_rail_scale' => '180' ],
			[ 'sm_rail_pitch' => '0' ],
			[ 'sm_rail_scale' => '250', 'sm_rail_pitch' => '16' ],
			[ 'sm_rail_small' => '260' ],
		] as $rail ) {
			$this->run_upgrade( array_merge( [ 'sm_content_inset' => '180' ], $rail ) );
			$this->assertSame( [], $this->updated, var_export( $rail, true ) );
			$this->assertSame( '1', $this->options[ Upgrade::RAIL_SMALL_MIGRATION_FLAG ] );
		}
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Provider\Upgrade;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class UpgradeFontSizingStateTest extends TestCase {
	public function test_absolute_font_sizing_migration_resets_the_stale_named_choice(): void {
		$deleted = [];
		$added   = [];
		$options = [
			Upgrade::VERSION_OPTION_NAME            => '999.0.0',
			'sm_perf_autoload_migrated_v1'          => '1',
			'sm_font_sizing_relative_migrated_v1'   => '1',
			'sm_font_sizing_absolute_migrated_v2'   => false,
			'sm_font_sizing'                        => 'smaller',
			'sm_font_primary_elevation'              => 6,
			'sm_font_primary_pitch'                  => 40,
			'sm_font_secondary_elevation'            => 16,
			'sm_font_secondary_pitch'                => 16,
			'sm_font_body_elevation'                 => 0,
			'sm_font_body_pitch'                     => 45,
		];

		Functions\when( 'get_option' )->alias( static function( string $key, $default = false ) use ( &$options ) {
			return $options[ $key ] ?? $default;
		} );
		Functions\when( 'delete_option' )->alias( static function( string $key ) use ( &$deleted, &$options ) {
			$deleted[] = $key;
			unset( $options[ $key ] );

			return true;
		} );
		Functions\when( 'add_option' )->alias( static function( string $key, $value ) use ( &$added, &$options ) {
			$added[ $key ]   = $value;
			$options[ $key ] = $value;

			return true;
		} );

		$upgrade = new Upgrade(
			$this->createMock( Options::class ),
			$this->createMock( PluginSettings::class ),
			$this->createMock( LoggerInterface::class )
		);
		$upgrade->maybe_upgrade();

		$this->assertContains( 'sm_font_sizing', $deleted );
		foreach ( [ 'primary', 'secondary', 'body' ] as $master ) {
			$this->assertContains( "sm_font_{$master}_elevation", $deleted );
			$this->assertContains( "sm_font_{$master}_pitch", $deleted );
		}
		$this->assertSame( '1', $added['sm_font_sizing_absolute_migrated_v2'] ?? null );
	}
}

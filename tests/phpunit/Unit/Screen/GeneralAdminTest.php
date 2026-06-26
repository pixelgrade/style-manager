<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Screen\GeneralAdmin;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class GeneralAdminTest extends TestCase {
	public function test_child_theme_migration_ajax_denies_unauthorized_users_before_mutating_theme_mods(): void {
		$json_error_called = false;

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'style_manager_migrate_customizations_from_parent_to_child_theme', 'nonce_migrate' );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'wp_get_theme' )->never();
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'get_theme_mods' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_send_json_success' )->never();
		Functions\when( 'wp_send_json_error' )->alias( static function() use ( &$json_error_called ) {
			$json_error_called = true;

			throw new \RuntimeException( 'migration_ajax_denied' );
		} );

		try {
			( new GeneralAdmin( $this->createMock( LoggerInterface::class ) ) )->migrate_customizations_from_parent_to_child_theme();
			$this->fail( 'Denied migration request should stop at wp_send_json_error().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'migration_ajax_denied', $e->getMessage() );
		}

		$this->assertTrue( $json_error_called );
	}

	public function test_child_theme_migration_merges_parent_theme_mods_without_legacy_pixcare_keys(): void {
		$updated_option = null;

		Functions\expect( 'check_ajax_referer' )
			->once()
			->with( 'style_manager_migrate_customizations_from_parent_to_child_theme', 'nonce_migrate' );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( true );
		Functions\expect( 'get_template' )
			->once()
			->andReturn( 'parent-theme' );
		Functions\expect( 'wp_get_theme' )
			->once()
			->with( 'parent-theme' )
			->andReturn( new class {
				public function exists(): bool {
					return true;
				}

				public function get_stylesheet(): string {
					return 'parent-theme';
				}
			} );
		Functions\when( 'get_option' )->alias( static function( string $key ) {
			if ( 'theme_mods_parent-theme' === $key ) {
				return [
					'shared_setting'                    => 'from-parent',
					'parent_only'                       => 'keep-parent',
					'pixcare_license'                   => 'drop-license',
					'pixcare_new_theme_version'         => 'drop-version',
					'pixcare_install_notice_dismissed'  => true,
				];
			}

			if ( 'stylesheet' === $key ) {
				return 'child-theme';
			}

			return null;
		} );
		Functions\expect( 'get_theme_mods' )
			->once()
			->andReturn( [
				'shared_setting' => 'from-child',
				'child_only'     => 'keep-child',
			] );
		Functions\when( 'update_option' )->alias( static function( string $key, array $value ) use ( &$updated_option ) {
			$updated_option = [ $key, $value ];

			return true;
		} );
		Functions\expect( 'wp_send_json_error' )->never();
		Functions\when( 'wp_send_json_success' )->alias( static function() {
			throw new \RuntimeException( 'migration_ajax_success' );
		} );

		try {
			( new GeneralAdmin( $this->createMock( LoggerInterface::class ) ) )->migrate_customizations_from_parent_to_child_theme();
			$this->fail( 'Successful migration request should stop at wp_send_json_success().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'migration_ajax_success', $e->getMessage() );
		}

		$this->assertSame( 'theme_mods_child-theme', $updated_option[0] );
		$this->assertSame(
			[
				'shared_setting' => 'from-parent',
				'child_only'     => 'keep-child',
				'parent_only'    => 'keep-parent',
			],
			$updated_option[1]
		);
	}

	public function test_legacy_dark_mode_on_migrates_to_advanced_dark_mode_and_swaps_palettes(): void {
		$options = [
			'sm_dark_mode_advanced'    => null,
			'sm_dark_mode'             => 'on',
			'sm_dark_primary_final'    => '#111111',
			'sm_dark_secondary_final'  => '#222222',
			'sm_dark_tertiary_final'   => '#333333',
			'sm_light_primary_final'   => '#eeeeee',
			'sm_light_secondary_final' => '#dddddd',
			'sm_light_tertiary_final'  => '#cccccc',
		];
		$updates = [];

		Functions\expect( 'current_theme_supports' )
			->once()
			->with( 'style_manager_advanced_dark_mode' )
			->andReturn( true );
		Functions\when( 'get_option' )->alias( static function( string $key, $default = null ) use ( &$options ) {
			return array_key_exists( $key, $options ) ? $options[ $key ] : $default;
		} );
		Functions\when( 'update_option' )->alias( static function( string $key, $value ) use ( &$updates ) {
			$updates[ $key ] = $value;

			return true;
		} );

		( new GeneralAdmin( $this->createMock( LoggerInterface::class ) ) )->migrate_to_advanced_dark_mode_control();

		$this->assertSame( 'on', $updates['sm_dark_mode_advanced'] );
		$this->assertSame( 'off', $updates['sm_dark_mode'] );
		$this->assertSame( '#eeeeee', $updates['sm_dark_primary_final'] );
		$this->assertSame( '#dddddd', $updates['sm_dark_secondary_final'] );
		$this->assertSame( '#cccccc', $updates['sm_dark_tertiary_final'] );
		$this->assertSame( '#111111', $updates['sm_light_primary_final'] );
		$this->assertSame( '#222222', $updates['sm_light_secondary_final'] );
		$this->assertSame( '#333333', $updates['sm_light_tertiary_final'] );
	}
}

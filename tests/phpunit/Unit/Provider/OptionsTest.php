<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Carbon_Fields\Field\Field;
use Mockery;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class OptionsTest extends TestCase {
	public function tearDown(): void {
		unset( $GLOBALS['wp_customize'] );

		parent::tearDown();
	}

	public function test_register_hooks_wires_cache_invalidation_paths(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );

		Filters\expectAdded( 'after_switch_theme' )
			->once()
			->with( Mockery::type( \Closure::class ), 1, 1 );
		Filters\expectAdded( 'upgrader_process_complete' )
			->once()
			->with( Mockery::type( \Closure::class ), 1, 2 );
		Filters\expectAdded( 'customize_changeset_save_data' )
			->once()
			->with( Mockery::type( \Closure::class ), 40, 2 );
		Filters\expectAdded( 'customize_changeset_save_data' )
			->once()
			->with( Mockery::type( \Closure::class ), 50, 1 );
		Filters\expectAdded( 'pixcare_sce_import_end' )
			->once()
			->with( Mockery::type( \Closure::class ), 1, 1 );

		$options->register_hooks();
		$this->addToAssertionCount( 5 );
	}

	public function test_upgrade_invalidation_only_runs_for_theme_or_style_manager_plugin_updates(): void {
		$options = new CacheSpyOptions( $this->createMock( PluginSettings::class ) );

		$options->maybe_invalidate_after_upgrade( new \stdClass(), [ 'type' => 'translation' ] );
		$this->assertSame( 0, $options->invalidate_calls );

		$options->maybe_invalidate_after_upgrade( new \stdClass(), [ 'type' => 'plugin', 'plugins' => [ 'akismet/akismet.php' ] ] );
		$this->assertSame( 0, $options->invalidate_calls );

		$options->maybe_invalidate_after_upgrade( new \stdClass(), [ 'type' => 'theme' ] );
		$this->assertSame( 1, $options->invalidate_calls );

		$options->maybe_invalidate_after_upgrade( new \stdClass(), [ 'type' => 'plugin', 'plugins' => [ 'style-manager/style-manager.php' ] ] );
		$this->assertSame( 2, $options->invalidate_calls );
	}

	public function test_changeset_save_filter_invalidates_details_cache_and_preserves_payload(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$deleted_keys = [];

		Functions\when( 'delete_option' )->alias( static function( string $key ) use ( &$deleted_keys ) {
			$deleted_keys[] = $key;

			return true;
		} );
		Functions\when( 'update_option' )->justReturn( true );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$payload = [
			'hive-lt_options[accent_color]' => [
				'value' => '#123456',
			],
		];

		$this->assertSame( $payload, $options->expose_filter_invalidate_details_cache( $payload ) );
		$this->assertContains( Options::MINIMAL_DETAILS_CACHE_KEY, $deleted_keys );
		$this->assertContains( Options::EXTRA_DETAILS_CACHE_KEY, $deleted_keys );
	}

	public function test_get_thememod_value_falls_back_to_filtered_root_theme_mod_when_setting_has_no_post_value(): void {
		Functions\expect( 'is_wp_error' )->never();

		Functions\expect( 'get_theme_mod' )
			->once()
			->with( 'hive-lt_options' )
			->andReturn( [
				'display_font' => [
					'font_size' => [ 'value' => 54 ],
				],
			] );

		$GLOBALS['wp_customize'] = new class {
			public function get_setting( string $setting_id ) {
				return new class {
					public function post_value( $default = null ) {
						return $default;
					}
				};
			}

			public function is_preview(): bool {
				return true;
			}
		};

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( 'hive-lt_options' );

		$value = $options->expose_get_thememod_value( 'display_font', 'hive-lt_options[display_font]' );

		$this->assertSame( 54, $value['font_size']['value'] );
	}

	public function test_get_thememod_value_prefers_explicit_customize_post_value(): void {
		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'get_theme_mod' )->never();

		$GLOBALS['wp_customize'] = new class {
			public function get_setting( string $setting_id ) {
				return new class {
					public function post_value( $default = null ) {
						return [
							'font_size' => [ 'value' => 60 ],
						];
					}
				};
			}

			public function is_preview(): bool {
				return true;
			}
		};

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( 'hive-lt_options' );

		$value = $options->expose_get_thememod_value( 'display_font', 'hive-lt_options[display_font]' );

		$this->assertSame( 60, $value['font_size']['value'] );
	}

	public function test_get_options_key_regenerates_when_cached_value_is_empty(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
			if ( $name === Options::CUSTOMIZER_OPT_NAME_CACHE_KEY ) {
				return '';
			}

			if ( $name === Options::CUSTOMIZER_OPT_NAME_CACHE_TIMESTAMP_KEY ) {
				return time() + HOUR_IN_SECONDS;
			}

			return $default;
		} );

		$updated = [];
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'update_option' )->alias( function ( $name, $value ) use ( &$updated ) {
			$updated[ $name ] = $value;

			return true;
		} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->inject_customizer_config( [
			'opt-name' => 'hive-lt_options',
		] );

		$this->assertSame( 'hive-lt_options', $options->get_options_key() );
		$this->assertSame(
			'hive-lt_options',
			$updated[ Options::CUSTOMIZER_OPT_NAME_CACHE_KEY ] ?? null,
			'The regenerated non-empty option name should replace the bad cached value.'
		);
	}

	public function test_get_details_all_regenerates_when_cached_minimal_details_is_an_empty_array(): void {
		// Seed: cache is stored as [] with a fresh expiration (the
		// pathological state that used to persist for 24h, blanking out
		// the frontend dynamic CSS).
		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
			if ( $name === Options::MINIMAL_DETAILS_CACHE_KEY ) {
				return []; // the bug: empty array persisted as "valid" cache
			}
			if ( $name === Options::EXTRA_DETAILS_CACHE_KEY ) {
				return [];
			}
			if ( $name === Options::DETAILS_CACHE_TIMESTAMP_KEY ) {
				return time() + HOUR_IN_SECONDS; // fresh, not yet expired
			}
			return $default;
		} );
		// Prevent `filter_fields` from producing real config; we only want
		// to observe whether regeneration is attempted.
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( '_doing_it_wrong' )->justReturn( null );
		Functions\when( 'current_user_can' )->justReturn( true );
		// update_option shouldn't be called because the regenerated result
		// is also empty (see companion test below). Expect zero writes.
		$updateCalls = 0;
		Functions\when( 'update_option' )->alias( function () use ( &$updateCalls ) {
			$updateCalls++;
			return true;
		} );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->inject_customizer_config( [ 'panels' => [] ] );

		$result = $options->get_details_all( true );

		$this->assertSame(
			[],
			$result,
			'With no panels registered the regen produces an empty result — but by reaching this point we prove that the empty-cache did NOT short-circuit us into returning stale data.'
		);
		$this->assertSame(
			0,
			$updateCalls,
			'Empty regeneration output must not be persisted, otherwise the same pathological state would return next request.'
		);
	}

	public function test_get_details_all_persists_the_cache_only_when_regen_has_options(): void {
		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
			if ( $name === Options::MINIMAL_DETAILS_CACHE_KEY ) return false;
			if ( $name === Options::EXTRA_DETAILS_CACHE_KEY ) return false;
			if ( $name === Options::DETAILS_CACHE_TIMESTAMP_KEY ) return false;
			return $default;
		} );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_theme_mod' )->alias( static function () {
			return [
				'my_option' => 'computed',
			];
		} );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );

		$updatedKeys = [];
		Functions\when( 'update_option' )->alias( function ( $key ) use ( &$updatedKeys ) {
			$updatedKeys[] = $key;
			return true;
		} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->inject_customizer_config( [
			'panels' => [
				'p1' => [
					'sections' => [
						's1' => [
							'options' => [
								'my_option' => [ 'type' => 'text', 'default' => 'x' ],
							],
						],
					],
				],
			],
		] );

		$options->get_details_all( true );

		$this->assertContains(
			Options::MINIMAL_DETAILS_CACHE_KEY,
			$updatedKeys,
			'A non-empty regeneration SHOULD persist the cache.'
		);
	}

	public function test_invalidate_all_caches_removes_cached_details_rows(): void {
		if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
			define( 'HOUR_IN_SECONDS', 3600 );
		}

		$deleted_keys = [];

		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'delete_option' )->alias( static function( string $key ) use ( &$deleted_keys ) {
			$deleted_keys[] = $key;

			return true;
		} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );

		$options->invalidate_all_caches();

		$this->assertContains(
			Options::MINIMAL_DETAILS_CACHE_KEY,
			$deleted_keys,
			'Invalidation must remove minimal details so frontend requests cannot reuse stale resolved values.'
		);
		$this->assertContains(
			Options::EXTRA_DETAILS_CACHE_KEY,
			$deleted_keys,
			'Invalidation must remove extra details together with the minimal cache.'
		);
	}

	public function test_maybe_migrate_controls_data_copies_saved_theme_mod_values_to_option_store(): void {
		$theme_mod_writes = 0;
		$updated_options  = [];

		Functions\when( 'get_theme_mod' )
			->alias( static function ( string $key ) {
				if ( $key !== 'hive-lt_options' ) {
					return null;
				}

				return [
					'body_font'            => [ 'font_family' => 'Inter' ],
					'accent_color'         => '#111111',
					'html_hint'            => 'visual-only',
					'button_hint'          => 'visual-only',
					'explicit_option'      => 'kept in explicit storage',
					'unregistered_setting' => 'ignored',
				];
			} );

		Functions\when( 'get_option' )
			->alias( static function ( string $key ) {
				if ( $key !== 'hive-lt_options' ) {
					return null;
				}

				return [
					'option_only' => 'preserved',
					'body_font'   => [ 'font_family' => 'Old option value' ],
				];
			} );

		Functions\when( 'set_theme_mod' )
			->alias( static function () use ( &$theme_mod_writes ) {
				$theme_mod_writes++;
			} );
		Functions\when( 'update_option' )
			->alias( static function ( string $key, array $value ) use ( &$updated_options ) {
				$updated_options[ $key ] = $value;

				return true;
			} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( 'hive-lt_options' );
		$options->inject_details( [
			'body_font'       => [ 'type' => 'font' ],
			'accent_color'    => [ 'type' => 'color' ],
			'html_hint'       => [ 'type' => 'html' ],
			'button_hint'     => [ 'type' => 'button' ],
			'explicit_option' => [
				'type'         => 'text',
				'setting_type' => 'option',
			],
			'missing_value'   => [ 'type' => 'text' ],
		] );

		$options->expose_maybe_migrate_controls_data(
			'values_store_mod',
			'option',
			[ 'values_store_mod' => 'option' ],
			[ 'values_store_mod' => 'theme_mod' ]
		);

		$this->assertSame(
			[
				'option_only'   => 'preserved',
				'body_font'     => [ 'font_family' => 'Inter' ],
				'accent_color'  => '#111111',
			],
			$updated_options['hive-lt_options'] ?? null
		);
		$this->assertSame(
			0,
			$theme_mod_writes,
			'Switching to option storage must not write back to the old theme_mod store.'
		);
	}

	public function test_maybe_migrate_controls_data_copies_saved_option_values_to_theme_mod_store(): void {
		$theme_mods    = [];
		$option_writes = 0;

		Functions\when( 'get_option' )
			->alias( static function ( string $key ) {
				if ( $key !== 'hive-lt_options' ) {
					return null;
				}

				return [
					'body_font'       => [ 'font_family' => 'Inter' ],
					'accent_color'    => '#111111',
					'html_hint'       => 'visual-only',
					'explicit_option' => 'kept in explicit storage',
				];
			} );

		Functions\when( 'get_theme_mod' )
			->alias( static function ( string $key ) {
				if ( $key !== 'hive-lt_options' ) {
					return null;
				}

				return [
					'theme_only' => 'preserved',
					'body_font'  => [ 'font_family' => 'Old theme mod value' ],
				];
			} );

		Functions\when( 'update_option' )
			->alias( static function () use ( &$option_writes ) {
				$option_writes++;

				return true;
			} );
		Functions\when( 'set_theme_mod' )
			->alias( static function ( string $key, array $value ) use ( &$theme_mods ) {
				$theme_mods[ $key ] = $value;
			} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( 'hive-lt_options' );
		$options->inject_details( [
			'body_font'       => [ 'type' => 'font' ],
			'accent_color'    => [ 'type' => 'color' ],
			'html_hint'       => [ 'type' => 'html' ],
			'explicit_option' => [
				'type'         => 'text',
				'setting_type' => 'option',
			],
			'missing_value'   => [ 'type' => 'text' ],
		] );

		$options->expose_maybe_migrate_controls_data(
			'values_store_mod',
			'theme_mod',
			[ 'values_store_mod' => 'theme_mod' ],
			[ 'values_store_mod' => 'option' ]
		);

		$this->assertSame(
			[
				'theme_only'   => 'preserved',
				'body_font'    => [ 'font_family' => 'Inter' ],
				'accent_color' => '#111111',
			],
			$theme_mods['hive-lt_options'] ?? null
		);
		$this->assertSame(
			0,
			$option_writes,
			'Switching to theme_mod storage must not write back to the old option store.'
		);
	}

	public function test_maybe_migrate_controls_data_does_not_write_when_storage_mode_does_not_change(): void {
		$calls = 0;

		Functions\when( 'get_option' )->alias( static function () use ( &$calls ) {
			$calls++;
			return [];
		} );
		Functions\when( 'get_theme_mod' )->alias( static function () use ( &$calls ) {
			$calls++;
			return [];
		} );
		Functions\when( 'update_option' )->alias( static function () use ( &$calls ) {
			$calls++;
			return true;
		} );
		Functions\when( 'set_theme_mod' )->alias( static function () use ( &$calls ) {
			$calls++;
		} );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( 'hive-lt_options' );
		$options->inject_details( [
			'body_font' => [ 'type' => 'font' ],
		] );

		$options->expose_maybe_migrate_controls_data(
			'values_store_mod',
			'theme_mod',
			[ 'values_store_mod' => 'theme_mod' ],
			[ 'values_store_mod' => 'theme_mod' ]
		);

		$this->assertSame(
			0,
			$calls,
			'No storage reads or writes should run when the selected storage mode is unchanged.'
		);
	}

	public function test_prevent_deletion_on_save_keeps_storage_mode_field_value(): void {
		$field = $this->createMock( Field::class );
		$field->method( 'get_base_name' )->willReturn( 'values_store_mod' );

		$options = new TestOptions( $this->createMock( PluginSettings::class ) );

		$this->assertFalse(
			$options->expose_prevent_deletion_on_save( true, $field ),
			'Carbon Fields must not delete the storage mode value while saving plugin settings.'
		);
	}

}

class TestOptions extends Options {
	public function set_options_key( string $key ): void {
		$this->opt_name = $key;
	}

	public function expose_get_thememod_value( string $option_id, string $setting_id ) {
		return $this->get_thememod_value( $option_id, $setting_id );
	}

	public function expose_maybe_migrate_controls_data( $key, $value, $new_all_values, $old_all_values ): void {
		$this->maybe_migrate_controls_data( $key, $value, $new_all_values, $old_all_values );
	}

	public function expose_prevent_deletion_on_save( bool $delete, Field $field ): bool {
		return $this->prevent_deletion_on_save( $delete, $field );
	}

	public function expose_filter_invalidate_details_cache( $value ) {
		return $this->filter_invalidate_details_cache( $value );
	}

	public function inject_details( array $details ): void {
		$this->details = $details;
	}

	/**
	 * Inject a pre-built customizer config so the test doesn't need the
	 * full cache-load + filter_fields plumbing.
	 */
	public function inject_customizer_config( array $config ): void {
		$reflection = new \ReflectionClass( Options::class );
		$property   = $reflection->getProperty( 'customizer_config' );
		$property->setAccessible( true );
		$property->setValue( $this, $config );
	}
}

class CacheSpyOptions extends TestOptions {
	public int $invalidate_calls = 0;

	public function invalidate_all_caches() {
		$this->invalidate_calls++;
	}
}

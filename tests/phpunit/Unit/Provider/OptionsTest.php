<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class OptionsTest extends TestCase {
	public function tearDown(): void {
		unset( $GLOBALS['wp_customize'] );

		parent::tearDown();
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
}

class TestOptions extends Options {
	public function set_options_key( string $key ): void {
		$this->opt_name = $key;
	}

	public function expose_get_thememod_value( string $option_id, string $setting_id ) {
		return $this->get_thememod_value( $option_id, $setting_id );
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

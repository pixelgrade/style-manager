<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\LayoutSection;

/**
 * style-manager#220: only Style Manager's own save paths make a Content Inset
 * explicit. The Customizer, the Site Editor panel and `wp pixelgrade sm set`
 * all publish a changeset, so each save runs `customize_save_sm_content_inset`,
 * which records the saved value as the explicit one. A reset (an empty value)
 * removes the marker.
 */
class ContentInsetSaveMarkerTest extends TestCase {
	private const MARKER = 'style_manager_content_inset_explicit';

	private array $options = [];
	private array $updated = [];
	private array $deleted = [];

	public function tearDown(): void {
		unset( $GLOBALS['wp_customize'], $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	public function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->alias( function ( $name, $default = false ) {
			return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default;
		} );
		Functions\when( 'update_option' )->alias( function ( $name, $value ) {
			$this->updated[ $name ] = $value;
			$this->options[ $name ] = $value;

			return true;
		} );
		Functions\when( 'delete_option' )->alias( function ( $name ) {
			$this->deleted[] = $name;
			unset( $this->options[ $name ] );

			return true;
		} );
	}

	private function save( array $options, $value ): void {
		$this->options = $options;
		$this->updated = [];
		$this->deleted = [];

		style_manager_mark_content_inset_saved( $value );
	}

	public function test_a_saved_value_is_marked_explicit(): void {
		$this->save( [], 140 );

		$this->assertSame( [ self::MARKER => '140' ], $this->updated );
		$this->assertSame( [], $this->deleted );
	}

	public function test_a_fractional_value_is_marked_exactly(): void {
		$this->save( [], '187.5' );

		$this->assertSame( '187.5', $this->updated[ self::MARKER ] );
	}

	public function test_a_reset_removes_the_marker(): void {
		foreach ( [ '', null, 'wide' ] as $reset ) {
			$this->save( [ 'sm_content_inset' => '140', self::MARKER => '140' ], $reset );

			$this->assertSame( [ self::MARKER ], $this->deleted, var_export( $reset, true ) );
			$this->assertSame( [], $this->updated, var_export( $reset, true ) );
		}
	}

	public function test_re_saving_a_legacy_inset_pins_its_small_rail(): void {
		// A legacy inset still sets the Small rail (as in 2.6). Once the save makes
		// the inset explicit, the Small rail decouples, so keep that width.
		$this->save( [ 'sm_content_inset' => '180' ], 150 );

		$this->assertSame( [ 'sm_rail_small' => 180, self::MARKER => '150' ], $this->updated );
	}

	public function test_re_saving_an_explicit_inset_does_not_touch_the_rails(): void {
		$this->save( [ 'sm_content_inset' => '180', self::MARKER => '180' ], 150 );

		$this->assertSame( [ self::MARKER => '150' ], $this->updated );
	}

	public function test_a_first_save_without_a_previous_inset_does_not_touch_the_rails(): void {
		$this->save( [ 'sm_content_inset' => '' ], 150 );

		$this->assertSame( [ self::MARKER => '150' ], $this->updated );
	}

	public function test_saved_rail_settings_are_never_overwritten(): void {
		foreach ( [
			[ 'sm_rail_scale' => '180' ],
			[ 'sm_rail_pitch' => '0' ],
			[ 'sm_rail_small' => '260' ],
		] as $rail ) {
			$this->save( array_merge( [ 'sm_content_inset' => '180' ], $rail ), 150 );

			$this->assertSame( [ self::MARKER => '150' ], $this->updated, var_export( $rail, true ) );
		}
	}

	public function test_a_reset_of_a_legacy_inset_does_not_touch_the_rails(): void {
		$this->save( [ 'sm_content_inset' => '180' ], '' );

		$this->assertSame( [], $this->updated );
	}

	public function test_the_layout_section_marks_the_value_every_save_path_publishes(): void {
		$this->options = [];
		$section       = new LayoutSection();

		// The hook provider registers actions through add_filter().
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function ( $hook, $callback, $priority ) {
				return $hook . '|' . ( is_array( $callback ) ? $callback[1] : 'closure' ) . '|' . $priority;
			}
		);
		$registered = [];
		Functions\when( 'add_filter' )->alias(
			function ( $hook, $callback ) use ( &$registered ) {
				$registered[ $hook ] = $callback;

				return true;
			}
		);
		$section->register_hooks();

		$this->assertArrayHasKey( 'customize_save_sm_content_inset', $registered );

		$setting = new class() {
			public function post_value( $default = null ) {
				return 120;
			}
		};
		\call_user_func( $registered['customize_save_sm_content_inset'], $setting );

		$this->assertSame( '120', $this->options[ self::MARKER ] );
	}

	public function test_a_customizer_publish_still_pins_the_stored_legacy_small_rail(): void {
		// While a changeset publishes, the manager previews it: get_option() may
		// already answer the new value, so the stored one is read from the table.
		$GLOBALS['wp_customize'] = new class() {
			public function is_preview(): bool {
				return true;
			}

			public function unsanitized_post_values(): array {
				return [ 'sm_content_inset' => 150 ];
			}
		};
		$GLOBALS['wpdb'] = new class() {
			public string $options = 'wp_options';

			public function prepare( $query, ...$args ) {
				return str_replace( '%s', "'" . $args[0] . "'", $query );
			}

			public function get_var( $query ) {
				return false !== strpos( $query, "'sm_content_inset'" ) ? '180' : null;
			}
		};
		Functions\when( 'maybe_unserialize' )->returnArg( 1 );

		$this->save( [ 'sm_content_inset' => 150 ], 150 );

		$this->assertSame( [ 'sm_rail_small' => 180, self::MARKER => '150' ], $this->updated );
	}
}

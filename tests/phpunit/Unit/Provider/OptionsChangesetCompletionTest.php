<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

/**
 * Publishing a changeset that contains some keys of the multidimensional
 * options root must explicitly carry every other stored key of that root,
 * so read-time filters can never be persisted for settings the user did
 * not change. See issue #127.
 */
class OptionsChangesetCompletionTest extends TestCase {
	public function tearDown(): void {
		unset( $GLOBALS['wpdb'] );

		parent::tearDown();
	}

	protected function make_options( string $options_key ): ChangesetCompletionTestOptions {
		$options = new ChangesetCompletionTestOptions( $this->createMock( PluginSettings::class ) );
		$options->set_options_key( $options_key );

		return $options;
	}

	protected function mock_wpdb_row( string $expected_option_name, $row_value ): void {
		$GLOBALS['wpdb'] = new class( $expected_option_name, $row_value ) {
			public string $options = 'wp_options';
			private string $expected;
			private $row;

			public function __construct( string $expected, $row ) {
				$this->expected = $expected;
				$this->row      = $row;
			}

			public function prepare( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}

			public function get_var( $query ) {
				if ( false === strpos( (string) $query, "'" . $this->expected . "'" ) ) {
					return null;
				}

				return $this->row;
			}
		};

		Functions\when( 'maybe_unserialize' )->alias( static function ( $value ) {
			if ( is_string( $value ) ) {
				$unserialized = @unserialize( $value );
				if ( false !== $unserialized || 'b:0;' === $value ) {
					return $unserialized;
				}
			}

			return $value;
		} );
	}

	public function test_publish_completes_missing_theme_mod_siblings_from_raw_storage(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 7 );

		$stored_root = [
			'display_font' => [ 'font_size' => [ 'value' => 107.7 ] ],
			'body_font'    => [ 'font_size' => [ 'value' => 16.2 ] ],
			'meta_font'    => [ 'font_size' => [ 'value' => 16.2 ] ],
		];
		$this->mock_wpdb_row( 'theme_mods_anima', serialize( [ 'mies-lt_options' => $stored_root ] ) );

		$options = $this->make_options( 'mies-lt_options' );

		$data = [
			'anima::mies-lt_options[display_font]' => [
				'value' => [ 'font_size' => [ 'value' => 54 ] ],
				'type'  => 'theme_mod',
			],
			'sm_spacing_level'                     => [ 'value' => '1', 'type' => 'option' ],
		];

		$result = $options->expose_complete_options_root_changeset_data( $data, [ 'status' => 'publish' ] );

		// The user's own entry is untouched.
		$this->assertSame( 54, $result['anima::mies-lt_options[display_font]']['value']['font_size']['value'] );

		// Missing siblings are added with their raw stored values.
		$this->assertArrayHasKey( 'anima::mies-lt_options[body_font]', $result );
		$this->assertSame( 16.2, $result['anima::mies-lt_options[body_font]']['value']['font_size']['value'] );
		$this->assertSame( 'theme_mod', $result['anima::mies-lt_options[body_font]']['type'] );
		$this->assertSame( 7, $result['anima::mies-lt_options[body_font]']['user_id'] );
		$this->assertArrayHasKey( 'anima::mies-lt_options[meta_font]', $result );

		// Unrelated settings stay as-is.
		$this->assertSame( '1', $result['sm_spacing_level']['value'] );
	}

	public function test_non_publish_statuses_leave_data_untouched(): void {
		$options = $this->make_options( 'mies-lt_options' );

		$data = [
			'anima::mies-lt_options[display_font]' => [
				'value' => [ 'font_size' => [ 'value' => 54 ] ],
				'type'  => 'theme_mod',
			],
		];

		foreach ( [ [ 'status' => 'auto-draft' ], [ 'status' => 'draft' ], [ 'status' => null ], [] ] as $context ) {
			$this->assertSame( $data, $options->expose_complete_options_root_changeset_data( $data, $context ) );
		}
	}

	public function test_changesets_without_root_keys_are_untouched(): void {
		$options = $this->make_options( 'mies-lt_options' );

		$data = [
			'sm_collection_title_position' => [ 'value' => 'sideways', 'type' => 'option' ],
		];

		$this->assertSame( $data, $options->expose_complete_options_root_changeset_data( $data, [ 'status' => 'publish' ] ) );
	}

	public function test_publish_completes_option_storage_roots_without_stylesheet_prefix(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$stored_root = [
			'display_font' => [ 'font_size' => [ 'value' => 107.7 ] ],
			'body_font'    => [ 'font_size' => [ 'value' => 16.2 ] ],
		];
		$this->mock_wpdb_row( 'mies-lt_options', serialize( $stored_root ) );

		$options = $this->make_options( 'mies-lt_options' );

		$data = [
			'mies-lt_options[display_font]' => [
				'value' => [ 'font_size' => [ 'value' => 54 ] ],
				'type'  => 'option',
			],
		];

		$result = $options->expose_complete_options_root_changeset_data( $data, [ 'status' => 'publish' ] );

		$this->assertArrayHasKey( 'mies-lt_options[body_font]', $result );
		$this->assertSame( 16.2, $result['mies-lt_options[body_font]']['value']['font_size']['value'] );
		$this->assertSame( 'option', $result['mies-lt_options[body_font]']['type'] );
	}

	public function test_existing_changeset_entries_are_never_overwritten(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 1 );

		$stored_root = [
			'display_font' => [ 'font_size' => [ 'value' => 107.7 ] ],
			'body_font'    => [ 'font_size' => [ 'value' => 16.2 ] ],
		];
		$this->mock_wpdb_row( 'theme_mods_anima', serialize( [ 'mies-lt_options' => $stored_root ] ) );

		$options = $this->make_options( 'mies-lt_options' );

		$data = [
			'anima::mies-lt_options[display_font]' => [
				'value' => [ 'font_size' => [ 'value' => 54 ] ],
				'type'  => 'theme_mod',
			],
			'anima::mies-lt_options[body_font]'    => [
				'value' => [ 'font_size' => [ 'value' => 20 ] ],
				'type'  => 'theme_mod',
			],
		];

		$result = $options->expose_complete_options_root_changeset_data( $data, [ 'status' => 'publish' ] );

		$this->assertSame( 20, $result['anima::mies-lt_options[body_font]']['value']['font_size']['value'] );
	}
}

class ChangesetCompletionTestOptions extends Options {
	public function set_options_key( string $key ): void {
		$this->opt_name = $key;
	}

	public function expose_complete_options_root_changeset_data( $data, $context ) {
		return $this->filter_complete_options_root_changeset_data( $data, $context );
	}
}

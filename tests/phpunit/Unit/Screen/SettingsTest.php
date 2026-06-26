<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Functions;
use Carbon_Fields\Datastore\Datastore;
use Pixelgrade\StyleManager\Capabilities;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Screen\Settings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class SettingsTest extends TestCase {
	public function tearDown(): void {
		unset( $_REQUEST['style_manager_settings_nonce'], $_POST['style_manager_settings_nonce'] );

		parent::tearDown();
	}

	public function test_delete_customizer_settings_removes_current_options_key_from_both_storage_roots(): void {
		$deleted_options    = [];
		$removed_theme_mods = [];
		$response           = null;

		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'remove_theme_mod' )->alias( static function ( string $key ) use ( &$removed_theme_mods ) {
			$removed_theme_mods[] = $key;
		} );
		Functions\when( 'delete_option' )->alias( static function ( string $key ) use ( &$deleted_options ) {
			$deleted_options[] = $key;

			return true;
		} );
		Functions\when( 'wp_send_json_success' )->alias( static function ( $data ) use ( &$response ) {
			$response = [
				'success' => true,
				'data'    => $data,
			];
		} );

		$options = $this->createMock( Options::class );
		$options->method( 'get_options_key' )->willReturn( 'hive-lt_options' );
		$options->expects( $this->once() )->method( 'invalidate_all_caches' );

		$this->create_settings( $options )->delete_customizer_settings();

		$this->assertSame( [ 'hive-lt_options' ], $removed_theme_mods );
		$this->assertSame(
			[ 'hive-lt_options' ],
			$deleted_options,
			'Resetting Style Manager settings must also delete option-backed values.'
		);
		$this->assertSame(
			[
				'success' => true,
				'data'    => 'Deleted the "hive-lt_options" options key!',
			],
			$response
		);
	}

	public function test_permission_nonce_callback_requires_capability_and_valid_settings_nonce(): void {
		$_REQUEST['style_manager_settings_nonce'] = 'nonce-value';

		Functions\expect( 'current_user_can' )
			->once()
			->with( Capabilities::MANAGE_OPTIONS )
			->andReturn( true );
		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'nonce-value' )
			->andReturn( 'nonce-value' );
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 'nonce-value' )
			->andReturn( 'nonce-value' );
		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'nonce-value', 'style_manager_settings_nonce' )
			->andReturn( 1 );

		$this->assertTrue( (bool) $this->create_settings()->permission_nonce_callback() );
	}

	public function test_permission_nonce_callback_rejects_users_without_capability_before_nonce_check(): void {
		$_REQUEST['style_manager_settings_nonce'] = 'nonce-value';

		Functions\expect( 'current_user_can' )
			->once()
			->with( Capabilities::MANAGE_OPTIONS )
			->andReturn( false );
		Functions\expect( 'wp_verify_nonce' )->never();

		$this->assertFalse( (bool) $this->create_settings()->permission_nonce_callback() );
	}

	public function test_settings_action_link_points_to_style_manager_settings_screen(): void {
		Functions\expect( 'menu_page_url' )
			->once()
			->with( Settings::MENU_SLUG, false )
			->andReturn( 'http://example.test/wp-admin/options-general.php?page=style-manager' );
		Functions\expect( 'esc_url' )
			->once()
			->with( 'http://example.test/wp-admin/options-general.php?page=style-manager' )
			->andReturn( 'http://example.test/wp-admin/options-general.php?page=style-manager' );
		Functions\expect( 'esc_html__' )
			->once()
			->with( 'Settings', '__plugin_txtd' )
			->andReturn( 'Settings' );

		$this->assertSame(
			[
				'settings'   => '<a href="http://example.test/wp-admin/options-general.php?page=style-manager">Settings</a>',
				'deactivate' => 'Deactivate',
			],
			$this->create_settings()->expose_add_action_links( [ 'deactivate' => 'Deactivate' ] )
		);
	}

	public function test_reset_customizer_settings_control_is_rendered_as_a_real_button(): void {
		Functions\expect( 'esc_html__' )
			->once()
			->with( 'Reset Customizer Settings', '__plugin_txtd' )
			->andReturn( 'Reset Customizer Settings' );

		$this->assertSame(
			'<br><div class="reset_style_manager_settings"><button type="button" class="button" id="reset_customizer_settings">Reset Customizer Settings</button></div>',
			$this->create_settings()->expose_get_reset_customizer_settings_html()
		);
	}

	public function test_settings_page_parent_tracks_single_site_and_network_admin(): void {
		Functions\expect( 'is_network_admin' )
			->twice()
			->andReturn( false, true );

		$settings = $this->create_settings();

		$this->assertSame( 'options-general.php', $settings->expose_get_page_parent() );
		$this->assertSame( 'settings.php', $settings->expose_get_page_parent() );
	}

	private function create_settings( ?Options $options = null ): TestSettings {
		return new TestSettings(
			$options ?? $this->createMock( Options::class ),
			$this->createMock( Datastore::class ),
			$this->createMock( LoggerInterface::class )
		);
	}
}

class TestSettings extends Settings {
	public function expose_add_action_links( array $links ): array {
		return $this->add_action_links( $links );
	}

	public function expose_get_page_parent(): string {
		return $this->get_page_parent();
	}

	public function expose_get_reset_customizer_settings_html(): string {
		return $this->get_reset_customizer_settings_html();
	}
}

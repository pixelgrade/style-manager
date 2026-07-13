<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\LocalFonts;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Screen\GeneralAdmin;
use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Tests for GeneralAdmin's "host cloud fonts locally" notice condition helper
 * and its AJAX handlers.
 *
 * @since 2.4.0
 */
class GeneralAdminLocalFontsNoticeTest extends TestCase {

	// -----------------------------------------------------------------
	// should_show_local_fonts_notice()
	// -----------------------------------------------------------------

	public function test_notice_is_shown_when_all_conditions_are_met(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\when( 'get_option' )->justReturn( false );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			[ 'Uncut Sans' ],
			[ 'Uncut Sans' => false ]
		);

		$this->assertTrue( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_current_user_cannot_manage_options(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			[ 'Uncut Sans' ],
			[ 'Uncut Sans' => false ]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_cloud_fonts_setting_is_disabled(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => false, 'typography_host_cloud_fonts_locally' => true ] ),
			[ 'Uncut Sans' ],
			[ 'Uncut Sans' => false ]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_local_hosting_setting_is_disabled(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => false ] ),
			[ 'Uncut Sans' ],
			[ 'Uncut Sans' => false ]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_previously_dismissed(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\when( 'get_option' )->justReturn( 1 );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			[ 'Uncut Sans' ],
			[ 'Uncut Sans' => false ]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_there_are_no_used_cloud_font_families(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\when( 'get_option' )->justReturn( false );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			[],
			[]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	public function test_notice_is_hidden_when_all_used_font_families_are_already_healthy(): void {
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( true );
		Functions\when( 'get_option' )->justReturn( false );

		$general_admin = $this->make_general_admin(
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			[ 'Uncut Sans', 'Quentin' ],
			[ 'Uncut Sans' => true, 'Quentin' => true ]
		);

		$this->assertFalse( $this->invoke_should_show( $general_admin ) );
	}

	// -----------------------------------------------------------------
	// wp_ajax_style_manager_host_fonts_locally
	// -----------------------------------------------------------------

	public function test_host_fonts_locally_denies_unauthorized_users(): void {
		$json_error_called = false;

		Functions\expect( 'check_ajax_referer' )->once()->with( 'style_manager_host_fonts_locally', 'nonce_host' );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\when( 'wp_send_json_error' )->alias( static function() use ( &$json_error_called ) {
			$json_error_called = true;

			throw new \RuntimeException( 'host_fonts_locally_denied' );
		} );

		$local_fonts_provider = $this->createMock( LocalFonts::class );
		$local_fonts_provider->expects( $this->never() )->method( 'mirror_used_fonts' );

		$general_admin = $this->make_general_admin( null, [], [], $local_fonts_provider );

		try {
			$general_admin->host_fonts_locally();
			$this->fail( 'Denied request should stop at wp_send_json_error().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'host_fonts_locally_denied', $e->getMessage() );
		}

		$this->assertTrue( $json_error_called );
	}

	public function test_host_fonts_locally_reports_full_success_when_every_family_mirrors(): void {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->once()->andReturn( true );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$response = null;
		Functions\when( 'wp_send_json_success' )->alias( static function( $data = null ) use ( &$response ) {
			$response = $data;

			throw new \RuntimeException( 'host_fonts_locally_success' );
		} );

		$local_fonts_provider = $this->createMock( LocalFonts::class );
		$local_fonts_provider->expects( $this->once() )->method( 'mirror_used_fonts' );

		// Both families are unhealthy in the "before" snapshot; is_healthy() is
		// consulted again per family after mirror_used_fonts() runs, so both
		// calls return true the second time around (full success).
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturnOnConsecutiveCalls( false, false, true, true );

		$general_admin = $this->make_general_admin_with_store( $local_font_store, [ 'Uncut Sans', 'Quentin' ], $local_fonts_provider );

		try {
			$general_admin->host_fonts_locally();
			$this->fail( 'Successful request should stop at wp_send_json_success().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'host_fonts_locally_success', $e->getMessage() );
		}

		$this->assertSame( 2, $response['mirrored'] );
		$this->assertSame( 0, $response['failed'] );
		$this->assertSame( '2 fonts are now served from your site.', $response['message'] );
	}

	public function test_host_fonts_locally_reports_partial_failure_when_a_family_stays_unhealthy(): void {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->once()->andReturn( true );
		Functions\when( '_n' )->alias( static function( string $single, string $plural, int $number ): string {
			return 1 === $number ? $single : $plural;
		} );

		$response = null;
		Functions\when( 'wp_send_json_success' )->alias( static function( $data = null ) use ( &$response ) {
			$response = $data;

			throw new \RuntimeException( 'host_fonts_locally_success' );
		} );

		$local_fonts_provider = $this->createMock( LocalFonts::class );
		$local_fonts_provider->expects( $this->once() )->method( 'mirror_used_fonts' );

		$local_font_store = $this->createMock( LocalFontStore::class );
		// Before: both unhealthy. After: "Uncut Sans" recovers, "Broken Font" stays unhealthy.
		$local_font_store->method( 'is_healthy' )->willReturnOnConsecutiveCalls( false, false, true, false );

		$general_admin = $this->make_general_admin_with_store( $local_font_store, [ 'Uncut Sans', 'Broken Font' ], $local_fonts_provider );

		try {
			$general_admin->host_fonts_locally();
			$this->fail( 'Successful (partial) request should stop at wp_send_json_success().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'host_fonts_locally_success', $e->getMessage() );
		}

		$this->assertSame( 1, $response['mirrored'] );
		$this->assertSame( 1, $response['failed'] );
		$this->assertSame( '1 font could not be downloaded right now — a retry is scheduled.', $response['message'] );
	}

	// -----------------------------------------------------------------
	// wp_ajax_style_manager_dismiss_local_fonts_notice
	// -----------------------------------------------------------------

	public function test_dismiss_local_fonts_notice_denies_unauthorized_users(): void {
		Functions\expect( 'check_ajax_referer' )->once()->with( 'style_manager_dismiss_local_fonts_notice', 'nonce_dismiss' );
		Functions\expect( 'current_user_can' )->once()->with( 'manage_options' )->andReturn( false );
		Functions\expect( 'update_option' )->never();
		Functions\when( 'wp_send_json_error' )->alias( static function() {
			throw new \RuntimeException( 'dismiss_denied' );
		} );

		try {
			$this->make_general_admin()->dismiss_local_fonts_notice();
			$this->fail( 'Denied request should stop at wp_send_json_error().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'dismiss_denied', $e->getMessage() );
		}
	}

	public function test_dismiss_local_fonts_notice_persists_the_dismissed_option(): void {
		Functions\expect( 'check_ajax_referer' )->once();
		Functions\expect( 'current_user_can' )->once()->andReturn( true );
		Functions\expect( 'update_option' )
			->once()
			->with( 'style_manager_local_fonts_notice_dismissed', 1, false )
			->andReturn( true );
		Functions\when( 'wp_send_json_success' )->alias( static function() {
			throw new \RuntimeException( 'dismiss_success' );
		} );

		try {
			$this->make_general_admin()->dismiss_local_fonts_notice();
			$this->fail( 'Successful request should stop at wp_send_json_success().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'dismiss_success', $e->getMessage() );
		}
	}

	// -----------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------

	private function invoke_should_show( GeneralAdmin $general_admin ): bool {
		return (bool) PHPUnitUtil::getProtectedMethod( $general_admin, 'should_show_local_fonts_notice' )->invoke( $general_admin );
	}

	private function settings( array $values ): PluginSettings {
		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings->method( 'get' )->willReturnCallback(
			static function ( string $key, $default = null ) use ( $values ) {
				return $values[ $key ] ?? $default;
			}
		);

		return $plugin_settings;
	}

	/**
	 * Build a GeneralAdmin with used families and a family => is_healthy map.
	 *
	 * @param PluginSettings|null $plugin_settings
	 * @param array               $used_families
	 * @param array               $health_by_family
	 * @param LocalFonts|null     $local_fonts_provider
	 */
	private function make_general_admin(
		?PluginSettings $plugin_settings = null,
		array $used_families = [],
		array $health_by_family = [],
		?LocalFonts $local_fonts_provider = null
	): GeneralAdmin {
		$local_font_store = $this->createMock( LocalFontStore::class );
		$local_font_store->method( 'is_healthy' )->willReturnCallback(
			static function ( string $family ) use ( $health_by_family ) {
				return $health_by_family[ $family ] ?? false;
			}
		);

		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( $used_families );

		return new GeneralAdmin(
			$local_font_store,
			$sm_fonts,
			$local_fonts_provider ?? $this->createMock( LocalFonts::class ),
			$plugin_settings ?? $this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			$this->createMock( LoggerInterface::class )
		);
	}

	/**
	 * Build a GeneralAdmin using an already-configured LocalFontStore mock
	 * (used when a test needs to control the exact sequence of is_healthy() calls).
	 */
	private function make_general_admin_with_store( LocalFontStore $local_font_store, array $used_families, LocalFonts $local_fonts_provider ): GeneralAdmin {
		$sm_fonts = $this->createMock( Fonts::class );
		$sm_fonts->method( 'get_used_cloud_font_families' )->willReturn( $used_families );

		return new GeneralAdmin(
			$local_font_store,
			$sm_fonts,
			$local_fonts_provider,
			$this->settings( [ 'typography_cloud_fonts' => true, 'typography_host_cloud_fonts_locally' => true ] ),
			$this->createMock( LoggerInterface::class )
		);
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Lab\Config;
use Pixelgrade\StyleManager\Lab\LabAdminPage;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class LabAdminPageTest extends TestCase {
	public function test_ajax_config_denies_unauthorized_users_before_nonce_or_config_work(): void {
		$response = null;

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'manage_options' )
			->andReturn( false );
		Functions\expect( 'check_ajax_referer' )->never();
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wp_send_json_error' )->alias( static function( $data, int $status_code ) use ( &$response ) {
			$response = [
				'data'   => $data,
				'status' => $status_code,
			];

			throw new \RuntimeException( 'lab_ajax_denied' );
		} );
		Functions\expect( 'wp_send_json_success' )->never();

		$config = new Config(
			static function(): array {
				throw new \RuntimeException( 'Config should not be built for denied users.' );
			}
		);

		try {
			( new LabAdminPage( $config ) )->ajax_config();
			$this->fail( 'Denied Lab AJAX request should stop at wp_send_json_error().' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'lab_ajax_denied', $e->getMessage() );
		}

		$this->assertSame(
			[
				'data'   => 'You are not allowed to access the Style Manager Lab.',
				'status' => 403,
			],
			$response
		);
	}
}

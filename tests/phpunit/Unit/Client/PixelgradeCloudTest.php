<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Client;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Client\PixelgradeCloud;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class PixelgradeCloudTest extends TestCase {
	public function test_fetch_design_assets_retries_without_font_palettes_using_a_json_get_body(): void {
		$requests = [];
		$responses = [
			json_encode( [
				'code' => 'internal_server_error',
				'data' => [],
			] ),
			json_encode( [
				'code' => 'success',
				'data' => [
					'cloud_fonts' => [
						'trueno' => [
							'font_family' => 'Trueno',
						],
					],
				],
			] ),
		];

		Functions\when( 'apply_filters' )->alias( static function( string $hook, $value, ...$rest ) {
			return $value;
		} );
		Functions\when( 'home_url' )->alias( static function( string $path = '/' ) {
			return 'http://localhost:8893' . $path;
		} );
		Functions\when( 'wp_json_encode' )->alias( static function( $value ) {
			return json_encode( $value );
		} );

		$client = new TestPixelgradeCloud(
			[
				'cloud' => [
					'getDesignAssets' => [
						'method' => 'GET',
						'url' => 'https://cloud.pixelgrade.com/wp-json/pixcloud/v1/front/design_assets',
					],
				],
			],
			$this->createMock( LoggerInterface::class ),
			$requests,
			$responses
		);

		$result = $client->fetch_design_assets();

		$this->assertSame(
			[
				'cloud_fonts' => [
					'trueno' => [
						'font_family' => 'Trueno',
					],
				],
			],
			$result
		);
		$this->assertCount( 2, $requests );
		$this->assertSame( 'https://cloud.pixelgrade.com/wp-json/pixcloud/v1/front/design_assets', $requests[0]['url'] );
		$this->assertSame( 'GET', $requests[0]['method'] );

		$initial_request = json_decode( $requests[0]['body'], true );
		$fallback_request = json_decode( $requests[1]['body'], true );

		$this->assertContains( 'font_palettes', $initial_request['types'] );
		$this->assertNotContains( 'font_palettes', $fallback_request['types'] );
	}
}

class TestPixelgradeCloud extends PixelgradeCloud {
	private array $requests;
	private array $responses;

	public function __construct( array $endpoints, LoggerInterface $logger, array &$requests, array &$responses ) {
		parent::__construct( $endpoints, $logger );

		$this->requests = &$requests;
		$this->responses = &$responses;
	}

	protected function get_active_theme_data(): array {
		return [
			'slug' => 'anima',
		];
	}

	protected function get_site_data(): array {
		return [
			'url' => 'http://localhost:8893/',
		];
	}

	protected function execute_design_assets_request( string $url, string $method, string $request_body ): ?string {
		$this->requests[] = [
			'url' => $url,
			'method' => $method,
			'body' => $request_body,
		];

		return array_shift( $this->responses );
	}
}

<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_REST_Request', false ) ) {
		class WP_REST_Request {
			private array $params;

			public function __construct( array $params = [] ) {
				$this->params = $params;
			}

			public function get_param( string $param ) {
				return $this->params[ $param ] ?? null;
			}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\FontPalettes;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Provider\FrontendOutput;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Screen\EditWithBlocks;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	class SiteEditorEndpointsRestSaveTest extends TestCase {
		public function test_locked_plus_save_endpoint_strips_premium_values_before_saving_mixed_payload(): void {
			$this->mock_wordpress_functions();
			$this->mock_plus_entitlement_bridge( true, false );

			$persistable_values = [
				'sm_page_transitions_enable' => true,
				'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
			];

			$headless_customizer = $this->createMock( HeadlessCustomizer::class );
			$headless_customizer
				->expects( $this->once() )
				->method( 'save' )
				->with( $persistable_values )
				->willReturn( [ 'saved' => array_keys( $persistable_values ) ] );
			$headless_customizer
				->expects( $this->once() )
				->method( 'get_settings_values' )
				->willReturn( $persistable_values );

			$sm_fonts = $this->createMock( Fonts::class );
			$sm_fonts
				->expects( $this->exactly( 2 ) )
				->method( 'getFontsDynamicStyle' )
				->willReturn( '' );

			$frontend_output = $this->createMock( FrontendOutput::class );
			$frontend_output
				->expects( $this->exactly( 2 ) )
				->method( 'get_dynamic_style' )
				->willReturn( '' );

			$endpoints = new \Pixelgrade\StyleManager\Tests\Unit\Provider\TestSiteEditorEndpoints(
				$headless_customizer,
				$this->createMock( EditWithBlocks::class ),
				$sm_fonts,
				$this->createMock( FontPalettes::class ),
				$frontend_output
			);

			$response = $endpoints->handle_save_settings_record(
				new \WP_REST_Request(
					[
						'id'       => 'style-manager',
						'settings' => [
							'sm_page_transitions_enable' => true,
							'sm_advanced_palette_source' => '[{"sources":[{"value":"#123456"}]}]',
							'sm_color_grades_number'     => 8,
							'sm_advanced_palette_output' => '[{"source":["#123456"],"options":{"sm_color_grades_number":8}}]',
						],
					]
				)
			);

			$this->assertSame( 'style-manager', $response['id'] );
			$this->assertSame( 'Design system settings', $response['title'] );
			$this->assertSame( $persistable_values, $response['settings'] );
			$this->assertSame( array_keys( $persistable_values ), $response['saved'] );
			$this->assertSame( [ 'editor' => '', 'frontend' => '' ], $response['css'] );
		}

		private function mock_wordpress_functions(): void {
			Functions\when( 'add_filter' )->justReturn( true );
			Functions\when( 'remove_filter' )->justReturn( true );
			Functions\when( 'esc_html__' )->alias( static fn( string $text ): string => $text );
			Functions\when( 'is_wp_error' )->alias( static fn(): bool => false );
			Functions\when( 'rest_ensure_response' )->alias( static fn( $response ) => $response );
		}

		private function mock_plus_entitlement_bridge( bool $bridge_available, bool $entitled ): void {
			Functions\when( 'has_filter' )->alias( static function( string $hook ) use ( $bridge_available ) {
				if ( 'pixelgrade/has_entitlement' === $hook ) {
					return $bridge_available ? 10 : false;
				}

				return false;
			} );

			Functions\when( 'apply_filters' )->alias( static function( string $hook, $value ) use ( $entitled ) {
				if ( 'pixelgrade/has_entitlement' === $hook ) {
					return $entitled;
				}

				return $value;
			} );
		}
	}
}

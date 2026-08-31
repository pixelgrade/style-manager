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

	if ( ! class_exists( 'WP_Error', false ) ) {
		class WP_Error {
			private string $code;
			private string $message;
			private $data;

			public function __construct( string $code = '', string $message = '', $data = null ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}

			public function get_error_code(): string {
				return $this->code;
			}

			public function get_error_message(): string {
				return $this->message;
			}

			public function get_error_data() {
				return $this->data;
			}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {

	use Brain\Monkey\Actions;
	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Provider\FrontendOutput;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Provider\SettingsWriter;
	use Pixelgrade\StyleManager\Provider\SiteEditorEndpoints;
	use Pixelgrade\StyleManager\Screen\EditWithBlocks;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;

	/**
	 * The Plus gate and the post-save fan-out now live in Provider\SettingsWriter — see
	 * SettingsWriterTest for their behavior. What matters here is that the endpoint
	 * delegates to that writer and keeps the responses it always produced.
	 */
	class SiteEditorEndpointsTest extends TestCase {

		public function test_site_editor_endpoints_require_edit_theme_options_capability(): void {
			Functions\expect( 'current_user_can' )
				->once()
				->with( 'edit_theme_options' )
				->andReturn( false );

			$this->assertFalse( $this->create_endpoints()->check_permissions() );
		}

		public function test_save_delegates_to_the_settings_writer_and_no_longer_fires_settings_saved(): void {
			$this->mock_wordpress_functions();

			$submitted = [ 'sm_page_transitions_enable' => true ];

			$writer = $this->createMock( SettingsWriter::class );
			$writer
				->expects( $this->once() )
				->method( 'save' )
				->with( $submitted )
				->willReturn(
					[
						'saved'    => [ 'sm_page_transitions_enable' ],
						'skipped'  => [],
						'stripped' => [],
					]
				);

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless->method( 'get_settings_values' )->willReturn( $submitted );

			// The action moved INTO SettingsWriter::save() — the endpoint must not
			// fire it a second time (contract §3.3: exactly once per save).
			Actions\expectDone( 'style_manager/settings_saved' )->never();

			$response = $this->create_endpoints( $writer, $headless )->handle_save_settings_record(
				new \WP_REST_Request(
					[
						'id'       => 'style-manager',
						'settings' => $submitted,
					]
				)
			);

			$this->assertSame( 'style-manager', $response['id'] );
			$this->assertSame( 'Design system settings', $response['title'] );
			$this->assertSame( $submitted, $response['settings'] );
			$this->assertSame( [ 'sm_page_transitions_enable' ], $response['saved'] );
			$this->assertSame( [ 'editor' => '', 'frontend' => '' ], $response['css'] );
		}

		public function test_endpoint_still_reports_an_all_stripped_save_as_nothing_to_save(): void {
			$this->mock_wordpress_functions();

			$writer = $this->createMock( SettingsWriter::class );
			$writer
				->method( 'save' )
				->willReturn(
					[
						'saved'    => [],
						'skipped'  => [],
						'stripped' => [
							[
								'id'        => 'sm_color_grades_number',
								'reason'    => SettingsWriter::REASON_PLUS_LOCKED,
								'requested' => 8,
							],
						],
					]
				);

			$response = $this->create_endpoints( $writer )->handle_save_settings_record(
				new \WP_REST_Request(
					[
						'id'       => 'style-manager',
						'settings' => [ 'sm_color_grades_number' => 8 ],
					]
				)
			);

			$this->assertInstanceOf( \WP_Error::class, $response );
			$this->assertSame( 'style_manager_site_editor_nothing_to_save', $response->get_error_code() );
		}

		private function create_endpoints( ?SettingsWriter $writer = null, ?HeadlessCustomizer $headless = null ): SiteEditorEndpoints {
			$fonts = $this->createMock( Fonts::class );
			$fonts->method( 'getFontsDynamicStyle' )->willReturn( '' );

			$frontend_output = $this->createMock( FrontendOutput::class );
			$frontend_output->method( 'get_dynamic_style' )->willReturn( '' );

			return new SiteEditorEndpoints(
				$headless ?: $this->createMock( HeadlessCustomizer::class ),
				$this->createMock( EditWithBlocks::class ),
				$fonts,
				$frontend_output,
				$writer ?: $this->createMock( SettingsWriter::class )
			);
		}

		private function mock_wordpress_functions(): void {
			Functions\when( 'add_filter' )->justReturn( true );
			Functions\when( 'remove_filter' )->justReturn( true );
			Functions\when( 'esc_html__' )->returnArg( 1 );
			Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof \WP_Error );
			Functions\when( 'rest_ensure_response' )->alias( static fn( $response ) => $response );
		}
	}
}

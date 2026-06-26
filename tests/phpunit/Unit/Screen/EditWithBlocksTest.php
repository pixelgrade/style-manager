<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Screen;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Screen\EditWithBlocks;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class EditWithBlocksTest extends TestCase {
	public function test_registers_dynamic_css_on_iframed_block_assets_hook(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		Filters\expectAdded( 'enqueue_block_editor_assets' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );
		Filters\expectAdded( 'enqueue_block_assets' )
			->once()
			->with( Mockery::type( \Closure::class ), 999, 1 );

		$this->create_edit_screen()->register_hooks();
		$this->addToAssertionCount( 2 );
	}

	public function test_dynamic_css_does_not_run_outside_the_admin_editor_canvas(): void {
		Functions\when( 'is_admin' )->justReturn( false );

		$fonts = $this->createMock( Fonts::class );
		$fonts
			->expects( $this->never() )
			->method( 'enqueue_frontend_scripts_styles' );
		$fonts
			->expects( $this->never() )
			->method( 'getFontsDynamicStyle' );

		$frontend_output = $this->createMock( FrontendOutput::class );
		$frontend_output
			->expects( $this->never() )
			->method( 'get_dynamic_style' );

		$this->create_edit_screen( $fonts, $frontend_output )->enqueue_editor_dynamic_css();
	}

	public function test_dynamic_css_respects_disabled_editor_style_setting(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $id = 'post';

			public function is_block_editor(): bool {
				return true;
			}
		} );

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings
			->expects( $this->once() )
			->method( 'get' )
			->with( 'enable_editor_style', true )
			->willReturn( false );

		$fonts = $this->createMock( Fonts::class );
		$fonts
			->expects( $this->never() )
			->method( 'enqueue_frontend_scripts_styles' );
		$fonts
			->expects( $this->never() )
			->method( 'getFontsDynamicStyle' );

		$frontend_output = $this->createMock( FrontendOutput::class );
		$frontend_output
			->expects( $this->never() )
			->method( 'get_dynamic_style' );

		$this->create_edit_screen( $fonts, $frontend_output, $plugin_settings )->enqueue_editor_dynamic_css();
	}

	public function test_dynamic_css_still_renders_for_the_admin_block_editor_canvas(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $id = 'post';

			public function is_block_editor(): bool {
				return true;
			}
		} );

		$fonts = $this->createMock( Fonts::class );
		$fonts
			->expects( $this->once() )
			->method( 'enqueue_frontend_scripts_styles' );
		$fonts
			->expects( $this->once() )
			->method( 'getFontsDynamicStyle' )
			->willReturn( 'body { font-family: serif; }' );

		$frontend_output = $this->createMock( FrontendOutput::class );
		$frontend_output
			->expects( $this->once() )
			->method( 'get_dynamic_style' )
			->willReturn( ':root { --sm-current-accent-color: #123456; }' );

		Functions\expect( 'wp_register_style' )
			->once()
			->with( 'style-manager-editor-dynamic', false, [], \Pixelgrade\StyleManager\VERSION );
		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'style-manager-editor-dynamic' );
		Functions\expect( 'wp_add_inline_style' )
			->once()
			->with( 'style-manager-editor-dynamic', ':root { --sm-current-accent-color: #123456; }body { font-family: serif; }' );

		$this->create_edit_screen( $fonts, $frontend_output )->enqueue_editor_dynamic_css();
	}

	public function test_launcher_target_url_points_to_current_site_editor_editable_entry(): void {
		$this->mock_admin_url();
		Functions\when( 'get_post_type_object' )->alias(
			static function( string $post_type ) {
				return (object) [
					'public'       => 'page' === $post_type,
					'show_in_rest' => 'page' === $post_type,
				];
			}
		);
		Functions\when( 'post_type_supports' )->alias(
			static function( string $post_type, string $feature ): bool {
				return 'page' === $post_type && 'editor' === $feature;
			}
		);

		$url = $this->create_testable_edit_screen()->expose_resolve_style_manager_launcher_target_url( 123, 'page' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=page&postId=123&canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_falls_back_to_rendering_template_for_unsupported_types(): void {
		$this->mock_admin_url();
		Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'public'       => false,
				'show_in_rest' => false,
			]
		);
		Functions\when( 'post_type_supports' )->justReturn( false );

		$url = $this->create_testable_edit_screen()->expose_resolve_style_manager_launcher_target_url( 456, 'portfolio' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=wp_template&postId=anima-lt%2F%2Fsingle-portfolio&canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_falls_back_to_rendering_template_for_custom_post_types(): void {
		$this->mock_admin_url();
		Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'public'       => true,
				'show_in_rest' => true,
			]
		);
		Functions\when( 'post_type_supports' )->justReturn( true );

		$url = $this->create_testable_edit_screen()->expose_resolve_style_manager_launcher_target_url( 456, 'portfolio' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=wp_template&postId=anima-lt%2F%2Fsingle-portfolio&canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_uses_single_template_when_custom_post_type_template_is_missing(): void {
		$this->mock_admin_url();
		Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );

		$url = $this->create_template_aware_edit_screen( [ 'anima-lt//single' ] )
			->expose_resolve_style_manager_launcher_target_url( 456, 'portfolio' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=wp_template&postId=anima-lt%2F%2Fsingle&canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_uses_default_deep_link_when_no_rendering_template_exists(): void {
		$this->mock_admin_url();
		Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );

		$url = $this->create_template_aware_edit_screen( [] )
			->expose_resolve_style_manager_launcher_target_url( 456, 'portfolio' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_falls_back_to_rendering_template_for_unsaved_entries(): void {
		$this->mock_admin_url();
		Functions\when( 'get_stylesheet' )->justReturn( 'anima-lt' );

		$url = $this->create_testable_edit_screen()->expose_resolve_style_manager_launcher_target_url( 0, 'post' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=wp_template&postId=anima-lt%2F%2Fsingle&canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_target_url_falls_back_to_default_style_manager_deep_link_without_a_post_type(): void {
		$this->mock_admin_url();

		$url = $this->create_testable_edit_screen()->expose_resolve_style_manager_launcher_target_url( 0, '' );

		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?canvas=edit&sm-sidebar=1',
			$url
		);
	}

	public function test_launcher_is_not_available_on_the_site_editor_screen(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $id = 'site-editor';

			public function is_block_editor(): bool {
				return true;
			}
		} );

		$this->assertFalse( $this->create_testable_edit_screen()->expose_should_enqueue_style_manager_launcher() );
	}

	public function test_launcher_requires_style_manager_support_and_theme_editing_capability(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $id        = 'post';
			public string $post_type = 'post';

			public function is_block_editor(): bool {
				return true;
			}
		} );
		Functions\when( 'current_theme_supports' )->justReturn( true );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\expect( 'current_user_can' )
			->once()
			->with( 'edit_theme_options' )
			->andReturn( false );

		$this->assertFalse( $this->create_testable_edit_screen()->expose_should_enqueue_style_manager_launcher() );
	}

	public function test_launcher_is_hidden_when_style_manager_is_not_supported(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $id        = 'post';
			public string $post_type = 'post';

			public function is_block_editor(): bool {
				return true;
			}
		} );
		Functions\when( 'current_theme_supports' )->justReturn( false );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\expect( 'current_user_can' )->never();

		$this->assertFalse( $this->create_testable_edit_screen()->expose_should_enqueue_style_manager_launcher() );
	}

	public function test_launcher_context_treats_post_new_auto_drafts_as_unsaved_entries(): void {
		$previous_pagenow = $GLOBALS['pagenow'] ?? null;
		$previous_post    = $GLOBALS['post'] ?? null;
		$previous_get     = $_GET;

		$GLOBALS['pagenow'] = 'post-new.php';
		$GLOBALS['post']    = (object) [
			'ID'        => 1743,
			'post_type' => 'post',
		];
		$_GET               = [
			'post_type' => 'post',
		];

		Functions\when( 'get_current_screen' )->justReturn( new class() {
			public string $post_type = 'post';
		} );

		try {
			$context = $this->create_testable_edit_screen()->expose_get_current_style_manager_launcher_context();
		} finally {
			if ( null === $previous_pagenow ) {
				unset( $GLOBALS['pagenow'] );
			} else {
				$GLOBALS['pagenow'] = $previous_pagenow;
			}

			if ( null === $previous_post ) {
				unset( $GLOBALS['post'] );
			} else {
				$GLOBALS['post'] = $previous_post;
			}

			$_GET = $previous_get;
		}

		$this->assertSame(
			[
				'post_id'   => 0,
				'post_type' => 'post',
			],
			$context
		);
	}

	public function test_launcher_payload_localizes_copy_icon_and_target_url(): void {
		$this->mock_admin_url();
		Functions\when( 'esc_html__' )->alias(
			static function( string $text ): string {
				return $text;
			}
		);
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'get_post_type_object' )->justReturn(
			(object) [
				'public'       => true,
				'show_in_rest' => true,
			]
		);
		Functions\when( 'post_type_supports' )->justReturn( true );

		$payload = $this->create_testable_edit_screen()->expose_get_style_manager_launcher_payload( 123, 'page' );

		$this->assertSame( 'admin-customizer', $payload['icon'] );
		$this->assertSame( 'Open Style Manager', $payload['copy']['buttonLabel'] );
		$this->assertSame(
			'https://example.test/wp-admin/site-editor.php?postType=page&postId=123&canvas=edit&sm-sidebar=1',
			$payload['targetUrl']
		);
		$this->assertSame(
			'Colors, typography and spacing are global — edited in the Site Editor.',
			$payload['copy']['description']
		);
	}

	private function create_edit_screen(
		?Fonts $fonts = null,
		?FrontendOutput $frontend_output = null,
		?PluginSettings $plugin_settings = null
	): EditWithBlocks {
		if ( null === $plugin_settings ) {
			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings
				->method( 'get' )
				->with( 'enable_editor_style', true )
				->willReturn( true );
		}

		return new EditWithBlocks(
			$this->createMock( Options::class ),
			$plugin_settings,
			$fonts ?? $this->createMock( Fonts::class ),
			$frontend_output ?? $this->createMock( FrontendOutput::class ),
			$this->createMock( LoggerInterface::class )
		);
	}

	private function create_testable_edit_screen(): TestableEditWithBlocks {
		return new TestableEditWithBlocks(
			$this->createMock( Options::class ),
			$this->createMock( PluginSettings::class ),
			$this->createMock( Fonts::class ),
			$this->createMock( FrontendOutput::class ),
			$this->createMock( LoggerInterface::class )
		);
	}

	private function create_template_aware_edit_screen( array $available_template_ids ): TemplateAwareEditWithBlocks {
		return new TemplateAwareEditWithBlocks(
			$this->createMock( Options::class ),
			$this->createMock( PluginSettings::class ),
			$this->createMock( Fonts::class ),
			$this->createMock( FrontendOutput::class ),
			$this->createMock( LoggerInterface::class ),
			$available_template_ids
		);
	}

	private function mock_admin_url(): void {
		Functions\when( 'admin_url' )->alias(
			static function( string $path = '' ): string {
				return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
			}
		);
	}
}

class TestableEditWithBlocks extends EditWithBlocks {
	public function expose_resolve_style_manager_launcher_target_url( int $post_id, string $post_type ): string {
		return $this->resolve_style_manager_launcher_target_url( $post_id, $post_type );
	}

	public function expose_should_enqueue_style_manager_launcher(): bool {
		return $this->should_enqueue_style_manager_launcher();
	}

	public function expose_get_current_style_manager_launcher_context(): array {
		return $this->get_current_style_manager_launcher_context();
	}

	public function expose_get_style_manager_launcher_payload( int $post_id, string $post_type ): array {
		return $this->get_style_manager_launcher_payload( $post_id, $post_type );
	}
}

class TemplateAwareEditWithBlocks extends TestableEditWithBlocks {
	private array $available_template_ids;

	public function __construct(
		Options $options,
		PluginSettings $plugin_settings,
		Fonts $sm_fonts,
		FrontendOutput $frontend_output,
		LoggerInterface $logger,
		array $available_template_ids
	) {
		parent::__construct( $options, $plugin_settings, $sm_fonts, $frontend_output, $logger );
		$this->available_template_ids = $available_template_ids;
	}

	protected function is_site_editor_template_available( string $template_post_id ): bool {
		return in_array( $template_post_id, $this->available_template_ids, true );
	}
}

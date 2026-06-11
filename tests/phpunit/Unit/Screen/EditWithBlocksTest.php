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
			->with( 'style-manager-editor-dynamic', false );
		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with( 'style-manager-editor-dynamic' );
		Functions\expect( 'wp_add_inline_style' )
			->once()
			->with( 'style-manager-editor-dynamic', ':root { --sm-current-accent-color: #123456; }body { font-family: serif; }' );

		$this->create_edit_screen( $fonts, $frontend_output )->enqueue_editor_dynamic_css();
	}

	private function create_edit_screen(
		?Fonts $fonts = null,
		?FrontendOutput $frontend_output = null
	): EditWithBlocks {
		return new EditWithBlocks(
			$this->createMock( Options::class ),
			$this->createMock( PluginSettings::class ),
			$fonts ?? $this->createMock( Fonts::class ),
			$frontend_output ?? $this->createMock( FrontendOutput::class ),
			$this->createMock( LoggerInterface::class )
		);
	}
}

<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_Customize_Manager', false ) ) {
		class WP_Customize_Manager {
			public static array $instances = [];

			public string $options_key = '';

			public array $constructor_args = [];

			public array $save_changeset_post_calls = [];

			private array $sections = [];

			public function __construct( array $sections = [] ) {
				if ( array_key_exists( 'changeset_uuid', $sections ) || array_key_exists( 'settings_previewed', $sections ) ) {
					$this->constructor_args = $sections;
					$this->sections         = [];
				} else {
					$this->sections = $sections;
				}

				self::$instances[] = $this;
			}

			public function remove_section( $id ): void {}
			public function remove_panel( $id ): void {}

			public function sections(): array {
				return $this->sections;
			}

			public function get_section( $id ) {
				return $this->sections[ $id ] ?? null;
			}

			public function register_controls(): void {}

			public function save_changeset_post( array $args ) {
				$this->save_changeset_post_calls[] = $args;

				return 123;
			}

			public function add_section( $id, $args = [] ) {}
			public function add_setting( $id, $args = [] ) {}
			public function add_control( $id, $args = [] ) {}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Customize {

	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Client\CloudInterface;
	use Pixelgrade\StyleManager\Customize\Customize;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

	class CustomizerCleanupTest extends TestCase {
		public function test_supported_style_manager_removes_theme_and_block_widgets_panels(): void {
			Functions\when( 'current_theme_supports' )->alias(
				static fn( string $feature ): bool => 'customizer_style_manager' === $feature
			);
			Functions\when( 'apply_filters' )->alias(
				static fn( string $hook, $value ) => 'style_manager/is_supported' === $hook ? $value : $value
			);
			Functions\when( 'wp_is_block_theme' )->justReturn( true );

			$removed_panels = [];
			$wp_customize   = $this->getMockBuilder( \WP_Customize_Manager::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'remove_panel' ] )
				->getMock();
			$wp_customize
				->expects( $this->exactly( 2 ) )
				->method( 'remove_panel' )
				->willReturnCallback( static function( string $panel ) use ( &$removed_panels ): void {
					$removed_panels[] = $panel;
				} );

			$customize = $this->create_customize();
			$customize->expose_remove_switch_theme_panel( $wp_customize );
			$customize->expose_remove_widgets_panel( $wp_customize );

			$this->assertSame( [ 'themes', 'widgets' ], $removed_panels );
		}

		public function test_unsupported_style_manager_keeps_core_panels_available(): void {
			Functions\when( 'current_theme_supports' )->justReturn( false );
			Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
			Functions\when( 'wp_is_block_theme' )->justReturn( true );

			$wp_customize = $this->getMockBuilder( \WP_Customize_Manager::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'remove_panel' ] )
				->getMock();
			$wp_customize->expects( $this->never() )->method( 'remove_panel' );

			$customize = $this->create_customize();
			$customize->expose_remove_switch_theme_panel( $wp_customize );
			$customize->expose_remove_widgets_panel( $wp_customize );
		}

		private function create_customize(): TestCustomize {
			return new TestCustomize(
				$this->createMock( CloudInterface::class ),
				$this->createMock( LoggerInterface::class )
			);
		}
	}

	class TestCustomize extends Customize {
		public function expose_remove_switch_theme_panel( \WP_Customize_Manager $wp_customize ): void {
			$this->remove_switch_theme_panel( $wp_customize );
		}

		public function expose_remove_widgets_panel( \WP_Customize_Manager $wp_customize ): void {
			$this->remove_widgets_panel( $wp_customize );
		}
	}
}

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

namespace Pixelgrade\StyleManager\Tests\Unit\Screen {

	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\FontPalettes;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Provider\Options;
	use Pixelgrade\StyleManager\Provider\PluginSettings;
	use Pixelgrade\StyleManager\Screen\Customizer;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

	class CustomizerSectionRemovalTest extends TestCase {
		public function test_default_customizer_sections_selected_in_settings_are_removed(): void {
			global $wp_registered_sidebars;

			$wp_registered_sidebars = [
				'sidebar-1' => [],
				'footer-1'  => [],
			];

			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings
				->expects( $this->once() )
				->method( 'get' )
				->with( 'disable_default_sections' )
				->willReturn( [
					'nav_menus'     => true,
					'widgets'       => true,
					'static_front_page' => true,
				] );

			$removed_sections = [];
			$wp_customize     = $this->getMockBuilder( \WP_Customize_Manager::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'remove_section' ] )
				->getMock();
			$wp_customize
				->expects( $this->exactly( 4 ) )
				->method( 'remove_section' )
				->willReturnCallback( static function( string $section ) use ( &$removed_sections ): void {
					$removed_sections[] = $section;
				} );

			$this->create_customizer( $plugin_settings )->expose_maybe_remove_default_sections( $wp_customize );

			$this->assertSame(
				[ 'nav_menus', 'sidebar-widgets-sidebar-1', 'sidebar-widgets-footer-1', 'static_front_page' ],
				$removed_sections
			);
		}

		private function create_customizer( PluginSettings $plugin_settings ): TestCustomizer {
			return new TestCustomizer(
				$this->createMock( Options::class ),
				$plugin_settings,
				$this->createMock( Fonts::class ),
				$this->createMock( FontPalettes::class ),
				$this->createMock( LoggerInterface::class )
			);
		}
	}

	class TestCustomizer extends Customizer {
		public function expose_maybe_remove_default_sections( \WP_Customize_Manager $wp_customize ): void {
			$this->maybe_remove_default_sections( $wp_customize );
		}
	}
}

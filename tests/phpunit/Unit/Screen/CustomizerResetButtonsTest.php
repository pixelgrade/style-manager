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

			public function register_controls(): void {
			}

			public function save_changeset_post( array $args ) {
				$this->save_changeset_post_calls[] = $args;

				return 123;
			}

			public function add_section( $id, $args = [] ) {}
			public function add_setting( $id, $args = [] ) {}
			public function add_control( $id, $args = [] ) {}
		}
	}

	if ( ! class_exists( 'WP_Customize_Control' ) ) {
		class WP_Customize_Control {
			public $manager;
			public string $id;
			public string $label = '';
			public string $section = '';
			public string $settings = '';
			public string $action = '';

			public function __construct( $manager, string $id, array $args = [] ) {
				$this->manager = $manager;
				$this->id      = $id;

				foreach ( $args as $key => $value ) {
					$this->{$key} = $value;
				}
			}
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
	use Pixelgrade\StyleManager\Tests\Framework\PHPUnitUtil;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

	class CustomizerResetButtonsTest extends TestCase {
		public function test_reset_toolbox_is_not_registered_when_reset_buttons_are_disabled(): void {
			$wp_customize = new TestCustomizeManager();

			$this->mock_customizer_config_filters();

			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings
				->expects( $this->once() )
				->method( 'get' )
				->with( 'enable_reset_buttons' )
				->willReturn( false );

			$this->invoke_process_customizer_config( $wp_customize, $plugin_settings );

			$this->assertArrayNotHasKey( 'style_manager_toolbar', $wp_customize->recorded_sections );
			$this->assertArrayNotHasKey( 'reset_style_manager', $wp_customize->recorded_settings );
			$this->assertArrayNotHasKey( 'reset_style_manager', $wp_customize->recorded_controls );
		}

		public function test_reset_toolbox_is_registered_when_reset_buttons_are_enabled(): void {
			$wp_customize = new TestCustomizeManager();

			$this->mock_customizer_config_filters();
			Functions\when( 'esc_html__' )->returnArg( 1 );

			$plugin_settings = $this->createMock( PluginSettings::class );
			$plugin_settings
				->expects( $this->once() )
				->method( 'get' )
				->with( 'enable_reset_buttons' )
				->willReturn( true );

			$this->invoke_process_customizer_config( $wp_customize, $plugin_settings );

			$this->assertSame( 'hive-lt_options', $wp_customize->options_key );
			$this->assertSame(
				[
					'title'      => 'Style Manager Toolbox',
					'capability' => 'manage_options',
					'priority'   => 999999999,
					'options'    => [
						'reset_all_button' => [
							'type'   => 'button',
							'label'  => 'Reset Style Manager',
							'action' => 'reset_style_manager',
							'value'  => 'reset',
						],
					],
				],
				$wp_customize->recorded_sections['style_manager_toolbar']
			);
			$this->assertSame( [], $wp_customize->recorded_settings['reset_style_manager'] );
			$this->assertSame( 'Reset All Style Manager Options to Default', $wp_customize->recorded_controls['reset_style_manager']->label );
			$this->assertSame( 'style_manager_toolbar', $wp_customize->recorded_controls['reset_style_manager']->section );
			$this->assertSame( 'reset_style_manager', $wp_customize->recorded_controls['reset_style_manager']->action );
		}

		private function invoke_process_customizer_config( \WP_Customize_Manager $wp_customize, PluginSettings $plugin_settings ): void {
			$options = $this->createMock( Options::class );
			$options
				->expects( $this->once() )
				->method( 'get_customizer_config' )
				->willReturn( [ 'opt-name' => 'hive-lt_options' ] );

			$customizer = new Customizer(
				$options,
				$plugin_settings,
				$this->createMock( Fonts::class ),
				$this->createMock( FontPalettes::class ),
				$this->createMock( LoggerInterface::class )
			);

			PHPUnitUtil::getProtectedMethod( $customizer, 'process_customizer_config' )
				->invoke( $customizer, $wp_customize );
		}

		private function mock_customizer_config_filters(): void {
			Functions\when( 'do_action' )->justReturn( null );
			Functions\when( 'apply_filters' )->alias(
				static function ( string $tag, $value ) {
					return $value;
				}
			);
		}
	}

	class TestCustomizeManager extends \WP_Customize_Manager {
		public string $options_key = '';
		public array $recorded_sections = [];
		public array $recorded_settings = [];
		public array $recorded_controls = [];

		public function __construct() {
		}

		public function add_section( $id, $args = [] ) {
			$this->recorded_sections[ $id ] = $args;
		}

		public function add_setting( $id, $args = [] ) {
			$this->recorded_settings[ $id ] = $args;
		}

		public function add_control( $id, $args = [] ) {
			if ( is_object( $id ) ) {
				$this->recorded_controls[ $id->id ] = $id;

				return;
			}

			$this->recorded_controls[ $id ] = $args;
		}
	}
}

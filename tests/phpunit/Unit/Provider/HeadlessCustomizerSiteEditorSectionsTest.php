<?php
declare ( strict_types = 1 );

namespace {
	if ( ! class_exists( 'WP_Customize_Manager', false ) ) {
		class WP_Customize_Manager {
			private array $sections;

			public function __construct( array $sections = [] ) {
				$this->sections = $sections;
			}

			public function sections(): array {
				return $this->sections;
			}
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Provider {
	use Brain\Monkey\Functions;
	use Pixelgrade\StyleManager\Customize\Fonts;
	use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
	use Pixelgrade\StyleManager\Provider\Options;
	use Pixelgrade\StyleManager\Provider\PluginSettings;
	use Pixelgrade\StyleManager\Screen\Customizer;
	use Pixelgrade\StyleManager\Screen\EditWithBlocks;
	use Pixelgrade\StyleManager\Screen\SiteEditor;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

	class HeadlessCustomizerSiteEditorSectionsTest extends TestCase {
		public function tearDown(): void {
			unset( $GLOBALS['wp_current_filter'] );

			parent::tearDown();
		}

		public function test_sm_section_ids_include_theme_colors_section_for_active_options_key(): void {
			Functions\when( 'apply_filters' )->returnArg( 2 );

			$options = $this->createMock( Options::class );
			$options
				->method( 'get_options_key' )
				->willReturn( 'hive-lt_options' );

			$manager = new \WP_Customize_Manager( [
				'style_manager_section'              => (object) [ 'panel' => 'style_manager_panel' ],
				'sm_color_palettes_section'          => (object) [ 'panel' => 'theme_options_panel' ],
				'hive-lt_options[colors_section]'    => (object) [ 'panel' => 'theme_options_panel' ],
				'hive-lt_options[unrelated_section]' => (object) [ 'panel' => 'theme_options_panel' ],
			] );

			$headless = new TestHeadlessCustomizer(
				$options,
				$this->createMock( Customizer::class ),
				$manager
			);

			$section_ids = $headless->get_sm_section_ids();

			$this->assertContains(
				'hive-lt_options[colors_section]',
				$section_ids,
				'The theme Elements coloration section must be exposed in the Site Editor.'
			);
			$this->assertNotContains(
				'hive-lt_options[unrelated_section]',
				$section_ids,
				'Only the theme colors_section shortcut should be added from the Theme Options panel.'
			);
		}

		public function test_site_editor_usage_section_groups_color_targets_and_appearance_after_coloration(): void {
			Functions\when( 'apply_filters' )->returnArg( 2 );
			Functions\when( 'get_option' )->alias( static function( string $name, $default = false ) {
				return $default;
			} );
			Functions\when( 'home_url' )->alias( static function( string $path = '' ) {
				return 'https://example.test' . $path;
			} );
			Functions\when( 'esc_url_raw' )->alias( static function( string $url ) {
				return $url;
			} );
			Functions\when( 'esc_html__' )->alias( static function( string $text ) {
				return $text;
			} );
			$GLOBALS['wp_current_filter'] = [ 'style_manager/filter_fields' ];

			$headless = $this->createMock( HeadlessCustomizer::class );
			$headless
				->method( 'get_structure' )
				->willReturn( [
					'panels'   => [],
					'sections' => [
						[
							'id'       => 'sm_color_palettes_section',
							'title'    => 'Color System',
							'priority' => 10,
							'panel'    => 'style_manager_panel',
							'controls' => [],
						],
						[
							'id'       => 'sm_color_usage_section',
							'title'    => 'Usage',
							'priority' => 20,
							'panel'    => 'theme_options_panel',
							'controls' => [
								[
									'id'     => 'sm_description_color_usage_intro_control',
									'type'   => 'html',
									'html'   => '<li id="customize-control-sm_description_color_usage_intro_control"></li>',
									'active' => true,
								],
								[
									'id'     => 'sm_coloration_level_control',
									'type'   => 'sm_radio',
									'html'   => '<li id="customize-control-sm_coloration_level_control"></li>',
									'active' => true,
								],
								[
									'id'     => 'sm_dark_mode_advanced_control',
									'type'   => 'sm_radio',
									'html'   => '<li id="customize-control-sm_dark_mode_advanced_control"></li>',
									'active' => true,
								],
							],
						],
						[
							'id'       => 'hive-lt_options[colors_section]',
							'title'    => 'Elements coloration',
							'priority' => 30,
							'panel'    => 'theme_options_panel',
							'controls' => [
								[
									'id'     => 'hive-lt_options[sm-description_colorize_elements_intro]_control',
									'type'   => 'html',
									'html'   => '<li id="customize-control-hive-lt_options-sm-description_colorize_elements_intro_control"></li>',
									'active' => true,
								],
								[
									'id'     => 'page_title_control',
									'type'   => 'sm_toggle',
									'html'   => '<li id="customize-control-page_title_control"></li>',
									'active' => true,
								],
								[
									'id'     => 'body_color_control',
									'type'   => 'sm_toggle',
									'html'   => '<li id="customize-control-body_color_control"></li>',
									'active' => true,
								],
							],
						],
					],
				] );
			$headless
				->method( 'get_settings_data' )
				->willReturn( [] );

			$options = $this->createMock( Options::class );
			$options
				->method( 'get_options_key' )
				->willReturn( 'hive-lt_options' );

			$site_editor = new TestSiteEditor(
				$options,
				$this->createMock( PluginSettings::class ),
				$this->createMock( Fonts::class ),
				$headless,
				$this->createMock( Customizer::class ),
				$this->createMock( EditWithBlocks::class ),
				$this->createMock( LoggerInterface::class )
			);

			$payload        = $site_editor->expose_get_site_editor_payload();
			$color_tabs     = $payload['sectionTabs']['sm_color_palettes_section'];
			$sections_by_id = array_column( $payload['structure']['sections'], null, 'id' );

			$this->assertSame(
				[
					[ 'id' => 'sm_color_palettes_section', 'label' => 'Palette' ],
					[ 'id' => 'sm_fine_tune_color_palette_section', 'label' => 'Fine-tune' ],
					[ 'id' => 'sm_color_usage_section', 'label' => 'Usage' ],
				],
				$color_tabs,
				'The theme Elements coloration section should be folded into Usage, not exposed as a Color System tab.'
			);
			$this->assertArrayNotHasKey(
				'hive-lt_options[colors_section]',
				$sections_by_id,
				'The folded theme Elements coloration section should not render as a standalone section.'
			);
			$this->assertSame(
				[
					'sm_description_color_usage_intro_control',
					'sm_coloration_level_control',
					'hive-lt_options[sm-description_colorize_elements_intro]_control',
					'page_title_control',
					'body_color_control',
					'sm_dark_mode_advanced_control',
				],
				array_column( $sections_by_id['sm_color_usage_section']['controls'], 'id' ),
				'Theme element coloration presets should appear directly below the Coloration Level control.'
			);
			$this->assertSame(
				[
					[
						'before'      => 'hive-lt_options[sm-description_colorize_elements_intro]',
						'label'       => 'Color targets',
						'collapsible' => true,
					],
					[
						'before' => 'sm_dark_mode_advanced',
						'label'  => 'Appearance',
					],
				],
				$payload['sectionGroupHeaders']['sm_color_usage_section'],
				'Usage should keep Coloration Level first, then group color targets and Appearance separately.'
			);
		}
	}

	class TestHeadlessCustomizer extends HeadlessCustomizer {
		private \WP_Customize_Manager $test_manager;

		public function __construct( Options $options, Customizer $customizer_screen, \WP_Customize_Manager $manager ) {
			parent::__construct( $options, $customizer_screen );

			$this->test_manager = $manager;
		}

		public function get_manager(): \WP_Customize_Manager {
			return $this->test_manager;
		}
	}

	class TestSiteEditor extends SiteEditor {
		public function expose_get_site_editor_payload( array $localized = [] ): array {
			return $this->get_site_editor_payload( $localized );
		}
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Brain\Monkey\Filters;
use Mockery;
use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

class FrontendOutputTest extends TestCase {
	public function test_add_hooks_uses_configured_dynamic_style_output_location(): void {
		Functions\when( '_wp_filter_build_unique_id' )->alias(
			static function( string $hook_name, $callback, int $priority ): string {
				return $hook_name . '_' . $priority . '_' . ( is_array( $callback ) ? $callback[1] : 'callback' );
			}
		);

		$plugin_settings = $this->createMock( PluginSettings::class );
		$plugin_settings
			->expects( $this->once() )
			->method( 'get' )
			->with( 'style_resources_location', 'wp_head' )
			->willReturn( 'wp_footer' );

		Filters\expectAdded( 'wp_footer' )
			->once()
			->with( Mockery::type( \Closure::class ), 99, 1 );
		Filters\expectAdded( 'style_manager/localized_js_settings' )
			->once()
			->with( Mockery::type( \Closure::class ), 10, 1 );
		Filters\expectAdded( 'wp_enqueue_scripts' )
			->once()
			->with( Mockery::type( \Closure::class ), 15, 1 );

		$frontend_output = new FrontendOutput(
			$this->createMock( Options::class ),
			$plugin_settings,
			$this->createMock( LoggerInterface::class )
		);

		$frontend_output->add_hooks();
		$this->addToAssertionCount( 3 );
	}

	public function test_dynamic_style_generates_representative_frontend_css(): void {
		$this->mock_wordpress_functions();

		$options = $this->createMock( Options::class );
		$options
			->expects( $this->once() )
			->method( 'get_details_all' )
			->with( true )
			->willReturn(
				[
					'site_title_size'       => [
						'value' => 24,
						'css'   => [
							[
								'property' => 'font-size',
								'selector' => '.site-title',
							],
						],
					],
					'spacing_level'         => [
						'value' => 1.25,
						'css'   => [
							[
								'property' => '--sm-spacing-level',
								'selector' => ':root',
								'unit'     => '',
							],
						],
					],
					'filtered_value'        => [
						'value' => 'accent',
						'css'   => [
							[
								'property'        => 'color',
								'selector'        => '.filtered',
								'filter_value_cb' => [
									'callback' => [ self::class, 'format_color_token' ],
								],
							],
						],
					],
					'callback_output'       => [
						'value' => 'fg1',
						'css'   => [
							[
								'property'        => 'background',
								'selector'        => '.callback',
								'callback_filter' => static fn( string $value, string $selector, string $property ): string => $selector . ' { ' . $property . ': token(' . $value . '); }' . PHP_EOL,
							],
						],
					],
					'container_width'       => [
						'value' => 960,
						'css'   => [
							[
								'property' => 'max-width',
								'selector' => '.container',
								'media'    => 'screen and (min-width: 1000px)',
							],
						],
					],
					'hero_background_image' => [
						'type'   => 'custom_background',
						'output' => [ '.hero', '.cover' ],
						'value'  => [
							'background-image'      => 'https://example.com/hero.jpg',
							'background-repeat'     => 'no-repeat',
							'background-position'   => 'center center',
							'background-size'       => 'cover',
							'background-attachment' => 'fixed',
						],
					],
				]
			);

		$frontend_output = new FrontendOutput(
			$options,
			$this->createMock( PluginSettings::class ),
			$this->createMock( LoggerInterface::class )
		);

		$css = $frontend_output->get_dynamic_style();

		$this->assertStringContainsString( '.site-title { font-size: 24px; }', $css );
		$this->assertStringContainsString( ':root { --sm-spacing-level: 1.25; }', $css );
		$this->assertStringContainsString( '.filtered { color: var(--sm-current-accent-color); }', $css );
		$this->assertStringContainsString( '.callback { background: token(fg1); }', $css );
		$this->assertStringContainsString( '.hero .cover {background-image: url( https://example.com/hero.jpg);background-repeat:no-repeat;background-position:center center;background-size:cover;background-attachment:fixed;}', $css );
		$this->assertStringContainsString( '@media screen and (min-width: 1000px)', $css );
		$this->assertStringContainsString( '.container { max-width: 960px; }', $css );
	}

	/**
	 * Sink hardening (internal maintenance pass): a hostile stored value
	 * reaching the frontend `<style>` sink must not be able to break out of
	 * it, even beyond what wp_strip_all_tags() alone removes.
	 */
	public function test_output_dynamic_style_neutralizes_a_hostile_stored_value(): void {
		$this->mock_wordpress_functions();

		$options = $this->createMock( Options::class );
		$options
			->method( 'get_details_all' )
			->with( true )
			->willReturn(
				[
					'hostile_value' => [
						'value' => 'irrelevant',
						'css'   => [
							[
								'property'        => 'color',
								'selector'        => '.x',
								'callback_filter'  => static fn( string $value, string $selector, string $property ): string =>
									'--sm-bg-color-1: red;} </style><script>alert(document.cookie)</script><style>.y{color:',
							],
						],
					],
				]
			);

		$frontend_output = new FrontendOutput(
			$options,
			$this->createMock( PluginSettings::class ),
			$this->createMock( LoggerInterface::class )
		);

		ob_start();
		$frontend_output->output_dynamic_style();
		$output = ob_get_clean();

		// Exactly one legitimate closing </style> (the wrapper's own) survives;
		// the attacker-controlled one is neutralized, and no <script tag survives.
		$this->assertSame( 1, preg_match_all( '/<\/style/i', $output ) );
		$this->assertStringNotContainsStringIgnoringCase( '<script', $output );
	}

	public static function format_color_token( string $value ): string {
		return 'var(--sm-current-' . $value . '-color)';
	}

	private function mock_wordpress_functions(): void {
		Functions\when( 'apply_filters' )->alias( static fn( string $hook, $value ) => $value );
		Functions\when( 'esc_url' )->alias( static fn( string $url ): string => $url );
		Functions\when( 'normalize_whitespace' )->alias( static fn( string $text ): string => trim( (string) preg_replace( '/\s+/', ' ', $text ) ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => strip_tags( $text ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $text ): string => strip_tags( (string) $text ) );
	}
}

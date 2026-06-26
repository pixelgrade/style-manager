<?php
declare ( strict_types = 1 );

namespace {
	if ( ! function_exists( 'rocket_clean_exclude_file' ) ) {
		function rocket_clean_exclude_file( string $url ): string {
			return 'clean:' . $url;
		}
	}
}

namespace Pixelgrade\StyleManager\Tests\Unit\Integration {

	use Pixelgrade\StyleManager\Integration\Autoptimize;
	use Pixelgrade\StyleManager\Integration\W3TotalCache;
	use Pixelgrade\StyleManager\Integration\WPFastestCache;
	use Pixelgrade\StyleManager\Integration\WPRocket;
	use Pixelgrade\StyleManager\Tests\Unit\TestCase;
	use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;

	class CachePluginsTest extends TestCase {
		public function test_autoptimize_keeps_webfontloader_in_place_and_excluded(): void {
			$integration = new TestAutoptimize();

			$this->assertSame( [ 'existing', 'vendor_js/webfontloader' ], $integration->expose_js_dontmove( [ 'existing' ] ) );
			$this->assertSame( 'jquery.js,vendor_js/webfontloader', $integration->expose_js_exclude( 'jquery.js' ) );
			$this->assertSame( [ 'jquery.js', 'vendor_js/webfontloader' ], $integration->expose_js_exclude( [ 'jquery.js' ] ) );
		}

		public function test_wp_rocket_excludes_webfontloader_file_and_inline_loader(): void {
			$integration = ( new TestWPRocket() )->set_plugin( $this->create_plugin( 'https://example.test/wp-content/plugins/style-manager/' ) );

			$this->assertSame(
				[ 'existing', 'clean:https://example.test/wp-content/plugins/style-manager/vendor_js/webfontloader-1-6-28.min.js' ],
				$integration->expose_exclude_webfontloader_script( [ 'existing' ] )
			);
			$this->assertSame(
				[ 'keep me', 'styleManagerFontLoader = function()' ],
				$integration->expose_exclude_inline_script( [ 'keep me' ] )
			);
		}

		public function test_wp_fastest_cache_seeds_default_minify_exclusion_for_webfontloader(): void {
			$integration = ( new TestWPFastestCache() )->set_plugin( $this->create_plugin( 'https://example.test/wp-content/plugins/style-manager/' ) );

			$rules = json_decode( $integration->expose_exclude_scripts_from_minify( false ), true );

			$this->assertSame(
				[
					[
						'prefix'  => 'contain',
						'content' => 'https://example.test/wp-content/plugins/style-manager/vendor_js/webfontloader',
						'type'    => 'js',
					],
				],
				$rules
			);
			$this->assertSame( 'existing-json', $integration->expose_exclude_scripts_from_minify( 'existing-json' ) );
		}

		public function test_w3_total_cache_removes_webfontloader_script_tags_from_minify_list(): void {
			$integration = ( new TestW3TotalCache() )->set_plugin( $this->create_plugin( 'https://example.test/wp-content/plugins/style-manager/' ) );

			$result = $integration->expose_exclude_scripts_from_minify( [
				'<script src="https://example.test/wp-content/plugins/style-manager/vendor_js/webfontloader-1-6-28.min.js"></script>',
				'<script>styleManagerFontLoader = function(){}</script>',
				'<script src="keep.js"></script>',
			] );

			$this->assertSame( [ 2 => '<script src="keep.js"></script>' ], $result );
		}

		private function create_plugin( string $base_url ): PluginInterface {
			$plugin = $this->createMock( PluginInterface::class );
			$plugin
				->method( 'get_url' )
				->willReturnCallback( static fn( string $path = '' ): string => $base_url . ltrim( $path, '/' ) );

			return $plugin;
		}
	}

	class TestAutoptimize extends Autoptimize {
		public function expose_js_dontmove( array $dontmove ): array {
			return $this->js_dontmove( $dontmove );
		}

		public function expose_js_exclude( $exclude ) {
			return $this->js_exclude( $exclude );
		}
	}

	class TestWPRocket extends WPRocket {
		public function expose_exclude_webfontloader_script( array $list ): array {
			return $this->exclude_webfontloader_script( $list );
		}

		public function expose_exclude_inline_script( array $inline_js ): array {
			return $this->exclude_inline_script( $inline_js );
		}
	}

	class TestWPFastestCache extends WPFastestCache {
		public function expose_exclude_scripts_from_minify( $default ) {
			return $this->exclude_scripts_from_minify( $default );
		}
	}

	class TestW3TotalCache extends W3TotalCache {
		public function expose_exclude_scripts_from_minify( $script_tags ) {
			return $this->exclude_scripts_from_minify( $script_tags );
		}
	}
}

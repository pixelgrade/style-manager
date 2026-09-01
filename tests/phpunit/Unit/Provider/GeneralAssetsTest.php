<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\GeneralAssets;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\PluginInterface;

class GeneralAssetsTest extends TestCase {
	public function tearDown(): void {
		unset( $GLOBALS['pagenow'] );
		parent::tearDown();
	}

	public function test_inline_frontend_output_keeps_a_hostile_value_inside_the_script_payload(): void {
		$hostile = 'x";<!--<script></script></script><script>window.compromised=true</script><script>"y';

		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static fn( string $name, $default = false ) => 'sm_advanced_palette_output' === $name
				? '[{"id":"1"}]'
				: $default
		);
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $value, int $flags = 0 ): string => (string) json_encode( $value, $flags )
		);
		Functions\when( 'absint' )->alias( static fn( $value ): int => abs( (int) $value ) );
		Functions\when( 'esc_url' )->returnArg( 1 );

		$GLOBALS['pagenow'] = 'customize.php';

		$options = $this->createMock( Options::class );
		$options->method( 'get' )->willReturn( 1 );

		$frontend_output = $this->createMock( FrontendOutput::class );
		$frontend_output->method( 'get_dynamic_style' )->willReturn( $hostile );

		$plugin = $this->createMock( PluginInterface::class );
		$plugin->method( 'get_url' )->willReturn( 'https://example.test/style-manager.css' );

		$assets = new TestGeneralAssets( $options, $frontend_output );
		$assets->set_plugin( $plugin );

		ob_start();
		$assets->print_inline_scripts_for_test();
		$output = (string) ob_get_clean();

		// Only the wrapper's legitimate closing tag may remain visible to the HTML parser.
		$this->assertSame( 1, preg_match_all( '/<\/script/i', $output ) );
		$this->assertMatchesRegularExpression( '/frontendOutput = (.+);/', $output );
		preg_match( '/frontendOutput = (.+);/', $output, $matches );
		$this->assertStringContainsString( '\\u003C', $matches[1] );
		$this->assertStringNotContainsString( '<', $matches[1] );
		$this->assertSame( $hostile, json_decode( $matches[1], true ) );
	}
}

class TestGeneralAssets extends GeneralAssets {
	public function print_inline_scripts_for_test(): void {
		$this->print_inline_scripts();
	}
}

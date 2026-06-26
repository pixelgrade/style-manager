<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Runtime;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

use function style_manager_color_select_dark_cb;
use function style_manager_color_switch_dark_cb;
use function style_manager_get_color_select_dark_config;
use function style_manager_get_color_switch_dark_config;

class ColorHelperApiTest extends TestCase {
	public function test_color_select_dark_config_outputs_live_select_with_css_callbacks(): void {
		Functions\when( 'esc_html__' )->returnArg( 1 );

		$config = style_manager_get_color_select_dark_config( 'Navigation', '.nav a', 'dark', [ 'color', 'border-color' ] );

		$this->assertSame( 'select_color', $config['type'] );
		$this->assertSame( 'Navigation', $config['label'] );
		$this->assertTrue( $config['live'] );
		$this->assertSame( [ 'background', 'dark', 'accent' ], array_keys( $config['choices'] ) );
		$this->assertSame(
			[
				[
					'property'        => 'color',
					'selector'        => '.nav a',
					'callback_filter' => 'sm_color_select_dark_cb',
				],
				[
					'property'        => 'border-color',
					'selector'        => '.nav a',
					'callback_filter' => 'sm_color_select_dark_cb',
				],
			],
			$config['css']
		);
		$this->assertSame(
			'.nav a { color: var(--sm-current-accent-color); }' . PHP_EOL,
			style_manager_color_select_dark_cb( 'accent', '.nav a', 'color' )
		);
	}

	public function test_color_switch_dark_config_tolerates_imported_toggle_values(): void {
		$config = style_manager_get_color_switch_dark_config( 'Button accent', '.button', false, 3, 'background-color' );

		$this->assertSame( 'sm_toggle', $config['type'] );
		$this->assertSame( 3, $config['coloration'] );
		$this->assertSame(
			[
				[
					'property'        => 'background-color',
					'selector'        => '.button',
					'callback_filter' => 'sm_color_switch_dark_cb',
				],
			],
			$config['css']
		);
		$this->assertSame(
			'.button {background-color: var(--sm-current-accent-color); }' . PHP_EOL,
			style_manager_color_switch_dark_cb( '1', '.button', 'background-color' )
		);
		$this->assertSame(
			'.button {background-color: var(--sm-current-fg1-color); }' . PHP_EOL,
			style_manager_color_switch_dark_cb( '0', '.button', 'background-color' )
		);
	}
}

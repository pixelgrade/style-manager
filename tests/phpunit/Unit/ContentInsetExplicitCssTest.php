<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * The Content Inset opt-in signal (nova-blocks#655): `--sm-content-inset` is
 * always emitted (default 230), so Nova Blocks applies the Layout board's inset
 * contract only when `--sm-content-inset-explicit` says a value was saved.
 */
class ContentInsetExplicitCssTest extends TestCase {
	private function with_saved( $value ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $value ) {
				return 'sm_content_inset' === $name ? $value : $default;
			}
		);
	}

	public function test_unsaved_option_emits_nothing(): void {
		$this->with_saved( null );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
		$this->assertSame( '', style_manager_content_inset_explicit_css_cb( 230, ':root', '--sm-content-inset-explicit' ) );
	}

	public function test_empty_or_non_numeric_saved_value_emits_nothing(): void {
		$this->with_saved( '' );
		$this->assertSame( '', style_manager_content_inset_explicit_css_cb( 230, ':root', '--sm-content-inset-explicit' ) );

		$this->with_saved( 'wide' );
		$this->assertSame( '', style_manager_content_inset_explicit_css_cb( 230, ':root', '--sm-content-inset-explicit' ) );
	}

	public function test_saved_value_emits_the_signal_even_at_the_default(): void {
		$this->with_saved( '230' );

		$this->assertTrue( style_manager_content_inset_is_explicit() );
		$this->assertSame(
			':root { --sm-content-inset-explicit: 1; }' . PHP_EOL,
			style_manager_content_inset_explicit_css_cb( 230, ':root', '--sm-content-inset-explicit' )
		);
	}

	public function test_legacy_alias_matches(): void {
		$this->with_saved( 180 );

		$this->assertSame(
			style_manager_content_inset_explicit_css_cb( 180, ':root', '--sm-content-inset-explicit' ),
			sm_content_inset_explicit_css_cb( 180, ':root', '--sm-content-inset-explicit' )
		);
	}
}

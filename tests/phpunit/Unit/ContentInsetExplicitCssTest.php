<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * The Content Inset opt-in signal (nova-blocks#655, style-manager#220):
 * `--sm-content-inset` is always emitted (default 230), so Nova Blocks applies
 * the Layout board's inset contract only when `--sm-content-inset-explicit`
 * says the value was saved through Style Manager (Customizer, Site Editor or
 * `wp pixelgrade sm set`). Values saved before 2.7.0 and values written
 * straight to the option (a starter import) stay legacy.
 */
class ContentInsetExplicitCssTest extends TestCase {
	private const MARKER = 'style_manager_content_inset_explicit';

	public function tearDown(): void {
		unset( $GLOBALS['wp_customize'] );
		parent::tearDown();
	}

	private function with_options( array $options ): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $options ) {
				return array_key_exists( $name, $options ) ? $options[ $name ] : $default;
			}
		);
	}

	private function with_saved( $value, $marker = null ): void {
		$options = [ 'sm_content_inset' => $value ];
		if ( null !== $marker ) {
			$options[ self::MARKER ] = $marker;
		}
		$this->with_options( $options );
	}

	private function emitted(): string {
		return style_manager_content_inset_explicit_css_cb( 230, ':root', '--sm-content-inset-explicit' );
	}

	/**
	 * A stand-in for the Customizer manager during a preview request.
	 */
	private function preview_with_pending( array $pending, bool $is_preview = true ): void {
		$GLOBALS['wp_customize'] = new class( $pending, $is_preview ) {
			private array $pending;
			private bool $is_preview;

			public function __construct( array $pending, bool $is_preview ) {
				$this->pending    = $pending;
				$this->is_preview = $is_preview;
			}

			public function is_preview(): bool {
				return $this->is_preview;
			}

			public function unsanitized_post_values(): array {
				return $this->pending;
			}
		};
	}

	public function test_unsaved_option_emits_nothing(): void {
		$this->with_saved( null );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
		$this->assertSame( '', $this->emitted() );
	}

	public function test_empty_or_non_numeric_saved_value_emits_nothing(): void {
		$this->with_saved( '', '' );
		$this->assertSame( '', $this->emitted() );

		$this->with_saved( 'wide', 'wide' );
		$this->assertSame( '', $this->emitted() );
	}

	public function test_a_value_without_the_marker_is_legacy(): void {
		// Saved before 2.7.0, or written straight to the option by a starter import.
		$this->with_saved( '230' );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
		$this->assertTrue( style_manager_content_inset_stored_is_legacy() );
		$this->assertSame( '', $this->emitted() );
	}

	public function test_a_value_marked_by_a_style_manager_save_emits_the_signal_even_at_the_default(): void {
		$this->with_saved( '230', '230' );

		$this->assertTrue( style_manager_content_inset_is_explicit() );
		$this->assertFalse( style_manager_content_inset_stored_is_legacy() );
		$this->assertSame( ':root { --sm-content-inset-explicit: 1; }' . PHP_EOL, $this->emitted() );
	}

	public function test_the_marker_matches_numerically(): void {
		$this->with_saved( 180, '180' );
		$this->assertTrue( style_manager_content_inset_is_explicit() );

		$this->with_saved( '187.50', 187.5 );
		$this->assertTrue( style_manager_content_inset_is_explicit() );
	}

	public function test_a_raw_write_over_a_marked_value_is_legacy_again(): void {
		// Saved at 90 through the Customizer, then a starter import wrote 230.
		$this->with_saved( '230', '90' );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
		$this->assertTrue( style_manager_content_inset_stored_is_legacy() );
	}

	public function test_a_leftover_marker_without_a_value_is_not_explicit(): void {
		$this->with_saved( null, '230' );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
		$this->assertFalse( style_manager_content_inset_stored_is_legacy() );
	}

	public function test_a_customizer_preview_with_a_pending_inset_previews_the_save(): void {
		// Publishing that changeset marks the value, so its preview shows it marked.
		$this->with_saved( '140' );
		$this->preview_with_pending( [ 'sm_content_inset' => 140 ] );

		$this->assertTrue( style_manager_content_inset_is_explicit() );
	}

	public function test_a_customizer_preview_resetting_the_inset_is_not_explicit(): void {
		$this->with_saved( '', '230' );
		$this->preview_with_pending( [ 'sm_content_inset' => '' ] );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
	}

	public function test_a_customizer_preview_without_a_pending_inset_keeps_the_saved_state(): void {
		$this->with_saved( '230' );
		$this->preview_with_pending( [ 'sm_rail_gap' => 3 ] );
		$this->assertFalse( style_manager_content_inset_is_explicit() );

		$this->with_saved( '230', '230' );
		$this->assertTrue( style_manager_content_inset_is_explicit() );
	}

	public function test_pending_values_outside_a_preview_are_ignored(): void {
		// A publishing request (Site Editor save, WP-CLI) is not a preview.
		$this->with_saved( '230' );
		$this->preview_with_pending( [ 'sm_content_inset' => 230 ], false );

		$this->assertFalse( style_manager_content_inset_is_explicit() );
	}

	public function test_legacy_alias_matches(): void {
		$this->with_saved( 180, 180 );

		$this->assertSame(
			$this->emitted(),
			sm_content_inset_explicit_css_cb( 180, ':root', '--sm-content-inset-explicit' )
		);
	}
}

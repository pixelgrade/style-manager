<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Brain\Monkey\Functions;

/**
 * The quiet-text colour role (style-manager#214): `--sm-fg-muted-color-N` per variation, softer
 * than fg1 and never below 4.5:1 against that variation's bg. JS twin:
 * tests/js/quiet-text-role.test.js. Both are pinned to tests/phpunit/fixtures/quiet-text/corpus.json
 * so the frontend, the Customizer preview and the Site Editor emit the same colours.
 */
class QuietTextRoleTest extends TestCase {

	private static function fixture( string $file ): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/fixtures/' . $file ), true );
	}

	/** Independent WCAG 2.x contrast, so the floor is not checked by the code under test. */
	private static function contrast( string $a, string $b ): float {
		$luminance = static function ( string $hex ): float {
			$hex = ltrim( $hex, '#' );
			if ( 3 === strlen( $hex ) ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$linear = [];
			foreach ( [ 0, 2, 4 ] as $offset ) {
				$c        = hexdec( substr( $hex, $offset, 2 ) ) / 255;
				$linear[] = $c <= 0.04045 ? $c / 12.92 : ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
			}

			return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
		};

		$la = $luminance( $a );
		$lb = $luminance( $b );

		return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
	}

	private static function palettes(): array {
		$palettes = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Customize/sm_advanced_palette_output.json' ) );
		foreach ( glob( dirname( __DIR__ ) . '/fixtures/palette-parity/*output.json' ) as $file ) {
			$palettes = array_merge( $palettes, json_decode( (string) file_get_contents( $file ) ) );
		}

		return $palettes;
	}

	public function test_the_role_is_registered_as_fg_muted_from_fg1_with_a_4_5_floor(): void {
		$this->assertSame(
			[ 'fg-muted' => [ 'source' => 'fg1', 'minContrast' => 4.5, 'maxMix' => 1.0 ] ],
			style_manager_get_contrast_floor_roles()
		);
	}

	public function test_php_matches_the_corpus_the_js_emitter_is_pinned_to(): void {
		$corpus = self::fixture( 'quiet-text/corpus.json' );
		$this->assertGreaterThan( 1500, count( $corpus ) );

		$mismatches = [];
		foreach ( $corpus as $entry ) {
			$variation = (object) [ 'bg' => $entry['bg'], 'fg1' => $entry['fg1'], 'fg2' => $entry['fg2'] ];
			$roles     = style_manager_get_contrast_floor_role_colors( $variation );
			if ( $roles !== $entry['roles'] ) {
				$mismatches[] = [ $entry, $roles ];
			}
		}

		$this->assertSame( [], array_slice( $mismatches, 0, 5 ) );
	}

	public function test_quiet_text_holds_the_floor_on_all_12_light_and_dark_variations(): void {
		$checked = 0;
		foreach ( self::palettes() as $palette ) {
			foreach ( [ 'variations', 'darkVariations' ] as $key ) {
				$this->assertCount( 12, $palette->{$key} );
				foreach ( $palette->{$key} as $index => $variation ) {
					$quiet = style_manager_get_quiet_text_color( $variation->bg, $variation->fg1 );
					$ratio = self::contrast( $quiet, $variation->bg );
					$this->assertGreaterThanOrEqual( 4.5, $ratio, "palette {$palette->id} {$key}[{$index}] {$quiet} on {$variation->bg}" );
					$checked++;
				}
			}
		}

		$this->assertGreaterThanOrEqual( 24 * 8, $checked );
	}

	public function test_the_issue_case_gets_the_minimal_darkening(): void {
		$fixed = style_manager_get_quiet_text_color( '#ffffff', '#8e9295' );
		$this->assertGreaterThanOrEqual( 4.5, self::contrast( $fixed, '#ffffff' ) );
		$this->assertLessThan( 4.65, self::contrast( $fixed, '#ffffff' ) );

		$grey = style_manager_get_quiet_text_color( '#777777', '#888888' );
		$this->assertGreaterThanOrEqual( 4.5, self::contrast( $grey, '#777777' ) );
	}

	public function test_non_hex_values_pass_through(): void {
		$this->assertSame( '#ffffff', style_manager_get_contrast_floor_color( 'rgb(0,0,0)', '#ffffff', 4.5 ) );
		$this->assertSame( [], style_manager_get_contrast_floor_role_colors( (object) [ 'fg1' => '#000000' ] ) );
		$this->assertSame( '#808080', style_manager_get_contrast_floor_color( '#ffffff', '#000000', 1.2, 0.5 ) );
	}

	public function test_the_variation_css_appends_the_role_and_leaves_existing_roles_untouched(): void {
		$variations = [];
		for ( $i = 0; $i < 12; $i++ ) {
			$variations[] = (object) [ 'bg' => '#f4efe6', 'accent' => '#b0472c', 'fg1' => '#1d1b19', 'fg2' => '#3a3530', 'accent2' => '#1f6fd0' ];
		}

		$css      = style_manager_get_variation_css_variables( $variations, 2, 0 );
		$existing = '--sm-bg-color-3: #f4efe6; --sm-accent-color-3: #b0472c; --sm-fg1-color-3: #1d1b19; --sm-fg2-color-3: #3a3530; --sm-accent2-color-3: #1f6fd0; ';

		$this->assertSame( $existing . '--sm-fg-muted-color-3: ' . style_manager_get_quiet_text_color( '#f4efe6', '#1d1b19' ) . '; ', $css );
	}

	public function test_a_stored_fg_muted_value_cannot_bypass_the_floor(): void {
		$variations = array_fill( 0, 12, (object) [ 'bg' => '#ffffff', 'accent' => '#111111', 'fg1' => '#111111', 'fg2' => '#111111', 'fg-muted' => '#eeeeee' ] );

		$css = style_manager_get_variation_css_variables( $variations, 0, 0 );

		$this->assertStringNotContainsString( '#eeeeee', $css );
		$this->assertSame( 1, substr_count( $css, '--sm-fg-muted-color-1:' ) );
	}

	public function test_palette_css_emits_the_role_for_every_variation_of_every_selector(): void {
		Functions\when( 'get_option' )->justReturn( 1 );

		$palettes = json_decode( (string) file_get_contents( dirname( __DIR__, 3 ) . '/src/Customize/sm_advanced_palette_output.json' ) );
		$css      = style_manager_get_palette_css( $palettes[0] );

		// Light, dark and shifted blocks each carry all 12.
		$this->assertSame( 36, preg_match_all( '/--sm-fg-muted-color-(\d+): #[0-9a-f]{6};/', $css ) );
		$this->assertSame( 36, preg_match_all( '/--sm-fg2-color-\d+: /', $css ) );

		foreach ( $palettes[0]->variations as $index => $variation ) {
			$this->assertStringContainsString(
				'--sm-fg-muted-color-' . ( $index + 1 ) . ': ' . style_manager_get_quiet_text_color( $variation->bg, $variation->fg1 ) . ';',
				$css
			);
		}
	}
}

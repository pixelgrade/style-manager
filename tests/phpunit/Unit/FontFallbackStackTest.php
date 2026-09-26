<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Utils\Fonts as FontsHelper;

/**
 * style-manager#219: each family gets the fallback stack of its own category
 * (serif, sans-serif, display, monospace, handwriting). The cases are shared
 * with the JS twin (tests/js/font-fallback-stack.test.js).
 */
class FontFallbackStackTest extends TestCase {
	private static function fixture(): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/js/support/font-fallback-stack-cases.json' ), true );
	}

	public static function cases(): array {
		$fixture = self::fixture();
		$cases   = [];
		foreach ( $fixture['cases'] as $case ) {
			$cases[ $case['name'] ] = [
				$case['details'],
				$case['categories'] ?? $fixture['categories'],
				$case['catalogCategory'] ?? '',
				$case['expected'],
			];
		}

		return $cases;
	}

	/**
	 * @dataProvider cases
	 */
	public function test_resolves_the_category_stack( array $details, array $categories, string $catalog_category, string $expected ): void {
		$this->assertSame( $expected, FontsHelper::resolveFallbackStack( $details, $categories, $catalog_category ) );
	}

	public function test_the_neutral_stack_matches_the_shared_fixture(): void {
		$this->assertSame( self::fixture()['neutral'], FontsHelper::NEUTRAL_FALLBACK_STACK );
	}

	/**
	 * The font output resolves each used family through the shared resolver,
	 * with the Google Fonts category of the same family as the catalog fallback.
	 */
	public function test_the_font_output_uses_each_family_s_category(): void {
		$fonts = ( new \ReflectionClass( Fonts::class ) )->newInstanceWithoutConstructor();
		$set   = function ( string $property, array $value ) use ( $fonts ): void {
			$reflection = new \ReflectionProperty( Fonts::class, $property );
			$reflection->setAccessible( true );
			$reflection->setValue( $fonts, $value );
		};
		$set( 'categories', self::fixture()['categories'] );
		$set( 'google_fonts', [ 'Lora' => [ 'family' => 'Lora', 'category' => 'serif' ], 'Inter' => [ 'family' => 'Inter', 'category' => 'sans-serif' ] ] );
		$set( 'cloud_fonts', [ 'Reforma1969' => [ 'family' => 'Reforma1969', 'category' => 'display', 'fallback_stack' => '"SF Pro Display", Impact, "Arial Black", sans-serif', 'tags' => [ 'serif' ] ] ] );
		$set( 'font_library_fonts', [ 'Lora Local' => [ 'family' => 'Lora Local', 'category' => 'other', 'fallback_stack' => '' ] ] );
		$set( 'third_party_fonts', [] );
		$set( 'theme_fonts', [] );
		$set( 'system_fonts', [] );

		$stack = new \ReflectionMethod( Fonts::class, 'getFontFamilyFallbackStack' );
		$stack->setAccessible( true );

		$serif = self::fixture()['categories']['serif']['fallback_stack'];
		$this->assertSame( $serif, $stack->invoke( $fonts, 'Lora' ) );
		$this->assertSame( self::fixture()['categories']['sans-serif']['fallback_stack'], $stack->invoke( $fonts, 'Inter' ) );
		$this->assertSame( $serif, $stack->invoke( $fonts, 'Reforma1969' ) );
		$this->assertSame( FontsHelper::NEUTRAL_FALLBACK_STACK, $stack->invoke( $fonts, 'Lora Local' ) );
		$this->assertSame( '', $stack->invoke( $fonts, 'Georgia, serif' ) );

		// A Font Library family named like a Google family borrows its category.
		$set( 'font_library_fonts', [ 'Lora' => [ 'family' => 'Lora', 'category' => 'other', 'fallback_stack' => '' ] ] );
		$this->assertSame( $serif, $stack->invoke( $fonts, 'Lora' ) );
	}
}

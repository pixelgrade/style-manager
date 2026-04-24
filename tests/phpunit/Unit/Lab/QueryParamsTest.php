<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Lab;

use Pixelgrade\StyleManager\Lab\QueryParams;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class QueryParamsTest extends TestCase {
	public function test_defaults_are_used_for_empty_input(): void {
		$params = QueryParams::from_array( [] );

		$this->assertSame( '1', $params->palette() );
		$this->assertSame( 1, $params->variation() );
		$this->assertSame( 0, $params->signal() );
		$this->assertSame( 1, $params->parent_variation() );
		$this->assertFalse( $params->dark() );
		$this->assertFalse( $params->shifted() );
		$this->assertSame( '', $params->contextual() );
	}

	public function test_values_are_sanitized_and_clamped(): void {
		$params = QueryParams::from_array( [
			'palette'         => 'Brand Blue!',
			'variation'       => '20',
			'signal'          => '9',
			'parentVariation' => '8',
			'dark'            => '1',
			'shifted'         => 'true',
			'contextual'      => '#ABCDEF',
		] );

		$this->assertSame( 'brand-blue', $params->palette() );
		$this->assertSame( 12, $params->variation() );
		$this->assertSame( 3, $params->signal() );
		$this->assertSame( 9, $params->parent_variation() );
		$this->assertTrue( $params->dark() );
		$this->assertTrue( $params->shifted() );
		$this->assertSame( '#abcdef', $params->contextual() );
	}

	public function test_invalid_contextual_color_is_dropped(): void {
		$params = QueryParams::from_array( [
			'contextual' => 'javascript:alert(1)',
		] );

		$this->assertSame( '', $params->contextual() );
	}
}

<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Customize;

use Brain\Monkey\Functions;
use Pixelgrade\StyleManager\Customize\TweakBoardSection;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class TweakBoardSectionTest extends TestCase {
	public function test_tweak_board_defines_collection_and_decorative_title_controls(): void {
		Functions\when( 'apply_filters' )->alias(
			static fn( string $hook, $value ) => 'style_manager/tweak_board_is_supported' === $hook ? $value : $value
		);
		Functions\when( 'esc_html__' )->returnArg( 1 );

		$config = ( new TestTweakBoardSection() )->expose_add_style_manager_section_master_tweak_board_config( [] );

		$options = $config['sections']['style_manager_section']['options'];

		$this->assertSame( [ 'above', 'sideways' ], array_keys( $options['sm_collection_title_position']['choices'] ) );
		$this->assertSame( 'above', $options['sm_collection_title_position']['default'] );
		$this->assertSame( [ 'none', 'hive', 'felt', 'pile' ], array_keys( $options['sm_collection_hover_effect']['choices'] ) );
		$this->assertSame( 'none', $options['sm_collection_hover_effect']['default'] );
		$this->assertSame( [ 'underline', 'blocky' ], array_keys( $options['sm_decorative_titles_style']['choices'] ) );
		$this->assertSame( 'underline', $options['sm_decorative_titles_style']['default'] );
	}
}

class TestTweakBoardSection extends TweakBoardSection {
	public function expose_add_style_manager_section_master_tweak_board_config( array $config ): array {
		return $this->add_style_manager_section_master_tweak_board_config( $config );
	}
}

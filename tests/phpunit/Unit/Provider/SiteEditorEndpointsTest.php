<?php
declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Tests\Unit\Provider;

use Pixelgrade\StyleManager\Customize\FontPalettes;
use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Provider\FrontendOutput;
use Pixelgrade\StyleManager\Provider\HeadlessCustomizer;
use Pixelgrade\StyleManager\Provider\SiteEditorEndpoints;
use Pixelgrade\StyleManager\Screen\EditWithBlocks;
use Pixelgrade\StyleManager\Tests\Unit\TestCase;

class SiteEditorEndpointsTest extends TestCase {
	public function test_saving_font_palette_applies_connected_font_fields(): void {
		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->once() )
			->method( 'apply_current_font_palette_to_connected_fields' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects(
			[ FontPalettes::SM_FONT_PALETTE_OPTION_KEY ]
		);
	}

	public function test_saving_other_settings_does_not_apply_font_palette_connected_fields(): void {
		$font_palettes = $this->createMock( FontPalettes::class );
		$font_palettes
			->expects( $this->never() )
			->method( 'apply_current_font_palette_to_connected_fields' );

		$endpoints = new TestSiteEditorEndpoints(
			$this->createMock( HeadlessCustomizer::class ),
			$this->createMock( EditWithBlocks::class ),
			$this->createMock( Fonts::class ),
			$font_palettes,
			$this->createMock( FrontendOutput::class )
		);

		$endpoints->expose_apply_post_save_side_effects(
			[ 'sm_color_palette_in_use' ]
		);
	}
}

class TestSiteEditorEndpoints extends SiteEditorEndpoints {
	public function expose_apply_post_save_side_effects( array $values ): void {
		$this->apply_post_save_side_effects( $values );
	}
}

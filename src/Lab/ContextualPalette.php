<?php
/**
 * Runtime contextual palette helper for Style Manager Lab.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

final class ContextualPalette {
	public const ID = 'contextual-lab';
	public const LABEL = 'Contextual Lab';

	private const MIXES = [
		[ '#ffffff', 0.92 ],
		[ '#ffffff', 0.84 ],
		[ '#ffffff', 0.72 ],
		[ '#ffffff', 0.60 ],
		[ '#ffffff', 0.40 ],
		[ '#ffffff', 0.20 ],
		[ null, 0.00 ],
		[ '#000000', 0.18 ],
		[ '#000000', 0.34 ],
		[ '#000000', 0.50 ],
		[ '#000000', 0.66 ],
		[ '#000000', 0.82 ],
	];

	public function css_for_color( string $color ): string {
		if ( '' === $color ) {
			return '';
		}

		$palette = \sm_build_contextual_palette_from_color( $color, self::ID, self::LABEL );

		if ( empty( $palette ) ) {
			return '';
		}

		return \sm_palettes_output( [ $palette ] );
	}

	public function palette_for_color( string $color ) {
		$color = $this->normalize_hex( $color );

		if ( '' === $color ) {
			return null;
		}

		$variations      = [];
		$dark_variations = [];

		foreach ( self::MIXES as [ $reference, $ratio ] ) {
			$background      = null === $reference ? $color : \sm_mix_hex_colors( $color, $reference, $ratio );
			$dark_background = \sm_mix_hex_colors( $background, '#000000', 0.18 );

			$variations[]      = $this->build_variation( $background, $color );
			$dark_variations[] = $this->build_variation( $dark_background, $color );
		}

		return (object) [
			'id'             => self::ID,
			'label'          => self::LABEL,
			'source'         => [ $color ],
			'sourceIndex'    => 6,
			'variations'     => $variations,
			'darkVariations' => $dark_variations,
		];
	}

	private function normalize_hex( string $color ): string {
		$normalized = strtolower( trim( $color ) );

		return 1 === preg_match( '/^#[0-9a-f]{6}$/', $normalized ) ? $normalized : '';
	}

	private function build_variation( string $background, string $source ): object {
		$foreground = $this->pick_text_color( $background );
		$accent     = \sm_get_accessible_contextual_accent( $background, $source, $foreground );

		return (object) [
			'bg'     => $background,
			'accent' => $accent,
			'fg1'    => $foreground,
			'fg2'    => $foreground,
		];
	}

	private function pick_text_color( string $background ): string {
		$black_contrast = \sm_hex_color_contrast_ratio( $background, '#111111' );
		$white_contrast = \sm_hex_color_contrast_ratio( $background, '#ffffff' );

		return $white_contrast >= $black_contrast ? '#ffffff' : '#111111';
	}
}

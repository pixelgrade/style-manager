<?php
/**
 * Style Manager Lab query parameter contract.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

final class QueryParams {
	private string $palette;
	private int $variation;
	private int $signal;
	private int $parent_variation;
	private bool $dark;
	private bool $shifted;
	private string $contextual;

	private function __construct( array $params ) {
		$this->palette          = self::sanitize_palette( $params['palette'] ?? '1' );
		$this->variation        = self::clamp_int( $params['variation'] ?? 1, 1, 12 );
		$this->signal           = self::clamp_int( $params['signal'] ?? 0, 0, 3 );
		$this->parent_variation = self::sanitize_parent_variation( $params['parentVariation'] ?? $params['parent_variation'] ?? 1 );
		$this->dark             = self::sanitize_boolean( $params['dark'] ?? false );
		$this->shifted          = self::sanitize_boolean( $params['shifted'] ?? false );
		$this->contextual       = self::sanitize_hex( $params['contextual'] ?? '' );
	}

	public static function from_array( array $params ): self {
		return new self( $params );
	}

	public function palette(): string {
		return $this->palette;
	}

	public function variation(): int {
		return $this->variation;
	}

	public function signal(): int {
		return $this->signal;
	}

	public function parent_variation(): int {
		return $this->parent_variation;
	}

	public function dark(): bool {
		return $this->dark;
	}

	public function shifted(): bool {
		return $this->shifted;
	}

	public function contextual(): string {
		return $this->contextual;
	}

	public function body_classes(): array {
		$classes = [
			'sm-lab-showcase',
			'sm-palette-' . $this->palette,
			'sm-variation-' . $this->variation,
		];

		if ( $this->dark ) {
			$classes[] = 'is-dark';
		}

		if ( $this->shifted ) {
			$classes[] = 'sm-palette--shifted';
		}

		return $classes;
	}

	private static function sanitize_palette( $value ): string {
		$palette = strtolower( trim( (string) $value ) );
		$palette = preg_replace( '/[^a-z0-9_-]+/', '-', $palette );
		$palette = trim( (string) $palette, '-' );

		return '' !== $palette ? $palette : '1';
	}

	private static function sanitize_hex( $value ): string {
		$color = strtolower( trim( (string) $value ) );

		return 1 === preg_match( '/^#[0-9a-f]{6}$/', $color ) ? $color : '';
	}

	private static function sanitize_boolean( $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value;
	}

	private static function clamp_int( $value, int $min, int $max ): int {
		$value = (int) $value;

		return min( max( $value, $min ), $max );
	}

	private static function sanitize_parent_variation( $value ): int {
		$value = self::clamp_int( $value, 1, 9 );
		$slots = [ 1, 5, 9 ];

		usort( $slots, function( int $a, int $b ) use ( $value ): int {
			return abs( $a - $value ) <=> abs( $b - $value );
		} );

		return $slots[0];
	}
}

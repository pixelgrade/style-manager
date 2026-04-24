<?php
/**
 * Style Manager Lab access checks.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

final class Access {
	public static function is_enabled(): bool {
		return (bool) apply_filters( 'style_manager/enable_lab', true );
	}

	public static function current_user_can_access(): bool {
		return current_user_can( 'manage_options' );
	}
}

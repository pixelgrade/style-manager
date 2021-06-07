<?php
/**
 * Capabilities.
 *
 * Meta capabilities are mapped to primitive capabilities in
 * \Pixelgrade\StyleManager\Provider\Capabilities.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager;

/**
 * Capabilities.
 *
 * @since 2.0.0
 */
final class Capabilities {

	/**
	 * Primitive capability for managing options.
	 *
	 * @var string
	 */
	const MANAGE_OPTIONS = 'pixelgrade_style_manager_manage_options';

	/**
	 * Register capabilities.
	 *
	 * @since 2.0.0
	 */
	public static function register() {
		$wp_roles = wp_roles();

		$wp_roles->add_cap( 'administrator', self::MANAGE_OPTIONS );
	}
}

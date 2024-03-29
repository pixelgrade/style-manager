<?php
/**
 * The Events Calendar (including the PRO version) plugin integration.
 *
 * @link    https://theeventscalendar.com/
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Integration;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * The Events Calendar plugin (including the PRO version) integration provider class.
 *
 * @since 2.0.0
 */
class TheEventsCalendar extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_action( 'admin_enqueue_scripts', 'deregister_select2', 99 );
	}

	/**
	 * Deregister The Event Calendar's select2 scripts and styles since they mess up our Select2.
	 *
	 * @since 2.0.0
	 */
	protected function deregister_select2() {
		if ( ! is_customize_preview() ) {
			return;
		}

		wp_deregister_script( 'tribe-select2' );
		wp_register_script( 'tribe-select2', '' );

		wp_deregister_style( 'tribe-select2-css' );
		wp_register_style( 'tribe-select2-css', '' );
	}
}

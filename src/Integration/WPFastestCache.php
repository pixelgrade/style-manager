<?php
/**
 * WP Fastest Cache plugin integration.
 *
 * @link    https://wordpress.org/plugins/wp-fastest-cache/
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Integration;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * WP Fastest Cache plugin integration provider class.
 *
 * @since 2.0.0
 */
class WPFastestCache extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_filter( 'default_option_WpFastestCacheExclude', 'exclude_scripts_from_minify', 10, 1 );
	}

	/**
	 * Try to exclude the webfontloader script by adding a default rule in the plugin options.
	 *
	 * @since 2.0.0
	 *
	 * @param $default
	 *
	 * @return mixed
	 */
	protected function exclude_scripts_from_minify( $default ) {
		$webfontloader_script_url = $this->plugin->get_url('vendor_js/webfontloader');
		if ( empty( $default ) ) {
			$default = json_encode( [
				[
					'prefix' => 'contain',
					'content' => $webfontloader_script_url,
					'type' => 'js',
				]
			] );
		}

		return $default;
	}
}

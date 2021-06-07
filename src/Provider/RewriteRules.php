<?php
/**
 * Register rewrite rules.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use WP_Rewrite;

/**
 * Class to register rewrite rules.
 *
 * @since 2.0.0
 */
class RewriteRules extends AbstractHookProvider {
	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_action( 'generate_rewrite_rules', [ $this, 'register_external_rewrite_rules' ] );
		add_action( 'wp_loaded', [ $this, 'maybe_flush_rewrite_rules' ] );
	}

	/**
	 * Register query variables.
	 *
	 * @since 2.0.0
	 *
	 * @param array $vars List of query variables.
	 * @return array
	 */
	public function register_query_vars( array $vars ): array {

		return $vars;
	}

	/**
	 * Register rewrite rules.
	 *
	 * @since 2.0.0
	 */
	public function register_rewrite_rules() {
	}

	/**
	 * Register external rewrite rules.
	 *
	 * This added to .htaccess on Apache servers to account for cases where
	 * WordPress doesn't handle the .json file extension.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_Rewrite $wp_rewrite WP rewrite API.
	 */
	public function register_external_rewrite_rules( WP_Rewrite $wp_rewrite ) {

	}

	/**
	 * Flush the rewrite rules if needed.
	 *
	 * @since 2.0.0
	 */
	public function maybe_flush_rewrite_rules() {
		if ( is_network_admin() || 'no' === get_option( 'pixelgrade_style_manager_flush_rewrite_rules' ) ) {
			return;
		}

		update_option( 'pixelgrade_style_manager_flush_rewrite_rules', 'no' );
		flush_rewrite_rules();
	}
}

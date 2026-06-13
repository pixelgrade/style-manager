<?php
/**
 * WP-CLI commands provider.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.3.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Registers Style Manager's WP-CLI commands.
 *
 * @since 2.3.0
 */
class CliCommands extends AbstractHookProvider {

	/**
	 * Options provider.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Create the WP-CLI commands provider.
	 *
	 * @since 2.3.0
	 *
	 * @param Options $options Options provider.
	 */
	public function __construct( Options $options ) {
		$this->options = $options;
	}

	/**
	 * Register the commands.
	 *
	 * @since 2.3.0
	 */
	public function register_hooks() {
		if ( ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		\WP_CLI::add_command( 'style-manager flush-cache', [ $this, 'flush_cache' ] );
	}

	/**
	 * Flush Style Manager's cached Customizer config and option details.
	 *
	 * Use after changing option or section definitions in code so the cached
	 * config is rebuilt on the next request — instead of bumping the plugin
	 * version, waiting for the cache to expire, or deleting options by hand.
	 *
	 * ## EXAMPLES
	 *
	 *     wp style-manager flush-cache
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Associative arguments. Unused.
	 */
	public function flush_cache( $args, $assoc_args ) {
		$this->options->invalidate_all_caches();

		\WP_CLI::success( 'Style Manager caches flushed (Customizer config, option details, opt-name).' );
	}
}

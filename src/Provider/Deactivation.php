<?php
/**
 * Plugin deactivation routines.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\Provider;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Class to deactivate the plugin.
 *
 * @since 2.0.0
 */
class Deactivation extends AbstractHookProvider {

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options         $options Options.
	 * @param LoggerInterface $logger  Logger.
	 */
	public function __construct(
		Options $options,
		LoggerInterface $logger
	) {
		$this->options          = $options;
		$this->logger          = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		register_deactivation_hook( $this->plugin->get_file(), [ $this, 'deactivate' ] );
	}

	/**
	 * Deactivation routine.
	 *
	 * Deleting the rewrite rules option should force WordPress to regenerate them next time they're needed.
	 *
	 * @since 2.0.0
	 */
	public function deactivate() {
		delete_option( 'rewrite_rules' );
		delete_option( 'pixelgrade_style_manager_flush_rewrite_rules' );

		$this->options->invalidate_all_caches();
	}
}

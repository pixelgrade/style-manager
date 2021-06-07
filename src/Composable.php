<?php
/**
 * Composable interface
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 0.1.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager;

/**
 * Segregated interface of something that should be composed.
 */
interface Composable {
	/**
	 * Compose the object graph.
	 *
	 * @since 0.1.0
	 */
	public function compose();
}

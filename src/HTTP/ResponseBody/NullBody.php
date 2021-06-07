<?php
/**
 * Null response body.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\HTTP\ResponseBody;

/**
 * Null response body class.
 *
 * @since 2.0.0
 */
class NullBody implements ResponseBody {
	/**
	 * Emit the body.
	 *
	 * @since 2.0.0
	 */
	public function emit() {
		// Silence.
	}
}

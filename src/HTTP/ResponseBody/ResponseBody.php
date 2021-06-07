<?php
/**
 * Response body interface.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\HTTP\ResponseBody;

/**
 * Response body interface.
 *
 * @since 2.0.0
 */
interface ResponseBody {
	/**
	 * Emit the response body.
	 */
	public function emit();
}

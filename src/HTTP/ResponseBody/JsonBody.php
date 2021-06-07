<?php
/**
 * JSON response body.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 * @since 2.0.0
 */

declare ( strict_types = 1 );

namespace Pixelgrade\StyleManager\HTTP\ResponseBody;

/**
 * JSON response body class.
 *
 * @since 2.0.0
 */
class JsonBody implements ResponseBody {
	/**
	 * Message data.
	 *
	 * @var mixed
	 */
	protected $data;

	/**
	 * Create a JSON response body.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $data Response data.
	 */
	public function __construct( $data ) {
		$this->data = $data;
	}

	/**
	 * Emit the data as a JSON-serialized string.
	 *
	 * @since 2.0.0
	 */
	public function emit() {
		echo wp_json_encode( $this->data );
	}
}

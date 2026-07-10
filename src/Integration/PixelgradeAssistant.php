<?php
/**
 * Pixelgrade Assistant plugin integration.
 *
 * @link    https://wordpress.org/plugins/pixelgrade-assistant/
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Integration;

use Pixelgrade\StyleManager\Provider\DesignSystemPreviewEndpoint;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

/**
 * Pixelgrade Assistant plugin integration provider class.
 *
 * @since 2.0.0
 */
class PixelgradeAssistant extends AbstractHookProvider {

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Options $options Options.
	 */
	public function __construct(
		Options $options
	) {
		$this->options = $options;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_filter( 'pre_set_theme_mod_pixassist_license', 'invalidate_all_caches', 10, 1 );
		$this->add_filter( 'pixassist_styles_data', 'add_design_system_preview', 10, 1 );
	}

	/**
	 * Advertises the versioned saved design-system preview contract to Assistant.
	 *
	 * @since 2.4.0
	 *
	 * @param mixed $data Assistant Styles tab data.
	 *
	 * @return mixed
	 */
	protected function add_design_system_preview( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$data['designSystemPreview'] = [
			'schemaVersion' => DesignSystemPreviewEndpoint::SCHEMA_VERSION,
			'path'          => '/' . DesignSystemPreviewEndpoint::REST_NAMESPACE . DesignSystemPreviewEndpoint::REST_PATH,
		];

		return $data;
	}

	/**
	 * Invalidate all caches on license update.
	 *
	 * @since 2.0.0
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	protected function invalidate_all_caches( $value ) {
		$this->options->invalidate_all_caches();

		return $value;
	}
}

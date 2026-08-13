<?php
/**
 * Style Manager Lab config payload builder.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

final class Config {
	/** @var callable */
	private $get_palette_runtime_payload;

	/** @var callable */
	private $get_sm_option;

	/** @var callable */
	private $get_wp_option;

	/** @var callable */
	private $admin_url;

	/** @var callable */
	private $home_url;

	/** @var callable */
	private $create_nonce;

	public function __construct(
		?callable $get_palette_runtime_payload = null,
		?callable $get_sm_option = null,
		?callable $get_wp_option = null,
		?callable $admin_url = null,
		?callable $home_url = null,
		?callable $create_nonce = null
	) {
		$this->get_palette_runtime_payload = $get_palette_runtime_payload ?: static function (): array {
			return function_exists( 'sm_get_palette_runtime_payload' )
				? \sm_get_palette_runtime_payload()
				: [ 'palettes' => [] ];
		};
		$this->get_sm_option = $get_sm_option ?: static function ( string $option_id, $default = null ) {
			return \Pixelgrade\StyleManager\get_option( $option_id, $default );
		};
		$this->get_wp_option = $get_wp_option ?: static function ( string $option_id, $default = false ) {
			return \get_option( $option_id, $default );
		};
		$this->admin_url = $admin_url ?: static function ( string $path = '' ): string {
			return admin_url( $path );
		};
		$this->home_url = $home_url ?: static function ( string $path = '/' ): string {
			return home_url( $path );
		};
		$this->create_nonce = $create_nonce ?: static function ( string $action ): string {
			return wp_create_nonce( $action );
		};
	}

	public function admin_config(): array {
		$runtime_palette_payload = ( $this->get_palette_runtime_payload )();
		$site_variation          = (int) ( $this->get_sm_option )( 'sm_site_color_variation', 1 );

		return [
			'adminUrl'        => ( $this->admin_url )( 'tools.php?page=' . LabAdminPage::MENU_SLUG ),
			'showcaseBaseUrl' => ( $this->home_url )( '/' ),
			'showcaseNonce'   => ( $this->create_nonce )( 'sm_lab_showcase' ),
			'ajaxUrl'         => ( $this->admin_url )( 'admin-ajax.php' ),
			'ajaxNonce'       => ( $this->create_nonce )( 'sm_lab_refresh' ),
			'palettes'        => $runtime_palette_payload['palettes'] ?? [],
			'siteVariation'   => $site_variation,
			'defaultState'    => [
				'palette'         => (string) ( $this->get_sm_option )( 'sm_color_palette_in_use', '1' ),
				'variation'       => $site_variation,
				'signal'          => 0,
				'parentVariation' => 1,
				'dark'            => 'on' === (string) ( $this->get_wp_option )( 'sm_dark_mode_advanced', 'off' ),
				'shifted'         => false,
				'contextual'      => '',
			],
		];
	}

	public function color_system_preview_config( QueryParams $params ): array {
		$runtime_palette_payload = ( $this->get_palette_runtime_payload )();

		return [
			'palettes'      => $runtime_palette_payload['palettes'] ?? [],
			'siteVariation' => (int) ( $this->get_sm_option )( 'sm_site_color_variation', 1 ),
			'isDark'        => $params->dark(),
			'strings'       => $this->color_system_preview_strings(),
		];
	}

	private function color_system_preview_strings(): array {
		return [
			'palettePreviewTitle'                => $this->translate( 'The color system' ),
			'palettePreviewDesc'                 => $this->translate( 'The color system presented below is designed based on your brand colors. Hover over a color grade to see a preview of how you will be able to use colors with your content blocks.' ),
			'palettePreviewListDesc'             => $this->translate( 'Each column from the color palette below represent a state where a component could be. The first row is the main surface or background color, while the other two rows are for the content.' ),
			'palettePreviewSwatchSurfaceText'    => $this->translate( 'Surface' ),
			'palettePreviewSwatchAccentText'     => $this->translate( 'Accent' ),
			'palettePreviewSwatchForegroundText' => $this->translate( 'Text' ),
		];
	}

	private function translate( string $text ): string {
		// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- Lab-only helper; the literal strings are passed at the call sites for extraction tooling to pick up.
		return function_exists( '__' ) ? __( $text, '__plugin_txtd' ) : $text;
	}
}

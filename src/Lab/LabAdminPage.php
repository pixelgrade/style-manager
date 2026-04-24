<?php
/**
 * Style Manager Lab admin page.
 *
 * @package Style Manager
 * @license GPL-2.0-or-later
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Lab;

use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;

final class LabAdminPage extends AbstractHookProvider {
	public const MENU_SLUG = 'sm-lab';

	private string $page_hook = '';
	private Config $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	public function register_hooks() {
		if ( ! Access::is_enabled() ) {
			return;
		}

		$this->add_action( 'admin_menu', 'register_page' );
		$this->add_action( 'wp_ajax_style_manager_lab_config', 'ajax_config' );
	}

	protected function register_page(): void {
		$this->page_hook = (string) add_management_page(
			esc_html__( 'Style Manager Lab', '__plugin_txtd' ),
			esc_html__( 'Style Manager Lab', '__plugin_txtd' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render' ]
		);

		if ( '' !== $this->page_hook ) {
			add_action( 'load-' . $this->page_hook, [ $this, 'load_screen' ] );
		}
	}

	public function load_screen(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function enqueue_assets(): void {
		wp_enqueue_script( 'pixelgrade_style_manager-lab' );
		wp_enqueue_style( 'pixelgrade_style_manager-lab' );

		wp_add_inline_script(
			'pixelgrade_style_manager-lab',
			'window.styleManagerLab = ' . wp_json_encode( $this->config->admin_config() ) . ';',
			'before'
		);
	}

	public function render(): void {
		if ( ! Access::current_user_can_access() ) {
			wp_die( esc_html__( 'You are not allowed to access the Style Manager Lab.', '__plugin_txtd' ), '', [ 'response' => 403 ] );
		}

		echo '<div class="wrap sm-lab-admin-wrap"><div id="style-manager-lab-root"></div></div>';
	}

	public function ajax_config(): void {
		if ( ! Access::current_user_can_access() ) {
			wp_send_json_error( esc_html__( 'You are not allowed to access the Style Manager Lab.', '__plugin_txtd' ), 403 );
		}

		check_ajax_referer( 'sm_lab_refresh', 'nonce' );

		wp_send_json_success( $this->config->admin_config() );
	}
}

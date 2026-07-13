<?php
/**
 * Settings screen provider.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen;

use Carbon_Fields\Carbon_Fields;
use Carbon_Fields\Container\Container;
use Carbon_Fields\Datastore\Datastore;
use Carbon_Fields\Field;
use Pixelgrade\StyleManager\Capabilities;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\Options;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * Settings screen provider class.
 *
 * @since 2.0.0
 */
class Settings extends AbstractHookProvider {

	const MENU_SLUG = 'style-manager';

	/**
	 * User messages to display in the WP admin.
	 *
	 * @var array
	 */
	protected array $user_messages = [
		'error'   => [],
		'warning' => [],
		'info'    => [],
	];

	/**
	 * Options.
	 *
	 * @var Options
	 */
	protected Options $options;

	/**
	 * The Carbon Fields datastore to use.
	 *
	 * @var Datastore
	 */
	protected Datastore $cf_datastore;

	/**
	 * The local font store, used to report which cloud fonts are hosted locally.
	 *
	 * @var LocalFontStore
	 */
	protected LocalFontStore $local_font_store;

	/**
	 * Logger.
	 *
	 * @var LoggerInterface
	 */
	protected LoggerInterface $logger;

	/**
	 * Create the setting screen.
	 *
	 * @since 2.0.0
	 *
	 * @param Options         $options          Options.
	 * @param Datastore       $cf_datastore     The Carbon Fields datastore to use.
	 * @param LocalFontStore  $local_font_store The local font store.
	 * @param LoggerInterface $logger           Logger.
	 */
	public function __construct(
		Options $options,
		Datastore $cf_datastore,
		LocalFontStore $local_font_store,
		LoggerInterface $logger
	) {
		$this->options          = $options;
		$this->cf_datastore     = $cf_datastore;
		$this->local_font_store = $local_font_store;
		$this->logger           = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_action( 'setup_theme', 'carbonfields_load', 99 );
		$this->add_action( 'carbon_fields_register_fields', 'setup' );
		$this->add_action( 'current_screen', 'hook_to_screen' );

		$this->add_filter( 'carbon_fields_theme_options_container_admin_only_access', 'disable_default_access', 10, 3 );

		// Modify the plugin's actions on the plugins list page.
		$this->add_filter( 'plugin_action_links_' . $this->plugin->get_basename(), 'add_action_links' );

		$this->add_action( 'rest_api_init', 'add_rest_api_routes' );
	}

	/**
	 * Setup the settings page and options.
	 *
	 * @since 2.0.0
	 */
	protected function setup() {
		Container::make( 'theme_options', 'style_manager_options', esc_html__( 'Style Manager', '__plugin_txtd' ) )
		         ->set_page_parent( $this->get_page_parent() )
		         ->set_page_menu_title( esc_html__( 'Style Manager', '__plugin_txtd' ) )
		         ->set_page_file( self::MENU_SLUG )
		         ->where( 'current_user_capability', '=', Capabilities::MANAGE_OPTIONS )
		         ->set_datastore( $this->cf_datastore )
		         ->add_tab( esc_html__( 'General', '__plugin_txtd' ), [
			         Field::make( 'select', 'values_store_mod', esc_html__( 'Store values as:', '__plugin_txtd' ) )
			              ->set_help_text(
							  esc_html__( 'You can store the values globally so you can use them with other themes or store them as a "theme_mod" which will make an individual set of options only for the current theme.', '__plugin_txtd' ) .
							  '<p>' . wp_kses_post( __( '<strong>Important Note:</strong> On save with a different way of storing, we will <strong>automatically migrate your existing data</strong> to the new storage location, so you can easily pick-up from were you left off.', '__plugin_txtd' ) ). '</p>'
			                )
			              ->set_options( [
				              'option'    => esc_html__( 'Option (global options)', '__plugin_txtd' ),
				              'theme_mod' => esc_html__( 'Theme Mod (per theme options)', '__plugin_txtd' ),
			              ] )
			              ->set_default_value( 'theme_mod' )
			              ->set_required( true ),
			         Field::make( 'set', 'disable_default_sections', esc_html__( 'Disable default sections', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'By checking the checkboxes above, you can disable sections available by default in the Customize view.', '__plugin_txtd' ) )
			              ->set_options( [
				              'nav'               => esc_html__( 'Navigation', '__plugin_txtd' ),
				              'static_front_page' => esc_html__( 'Front Page', '__plugin_txtd' ),
				              'title_tagline'     => esc_html__( 'Title', '__plugin_txtd' ),
				              'colors'            => esc_html__( 'Colors', '__plugin_txtd' ),
				              'background_image'  => esc_html__( 'Background', '__plugin_txtd' ),
				              'header_image'      => esc_html__( 'Header', '__plugin_txtd' ),
				              'widgets'           => esc_html__( 'Widgets', '__plugin_txtd' ),
			              ] ),
			         Field::make( 'checkbox', 'enable_reset_buttons', esc_html__( 'Enable Reset Buttons', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'You can enable "Reset to defaults" buttons for panels / sections or all settings. We have disabled this feature by default to avoid accidental resets. If you are sure that you need it please enable this.', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' ),
			         Field::make( 'checkbox', 'enable_editor_style', esc_html__( 'Enable Editor Style', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'The styling added by Style Manager in front-end can be added in the WordPress editor too by enabling this option', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' ),
		         ] )
		         ->add_tab( esc_html__( 'Output', '__plugin_txtd' ), [
			         Field::make( 'select', 'style_resources_location', esc_html__( 'Styles location:', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Here you can decide where to put your style output, in header or footer', '__plugin_txtd' ) )
			              ->set_options( [
				              'wp_head'   => esc_html__( 'In head (just before the closing head tag)', '__plugin_txtd' ),
				              'wp_footer' => esc_html__( 'Footer (just before the end of the body tag)', '__plugin_txtd' ),
			              ] )
			              ->set_default_value( 'wp_footer' )
			              ->set_required( true ),
		         ] )
		         ->add_tab( esc_html__( 'Typography', '__plugin_txtd' ), [
			         Field::make( 'checkbox', 'enable_typography', esc_html__( 'Enable Typography Options', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' ),
			         Field::make( 'checkbox', 'typography_system_fonts', esc_html__( 'Use system fonts', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Would you like to have system fonts available in the font controls?', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
			              ] ),
			         Field::make( 'checkbox', 'typography_google_fonts', esc_html__( 'Use Google fonts', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Would you like to have Google fonts available in the font controls?', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
			              ] ),
			         Field::make( 'checkbox', 'typography_group_google_fonts', esc_html__( 'Group Google fonts', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'See Google fonts grouped by their type instead of alphabetical order.', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
				              [
					              'field' => 'typography_google_fonts',
					              'value' => true,
				              ],
			              ] ),
			         Field::make( 'checkbox', 'typography_cloud_fonts', esc_html__( 'Use cloud fonts', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Would you like to have Cloud fonts available in the font controls?', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
			              ] ),
			         Field::make( 'checkbox', 'typography_host_cloud_fonts_locally', esc_html__( 'Keep fonts on this site', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Every cloud font you use is saved to your site and served from here. Your visitors never connect to Pixelgrade, and your typography is yours to keep — it works forever, with or without us.', '__plugin_txtd' ) )
			              ->set_option_value( 'yes' )
			              ->set_default_value( 'yes' )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
				              [
					              'field' => 'typography_cloud_fonts',
					              'value' => true,
				              ],
			              ] ),
			         Field::make( 'html', 'local_fonts_status' )
			              ->set_html( [ $this, 'get_local_fonts_status_html' ] )
			              ->set_conditional_logic( [
				              [
					              'field' => 'enable_typography',
					              'value' => true,
				              ],
				              [
					              'field' => 'typography_cloud_fonts',
					              'value' => true,
				              ],
			              ] ),

		         ] )
		         ->add_tab( esc_html__( 'Tools', '__plugin_txtd' ), [
			         Field::make( 'html', 'reset_customizer_settings', esc_html__( 'Reset Customizer Settings', '__plugin_txtd' ) )
			              ->set_help_text( esc_html__( 'Resets all the Customizer settings introduced by this plugin. It will NOT reset the core WordPress Customizer settings or plugin settings.', '__plugin_txtd' ) )
			              ->set_html( $this->get_reset_customizer_settings_html() ),
		         ] );
	}

	protected function get_reset_customizer_settings_html(): string {
		return '<br><div class="reset_style_manager_settings"><button type="button" class="button" id="reset_customizer_settings">' . esc_html__( 'Reset Customizer Settings', '__plugin_txtd' ) . '</button></div>';
	}

	/**
	 * Build the local fonts status HTML from the current local fonts manifest.
	 *
	 * Public so Carbon Fields can invoke it as the `html` field's lazy content
	 * callback (`Field::make( 'html', ... )->set_html( [ $this, ... ] )`).
	 *
	 * @since 2.4.0
	 */
	public function get_local_fonts_status_html(): string {
		return self::build_local_fonts_status_html( $this->local_font_store->get_manifest() ) . $this->get_manage_in_hub_link_html();
	}

	/**
	 * Build the "Manage in Pixelgrade Design &rarr;" link paragraph appended
	 * after the local fonts status HTML, or an empty string when the hub
	 * isn't available.
	 *
	 * @since 2.4.0
	 *
	 * @return string Escaped HTML, or ''.
	 */
	protected function get_manage_in_hub_link_html(): string {
		$hub_url = $this->get_hub_fonts_url();
		if ( '' === $hub_url ) {
			return '';
		}

		return sprintf(
			'<p><a href="%1$s">%2$s &rarr;</a></p>',
			esc_url( $hub_url ),
			esc_html__( 'Manage in Pixelgrade Design', '__plugin_txtd' )
		);
	}

	/**
	 * Get the URL to the Fonts section of the Pixelgrade Design hub's Styles tab.
	 *
	 * Extracted as a seam over the shared namespaced helper -- the underlying
	 * `pixassist_get_hub_url()` function is defined by the Assistant plugin at
	 * runtime, which can't be toggled on/off mid test-suite the way an
	 * overridden method can.
	 *
	 * @since 2.4.0
	 *
	 * @return string
	 */
	protected function get_hub_fonts_url(): string {
		return \Pixelgrade\StyleManager\get_design_hub_fonts_url();
	}

	/**
	 * Build the local fonts status HTML for a given local fonts manifest.
	 *
	 * Pure and static so it can be unit-tested without a full Settings instance.
	 *
	 * @since 2.4.0
	 *
	 * @param array $manifest The local fonts manifest, as returned by
	 *                        `LocalFontStore::get_manifest()` (`[ family => entry ]`).
	 *
	 * @return string Escaped HTML.
	 */
	public static function build_local_fonts_status_html( array $manifest ): string {
		if ( empty( $manifest ) ) {
			return '<p>' . esc_html__( 'No cloud fonts saved to your site yet.', '__plugin_txtd' ) . '</p>';
		}

		$rows     = [];
		$ok_count = 0;
		foreach ( $manifest as $family => $entry ) {
			$entry        = is_array( $entry ) ? $entry : [];
			$family_label = ! empty( $entry['family_display'] ) ? (string) $entry['family_display'] : (string) $family;
			$is_ok        = 'ok' === ( $entry['status'] ?? '' );
			$status_label = $is_ok
				? esc_html__( 'on your site', '__plugin_txtd' )
				: esc_html__( 'download hiccup, we’ll retry automatically', '__plugin_txtd' );

			if ( $is_ok ) {
				$ok_count++;
			}

			$rows[] = '<li>' . esc_html( $family_label ) . ' &mdash; ' . $status_label . '</li>';
		}

		// Only count successfully-hosted entries in the heading; failed
		// entries are still listed below (with their status) so the count
		// doesn't overstate what's actually being served locally.
		$heading = esc_html(
			sprintf(
				/* translators: %d: number of font families saved to this site. */
				_n(
					'%d font family saved to your site.',
					'%d font families saved to your site.',
					$ok_count,
					'__plugin_txtd'
				),
				$ok_count
			)
		);

		return '<p>' . $heading . '</p><ul>' . implode( '', $rows ) . '</ul>';
	}

	protected function get_page_parent(): string {
		$parent_slug = 'options-general.php';
		// If we are in a WordPress Multisite admin dashboard, put the settings pages under a different parent.
		if ( is_network_admin() ) {
			$parent_slug = 'settings.php';
		}

		return $parent_slug;
	}

	protected function carbonfields_load() {
		Carbon_Fields::boot();
	}

	protected function hook_to_screen() {
		$page_hook = get_plugin_page_hookname( self::MENU_SLUG, $this->get_page_parent() );
		$this->add_action( 'load-' . $page_hook, 'load_screen' );
	}

	/**
	 * Load up the screen.
	 *
	 * @since 2.0.0
	 */
	protected function load_screen() {
		$this->add_action( 'admin_enqueue_scripts', 'enqueue_assets' );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 2.0.0
	 */
	protected function enqueue_assets() {
		wp_enqueue_script( 'pixelgrade_style_manager-settings' );
		wp_enqueue_style( 'pixelgrade_style_manager-settings' );
	}

	protected function disable_default_access( bool $enable, string $container_title, Container $container ): bool {
		// We will define the access ourselves and we don't want the default Carbon Fields behavior.
		if ( 'style_manager_options' === $container->get_id() ) {
			return false;
		}

		return $enable;
	}

	/**
	 * Add a "Settings" action link to the plugins list page.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	protected function add_action_links( array $links ): array {
		return array_merge( [ 'settings' => '<a href="' . esc_url( menu_page_url( self::MENU_SLUG, false ) ) . '">' . esc_html__( 'Settings', '__plugin_txtd' ) . '</a>' ], $links );
	}

	/**
	 * Register any REST-API routes we need.
	 */
	protected function add_rest_api_routes() {
		register_rest_route( 'style_manager/v1', '/delete_customizer_settings', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_customizer_settings' ],
			'permission_callback' => [ $this, 'permission_nonce_callback' ],
		] );
	}

	public function delete_customizer_settings() {
		if ( ! current_user_can( Capabilities::MANAGE_OPTIONS ) ) {
			wp_send_json_error( esc_html__( 'You don\'t have the necessary privileges to do this.', '__plugin_txtd' ) );
		}

		$key = $this->options->get_options_key();

		if ( empty( $key ) ) {
			wp_send_json_error( esc_html__( 'We couldn\'t  find an options key. Nothing was reset.', '__plugin_txtd' ) );
		}

		delete_option( $key );
		remove_theme_mod( $key );

		$this->options->invalidate_all_caches();

		/* translators: %s: The deleted options key name. */
		wp_send_json_success( sprintf( esc_html__( 'Deleted the "%s" options key!', '__plugin_txtd' ), $key ) );
	}

	public function permission_nonce_callback() {
		return current_user_can( Capabilities::MANAGE_OPTIONS )
		       && wp_verify_nonce( $this->get_nonce(), 'style_manager_settings_nonce' );
	}

	private function get_nonce() {
		$nonce = null;

		// phpcs:disable WordPress.Security.NonceVerification -- this IS the nonce read; it is verified in permission_nonce_callback().
		if ( isset( $_REQUEST['style_manager_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_REQUEST['style_manager_settings_nonce'] ) );
		} elseif ( isset( $_POST['style_manager_settings_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['style_manager_settings_nonce'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification

		return $nonce;
	}
}

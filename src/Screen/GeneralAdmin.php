<?php
/**
 * General admin dashboard screen provider.
 *
 * @since   2.0.0
 * @license GPL-2.0-or-later
 * @package Style Manager
 */

declare ( strict_types=1 );

namespace Pixelgrade\StyleManager\Screen;

use Pixelgrade\StyleManager\Customize\Fonts;
use Pixelgrade\StyleManager\Customize\LocalFontStore;
use Pixelgrade\StyleManager\Provider\LocalFonts;
use Pixelgrade\StyleManager\Provider\PluginSettings;
use Pixelgrade\StyleManager\Vendor\Cedaro\WP\Plugin\AbstractHookProvider;
use Pixelgrade\StyleManager\Vendor\Psr\Log\LoggerInterface;

/**
 * General admin dashboard screen provider class.
 *
 * @since 2.0.0
 */
class GeneralAdmin extends AbstractHookProvider {

	/**
	 * The option holding whether the "host cloud fonts locally" admin notice was dismissed.
	 *
	 * @since 2.4.0
	 */
	const LOCAL_FONTS_NOTICE_DISMISSED_OPTION = 'style_manager_local_fonts_notice_dismissed';

	/**
	 * Local font store.
	 *
	 * @var LocalFontStore
	 */
	protected LocalFontStore $local_font_store;

	/**
	 * Style Manager Fonts.
	 *
	 * @var Fonts
	 */
	protected Fonts $sm_fonts;

	/**
	 * Local fonts hook provider (handles the actual mirroring).
	 *
	 * @var LocalFonts
	 */
	protected LocalFonts $local_fonts_provider;

	/**
	 * Plugin settings.
	 *
	 * @var PluginSettings
	 */
	protected PluginSettings $plugin_settings;

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
	 * @param LocalFontStore  $local_font_store     Local font store.
	 * @param Fonts           $sm_fonts             Style Manager Fonts.
	 * @param LocalFonts      $local_fonts_provider Local fonts hook provider.
	 * @param PluginSettings  $plugin_settings      Plugin settings.
	 * @param LoggerInterface $logger               Logger.
	 */
	public function __construct(
		LocalFontStore $local_font_store,
		Fonts $sm_fonts,
		LocalFonts $local_fonts_provider,
		PluginSettings $plugin_settings,
		LoggerInterface $logger
	) {
		$this->local_font_store     = $local_font_store;
		$this->sm_fonts             = $sm_fonts;
		$this->local_fonts_provider = $local_fonts_provider;
		$this->plugin_settings      = $plugin_settings;
		$this->logger               = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0.0
	 */
	public function register_hooks() {
		$this->add_action( 'after_switch_theme', 'maybe_show_notice_to_migrate_when_child_theme', 100, 2 );
		$this->add_action( 'wp_ajax_style_manager_migrate_customizations_from_parent_to_child_theme', 'migrate_customizations_from_parent_to_child_theme' );
		$this->add_action( 'admin_init', 'migrate_to_advanced_dark_mode_control' );
		$this->add_action( 'admin_enqueue_scripts', 'enqueue_assets' );

		// Prevent the old Customify from being activated via the Plugins dashboard page.
		$this->add_action( 'load-plugins.php', 'add_plugin_action_link_filters', 1 );

		// Host cloud fonts locally notice + one-click migrate.
		$this->add_action( 'admin_notices', 'local_fonts_migration_notice' );
		$this->add_action( 'wp_ajax_style_manager_host_fonts_locally', 'host_fonts_locally' );
		$this->add_action( 'wp_ajax_style_manager_dismiss_local_fonts_notice', 'dismiss_local_fonts_notice' );
	}

	/**
	 * Hook up to show notice for customization options migration.
	 *
	 * @since 2.0.0
	 *
	 * @param string    $old_theme_name
	 * @param \WP_Theme $old_theme
	 */
	protected function maybe_show_notice_to_migrate_when_child_theme( string $old_theme_name, \WP_Theme $old_theme ) {
		$current_theme = wp_get_theme();
		// If the current theme is a child theme, show a notice.
		if ( $current_theme->exists()
		     && $old_theme->exists()
		     && $current_theme->get_template() === $old_theme->get_stylesheet() ) {

			$this->add_action( 'admin_notices', 'child_theme_migrate_theme_mods_notice' );
		}
	}

	/**
	 * Output a notice allowing for theme mods migration from the parent theme to the current child theme.
	 *
	 * @since 2.0.0
	 *
	 * @global string $pagenow
	 */
	function child_theme_migrate_theme_mods_notice() {
		global $pagenow;

		// We only show the notice on the Themes dashboard page, and if we are allowed to.
		if ( 'themes.php' !== $pagenow
		     || ! is_child_theme()
		     || true !== apply_filters( 'style_manager/allow_child_theme_mod_migrate_notice', true )
		     || ! current_user_can( 'manage_options' ) ) {

			return;
		}

		$parent_theme = wp_get_theme( get_template() );
		if ( ! $parent_theme->exists() ) {
			return;
		}

		ob_start(); ?>
		<div class="style-manager-notice__container updated notice fade is-dismissible">
			<h3><?php
				/* translators: %s: The parent theme name. */
				echo esc_html( sprintf( __( 'You have activated a child theme for "%s". Good for you!', '__plugin_txtd' ), $parent_theme->get('Name') ) );
			?></h3>
			<p>
				<?php echo wp_kses_post( __( 'If you have already <strong>set up things in the Customizer,</strong> you may want to <strong>keep those customizations</strong> so you don\'t start over.', '__plugin_txtd' ) ); ?>
			</p>
			<p>
				<?php echo wp_kses_post( __( 'So, the question is simple: <strong>would you like to migrate all theme-specific options (theme mods) from the parent theme to the child one?</strong>', '__plugin_txtd' ) ); ?>
			</p>
			<p>
				<?php echo wp_kses_post( __( 'All parent theme customizations will remain in place, while those of the active child theme will be overwritten, if any.', '__plugin_txtd' ) ); ?>
			</p>
			<form class="style-manager-notice-form" method="post">
				<noscript><input type="hidden" name="style-manager-notice-no-js" value="1"/></noscript>

				<p>
					<button class="style-manager-notice-button button button-primary js-handle-style-manager">
						<span class="style-manager-notice-button__text"><?php esc_html_e( 'Yes, migrate customizations', '__plugin_txtd' ); ?></span>
					</button>
					<button type="submit" class="style-manager-dismiss-button button button-secondary js-dismiss-style-manager"><?php esc_html_e( 'No, thank you', '__plugin_txtd' ); ?></button>
					&nbsp;<span class="message js-plugin-message" style="font-style:italic"></span>
				</p>

				<?php wp_nonce_field( 'style_manager_migrate_customizations_from_parent_to_child_theme', 'nonce-style_manager_theme_mods_migrate' ); ?>
			</form>
		</div>
		<script>
			(function ($) {
				$(function () {
					let $noticeContainer = $('.style-manager-notice__container'),
						$button = $noticeContainer.find('.js-handle-style-manager'),
						$buttonText = $noticeContainer.find('.style-manager-notice-button__text'),
						$dismissButton = $noticeContainer.find('.js-dismiss-style-manager'),
						$statusMessage = $noticeContainer.find('.js-plugin-message')

					$button.on('click', function (e) {
						e.preventDefault();

						$buttonText.html("<?php esc_html_e( 'Migrating customizations..' ,'__plugin_txtd'); ?>")
						$button.attr('disabled', true)
						$dismissButton.hide()

						// Do an AJAX call to migrate the theme_mods.
						$.ajax({
							url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
							type: 'post',
							data: {
								action: 'style_manager_migrate_customizations_from_parent_to_child_theme',
								nonce_migrate: $noticeContainer.find('#nonce-style_manager_theme_mods_migrate').val()
							}
						})
							.done(function(response) {
								if (typeof response.success !== 'undefined' && response.success) {
									$statusMessage.html("<?php esc_html_e( 'Successfully migrated the parent customizations! Enjoy crafting your site!', '__plugin_txtd' ); ?>")
									$buttonText.html("<?php esc_html_e( 'Finished migration', '__plugin_txtd' ); ?>")
								} else {
									$statusMessage.html("<?php esc_html_e( 'Something went wrong and we couldn\'t migrate the customizations.' ,'__plugin_txtd'); ?>")
									$buttonText.html("<?php esc_html_e( 'Migration error' ,'__plugin_txtd'); ?>")
								}
							})
							.fail(function() {
								$statusMessage.html("<?php esc_html_e( 'Something went wrong and we couldn\'t migrate the customizations.' ,'__plugin_txtd'); ?>")
								$buttonText.html("<?php esc_html_e( 'Migration error' ,'__plugin_txtd'); ?>")
							})
					})

					// Dismiss the notice.
					$dismissButton.on('click', function (e) {
						e.preventDefault();

						$noticeContainer.slideUp();
					})
				})
			})(jQuery)
		</script>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffered admin notice markup; embedded strings use esc_html_e / wp_kses_post / wp_nonce_field above.
		echo ob_get_clean();
	}

	/**
	 * Output a dismissible notice offering to mirror in-use Pixelgrade Cloud fonts
	 * to this site's own media folder (one-click, via AJAX).
	 *
	 * @since 2.4.0
	 */
	function local_fonts_migration_notice() {
		if ( ! $this->should_show_local_fonts_notice() ) {
			return;
		}

		$unhealthy_count = count( $this->get_unhealthy_used_font_families() );

		ob_start(); ?>
		<div class="style-manager-notice__container style-manager-local-fonts-notice notice notice-info is-dismissible">
			<h3><?php esc_html_e( 'Host your fonts on your own site', '__plugin_txtd' ); ?></h3>
			<div class="js-style-manager-local-fonts-body">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of fonts currently loaded from Pixelgrade Cloud. */
							_n(
								'Your site currently loads %d font from Pixelgrade Cloud for your visitors. Host them on this site — your visitors never connect to our servers, and your typography keeps working no matter what. Fonts are downloaded once to your media folder and stay up to date automatically.',
								'Your site currently loads %d fonts from Pixelgrade Cloud for your visitors. Host them on this site — your visitors never connect to our servers, and your typography keeps working no matter what. Fonts are downloaded once to your media folder and stay up to date automatically.',
								$unhealthy_count,
								'__plugin_txtd'
							),
							$unhealthy_count
						)
					);
					?>
				</p>
				<p>
					<button type="button" class="style-manager-local-fonts-button button button-primary js-style-manager-host-fonts-locally">
						<span class="style-manager-local-fonts-button__text"><?php esc_html_e( 'Host fonts locally', '__plugin_txtd' ); ?></span>
					</button>
					<a href="#" class="js-style-manager-dismiss-local-fonts-notice"><?php esc_html_e( 'Dismiss', '__plugin_txtd' ); ?></a>
					<?php echo $this->get_manage_in_hub_link_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- already escaped in get_manage_in_hub_link_html(). ?>
					&nbsp;<span class="message js-style-manager-local-fonts-message" style="font-style:italic"></span>
				</p>
			</div>
			<?php wp_nonce_field( 'style_manager_host_fonts_locally', 'nonce-style_manager_host_fonts_locally' ); ?>
			<?php wp_nonce_field( 'style_manager_dismiss_local_fonts_notice', 'nonce-style_manager_dismiss_local_fonts_notice' ); ?>
		</div>
		<script>
			(function ($) {
				$(function () {
					let $noticeContainer = $('.style-manager-local-fonts-notice'),
						$body = $noticeContainer.find('.js-style-manager-local-fonts-body'),
						$button = $noticeContainer.find('.js-style-manager-host-fonts-locally'),
						$buttonText = $noticeContainer.find('.style-manager-local-fonts-button__text'),
						$dismissLink = $noticeContainer.find('.js-style-manager-dismiss-local-fonts-notice'),
						$statusMessage = $noticeContainer.find('.js-style-manager-local-fonts-message');

					function dismissNotice() {
						$.ajax({
							url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
							type: 'post',
							data: {
								action: 'style_manager_dismiss_local_fonts_notice',
								nonce_dismiss: $noticeContainer.find('#nonce-style_manager_dismiss_local_fonts_notice').val()
							}
						});
					}

					$button.on('click', function (e) {
						e.preventDefault();

						$buttonText.html("<?php esc_html_e( 'Hosting fonts…', '__plugin_txtd' ); ?>");
						$button.attr('disabled', true);
						$dismissLink.hide();

						$.ajax({
							url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
							type: 'post',
							data: {
								action: 'style_manager_host_fonts_locally',
								nonce_host: $noticeContainer.find('#nonce-style_manager_host_fonts_locally').val()
							}
						})
							.done(function (response) {
								if (response && response.success && response.data && response.data.message) {
									$body.html('<p>' + response.data.message + '</p>');
								} else {
									$statusMessage.html("<?php esc_html_e( 'Something went wrong and we couldn\'t host the fonts locally.', '__plugin_txtd' ); ?>");
									$buttonText.html("<?php esc_html_e( 'Host fonts locally', '__plugin_txtd' ); ?>");
									$button.attr('disabled', false);
									$dismissLink.show();
								}
							})
							.fail(function () {
								$statusMessage.html("<?php esc_html_e( 'Something went wrong and we couldn\'t host the fonts locally.', '__plugin_txtd' ); ?>");
								$buttonText.html("<?php esc_html_e( 'Host fonts locally', '__plugin_txtd' ); ?>");
								$button.attr('disabled', false);
								$dismissLink.show();
							})
					})

					// The "Dismiss" link.
					$dismissLink.on('click', function (e) {
						e.preventDefault();

						dismissNotice();
						$noticeContainer.slideUp();
					})

					// The WP-native dismiss "x" button (added because of the `is-dismissible` class).
					$(document).on('click', '.style-manager-local-fonts-notice .notice-dismiss', function () {
						dismissNotice();
					})
				})
			})(jQuery)
		</script>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffered admin notice markup; embedded strings use esc_html_e / wp_kses_post / wp_nonce_field above.
		echo ob_get_clean();
	}

	/**
	 * Whether the "host cloud fonts locally" notice should be shown: the current
	 * user can manage options, both the `typography_cloud_fonts` and
	 * `typography_host_cloud_fonts_locally` settings are truthy, the notice
	 * hasn't been dismissed, and at least one currently-used cloud font family
	 * isn't healthy in the local font store yet.
	 *
	 * @since 2.4.0
	 *
	 * @return bool
	 */
	protected function should_show_local_fonts_notice(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		// Duplicate the same two reads as Provider\LocalFonts::is_enabled() -- kept
		// protected there on purpose, so we read the settings ourselves here.
		if ( ! $this->plugin_settings->get( 'typography_cloud_fonts', 'yes' )
			|| ! $this->plugin_settings->get( 'typography_host_cloud_fonts_locally', 'yes' ) ) {

			return false;
		}

		if ( get_option( self::LOCAL_FONTS_NOTICE_DISMISSED_OPTION ) ) {
			return false;
		}

		return ! empty( $this->get_unhealthy_used_font_families() );
	}

	/**
	 * Currently-used cloud font families that aren't healthy in the local font store.
	 *
	 * @since 2.4.0
	 *
	 * @return string[]
	 */
	protected function get_unhealthy_used_font_families(): array {
		$unhealthy = [];
		foreach ( $this->sm_fonts->get_used_cloud_font_families() as $family ) {
			if ( ! is_string( $family ) || '' === $family ) {
				continue;
			}
			if ( ! $this->local_font_store->is_healthy( $family ) ) {
				$unhealthy[] = $family;
			}
		}

		return $unhealthy;
	}

	/**
	 * Build the "Manage in Pixelgrade Design" link markup shown next to the
	 * Dismiss link, or an empty string when the hub isn't available.
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
			'&nbsp;<a href="%1$s" class="js-sm-manage-in-hub">%2$s</a>',
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
	 * Process the ajax call to mirror all currently-used cloud fonts to local storage.
	 *
	 * @since 2.4.0
	 */
	function host_fonts_locally() {
		check_ajax_referer( 'style_manager_host_fonts_locally', 'nonce_host' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$previously_unhealthy = $this->get_unhealthy_used_font_families();

		$this->local_fonts_provider->mirror_used_fonts();

		$mirrored = 0;
		$failed   = 0;
		foreach ( $previously_unhealthy as $family ) {
			if ( $this->local_font_store->is_healthy( $family ) ) {
				$mirrored++;
			} else {
				$failed++;
			}
		}

		if ( $failed > 0 ) {
			$message = sprintf(
				/* translators: %d: number of fonts that could not be downloaded. */
				_n(
					'%d font could not be downloaded right now — a retry is scheduled.',
					'%d fonts could not be downloaded right now — retries are scheduled.',
					$failed,
					'__plugin_txtd'
				),
				$failed
			);
		} else {
			$message = sprintf(
				/* translators: %d: number of fonts now served from this site. */
				_n(
					'%d font is now served from your site.',
					'%d fonts are now served from your site.',
					$mirrored,
					'__plugin_txtd'
				),
				$mirrored
			);
		}

		wp_send_json_success( [
			'mirrored' => $mirrored,
			'failed'   => $failed,
			'message'  => $message,
		] );
	}

	/**
	 * Process the ajax call to dismiss the "host cloud fonts locally" notice.
	 *
	 * @since 2.4.0
	 */
	function dismiss_local_fonts_notice() {
		check_ajax_referer( 'style_manager_dismiss_local_fonts_notice', 'nonce_dismiss' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		update_option( self::LOCAL_FONTS_NOTICE_DISMISSED_OPTION, 1, false );

		wp_send_json_success();
	}

	/**
	 * Process ajax call to migrate customizations from parent to current child theme.
	 *
	 * @since 2.0.0
	 */
	function migrate_customizations_from_parent_to_child_theme() {
		// Check nonce.
		check_ajax_referer( 'style_manager_migrate_customizations_from_parent_to_child_theme', 'nonce_migrate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}

		$parent_theme = wp_get_theme( get_template() );
		if ( ! $parent_theme->exists() ) {
			wp_send_json_error();
		}

		// Migrate theme mods
		$parent_theme_mods = get_option( 'theme_mods_' . $parent_theme->get_stylesheet() );
		// We need to exclude certain theme_mods since they are not needed by the child theme.
		$excluded = [
			'pixcare_license',
			'pixcare_new_theme_version',
			'pixcare_install_notice_dismissed',
		];
		foreach ( $excluded as $exclude ) {
			unset( $parent_theme_mods[ $exclude ] );
		}

		$current_theme_mods = get_theme_mods();
		// Merge the parent ones, overwriting the existing entries.
		$new_theme_mods = array_merge( $current_theme_mods, $parent_theme_mods );

		// Finally, write the new theme mods for the active child theme.
		if ( ! update_option( 'theme_mods_' . get_option( 'stylesheet' ), $new_theme_mods ) ) {
			wp_send_json_error( esc_html__( 'Could not update the child theme theme_mods.', '__plugin_txtd' ) );
		}

		// Redirect if this is not an ajax request.
		if ( isset( $_POST['pixcare-notice-no-js'] ) ) {

			// Go back to where we came from.
			wp_safe_redirect( wp_get_referer() );
			exit();
		}

		wp_send_json_success();
	}

	/**
	 * Migrate data from the simple Dark Mode control to Advanced Dark Mode Control, if the current theme supports it.
	 *
	 * @since 2.0.0
	 */
	function migrate_to_advanced_dark_mode_control() {
		// Bail if the current theme doesn't support the advanced control.
		if ( ! current_theme_supports( 'style_manager_advanced_dark_mode' ) ) {
			return;
		}

		$advanced_dark_mode = get_option( 'sm_dark_mode_advanced', null );
		// Bail if we already have advanced control data saved.
		if ( ! is_null( $advanced_dark_mode ) ) {
			return;
		}

		// Bail if there isn't a simple dark mode option saved.
		$simple_dark_mode = get_option( 'sm_dark_mode', null );
		if ( is_null( $simple_dark_mode ) ) {
			return;
		}

		// If the simple control value was on, we have work to do.
		if ( 'on' === $simple_dark_mode ) {
			$old_sm_dark_primary_final    = get_option( 'sm_dark_primary_final' );
			$old_sm_dark_secondary_final  = get_option( 'sm_dark_secondary_final' );
			$old_sm_dark_tertiary_final   = get_option( 'sm_dark_tertiary_final' );
			$old_sm_light_primary_final   = get_option( 'sm_light_primary_final' );
			$old_sm_light_secondary_final = get_option( 'sm_light_secondary_final' );
			$old_sm_light_tertiary_final  = get_option( 'sm_light_tertiary_final' );

			update_option( 'sm_dark_mode_advanced', 'on' );
			update_option( 'sm_dark_mode', 'off' );
			update_option( 'sm_dark_primary_final', $old_sm_light_primary_final );
			update_option( 'sm_dark_secondary_final', $old_sm_light_secondary_final );
			update_option( 'sm_dark_tertiary_final', $old_sm_light_tertiary_final );
			update_option( 'sm_light_primary_final', $old_sm_dark_primary_final );
			update_option( 'sm_light_secondary_final', $old_sm_dark_secondary_final );
			update_option( 'sm_light_tertiary_final', $old_sm_dark_tertiary_final );
		} else {
			update_option( 'sm_dark_mode_advanced', 'off' );
		}
	}

	/**
	 * Enqueue assets.
	 *
	 * @since 2.0.0
	 */
	protected function enqueue_assets() {

	}

	/**
	 * Hook in plugin action link filters for the WP native plugins page.
	 *
	 * - Prevent activation of plugins which don't meet the minimum version requirements.
	 * - Prevent deactivation of force-activated plugins.
	 *
	 * @since 2.0.0
	 */
	protected function add_plugin_action_link_filters() {
		$prevent_activate = [];
		foreach ( get_plugins() as $plugin_filename => $plugin_data ) {
			// We will search all plugins by the Customify file name and deactivate any one of them that are active.
			// This way we account for modified directories, etc.
			if ( strrpos( $plugin_filename, 'customify.php' ) === ( strlen( $plugin_filename ) - strlen( 'customify.php' ) ) ) {
				$prevent_activate[] = $plugin_filename;
			}
		}

		if ( ! empty( $prevent_activate ) ) {
			foreach ( $prevent_activate as $filename ) {
				$this->add_filter( 'plugin_action_links_' . $filename, 'filter_plugin_action_links_activate', 20 );
			}
		}
	}

	/**
	 * Remove the 'Activate' link on the WP native plugins page if a plugin should not be activated as long as Style Manager is active.
	 *
	 * @since 2.0.0
	 *
	 * @param array $actions Action links.
	 *
	 * @return array
	 */
	protected function filter_plugin_action_links_activate( array $actions ): array {
		unset( $actions['activate'] );

		return $actions;
	}

	/**
	 * Remove the 'Deactivate' link on the WP native plugins page if the plugin should not be deactivated as long as Style Manager is active.
	 *
	 * @since 2.0.0
	 *
	 * @param array $actions Action links.
	 *
	 * @return array
	 */
	public function filter_plugin_action_links_deactivate( array $actions ): array {
		unset( $actions['deactivate'] );

		return $actions;
	}
}

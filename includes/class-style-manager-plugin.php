<?php

/**
 * The core plugin class.
 *
 * This loads all the components that make up the plugin.
 */
class Style_Manager_Plugin {

	/**
	 * Holds the only instance of this class.
	 * @var null|Style_Manager_Plugin
	 * @access protected
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * The plugin version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The plugin's base path.
	 * @var null|string
	 * @access public
	 * @since 1.0.0
	 */
	public $plugin_basepath = null;

	/**
	 * The plugin's base URL.
	 * @var null|string
	 * @access public
	 * @since 1.0.0
	 */
	public $plugin_baseuri = null;

	/**
	 * The site's base URL.
	 * @var null|string
	 * @access protected
	 * @since 1.0.0
	 */
	protected $base_url = null;

	/**
	 * Metaboxes class object.
	 * @var Style_Manager_Metaboxes
	 * @access  public
	 * @since   1.0.0
	 */
	public $metaboxes = null;

	/**
	 * Plugin settings class object.
	 * @var Style_Manager_Settings
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * Plugin's REST API class object.
	 * @var Style_Manager_Rest_Api_V1
	 * @access  protected
	 * @since   1.0.0
	 */
	public $rest = null;

	/**
	 * Style Manager class object.
	 * @var Style_Manager
	 * @access  public
	 * @since   1.0.0
	 */
	public $style_manager = null;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * Minimal Required PHP Version.
	 * @var string
	 * @access  private
	 * @since   1.0.0
	 */
	private $minimalRequiredPhpVersion  = 5.2;

	protected function  __construct( $file, $version = '1.0.0' ) {
		// The main plugin file (the one that loads all this).
		$this->file = $file;
		// The current plugin version.
		$this->_version = $version;

		// Setup the helper variables for easily retrieving PATHS and URLS everywhere (these are already trailingslashit).
		$this->plugin_basepath = plugin_dir_path( $file );
		$this->plugin_baseuri  = plugin_dir_url( $file );
		$this->base_url  = home_url();

		// The plugin's token to be used for prefixing.
		$this->_token = 'sm';

		if ( $this->php_version_check() ) {
			// Only load and run the init function if we know PHP version can parse it.
			$this->init();
		}
	}

	/**
	 * Initialize the plugin.
	 */
	private function init() {

		/* Initialize the metaboxes logic (CMB2). */
		require_once( $this->plugin_basepath . 'includes/class-style-manager-metaboxes.php' );
		if ( is_null( $this->metaboxes ) ) {
			$this->metaboxes = Style_Manager_Metaboxes::instance( $this->_token );
		}

		/* Initialize the settings page. */
		require_once( $this->plugin_basepath . 'includes/class-style-manager-settings.php' );
		if ( is_null( $this->settings ) ) {
			$this->settings = Style_Manager_Settings::instance( $this );
		}

		/* Initialize the REST API endpoints. */
		require_once( $this->plugin_basepath . 'includes/class-style-manager-rest-api-v1.php' );
		if ( is_null( $this->rest ) ) {
			$this->rest = Style_Manager_Rest_Api_V1::instance( $this );
		}

		/* Initialize the Style Manager logic. */
		require_once( $this->plugin_basepath . 'includes/class-style-manager.php' );
		if ( is_null( $this->style_manager ) ) {
			$this->style_manager = Style_Manager::instance( $this );
		}

		// Register all the needed hooks
		$this->register_hooks();
	}

	/**
	 * Register our actions and filters
	 */
	function register_hooks() {
		/* Handle the install and uninstall logic. */
		register_activation_hook( $this->file, array( 'Style_Manager_Plugin', 'install' ) );
		register_deactivation_hook( $this->file, array( 'Style_Manager_Plugin', 'uninstall' ) );

		/* Handle localisation. */
		add_action( 'init', array( $this, 'load_localisation' ), 0 );

		/*
		 * Prepare and load the configuration
		 */
		$this->init_plugin_configs();

		// We need to load the configuration as late as possible so we allow all that want to influence it
		// We need the init hook and not after_setup_theme because there a number of plugins that fire up on init (like certain modules from Jetpack)
		// We need to be able to load things like components configs depending on those firing up or not
		// DO NOT TRY to use the Customify values before this!
		add_action( 'init', array( $this, 'load_plugin_configs' ), 15 );

		/*
		 * Now setup the admin side of things
		 */
		// Starting with the menu item for this plugin
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( $this->file ) . 'pixcustomify.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		/*
		 * Now it's time for the Customizer logic to kick in
		 */
		// Styles for the Customizer
		add_action( 'customize_controls_init', array( $this, 'register_admin_customizer_styles' ), 10 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_admin_customizer_styles' ), 10 );
		// Scripts enqueued in the Customizer
		add_action( 'customize_controls_init', array( $this, 'register_admin_customizer_scripts' ), 15 );
		add_action( 'customize_controls_enqueue_scripts', array( $this, 'enqueue_admin_customizer_scripts' ), 15 );

		// Scripts enqueued only in the theme preview
		add_action( 'customize_preview_init', array( $this, 'customizer_live_preview_register_scripts' ), 10 );
		add_action( 'customize_preview_init', array( $this, 'customizer_live_preview_enqueue_scripts' ), 99999 );

		// Add extra settings data to _wpCustomizeSettings.settings of the parent window.
		add_action( 'customize_controls_print_footer_scripts', array( $this, 'customize_pane_settings_additional_data' ), 10000 );

		// The frontend effects of the Customizer controls
		$load_location = $this->get_plugin_setting( 'style_resources_location', 'wp_head' );

		add_action( $load_location, array( $this, 'output_dynamic_style' ), 99 );
		add_action( 'wp_head', array( $this, 'output_typography_dynamic_style' ), 10 );

		add_action( 'customize_register', array( $this, 'remove_default_sections' ), 11 );
		add_action( 'customize_register', array( $this, 'register_customizer' ), 12 );

		if ( $this->get_plugin_setting( 'enable_editor_style', true ) ) {
			add_action( 'admin_head', array( $this, 'add_customizer_settings_into_wp_editor' ) );
		}

		add_action( 'rest_api_init', array( $this, 'add_rest_routes_api' ) );

		/*
		 * Development related
		 */
		if ( defined( 'CUSTOMIFY_DEV_FORCE_DEFAULTS' ) && true === CUSTOMIFY_DEV_FORCE_DEFAULTS ) {
			// If the development constant CUSTOMIFY_DEV_FORCE_DEFAULTS has been defined we will not save anything in the database
			// Always go with the default
			add_filter( 'customize_changeset_save_data', array( $this, 'prevent_changeset_save_in_devmode' ), 50, 2 );
			// Add a JS to display a notification
			add_action( 'customize_controls_print_footer_scripts', array( $this, 'prevent_changeset_save_in_devmode_notification' ), 100 );
		}
	}

	/**
	 * Initialize Configs, Options and Values methods.
	 */
	function init_plugin_configs() {
		$this->customizer_config = get_option( 'pixcustomify_config' );

		// no option so go for default.
		if ( empty( $this->customizer_config ) ) {
			$this->customizer_config = $this->get_config_option( 'default_options' );
		}

		if ( empty( $this->customizer_config ) ) {
			$this->customizer_config = array();
		}
	}

	/**
	 * Load the plugin configuration and options.
	 */
	function load_plugin_configs() {

		// Allow themes or other plugins to filter the config.
		$this->customizer_config = apply_filters( 'customify_filter_fields', $this->customizer_config );
		// We apply a second filter for those that wish to work with the final config and not rely on a a huge priority number.
		$this->customizer_config = apply_filters( 'customify_final_config', $this->customizer_config );

		$this->opt_name          = $this->localized['options_name'] = $this->customizer_config['opt-name'];
		$this->options_list      = $this->get_options();

		// Load the current options values.
		$this->current_values = $this->get_current_values();

		$this->localized['theme_fonts'] = $this->theme_fonts = Customify_Font_Selector::instance()->get_theme_fonts();

		$this->localized['ajax_url'] = admin_url( 'admin-ajax.php' );
		$this->localized['style_manager_user_feedback_nonce'] = wp_create_nonce( 'customify_style_manager_user_feedback' );
		$this->localized['style_manager_user_feedback_provided'] = get_option( 'style_manager_user_feedback_provided', false );
	}

	public function get_version() {
		return $this->_version;
	}

	public function get_slug() {
		return $this->plugin_slug;
	}

	/*
	 * Install everything needed
	 */
	static public function install() {
		flush_rewrite_rules();

		// Setup cron jobs.
		self::setup_cron_jobs();
	}

	/*
	 * Uninstall everything needed
	 */
	static public function uninstall() {
		 flush_rewrite_rules();

		 // Remove cron jobs.
		self::remove_cron_jobs();
	}

	static function setup_cron_jobs() {
		// Add cron jobs here.
	}

	static function remove_cron_jobs() {
		// Remove cron jobs here.
	}

	function debug( $what ) {
		echo '<pre style="margin-left: 160px">';
		var_dump( $what );
		echo '</pre>';
	}

	public function get_plugin_baseuri() {
		return $this->plugin_baseuri;
	}

	/**
	 * PHP version check
	 */
	protected function php_version_check() {
		if ( version_compare( phpversion(), $this->minimalRequiredPhpVersion ) < 0 ) {
			add_action( 'admin_notices', array( $this, 'notice_php_version_wrong' ) );
			return false;
		}

		return true;
	}

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation() {
		$this->l10ni18n();
	}

	/**
	 * Registers Style Manager text domain path
	 * @since  1.0.0
	 */
	public function l10ni18n() {
		$loaded = load_plugin_textdomain( 'Style_Manager_Plugin', false, '/languages/' );

		if ( ! $loaded ) {
			$loaded = load_muplugin_textdomain( 'Style_Manager_Plugin', '/languages/' );
		}

		if ( ! $loaded ) {
			$loaded = load_theme_textdomain( 'Style_Manager_Plugin', get_stylesheet_directory() . '/languages/' );
		}

		if ( ! $loaded ) {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'Style_Manager_Plugin' );
			$mofile = dirname( __DIR__ ) . '/languages/sm-' . $locale . '.mo';
			load_textdomain( 'Style_Manager_Plugin', $mofile );
		}
	}

	/** === RESOURCES === **/

	/**
	 * Register Customizer admin styles
	 */
	function register_admin_customizer_styles() {
		wp_register_style( 'customify_select2', plugins_url( 'js/select2/css/select2.css', $this->file ), array(), $this->_version );
		wp_register_style( 'customify_style', plugins_url( 'css/customizer.css', $this->file ), array( 'customify_select2' ), $this->_version );
	}

	/**
	 * Enqueue Customizer admin styles
	 */
	function enqueue_admin_customizer_styles() {
		wp_enqueue_style( 'customify_style' );
	}

	/**
	 * Register Customizer admin scripts
	 */
	function register_admin_customizer_scripts() {

		wp_register_script( 'customify_select2', plugins_url( 'js/select2/js/select2.js', $this->file ), array( 'jquery' ), $this->_version );
		wp_register_script( 'jquery-react', plugins_url( 'js/jquery-react.js', $this->file ), array( 'jquery' ), $this->_version );

		wp_register_script( 'customify-scale', plugins_url( 'js/customizer/scale-iframe.js', $this->file ), array( 'jquery' ), $this->_version );
		wp_register_script( 'customify-fontselectfields', plugins_url( 'js/customizer/font-select-fields.js', $this->file ), array( 'jquery' ), $this->_version );

		wp_register_script( sm_prefix( 'customizer-scripts' ), plugins_url( 'js/customizer.js', $this->file ), array(
			'jquery',
			'customify_select2',
			'underscore',
			'customize-controls',
			'customify-fontselectfields',

			'customify-scale',
		), $this->_version );
	}

	/**
	 * Enqueue Customizer admin scripts
	 */
	function enqueue_admin_customizer_scripts() {
		wp_enqueue_script( 'jquery-react' );
		wp_enqueue_script( sm_prefix( 'customizer-scripts' ) );

		wp_localize_script( sm_prefix( 'customizer-scripts' ), 'sm_settings', apply_filters( 'style_manager_localized_js_settings', $this->localized ) );
	}

	/** Register Customizer scripts loaded only on previewer page */
	function customizer_live_preview_register_scripts() {
		wp_register_script( sm_prefix( 'CSSOM' ), plugins_url( 'js/CSSOM.js', $this->file ), array( 'jquery' ), $this->_version, true );
		wp_register_script( sm_prefix( 'cssUpdate' ), plugins_url( 'js/jquery.cssUpdate.js', $this->file ), array(), $this->_version, true );
		wp_register_script( sm_prefix( 'previewer-scripts' ), plugins_url( 'js/customizer_preview.js', $this->file ), array(
			'jquery',
			'customize-preview',
			sm_prefix( 'CSSOM' ),
			sm_prefix( 'cssUpdate' ),
		), $this->_version, true );
	}

	/** Enqueue Customizer scripts loaded only on previewer page */
	function customizer_live_preview_enqueue_scripts() {
		wp_enqueue_script( sm_prefix( 'previewer-scripts' ) );

		// when a live preview field is in action we need to know which props need 'px' as defaults
		$this->localized['px_dependent_css_props'] = self::$pixel_dependent_css_properties;

		wp_localize_script( sm_prefix( 'previewer-scripts' ), 'sm_settings', $this->localized );
	}

	/**
	 * Add dynamic style only on the previewer page
	 */

	/**
	 * Settings page styles
	 */
	function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen->id == $this->plugin_screen_hook_suffix ) {
			wp_enqueue_style( sm_prefix( 'admin-styles' ), plugins_url( 'css/admin.css', $this->file ), array(), $this->_version );
		}
	}

	/**
	 * Settings page scripts
	 */
	function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen->id == $this->plugin_screen_hook_suffix ) {
			wp_enqueue_script( sm_prefix( 'admin-script' ), plugins_url( 'js/admin.js', $this->file ), array( 'jquery' ), $this->_version );
			wp_localize_script( sm_prefix( 'admin-script' ), 'customify_settings', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'wp_rest'  => array(
					'root'                     => esc_url_raw( rest_url() ),
					'nonce'                    => wp_create_nonce( 'wp_rest' ),
					'customify_settings_nonce' => wp_create_nonce( 'customify_settings_nonce' ),
				),
			) );
		}

		wp_localize_script( sm_prefix( 'customizer-scripts' ), 'WP_API_Settings', array(
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		) );
	}

	/**
	 * Main Style_Manager_Plugin Instance
	 *
	 * Ensures only one instance of Style_Manager_Plugin is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @static
	 *
	 * @param string $file    File.
	 * @param string $version Version.
	 *
	 * @see    Style_Manager_Plugin()
	 * @return Style_Manager_Plugin Main Style_Manager_Plugin instance
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__,esc_html( __( 'Cheatin&#8217; huh?' ) ), esc_html( $this->_version ) );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cheatin&#8217; huh?' ) ),  esc_html( $this->_version ) );
	}

}

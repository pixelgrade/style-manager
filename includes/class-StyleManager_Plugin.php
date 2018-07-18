<?php

/**
 * The main plugin class.
 *
 * This loads all the components that make up the plugin.
 */
final class StyleManager_Plugin extends StyleManager_Plugin_Init {

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
	 * Unique identifier for your plugin.
	 * Use this value (not the variable name) as the text domain when internationalizing strings of text. It should
	 * match the Text Domain file header in the main plugin file.
	 * @since    1.0.0
	 * @var      string
	 */
	protected $plugin_slug = 'style-manager';

	/**
	 * The site's base URL.
	 * @var null|string
	 * @access protected
	 * @since 1.0.0
	 */
	protected $base_url = null;

	/**
	 * Customizer class object.
	 * @var StyleManager_Customizer
	 * @access  public
	 * @since   1.0.0
	 */
	public $customizer = null;

	/**
	 * Metaboxes class object.
	 * @var StyleManager_Metaboxes
	 * @access  public
	 * @since   1.0.0
	 */
	public $metaboxes = null;

	/**
	 * Plugin settings class object.
	 * @var StyleManager_Settings
	 * @access  public
	 * @since   1.0.0
	 */
	public $settings = null;

	/**
	 * Options class object
	 * @var     StyleManager_Options
	 * @access  public
	 * @since   1.0.0
	 */
	public $options = null;

	/**
	 * Plugin's REST API class object.
	 * @var StyleManager_Rest_Api_V1
	 * @access  public
	 * @since   1.0.0
	 */
	public $rest = null;

	/**
	 * Style Manager class object.
	 * @var StyleManager
	 * @access  public
	 * @since   1.0.0
	 */
	public $style_manager = null;

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
	private $minimalRequiredPhpVersion = 5.2;

	protected function __construct( $file, $version = '1.0.0' ) {
		// The main plugin file (the one that loads all this).
		$this->file = $file;
		// The current plugin version.
		$this->_version = $version;

		// Setup the helper variables for easily retrieving PATHS and URLS everywhere (these are already trailingslashit).
		$this->plugin_basepath = plugin_dir_path( $file );
		$this->plugin_baseuri  = plugin_dir_url( $file );
		$this->base_url        = home_url();

		// Initialize the options API.
		require_once( $this->plugin_basepath . 'includes/lib/class-Options.php' );
		if ( is_null( $this->options ) ) {
			$this->options = StyleManager_Options::getInstance( 'sm' );
		}

		parent::__construct( 'Style Manager' );

		// Only load and run the init function if we know PHP version can parse it.
		if ( $this->php_version_check() ) {
			$this->upgrade();
			$this->init();
		}
	}

	/**
	 * Initialize the plugin.
	 */
	private function init() {

		/* Initialize the metaboxes logic (CMB2). */
		require_once( $this->plugin_basepath . 'includes/class-Metaboxes.php' );
		if ( is_null( $this->metaboxes ) ) {
			$this->metaboxes = StyleManager_Metaboxes::getInstance( 'sm' );
		}

		/* Initialize the settings page. */
		require_once( $this->plugin_basepath . 'includes/class-Settings.php' );
		if ( is_null( $this->settings ) ) {
			$this->settings = StyleManager_Settings::getInstance( $this );
		}

		/* Initialize the REST API endpoints. */
		require_once( $this->plugin_basepath . 'includes/class-Rest_Api_V1.php' );
		if ( is_null( $this->rest ) ) {
			$this->rest = StyleManager_Rest_Api_V1::getInstance();
		}

		/* Initialize the Customizer logic. */
		require_once( $this->plugin_basepath . 'includes/class-Customizer.php' );
		if ( is_null( $this->customizer ) ) {
			$this->customizer = StyleManager_Customizer::getInstance( 'sm' );
		}

		/* Initialize the Style Manager logic. */
		require_once( $this->plugin_basepath . 'includes/class-StyleManager.php' );
		if ( is_null( $this->style_manager ) ) {
			$this->style_manager = StyleManager::getInstance( $this );
		}

		// Register all the needed hooks
		$this->register_hooks();
	}

	/**
	 * Register our actions and filters
	 */
	function register_hooks() {
		/* Handle the install and uninstall logic. */
		register_activation_hook( $this->file, array( 'StyleManager_Plugin', 'install' ) );
		register_deactivation_hook( $this->file, array( 'StyleManager_Plugin', 'uninstall' ) );

		/* Handle localisation. */
		add_action( 'init', array( $this, 'load_localisation' ), 0 );
	}

	public function get_version() {
		return $this->_version;
	}

	public function get_file() {
		return $this->file;
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

	public function get_baseuri() {
		return $this->plugin_baseuri;
	}

	public function get_basepath() {
		return $this->plugin_basepath;
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
		$loaded = load_plugin_textdomain( 'style-manager', false, dirname( $this->get_basepath() ) . '/languages/' );

		if ( ! $loaded ) {
			$loaded = load_muplugin_textdomain( 'style-manager', dirname( $this->get_basepath() ) . '/languages/' );
		}

		if ( ! $loaded ) {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'style-manager' );
			$mofile = dirname( $this->get_basepath() ) . '/languages/sm-' . $locale . '.mo';
			load_textdomain( 'style-manager', $mofile );
		}
	}
}

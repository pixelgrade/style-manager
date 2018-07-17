<?php
/**
 * Ensures the metaboxes logic is loaded and ready for use (CMB2 right now).
 */
class Style_Manager_Metaboxes {

	/**
	 * Holds the only instance of this class.
	 * @var null|string
	 * @access protected
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Prefix for all of our metas or options.
	 * @var string
	 */
	protected $prefix = null;

	/**
	 * Constructor
	 * @since 0.1.0
	 *
	 * @param Style_Manager_Plugin $parent
	 * @param string $prefix
	 */
	private function __construct( $prefix = null ) {
		$this->prefix = $prefix;

		$this->load_cmb2();

		$this->add_hooks();
	}

	/**
	 * Initiate our hooks
	 * @since 1.0.0
	 */
	public function add_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ), 9, 1 );
	}

	/**
	 * Fire up CMB2.
	 *
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_cmb2() {

		//load everything about CMB2 - if that is the case
		/**
		 * A constant you can use to check if CMB2 is loaded
		 */
		if ( ! defined( 'CMB2_LOADED' ) ) {
			define( 'CMB2_LOADED', true );
		}
		add_action( 'init', array( $this, 'include_cmb' ), 9969 );
		add_filter( 'cmb2_script_dependencies', array( $this, 'cmb2_requires_wp_media' ) );

		//The CMB2 conditional display of fields
		if ( file_exists( dirname( __FILE__ ) . '/vendor/cmb2-conditionals/cmb2-conditionals.php' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/cmb2-conditionals/cmb2-conditionals.php';
		}

		//The CMB2 tabs
		if ( file_exists( dirname( __FILE__ ) . '/vendor/cmb2-metatabs-options/cmb2_metatabs_options.php' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/cmb2-metatabs-options/cmb2_metatabs_options.php';
		}

		//The CMB2 Select2 field
		if ( file_exists( dirname( __FILE__ ) . '/vendor/cmb-field-select2/cmb-field-select2.php' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/cmb-field-select2/cmb-field-select2.php';

			// Also filter the path used for enqueuing assets by the field
			add_filter( 'pw_cmb2_field_select2_asset_path', array( $this, 'pw_cmb2_field_select2_asset_path' ) );
		}

		//The CMB2 Grid plugin
		if ( file_exists( dirname( __FILE__ ) . '/vendor/CMB2-grid/Cmb2GridPluginLoad.php' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/CMB2-grid/Cmb2GridPluginLoad.php';
		}
	}

	/**
	 * CMB2 Field Type: Select2 asset path
	 *
	 * Filter the path to front end assets (JS/CSS).
	 */
	public function pw_cmb2_field_select2_asset_path() {
		return Style_Manager_Plugin::instance()->plugin_baseuri . '/includes/vendor/cmb-field-select2';
	}

	/**
	 * A final check if CMB2 exists before kicking off our CMB2 loading.
	 * CMB2_VERSION and CMB2_DIR constants are set at this point.
	 *
	 * @since  1.0.0
	 */
	public function include_cmb() {
		if ( class_exists( 'CMB2', false ) ) {
			return;
		}

		if ( ! defined( 'CMB2_VERSION' ) ) {
			define( 'CMB2_VERSION', '2.3.0' );
		}

		if ( ! defined( 'CMB2_DIR' ) ) {
			define( 'CMB2_DIR', trailingslashit( dirname( __FILE__ ) ) . '/vendor/CMB2/' );
		}

		$this->l10ni18n_cmb();

		// Include helper functions
		require_once CMB2_DIR . 'includes/CMB2_Base.php';
		require_once CMB2_DIR . 'includes/CMB2.php';
		require_once CMB2_DIR . 'includes/helper-functions.php';

		// Now kick off the class autoloader
		spl_autoload_register( 'cmb2_autoload_classes' );

		// Kick the whole thing off
		require_once CMB2_DIR . 'bootstrap.php';
		cmb2_bootstrap();
	}

	function cmb2_requires_wp_media( $dependencies ) {
		$dependencies['media-editor'] = 'media-editor';

		return $dependencies;
	}

	/**
	 * Registers CMB2 text domain path
	 * @since  1.0.0
	 */
	public function l10ni18n_cmb() {
		$loaded = load_plugin_textdomain( 'cmb2', false, '/languages/' );

		if ( ! $loaded ) {
			$loaded = load_muplugin_textdomain( 'cmb2', '/languages/' );
		}

		if ( ! $loaded ) {
			$loaded = load_theme_textdomain( 'cmb2', get_stylesheet_directory() . '/languages/' );
		}

		if ( ! $loaded ) {
			$locale = apply_filters( 'plugin_locale', get_locale(), 'cmb2' );
			$mofile = dirname( __FILE__ ) . '/vendor/CMB2/languages/cmb2-' . $locale . '.mo';
			load_textdomain( 'cmb2', $mofile );
		}
	}

	public function register_admin_scripts() {
		wp_register_script( sm_prefix( 'cmb2-conditionals' ), plugins_url( 'assets/js/cmb2-conditionals.js', Style_Manager_Plugin::instance()->file ), array('jquery'), '1.0.4');
	}

	/**
	 * Adds a prefix to an option name.
	 *
	 * @param string $option
	 * @param string $separator Optional. Defaults to '_'.
	 *
	 * @return string
	 */
	public function prefix( $option, $separator = '_' ) {
		return $this->prefix . $separator . $option;
	}

	/**
	 * Main Style_Manager_Metaboxes Instance
	 *
	 * Ensures only one instance of Style_Manager_Metaboxes is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @static
	 * @param string $prefix
	 * @return Style_Manager_Metaboxes Main Style_Manager_Metaboxes instance
	 */
	public static function instance( $prefix = 'sm' ) {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self(  $prefix );
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {

		_doing_it_wrong( __FUNCTION__,esc_html( __( 'Cheatin&#8217; huh?' ) ), null );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cheatin&#8217; huh?' ) ),  null );
	} // End __wakeup ()

}

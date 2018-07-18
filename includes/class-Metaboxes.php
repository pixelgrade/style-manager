<?php
/**
 * Ensures the metaboxes logic is loaded and ready for use (CMB2 right now).
 */
class StyleManager_Metaboxes extends StyleManager_Singleton_Registry {

	/**
	 * Prefix for all of our metas or options.
	 * @var string
	 */
	protected $prefix = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix
	 */
	protected function __construct( $prefix = null ) {
		$this->prefix = $prefix;

		$this->load_cmb2();

		$this->add_hooks();
	}

	/**
	 * Initiate our hooks.
	 *
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

		// Load everything about CMB2 - if that is the case.
		if ( file_exists( dirname( __FILE__ ) . '/vendor/CMB2/init.php' ) ) {
			require_once dirname( __FILE__ ) . '/vendor/CMB2/init.php';
		}

		//The CMB2 conditional display of fields
		if ( ! defined( 'CMB2_CONDITIONALS_PRIORITY' ) && file_exists( dirname( __FILE__ ) . '/vendor/cmb2-conditionals/cmb2-conditionals.php' ) ) {
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
		return StyleManager_Plugin::getInstance()->get_baseuri() . '/includes/vendor/cmb-field-select2';
	}

	public function register_admin_scripts() {
		wp_register_script( sm_prefix( 'cmb2-conditionals' ), plugins_url( 'assets/js/cmb2-conditionals.js', StyleManager_Plugin::getInstance()->get_file() ), array('jquery'), '1.0.4');
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
}

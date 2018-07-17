<?php
/**
 * Style Manager settings page logic.
 */
class Style_Manager_Settings {

	/**
	 * Holds the only instance of this class.
	 * @var null|string
	 * @access protected
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
 	 * Option key, and, at the same time, option page slug
 	 * @var string
 	 */
	protected $key = null;

	/**
	 * Settings Page title
	 * @var string
	 * @access protected
	 * @since 1.0.0
	 */
	protected $title = '';

	/**
	 * Settings Page hook
	 * @var string
	 * @access protected
	 * @since 1.0.0
	 */
	protected $options_page = '';

	/**
	 * The main plugin object (the parent).
	 * @var     Style_Manager_Plugin
	 * @access  public
	 * @since     1.0.0
	 */
	public $parent = null;

	/**
	 * Constructor
	 * @since 0.1.0
	 *
	 * @param Style_Manager_Plugin $parent
	 */
	private function __construct( $parent = null ) {
		$this->parent = $parent;

		$this->key = $this->prefix('options' );

		// Set our settings page title.
		$this->title = __( 'Style Manager Setup', 'style-manager' );



		$this->add_hooks();
	}

	/**
	 * Initiate our hooks
	 *
	 * @since 1.0.0
	 */
	public function add_hooks() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'cmb2_admin_init', array( $this, 'cmb2_init' ) );
	}

	/**
	 * Do general things on the `init` hook.
	 *
	 * @since  1.0.0
	 */
	public function init() {

	}

	/**
	 * Register the fields, tabs, etc for our settings page.
	 *
	 * @since  1.0.0
	 */
	public function cmb2_init() {

		// Use CMO filter to add an intro at the top of the options page.
		add_filter( 'cmb2metatabs_before_form', array( $this, 'cmb2_metatabs_options_add_intro_via_filter' ) );

		$args = array(
			'key'     => $this->key,
			'title'   => $this->title,
			'topmenu' => '', // put it on the top level in the admin sidebar.
			'cols'    => 1, // we will not use sidebar boxes for now.
			'boxes'	  => $this->cmb2_metatabs_options_add_boxes( $this->key ),
			'tabs'	  => $this->cmb2_metatabs_options_add_tabs(),
			'menuargs' => array(
				'menu_title' => 'Style Manager',
				'position'    => 56.01,
			),
			'load'  => array(
				array(
					'action'   => 'load-[hook]',
					'callback' => array( $this, 'enqueue_admin_scripts' ),
                    'priority' => 10,
                    'args'     => 1,
                ),
			),
			'savetxt' => '', //disable the global save button (one for all tabs).
			'resettxt' => '', //disable reset button.

		);

		// Create the options page
		new Cmb2_Metatabs_Options( $args );
	}

	public function enqueue_admin_scripts() {
		// The styles.
		wp_enqueue_style( $this->prefix( 'admin-style' ), plugins_url( 'assets/css/admin.css', Style_Manager_Plugin::instance()->file ), array( $this->prefix( 'jquery-ui-style' ), ), $this->parent->_version );

		// The scripts.
		wp_register_script( $this->prefix( 'moment-js' ), plugins_url(  'assets/js/moment.min.js', Style_Manager_Plugin::instance()->file ), array( 'jquery' ), '2.22.1' );

		wp_register_script( $this->prefix( 'settings-js' ), plugins_url( 'assets/js/settings-page.js', Style_Manager_Plugin::instance()->file ),
			array(
				'jquery',
				$this->prefix( 'cmb2-conditionals' ),
				'wp-api',
			), $this->parent->_version );

		wp_enqueue_script( $this->prefix( 'settings-js' ) );

		// Localize the script so it knows about the REST API route to use to fetch licenses data.
		$namespace = $this->parent->rest->get_namespace();
		$base      = 'front';
		$data = array(
			'user_id' => get_current_user_id(),
		);

		$data = apply_filters( 'style_manager_localization_data', $data );

		wp_localize_script( $this->prefix( 'settings-js' ), 'style_manager_data', $data );
	}

	/**
	 * Add boxes the normal CMB2 way.
	 *
	 * This is typical CMB2, but note two crucial extra items:
	 *
	 * - the ['show_on'] property is configured
	 * - a call to object_type method
	 *
	 * See the wiki for more detail on why these are important and what their values are.
	 *
	 * @param string $options_key
	 * @return array
	 */
	public function cmb2_metatabs_options_add_boxes( $options_key ) {

		// holds all CMB2 box objects
		$boxes = array();

		// we will be adding this to all boxes
		$show_on = array(
			'key' => 'options-page',
			'value' => array( $options_key ),
		);

		$cmb = new_cmb2_box( array(
			'id'              => $this->prefix( 'color_palettes' ),
			'title'           => __( 'Color Palettes', 'style-manager' ),
			'show_on'         => $show_on,
			'display_cb'      => false,
			'admin_menu_hook' => false,
		) );

		$cmb->add_field( array(
			'name'    => esc_html__( 'Default Background Image', 'style-manager' ),
			'desc' => esc_html__( 'The default background image to use for color palettes, preferably JPEG or PNG (if you must). Please note that we will crop this image to the following dimensions: 400x156.', 'style-manager' ),
			'id'      => $this->prefix( 'color_palettes_default_background_image' ),
			'type'    => 'file',
			'preview_size' => 'palette_background',
			'options' => array(
				'url' => false, // Hide the text input for the url
			),
			'text'    => array(
				'add_upload_file_text' => esc_html__( 'Select Image', 'style-manager' ), // Change upload button text. Default: "Add or Upload File"
			),
			'query_args' => array(
				'type' => array(
					'image/jpeg',
					'image/png',
				),
			),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( '', 'cmb2' ),
			'desc' => esc_html__( 'Save Changes', 'style-manager' ),
			'id'   => $this->prefix( 'color_palettes_save' ),
			'type' => 'options_save_button',
		) );
		$cmb->object_type( 'options-page' );
		$boxes[] = $cmb;

		return $boxes;
	}

	/**
	 * Add the settings tabs.
	 *
	 * Tabs are completely optional and removing them would result in the option metaboxes displaying sequentially.
	 *
	 * If you do configure tabs, all boxes whose context is "normal" or "advanced" must be in a tab to display.
	 *
	 * @return array
	 */
	public function cmb2_metatabs_options_add_tabs() {

		$tabs = array();

		$tabs[] = array(
			'id'    => $this->prefix( 'general_tab' ),
			'title' => __( 'Setup', 'style-manager' ),
			'desc'  => '<p>Setup the general cloud settings.</p>',
			'boxes' => array(
				$this->prefix( 'color_palettes' ),
			),
		);
		$tabs[] = array(
			'id'    => $this->prefix( 'devhelp_tab' ),
			'title' => __( 'Dev Help', 'style-manager' ),
			'desc'  => '<p>Settings to help developers test the system and integration with it.</p>',
			'boxes' => array(
//				$this->prefix( 'sandbox_assets_management' ),
			),
		);
		$tabs[] = array(
			'id'    => $this->prefix( 'import_export_tab' ),
			'title' => __( 'Import/Export', 'style-manager' ),
			'desc'  => '<p>Import or export current settings (to do).</p>',
			'boxes' => array(
//				$this->prefix( 'import' ),
//				$this->prefix( 'export' ),
			),
		);

		return $tabs;
	}


	/**
	 * Callback for CMO filter.
	 *
	 * The two filters in CMO do not send any content; simply return your HTML.
	 *
	 * @return string
	 */
	public function cmb2_metatabs_options_add_intro_via_filter() {
		return wp_kses_post( __( '<p>Some general intro here.</p>', 'style-manager' ) );
	}

	/**
	 * Get a list of posts
	 *
	 * Generic function to return an array of posts formatted for CMB2. Simply pass
	 * in your WP_Query arguments and get back a beautifully formatted CMB2 options
	 * array.
	 *
	 * @param array $query_args WP_Query arguments
	 * @return array CMB2 options array
	 */
	protected function get_cmb_options_array_posts( $query_args = array() ) {
		$defaults = array(
			'posts_per_page' => -1
		);
		$query = new WP_Query( array_replace_recursive( $defaults, $query_args ) );
		return wp_list_pluck( $query->get_posts(), 'post_title', 'ID' );
	}

	/**
	 * Get a list of terms
	 *
	 * Generic function to return an array of taxonomy terms formatted for CMB2.
	 * Simply pass in your get_terms arguments and get back a beautifully formatted
	 * CMB2 options array.
	 *
	 * @param string|array $taxonomies Taxonomy name or list of Taxonomy names
	 * @param  array|string $query_args Optional. Array or string of arguments to get terms
	 * @return array CMB2 options array
	 */
	protected function get_cmb_options_array_tax( $taxonomies, $query_args = '' ) {
		$defaults = array(
			'hide_empty' => false
		);
		$args = wp_parse_args( $query_args, $defaults );
		$terms = get_terms( $taxonomies, $args );
		$terms_array = array();
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$terms_array[$term->term_id] = $term->name;
			}
		}
		return $terms_array;
	}

	/**
	 * Replaces get_option with get_site_option
	 * @since  0.1.0
	 */
	public function get_override( $test, $default = false ) {
		return get_site_option( $this->key, $default );
	}

	/**
	 * Replaces update_option with update_site_option
	 * @since  0.1.0
	 */
	public function update_override( $test, $option_value ) {
		return update_site_option( $this->key, $option_value );
	}

	/**
	 * Public getter method for retrieving protected/private variables
	 *
	 * @throws Exception
	 * @param  string  $field Field to retrieve
	 * @return mixed          Field value or exception is thrown
	 */
	public function __get( $field ) {
		// Allowed fields to retrieve
		if ( in_array( $field, array( 'key', 'metabox_id', 'title', 'options_page' ), true ) ) {
			return $this->{$field};
		}

		throw new Exception( 'Invalid property: ' . $field );
	}

	/**
	 * Adds a prefix to an option name.
	 *
	 * @param string $option
	 * @param bool $private Optional. Whether this option name should also get a '_' in front, marking it as private
	 *
	 * @return string
	 */
	public function prefix( $option, $private = false ) {
		$option = $this->parent->metaboxes->prefix( $option );

		if ( true === $private ) {
			return '_' . $option;
		}

		return $option;
	}

	/**
	 * Main Style_Manager_Settings Instance
	 *
	 * Ensures only one instance of Style_Manager_Settings is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @static
	 * @see    sm_settings()
	 * @param  object $parent Main Style_Manager instance.
	 * @return Style_Manager_Settings Main Style_Manager_Settings instance
	 */
	public static function instance( $parent = null ) {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone() {

		_doing_it_wrong( __FUNCTION__,esc_html( __( 'Cheatin&#8217; huh?' ) ), esc_html( $this->parent->_version ) );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {

		_doing_it_wrong( __FUNCTION__, esc_html( __( 'Cheatin&#8217; huh?' ) ),  esc_html( $this->parent->_version ) );
	} // End __wakeup ()

}

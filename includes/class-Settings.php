<?php
/**
 * Style Manager settings page logic.
 */
class StyleManager_Settings extends StyleManager_Singleton_Registry {

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {

		$this->key = $this->prefix('options' );

		// Set our settings page title.
		$this->title = esc_html__( 'Style Manager Setup', 'style-manager' );

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
//		add_filter( 'cmb2metatabs_before_form', array( $this, 'cmb2_metatabs_options_add_intro_via_filter' ) );

		$args = array(
			'key'     => $this->key,
			'title'   => $this->title,
			'topmenu' => 'themes.php', // put it in Appearance in the admin sidebar.
			'cols'    => 1, // we will not use sidebar boxes for now.
			'boxes'	  => $this->cmb2_metatabs_options_add_boxes( $this->key ),
			'tabs'	  => $this->cmb2_metatabs_options_add_tabs(),
			'menuargs' => array(
				'menu_title' => esc_html__('Style Manager', 'style-manager' ),
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
		wp_enqueue_style( $this->prefix( 'admin-style' ), plugins_url( 'assets/css/admin.css', StyleManager_Plugin()->get_file() ), array( $this->prefix( 'jquery-ui-style' ), ), StyleManager_Plugin()->get_version() );

		// The scripts.
		wp_register_script( $this->prefix( 'moment-js' ), plugins_url(  'assets/js/moment.min.js', StyleManager_Plugin()->get_file() ), array( 'jquery' ), '2.22.1' );

		wp_register_script( $this->prefix( 'settings-js' ), plugins_url( 'assets/js/settings-page.js', StyleManager_Plugin()->get_file() ),
			array(
				'jquery',
				$this->prefix( 'cmb2-conditionals' ),
				'wp-api',
			), StyleManager_Plugin()->get_version() );

		wp_enqueue_script( $this->prefix( 'settings-js' ) );

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
			'key'   => 'options-page',
			'value' => array( $options_key ),
		);

		$cmb = new_cmb2_box( array(
			'id'              => $this->prefix( 'customizer' ),
			'title'           => __( 'Customizer', 'style-manager' ),
			'show_on'         => $show_on,
			'display_cb'      => false,
			'admin_menu_hook' => false,
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Editor Style', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'enable_editor_style' ),
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( '', 'cmb2' ),
			'desc' => esc_html__( 'Save Changes', 'style-manager' ),
			'id'   => $this->prefix( 'customizer_save' ),
			'type' => 'options_save_button',
		) );
		$cmb->object_type( 'options-page' );
		$boxes[] = $cmb;

		$cmb = new_cmb2_box( array(
			'id'              => $this->prefix( 'output' ),
			'title'           => __( 'Output', 'style-manager' ),
			'show_on'         => $show_on,
			'display_cb'      => false,
			'admin_menu_hook' => false,
		) );

		$cmb->add_field( array(
			'name'             => esc_html__( 'Where to output the inline CSS style', 'style-manager' ),
			'desc'             => esc_html__( 'Here you can decide where to put your style output, in header or footer', 'style-manager' ),
			'id'               => $this->prefix( 'style_resources_location' ),
			'type'             => 'select',
			'show_option_none' => false,
			'default'          => 'head',
			'options'          => array(
				'head'   => esc_html__( 'In header (just before the head tag)', 'style-manager' ),
				'footer' => esc_html__( 'Footer (just before the end of the body tag)', 'style-manager' ),
			),
		) );

		$cmb->add_field( array(
			'name' => esc_html__( '', 'cmb2' ),
			'desc' => esc_html__( 'Save Changes', 'style-manager' ),
			'id'   => $this->prefix( 'output_save' ),
			'type' => 'options_save_button',
		) );
		$cmb->object_type( 'options-page' );
		$boxes[] = $cmb;

		$cmb = new_cmb2_box( array(
			'id'              => $this->prefix( 'typography' ),
			'title'           => __( 'Typography', 'style-manager' ),
			'show_on'         => $show_on,
			'display_cb'      => false,
			'admin_menu_hook' => false,
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Typography Controls', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'enable_typography' ),
			'type' => 'checkbox',
			'default' => true,
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Use Standard/System Fonts', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'typography_use_standard_fonts' ),
			'type' => 'checkbox',
			'default' => true,
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Use Google Fonts', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'typography_use_google_fonts' ),
			'type' => 'checkbox',
			'default' => true,
		) );
		$cmb->add_field( array(
			'name' => esc_html__( 'Group Google Fonts', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'typography_group_google_fonts' ),
			'type' => 'checkbox',
			'default' => true,
		) );

		$cmb->add_field( array(
			'name' => esc_html__( '', 'cmb2' ),
			'desc' => esc_html__( 'Save Changes', 'style-manager' ),
			'id'   => $this->prefix( 'typography_save' ),
			'type' => 'options_save_button',
		) );
		$cmb->object_type( 'options-page' );
		$boxes[] = $cmb;

		// Dev boxes
		$cmb = new_cmb2_box( array(
			'id'              => $this->prefix( 'dev_customizer' ),
			'title'           => __( 'Customizer', 'style-manager' ),
			'show_on'         => $show_on,
			'display_cb'      => false,
			'admin_menu_hook' => false,
		) );

		$cmb->add_field( array(
			'name' => esc_html__( 'Enable Reset Buttons', 'style-manager' ),
			'desc' => 'field description (optional)',
			'id'   => $this->prefix( 'enable_reset_buttons' ),
			'type' => 'checkbox',
		) );

		$cmb->add_field( array(
			'name' => esc_html__( '', 'cmb2' ),
			'desc' => esc_html__( 'Save Changes', 'style-manager' ),
			'id'   => $this->prefix( 'dev_customizer_save' ),
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
			'desc'  => '<p>Setup the general Style Manager settings.</p>',
			'boxes' => array(
				$this->prefix( 'customizer' ),
				$this->prefix( 'output' ),
				$this->prefix( 'typography' ),
			),
		);
		$tabs[] = array(
			'id'    => $this->prefix( 'devhelp_tab' ),
			'title' => __( 'Dev Help', 'style-manager' ),
			'desc'  => '<p>Settings to help developers test the system and integration with it.</p>',
			'boxes' => array(
				$this->prefix( 'dev_customizer' ),
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
	 * Get a list of terms.
	 *
	 * Generic function to return an array of taxonomy terms formatted for CMB2.
	 * Simply pass in your get_terms arguments and get back a beautifully formatted
	 * CMB2 options array.
	 *
	 * @param string|array $taxonomies Taxonomy name or list of Taxonomy names.
	 * @param  array|string $query_args Optional. Array or string of arguments to get terms.
	 * @return array CMB2 options array.
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
	 * Replaces get_option with get_site_option.
	 *
	 * @since 1.0.0
	 */
	public function get_override( $test, $default = false ) {
		return get_site_option( $this->key, $default );
	}

	/**
	 * Replaces update_option with update_site_option.
	 *
	 * @since  1.0.0
	 */
	public function update_override( $test, $option_value ) {
		return update_site_option( $this->key, $option_value );
	}

	/**
	 * Public getter method for retrieving protected/private variables.
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
	 * @param bool $private Optional. Whether this option name should also get a '_' in front, marking it as private.
	 *
	 * @return string
	 */
	public function prefix( $option, $private = false ) {
		$option = sm_prefix( $option );

		if ( true === $private ) {
			return '_' . $option;
		}

		return $option;
	}
}

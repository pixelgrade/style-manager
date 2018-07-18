<?php

/**
 * Class StyleManager_Rest_Api_V1
 */
class StyleManager_Rest_Api_V1 extends StyleManager_Singleton_Registry {

	/**
	 * The REST API slug used to form the namespace.
	 *
	 * @var     string
	 * @access  private
	 * @since     1.0.0
	 */
	protected $slug = 'sm';

	/**
	 * The REST API version used to form the namespace.
	 *
	 * @var     string
	 * @access  private
	 * @since     1.0.0
	 */
	protected $version = '1';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {
		// Register the public REST API routes
		add_action( 'rest_api_init', array( $this, 'register_public_routes' ) );
	}

	public function get_namespace() {
		return $this->slug . '/v' . $this->version;
	}

	/**
	 * Register the REST API routes.
	 */
	public function register_public_routes() {
		$namespace = $this->get_namespace();
		$base      = 'front';

		/**
		 * Register REST API endpoints.
		 */
//		register_rest_route( $namespace, $base . '/design_assets', array(
//			array(
//				'methods'             => WP_REST_Server::READABLE,
//				'callback'            => array( $this, 'get_design_assets' ),
//				'show_in_index'       => false, // We don't need others to know about this (API discovery)
//				'args'     => array(
//					'site_url' => array(
//						'required' => true,
//						'type' => 'string',
//						'sanitize_callback' => array( __CLASS__, 'sanitize_string' ),
//						//'validate_callback' => ...
//						'description' => esc_html__( 'The WordPress installation root URL.', 'style-manager' ),
//					),
//					'theme_data' => array(
//						'required' => true,
//						//'validate_callback' => ...
//						'description' => esc_html__( 'The active theme data.', 'style-manager' ),
//					),
//					'site_data' => array(
//						'required' => true,
//						//'validate_callback' => ...
//						'description' => esc_html__( 'The WordPress site general data.', 'style-manager' ),
//					),
//					'customer' => array(
//						'required' => false,
//						//'validate_callback' => ...
//						'description' => esc_html__( 'The customer data.', 'style-manager' ),
//					),
//				),
//			),
//		) );
	}

	/**
	 * Check if a given request has access to get items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		$params = $request->get_body_params();

		if ( ! isset( $params['user_id'] ) && ! isset( $params['type'] ) ) {
			return false;
		}

		// ensure the post type starts with wup
		if ( false === strpos( $params['type'], 'wup_' ) ) {
			$post_type = 'wup_' . $params['type'];
		} else {
			$post_type = $params['type'];
		}

		$post_type = get_post_type_object( $post_type );

		if ( empty ( $post_type ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->edit_posts );
	}

	/**
	 * Check if a given request has access to get a specific item
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
	}

	/**
	 * Check if a given request has access to create items
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check( $request ) {
		$params = $request->get_params();

		if ( ! isset( $params['user_id'] ) && ! isset( $params['post_type'] ) ) {
			return false;
		}

		// ensure the post type starts with wup
		if ( false === strpos( $params['post_type'], 'wup_' ) ) {
			$post_type = 'wup_' . $params['post_type'];
		} else {
			$post_type = $params['post_type'];
		}

		$post_type = get_post_type_object( $post_type );

		if ( empty ( $post_type ) ) {
			return false;
		}

		return current_user_can( $post_type->cap->edit_posts );
	}

	public function edit_others_posts_permissions_check( $request ) {
		return current_user_can( 'edit_others_posts' );
	}

	/**
	 * Jut a little bit of trimming.
	 *
	 * @param $string
	 * @param $request
	 * @param $param
	 *
	 * @return string
	 */
	public static function sanitize_string( $string, $request, $param ) {
		return trim( $string );
	}

	/**
	 * Make sure that we get an int.
	 *
	 * @param $int
	 * @param $request
	 * @param $param
	 *
	 * @return string
	 */
	public static function sanitize_int( $int, $request, $param ) {
		return intval( $int );
	}

	/**
	 * Make sure that we get a safe text.
	 *
	 * @param $text
	 * @param $request
	 * @param $param
	 *
	 * @return string
	 */
	public static function sanitize_text_field( $text, $request, $param ) {
		return sanitize_text_field( $text );
	}
}

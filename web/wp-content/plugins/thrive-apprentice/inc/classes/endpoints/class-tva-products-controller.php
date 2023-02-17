<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package ${NAMESPACE}
 */

use TVA\Drip\Campaign;
use TVA\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class TVA_Products_Controller
 *
 * @project: thrive-apprentice
 */
class TVA_Products_Controller extends WP_REST_Controller {

	protected $rest_base = 'products';

	protected $namespace = 'tva/v1';

	public function register_routes() {
		register_rest_route( $this->namespace, $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'name' => array(
						'description' => 'Title of the Apprentice Product',
						'type'        => 'string',
						'required'    => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'search' => array(
						'description' => 'Search by term name',
						'type'        => 'string',
						'required'    => false,
					),
					'offset' => array(
						'description' => 'Used in pagination. An integer from where the query should start fetching items',
						'type'        => 'integer',
						'required'    => false,
					),
					'number' => array(
						'description' => 'Used in pagination. The maximum number of items to fetch',
						'type'        => 'integer',
						'required'    => false,
					),
				),
			),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<ID>[\d]+)/assign-campaign', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'assign_drip_campaign' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID'          => array(
						'type'     => 'integer',
						'required' => true,
					),
					'campaign_id' => array(
						'type'     => 'integer',
						'required' => true,
					),
					'course_id'   => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<ID>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID'    => array(
						'type'     => 'integer',
						'required' => true,
					),
					'name'  => array(
						'type'     => 'string',
						'required' => true,
					),
					'rules' => array(
						'type'     => 'array',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<ID>[\d]+)/sets', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_item_sets' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID'   => array(
						'type'     => 'integer',
						'required' => true,
					),
					'name' => array(
						'type'     => 'string',
						'required' => true,
					),
					'sets' => array(
						'type'     => 'array',
						'required' => false,
					),
				),
			),
		) );

		register_rest_route(
			$this->namespace, $this->rest_base . '/update_orders',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_orders' ),
					'permission_callback' => array( 'TVA_Product', 'has_access' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<ID>[\d]+)/courses', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_courses' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
		) );

		register_rest_route( $this->namespace, $this->rest_base . '/(?P<ID>[\d]+)/identifier', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_identifier' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID'         => array(
						'type'     => 'integer',
						'required' => true,
					),
					'identifier' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_identifier' ),
				'permission_callback' => array( 'TVA_Product', 'has_access' ),
				'args'                => array(
					'ID' => array(
						'type'     => 'integer',
						'required' => true,
					),
				),
			),
		) );

	}

	/**
	 * Assigned a drip campaign of a course to a product
	 * - so that a course has a specific drip campaign for a product
	 *
	 * @param WP_REST_Request $request
	 */
	public function assign_drip_campaign( $request ) {

		$product = new Product( (int) $request->get_param( 'ID' ) );

		$saved = $product->assign_drip_campaign(
			new Campaign( (int) $request->get_param( 'campaign_id' ) ),
			new TVA_Course_V2( (int) $request->get_param( 'course_id' ) )
		);

		return new WP_REST_Response( $saved );
	}

	/**
	 * Return a list of courses which have been assigned to current product through content Sets
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_courses( $request ) {
		$product_id = (int) $request->get_param( 'ID' );
		$product    = new TVA\Product( $product_id );
		$courses    = $product->get_courses();

		foreach ( $courses as $course ) {
			$campaign                  = $product->get_drip_campaign_for_course( $course );
			$course->selected_campaign = $campaign ? $campaign->ID : null;
		}

		return new WP_REST_Response( $courses );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return true|WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {

		$product = new TVA\Product( $request->get_params() );

		$cache_plugin = tve_dash_detect_cache_plugin();
		if ( $cache_plugin ) {
			tve_dash_cache_plugin_clear( $cache_plugin );
		}

		return $product->save()->update_sets();
	}

	/**
	 * Endpoint to get all products
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array_map( static function ( $item ) {
			return sanitize_text_field( trim( $item ) );
		}, $request->get_params() );

		$items = TVA\Product::get_items( $args );

		/**
		 * We need to unset the pagination arguments for fetching the total number of items
		 */
		unset( $args['number'], $args['offset'] );

		return new WP_REST_Response(
			array(
				'total' => TVA\Product::get_items( $args, true ),
				'items' => $items,
			), 200 );
	}

	public function delete_item( $request ) {

		$product = new TVA\Product( (int) $request->get_param( 'ID' ) );

		return $product->delete();

	}

	public function get_item( $request ) {
		return rest_ensure_response( new TVA\Product( (int) $request->get_param( 'ID' ) ) );
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return Product
	 */
	public function update_item( $request ) {
		$product = new TVA\Product( $request->get_params() );

		$cache_plugin = tve_dash_detect_cache_plugin();
		if ( $cache_plugin ) {
			tve_dash_cache_plugin_clear( $cache_plugin );
		}

		return $product->save();
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return Product
	 */
	public function update_item_sets( $request ) {
		$product = new TVA\Product( $request->get_params() );

		$cache_plugin = tve_dash_detect_cache_plugin();
		if ( $cache_plugin ) {
			tve_dash_cache_plugin_clear( $cache_plugin );
		}

		return $product->update_sets();
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return boolean
	 */
	public function update_orders( $request ) {
		foreach ( $request->get_params() as $product_id => $order ) {
			update_term_meta( (int) $product_id, 'tva_order', (int) $order );
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_REST_Response
	 */
	public function update_identifier( $request ) {
		$id               = $request->get_param( 'ID' );
		$identifier       = sanitize_text_field( trim( $request->get_param( 'identifier' ) ) );
		$existing_product = new TVA\Product( $identifier );
		$product          = new TVA\Product( $id );

		if ( ! empty( $existing_product->get_id() ) ) {
			return new WP_REST_Response( [
				'error_messages' => 'There is already another product with this identifier',
				'old_identifier' => $product->get_identifier(),
			], 409 );
		}

		$result = $product->update_identifier( $identifier );

		if ( is_wp_error( $result ) || is_null( $result ) ) {
			return new WP_REST_Response( 'Invalid identifier provided', 400 );
		}

		return true;
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_REST_Response
	 */
	public function delete_identifier( $request ) {
		$id      = $request->get_param( 'ID' );
		$product = new TVA\Product( $id );
		$result  = $product->delete_identifier();

		if ( is_wp_error( $result ) || is_null( $result ) ) {
			return new WP_REST_Response( 'Could not be deleted', 500 );
		}

		return true;
	}
}

<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class TVA_Customer_Controller
 */
class TVA_Customer_Controller extends TVA_REST_Controller {
	/**
	 * endpoint base
	 *
	 * @var string
	 */
	public $base = 'customer';

	/**
	 * Allowed Customer File Extension
	 *
	 * @var array
	 */
	private $allowed_file_extensions = array( 'csv' );
	private $allowed_mimes = array( 'application/vnd.ms-excel', 'text/plain', 'text/csv', 'text/tsv' );

	public function register_routes() {

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)/purchased-items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_purchased_items' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'ID' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)/add-access',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'add_access' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)/disable-item/(?P<item_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'disable_order_item' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)/products',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_products' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'ID' => array(
							'required' => true,
							'type'     => 'integer',
						),
					),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/upload_file/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'upload_file' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/import_customers/',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'import_customers' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_customer' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(
						'user_email'   => array(
							'required' => true,
							'type'     => 'string',
						),
						'display_name' => array(
							'required' => true,
							'type'     => 'string',
						),
						'services'     => array(
							'required' => true,
							'type'     => 'object',
						),
					),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'edit_customer' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
			)
		);

		register_rest_route(
			static::$namespace . static::$version,
			'/' . $this->base . '/(?P<ID>[\d]+)/login_as_customer',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'login_as_customer' ),
					'permission_callback' => array( $this, 'admin_permission_check' ),
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Route for get customers
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return void|WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		$args = array(
			'offset' => (int) $request->get_param( 'offset' ),
			'limit'  => (int) $request->get_param( 'limit' ),
			's'      => $request->get_param( 's' ),
		);

		/**
		 * We use array_filter to filter empty values
		 */
		$products = array_filter( array( $request->get_param( 'course_id' ), $request->get_param( 'product_id' ), $request->get_param( 'bundle_id' ) ) );
		foreach ( $products as $product ) {
			$args['product_id'] = $product;
			break; //We break here in case there are multiple filters applied. (should not be the case)
		}

		if ( ! $request->get_param( 'no_product' ) ) {
			$customers = TVA_Customer::get_list( $args );
			$total     = (int) TVA_Customer::get_list( $args, true );
		} else {
			$customers = TVA_Customer::get_customers_with_no_products( $args );
			$total     = (int) TVA_Customer::get_customers_with_no_products( $args, true );
		}

		return new WP_REST_Response( array(
			'total' => $total,
			'items' => $customers,
		), 200 );
	}

	/**
	 * Endpoint for editing an existing customer
	 *
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function edit_customer( $request ) {
		$services = $request->get_param( 'services' );
		$user_id  = (int) $request->get_param( 'ID' );

		if ( empty( $services ) || ! is_array( $services ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid Parameters' ), 405 );
		}

		$user_obj = get_user_by( 'id', $user_id );
		$tva_user = new TVA_User( $user_id );

		$all_services = TVA_Customer_Manager::get_services();

		/**
		 * All all orders till now will be archived
		 */
		$orders = $tva_user->get_orders();
		/** @var TVA_Order $order */
		foreach ( $orders as $order ) {
			$order->set_status( TVA_Const::STATUS_EMPTY );
			$order->save( false );
		}

		/**
		 * We loop through passed services and create an order for each of one
		 */
		foreach ( $services as $service_key => $items ) {
			if ( empty( $items ) ) {
				continue;
			}

			TVA_Customer_Manager::create_order_for_customer( $user_obj, $service_key, $items, array(
				'gateway' => $all_services[ $service_key ]['gateway'],
				'type'    => TVA_Order::MANUAL,
			) );
		}

		return new WP_REST_Response( array( 'message' => sprintf( __( 'Customer %s has been updated!', TVA_Const::T ), $tva_user->get_display_name() ) ), 200 );
	}

	/**
	 * Endpoint used for creating new customers
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function create_customer( $request ) {
		$email    = sanitize_email( $request->get_param( 'user_email' ) );
		$name     = sanitize_text_field( $request->get_param( 'display_name' ) );
		$services = $request->get_param( 'services' );
		$notify   = (int) $request->get_param( 'notify' );

		if ( empty( $email ) || empty( $name ) || empty( $services ) || ! is_array( $services ) ) {
			return new WP_REST_Response( array( 'message' => esc_html__( 'Invalid Parameters', 'thrive-apprentice' ) ), 405 );
		}
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user instanceof WP_User ) {
			$existing_tva_user_obj = new TVA_User( $existing_user->ID );
			$orders                = $existing_tva_user_obj->get_orders_by_status( TVA_Const::STATUS_COMPLETED );

			if ( ! empty( $orders ) ) {
				return new WP_REST_Response( array( 'message' => __( "The customer already exists! If you want to update the user's access please use the Edit Access Rights option", TVA_Const::T ) ), 405 );
			}
		}

		$email_template = tva_email_templates()->check_template_for_any_trigger();
		$send_email     = ! empty( $notify ) && $notify === 1
						  && false !== $email_template
						  && true !== $existing_user instanceof WP_User;

		$user_data = TVA_Customer_Manager::insert_customer( array(
			'name'  => $name,
			'email' => $email,
		), $services, array(
			'email_template' => $email_template,
			'send_email'     => $send_email,
			'order_type'     => TVA_Order::MANUAL,
		) );

		if ( is_array( $user_data ) ) {
			$user_data['message'] = sprintf( __( 'Customer %s has been added!', TVA_Const::T ), $name );

			return new WP_REST_Response( $user_data, 200 );
		}

		return new WP_REST_Response( array( 'message' => 'Not Allowed' ), 405 );
	}

	/**
	 * Endpoint used for bulk importing customers
	 * (ex: from CSV File)
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function import_customers( $request ) {
		$customers = $request->get_param( 'customers' );
		$services  = $request->get_param( 'services' );
		$notify    = (int) $request->get_param( 'notify' );
		$response  = array(
			'imported_users' => array(),
		);

		/**
		 * If customer list empty we return an error for the user
		 */
		if ( empty( $customers ) || ! is_array( $customers ) || empty( $services ) || ! is_array( $services ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid Customer List' ), 401 );
		}

		$email_template = tva_email_templates()->check_template_for_any_trigger();

		$send_email = ! empty( $notify ) && $notify === 1 && false !== $email_template;

		/**
		 * push only users which don't have an account, by email
		 */
		foreach ( $customers as $key => $customer ) {

			$user_data = TVA_Customer_Manager::insert_customer( array(
				'email' => $customer['buyer_email'],
				'name'  => $customer['buyer_name'],
			), $services, array(
				'email_template' => $email_template,
				'send_email'     => $send_email,
				'order_type'     => TVA_Order::IMPORTED,
			) );

			if ( is_array( $user_data ) ) {
				$response['imported_users'][] = $user_data;
			}
		}

		$response['message'] = count( $response['imported_users'] ) . ' ' . __( 'users have been added', TVA_Const::T );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * @param $request
	 *
	 * @return WP_REST_Response
	 */
	public function login_as_customer( $request ) {
		if ( false === check_admin_referer( 'wp_rest', 'nonce' ) ) {
			return new WP_REST_Response( array( 'message' => 'Forbidden' ), 403 );
		}

		$customer_id = (int) $request->get_param( 'ID' );

		if ( user_can( $customer_id, 'create_sites' ) || user_can( $customer_id, 'manage_options' ) ) {
			return new WP_REST_Response( array( 'message' => 'Forbidden' ), 403 );
		}

		wp_clear_auth_cookie();
		wp_set_auth_cookie( $customer_id );
		wp_set_current_user( $customer_id );

		return new WP_REST_Response( [
			'courses_url' => tva_get_settings_manager()->factory( 'index_page' )->get_link(),
		], 200 );
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function upload_file( $request ) {
		if ( empty( $_FILES ) || ! empty( $_FILES['file']['error'] ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid File' ), 401 );
		}

		$file_name = $_FILES['file']['name'];
		$extension = pathinfo( $file_name, PATHINFO_EXTENSION );

		if ( ! in_array( $extension, $this->allowed_file_extensions ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid File Extension' ), 401 );
		}

		$file_type = $_FILES['file']['type'];
		if ( ! in_array( $file_type, $this->allowed_mimes ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid File type' ), 401 );
		}

		$rows = array();
		if ( ( $handle = fopen( $_FILES['file']['tmp_name'], 'r' ) ) !== false ) {
			while ( ( ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false ) ) && count( $rows ) < 1001 ) {
				$rows[] = $data;
			}
			fclose( $handle );
		}

		$data = TVA_Customer_Manager::process_data_from_csv( $rows );

		if ( empty( $data ) ) {
			return new WP_REST_Response( array( 'message' => 'invalid_file' ), 401 );
		}

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * List of items purchased by a customer
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_purchased_items( $request ) {

		$items   = array();
		$user_id = (int) $request->get_param( 'ID' );

		$tva_user            = new TVA_User( $user_id );
		$sendowl_product_ids = TVA_SendOwl::get_products_ids();

		/** @var TVA_Order $order */
		foreach ( $tva_user->get_orders_by_status( TVA_Const::STATUS_COMPLETED ) as $order ) {

			$check_product_availability = strtolower( $order->get_gateway() ) !== strtolower( TVA_Const::SENDOWL_GATEWAY );

			/** @var TVA_Order_Item $order_item */
			foreach ( $order->get_order_items() as $order_item ) {

				if ( ! $order_item->get_status() ) {
					continue;
				}

				if ( $check_product_availability && ! get_term( $order_item->get_product_id(), \TVA\Product::TAXONOMY_NAME ) instanceof WP_Term ) {
					/**
					 * For sendowl we have an exception here because the order ID that is stored in the database is from sendowl platform
					 *
					 * We need to make sure that we show order items that are linked to existing apprentice products
					 * If the user deletes the apprentice product, we do not show that order item
					 */
					continue;
				}

				$name = $order_item->get_product_name();

				$bundle = TVA_Bundle::init_by_number( $order_item->get_product_id() );
				if ( false === is_wp_error( $bundle ) ) {
					$name = $bundle->name;
				}

				$wc_product = null;
				if ( function_exists( 'wc_get_product' ) ) {
					$wc_product = wc_get_product( $order_item->get_gateway_order_item_id() );
				}

				$access_type = $order_item->get_access_type( $order, $sendowl_product_ids, $wc_product );

				$items[] = array(
					'id'       => $order_item->get_ID(),
					'order_id' => $order->get_id(),
					'name'     => $name,
					'type'     => $access_type,
					'source'   => TVA_Order::source( $order ),
					'icon'     => $order_item->get_access_type_slug( $access_type ),
					'date'     => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order_item->get_created_at() ) ),
				);
			}
		}

		return rest_ensure_response( $items );
	}

	/**
	 * List of courses customer has access to
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function get_products( $request ) {

		$items    = array();
		$user     = new WP_User( $request->get_param( 'ID' ) );
		$products = \TVA\Product::get_items();
		tva_access_manager()->set_user( $user );

		/** @var \TVA\Product $product */
		foreach ( $products as $product ) {

			$allowed = tva_access_manager()->set_product( $product )->check_rules();

			if ( $allowed ) {
				$items[] = array(
					'id'   => $product->get_id(),
					'name' => $product->get_name(),
				);
			}
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Disables an order item
	 * - disables the whole order if the item is the last one enabled
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return true|WP_Error
	 */
	public function disable_order_item( $request ) {

		$item_id = (int) $request->get_param( 'item_id' );
		$item    = new TVA_Order_Item( $item_id );

		$saved = $item->set_status( 0 )->save();

		if ( ! $saved ) {
			return new WP_Error( 'order_item_not_saved', esc_html__( 'Removing access was not possible', 'thrive-apprentice' ) );
		}

		$order          = new TVA_Order( $item->get_order_id() );
		$disabled_items = 0;

		foreach ( $order->get_order_items() as $item ) {
			if ( ! $item->get_status() ) {
				$disabled_items ++;
			}
		}

		if ( count( $order->get_order_items() ) <= $disabled_items ) {
			$order->set_status( TVA_Const::STATUS_EMPTY );
			$order->save( false );
		}

		return true;
	}

	/**
	 * Adds new manual orders in DB for specified courses ids
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function add_access( $request ) {

		$user_id     = (int) $request->get_param( 'ID' );
		$product_ids = $request->get_param( 'course_ids' );

		TVA_Customer::enrol_user_to_product( $user_id, $product_ids );

		return $this->get_products( $request );
	}

	/**
	 * @return bool
	 */
	public function admin_permission_check() {
		return TVA_Product::has_access();
	}
}

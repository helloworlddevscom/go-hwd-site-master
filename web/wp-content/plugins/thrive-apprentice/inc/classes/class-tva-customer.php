<?php

use TVA\Drip\Trigger\Time_After_Purchase;

if ( ! trait_exists( '\TVA\Drip\Schedule\Utils', false ) ) {
	require_once TVA_Const::plugin_path() . '/inc/drip/schedule/trait-utils.php';
}

/**
 * Class TVA_Customer Model
 * - WP_User wrapper which has specific properties for TA
 * - ThriveCart Customer
 * - SendOwl Customer
 */
class TVA_Customer implements JsonSerializable {

	use \TVA\Drip\Schedule\Utils;

	/**
	 * @var WP_User
	 */
	protected $_user;

	/**
	 * @var string
	 */
	static protected $_admin_url;

	/**
	 * @var null|integer[]
	 */
	private $_purchased_item_ids;

	/**
	 * @var null|array
	 */
	private $_learned_lessons;

	/**
	 * Holds an array with timestamps for the first time the first lesson is accessed from a course
	 * It is used in a specific drip trigger
	 *
	 * @var null|array
	 */
	private $_course_begin_timestamps;

	/**
	 * TVA_Customer constructor.
	 *
	 * @param int|WP_User $data
	 */
	public function __construct( $data ) {

		if ( is_numeric( $data ) ) {
			$this->_user = new WP_User( (int) $data );
		} elseif ( $data instanceof WP_User ) {
			$this->_user = $data;
		} else {
			$this->_user = new WP_User( get_current_user_id() );
		}
	}

	/**
	 * Returns the logged in customer ID
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->_user->ID;
	}

	/**
	 * Returns the WP_User object
	 *
	 * @return WP_User
	 */
	public function get_user() {
		return $this->_user;
	}

	/**
	 * Return a list of customers based on a set of filters
	 *
	 * @param array $filters
	 *
	 * @return TVA_Customer[]
	 */
	public static function get_customers( $filters = array() ) {
		$items = array();
		/**
		 * Returns a list of users that satisfy the filters
		 */
		$users = get_users( $filters );

		foreach ( $users as $user ) {
			$items[] = new self( $user );
		}

		return $items;
	}

	/**
	 * Progress Labels
	 *
	 * @var array
	 */
	private $progress_labels = array();

	/**
	 * Returns the progress labels
	 *
	 * @return array
	 */
	public function get_progress_labels() {

		if ( empty( $this->progress_labels ) ) {
			$this->set_progress_labels();
		}

		return $this->progress_labels;
	}

	/**
	 * Sets the progress labels
	 */
	private function set_progress_labels() {
		$labels = \TVA_Dynamic_Labels::get( 'course_progress' );

		$this->progress_labels = array(
			\TVA_Const::TVA_COURSE_PROGRESS_NOT_STARTED => $labels['not_started']['title'],
			\TVA_Const::TVA_COURSE_PROGRESS_COMPLETED   => $labels['finished']['title'],
			\TVA_Const::TVA_COURSE_PROGRESS_IN_PROGRESS => $labels['in_progress']['title'],
			\TVA_Const::TVA_COURSE_PROGRESS_NO_ACCESS   => $labels['not_started']['title'],
		);
	}

	/**
	 * Data which is encoded at localize
	 *
	 * @return array
	 */
	public function json_serialize() {

		if ( false === $this->_user instanceof WP_User ) {
			return array();
		}

		return array(
			'ID'           => $this->_user->ID,
			'display_name' => $this->_user->display_name,
			'user_email'   => $this->_user->user_email,
			'user_login'   => $this->_user->user_login,
			'edit_url'     => $this->get_edit_url(),
			'avatar_url'   => get_avatar_url( $this->_user->ID ),
		);
	}

	/**
	 * Called on this instance has to be serialized/localized
	 *
	 * @return array
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return $this->json_serialize();
	}

	/**
	 * Retrieve the customer learned lessons
	 *
	 * @return array
	 */
	public function get_learned_lessons() {
		if ( ! isset( $this->_learned_lessons ) ) {
			$this->_learned_lessons = tva_get_learned_lessons();
		}

		return $this->_learned_lessons;
	}


	/**
	 * Returns the timestamps when the customer accessed first lesson in the course
	 * The return array is with the following form
	 *          COURSE_ID => TIMESTAMP
	 *
	 * @return array
	 */
	private function get_timestamps_for_begin_course() {
		if ( ! isset( $this->_course_begin_timestamps ) ) {
			$timestamps = get_user_meta( $this->_user->ID, 'tva_course_begin_timestamp', true );

			$this->_course_begin_timestamps = ! empty( $timestamps ) ? $timestamps : array();
		}

		return $this->_course_begin_timestamps;
	}

	/**
	 * For a specific course, returns the timestamp its first lesson it accessed
	 *
	 * @param integer $course_id
	 *
	 * @return false|DateTime|DateTimeImmutable
	 */
	public function get_begin_course_timestamp( $course_id ) {
		$timestamps = $this->get_timestamps_for_begin_course();

		return ! empty( $timestamps[ $course_id ] ) ? static::get_datetime( '@' . $timestamps[ $course_id ] ) : false;
	}

	/**
	 * Saves the timestamps in the DB and updates the timestamps cache
	 *
	 * @param integer $course_id
	 */
	public function set_begin_course_timestamp( $course_id ) {
		$this->_course_begin_timestamps               = $this->get_timestamps_for_begin_course();
		$this->_course_begin_timestamps[ $course_id ] = current_datetime()->getTimestamp();

		update_user_meta( $this->_user->ID, 'tva_course_begin_timestamp', $this->_course_begin_timestamps );
	}

	/**
	 * Populates the drip content unlocked meta with an additional post
	 *
	 * @param int $post_id
	 */
	public function set_drip_content_unlocked( $post_id ) {
		$this->_drip_content_unlocked = $this->get_drip_content_unlocked();

		if ( ! in_array( $post_id, $this->_drip_content_unlocked ) ) {
			$this->_drip_content_unlocked[] = $post_id;
		}

		update_user_meta( $this->_user->ID, 'tva_drip_content_unlocked', $this->_drip_content_unlocked );
	}

	/**
	 * Returns the drip content unlocked meta
	 *
	 * @return array
	 */
	public function get_drip_content_unlocked() {
		if ( ! isset( $this->_drip_content_unlocked ) ) {
			$unlocked = get_user_meta( $this->_user->ID, 'tva_drip_content_unlocked', true );

			$this->_drip_content_unlocked = ! empty( $unlocked ) ? $unlocked : array();
		}

		return $this->_drip_content_unlocked;
	}

	/**
	 * Retrieve the customer lerned lessons from a course
	 *
	 * @param {integer} $course_id
	 *
	 * @return array|null
	 */
	public function get_course_learned_lessons( $course_id ) {

		$this->get_learned_lessons();

		return isset( $this->_learned_lessons[ $course_id ] ) ? $this->_learned_lessons[ $course_id ] : array();
	}

	/**
	 * Computes the course progress
	 *
	 * @param TVA_Course_V2|int $course
	 *
	 * @return int
	 */
	public function get_course_progress_status( $course ) {
		if ( is_int( $course ) ) {
			$course = new TVA_Course_V2( $course );
		}

		if ( ! $course->has_access() ) {
			return TVA_Const::TVA_COURSE_PROGRESS_NO_ACCESS;
		}

		$learned_lessons_number = count( $this->get_course_learned_lessons( $course->get_id() ) );

		$lessons_number = $course->count_lessons( array( 'post_status' => 'publish' ) );

		if ( 0 === $lessons_number || 0 === $learned_lessons_number ) {
			$status = TVA_Const::TVA_COURSE_PROGRESS_NOT_STARTED;
		} elseif ( $learned_lessons_number === $lessons_number ) {
			$status = TVA_Const::TVA_COURSE_PROGRESS_COMPLETED;
		} else {
			$status = TVA_Const::TVA_COURSE_PROGRESS_IN_PROGRESS;
		}

		return $status;
	}

	/**
	 * Returns the course progress label
	 *
	 * @param TVA_Course_V2|int $course
	 *
	 * @return string
	 */
	public function get_course_progress_label( $course ) {
		$status = $this->get_course_progress_status( $course );
		$labels = $this->get_progress_labels();
		$label  = '';


		if ( isset( $labels[ $status ] ) ) {
			$label = $labels[ $status ];
		}

		return $label;
	}

	/**
	 * Returns a list of vendor item ids
	 *
	 * @param bool $force where to fetch them again from DB
	 *
	 * @return integer[]|null
	 */
	protected function _get_purchased_item_ids( $force = false ) {

		if ( null === $this->_purchased_item_ids || true === $force ) {
			$this->_purchased_item_ids = TVA_Order_Item::get_purchased_items(
				array(
					'user_id' => (int) $this->_user->ID,
				)
			);
		}

		return $this->_purchased_item_ids;
	}

	/**
	 * List of course IDs user has access to for buying a ThriveCart product(s)
	 *
	 * @return integer[]
	 */
	public function get_thrivecart_courses() {

		return TVA_Order_Item::get_purchased_items(
			array(
				'user_id' => $this->_user->ID,
				'gateway' => TVA_Const::THRIVECART_GATEWAY,
			)
		);
	}

	/**
	 * All SendOwl Simple Product IDs user bought
	 *
	 * @return integer[]
	 */
	public function get_sendowl_products() {

		$intersect = array_intersect( $this->_get_purchased_item_ids(), TVA_SendOwl::get_products_ids() );

		return array_values( $intersect );
	}

	/**
	 * All SendOwl Bundle Product IDs user bought
	 *
	 * @return integer[]
	 */
	public function get_sendowl_bundles() {

		$intersect = array_intersect( $this->_get_purchased_item_ids(), TVA_SendOwl::get_bundle_ids() );

		return array_values( $intersect );
	}

	/**
	 * Gets a unique list of purchased/assigned bundles
	 */
	public function get_course_bundles() {

		$intersect = array_intersect( $this->_get_purchased_item_ids(), TVA_Course_Bundles_Manager::get_all_bundle_numbers() );

		return array_values( $intersect );
	}

	/**
	 * Returns the edit user for current user
	 *
	 * @return string
	 */
	public function get_edit_url() {

		$admin_url = $this->_get_admin_url();

		return add_query_arg( 'user_id', $this->_user->ID, $admin_url );
	}

	/**
	 * Lazy loading for admin user
	 *
	 * @return string
	 */
	protected function _get_admin_url() {

		if ( ! self::$_admin_url ) {
			self::$_admin_url = self_admin_url( 'user-edit.php' );
		}

		return self::$_admin_url;
	}

	/**
	 * Fetches a list of users from DB based on orders
	 * - usually users are being made by ThriveCart and SendOwl
	 *
	 * @param array $args
	 * @param bool  $count
	 *
	 * @return TVA_Customer[]|int
	 */
	public static function get_list( $args = array(), $count = false ) {

		global $wpdb;

		$defaults = array(
			'offset' => 0,
			'limit'  => class_exists( 'TVA_Admin', false ) ? TVA_Admin::ITEMS_PER_PAGE : 10,
		);

		$args = array_merge( $defaults, $args );

		$offset            = (int) $args['offset'];
		$limit             = (int) $args['limit'];
		$users_table       = $wpdb->base_prefix . 'users';
		$orders_table      = $wpdb->prefix . 'tva_orders';
		$order_items_table = $wpdb->prefix . 'tva_order_items';
		$usermeta_table    = $wpdb->base_prefix . 'usermeta';
		$join              = '';

		$params = array();

		if ( empty( $args['product_id'] ) ) {
			$where = 'WHERE orders.status IS NOT NULL';
		} else {
			$where    = 'WHERE orders.status = %d';
			$params[] = TVA_Const::STATUS_COMPLETED;
		}

		if ( ! empty( $args['s'] ) ) {
			$where .= " AND ( users.display_name LIKE '%%%s%%' OR users.user_email LIKE '%%%s%%' ) ";

			$params[] = $args['s'];
			$params[] = $args['s'];
		}

		if ( ! empty( $args['product_id'] ) ) {
			$where .= ' AND order_items.product_id = %d';

			$params[] = (int) $args['product_id'];
		}

		if ( is_multisite() ) {
			$where .= " AND $usermeta_table.meta_key = '$wpdb->prefix" . 'capabilities\'';
			$join  .= "INNER JOIN $usermeta_table ON users.ID = $usermeta_table.user_id";
		}

		$sql = "SELECT " . ( $count ? "count(DISTINCT users.ID) as count" : "DISTINCT users.ID" ) . " FROM $orders_table AS orders
        		INNER JOIN $users_table AS users ON users.ID = orders.user_id
        		LEFT JOIN $order_items_table AS order_items ON orders.ID = order_items.order_id
        		$join
				$where
				ORDER BY users.ID DESC
				";

		$limit_sql = '';

		if ( ! $count ) {
			$limit_sql = 'LIMIT %d , %d';
			$params[]  = $offset;
			$params[]  = $limit;
		}

		$sql .= $limit_sql;

		$results = $wpdb->get_col( empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ) );

		if ( $count ) {
			return $results[0];
		}

		$users = array();
		foreach ( $results as $item ) {
			$user = new TVA_Customer( $item );

			$users[] = $user;
		}

		return $users;
	}

	/**
	 * Fetches a list of users from DB who do not have any products
	 * - usually users are being made by ThriveCart and SendOwl
	 *
	 * @param array $args
	 * @param bool  $count
	 *
	 * @return TVA_Customer[]|int
	 */
	public static function get_customers_with_no_products( $args = array(), $count = false ) {

		global $wpdb;

		$defaults = array(
			'offset' => 0,
			'limit'  => class_exists( 'TVA_Admin', false ) ? TVA_Admin::ITEMS_PER_PAGE : 10,
		);

		$args = array_merge( $defaults, $args );

		$offset            = (int) $args['offset'];
		$limit             = (int) $args['limit'];
		$users_table       = $wpdb->base_prefix . 'users';
		$orders_table      = $wpdb->prefix . 'tva_orders';
		$order_items_table = $wpdb->prefix . 'tva_order_items';
		$usermeta_table    = $wpdb->base_prefix . 'usermeta';
		$join              = '';

		$where  = '';
		$params = array();

		if ( ! empty( $args['s'] ) ) {
			$where .= " AND ( users.display_name LIKE '%%%s%%' OR users.user_email LIKE '%%%s%%' ) ";

			$params[] = $args['s'];
			$params[] = $args['s'];
		}

		if ( is_multisite() ) {
			$where .= " AND $usermeta_table.meta_key = '$wpdb->prefix" . 'capabilities\'';
			$join  .= "INNER JOIN $usermeta_table ON users.ID = $usermeta_table.user_id";
		}

		$sql = "SELECT " . ( $count ? 'count(DISTINCT users.ID) as count' : "DISTINCT users.ID" ) . " FROM $orders_table AS orders
        		INNER JOIN $users_table AS users ON users.ID = orders.user_id
        		LEFT JOIN $order_items_table AS order_items ON orders.ID = order_items.order_id
        		$join
        		WHERE ( orders.status = 4 OR orders.status = 2 OR orders.status = 0 ) AND orders.user_id NOT IN 
        		(
        		    SELECT DISTINCT user_id FROM $orders_table AS orders
        		    INNER JOIN $users_table AS users ON users.ID = orders.user_id
        		    LEFT JOIN $order_items_table AS order_items ON orders.ID = order_items.order_id
                    WHERE orders.status = 1
                )
				$where
				ORDER BY users.ID DESC
				";

		$limit_sql = '';

		if ( ! $count ) {
			$limit_sql = 'LIMIT %d , %d';
			$params[]  = $offset;
			$params[]  = $limit;
		}

		$sql .= $limit_sql;

		$results = $wpdb->get_col( empty( $params ) ? $sql : $wpdb->prepare( $sql, $params ) );

		if ( $count ) {
			return $results[0];
		}

		$users = array();
		foreach ( $results as $item ) {
			$users[] = new TVA_Customer( $item );
		}

		return $users;
	}

	public function trigger_product_received_access( $products ) {
		foreach ( $products as $product ) {
			do_action( 'tva_user_receives_product_access', $this->_user, $product );
		}
	}

	/**
	 * Triggered when a user makes a Thrive Apprentice purchase
	 *
	 * @param $order
	 */
	public function trigger_purchase( $order ) {
		if ( is_a( $order, 'TVA_Order' ) ) {
			foreach ( $order->get_order_items() as $order_item ) {
				do_action( 'tva_purchase', $this->_user, $order_item );
			}
		} elseif ( is_a( $order, 'TVA_Order_Item' ) ) {
			do_action( 'tva_purchase', $this->_user, $order );
		}
	}

	/**
	 * Fires a do_action tva_user_course_purchase when a user buys access to a course
	 * - SendOwl orders
	 * - WooCommerce orders
	 * - ThriveCart orders
	 *
	 * TODO: we need to refactor this logic.
	 * - remove course logic from this function
	 *
	 * @param TVA_Order $order
	 * @param string    $initiator
	 */
	public function trigger_course_purchase( $order, $initiator = '' ) {

		/**
		 * @deprecated This is deprecated. We do not have course logic anymore
		 *             If this is no longer user we should remove this hook
		 */
		do_action( 'tva_user_course_purchase', $this->_user, $order, $initiator );

		foreach ( $order->get_order_items() as $order_item ) {
			/**
			 * Special case for sendowl
			 *
			 * Sendowl product IDs can link to different apprentice products,
			 * Therefore we need this loop here.
			 */
			$products = $order->is_sendowl() ? TVA_Sendowl_Manager::get_products_that_have_protection( (int) $order_item->get_product_id() ) : array( new \TVA\Product( (int) $order_item->get_product_id() ) );

			foreach ( $products as $product ) {
				$courses   = $product->get_courses();
				$campaigns = array();

				foreach ( $courses as $course ) {
					$campaign = $product->get_drip_campaign_for_course( $course );

					if ( $campaign instanceof \TVA\Drip\Campaign ) {
						$campaigns[] = $campaign;
					}
				}

				foreach ( $campaigns as $campaign ) {
					$post_ids = $campaign->get_posts_with_trigger( Time_After_Purchase::NAME );
					foreach ( $post_ids as $post_id ) {
						$campaign->get_trigger_for_post( $post_id, Time_After_Purchase::NAME )
								 ->schedule_event( $product->get_id(), $post_id, $order->get_user_id() ); // no $from_date parameter - the purchase event is occurring right now
					}
				}
			}
		}
	}

	/**
	 * On user register check the courses that the user might get access to
	 * based on WordPress rules and trigger a user enrolment action
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public static function on_user_register( $user_id ) {

		$user = get_user_by( 'ID', (int) $user_id );

		if ( false === $user instanceof WP_User ) {
			return array();
		}

		$protected_products = TVA\Product::get_protected_products_by_integration( 'wordpress' );

		$protected_courses = TVA_Terms_Collection::make(
			tva_term_query()->get_protected_items()
		)->get_wp_protected_items();

		$courses_ids = array();

		/** @var TVA_Term_Model $item */
		foreach ( $protected_courses->get_items() as $item ) {

			$rule = current( $item->get_rules_by_integration( 'wordpress' ) );

			$matched_roles = array_filter(
				$rule['items'],
				function ( $rule_item ) use ( $user ) {

					return ! empty( $rule_item['id'] ) && in_array( $rule_item['id'], $user->roles, true );
				}
			);

			if ( ! empty( $matched_roles ) ) {
				$courses_ids[] = $item->get_id();
			}
		}
		$customer = new TVA_Customer( $user->ID );

		if ( ! empty( $protected_products ) ) {
			$customer->trigger_product_received_access( $protected_products );
		}

		return $courses_ids;
	}

	/**
	 * Gives user access to a product
	 *
	 * @param int       $user_id user has to exist
	 * @param int|array $product_id
	 *
	 * @return bool whether the user got access
	 */
	public static function enrol_user_to_product( $user_id, $product_id ) {

		$user     = get_user_by( 'ID', (int) $user_id );
		$tva_user = new TVA_User( $user_id );


		if ( false === $user instanceof WP_User || empty( $product_id ) ) {
			return false;
		}

		if ( ! is_array( $product_id ) ) {
			$product_id = array( $product_id );
		}

		$new_products = [];
		/**
		 * Enroll only in new courses
		 */
		foreach ( $product_id as $product ) {
			if ( ! $tva_user->has_bought( $product ) ) {
				/* check if product exists */
				$instance = new \TVA\Product( $product );
				if ( $instance->get_id() ) {
					$new_products[] = $product;
				}
			}
		}

		if ( ! empty( $new_products ) ) {
			TVA_Customer_Manager::create_order_for_customer(
				$user,
				'course_ids',
				$new_products,
				array(
					'gateway' => TVA_Const::MANUAL_GATEWAY,
				)
			);
		}

		return true;
	}

	/**
	 * Removes user access from a product
	 *
	 * @param int|WP_User      $user
	 * @param int|\TVA\Product $course
	 *
	 * @return bool
	 */
	public static function remove_user_from_product( $user, $product ) {

		if ( false === $user instanceof WP_User ) {
			$user = get_user_by( 'ID', (int) $user );
		}

		if ( false === $product instanceof \TVA\Product ) {
			$product = new \TVA\Product( (int) $product );
		}

		if ( ! $user || ! $product ) {
			return false;
		}

		tva_access_manager()
			->set_tva_user( $user )
			->set_user( $user )
			->set_product( $product );

		$has_access = tva_access_manager()->check_rules();

		//while user still has access to the course, try to disable all orders
		while ( $has_access ) {

			$integration = tva_access_manager()->get_allowed_integration();

			//if user has a specific role and the course is protected by this specific role
			//the user cannot be removed from the course
			if ( true === $integration instanceof TVA_WP_Integration ) {
				$has_access = false;
				continue;
			}

			//deactivate order item
			$order_item = $integration->get_order_item();
			if ( true === $order_item instanceof TVA_Order_Item ) {
				$order_item->set_status( 0 )->save();
			}

			$order          = $integration->get_order();
			$disabled_items = 0;

			if ( true === $order instanceof TVA_Order ) {
				foreach ( $order->get_order_items() as $item ) {
					if ( 0 === $item->get_status() ) {
						$disabled_items ++;
					}
				}
				if ( count( $order->get_order_items() ) <= $disabled_items ) {
					$order->set_status( TVA_Const::STATUS_EMPTY )->save( false );
				}
			}

			$has_access = tva_access_manager()->check_rules();
		}

		return true;
	}

}

/**
 * Returns an instance of TVA_Customer of the logged in user
 *
 * @return TVA_Customer
 */
function tva_customer() {
	global $tva_customer;

	/**
	 * if we have a customer then return it
	 */
	if ( $tva_customer instanceof TVA_Customer ) {
		return $tva_customer;
	}

	/**
	 * After WP is fully loaded
	 */
	add_action(
		'wp_loaded',
		function () {
			global $tva_customer;

			$tva_customer = new TVA_Customer( get_current_user_id() );
		}
	);

	return $tva_customer;
}

tva_customer();

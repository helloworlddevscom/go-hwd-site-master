<?php
/**
 * Created by PhpStorm.
 * User: dan bilauca
 * Date: 15-Apr-19
 * Time: 11:11 AM
 */

/**
 * Class TVA_Integrations_Manager
 * - global access
 * - manages access integrations only for integrations that can be instantiated
 */
class TVA_Integrations_Manager {

	/**
	 * @var array list of integration slugs which are supported by TA
	 */
	private $_integrations_names
		= array(
			'sendowl_product',
			'sendowl_bundle',
			'wishlist',
			'memberpress',
			'membermouse',
			'membermouse_bundle',
			'wordpress',
			'thrivecart',
			'manual',
			'course_bundle',
			'woocommerce',
		);

	/**
	 * @var array of TVA_Integration instances
	 */
	protected $_integrations_instances = array();

	public function __construct() {

		$this->hooks();
	}

	public function hooks() {

		add_filter( 'tva_admin_localize', array( $this, 'admin_localize' ) );
		add_action( 'wp_loaded', array( $this, 'init_integrations' ) );
	}

	/**
	 * Instantiate all integrations based on what is defined in $_integrations_names
	 * - for each integration _init_{slug} exists
	 */
	public function init_integrations() {

		foreach ( $this->_integrations_names as $name ) {

			$method_name = '_init_' . $name;

			/** @var TVA_Integration $integration */
			if ( method_exists( $this, $method_name ) ) {
				$integration = $this->$method_name();
			}

			if ( isset( $integration ) && true === $integration instanceof TVA_Integration ) {
				$this->_integrations_instances[ $integration->get_slug() ] = $integration;
			}
		}
	}

	/**
	 * Initialize WordPress Integration
	 * - used for access to resources
	 *
	 * @return TVA_WP_Integration
	 */
	private function _init_wordpress() {

		return new TVA_WP_Integration( 'wordpress', 'WordPress Role' );
	}

	/**
	 * @return TVA_WL_Integration|TVA_Unknown_Integration
	 */
	private function _init_wishlist() {
		try {
			return new TVA_WL_Integration( 'wishlist', 'WishList Membership' );
		} catch ( Exception $e ) {
			return new TVA_Unknown_Integration( 'wishlist', 'WishList Membership' );
		}
	}

	/**
	 * @return TVA_SendOwl_Product_Integration|TVA_Unknown_Integration
	 */
	private function _init_sendowl_product() {

		$class_name = TVA_SendOwl::is_connected() ? 'TVA_SendOwl_Product_Integration' : 'TVA_Unknown_Integration';

		return new $class_name( 'sendowl_product', 'SendOwl Product' );
	}

	/**
	 * @return TVA_Woocommerce_Integration
	 */
	private function _init_woocommerce() {
		return new TVA_Woocommerce_Integration( 'woocommerce', 'WooCommerce Product' );
	}

	/**
	 * @return TVA_SendOwl_Bundle_Integration|TVA_Unknown_Integration
	 */
	private function _init_sendowl_bundle() {

		$class_name = TVA_SendOwl::is_connected() ? 'TVA_SendOwl_Bundle_Integration' : 'TVA_Unknown_Integration';

		return new $class_name( 'sendowl_bundle', 'SendOwl Bundle' );
	}

	/**
	 * @return TVA_MemberPress_Integration|TVA_Unknown_Integration
	 */
	private function _init_memberpress() {

		$class_name = class_exists( 'MeprProduct', false ) ? 'TVA_MemberPress_Integration' : 'TVA_Unknown_Integration';

		return new $class_name( 'memberpress', 'MemberPress Membership' );
	}

	/**
	 * @return TVA_Manual_Integration
	 */
	private function _init_manual() {

		return new TVA_Manual_Integration( 'manual', 'Manual' );
	}

	/**
	 * @return TVA_Membermouse_Integration|TVA_Unknown_Integration
	 */
	private function _init_membermouse() {

		$class_name = class_exists( 'MM_MembershipLevel', true ) ? 'TVA_Membermouse_Integration' : 'TVA_Unknown_Integration';

		return new $class_name( 'membermouse', 'MemberMouse Membership' );
	}

	/**
	 * @return TVA_Membermouse_Bundle_Integration|TVA_Unknown_Integration
	 */
	private function _init_membermouse_bundle() {

		$class_name = class_exists( 'MM_Bundle', true ) ? 'TVA_Membermouse_Bundle_Integration' : 'TVA_Unknown_Integration';

		return new $class_name( 'membermouse_bundle', 'MemberMouse Bundle' );
	}

	/**
	 * @return TVA_ThriveCart_Integration
	 */
	private function _init_thrivecart() {

		return new TVA_ThriveCart_Integration( 'thrivecart', 'ThriveCart Product' );
	}

	/**
	 * @return TVA_Course_Bundle_Integration
	 */
	private function _init_course_bundle() {

		return new TVA_Course_Bundle_Integration( 'course_bundle', 'Course Bundle' );
	}

	/**
	 * @param $slug
	 *
	 * @return TVA_Integration|null
	 */
	public function get_integration( $slug ) {

		$integration = null;

		if ( isset( $this->_integrations_instances[ $slug ] ) && $this->_integrations_instances[ $slug ] instanceof TVA_Integration ) {
			$integration = $this->_integrations_instances[ $slug ];
		}

		return $integration;
	}

	/**
	 * Push access_integrations to $data to be localized
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function admin_localize( $data ) {

		$access_integrations = array();

		/** @var TVA_Integration $integration */

		foreach ( $this->_integrations_instances as $integration ) {

			/**
			 * Do not localize Unknown Integrations: deactivated membership plugins
			 */
			if ( $integration->get_slug() === 'manual'
				 || $integration instanceof TVA_Course_Bundle_Integration
				 || $integration instanceof TVA_Unknown_Integration
			) {
				continue;
			}

			$integration_data          = array();
			$integration_data['slug']  = $integration->get_slug();
			$integration_data['label'] = $integration->get_label();
			$integration_data['items'] = $integration->get_items( true );
			$integration_data['allow'] = $integration->allow();

			$access_integrations[] = $integration_data;
		}

		$data['access_integrations'] = $access_integrations;

		return $data;
	}

	/**
	 * Gets all rules for all integrations of a Course
	 * - old metas are checked for backwards compatibility
	 *
	 * @param $course_or_product TVA_Course|\TVA\Product
	 *
	 * @return array
	 */
	public function get_rules( $course_or_product ) {

		if ( false === $course_or_product instanceof TVA_Course && false === $course_or_product instanceof TVA_Course_V2 && false === $course_or_product instanceof \TVA\Product ) {
			return array();
		}

		$rules    = array();
		$db_rules = get_term_meta( $course_or_product->get_id(), 'tva_rules', true );

		if ( is_array( $db_rules ) ) {

			foreach ( $db_rules as $key => $rule ) {

				if ( empty( $rule['integration'] ) ) {
					/**
					 * Security check: this should not happen however. Every rule has an integration index and empty rules do not exist
					 */
					continue;
				}

				if ( isset( $this->_integrations_instances[ $rule['integration'] ] ) ) {
					$rules[] = $rule;
				}
			}
		}

		/** @var TVA_Integration $integration */
		foreach ( $this->_integrations_instances as $integration ) {
			$old_rule = $integration->get_old_rule( $course_or_product );
			$integration->append_rule( $old_rule, $rules );
		}

		return $rules;
	}

	/**
	 * Saves access restriction rules for a course
	 *
	 * @param int   $product_id
	 * @param array $rules
	 *
	 * @return bool
	 */
	public function save_rules( $product_id, $rules ) {

		$updated    = false;
		$product_id = (int) $product_id;

		if ( false === $this->_has_rule( $rules, 'membermouse' ) ) {
			$mm_membership = $this->_init_membermouse();
			$mm_membership->before_saving_rule( $product_id, array() );
		}

		if ( false === $this->_has_rule( $rules, 'membermouse_bundle' ) ) {
			$mm_bundle = $this->_init_membermouse_bundle();
			$mm_bundle->before_saving_rule( $product_id, array() );
		}

		if ( $product_id ) {

			foreach ( $rules as $rule ) {
				$integration = $this->get_integration( $rule['integration'] );
				$integration->before_saving_rule( $product_id, $rule );
				$integration->remove_old_rule( $product_id );
			}

			$result  = \TVA\Product::update_rules( $product_id, $rules );
			$updated = is_int( $result ) || $result === true;
		}

		return $updated;
	}

	/**
	 * Checks if in the $rules array exists a rule for an $integration
	 *
	 * @param array  $rules
	 * @param string $integration slug
	 *
	 * @return bool
	 */
	protected function _has_rule( $rules, $integration ) {

		if ( false === is_array( $rules ) || false === is_string( $integration ) ) {
			return false;
		}

		$_has = false;

		foreach ( $rules as $rule ) {
			if ( $rule['integration'] === $integration ) {
				$_has = true;
				break;
			}
		}

		return $_has;
	}

	/**
	 * Try to get the integration instance from the 1st rule
	 * - or gets the first integration from array of instances
	 * - does not return TVA_WP_Integration, if it'll be the case, to let lesson template display login form
	 *
	 * @param TVA_Course|\TVA\Product $course_or_product
	 *
	 * @return TVA_Integration|null
	 */
	public function get_fallback_integration( $course_or_product = null ) {

		$integration = null;

		if ( $course_or_product instanceof TVA_Course || $course_or_product instanceof \TVA\Product ) {
			$rules = $this->get_rules( $course_or_product );
		}

		if ( ! empty( $rules ) && ! empty( $this->_integrations_instances ) && isset( $this->_integrations_instances[ $rules[0]['integration'] ] ) ) {
			$integration = $this->_integrations_instances[ $rules[0]['integration'] ];
		}

		/**
		 * take the 1st integration in the list if a specific one could not be determined
		 */
		if ( ! isset( $integration ) && ! empty( $this->_integrations_instances ) ) {
			$integration = reset( $this->_integrations_instances );
		}

		return $integration instanceof TVA_WP_Integration ? null : $integration;
	}
}

global $tva_integrations;

/**
 * Global Accessor
 *
 * @return TVA_Integrations_Manager
 */
function tva_integration_manager() {

	global $tva_integrations;

	if ( empty( $tva_integrations ) ) {
		$tva_integrations = new TVA_Integrations_Manager();
	}

	return $tva_integrations;
}

tva_integration_manager();

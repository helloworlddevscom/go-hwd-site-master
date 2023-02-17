<?php

namespace TVE\Dashboard\Automator;

use Thrive\Automator\Items\Data_Object;
use Thrive\Automator\Items\Trigger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Woo_Product_Processing
 */
class Woo_Product_Processing extends Trigger {
	/**
	 * Get the trigger identifier
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'woocommerce/product_processing';
	}

	/**
	 * Get the trigger hook
	 *
	 * @return string
	 */
	public static function get_wp_hook() {
		return 'thrive_woo_product_purchase_processing';
	}

	/**
	 * Get the trigger provided params
	 *
	 * @return array
	 */
	public static function get_provided_data_objects() {
		return array( 'woo_product_data', 'user_data' );
	}

	/**
	 * Get the number of params
	 *
	 * @return int
	 */
	public static function get_hook_params_number() {
		return 2;
	}

	/**
	 * Get the name of the app to which the hook belongs
	 *
	 * @return string
	 */
	public static function get_app_id() {
		return Woo_App::get_id();
	}

	/**
	 * Get the trigger name
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'WooCommerce product purchase processing';
	}

	/**
	 * Get the trigger description
	 *
	 * @return string
	 */
	public static function get_description() {
		return 'This trigger will be fired whenever a WooCommerce product purchase is processing';
	}

	/**
	 * Get the trigger logo
	 *
	 * @return string
	 */
	public static function get_image() {
		return 'tap-woocommerce-logo';
	}

	public function process_params( $params = array() ) {
		$data = array();

		if ( ! empty( $params[1] ) ) {

			$data_object_classes = Data_Object::get();

			list ( $product, $user ) = $params;

			$data['woo_product_data'] = empty( $data_object_classes['woo_product_data'] ) ? $product : new $data_object_classes['woo_product_data']( $product );
			$data['user_data']        = empty( $data_object_classes['user_data'] ) ? null : new $data_object_classes['user_data']( $user );
		}

		return $data;
	}

}

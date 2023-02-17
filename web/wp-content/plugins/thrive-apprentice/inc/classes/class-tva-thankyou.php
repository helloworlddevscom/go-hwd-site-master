<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 4/10/2019
 * Time: 10:39
 */

class TVA_Thankyou {

	protected $endpoint_name = 'thrv_thankyou';

	/**
	 * Name for last seen course cookie
	 */
	const LAST_PURCHASED_PRODUCT = 'last_purchased_product';

	public function __construct() {
		$this->hooks();
	}

	protected function hooks() {

		add_action( 'init', array( $this, 'add_endpoint' ) );
		add_action( 'template_redirect', array( $this, 'endpoint_redirect' ) );
		add_filter( 'tva_admin_localize', array( $this, 'admin_localize' ) );
	}

	/**
	 * add new endpoint to wp rewrite endpoints
	 * so that users would be redirected to the thankyou page set by the admin
	 */
	public function add_endpoint() {
		if ( false === TVA_SendOwl::is_connected() ) {
			return;
		}

		add_rewrite_endpoint( $this->endpoint_name, EP_ALL );
	}

	/**
	 * Generates full site url for the endpoint
	 *
	 * @return string
	 */
	public function get_endpoint_url() {

		$permalink = get_option( 'permalink_structure' );
		$glue      = $permalink ? '' : '?';

		$url = home_url( $glue . $this->endpoint_name );

		return $url;
	}

	/**
	 * Injects the thankyou endpoint into localized data
	 *
	 * @param $data array
	 *
	 * @return array
	 */
	public function admin_localize( $data ) {

		$data['data']['settings']['thankyou_endpoint'] = $this->get_endpoint_url();

		return $data;
	}

	/**
	 * redirects to checkout page set if user access the endpoint
	 */
	public function endpoint_redirect() {

		/** @var $wp_query WP_Query */
		global $wp_query;

		if ( ! isset( $wp_query->query_vars[ $this->endpoint_name ] ) || ! TVA_SendOwl::is_connected() ) {
			return;
		}

		$product_id = (int) TVA_Cookie_Manager::get_cookie( self::LAST_PURCHASED_PRODUCT );
		$url        = $this->get_redirect_url( $product_id );

		TVA_Cookie_Manager::remove_cookie( self::LAST_PURCHASED_PRODUCT );

		wp_redirect( $url );
		exit();
	}

	/**
	 * Get the redirect url after a purchase from sendowl
	 *
	 * @param int $product_id
	 *
	 * @return string
	 */
	public function get_redirect_url( $product_id = 0 ) {
		$thankyou_page_type = tva_get_settings_manager()->factory( 'thankyou_page_type' )->get_value();
		$index_url          = tva_get_settings_manager()->factory( 'index_page' )->get_link();
		$redirect_url       = empty( $index_url ) ? home_url( '/' ) : $index_url;

		if ( 0 === $product_id ) {
			return $redirect_url;
		}

		switch ( $thankyou_page_type ) {
			case 'static':
				$_url         = tva_get_settings_manager()->factory( 'thankyou_page' )->get_link();
				$redirect_url = ! empty( $_url ) ? $_url : $redirect_url;
				break;

			case 'redirect':
				$redirect_url = $this->_get_redirect_url( $product_id );

				break;

			default:
				break;
		}

		return $redirect_url;
	}

	/**
	 * Get the url where the user should be redirected after a purchase on sendowl
	 *
	 * @param $product_id
	 *
	 * @return string
	 */
	private function _get_redirect_url( $product_id ) {
		/** @var TVA_Product_Model $product */
		$product      = TVA_Products_Collection::make( TVA_Sendowl_Manager::get_products() )->get_from_key( $product_id );
		$redirect_url = tva_get_settings_manager()->factory( 'index_page' )->get_link();

		if ( ! $product instanceof TVA_Model || empty( $product->protected_terms ) ) {
			return $redirect_url;
		}

		if ( count( $product->protected_terms ) > 1 ) {

			$thankyou_page_url = tva_get_settings_manager()->factory( 'thankyou_multiple_page' )->get_link();
			$redirect_url      = ! empty( $thankyou_page_url ) ? $thankyou_page_url : $redirect_url;

			return $redirect_url;
		}

		$term_id = isset( $product->protected_terms[0] ) ? (int) $product->protected_terms[0] : 0;
		$term    = WP_Term::get_instance( $term_id );

		if ( $term instanceof WP_Error ) {
			return $redirect_url;
		}

		$lessons = TVA_Manager::get_all_lessons( $term, array( 'post_status' => 'publish' ) );

		/** @var WP_Post $lesson */
		$lesson = reset( $lessons );

		if ( 1 === count( $lessons ) && true === $lesson instanceof WP_Post ) {
			return get_permalink( $lesson->ID );
		}

		$redirect_url = get_term_link( $term_id, TVA_Const::COURSE_TAXONOMY );

		return $redirect_url instanceof WP_Error ? tva_get_settings_manager()->factory( 'index_page' )->get_link() : $redirect_url;
	}
}

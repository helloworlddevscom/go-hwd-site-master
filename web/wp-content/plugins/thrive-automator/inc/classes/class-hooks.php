<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator;

use function get_current_user_id;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Hooks
 *
 * @package Thrive\Automator
 */
class Hooks {
	const APP_ID = 'thrive-automator-admin';

	public static function init() {
		static::add_actions();
		static::add_filters();
	}

	/**
	 * Setup hooks
	 */
	public static function add_actions() {

		add_action( 'rest_api_init', [ __CLASS__, 'tap_rest_controller' ] );

		add_action( 'admin_menu', [ __CLASS__, 'admin_menu' ] );

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts' ] );

		/**
		 * Load classes when in DOING_CRON environment
		 */
		if ( defined( 'DOING_CRON' ) && DOING_CRON === true ) {
			add_action( 'init', [ 'Thrive\Automator\Admin', 'load_items' ], 10 );
		}
		/**
		 * Setup listeners for all running automations
		 */
		add_action( 'wp_loaded', [ __CLASS__, 'start_automations' ] );

		/**
		 * Action used for running delayed automations
		 */
		add_action( 'tap_delayed_automations', [
			'Thrive\Automator\Items\Automation',
			'run_delayed_automations',
		], 1, 4 );


		add_action( 'init', [ __CLASS__, 'launch_thrive_automator_init_hook' ] );

		add_action( 'profile_update', [ __CLASS__, 'launch_thrive_automator_profile_update_hook' ], 10, 2 );

		add_filter( 'tvd_automator_api_data_sets', [ __CLASS__, 'dashboard_sets' ], 10, 1 );
	}

	public static function add_filters() {
		/**
		 * Add TAP Product to Thrive Dashboard
		 */
		add_filter( 'tve_dash_installed_products', [ __CLASS__, 'add_to_dashboard' ] );

		add_filter( 'thrive_dashboard_loaded', [ __CLASS__, 'load_product_file' ] );
		add_filter( 'tve_dash_menu_products_order', [ __CLASS__, 'set_admin_menu_order' ] );

		add_filter( 'tve_dash_admin_product_menu', [ __CLASS__, 'add_dash_menu' ] );

		/* enable dashboard features */
		add_filter( 'tve_dash_features', [ __CLASS__, 'tve_dash_features' ] );
	}

	public static function admin_menu() {

		add_menu_page(
			TAP_PLUGIN_NAME,
			TAP_PLUGIN_NAME,
			'manage_options',
			'thrive_automator',
			static function () {
				echo '<div id="' . static::APP_ID . '"></div>';
			},
			TAP_PLUGIN_URL . 'icons/thrive-logo-icon.png'
		);

	}

	/**
	 * Register Automator Product to Dashboard
	 *
	 * @param $items
	 *
	 * @return mixed
	 */
	public static function add_to_dashboard( $items ) {
		if ( class_exists( 'TVE_Dash_Product_Abstract', false ) ) {
			$items[] = new TAP_Product();
		}

		return $items;
	}

	/**
	 * Load Automator Product file
	 */
	public static function load_product_file() {
		require_once TAP_PLUGIN_PATH . '/inc/classes/class-tap-product.php';
	}


	/**
	 * Add Automator to TD menu instead of top level menu item
	 *
	 * @param $menus
	 *
	 * @return mixed
	 */
	public static function add_dash_menu( $menus ) {
		remove_menu_page( TAP_SLUG );

		$menus['automator'] = array(
			'parent_slug' => 'tve_dash_section',
			'page_title'  => TAP_PLUGIN_NAME,
			'menu_title'  => TAP_PLUGIN_NAME,
			'capability'  => TAP_Product::cap(),
			'menu_slug'   => TAP_SLUG,
			'function'    => static function () {
				echo '<div id="' . static::APP_ID . '"></div>';
			},
		);


		return $menus;
	}

	/**
	 * Push the new Thrive Automator submenu item into an array at a specific order
	 *
	 * @param array $items
	 *
	 * @return array
	 */
	public static function set_admin_menu_order( $items ) {

		$items[9] = 'automator';

		return $items;
	}

	/**
	 * Enqueue scripts inside automation editor
	 */
	public static function admin_enqueue_scripts( $screen ) {
		if ( ! empty( $screen ) && $screen === Admin::PAGE_SLUG) {
			wp_enqueue_script( 'tap-admin', TAP_PLUGIN_URL . 'assets/dist/js/admin.js', [], TAP_VERSION, true );
			wp_localize_script( 'tap-admin', 'TAPAdmin', static::get_localize_data() );

			wp_enqueue_style( 'tap-font', '//fonts.googleapis.com/css?family=Roboto:200,300,400,500,600,700,800' );

			if ( file_exists( TAP_PLUGIN_PATH . 'assets/dist/css/admin.css' ) ) {
				wp_enqueue_style( 'tap-admin', TAP_PLUGIN_URL . 'assets/dist/css/admin.css', [], TAP_VERSION );
			}

			include TAP_PLUGIN_PATH . 'icons/dashboard-icons.svg';

			do_action( 'tap_output_extra_svg' );
		}

	}

	/**
	 * localize data for automation editor
	 */
	public static function get_localize_data() {
		return [
			'app_id'              => self::APP_ID,
			'routes'              => get_rest_url( get_current_blog_id(), Internal_Rest_Controller::NAMESPACE ),
			'delay_units'         => Items\Delay::dropdown_options(),
			'error_log_intervals' => Error_Log_Handler::$available_intervals,
			'nonce'               => wp_create_nonce( 'wp_rest' ),
			'log_settings'        => Error_Log_Handler::get_log_settings(),
			'timezone_offset'     => get_option( 'gmt_offset' ),
			'settings_url'        => admin_url( 'options-general.php' ),
			'debug_mode'          => defined( 'TVE_DEBUG' ) && TVE_DEBUG,
			'api_connections'     => admin_url( 'admin.php?page=tve_dash_api_connect#tve_new_api' ),
		];
	}

	/**
	 * Setup listeners for all running automations
	 */
	public static function start_automations() {
		Items\Automations::start();
	}

	/**
	 * Setup automator rest controllers
	 */
	public static function tap_rest_controller() {
		$internal = new Internal_Rest_Controller();
		$internal->register_routes();

		$integrations = new Integrations_Rest_Controller();
		$integrations->register_routes();

		$error_log = new Errorlog_Rest_Controller();
		$error_log->register_routes();
	}

	/**
	 * Setup hook for external items loading
	 */
	public static function launch_thrive_automator_init_hook() {
		$can_run      = true;
		$incompatible = [];
		if ( ! defined( 'TVE_DEBUG' ) || ! TVE_DEBUG ) {
			/* TA */
			if ( class_exists( '\TVA_Const', false ) && version_compare( \TVA_Const::PLUGIN_VERSION, '4.3.1', '<' ) ) {
				$can_run         = false;
				$incompatible [] = 'Thrive Apprentice';
			}
			/* TU */
			if ( class_exists( '\TVE_Ult_Const', false ) && version_compare( \TVE_Ult_Const::PLUGIN_VERSION, '3.7.1', '<' ) ) {
				$can_run         = false;
				$incompatible [] = 'Thrive Ultimatum';
			}
			/* TAr */
			if ( defined( 'TVE_IN_ARCHITECT' ) && defined( 'TVE_VERSION' ) && version_compare( TVE_VERSION, '3.9.1', '<' ) ) {
				$can_run         = false;
				$incompatible [] = 'Thrive Architect';
			}
			/* TD */
			if ( defined( 'TVE_DASH_VERSION' ) && (
					version_compare( TVE_DASH_VERSION, '3.7.1', '<' ) &&
					! preg_match( '/0\.\d{8,}/', TVE_DASH_VERSION ) /* dev version on test site*/
				) ) {
				$can_run = false;
				if ( empty( $incompatible ) ) {
					/* only add TD as a last option here, if nothing else was found ( TD does not exist as a stand-alone plugin, so the message might be misleading ) */
					$incompatible [] = 'Thrive Dashboard';
				}
			}
		}

		if ( $can_run ) {
			define( 'THRIVE_AUTOMATOR_RUNNING', true );
			do_action( 'thrive_automator_init' );
		} else {
			add_action( 'admin_notices', static function () use ( $incompatible ) {
				echo sprintf(
					'<div class="notice notice-error error"><p>Warning! Thrive Automator is currently incompatible with the following Thrive plugins: %s. Please make sure all Thrive plugins are updated to their latest versions. %s</p></div>',
					implode( ', ', $incompatible ),
					'<a href="' . ( is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' ) ) . '">Manage plugins</a>'
				);
			} );
		}
	}

	/**
	 * Setup user updates own profile hook for existing trigger
	 */
	public static function launch_thrive_automator_profile_update_hook( $user_id, $old_user_data ) {
		if ( $user_id === get_current_user_id() ) {
			do_action( 'tap_user_updates_own_profile', $user_id, $old_user_data );
		}
	}

	/**
	 * Enable Api Connections card
	 *
	 * @param $features
	 *
	 * @return mixed
	 */
	public static function tve_dash_features( $features ) {
		$features['api_connections'] = true;

		return $features;
	}

	/**
	 * Enroll comment_data as data that can be used in TD for Automator actions
	 *
	 * @param $sets
	 *
	 * @return mixed
	 */
	public static function dashboard_sets( $sets ) {
		$sets[] = 'comment_data';

		return $sets;
	}

}

<?php

/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\TQB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Main entry-point for TVA-TQB integration
 */
class Main {
	/**
	 * @var Main
	 */
	private static $instance;

	/**
	 * @var boolean
	 */
	private $is_tqb_active;

	/**
	 * Singleton implementation for Main
	 *
	 * @return Main
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * Return true if Thrive Quiz Builder is active
	 *
	 * @return bool
	 */
	public function is_quiz_builder_active() {
		return $this->is_tqb_active;
	}

	/**
	 * Class constructor
	 */
	private function __construct() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		$this->is_tqb_active = is_plugin_active( 'thrive-quiz-builder/thrive-quiz-builder.php' );

		/**
		 * If the Quiz Builder plugin is active, include the dependencies
		 */
		if ( $this->is_tqb_active ) {
			require_once __DIR__ . '/class-hooks.php';
		}

		/**
		 * Add stuff that apply also when Thrive Quiz Builder plugin is not active
		 */
		$this->general_actions();
		$this->general_filters();
	}

	/**
	 * Actions that apply also when Thrive Quiz Builder plugin is not active
	 *
	 * @return void
	 */
	public function general_actions() {
		add_action( 'tva_admin_print_icons', [ $this, 'add_extra_icons' ] );
	}

	/**
	 * Filters that apply also when Thrive Quiz Builder plugin is not active
	 *
	 * @return void
	 */
	public function general_filters() {
		add_filter( 'tva_admin_localize', array( $this, 'admin_localize' ) );
	}


	/**
	 * Includes the Quiz Builder integration icons
	 *
	 * @return void
	 */
	public function add_extra_icons() {
		include \TVA_Const::plugin_path( 'tqb-bridge/assets/svg/admin-icons.svg' );
	}

	/**
	 * @param array $data
	 *
	 * @return array
	 */
	public function admin_localize( $data = [] ) {
		$data['tqb_active'] = (int) $this->is_quiz_builder_active();

		return $data;
	}
}

/**
 * @return Main
 */
function tva_tqb_integration() {
	return Main::get_instance();
}

tva_tqb_integration();

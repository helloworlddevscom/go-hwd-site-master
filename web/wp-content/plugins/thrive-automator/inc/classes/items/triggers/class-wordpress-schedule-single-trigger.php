<?php

namespace Thrive\Automator\Items;

use Thrive\Automator\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Wordpress_Schedule_Single extends Trigger {

	/**
	 * @param array $params
	 *
	 * @return array
	 */
	public function process_params( $params = [] ) {
		return [];
	}

	/**
	 * Get the trigger identifier
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'wordpress/schedule_single';
	}

	/**
	 * Get the trigger hook
	 *
	 * @return string
	 */
	public static function get_wp_hook() {
		return 'tap_run_single_event_trigger';
	}

	/**
	 * Get the trigger provided params
	 *
	 * @return array
	 */
	public static function get_provided_data_objects() {
		return [];
	}

	/**
	 * Get the number of params
	 *
	 * @return int
	 */
	public static function get_hook_params_number() {
		return 1;
	}


	/**
	 * Get the trigger name
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'Specific date and time';
	}

	/**
	 * Get the trigger description
	 *
	 * @return string
	 */
	public static function get_description() {
		return 'Trigger will be fired at the specified date and time. The timezone is inherited from the WordPress “General” settings screen.';
	}

	/**
	 * Get the trigger logo
	 *
	 * @return string
	 */
	public static function get_image() {
		return 'tap-date-time-logo';
	}

	public static function get_required_trigger_fields() {
		return [ Date_And_Time_Field::get_id() ];
	}

	public static function is_single_scheduled_event() {
		return true;
	}

	public function prepare_data( $data = [] ) {
		$value = '';
		if ( ! empty( $this->data['date_and_time'] ) ) {
			$value = strtotime( $this->data['date_and_time']['value'] . ' ' . Utils::calculate_timezone_offset() );
		}

		return $value;
	}
}

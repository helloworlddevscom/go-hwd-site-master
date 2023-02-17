<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Last_Login_Field
 */
class Last_Login_Data_Field extends Data_Field {

	/**
	 * Field name
	 */
	public static function get_name() {
		return 'Last logged in';
	}

	/**
	 * Field description
	 */
	public static function get_description() {
		return 'Filter by last login date';
	}

	/**
	 * Field input placeholder
	 */
	public static function get_placeholder() {
		return '';
	}

	public static function get_id() {
		return 'last_login';
	}

	public static function get_supported_filters() {
		return [ 'date' ];
	}

	public static function get_field_value_type() {
		return static::TYPE_DATE;
	}

	public static function get_dummy_value() {
		return '2021-09-06';
	}
}

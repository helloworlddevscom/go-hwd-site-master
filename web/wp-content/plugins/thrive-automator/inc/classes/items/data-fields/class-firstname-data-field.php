<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Username_Field
 */
class Firstname_Data_Field extends Data_Field {

	/**
	 * Field name
	 */
	public static function get_name() {
		return 'User first name';
	}

	/**
	 * Field description
	 */
	public static function get_description() {
		return 'Filter by user first name';
	}

	/**
	 * Field input placeholder
	 */
	public static function get_placeholder() {
		return '';
	}

	public static function get_id() {
		return 'first_name';
	}

	public static function get_supported_filters() {
		return [ 'string_equals' ];
	}

	public static function get_field_value_type() {
		return static::TYPE_STRING;
	}

	public static function get_dummy_value() {
		return 'john';
	}
}

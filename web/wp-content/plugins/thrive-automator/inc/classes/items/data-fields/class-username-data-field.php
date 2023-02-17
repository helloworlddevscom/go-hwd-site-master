<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Username_Field
 */
class Username_Data_Field extends Data_Field {

	/**
	 * Field name
	 */
	public static function get_name() {
		return 'WordPress username';
	}

	/**
	 * Field description
	 */
	public static function get_description() {
		return 'Filter by WordPress username';
	}

	/**
	 * Field input placeholder
	 */
	public static function get_placeholder() {
		return '';
	}

	public static function get_id() {
		return 'username';
	}

	public static function get_supported_filters() {
		return [ 'string_equals' ];
	}

	public static function get_field_value_type() {
		return static::TYPE_STRING;
	}

	public static function get_dummy_value() {
		return 'john_doe';
	}
}

<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator\Items;
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Comment_Parent_Data_Field extends Data_Field {

	public static function get_id() {
		return 'comment_parent';
	}

	public static function get_supported_filters() {
		return [ 'string_ec' ];
	}

	public static function get_name() {
		return 'Comment parent ID';
	}

	public static function get_description() {
		return 'Target by the ID of the comment parent';
	}

	public static function get_placeholder() {
		return '';
	}

	public static function get_field_value_type() {
		return static::TYPE_STRING;
	}

	public static function get_dummy_value() {
		return '34';
	}
}

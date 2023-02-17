<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator\Items;

use Thrive\Automator\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class First_Name extends Action_Field {
	public static function get_name() {
		return 'First name';
	}

	public static function get_description() {
		return 'Please enter your first name or select a dynamic value';
	}

	public static function get_placeholder() {
		return 'Please enter your first name or select a dynamic value';
	}

	public static function get_id() {
		return 'first_name';
	}

	public static function get_type() {
		return Utils::FIELD_TYPE_TEXT;
	}

	public static function get_validators() {
		return [];
	}

	public static function is_ajax_field() {
		return false;
	}

	public static function get_preview_template() {
		return 'First name: $$value';
	}

	public static function allow_dynamic_data() {
		return true;
	}
}

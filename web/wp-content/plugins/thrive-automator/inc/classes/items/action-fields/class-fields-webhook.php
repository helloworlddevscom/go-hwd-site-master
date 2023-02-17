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

class Fields_Webhook extends Action_Field {

	public static function get_name() {
		return 'Fields';
	}

	public static function get_description() {
		return 'Key value pairs.';
	}

	public static function get_placeholder() {
		return 'Send test';
	}

	public static function get_id() {
		return 'fields_webhook';
	}

	public static function get_validators() {
		return [ 'key_value_pair' ];
	}

	public static function get_type() {
		return 'key_value_pair';
	}

	public static function get_preview_template() {
		return '';
	}

	public static function allow_dynamic_data() {
		return true;
	}
}

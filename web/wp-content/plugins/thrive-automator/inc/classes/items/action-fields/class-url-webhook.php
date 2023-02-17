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

class Url_Webhook extends Action_Field {
	public static function get_name() {
		return 'Webhook URL';
	}

	public static function get_description() {
		return 'What URL should the webhook be sent to?';
	}

	public static function get_placeholder() {
		return 'Webhook URL (requires https://)';
	}

	public static function get_id() {
		return 'url_webhook';
	}

	public static function get_type() {
		return Utils::FIELD_TYPE_TEXT;
	}

	public static function get_validators() {
		return [ 'url' ];
	}

	public static function is_ajax_field() {
		return false;
	}

	public static function get_preview_template() {
		return 'Webhook URL: $$value';
	}

	public static function allow_dynamic_data() {
		return true;
	}
}

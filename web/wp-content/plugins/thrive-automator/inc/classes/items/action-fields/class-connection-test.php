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

class Connection_Test extends Action_Field {

	public static function get_name() {
		return 'Test connection';
	}

	public static function get_description() {
		return 'Test the connection.';
	}

	public static function get_placeholder() {
		return 'Send test';
	}

	public static function get_id() {
		return 'test_connection_button';
	}

	public static function get_type() {
		return 'action_test';
	}
}

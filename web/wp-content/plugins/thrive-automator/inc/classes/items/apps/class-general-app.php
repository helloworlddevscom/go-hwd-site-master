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

class General_App extends App {

	public static function get_id() {
		return 'general';
	}

	public static function get_name() {
		return 'General';
	}

	public static function get_description() {
		return 'General items';
	}

	public static function get_logo() {
		return 'tap-generic-cogs';
	}

	public static function has_access() {
		return true;
	}
}

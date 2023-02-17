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

class Wordpress_App extends App {

	public static function get_id() {
		return 'wordpress';
	}

	public static function get_name() {
		return 'WordPress';
	}

	public static function get_description() {
		return 'WordPress related items';
	}

	public static function get_logo() {
		return 'tap-wordpress-logo';
	}

	public static function has_access() {
		return true;
	}
}

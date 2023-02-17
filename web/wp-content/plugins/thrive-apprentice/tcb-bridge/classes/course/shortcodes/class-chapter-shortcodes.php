<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\Architect\Course\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

require_once __DIR__ . '/class-shortcodes.php';

class Chapter_Shortcodes extends Shortcodes {

	/**
	 * Returns the Chapter Post Type
	 *
	 * @return string
	 */
	protected function get_post_type() {
		return \TVA_Const::CHAPTER_POST_TYPE;
	}
}

return new Chapter_Shortcodes( 'chapter' );

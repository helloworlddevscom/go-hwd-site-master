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

class Lesson_Shortcodes extends Shortcodes {

	/**
	 * Returns the Lesson Post Type
	 *
	 * @return string
	 */
	protected function get_post_type() {
		return \TVA_Const::LESSON_POST_TYPE;
	}

	/*
	 * @param string $content
	 *
	 * @return mixed
	 */
	public function get_default_content( $content ) {
		return static::get_default_template( 'lesson' );
	}
}

return new Lesson_Shortcodes( 'lesson' );

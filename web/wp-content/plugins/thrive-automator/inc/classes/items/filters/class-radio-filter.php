<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Radio extends Filter {

	protected $value;

	/**
	 * Get the filter identifier
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'radio';
	}

	/**
	 * Get the filter name/label
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'Radio';
	}

	public function filter( $data ) {
		return $this->value == $data['value'];
	}

	public static function get_operators() {
		return [
			'radio' => [
				'label' => 'is one of the following',
			],
		];
	}
}

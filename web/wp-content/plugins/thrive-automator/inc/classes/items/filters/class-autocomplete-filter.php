<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Autocomplete extends Filter {

	protected $value;

	/**
	 * Get the filter identifier
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'autocomplete';
	}

	/**
	 * Get the filter name/label
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'Autocomplete';
	}

	public function filter( $data ) {
		if ( is_array( $data['value'] ) ) {
			$result = ! empty( array_intersect( $data['value'], $this->value ) );
		} else {
			$result = in_array( $data['value'], $this->value );
		}

		return $result;
	}

	public static function get_operators() {
		return [
			'autocomplete' => [
				'label' => 'is any of the following',
			],
		];
	}
}

<?php

namespace Thrive\Automator\Items;

use Thrive\Automator\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class String_Ec extends Filter {

	protected $value;

	protected $operation;

	/**
	 * Get the filter identifier
	 *
	 * @return string
	 */
	public static function get_id() {
		return 'string_ec';
	}

	/**
	 * Get the filter name/label
	 *
	 * @return string
	 */
	public static function get_name() {
		return 'String equals, exists and contains';
	}

	public function prepare_data( $data = [] ) {
		$this->operation = $data['operator'];
		$this->value     = $data['value'];
	}

	public function get_value_data() {
		return [
			'value'     => empty( $this->value ) ? null : $this->value,
			'operation' => empty( $this->operation ) ? null : $this->operation,
		];
	}

	public function filter( $data ) {
		switch ( $this->operation ) {

			case 'contains':
				$result = Utils::string_contains_items( $data['value'], $this->value );
				break;
			case 'empty':
				$result = empty( $data['value'] );
				break;

			case 'not_empty':
				$result = ! empty( $data['value'] );
				break;
			case 'equals':
				$result = $this->value == $data['value'];
				break;
			default:
				$result = false;

		}

		return $result;
	}

	public static function get_operators() {
		return [
			'equals'    => [
				'label' => 'equals',
			],
			'contains'  => [
				'label' => 'contains',
			],
			'empty'     => [
				'label' => 'is empty',
			],
			'not_empty' => [
				'label' => 'is not empty',
			],
		];
	}

}

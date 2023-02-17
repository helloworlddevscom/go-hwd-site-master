<?php

namespace Thrive\Automator\Items;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Delay {
	/**
	 * Delay object key
	 */
	static protected $key = 'delay';

	/**
	 * Actual instance value
	 */
	private $value;

	/**
	 * Actual instance time unit
	 */
	private $unit;

	/**
	 * Delay time unit options
	 */
	static private $options = [
		'minutes' => [
			'key'        => 'minutes',
			'label'      => 'Minutes',
			'multiplier' => MINUTE_IN_SECONDS,
		],
		'hours'   => [
			'key'        => 'hours',
			'label'      => 'Hours',
			'multiplier' => HOUR_IN_SECONDS,
		],
		'days'    => [
			'key'        => 'days',
			'label'      => 'Days',
			'multiplier' => DAY_IN_SECONDS,
		],
		'weeks'   => [
			'key'        => 'weeks',
			'label'      => 'Weeks',
			'multiplier' => WEEK_IN_SECONDS,
		],
		'months'  => [
			'key'        => 'months',
			'label'      => 'Months',
			'multiplier' => MONTH_IN_SECONDS,
		],
		'years'   => [
			'key'        => 'years',
			'label'      => 'Years',
			'multiplier' => YEAR_IN_SECONDS,
		],
	];

	/**
	 * Create delay instance with provided values
	 *
	 * @param array $settings
	 */
	public function __construct( $settings ) {
		$this->value = $settings['value'];
		$this->unit  = $settings['unit'];
	}

	/**
	 * Get calculated timestamp depending on instance
	 *
	 * @return int
	 */
	public function calculate() {
		return static::$options[ $this->unit ]['multiplier'] * $this->value + time();
	}

	/**
	 * Get delay unit options
	 *
	 * @return array
	 */
	public static function dropdown_options() {
		return static::$options;
	}

	/**
	 * Get object information wrapper
	 *
	 * @return array
	 */
	final public function localize_data() {
		return $this->get_info();
	}

	/**
	 * Get object information
	 *
	 * @return array
	 */
	public function get_info() {
		return [
			'id'    => static::$key,
			'value' => $this->value,
			'unit'  => $this->unit,
		];
	}


}

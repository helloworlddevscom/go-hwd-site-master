<?php

namespace Thrive\Automator\Items;

use Thrive\Automator\Traits\Automation_Item;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Trigger_Field
 *
 * for the time being trigger field is the same as action field
 * we might change this in the future
 */
abstract class Trigger_Field extends Action_Field {
	use Automation_Item;

	/**
	 * For multiple option inputs, name of the callback function called through ajax to get the options
	 * FIELD_TYPE_SELECT, FIELD_TYPE_AUTOCOMPLETE, FIELD_TYPE_CHECKBOX should have their values fetched
	 * Data format should be like array{ array{id: String|int, label: String} , ...}
	 */
	public static function get_options_callback( $trigger_id, $trigger_data ) {
		return [
			[
				'id'    => 1,
				'label' => 'Label 1',
			],
		];
	}
}

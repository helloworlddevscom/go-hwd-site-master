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

class Update_Profile_Fields extends Additional_Profile_Fields {

	public static function get_name(): string {
		return 'What would you like to update';
	}

	public static function get_id(): string {
		return 'update_profile_fields';
	}

	public static function get_options_callback( $action_id, $action_data ): array {
		$additional_fields = parent::get_options_callback( $action_id, $action_data );

		/* Basic Information Fields - only available for 'Update user' Action*/
		$basic_information_fields = array(
			'first_name' => array(
				'id'    => 'first_name',
				'label' => 'First name',
			),
			'last_name'  => array(
				'id'    => 'last_name',
				'label' => 'Last name',
			),
		);

		return array_merge( $basic_information_fields, $additional_fields );
	}
}

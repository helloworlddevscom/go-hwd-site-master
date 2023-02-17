<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator\Items;

use Thrive\Automator\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Additional_Profile_Fields extends Action_Field {

	public static function get_name(): string {
		return 'Additional profile fields';
	}

	public static function get_description(): string {
		return '';
	}

	public static function get_placeholder(): string {
		return '';
	}

	public static function get_id(): string {
		return 'additional_profile_fields';
	}

	public static function get_validators(): array {
		return [ 'key_value_pair' ];
	}

	public static function get_type(): string {
		return 'mapping_pair';
	}

	public static function get_preview_template(): string {
		return '';
	}

	public static function allow_dynamic_data(): bool {
		return true;
	}

	public static function get_options_callback( $action_id, $action_data ): array {

		/* Contact Information Fields */
		$contact_information_fields = array(
			'nickname'    => array(
				'id'    => 'nickname',
				'label' => 'Nickname',
			),
			'user_url'    => array(
				'id'    => 'user_url',
				'label' => 'Website',
			),
			'description' => array(
				'id'    => 'description',
				'label' => 'Biographical Info',
			),
		);

		$thrive_social_fields = array();

		/* Show Thrive Social Fields only when TTB is active */
		if ( Utils::is_ttb_active()) {
			/* Thrive Social URLs Fields */
			$thrive_social_fields = array(
				'fb'   => array(
					'id'    => 'tve_social_fb',
					'label' => 'Facebook Page URL',
				),
				't'    => array(
					'id'    => 'tve_social_t',
					'label' => 'Twitter Page URL',
				),
				'pin'  => array(
					'id'    => 'tve_social_pin',
					'label' => 'Pinterest Page URL',
				),
				'in'   => array(
					'id'    => 'tve_social_in',
					'label' => 'Linkedin Page URL',
				),
				'xing' => array(
					'id'    => 'tve_social_xing',
					'label' => 'Xing Page URL',
				),
				'yt'   => array(
					'id'    => 'tve_social_yt',
					'label' => 'YouTube Channel URL',
				),
				'ig'   => array(
					'id'    => 'tve_social_ig',
					'label' => 'Instagram Page URL',
				),
			);
		}

		/* ACF Fields */
		$acf_fields = array();

		if ( Utils::has_acf_plugin() ) {
			$user_role  = property_exists( $action_data, 'register_role' ) ? $action_data->register_role->value : '';
			$acf_fields = static::get_custom_fields( $user_role );
		}

		return array_merge( $contact_information_fields, $thrive_social_fields, $acf_fields );
	}

	/**
	 * Get custom fields for a certain user role. If no user role is provided, the function
	 * will return all the custom fields for all the user roles
	 *
	 * @param $role
	 *
	 * @return array
	 */
	public static function get_custom_fields( $role ): array {
		$acf_fields = array();
		$user_roles = static::get_roles( $role );

		foreach ( $user_roles as $user_role ) {
			if ( empty( $user_role['id'] ) ) {
				continue;
			}
			foreach ( Utils::get_acf_user_fields( $user_role['id'] ) as $field ) {
				if ( ! empty( $field['name'] ) ) {
					$acf_fields[ $field['name'] ] = array(
						'id'    => TAP_USER_FIELD_ACF_IDENTIFIER . $field['name'],
						'label' => $field['label'],
					);
				}
			}
		}

		return $acf_fields;
	}

	/**
	 * Get all available user roles
	 *
	 * @param $role
	 *
	 * @return array
	 */
	public static function get_roles( $role ): array {
		/* get_editable_roles only loaded in the admin sections */
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once( ABSPATH . '/wp-admin/includes/user.php' );
		}

		$editable_roles = get_editable_roles();
		$roles          = array();

		foreach ( $editable_roles as $key => $role_data ) {
			if ( empty( $role ) || $key === $role ) {
				$roles[] = array(
					'id'   => $key,
					'name' => $role_data['name'],
				);
			}
		}

		return $roles;
	}

}

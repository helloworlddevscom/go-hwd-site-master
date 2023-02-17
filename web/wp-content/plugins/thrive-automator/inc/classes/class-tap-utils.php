<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator;

use Thrive\Automator\Items\Data_Field;
use Thrive\Automator\Items\Data_Object;
use XMLWriter;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Utils
 */
class Utils {
	/**
	 * Available action field types
	 */
	const FIELD_TYPE_TEXT                = 'text';
	const FIELD_TYPE_TAGS                = 'tags';
	const FIELD_TYPE_SELECT              = 'select';
	const FIELD_TYPE_SELECT_TOGGLE       = 'select_toggle';
	const FIELD_TYPE_CHECKBOX            = 'checkbox';
	const FIELD_TYPE_RADIO               = 'radio';
	const FIELD_TYPE_AUTOCOMPLETE        = 'autocomplete';
	const FIELD_TYPE_AUTOCOMPLETE_TOGGLE = 'autocomplete_toggle';
	const FIELD_TYPE_DOUBLE_DROPDOWN     = 'double_dropdown';
	const FIELD_TYPE_MAPPING_PAIR        = 'mapping_pair';
	const FIELD_TYPE_BUTTON              = 'button';
	const FIELD_TYPE_KEY_PAIR            = 'key_value_pair';

	/**
	 * Whether a string contains
	 *
	 * @param $string
	 * @param $items
	 *
	 * @return bool
	 */
	public static function string_contains_items( $string, $items ): bool {
		if ( is_array( $items ) ) {
			$parts = $items;
		} else {
			$parts = explode( ',', $items );
		}
		$result = false;

		while ( ! $result && count( $parts ) ) {
			$result = strpos( $string, array_shift( $parts ) ) !== false;
		}

		return $result;
	}

	public static function get_rest_string_arg_data(): array {
		return [
			'type'              => 'string',
			'required'          => true,
			'validate_callback' => static function ( $param ) {
				return ! empty( $param );
			},
		];
	}

	public static function get_rest_integer_arg_data(): array {
		return [
			'type'     => 'integer',
			'required' => true,
		];
	}


	/**
	 * Safe unserialize to prevent loading classes
	 *
	 * @param $data
	 *
	 * @return false|mixed
	 */
	public static function safe_unserialize( $data ) {
		if ( ! is_serialized( $data ) ) {
			return $data;
		}

		if ( version_compare( '7.0', PHP_VERSION, '<=' ) ) {
			return unserialize( $data, array( 'allowed_classes' => false ) );
		}

		/**
		 * on php <= 5.6, we need to check if the serialized string contains an object instance
		 * some rudimentary way to check for serialized objects
		 */
		if ( ! is_string( $data ) || preg_match( '#(^|;)o:\d+:"[a-z0-9\\\_]+":\d+:#i', $data, $m ) ) {
			return false;
		}

		return unserialize( $data );
	}

	/**
	 * Check if a field type yields multiple values
	 *
	 * @param string $type
	 *
	 * @return bool
	 */
	public static function is_multiple( string $type ): bool {
		return in_array( $type, [
			static::FIELD_TYPE_AUTOCOMPLETE,
			static::FIELD_TYPE_CHECKBOX,
			static::FIELD_TYPE_SELECT,
			static::FIELD_TYPE_RADIO,
			static::FIELD_TYPE_DOUBLE_DROPDOWN,
			static::FIELD_TYPE_MAPPING_PAIR,
			static::FIELD_TYPE_AUTOCOMPLETE_TOGGLE,
			static::FIELD_TYPE_SELECT_TOGGLE,
		], true );
	}

	/**
	 * Go through all data and replace potential shortcodes
	 *
	 * @param $field
	 * @param $shortcode
	 * @param $field_value
	 *
	 * @return array|string
	 */
	public static function replace_data_shortcode( $field, $shortcode, $field_value ) {
		if ( is_array( $field ) ) {
			foreach ( $field as &$sub_data ) {
				if ( empty( $sub_data['value'] ) ) {
					$sub_data = static::replace_shortocode( $shortcode, $field_value, $sub_data );
				} else {
					$sub_data['value'] = static::replace_shortocode( $shortcode, $field_value, $sub_data['value'] );
				}
			}
		} else {
			$field = static::replace_shortocode( $shortcode, $field_value, $field );
		}

		return $field;
	}

	/**
	 * Replace a shortcode with its value
	 *
	 * @param $shortcode
	 * @param $new_value
	 * @param $prev_value
	 *
	 * @return array|string|string[]|null
	 */
	public static function replace_shortocode( $shortcode, $new_value, $prev_value ) {

		if ( is_array( $prev_value ) ) {
			return $prev_value;
		}

		$result = $prev_value;
		if ( is_array( $new_value ) ) {
			if ( strpos( $prev_value, $shortcode ) !== false ) {
				$result = $new_value;
			}
		} else {
			$result = preg_replace( "#$shortcode#", $new_value ?? '', $prev_value );
		}

		return $result;
	}

	/**
	 * Callback for array reduce in order to merge [key,value] arrays into a single one
	 *
	 * @param $carry
	 * @param $item
	 *
	 * @return mixed
	 */
	public static function flat_key_value_pairs( $carry, $item ) {
		if ( ! empty( $item['key'] ) && ! empty( $item['value'] ) ) {
			$carry[ $item['key'] ] = $item['value'];
		}

		return $carry;
	}

	/**
	 * Generate xml nodes
	 *
	 * @param XMLWriter $xml
	 * @param           $data
	 */
	public static function write_xml( XMLWriter $xml, $data ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$xml->startElement( $key );
				static::write_xml( $xml, $value );
				$xml->endElement();
				continue;
			}
			$xml->writeElement( $key, $value );
		}
	}

	/**
	 * Generate an xml format data from an array of data
	 *
	 * @param $data
	 *
	 * @return string
	 */
	public static function xml_encode( $data ): string {
		$xml = new XmlWriter();
		$xml->openMemory();
		$xml->startDocument( '1.0', 'utf-8' );
		$xml->startElement( 'root' );
		static::write_xml( $xml, $data );
		$xml->endElement();

		return $xml->outputMemory( true );
	}

	/**
	 * Get an array with week days
	 *
	 * @return array[]
	 */
	public static function get_day_options(): array {
		return [
			[
				'id'    => 1,
				'label' => __( 'Monday' ),
			],
			[
				'id'    => 2,
				'label' => __( 'Tuesday' ),
			],
			[
				'id'    => 3,
				'label' => __( 'Wednesday' ),
			],
			[
				'id'    => 4,
				'label' => __( 'Thursday' ),
			],
			[
				'id'    => 5,
				'label' => __( 'Friday' ),
			],
			[
				'id'    => 6,
				'label' => __( 'Saturday' ),
			],
			[
				'id'    => 0,
				'label' => __( 'Sunday' ),
			],
		];
	}

	/**
	 * Get an array with year months
	 *
	 * @return array[]
	 */
	public static function get_month_options(): array {
		return [
			[
				'id'    => 1,
				'label' => __( 'January' ),
			],
			[
				'id'    => 2,
				'label' => __( 'February' ),
			],
			[
				'id'    => 3,
				'label' => __( 'March' ),
			],
			[
				'id'    => 4,
				'label' => __( 'April' ),
			],
			[
				'id'    => 5,
				'label' => __( 'May' ),
			],
			[
				'id'    => 6,
				'label' => __( 'June' ),
			],
			[
				'id'    => 7,
				'label' => __( 'July' ),
			],
			[
				'id'    => 8,
				'label' => __( 'August' ),
			],
			[
				'id'    => 9,
				'label' => __( 'September' ),
			],
			[
				'id'    => 10,
				'label' => __( 'October' ),
			],
			[
				'id'    => 11,
				'label' => __( 'November' ),
			],
			[
				'id'    => 12,
				'label' => __( 'December' ),
			],
		];
	}

	public static function create_dynamic_trigger( $prefix, $id ): string {
		return $prefix . '_' . $id;
	}

	/**
	 * Update postmeta with prefixed keys
	 *
	 * @param $post_id
	 * @param $key
	 * @param $value
	 */
	public static function update_post_meta( $post_id, $key, $value ) {
		update_post_meta( $post_id, 'tap-' . $key, $value );
	}

	/**
	 * Get webhook post meta containing fields
	 *
	 * @param $webhook_id
	 *
	 * @return array|mixed|object|null
	 */
	public static function get_automator_webhook_fields( $webhook_id ) {

		$result = TAP_DB::get_automator_webhook_fields_data( $webhook_id );

		return empty( $result['fields'] ) ? [] : $result['fields'];

	}

	/**
	 * Get webhook post meta containing header fields
	 *
	 * @param $webhook_id
	 *
	 * @return array|null
	 */
	public static function get_automator_webhook_header_fields( $webhook_id ): array {

		$result = TAP_DB::get_automator_webhook_fields_data( $webhook_id );

		$headers = [];
		if ( ! empty( $result['security_headers'] ) ) {
			foreach ( $result['security_headers'] as $field ) {
				$headers[ $field['key'] ] = $field['value'];
			}
		}

		return $headers;
	}

	/**
	 * Structure webhook receive data adn identify type
	 *
	 * @param array $data
	 *
	 * @return array|null
	 */
	public static function process_webhook_structure( array $data = [] ): array {
		$result = [];
		foreach ( $data as $key => $value ) {

			if ( is_numeric( $value ) ) {
				$result[ $key ] = 'number';
			} elseif ( is_array( $value ) ) {
				$result = array_merge( $result, static::process_webhook_structure_array( $key, $value ) );
			} elseif ( is_bool( $value ) ) {
				$result[ $key ] = 'boolean';
			} elseif ( is_string( $value ) ) {
				$result[ $key ] = 'text';
			}
		}

		return $result;
	}

	/**
	 * Structure webhook multi level array keys
	 *
	 * @param       $key
	 * @param array $array
	 *
	 * @return array|null
	 */
	public static function process_webhook_structure_array( $key, array $array = [] ): array {
		$result = [];
		foreach ( $array as $array_key => $array_value ) {
			$result[ $key . '[' . $array_key . ']' ] = $array_value;
		}

		return static::process_webhook_structure( $result );
	}


	public static function replace_additional_data_shortcodes( $value, $data ) {
		$shortcode_tag = '%';
		$was_string    = false;
		if ( is_string( $value ) ) {
			$was_string = true;
			$value      = [ $value ];
		}
		foreach ( $value as &$item ) {
			if ( is_array( $data ) ) {
				/**
				 * Replace each field that might be inside the value
				 */
				foreach ( $data as $key => $data_value ) {
					$data_value = $data_value ?: '';

					if ( is_string( $item ) ) {
						$item = str_replace( $shortcode_tag . $key . $shortcode_tag, $data_value, $item );
					} elseif ( isset( $item['value'] ) ) {
						$item['value'] = str_replace( $shortcode_tag . $key . $shortcode_tag, $data_value, $item['value'] );
					}
				}
			}
		}


		return $was_string && isset( $value[0] ) ? $value[0] : $value;
	}

	public static function get_advanced_mapping_data_objects() {
		$data_objects = [];
		$fields       = Data_Field::get();
		$data_sets    = Data_Object::get();

		foreach ( $fields as $field ) {
			$primary_key = $field::primary_key();
			if ( $primary_key && isset( $data_sets[ $primary_key ] ) ) {
				$data_objects[ $data_sets[ $primary_key ]::get_id() ] = [
					'id'   => $data_sets[ $primary_key ]::get_id(),
					'name' => $data_sets[ $primary_key ]::get_nice_name() ?: $data_sets[ $primary_key ]::get_id(),
				];
			}
		}

		return $data_objects;
	}

	/**
	 * For actions that support dynamic data sets as field values, on execution this will fetch the actual values
	 *
	 * @param string $key
	 * @param mixed  $specific_value
	 *
	 * @return mixed
	 */
	public static function get_dynamic_data_object_from_automation( $key, $specific_value ) {
		if ( empty( $key ) || empty( $specific_value ) ) {
			return false;
		}

		if ( strpos( $key, 'tap-dynamic-' ) === false ) {
			return $key;
		}

		global $automation_data;

		$key = str_replace( 'tap-dynamic-', '', $key );

		$return_value = $data_object = $automation_data->get( $key );
		if ( ! empty( $data_object ) ) {
			if ( is_array( $specific_value ) ) {
				foreach ( $specific_value as $value ) {
					if ( in_array( $value, $data_object->get_fields() ) ) {
						$return_value = $data_object->get_value( $value );
					}
				}
			} else {
				$return_value = $data_object->get_value( $specific_value );
			}
		}

		return $return_value;
	}

	/**
	 * Calculate timezone offset based on the gmt_offset set in WP Settings
	 *
	 * @return string
	 */
	public static function calculate_timezone_offset(): string {
		$offset  = (float) get_option( 'gmt_offset' );
		$hours   = (int) $offset;
		$minutes = ( $offset - $hours );

		$sign     = ( $offset < 0 ) ? '-' : '+';
		$abs_hour = abs( $hours );
		$abs_mins = abs( $minutes * 60 );

		return sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
	}

	/**
	 * Check if ACF plugin is active
	 *
	 * @return bool
	 */
	public static function has_acf_plugin(): bool {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		}

		return is_plugin_active( 'advanced-custom-fields/acf.php' ) || is_plugin_active( 'advanced-custom-fields-pro/acf.php' );
	}

	/**
	 * Check if a user role has access to a certain ACF
	 *
	 * @param $user_role
	 * @param $queried_field
	 *
	 * @return bool
	 */
	public static function user_has_access_to_field( $user_role, $queried_field ): bool {
		$has_access = false;
		$fields     = static::get_acf_user_fields( $user_role );
		foreach ( $fields as $field ) {
			if ( $field['name'] === $queried_field ) {
				$has_access = true;
			}
		}

		return $has_access;
	}

	/**
	 * Get ACF fields by user role
	 *
	 * @param $user_role
	 *
	 * @return array
	 */
	public static function get_acf_user_fields( $user_role ): array {
		$fields = array();
		$args   = array( 'user_role' => $user_role[0] );
		$groups = \acf_get_field_groups( $args );

		foreach ( $groups as $group ) {
			$group_fields = array_filter( \acf_get_fields( $group ), static function ( $field ) {
				return in_array( $field['type'], array( 'text', 'textarea', 'url', 'number' ) );
			} );

			$fields = array_merge( $fields, $group_fields );
		}

		return $fields;
	}

	/**
	 * Check if TTB is active
	 *
	 * @return bool
	 */
	public static function is_ttb_active(): bool {
		return get_current_theme() === 'Thrive Theme Builder';
	}
}

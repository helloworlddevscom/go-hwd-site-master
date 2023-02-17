<?php

namespace Thrive\Automator;

use Thrive\Automator\Items\Action;
use Thrive\Automator\Items\Action_Field;
use Thrive\Automator\Items\App;
use Thrive\Automator\Items\Automation;
use Thrive\Automator\Items\Data_Field;
use Thrive\Automator\Items\Data_Object;
use Thrive\Automator\Items\Trigger;
use Thrive\Automator\Items\Trigger_Field;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

class Internal_Rest_Controller extends WP_REST_Controller {

	const NAMESPACE = 'tap/v1';

	const NO_FIELD  = 'No field was found!';
	const NO_PARAMS = 'Missing params!';
	const NO_ACTION = 'No action was found!';

	/**
	 * Registers routes for basic controller
	 */
	public function register_routes() {

		/**
		 * Routes for automations
		 */
		register_rest_route( static::NAMESPACE, '/automations', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_automations' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			),
		) );
		register_rest_route( static::NAMESPACE, '/automation/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_automation' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'id' => Utils::get_rest_integer_arg_data(),
				),
			),
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'save_automation' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'id' => Utils::get_rest_integer_arg_data(),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( __CLASS__, 'delete_automation' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'id' => Utils::get_rest_integer_arg_data(),
				),
			),
		) );

		/**
		 * Get list of all triggers
		 */
		register_rest_route( static::NAMESPACE, '/triggers', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_triggers' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		register_rest_route( static::NAMESPACE, '/triggers/(?P<trigger_id>[\S]+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'sync_trigger' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'trigger_id' => Utils::get_rest_string_arg_data(),
				),
			],
		] );

		/**
		 * Get list of all generic fields
		 */
		register_rest_route( static::NAMESPACE, '/primary_fields', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_primary_fields' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		register_rest_route( static::NAMESPACE, '/advanced_mapping_data_objects', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_advanced_mapping_data_objects' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		register_rest_route( static::NAMESPACE, '/data_objects', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_data_objects' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		register_rest_route( static::NAMESPACE, '/data_objects/(?P<id>[\S]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_data_object_options' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'id' => Utils::get_rest_string_arg_data(),
				),
			],
		] );
		/**
		 * Get a list of all field filters
		 */
		register_rest_route( static::NAMESPACE, '/filters', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_filters' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		/**
		 * Get list of all actions
		 */
		register_rest_route( static::NAMESPACE, '/actions', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_actions' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		/**
		 * Get options for fields with multiple options like autocomplete, checkbox, select
		 */
		register_rest_route( static::NAMESPACE, '/field_options', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_field_options' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			),
		) );

		/**
		 * Get options for action fields with multiple options like autocomplete, checkbox, select
		 */
		register_rest_route( static::NAMESPACE, '/action_field_options', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'get_action_field_options' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			),
		) );

		/**
		 * Get subfields for an action field
		 */
		register_rest_route( static::NAMESPACE, '/action_field_steps', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_action_subfields' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'action_id'     => Utils::get_rest_string_arg_data(),
					'current_value' => Utils::get_rest_string_arg_data(),
				),
			),
		) );

		register_rest_route( static::NAMESPACE, '/action_fields_mapping', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_action_fields_mapping' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'action_id' => Utils::get_rest_string_arg_data(),
				),
			),
		) );
		/**
		 * Get options for action fields with multiple options like autocomplete, checkbox, select
		 */
		register_rest_route( static::NAMESPACE, '/trigger_field_options', array(
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( __CLASS__, 'get_trigger_field_options' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			),
		) );

		/**
		 * Get subfields for a trigger field
		 */
		register_rest_route( static::NAMESPACE, '/trigger_field_steps', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_trigger_subfields' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
				'args'                => array(
					'trigger_id'    => Utils::get_rest_string_arg_data(),
					'current_value' => Utils::get_rest_string_arg_data(),
				),
			),
		) );


		register_rest_route( static::NAMESPACE, '/matching_actions', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_matching_actions' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );

		register_rest_route( static::NAMESPACE, '/apps', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_apps' ),
				'permission_callback' => array( __CLASS__, 'admin_permissions_check' ),
			],
		] );
	}

	/**
	 * Handle specific trigger ajax calls to get matching actions
	 */
	public static function get_matching_actions( $request ): WP_REST_Response {
		$fields = $request->get_param( 'provided_objects' );

		return new WP_REST_Response( Items\Trigger::get_trigger_matching_actions( $fields ), 200 );
	}

	/**
	 * Get options for fields with multiple options like autocomplete, checkbox, select
	 */
	public static function get_field_options( $request ) {

		$field_name = $request->get_param( 'field' );
		if ( empty( $field_name ) ) {
			return new WP_Error( 'no-results', static::NO_PARAMS );
		}

		$class = Data_Field::get_by_id( $field_name );
		if ( $class ) {
			return new WP_REST_Response( $class::get_options_callback(), 200 );
		}

		return new WP_Error( 'no-results', static::NO_FIELD );
	}

	/**
	 * Get options for action fields with multiple options like autocomplete, checkbox, select
	 */
	public static function get_action_field_options( $request ) {

		$action_id   = $request->get_param( 'action_id' );
		$field_name  = $request->get_param( 'field' );
		$parent_data = json_decode( $request->get_param( 'parent_data' ) );
		if ( empty( $action_id ) || empty( $field_name ) ) {
			return new WP_Error( 'no-results', static::NO_PARAMS );
		}

		$class = Action_Field::get_by_id( $field_name );
		if ( $class && $class::is_ajax_field() ) {
			return new WP_REST_Response( $class::get_options_callback( $action_id, $parent_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_FIELD );
	}

	/**
	 * Get subfields for an action field
	 */
	public static function get_action_subfields( $request ) {

		$action_id     = $request->get_param( 'action_id' );
		$fields        = $request->get_param( 'fields' );
		$current_value = $request->get_param( 'current_value' );
		$action_data   = json_decode( $request->get_param( 'parent_data' ) );

		$class = Action::get_by_id( $action_id );
		if ( $class ) {
			return new WP_REST_Response( $class::get_subfields( $fields, $current_value, $action_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_ACTION );
	}


	public static function get_action_fields_mapping( $request ) {
		$action_id   = $request->get_param( 'action_id' );
		$action_data = json_decode( $request->get_param( 'action_data' ) );
		$class       = Action::get_by_id( $action_id );

		if ( $class ) {
			return new WP_REST_Response( $class::get_action_mapped_fields( $action_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_ACTION );
	}

	/**
	 * Get options for action fields with multiple options like autocomplete, checkbox, select
	 */
	public static function get_trigger_field_options( $request ) {

		$trigger_id  = $request->get_param( 'trigger_id' );
		$field_name  = $request->get_param( 'field' );
		$parent_data = json_decode( $request->get_param( 'parent_data' ), false );
		if ( empty( $trigger_id ) || empty( $field_name ) ) {
			return new WP_Error( 'no-results', static::NO_PARAMS );
		}

		$class = Trigger_Field::get_by_id( $field_name );
		if ( $class && $class::is_ajax_field() ) {
			return new WP_REST_Response( $class::get_options_callback( $trigger_id, $parent_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_FIELD );
	}

	/**
	 * Get subfields for a trigger field
	 */
	public static function get_trigger_subfields( $request ) {

		$trigger_id    = $request->get_param( 'trigger_id' );
		$fields        = $request->get_param( 'fields' );
		$current_value = $request->get_param( 'current_value' );
		$action_data   = json_decode( $request->get_param( 'parent_data' ) );

		$class = Trigger::get_by_id( $trigger_id );
		if ( $class ) {
			return new WP_REST_Response( $class::get_subfields( $fields, $current_value, $action_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_ACTION );
	}

	/**
	 * Get list of all triggers
	 */
	public static function get_triggers(): WP_REST_Response {
		$apps = App::get();
		$data = [];

		foreach ( $apps as $app ) {
			$app_id          = $app::get_id();
			$triggers        = Trigger::get_all_by_app( $app_id );
			$data[ $app_id ] = [
				'label'  => $app::get_name(),
				'items'  => $triggers,
				'logo'   => $app::get_logo(),
				'app_id' => $app_id,
			];
		}

		return new WP_REST_Response( $data );
	}

	public static function get_apps(): WP_REST_Response {
		return new WP_REST_Response( Items\App::localize_all() );
	}

	public static function get_primary_fields(): WP_REST_Response {
		return new WP_REST_Response( Items\Data_Field::get_all_primary_keys() );
	}

	public static function get_advanced_mapping_data_objects(): WP_REST_Response {
		return new WP_REST_Response( Utils::get_advanced_mapping_data_objects() );
	}

	public static function get_data_object_options( $request ) {
		$object_id = $request->get_param( 'id' );
		$class     = Data_Object::get_by_id( $object_id );
		if ( $class ) {
			return new WP_REST_Response( $class::get_data_object_options(), 200 );
		}

		return new WP_Error( 'no-results', static::NO_ACTION );
	}

	public static function get_data_objects(): WP_REST_Response {
		return new WP_REST_Response( Items\Data_Object::localize_all() );
	}

	/**
	 * Get list of all triggers
	 */
	public static function get_filters(): WP_REST_Response {
		return new WP_REST_Response( Items\Filter::localize_all() );
	}

	/**
	 * Get list of all automations
	 */
	public static function get_automations(): WP_REST_Response {
		return new WP_REST_Response( Items\Automations::get_raw_data() );
	}

	/**
	 * Get list of all actions
	 */
	public static function get_actions(): WP_REST_Response {
		$apps = App::get();
		$data = [];

		foreach ( $apps as $app ) {
			$app_id          = $app::get_id();
			$actions                = Action::get_all_by_app( $app_id );
			$data[ $app_id] = [
				'label' => $app::get_name(),
				'items' => $actions,
				'logo'  => $app::get_logo(),
				'app_id' => $app_id,
			];
		}

		return new WP_REST_Response( $data );
	}

	/**
	 * Save automation model
	 */
	public static function save_automation( $request ) {
		$model = $request->get_param( 'model' );
		if ( ! empty( $model ) ) {
			return new WP_REST_Response( Items\Automation::save( $model ), 200 );
		}

		return new WP_Error( 'no-results', 'No automation was found!' );
	}

	/**
	 * Delete automation
	 */
	public static function delete_automation( $request ) {
		$id     = $request->get_param( 'id' );
		$result = Items\Automation::delete( $id );

		if ( $result ) {
			return new WP_REST_Response( true, 200 );
		}

		return new WP_Error( 'no-results', 'No automation was deleted!' );
	}

	/**
	 * Get automation model
	 */
	public static function get_automation( $request ) {
		$id         = $request->get_param( 'id' );
		$automation = new Automation( $id );
		$data       = $automation->get_frontend_data();

		if ( ! empty( $data ) ) {
			return new WP_REST_Response( $data, 200 );
		}

		return new WP_Error( 'no-results', 'No automation was found!' );
	}

	/**
	 * Setup basic permission callback
	 */
	public static function admin_permissions_check(): bool {
		return current_user_can( Admin::get_capability() );
	}

	public static function sync_trigger( $request ) {
		$trigger_id   = $request->get_param( 'trigger_id' );
		$trigger_data = $request->get_param( 'trigger_data' );

		$trigger = Trigger::get_by_id( $trigger_id );
		if ( $trigger ) {
			return new WP_REST_Response( $trigger::sync_trigger_data( $trigger_data ), 200 );
		}

		return new WP_Error( 'no-results', static::NO_ACTION );
	}

}

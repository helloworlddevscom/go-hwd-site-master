<?php

namespace Thrive\Automator\Items;

use Thrive\Automator\TAP_DB;
use Thrive\Automator\Utils;
use WP_Post;
use function Thrive\Automator\tap_limitations;
use function Thrive\Automator\tap_logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Automation
 * - handles Automation save, delete, execution
 */
class Automation {
	/**
	 * Objects represent grouped items of same type( triggers, filters, actions, delay ), as seen in the editor
	 *
	 */
	private $steps = [];

	/**
	 * Triggers from the automation are used when setting up listeners(hooks) for the automation
	 *
	 * @var Trigger[]
	 */
	private $triggers;

	/**
	 * Custom post containing automation
	 */
	private $post;

	/**
	 * Custom post type for automations
	 */
	const POST_TYPE = 'tve_automation';

	/**
	 * Create Automation array with instances of each object type( triggers, filters, actions, delay )
	 *
	 * @param int|WP_Post|array $automation
	 */
	public function __construct( $automation ) {
		if ( is_numeric( $automation ) ) {
			$automation = get_post( $automation );
		}

		if ( $automation instanceof WP_Post ) {
			$this->post = $automation;
		}

		if ( is_array( $automation ) ) {
			$this->post = new WP_Post( $automation );
		}

		if ( ! $this->is_post_valid() ) {
			return;
		}

		$steps = Utils::safe_unserialize( $automation->post_content );

		foreach ( $steps as $index => $step ) {
			$object_instances = [];
			switch ( $step['type'] ) {

				case 'triggers':
					foreach ( $step['data'] as $id => $data ) {
						$object_instances[ $id ] = $this->triggers[ $id ] = Trigger::get_instance( $data['key'], $data, $this->post->ID );
					}

					break;

				case 'filters':
					$object_instances = static::get_filter_instances( $step['data'], $object_instances, $this->post->ID );
					break;
				case 'delay':
					foreach ( $step['data'] as $id => $delay ) {
						if ( ! empty( $delay ) ) {
							$object_instances[ $id ] = new Delay( $delay );
						}
					}

					break;

				case 'actions':
					foreach ( $step['data'] as $id => $data ) {
						$object_instances[ $id ] = Action::get_instance( $data['key'], $data, $this->post->ID );
					}
					break;
				default:
					$object_instances = null;
			}
			$this->steps[ $index ]['type']  = $step['type'];
			$this->steps[ $index ]['items'] = $object_instances;
			$this->steps[ $index ]['id']    = $step['id'];
		}
	}

	public static function get_filter_instances( $filters, $container, $aut_id = 0 ) {
		foreach ( $filters as $id => $data ) {
			if ( ! empty( $data ) ) {
				$container[ $id ]['filter'] = Filter::get_instance( $data['filter'], $data, $aut_id );
				/* get data objects for the current filter. we can have multiple triggers with different data objects and also multiple filters. */
				$container[ $id ]['data_object'] = $data['data_object'];
				/* fields that we compare in the filter */
				$field                     = Data_Field::get_instance( $data['field'], $data );
				$container[ $id ]['field'] = empty( $field ) ? $data['field'] : $field;
			}

		}

		return $container;
	}

	/**
	 * Format automation information for frontend
	 */
	public function get_frontend_data() {
		if ( ! $this->is_post_valid() ) {
			return [];
		}
		$steps            = [];
		$provided_objects = [];
		$content          = Utils::safe_unserialize( $this->post->post_content );
		foreach ( $this->steps as $index => $object_instances ) {
			$instances = [];

			if ( ! empty( $object_instances['items'] ) ) {
				foreach ( $object_instances['items'] as $id => $instance ) {
					if ( ! empty( $instance ) ) {
						if ( is_array( $instance ) ) {
							$data = [];
						} else {
							$data = $instance->get_info();
						}

						$data = array_merge( $content[ $index ]['data'][ $id ], $data );
						/**
						 * Sync the trigger so any custom mappings are available
						 */
						if ( $content[ $index ]['type'] === 'triggers' ) {
							$data = $instance->sync_trigger_data( $data );
						}
						if ( $content[ $index ]['type'] === 'actions' ) {
							$data = $instance->sync_action_data( $data );
						}

						if ( ! empty( $data['provided_params'] ) ) {
							$provided_objects = array_merge( $provided_objects, $data['provided_params'] );
						}
						$instances[] = $data;
					}
				}
			}
			if ( ! empty( $instances ) ) {
				$step_data = [
					'type'  => $content[ $index ]['type'],
					'saved' => true,
					'data'  => in_array( $content[ $index ]['type'], [
						'actions',
						'delay',
					] ) ? $instances[0] : $instances,
				];

				if ( in_array( $content[ $index ]['type'], [ 'triggers', 'actions' ], true ) ) {
					$step_data['data_objects']     = array_values( array_unique( $provided_objects ) );
					$step_data['matching_actions'] = Trigger::get_trigger_matching_actions( $provided_objects );
				}

				$steps[] = $step_data;
			}
		}

		$meta = TAP_DB::get_automator_post_meta( $this->post->ID );

		return [
			'id'     => $this->post->ID,
			'title'  => $this->post->post_title,
			'status' => $this->post->post_status === 'publish',
			'steps'  => $steps,
			'meta'   => $meta,
		];
	}

	/**
	 * Save automation model
	 *
	 * @param array $model
	 *
	 * @return int|false
	 */
	public static function save( $model ) {
		$post = array(
			'post_type'   => static::POST_TYPE,
			'post_status' => $model['status'],
		);

		if ( ! empty( $model['content'] ) ) {
			$post['post_content'] = serialize( $model['content'] );
		}
		if ( ! empty( $model['title'] ) ) {
			$post['post_title'] = $model['title'];
		}

		if ( ! empty( $model['id'] ) ) {
			$item = get_post( $model['id'] );
			if ( $item && get_post_type( $item ) === static::POST_TYPE ) {
				static::unschedule_events( $item->ID );
				$post['ID'] = $model['id'];
				$id         = wp_update_post( $post );
			}
			/**
			 * Delete the execution log for automations if settings are changed
			 */
			$meta = TAP_DB::get_automator_post_meta( $model['id'] );
			if ( ! empty( $meta['limitations'] ) && ! empty( $model['meta'] ) && $meta['limitations'] != $model['meta']['limitations'] ) {
				tap_limitations( $model['id'] )->delete_automation_entries();
			}
		} else {
			$id = wp_insert_post( $post );
		}

		if ( empty( $id ) || is_wp_error( $id ) || $id == 0 ) {
			return false;
		}

		if ( ! empty( $model['meta'] ) ) {
			foreach ( $model['meta'] as $key => $value ) {

				Utils::update_post_meta( $id, $key, $value );
			}
		}

		self::schedule_events( $id );

		return $id;
	}


	final public static function unschedule_events( int $automation_id ) {
		$post  = get_post( $automation_id );
		$steps = Utils::safe_unserialize( $post->post_content );

		if ( ! empty( $steps[0]['data'] ) ) {
			foreach ( $steps[0]['data'] as $id => $trigger_data ) {

				$trigger = Trigger::get_instance( $trigger_data['key'], $trigger_data );

				if ( ! empty( $trigger ) && $trigger::is_single_scheduled_event() ) {

					wp_unschedule_event( $trigger->prepare_data(), $trigger::get_wp_hook(), [
						$post,
					] );

				}
			}
		}
	}

	final public static function schedule_events( int $automation_id ) {
		$post  = get_post( $automation_id );
		$steps = Utils::safe_unserialize( $post->post_content );

		if ( ! empty( $steps[0]['data'] ) ) {
			foreach ( $steps[0]['data'] as $id => $trigger_data ) {

				$trigger = Trigger::get_instance( $trigger_data['key'], $trigger_data );

				if ( $post->post_status === 'publish' && $trigger::is_single_scheduled_event() ) {
					wp_schedule_single_event( $trigger->prepare_data(), $trigger::get_wp_hook(), [
						$post,
					] );
				}
			}
		}
	}

	/**
	 * Get automation for automations collection
	 *
	 * @return array
	 */
	final public function localize_data(): array {
		$automation = [];
		foreach ( $this->steps as $index => $object_instances ) {
			$instances = [];
			if ( ! empty( $object_instances['items'] ) ) {

				foreach ( $object_instances['items'] as $id => $instance ) {

					if ( ! empty( $instance ) ) {
						if ( is_array( $instance ) ) {
							$instances[ $id ] = $instance['filter']->localize_data();
						} else {
							$instances[ $id ] = $instance->localize_data();
						}
					}
				}
			}
			$automation[ $index ] = $instances;
		}

		return $automation;
	}

	/**
	 * Check whether the post is an automation
	 *
	 * @return bool
	 */
	public function is_post_valid(): bool {
		return ! empty( $this->post ) && $this->post->post_type === static::POST_TYPE;
	}

	/**
	 * Delete automation
	 *
	 * @param int     $id
	 * @param boolean $force_delete
	 *
	 * @return boolean
	 */
	public static function delete( int $id, bool $force_delete = true ): bool {
		$post = get_post( $id );

		if ( empty( $post ) || $post->post_type !== static::POST_TYPE ) {
			return false;
		}

		if ( $force_delete ) {
			$deleted = wp_delete_post( $post->ID, true );
		} else {
			$deleted = wp_trash_post( $id );
		}

		return $deleted !== 0 && ! is_wp_error( $deleted );
	}

	/**
	 * Setup listener(trigger) hooks for this automation
	 */
	public function start() {
		if ( $this->post->post_status === 'publish' ) {

			foreach ( $this->triggers as $trigger ) {

				add_action( $trigger->get_automation_wp_hook(), function () use ( $trigger ) {
					$args = func_get_args();

					tap_logger( $this->post->ID )->set_raw_data( $this->post->ID, $args );

					global $automation_data;
					$automation_data = new Automation_Data( $trigger->process_params( $args ) );
					$automation_data->set_raw_data( $args );
					$automation_data->set( TAP_GLOBAL_DATA_OBJECT, new Global_Data( [], $this->post->ID ) );


					if ( tap_limitations( $this->post->ID )->handle_limitations( [ 'trigger' => $trigger::get_id(), 'raw_data' => $args[0] ] ) ) {
						$conditions = $trigger->get_conditions();

						if ( $this->run_filters( $conditions ) ) {
							$this->run_automation_steps( [] );
						}
					} else {
						tap_logger( $this->post->ID )->register( [
							'key'         => $trigger::get_id(),
							'id'          => 'limitation-reject',
							'message'     => 'The automation does not pass',
							'class-label' => tap_logger( $this->post->ID )->get_nice_class_name( get_class( $trigger ) ),
						] );
						tap_logger( $this->post->ID )->log();
					}

				}, $trigger::get_hook_priority(), $trigger::get_hook_params_number() );
			}
		}
	}

	/**
	 * Handle execution of filters and conditions
	 * Run through each group, check if previously executed and if it passes filters
	 *
	 * @param array $filters
	 *
	 * @return boolean
	 */
	public function run_filters( array $filters ): bool {
		$valid = true;
		/* because we can have more than one filter */
		foreach ( $filters as $filter ) {
			$data_object_key = $filter['data_object'];

			if ( ! array_key_exists( $data_object_key, Data_Object::get() ) ) {
				$data_object_key = 'generic_data';
			}

			/* filters are still valid / we have data object for this filter */
			if ( $valid && ! empty( $data_object_key ) ) {
				global $automation_data;
				$data_object = $automation_data->get( $data_object_key );
				if ( ! empty( $data_object ) ) {

					/* value from the data object */
					$data_object_value = $data_object->get_value( $filter['field'] );

					/* compare the data from our current trigger with the data that we have set */
					$valid = $filter['filter']->filter( [ 'value' => $data_object_value ] );

					if ( ! $valid ) {

						$error_key = $data_object_key;
						tap_logger( $filter['filter']->get_automation_id() )->insert_log(
							[
								$error_key => [
									'data-filter-not-valid' => [
										'message' => 'Data value could not be validated',
										'label'   => $filter['filter']->get_name(),
									],
								],
							],
							[
								'automation_value' => $data_object_value,
								'filter_data'      => $filter['filter']->get_value_data(),
								'filter_id'        => $filter['filter']->get_id(),
							]
						);
					}
				} else {
					tap_logger( $filter['filter']->get_automation_id() )->register( [
						'key'         => $data_object_key,
						'id'          => 'data-not-provided-to-filter',
						'message'     => 'Data object ' . $data_object_key . ' is not provided by trigger',
						'class-label' => tap_logger( $filter['filter']->get_automation_id() )->get_nice_class_name( static::class ),
					] );
					/* don't set valid as false because we want to check only filters that have data */
				}
			} else {
				$valid = false;
			}
		}

		return $valid;
	}

	/**
	 * Handle actual execution of automation objects
	 * Run through each group, check if previously executed and if passes filters
	 *
	 * @param array $executed
	 *
	 * @return boolean
	 */
	public function run_automation_steps( array $executed ): bool {
		$valid = true;
		if ( empty( $executed ) ) {
			$executed = [];
		}

		foreach ( $this->steps as $step ) {
			//check if it passed previous filters
			if ( $valid ) {
				//check if previously executed (in case a delay was setup, some of the objects might have already been executed)
				if ( empty( $executed[ $step['id'] ] ) ) {

					switch ( $step['type'] ) {
						case 'filters':
							$valid = $this->run_filters( $step['items'] );
							break;
						case 'actions':
							foreach ( $step['items'] as $action ) {
								$action->run();
							}
							break;
						case 'delay':
							$executed[ $step['id'] ] = true;
							global $automation_data;
							foreach ( $step['items'] as $delay ) {
								wp_schedule_single_event( $delay->calculate(), 'tap_delayed_automations', [
									'trigger_data' => $automation_data->get_all(),
									'automation'   => $this->post,
									'executed'     => $executed,
									'raw_data'     => $automation_data->get_raw_data(),
								] );
							}
							tap_logger( $this->post->ID )->log();

							return true;
					}
				}
			} else {
				break;
			}
			$executed[ $step['id'] ] = true;
		}
		tap_logger( $this->post->ID )->log();

		return true;
	}

	/**
	 * Callback function used by wp-cron hook to handle delayed automation executions
	 *
	 * @param array   $trigger_data
	 * @param WP_Post $automation
	 * @param array   $executed
	 * @param array   $raw_data
	 * @
	 */
	public static function run_delayed_automations( array $trigger_data, WP_Post $automation, array $executed, array $raw_data ) {
		$instance = new Automation( $automation );
		global $automation_data;
		$automation_data = new Automation_Data( $trigger_data );
		$automation_data->set_raw_data( $raw_data );
		tap_logger( $automation->ID )->set_raw_data( $automation->ID, $raw_data );

		$instance->run_automation_steps( $executed );
	}


	/**
	 * Validate automation, make sure all plugins involved are installed and active
	 */
	final public static function validate( $objects, $show_notice = false ): bool {
		$actions  = Action::get();
		$apps     = App::get();
		$triggers = Trigger::get();
		global $tap_notice_apps;
		if ( empty( $tap_notice_apps ) ) {
			$tap_notice_apps = [];
		}
		foreach ( $objects as $object ) {
			if ( in_array( $object['type'], [ 'actions', 'triggers' ] ) ) {
				foreach ( $object['data'] as $automation_classes ) {
					$classes = $object['type'] === 'actions' ? $actions : $triggers;
					if ( empty( $classes[ $automation_classes['key'] ] ) ) {
						$app_name = $automation_classes['app_name'] ?? '';
						/**
						 * Compat with Apps
						 */
						if ( ! $app_name && isset( $automation_classes['app_id'] ) ) {
							//check if app is installed
							if ( isset( $apps[ $automation_classes['app_id'] ] ) ) {
								$app_name = $apps[ $automation_classes['app_id'] ]::get_name();
							} else {
								//else show its ID
								$app_name = ucfirst( $automation_classes['app_id'] ) . ' automator app ';
							}
						}

						//display only once/app
						if ( $show_notice && $app_name && $app_name !== General_App::get_name() && ! in_array( $app_name, $tap_notice_apps, true ) ) {
							$tap_notice_apps[] = $app_name;
							static::show_notice( $app_name, $automation_classes['key'] );
						}

						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Missing plugin notice
	 */
	public static function show_notice( $missing_plugin, $missing_key ) {
		add_action( 'admin_notices', function () use ( $missing_plugin, $missing_key ) {
			if ( ! defined( 'THRIVE_AUTOMATOR_RUNNING' ) ) {
				return;
			}
			/**
			 * display any possible conflicts with other plugins / themes as error notification in the admin panel
			 */
			$is_plugin_active = apply_filters( 'tap_notice_check_plugin', false, $missing_plugin, $missing_key );

			if ( $is_plugin_active ) {
				$link = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=thrive_automator' ), 'Thrive Automator dashboard' );
				$message
				      = sprintf( 'Some of your triggers/actions from Thrive Automator have been deprecated. Go to the %s and replace them.',
					$link );
			} else {
				$link = sprintf( '<a href="%s">%s</a>', admin_url( 'plugins.php' ), 'plugins dashboard' );

				$message
					= sprintf( ' %s is not installed or not activated. Some automations will not work. Please go to %s and install it',
					$missing_plugin, $link );
			}
			echo sprintf( '<div class="error"><p>%s</p></div>', $message );
		} );
	}
}

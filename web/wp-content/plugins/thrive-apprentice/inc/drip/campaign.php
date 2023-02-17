<?php

namespace TVA\Drip;

use TVA\Drip\Schedule\Specific;
use TVA\Drip\Schedule\Utils;
use TVA\Drip\Trigger\Automator;
use TVA\Drip\Trigger\Base;
use TVA\Drip\Trigger\Specific_Date_Time_Interval;
use TVA\Drip\Trigger\Time_After_First_Lesson;
use TVA\Drip\Trigger\Time_After_Purchase;
use TVA\Product;
use TVA_Course_V2;
use WP_Error;
use WP_Post;
use WP_Term;

/**
 * Wrapper over custom post type
 * - tva_drip_campaign
 *
 * @property string         post_title
 * @property integer        ID
 *
 * @property string         $type                               Campaign template type
 * @property string         $content_type                       post_type on which it applies
 * @property string         $trigger                            main trigger for the campaign
 * @property string|integer $lock_from                          index of post from which content will get unlocked
 * @property string|integer $lock_from_id                       ID of post from which content will get unlocked
 * @property array          $schedule_type                      repeating / non_repeating
 * @property array          $schedule                           schedule data
 * @property array          $unlock                             custom unlock schedule for each post
 * @property string         $unlock_date                        date for first content unlock
 * @property bool           $display_locked                     Display locked lessons and content in lesson lists
 *
 */
class Campaign implements \JsonSerializable {

	use Utils;

	const POST_TYPE = 'tva_drip_campaign';

	/** @var WP_Post|null */
	protected $post;

	/**
	 * @var \TVA_Customer|false
	 */
	protected $customer;

	/**
	 * @var array for post props
	 */
	protected $data = [];

	protected $meta_fields = [
		'type'           => '',
		'content_type'   => '',
		'trigger'        => '',
		'lock_from'      => '',
		'lock_from_id'   => '',
		'schedule_type'  => 'non_repeating',
		'schedule'       => [],
		'unlock'         => [],
		'unlock_date'    => '',
		'display_locked' => true,
	];

	/**
	 * @var array of trigger instances ( cache having $post_id as key )
	 */
	protected $post_triggers = [];

	/**
	 * @param int|WP_Post|array $data
	 */
	public function __construct( $data ) {
		if ( is_int( $data ) ) {
			$this->post = get_post( (int) $data );
		} elseif ( $data instanceof WP_Post ) {
			$this->post = $data;
		} elseif ( is_array( $data ) ) {
			$this->data = $data;

			$meta = array_intersect_key( $data, $this->meta_fields );
		}

		if ( isset( $this->post ) ) {
			$meta = get_post_meta( $this->post->ID, 'tva_drip_settings', true );
		}

		/* make sure meta always contains the defaults defined in the class */
		if ( isset( $meta ) ) {
			$meta              = array_merge( $this->meta_fields, $meta ?: [] );
			$this->meta_fields = $meta;
		}
	}

	/**
	 * Set data from array
	 *
	 * @param array $data
	 */
	public function set_data( $data ) {
		foreach ( $data as $field => $value ) {
			$this->{$field} = $value;
		}
	}

	/**
	 * Assign current post to a course term
	 *
	 * @param int|TVA_Course_V2|WP_Term $course
	 *
	 * @return bool
	 */
	public function assign_to_course( $course ) {

		$course_id = static::_prepare_course_id( $course );

		$result = wp_set_object_terms( $this->post->ID, $course_id, \TVA_Const::COURSE_TAXONOMY );

		return false === is_wp_error( $result );
	}

	/**
	 * Assign campaign to product
	 *
	 * @param int|Product|WP_Term $product
	 */
	public function assign_to_product( $product ) {

		if ( true === $product instanceof WP_Term ) {
			$product_id = $product->term_id;
		} elseif ( $product instanceof Product ) {
			$product_id = $product->get_id();
		} else {
			$product_id = (int) $product;
		}

		$result = wp_set_object_terms( $this->post->ID, $product_id, Product::TAXONOMY_NAME, true );

		return false === is_wp_error( $result );
	}

	/**
	 * @return \TVA_Customer|false
	 */
	public function get_customer() {
		if ( ! isset( $this->customer ) ) {
			$this->set_customer( tva_customer() );
		}

		if ( ! isset( $this->customer ) ) {
			/**
			 * Fixed issue when reset products and there is a product made after the migration with a campaign running
			 */
			$this->set_customer( new \TVA_Customer( get_current_user_id() ) );
		}

		return $this->customer;
	}

	/**
	 * @param \TVA_Customer|false $customer
	 */
	public function set_customer( $customer ) {
		$this->customer = $customer;
	}

	/**
	 * For a campaign checks if the resource should be unlocked, when applied on a product ($product_id)
	 *
	 * @param int $product_id
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function should_unlock( $product_id, $post_id ) {
		$product_id = (int) $product_id;
		$post_id    = (int) $post_id;

		if ( ! $product_id || ! $post_id ) {
			return true;
		}

		$factory_post = \TVA_Post::factory( get_post( $post_id ) );
		if ( $factory_post->is_free_for_logged() || $factory_post->is_free_for_all() ) {
			/**
			 * If the post is free, we should unlock the post
			 */
			return true;
		}

		$has_unlock_settings = ! empty( $this->unlock[ $post_id ] ) && ( ! empty( $this->unlock[ $post_id ]['triggers'] ) || ! empty( $this->unlock[ $post_id ]['inherit_from'] ) );
		$should_be_unlocked  = true;

		/* for datetime triggers, every post that does not have unlock conditions is actually locked until the datetime selected for the campaign */
		if ( $this->trigger === 'datetime' && ! $has_unlock_settings ) {
			$should_be_unlocked = static::get_datetime( $this->unlock_date ) <= current_datetime();
		} elseif ( $has_unlock_settings ) {
			$content_index = isset( $this->unlock[ $post_id ]['content_index'] ) ? $this->unlock[ $post_id ]['content_index'] : - 1;
			$triggers      = array_map( function ( $data ) use ( $content_index ) {

				$trigger = $this->compute_trigger( $data, $content_index );

				return Base::factory( $trigger, $this );
			}, $this->unlock[ $post_id ]['triggers'] );

			/* First, check if this has any conditions inherited from parent - this can only happen for lessons */
			if ( ! empty( $this->unlock[ $post_id ]['inherit_from'] ) ) {
				$should_be_unlocked = $this->should_unlock( $product_id, $this->unlock[ $post_id ]['inherit_from'] );
			}

			/**
			 * Can be "check_unlock_and_condition" or "check_unlock_or_condition"
			 */
			$method_name = 'check_unlock_' . $this->unlock[ $post_id ]['condition'] . '_condition';

			if ( method_exists( $this, $method_name ) ) {
				$should_be_unlocked = $should_be_unlocked && $this->$method_name( $product_id, $post_id, $triggers );
			}
		}

		return $should_be_unlocked;
	}

	/**
	 * Dynamic called function from should_unlock
	 * Verifies the unlock AND condition
	 *
	 * @param int    $product_id
	 * @param Base[] $triggers
	 *
	 * @return bool
	 */
	public function check_unlock_and_condition( $product_id, $post_id, $triggers = [] ) {
		$unlock = true;
		/**
		 * @var Specific_Date_Time_Interval|Time_After_First_Lesson|Time_After_Purchase|Automator $trigger
		 */
		foreach ( $triggers as $trigger ) {
			$unlock = $unlock && $trigger->is_valid( $product_id, $post_id );

			if ( $unlock === false ) { //We break as soon as we have a false result
				break;
			}
		}

		return $unlock;
	}

	/**
	 * Dynamic called function from should_unlock
	 * Verifies the unlock OR condition
	 *
	 * @param int    $product_id
	 * @param Base[] $triggers
	 *
	 * @return bool
	 */
	public function check_unlock_or_condition( $product_id, $post_id, $triggers = [] ) {
		$unlock = false;

		/**
		 * @var Specific_Date_Time_Interval|Time_After_First_Lesson|Time_After_Purchase|Automator $trigger
		 */
		foreach ( $triggers as $trigger ) {
			$unlock = $unlock || $trigger->is_valid( $product_id, $post_id );

			if ( $unlock === true ) {//We break as soon as we have a true result
				break;
			}
		}

		return $unlock;
	}

	/**
	 * @param array $trigger_data
	 * @param int   $content_index
	 *
	 * @return array
	 */
	private function compute_trigger( $trigger_data, $content_index ) {

		if ( $trigger_data['id'] === 'campaign' ) {

			/**
			 * Particular case - `campaign` trigger - this should receive the same ID as the campaign trigger. everything else remains the same
			 */
			$trigger_data['id'] = $this->trigger;

			if ( $this->trigger === 'datetime' ) {
				/**
				 * For datetime-triggered campaigns, we need to emulate a fixed date trigger on the date when the event is supposed to occur.
				 * For this, we calculate when the event should occur (using the non_repeating schedule instance) and build the data for a specific datetime event
				 */
				$trigger_schedule = \TVA\Drip\Schedule\Base::factory( $trigger_data['schedule'] );
				$occurrence       = $trigger_schedule->get_next_occurrence( $this->unlock_date );

				$trigger_data['occurrence'] = 'after';
				$trigger_data['schedule']   = [
					'id'       => 'datetime',
					/* if occurrence is somehow null ( although this should never be the case ) use a date in the past to make sure the content is not locked in case of malformed data */
					'datetime' => $occurrence ? $occurrence->format( 'Y-m-d H:i' ) : current_datetime()->modify( '-1 day' )->format( 'Y-m-d H:i' ),
				];
			}

		} elseif ( $trigger_data['id'] === 'scheduled' && $content_index > - 1 ) {

			/**
			 * Particular case from schedule trigger
			 */
			$schedule = array_merge( $this->schedule, [
					'interval_number' => is_numeric( $this->schedule['interval_number'] ) ? (int) $this->schedule['interval_number'] * (int) $content_index : $this->schedule['interval_number'], // '+' . $content_index . ' ' .
				]
			);

			if ( in_array( $this->schedule['interval_number'], [ 'weekday', 'weekend' ] ) ) {
				$schedule['interval_counter'] = $content_index;
			}

			switch ( $this->trigger ) {
				case 'datetime':
					$trigger_data = [
						'id'       => 'base',
						'schedule' => $schedule,
						'datetime' => $this->unlock_date,
					];
					break;
				case 'purchase':
					$trigger_data = [
						'id'       => 'purchase',
						'schedule' => $schedule,
					];
					break;
				case 'first-lesson':
					$trigger_data = [
						'id'       => 'first-lesson',
						'schedule' => $schedule,
					];
					break;
				default:
					break;
			}
		}

		return $trigger_data;
	}

	/**
	 * Get all drip campaigns assigned to a course
	 *
	 * @param int|TVA_Course_V2|WP_Term|array $course
	 * @param array                           $args additional `get_posts()` arguments
	 *
	 * @return Campaign[]
	 */
	public static function get_items_for_course( $course, $args = [] ) {

		if ( is_array( $course ) ) {
			$course_ids = array_map( [ static::class, '_prepare_course_id' ], $course );
		} else {
			$course_ids = [ static::_prepare_course_id( $course ) ];
		}

		$campaigns = [];
		$args      = wp_parse_args( $args, [
			'posts_per_page' => - 1,
			'post_type'      => static::POST_TYPE,
			'post_status'    => [ 'publish', 'draft' ],
			'order'          => 'DESC',
			'orderby'        => 'ID',
			'tax_query'      => [
				[
					'taxonomy' => \TVA_Const::COURSE_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $course_ids,
				],
			],
		] );

		$posts = get_posts( $args );

		foreach ( $posts as $post ) {
			$campaigns[] = new Campaign( $post );
		}

		return $campaigns;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {

		$value = null;

		if ( array_key_exists( $key, $this->meta_fields ) ) {
			return $this->meta_fields[ $key ];
		}

		if ( true === $this->post instanceof WP_Post ) {
			$value = $this->post->$key;
		}

		return $value;
	}

	public function __set( $key, $value ) {

		if ( array_key_exists( $key, $this->meta_fields ) ) {
			if ( $key === 'unlock' ) {
				$this->post_triggers = []; // reset the trigger cache
			}
			$this->meta_fields[ $key ] = $value;

			return;
		}

		if ( $this->post instanceof WP_Post ) {
			$this->post->$key = $value;
		}
	}

	/**
	 * Insert or update a post
	 *
	 * @param boolean $fire_action
	 *
	 * @return Campaign|WP_Error
	 */
	public function save( $fire_action = true ) {

		if ( true === $this->post instanceof WP_Post ) {
			wp_update_post( $this->post );
		} else {
			$data  = array_merge( $this->data, array( 'post_type' => static::POST_TYPE ) );
			$saved = wp_insert_post( $data );
			if ( false === is_wp_error( $saved ) ) {
				$this->post = get_post( $saved );
			}
		}

		if ( isset( $this->data['course_id'] ) ) {
			$this->assign_to_course( $this->data['course_id'] );
		}

		if ( ! empty( $this->meta_fields ) ) {
			$this->update_meta_fields();
		}

		if ( $fire_action ) {
			do_action( 'tva_after_campaign_save', $this );
		}

		$this->reschedule_events();

		return $this;
	}

	/**
	 * @return static campaign instance
	 */
	public function update_meta_fields() {
		if ( isset( $this->post->ID ) ) {
			$post_id = $this->post->ID;
		} elseif ( ! empty( $this->data['id'] ) ) {
			$post_id = $this->data['id'];
		}

		if ( isset( $post_id ) ) {
			update_post_meta( $post_id, 'tva_drip_settings', $this->meta_fields );
			$this->reschedule_events();
		}

		return $this;
	}

	protected static function _prepare_course_id( $course ) {

		if ( true === $course instanceof WP_Term ) {
			$course_id = $course->term_id;
		} elseif ( $course instanceof TVA_Course_V2 ) {
			$course_id = $course->get_id();
		} else {
			$course_id = (int) $course;
		}

		return $course_id;
	}

	/**
	 * @codeCoverageIgnore
	 */
	public static function register_post_type() {
		register_post_type( static::POST_TYPE, [
			'public' => false,
		] );
	}

	#[\ReturnTypeWillChange]
	public function jsonSerialize() {
		if ( false === $this->post instanceof WP_Post ) {
			return null;
		}

		if ( ! empty( $this->meta_fields['unlock'] ) ) {
			foreach ( $this->meta_fields['unlock'] as $post_id => &$unlock_data ) {
				if ( empty( $unlock_data['triggers'] ) || ! is_array( $unlock_data['triggers'] ) ) {
					continue;
				}

				/**
				 * This code is for course content trigger to filter content that is no longer present in the system and to update the content names
				 */
				foreach ( $unlock_data['triggers'] as $trigger_id => &$trigger_data ) {
					if ( empty( $trigger_data['id'] ) || $trigger_data['id'] !== 'course-content' ) {
						continue;
					}
					$should_reset_keys = false;
					foreach ( $trigger_data['objects'] as $object_id => $course_content ) {
						$is_deleted = false;
						$name       = '';
						if ( $course_content['type'] === 'course' ) {
							$term = get_term( (int) $course_content['id'] );
							if ( ! ( $term instanceof WP_Term ) ) {
								$is_deleted = $should_reset_keys = true;
							} else {
								$name = $term->name;
							}

						} elseif ( in_array( $course_content['type'], [ 'module', 'lesson' ] ) ) {
							$post = get_post( (int) $course_content['id'] );
							if ( ! ( $post instanceof WP_Post ) ) {
								$is_deleted = $should_reset_keys = true;
							} else {
								$name = $post->post_title;
							}
						}

						if ( $is_deleted ) {
							unset( $trigger_data['objects'][ $object_id ] );
						} elseif ( ! empty( $name ) ) {
							$trigger_data['objects'][ $object_id ]['text'] = $name;
						}
					}
					if ( $should_reset_keys ) {
						$trigger_data['objects'] = array_values( $trigger_data['objects'] );

						if ( count( $trigger_data['objects'] ) === 0 ) {
							unset( $unlock_data['triggers'][ $trigger_id ] );

							$children = \TVA_Manager::get_children( get_post( $post_id ) );
							foreach ( $children as $child_post ) {
								if ( $this->is_visibility_inherited_for_post( $child_post ) && empty( $this->meta_fields['unlock'][ $child_post->ID ]['triggers'] ) ) {
									unset( $this->meta_fields['unlock'][ $child_post->ID ] );
								}
							}
						}
					}
				}
				unset( $trigger_data );
			}
			unset( $unlock_data );
		}

		return [
				   'id'         => $this->post->ID,
				   'post_title' => $this->post->post_title,
				   'course_id'  => isset( $this->data['course_id'] ) ? $this->data['course_id'] : 0,
			   ] + $this->meta_fields;
	}

	/**
	 * Delete a campaign
	 *
	 * @return WP_Error|WP_Post
	 */
	public function delete() {
		$error = new \WP_Error(
			'invalid_campaign_for_delete',
			'Invalid campaign for deleting'
		);
		$this->unschedule_events();

		$deleted = wp_delete_post( $this->ID, true );

		if ( empty( $deleted ) ) {
			$deleted = $error;
		} else {
			wp_delete_object_term_relationships( $this->ID, \TVA_Const::COURSE_TAXONOMY );
			wp_delete_object_term_relationships( $this->ID, Product::TAXONOMY_NAME );
		}

		return $deleted;
	}

	/**
	 * Get all campaigns
	 *
	 * @param array $args extra get_posts() arguments
	 *
	 * @return static[] of campaigns
	 */
	public static function get_items( $args = [] ) {
		$defaults  = [
			'posts_per_page' => - 1,
			'post_type'      => static::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
		];
		$posts     = get_posts( wp_parse_args( $args, $defaults ) );
		$campaigns = [];

		foreach ( $posts as $post ) {
			$campaigns[] = new Campaign( $post );
		}

		return $campaigns;
	}

	/**
	 * Return posts IDs that have specific trigger
	 *
	 * @param string $trigger
	 *
	 * @return array
	 */
	public function get_posts_with_trigger( $trigger ) {

		if ( $this->schedule_type === 'repeating' && $this->trigger === $trigger ) {
			$trigger = 'scheduled';
		}

		$ids = [];
		foreach ( $this->unlock as $post_id => $data ) {
			$found = ! empty( array_intersect( array_column( $data['triggers'], 'id' ), array( $trigger, 'campaign' ) ) );

			if ( $found === false ) {
				continue;
			}

			$ids[] = $post_id;

		}

		return $ids;
	}

	/**
	 * Get all triggers defined for a specific $post_id
	 *
	 * @param string|int $post_id
	 *
	 * @return Base[]
	 */
	public function get_all_triggers_for_post( $post_id ) {
		/* 1. check a cache first */
		if ( isset( $this->post_triggers[ $post_id ] ) ) {
			return $this->post_triggers[ $post_id ];
		}

		/* 2. if nothing found, calculate and instantiate triggers */
		if ( empty( $this->unlock[ $post_id ] ) ) {
			return [];
		}

		$content_index = isset( $this->unlock[ $post_id ]['content_index'] ) ? $this->unlock[ $post_id ]['content_index'] : - 1;

		$triggers = [];

		foreach ( $this->unlock[ $post_id ]['triggers'] as $data ) {
			$trigger_data = $this->compute_trigger( $data, $content_index );

			$triggers[ $trigger_data['id'] ] = Base::factory( $trigger_data, $this );
		}

		$this->post_triggers[ $post_id ] = $triggers;

		return $triggers;
	}

	/**
	 * Returns a specific trigger for a specific post
	 *
	 * @param int    $post_id
	 * @param string $trigger
	 *
	 * @return Base|Specific_Date_Time_Interval|Time_After_First_Lesson|Time_After_Purchase|false
	 */
	public function get_trigger_for_post( $post_id, $trigger ) {

		$all_triggers = $this->get_all_triggers_for_post( $post_id );

		return isset( $all_triggers[ $trigger ] ) ? $all_triggers[ $trigger ] : false;
	}

	/**
	 * @param int $product_id
	 * @param int $post_id
	 * @param int $customer_id optional
	 *
	 * @return false|void
	 */
	public function cron_check_post_unlocked( $product_id, $post_id, $customer_id = null ) {
		$purchase_trigger     = $this->trigger === Time_After_Purchase::NAME || $this->get_trigger_for_post( $post_id, Time_After_Purchase::NAME );
		$first_access_trigger = $this->trigger === Time_After_First_Lesson::NAME || $this->get_trigger_for_post( $post_id, Time_After_First_Lesson::NAME );

		$has_user_triggers = $purchase_trigger !== false || $first_access_trigger !== false;

		if ( $has_user_triggers ) {

			if ( $customer_id ) {
				$customers = [ new \TVA_Customer( $customer_id ) ];
			} else {
				/**
				 * @var \TVA_Customer[] $customers
				 */
				$customers = array();

				if ( $purchase_trigger ) {
					$customers = array_merge( $customers, \TVA_Customer::get_customers(
						array(
							'meta_key'   => Time_After_Purchase::get_user_meta_key( $post_id, $this->ID ),
							'meta_value' => '1',
						) )
					);
				}

				if ( $first_access_trigger ) {
					$customers = array_merge( $customers, \TVA_Customer::get_customers(
						array(
							'meta_query' => array(
								array(
									'relation' => 'OR',
									array(
										'key'     => Time_After_First_Lesson::get_user_meta_key( $post_id, $this->ID ),
										'value'   => '1',
										'compare' => '=',
									),
									array(
										'key'     => Time_After_First_Lesson::get_user_meta_key( $this->ID, $this->ID ),
										'value'   => '1',
										'compare' => '=',
									),
								),
							),
						) )
					);
				}

				/**
				 * Make sure that the customers are unique
				 */
				$customers = array_filter( $customers, function ( $customer ) {
					static $found_customer = [];

					if ( in_array( $customer->get_id(), $found_customer, true ) ) {
						return false;
					}

					$found_customer[] = $customer->get_id();

					return true;
				} );
			}
			/**
			 * @var \TVA_Customer $customer
			 */
			foreach ( $customers as $customer ) {

				$this->set_customer( $customer );

				$should_unlock = $this->should_unlock( $product_id, $post_id );

				if ( $should_unlock ) {
					/**
					 * We need to make sure that that the campaign si marked only once for a customer-> no matter the triggers
					 */
					$this->set_user_drip_complete( $post_id );

					/**
					 * We delete the previous meta keys set for user based triggers on customers
					 */
					delete_user_meta( $customer->get_id(), Time_After_Purchase::get_user_meta_key( $post_id, $this->ID ) );
					delete_user_meta( $customer->get_id(), Time_After_First_Lesson::get_user_meta_key( $post_id, $this->ID ) );

					/**
					 * Triggered when content is unlocked for a specific user
					 *
					 * @param \WP_User $user    User object for which content is unlocked
					 * @param \WP_Post $post    The post object that is unlocked
					 * @param \WP_Term $product The product term that the campaign belongs to
					 */
					do_action( 'tva_drip_content_unlocked_for_specific_user', $customer->get_user(), get_post( $post_id ), get_term( $this->get_product_id() ) );
				}
			}

		} else {
			/**
			 * Triggered when content is unlocked sitewide
			 *
			 * @param \WP_Post $post    The post object that is unlocked
			 * @param \WP_Term $product The product term that the campaign belongs to
			 */
			do_action( 'tva_drip_content_unlocked_sitewide', get_post( $post_id ), get_term( $this->get_product_id() ) );
		}
	}

	/**
	 * Checks for CRON callbacks
	 * This checks should be general and should apply to all callbacks
	 * This function is called before the callback logic is made
	 *
	 * @param int $product_id
	 * @param int $post_id
	 */
	public function cron_allow_execute( $product_id, $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			/**
			 * This means that something happened with the post from the schedule
			 */
			return false;
		}

		if ( $post->post_status !== 'publish' ) {
			/**
			 * The post from the schedule needs to be published at the time the cron callback runs
			 */
			return false;
		}

		if ( (int) $product_id !== $this->get_product_id() ) {
			/**
			 * This means that the drip settings for the product from schedule have been modified
			 */
			return false;
		}

		if ( empty( $this->unlock[ $post_id ] ) ) {
			/**
			 * This means that the course doesn't contain this post anymore
			 * (maybe it has been moved to another course)
			 */
			return false;
		}

		$factory_post = \TVA_Post::factory( $post );

		if ( $factory_post->is_free_for_all() || $factory_post->is_free_for_logged() ) {
			/**
			 * If the post if free, we do not execute cron
			 */
			return false;
		}

		$course_exists_in_product = false;
		$product                  = new Product( $product_id );
		$associated_course_id     = $this->get_course_id();
		foreach ( $product->get_courses() as $course ) {
			if ( $course->get_id() === $associated_course_id ) {
				$course_exists_in_product = true;
				break;
			}
		}

		if ( ! $course_exists_in_product ) {
			/**
			 * If the course got deleted somehow, or it has been removed from the content set
			 */
			return false;
		}

		$course = new TVA_Course_V2( $this->get_course_id() );

		if ( ! $course->is_published() ) {
			/**
			 * This means that the course that is associated with this campaign modified its status and it is no longer published
			 */
			return false;
		}

		return true;
	}

	/**
	 * Return the first product ID associated with the campaign
	 *
	 * @return int
	 */
	public function get_product_id() {
		$terms = get_the_terms( $this->post, Product::TAXONOMY_NAME );

		if ( ! empty( $terms ) ) {
			return $terms[0]->term_id;
		}

		return 0;
	}

	/**
	 * Get all the product IDs associated with the campaign
	 *
	 * @return array|int[]
	 */
	public function get_all_product_ids() {
		$terms = get_the_terms( $this->post, Product::TAXONOMY_NAME );
		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		return array_map( static function ( $term ) {
			return $term->term_id;
		}, $terms );
	}

	/**
	 * Return the associated course ID
	 *
	 * @return int
	 */
	public function get_course_id() {
		$terms = get_the_terms( $this->post, \TVA_Const::COURSE_TAXONOMY );

		if ( ! empty( $terms ) ) {
			return $terms[0]->term_id;
		}

		return 0;
	}

	/**
	 * Computes the user meta key that marks the user that he completed all the user based triggers
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public function get_drip_complete_user_meta_key( $post_id ) {
		return sprintf( 'tva_campaign_%s_post_%s_completed', $this->ID, $post_id );
	}

	/**
	 * Sets the active user a flag signifies that he completed all the user based triggers
	 *
	 * @param int $post_id
	 */
	public function set_user_drip_complete( $post_id ) {
		update_user_meta( $this->get_customer()->get_id(), $this->get_drip_complete_user_meta_key( $post_id ), 1 );
	}

	/**
	 * Checks if the active user has completed all the user based triggers
	 *
	 * @param int $post_id
	 *
	 * @return bool
	 */
	public function is_user_drip_complete( $post_id ) {
		return ! empty( get_user_meta( $this->get_customer()->get_id(), $this->get_drip_complete_user_meta_key( $post_id ), true ) );
	}

	/**
	 * Get a list of all WordPress scheduled events for a drip campaign
	 *
	 * @return array of cron event data, built as an associative map:
	 *                 post_id => [
	 *                          cron_data1, cron_data2
	 *                 ]
	 *               This will allow easier manipulation, e.g. directly unsetting some events for missing posts
	 */
	public function get_scheduled_events() {
		$campaign_id = $this->ID;

		/**
		 * map of hook => trigger_id
		 */
		$types = [
			Specific_Date_Time_Interval::EVENT => Specific_Date_Time_Interval::NAME,
			Time_After_First_Lesson::EVENT     => Time_After_First_Lesson::NAME,
			Time_After_Purchase::EVENT         => Time_After_Purchase::NAME,
		];

		$future_events = [];

		foreach ( _get_cron_array() as $timestamp => $cron ) {
			foreach ( $types as $hook => $trigger_id ) {
				if ( ! isset( $cron[ $hook ] ) ) {
					continue;
				}
				foreach ( $cron[ $hook ] as $args_key => $cron_data ) {
					/* the campaign ID is always the second argument for cron callbacks */
					if ( $cron_data['args'][1] !== $campaign_id ) {
						continue;
					}

					/* add some extra useful data in the returned cron */
					$cron_data['hook']         = $hook;
					$cron_data['drip_trigger'] = $trigger_id;
					$cron_data['timestamp']    = $timestamp;
					$cron_data['wp_args_key']  = $args_key;

					$post_id = $cron_data['args'][2];
					if ( ! isset( $future_events[ $post_id ] ) ) {
						$future_events[ $post_id ] = [];
					}
					$future_events[ $post_id ][] = $cron_data;
				}
			}
		}

		return $future_events;
	}

	/**
	 * Unschedule all events defined for this campaign
	 *
	 * @param int|null $product_id optional. If sent, it will only unschedule events related to $product_id
	 */
	public function unschedule_events( $product_id = null ) {
		$product_id = (int) $product_id;

		foreach ( $this->get_scheduled_events() as $events ) {
			foreach ( $events as $event ) {
				if ( ! $product_id || $event['args'][0] === $product_id ) {
					wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );
				}
			}
		}
	}

	/**
	 * Reschedule all WP cron events for a drip campaign
	 */
	public function reschedule_events() {

		/*
		1a. un-schedule all future events that don't have a post_id correlation
		1b. un-schedule all future events that have a datetime trigger
		1c. if no product associated, un-schedule all events
		1d. for evergreen crons, try to reschedule them based on the latest drip campaign settings
		1e. for evergreen events, unschedule all events related to triggers that do not exist anymore, for each post
		*/
		$product_ids = $this->get_all_product_ids();

		foreach ( $this->get_scheduled_events() as $post_id => $events ) {
			$post_triggers = $this->get_all_triggers_for_post( $post_id );

			foreach ( $events as $event ) {
				$has_event_trigger = isset( $post_triggers[ $event['drip_trigger'] ] );

				/* The event needs to be unscheduled. After this, if possible, re-schedule it. */
				wp_unschedule_event( $event['timestamp'], $event['hook'], $event['args'] );

				/* if the trigger is a date/time event, this is rescheduled below, no need to reschedule it here */
				if ( $event['hook'] === Specific_Date_Time_Interval::EVENT || $event['hook'] === Base::EVENT ) {
					continue;
				}

				/* if there's no unlock criteria for the current post, we have nothing to reschedule */
				if ( empty( $this->unlock[ $post_id ] ) ) {
					continue;
				}

				/* if the trigger has been removed from the post, we won't reschedule the corresponding event */
				if ( ! $has_event_trigger ) {
					continue;
				}

				/* if there's no product associated, we don't need to reschedule it */
				if ( empty( $product_ids ) || ! in_array( $event['args'][0], $product_ids ) ) {
					continue;
				}

				$trigger_instance = $post_triggers[ $event['drip_trigger'] ];

				$from_date = $trigger_instance->get_original_event_date( $event );

				/* if date is valid, reschedule the future event at the new date */
				if ( $from_date ) {
					$trigger_instance->schedule_event(
						$event['args'][0],
						$event['args'][2],
						$event['args'][3],
						$from_date
					);
				}
			}
		}

		/* 2. redefine future datetime-based cron events */
		if ( $product_ids ) {
			foreach ( $this->unlock as $post_id => $unlock_setting ) {
				$trigger = $this->get_trigger_for_post( $post_id, Specific_Date_Time_Interval::NAME );

				if ( $trigger ) {
					foreach ( $product_ids as $product_id ) {
						$trigger->schedule_event( $product_id, $post_id );
					}
				}
			}
		}
	}

	/**
	 * Duplicate this drip campaign and assign it to a course
	 *
	 * @param TVA_Course_V2 $new_course
	 * @param array         $content_id_map map of old content_id => new_content_id
	 *
	 * @return Campaign
	 */
	public function duplicate( $new_course, $content_id_map ) {
		$data = $this->jsonSerialize();
		unset( $data['id'] );
		$data['course_id'] = $new_course->get_id();
		if ( ! empty( $data['lock_from_id'] ) && isset( $content_id_map[ $data['lock_from_id'] ] ) ) {
			$data['lock_from_id'] = $content_id_map[ $data['lock_from_id'] ];
		} else {
			$data['lock_from_id'] = '';
		}
		if ( ! empty( $data['unlock'] ) ) {
			$unlock = [];
			/* build unlock conditions with new IDs based on content_id_map */
			foreach ( $data['unlock'] as $post_id => $settings ) {
				if ( isset( $content_id_map[ $post_id ] ) ) {
					$unlock[ $content_id_map[ $post_id ] ] = $settings;
				}
			}
			$data['unlock'] = $unlock;
		}

		return ( new static( $data ) )->save();
	}

	/**
	 * @param $post
	 *
	 * @return bool returns the visibility of a post
	 */
	public function get_visibility_for_post( $post ) {
		$is_visible = $this->display_locked;

		if ( ! empty( $this->unlock[ $post->ID ] ) ) {
			if ( $this->unlock[ $post->ID ]['visibility'] === 'hidden' ) {
				$is_visible = false;
			} else if ( $this->unlock[ $post->ID ]['visibility'] === 'displayed' ) {
				$is_visible = true;
			}
		}

		return $is_visible;
	}

	/**
	 * @param $post
	 *
	 * @return bool Returns true if the posi visibility is inherited
	 */
	public function is_visibility_inherited_for_post( $post ) {
		$is_inherited = false;

		if ( ! empty( $this->unlock[ $post->ID ] ) && $this->unlock[ $post->ID ]['visibility'] === 'inherited' ) {
			$is_inherited = true;
		}

		return $is_inherited;
	}
}

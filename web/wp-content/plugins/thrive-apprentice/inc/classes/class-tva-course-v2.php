<?php

use TVA\Drip\Campaign;

/**
 * Class TVA_Course_V2
 * - wrapper over WP_Term
 * - assigns properties to instance read from wp term meta
 * - can init course's structure: module/chapter/lessons
 *
 * @property string                       $name
 * @property string                       $description
 * @property int                          $term_id
 * @property string                       $status
 * @property int                          $topic
 * @property int                          $label
 * @property int                          $level
 * @property TVA_Author                   $author
 * @property string                       $slug
 * @property string                       $comment_status
 * @property bool                         $has_video
 * @property array                        $video
 * @property string                       $cover_image
 * @property string                       $message
 * @property bool                         $is_private
 * @property int                          $excluded
 * @property int                          $protect_overview
 * @property int                          $published_lessons_count
 * @property array|TVA_Access_Restriction $access_restrictions
 * @property array                        $all_lessons
 * @property array                        $ordered_published_lessons
 * @property string                       $type
 * @property string                       $type_label
 * @property TVA_Course_Overview_Post     $overview_post
 * @property string                       excerpt
 * @property string                       publish_date
 * @property WP_Term|false                product_term      - the product this course is associated with. Returns false if no product is found
 * @property integer                      selected_campaign in context of a product
 */
class TVA_Course_V2 extends TVA_Course implements JsonSerializable {

	/**
	 * Course Access cache - stores whether or not the current user has access to courses
	 * Cache is being built with each call to $this->has_access()
	 *
	 * @var array
	 */
	public static $ACCESS_CACHE = array();

	/**
	 * Conversions for all courses
	 *
	 * @var array
	 */
	protected static $conversions;

	/**
	 * All users who enrolled to any course
	 *
	 * @var array
	 */
	protected static $enrolled_users;

	/**
	 * Allowed values for comment status
	 *
	 * @var string[]
	 */
	private $_allowed_comment_status = array(
		'open',
		'closed',
	);

	/**
	 * @var WP_Term
	 */
	protected $_wp_term;

	/**
	 * @var array
	 */
	protected $_data = array();

	/**
	 * default properties for a TA Course
	 *
	 * @var array
	 */
	protected $_defaults = array(
		'id'          => null,
		'name'        => null,
		'description' => null,
		'status'      => 'draft',
		'order'       => 0,
		'excluded'    => 0,
		'message'     => '',
	);

	/**
	 * List of Lessons/Chapters/Module
	 *
	 * @var TVA_Post[]
	 */
	protected $_structure = array();

	/**
	 * @var TVA_Topic
	 */
	protected $_topic;

	/**
	 * @var TVA_Level
	 */
	protected $_difficulty;

	/**
	 * TVA_Course_V2 constructor.
	 *
	 * @param int|array|WP_Term $data
	 */
	public function __construct( $data ) {

		if ( is_int( $data ) ) {
			$this->_init_from_db( (int) $data );
		} elseif ( true === $data instanceof WP_Term ) {
			$this->_wp_term = $data;
		} else {
			$this->_data = array_merge( $this->_defaults, (array) $data );
		}
	}

	/**
	 * Set value at key in local $_data
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function __set( $key, $value ) {

		$this->_data[ $key ] = $value;
	}

	public function __isset( $key ) {

		return isset( $this->_data[ $key ] ) || ( true === $this->_wp_term instanceof WP_Term && $this->_wp_term->$key );
	}

	/**
	 * Gets $key from _data or _wp_term
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {

		$value = null;

		if ( isset( $this->_data[ $key ] ) ) {
			$value = $this->_data[ $key ];
		} elseif ( $this->_wp_term instanceof WP_Term && isset( $this->_wp_term->$key ) ) {
			$value = $this->_wp_term->$key;
		} elseif ( method_exists( $this, 'get_' . $key ) ) {
			$method_name = 'get_' . $key;
			$value       = $this->$method_name();
		}

		return $value;
	}

	/**
	 * Read wp_term from DB and init instance's prop
	 *
	 * @param int $id
	 */
	protected function _init_from_db( $id ) {

		$id = (int) $id;

		$this->_wp_term = get_term( $id );
	}

	/**
	 * Insert new wp_term into db
	 *
	 * @return int|WP_Error
	 */
	protected function _insert() {

		$data = array(
			'name'        => $this->name,
			'description' => $this->description,
		);

		$result = wp_insert_term( $this->name, TVA_Const::COURSE_TAXONOMY, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$id = $result['term_id'];

		$this->_init_from_db( $id );

		update_term_meta( $this->term_id, 'tva_status', $this->_data['status'] );
		update_term_meta( $this->term_id, 'tva_order', (int) $this->_data['order'] );
		update_term_meta( $this->term_id, 'tva_description', trim( $this->description ) );
		update_term_meta( $this->term_id, 'tva_video_status', (bool) $this->has_video );
		update_term_meta( $this->term_id, 'tva_term_media', $this->video instanceof TVA_Media ? $this->video->jsonSerialize() : $this->video );
		update_term_meta( $this->term_id, 'tva_message', $this->_data['message'] );
		update_term_meta( $this->term_id, TVA_Topic::COURSE_TERM_NAME, $this->topic instanceof TVA_Topic ? $this->topic->id : $this->topic );
		update_term_meta( $this->term_id, 'tva_excluded', isset( $this->_data['excluded'] ) ? $this->_data['excluded'] : 0 );
		update_term_meta( $this->term_id, TVA_Level::COURSE_TERM_NAME, $this->level );
		update_term_meta( $this->term_id, 'tva_excerpt', $this->excerpt );
		update_term_meta( $this->term_id, 'tva_publish_date', $this->publish_date );
		update_term_meta( $this->term_id, 'tva_last_edit_date', current_datetime()->getTimestamp() );


		update_term_meta( $this->term_id, 'tva_editable_module', true );

		return $id;
	}

	/**
	 * Saves data for an existing course
	 *
	 * @return bool
	 */
	public function _update() {

		update_term_meta( $this->term_id, TVA_Topic::COURSE_TERM_NAME, $this->topic instanceof TVA_Topic ? $this->topic->id : $this->topic );
		update_term_meta( $this->term_id, 'tva_label', $this->label );
		update_term_meta( $this->term_id, TVA_Level::COURSE_TERM_NAME, $this->level );
		update_term_meta( $this->term_id, 'tva_description', trim( $this->description ) );
		update_term_meta( $this->term_id, 'tva_comment_status', trim( $this->comment_status ) );
		update_term_meta( $this->term_id, 'tva_video_status', (bool) $this->has_video );
		update_term_meta( $this->term_id, 'tva_term_media', $this->video instanceof TVA_Media ? $this->video->jsonSerialize() : $this->video );
		update_term_meta( $this->term_id, 'tva_cover_image', $this->cover_image );
		update_term_meta( $this->term_id, 'tva_protect_overview', $this->protect_overview );
		update_term_meta( $this->term_id, 'tva_logged_in', ! empty( $this->_data['is_private'] ) );
		update_term_meta( $this->term_id, 'tva_excluded', isset( $this->_data['excluded'] ) ? $this->_data['excluded'] : $this->excluded );
		update_term_meta( $this->term_id, 'tva_message', isset( $this->_data['message'] ) ? $this->_data['message'] : $this->message );
		update_term_meta( $this->term_id, 'tva_status', isset( $this->_data['status'] ) ? $this->_data['status'] : $this->status );
		update_term_meta( $this->term_id, 'tva_excerpt', $this->excerpt );
		update_term_meta( $this->term_id, 'tva_publish_date', $this->publish_date );
		update_term_meta( $this->term_id, 'tva_last_edit_date', current_datetime()->getTimestamp() );

		if ( $this->access_restrictions && is_array( $this->access_restrictions ) ) {
			tva_access_restriction_settings( $this )->set( $this->access_restrictions )->save();
		}

		if ( ! empty( $this->_data['author'] ) && $this->_data['author'] instanceof TVA_Author ) {
			update_term_meta( $this->term_id, TVA_Author::COURSE_TERM_NAME, $this->_data['author']->jsonSerialize() );
		}

		$saved = wp_update_term( $this->term_id, TVA_Const::COURSE_TAXONOMY, $this->_data );

		return ! is_wp_error( $saved );
	}

	/**
	 * Updates slug of course
	 *
	 * @param {string} $slug
	 *
	 * @return array|WP_Error
	 */
	public function update_slug( $slug ) {
		$term = $this->get_wp_term();

		return wp_update_term( $term->term_id, TVA_Const::COURSE_TAXONOMY, [ 'slug' => $slug ] );
	}

	/**
	 * Inserts or updates a WP_Term
	 *
	 * @return bool|int|WP_Error
	 */
	public function save() {

		if ( $this->get_id() ) {
			return $this->_update();
		}

		return $this->_insert();
	}

	/**
	 * Duplicates a course
	 *
	 * @return TVA_Course_V2 | WP_Error
	 */
	public function duplicate() {

		$this->init_structure();

		$course_terms = get_terms( [ 'taxonomy' => 'tva_courses', 'hide_empty' => false, ] );
		$copy_number  = 0;
		foreach ( $course_terms as $course_term ) {
			if ( preg_match( '/Copy of ' . $this->name . '( \d*)?/', $course_term->name, $matches ) ) {
				if ( preg_match_all( '/ \d{1,2}$/', $matches[0], $digit_matches ) ) {
					if ( intval( $digit_matches[0][0] ) + 1 > $copy_number ) {
						$copy_number = intval( $digit_matches[0][0] ) + 1;
					}
				} else {
					$copy_number = 2;
				}
			}
		}

		$new_course = new TVA_Course_V2( array(
			'name'           => $copy_number ? 'Copy of ' . $this->name . ' ' . $copy_number : 'Copy of ' . $this->name,
			'description'    => $this->description,
			'cover_image'    => $this->cover_image,
			'message'        => $this->message,
			'order'          => (int) $this->get_order() + 1,
			'topic'          => (int) $this->get_topic_id(),
			'excluded'       => (int) $this->excluded,
			'has_video'      => $this->has_video(),
			'level'          => $this->get_level_id(),
			'label'          => $this->get_label_id(),
			'allow_comments' => (bool) $this->allows_comments(),
		) );

		$new_course->video          = $this->get_meta( 'tva_term_media' );
		$new_course->comment_status = $this->get_comment_status();
		$new_course->author         = $this->author;

		/**
		 * save course
		 */
		$course_id = $new_course->save();

		if ( is_wp_error( $course_id ) ) {
			return $course_id;
		}

		$new_course->update_slug( wp_unique_term_slug( $this->slug, $new_course->get_wp_term() ) );

		/**
		 * set overview post
		 */
		$this->get_overview_post( true )->duplicate( $new_course );

		$content_id_map = [];

		try {
			foreach ( $this->get_structure() as $element ) {
				$new_element                    = $element->duplicate();
				$content_id_map[ $element->ID ] = $new_element->ID;
				foreach ( $element->get_duplication_id_map() as $old => $new ) {
					$content_id_map[ $old ] = $new;
				}
				$new_element->init_structure();
				$new_element->assign_to_course( $course_id );
			}
			$new_course->save();

		} catch ( Exception $e ) {
			return new WP_Error( $e->getCode(), $e->getMessage() );
		}

		/* these need to be ordered ASC by id so the new ones are created in correct order */
		foreach ( $this->get_drip_campaigns( [ 'order' => 'ASC' ] ) as $campaign ) {
			$campaign->duplicate( $new_course, $content_id_map );
		}

		return $new_course;
	}

	/**
	 * Returns WP_Term id
	 *
	 * @return int
	 */
	public function get_id() {

		return (int) $this->term_id;
	}

	/**
	 * Assign a lesson to a course
	 *
	 * @param TVA_Lesson $lesson
	 *
	 * @return bool
	 */
	public function assign_lesson( TVA_Lesson $lesson ) {

		return $this->assign_post( $lesson->get_the_post() );
	}

	/**
	 * Assign a post to this Course Term
	 *
	 * @param WP_Post $post
	 *
	 * @return bool
	 */
	public function assign_post( WP_Post $post ) {

		$assigned = wp_set_object_terms( $post->ID, $this->get_id(), TVA_Const::COURSE_TAXONOMY );

		return ! is_wp_error( $assigned );
	}

	/**
	 * Returns all the courses used for TAR Integration
	 *
	 * @return array
	 */
	public static function get_items_for_architect_integration() {
		/* always show a demo course first */
		$demo_course_term = \TVA\TTB\Apprentice_Wizard::get_object_or_demo_content( TVA_Const::COURSE_TAXONOMY, 0, true );
		$is_template      = tva_is_apprentice_template();
		$courses          = array_merge( [ new TVA_Course_V2( $demo_course_term ) ], self::get_items() );

		$return = array();

		/**
		 * @var $course TVA_Course_V2
		 */
		foreach ( $courses as $course ) {
			$name = $course->name;
			if ( $course->get_status() === 'private' ) {
				if ( ! $is_template ) {
					continue;
				}

				$name = '[DEMO] ' . $name;
			}
			$return[ $course->id ] = array(
				'id'             => $course->get_id(),
				'admin_edit_url' => $course->get_edit_link(),
				'name'           => $name,
				'status'         => $course->get_status(), //Needed for the "Not Published Warning" -> Course Element
			);
		}

		return $return;
	}

	/**
	 * Returns the active Course ID from a give post id
	 *
	 * @param int $post_id
	 *
	 * @return int
	 */
	public static function get_active_course_id( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			$post_id = get_the_ID();
		}

		$terms = wp_get_post_terms( $post_id, TVA_Const::COURSE_TAXONOMY );

		/**
		 * @var $course_term WP_Term
		 */
		$course_term = reset( $terms );

		if ( ! empty( $course_term ) ) {
			return (int) $course_term->term_id;
		}

		return 0;
	}

	/**
	 * Returns an array of integers representing the order of the courses that is set as it is set in the admin area
	 *
	 * @param $args
	 *
	 * @return int[]
	 */
	public static function get_ordered_items_indexes( $args = array() ) {
		/**
		 * @var TVA_Course_V2 $course
		 */
		return array_map( static function ( $course ) {
			return $course->get_id();
		}, static::get_items( $args, false ) );
	}

	/**
	 * Get courses/wp_terms from db
	 *
	 * @param array $args
	 * @param bool  $count
	 *
	 * @return TVA_Course_V2[]|int
	 */
	public static function get_items( $args = array(), $count = false ) {

		$arguments = array(
			'taxonomy'   => TVA_Const::COURSE_TAXONOMY,
			'hide_empty' => false,
			'meta_query' => array(
				'relation'         => 'AND',
				'tva_order_clause' => array(
					'key' => 'tva_order',
				),
			),
			'orderby'    => 'meta_value_num',
			'order'      => 'DESC', // backwards compat ordering
		);

		/**
		 * exclude private items which are the demo courses
		 */
		$arguments['meta_query']['tva_status'] = array(
			'key'     => 'tva_status',
			'value'   => 'private',
			'compare' => '!=',
		);

		/**
		 * Filter by status
		 */
		if ( ! empty( $args['status'] ) ) {
			$arguments['meta_query']['tva_status'] = array(
				'key'     => 'tva_status',
				'value'   => $args['status'],
				'compare' => '=',
			);
		}

		if ( ! empty( $args['overview_post'] ) ) {
			$arguments['meta_query']['overview_post'] = ( $args['overview_post'] === true ) ?
				array(
					'key'     => 'tva_overview_post_id',
					'compare' => 'EXISTS',
				) : array(
					'key'     => 'tva_overview_post_id',
					'value'   => $args['overview_post'],
					'compare' => '=',
				);
		}

		if ( ! empty( $args['rule'] ) ) {
			$arguments['meta_query']['tva_rules'] = array(
				'key'     => 'tva_rules',
				'value'   => $args['rule'],
				'compare' => 'LIKE',
			);
		}

		/**
		 * Exclusions
		 */
		if ( ! empty( $args['exclude'] ) && is_array( $args['exclude'] ) ) {
			$arguments['exclude'] = $args['exclude'];
		}

		/**
		 * Course Topics
		 */
		if ( ! empty( $args['topics'] ) ) {
			$arguments['meta_query'][ TVA_Topic::COURSE_TERM_NAME ] = array(
				'key'     => TVA_Topic::COURSE_TERM_NAME,
				'value'   => $args['topics'],
				'compare' => is_array( $args['topics'] ) ? 'IN' : '=',
			);
		}

		if ( isset( $args['filter']['topic'] ) && - 1 !== (int) $args['filter']['topic'] ) {
			//This is used in TA Admin TODO: we need to modify this to use the clause above
			$arguments['meta_query'][ TVA_Topic::COURSE_TERM_NAME ] = array(
				'key'     => TVA_Topic::COURSE_TERM_NAME,
				'value'   => $args['filter']['topic'],
				'compare' => '=',
			);
		}

		/**
		 * Course Labels & logged_in filters
		 */
		if ( ! empty( $args['labels'] ) ) {
			$arguments['meta_query'][] = array(
				'key'     => 'tva_label',
				'value'   => $args['labels'],
				'compare' => is_array( $args['labels'] ) ? 'IN' : '=',
			);
		}

		/**
		 * Free for all filter
		 */
		if ( ! empty( $args['free_for_all'] ) ) {
			$arguments['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'thrive_content_set',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'thrive_content_set',
					'value'   => 'a:0:{}',
					'compare' => '=',
				),
			);
		}

		/**
		 * Course Level
		 */
		if ( ! empty( $args['levels'] ) && is_array( $args['levels'] ) ) {
			$arguments['meta_query']['tva_level'] = array(
				'key'     => 'tva_level',
				'value'   => $args['levels'],
				'compare' => 'IN',
			);
		}

		/**
		 * Course Authors Filter
		 *
		 * Because the author is saved in the database as an serialized array we need this script to decode the authors
		 */
		if ( ! empty( $args['authors'] ) && is_array( $args['authors'] ) ) {
			$author_query = array(
				'relation' => 'OR',
			);

			foreach ( $args['authors'] as $author ) {
				$author_query[] = array(
					'key'     => 'tva_author',
					'value'   => '"ID";i:' . $author . ';',
					'compare' => 'LIKE',
				);
			}

			$arguments['meta_query'][] = $author_query;
		}

		/**
		 * Inclusions
		 *
		 * If an array of IDs is provided it will return the terms with the corresponding IDs
		 */
		if ( ! empty( $args['include'] ) && is_array( $args['include'] ) ) {
			$arguments['include'] = $args['include'];
		}

		if ( false === $count ) {
			$limit               = ! empty( $args['limit'] ) ? (int) $args['limit'] : 0;
			$arguments['offset'] = ! empty( $args['offset'] ) ? (int) $args['offset'] : 0;
			$arguments['number'] = $limit;
		}

		if ( ! empty( $args['search'] ) && is_string( $args['search'] ) ) {
			$arguments['name__like'] = sanitize_text_field( trim( $args['search'] ) );
		}

		$terms = get_terms( $arguments );

		if ( true === $count ) {
			return count( $terms );
		}

		$data = array();

		/** @var WP_Term $term */
		foreach ( $terms as $term ) {

			$course = new TVA_Course_V2(
				array(
					'wp_term'     => $term,
					'term_id'     => $term->term_id,
					'name'        => $term->name,
					'description' => $term->description,
				)
			);
			$course->set_wp_term( $term );

			$data[] = $course;
		}

		return $data;
	}

	/**
	 * Gets wp term meta
	 *
	 * @return array
	 */
	public function get_rules() {

		$rules = get_term_meta( $this->get_id(), 'tva_rules', true );
		if ( ! empty( $rules ) ) {
			$rules = array_filter(
				$rules,
				function ( $rule ) {
					/**
					 * filter out the thrivecart rules
					 */
					return ! empty( $rule['integration'] ) && 'thrivecart' !== $rule['integration'];
				}
			);
			/**
			 * Make sure this is always a numerical indexed array with keys starting from zero. otherwise it's treated as an object in js and all hell breaks loose
			 */
			$rules = array_values( $rules );
		}

		return $rules;
	}

	/**
	 * Loops through a set of rules and check if there is one of $rule_slug
	 *
	 * @param string $rule_slug
	 *
	 * @return bool
	 */
	public function has_rule( $rule_slug ) {

		$has_rule = false;
		$rules    = $this->get_rules();

		if ( ! empty( $rules ) && is_array( $rules ) ) {
			foreach ( $rules as $rule ) {
				if ( ! $has_rule && ! empty( $rule['integration'] ) && $rule['integration'] === $rule_slug ) {
					$has_rule = true;
				}
			}
		}

		return $has_rule;
	}

	/**
	 * Gets term meta value
	 *
	 * @param string $meta
	 *
	 * @return mixed
	 */
	public function get_meta( $meta ) {

		return get_term_meta( $this->get_id(), $meta, true );
	}

	/**
	 * Gets status value from term meta
	 *
	 * @return string
	 */
	public function get_status() {

		return $this->get_meta( 'tva_status' );
	}

	/**
	 * Return true if the course is published
	 *
	 * @return bool
	 */
	public function is_published() {
		return $this->get_meta( 'tva_status' ) === 'publish';
	}

	/**
	 * Gets course topic id from term meta
	 *
	 * @return int
	 */
	public function get_topic_id() {

		return (int) $this->get_meta( TVA_Topic::COURSE_TERM_NAME );
	}

	/**
	 * Based on current topic id gets a topic instance
	 *
	 * @return TVA_Topic
	 */
	public function get_topic() {

		if ( $this->_topic instanceof TVA_Topic ) {
			return $this->_topic;
		}

		$current_topic_id = $this->get_topic_id();
		$topics           = TVA_Topic::get_items();

		foreach ( $topics as $item ) {
			if ( $item->id === $current_topic_id ) {
				$this->_topic = $item;

				return $this->_topic;
			}
		}

		return current( $topics );
	}

	/**
	 * @return bool true if the course has only 1 lesson published
	 */
	public function is_guide() {

		return $this->get_published_lessons_count() === 1;
	}

	/**
	 * Based on current difficulty id gets a difficulty instance
	 * - first level difficulty is return by default
	 *
	 * @return TVA_Level
	 */
	public function get_difficulty() {

		if ( $this->_difficulty instanceof TVA_Level ) {
			return $this->_difficulty;
		}

		$current_level_id = $this->get_level_id();
		$levels           = TVA_Level::get_items();

		foreach ( $levels as $item ) {
			if ( $item instanceof TVA_Level && $item->id === $current_level_id ) {
				$this->_difficulty = $item;

				return $this->_difficulty;
			}
		}

		return current( $levels ) instanceof TVA_Level ? current( $levels ) : new TVA_Level( current( $levels ) );
	}

	/**
	 * Gets course label id from term meta
	 *
	 * @return int
	 */
	public function get_label_id() {

		return (int) $this->get_meta( 'tva_label' );
	}

	/**
	 * Saves the label ID
	 *
	 * @param int $label_id
	 */
	public function save_label_id( $label_id ) {
		update_term_meta( $this->get_id(), 'tva_label', $label_id );
	}

	/**
	 * Gets the course label data
	 *
	 * @return array
	 */
	public function get_label_data() {
		$label = \TVA_Dynamic_Labels::get_course_label( $this );

		if ( empty( $label ) || ! is_array( $label ) ) {
			$label = array(
				'title'         => esc_attr__( 'Label not available', \TVA_Const::T ),
				'color'         => '#999999',
				'default_label' => 1,
			);
		}

		return $label;
	}

	/**
	 * Gets course level of difficulty
	 *
	 * @return int
	 */
	public function get_level_id() {

		return (int) $this->get_meta( TVA_Level::COURSE_TERM_NAME );
	}

	/**
	 * Get course schedule date
	 *
	 * @return false|string
	 */
	public function get_publish_date() {

		$date = $this->get_meta( 'tva_publish_date' );
		$now  = current_time( 'Y-m-d H:i:s' );

		if ( strtotime( $date ) < strtotime( $now ) ) {
			$date = $now;
		}

		return ! empty( $date ) ? $date : $now;
	}

	/**
	 * Gets course last edited date
	 *
	 * @return string
	 */
	public function get_last_edit_date() {
		$last_edit_date = $this->get_meta( 'tva_last_edit_date' );

		if ( empty( $last_edit_date ) ) {

			$last_edit_date = current_datetime()->getTimestamp();

			update_term_meta( $this->term_id, 'tva_last_edit_date', $last_edit_date );
		}

		$date = date_create( '@' . $last_edit_date, wp_timezone() );

		$date->setTimezone( wp_timezone() );

		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Updates course last edited date
	 *
	 * @return void
	 */
	public function update_last_edit_date() {
		$last_edit_date = current_datetime()->getTimestamp();

		update_term_meta( $this->term_id, 'tva_last_edit_date', $last_edit_date );
	}

	/**
	 * Gets author instance which has been set for current course
	 *
	 * @return TVA_Author
	 */
	public function get_author() {

		return new TVA_Author( null, $this->get_id() );
	}

	/**
	 * Checks if the current course has a specific status
	 *
	 * @param string $status
	 *
	 * @return bool
	 */
	public function has_status( $status ) {

		return $this->get_status() === $status;
	}

	/**
	 * Gets access type visitors have to course
	 *
	 * @return int
	 * @example 1 it's a private course
	 * @example 0 all visitors have access to the course
	 */
	public function get_access() {

		return (int) $this->get_meta( 'tva_logged_in' );
	}

	/**
	 * Gets conversions for all courses
	 *
	 * @return array
	 */
	public static function get_conversions() {

		if ( null === self::$conversions ) {
			self::$conversions = get_option( 'tva_conversions', array() );
		}

		return self::$conversions;
	}

	/**
	 * Gets conversions count for current course
	 *
	 * @return int
	 */
	public function count_conversions() {

		$count       = 0;
		$conversions = self::get_conversions();

		if ( ! empty( $conversions[ $this->get_id() ] ) ) {
			$count = (int) $conversions[ $this->get_id() ];
		}

		return $count;
	}

	/**
	 * Counts all enrolled users for current course
	 *
	 * @return int
	 */
	public function count_enrolled_users() {

		$count = $this->get_meta( 'tva_count_enrolled_users_cache' );

		if ( is_numeric( $count ) ) {
			return $count;
		}

		global $wpdb;
		$products = $this->get_product( true );
		$count    = 0;

		if ( ! empty( $products ) && is_array( $products ) ) {
			$query = "SELECT COUNT(ID) as user_nr FROM {$wpdb->users} WHERE ";
			$parts = array();

			/**
			 * @var \TVA\Product $product
			 */
			foreach ( $products as $product ) {
				$parts = array_merge( $parts, $product->get_users_that_have_access_query_part() );
			}

			if ( count( $parts ) > 0 ) {
				$query = $query . implode( ' OR ', $parts );
			}

			$row = $wpdb->get_row( $query, ARRAY_A );

			if ( is_array( $row ) ) {
				$count = $row['user_nr'];
			}
		}


		update_term_meta( $this->get_id(), 'tva_count_enrolled_users_cache', $count );

		return $count;
	}

	/**
	 * Invalidate the cache for counting enrolled users for a course
	 *
	 * The cache is deleted for any of the following actions
	 * - edit user
	 * - delete user
	 * - update product rules
	 * - new order
	 *
	 * @param int $course_id
	 *
	 * @return void
	 */
	public static function delete_count_enrolled_users_cache( $course_id = 0 ) {
		$delete_all = $course_id === 0;

		delete_metadata( 'term', $course_id, 'tva_count_enrolled_users_cache', '', $delete_all );
	}

	/**
	 * Checks if current course is private for visitors
	 *
	 * @return bool
	 */
	public function is_private() {
		return $this->product_term instanceof WP_Term;
	}

	/**
	 * If exists, returns the product associated with the course
	 *
	 * @param bool $return_all
	 *
	 * @return \TVA\Product|null
	 */
	public function get_product( $return_all = false ) {
		return \TVA\Product::get_from_set( \TVD\Content_Sets\Set::get_for_object( $this->get_wp_term(), $this->get_id() ), array( 'return_all' => $return_all ) );
	}


	/**
	 * Returns the product this course is associated with
	 * Returns false if no product is found.
	 *
	 * @param bool $return_all
	 *
	 * Caches the result for optimization
	 */
	public function get_product_term( $return_all = false ) {
		if ( ! isset( $this->_data['product_term'] ) ) {
			$product = $this->get_product( $return_all );

			$this->_data['product_term'] = $product instanceof \TVA\Product ? $product->get_term() : false;
		}

		return $this->_data['product_term'];
	}

	/**
	 * @return null|array of data needed for admin course card
	 */
	#[\ReturnTypeWillChange]
	public function jsonSerialize() {

		return $this->to_array();
	}

	/**
	 * Init list of modules/chapter/lessons
	 *
	 * @return TVA_Post[]
	 */
	public function init_structure() {

		/**
		 * Init lessons at level courses
		 */
		$course_level_lessons = TVA_Manager::get_course_lessons( $this->get_wp_term() );
		if ( ! empty( $course_level_lessons ) ) {
			foreach ( $course_level_lessons as $post_lesson ) {
				$lesson = new TVA_Lesson( $post_lesson );
				$lesson->set_course_v2( $this );
				$this->_structure[] = $lesson;
			}

			return $this->_structure;
		}

		/**
		 * Init chapters at level courses
		 */
		$course_level_chapters = TVA_Manager::get_course_chapters( $this->get_wp_term() );
		if ( ! empty( $course_level_chapters ) ) {
			foreach ( $course_level_chapters as $post_chapter ) {
				$chapter = new TVA_Chapter( $post_chapter );
				$chapter->set_course_v2( $this );
				$chapter->init_structure();
				$this->_structure[] = $chapter;
			}

			return $this->_structure;
		}

		/**
		 * Init modules at level courses
		 */
		$course_level_modules = TVA_Manager::get_course_modules( $this->get_wp_term() );
		if ( ! empty( $course_level_modules ) ) {
			foreach ( $course_level_modules as $post_module ) {
				$module = new TVA_Module( $post_module );
				$module->set_course_v2( $this );
				$module->init_structure();
				$this->_structure[] = $module;
			}
		}

		return $this->_structure;
	}

	/**
	 * List os course items
	 *
	 * @return TVA_Post[]
	 */
	public function get_structure() {

		return $this->_structure;
	}

	/**
	 * @return WP_Term|null
	 */
	public function get_wp_term() {
		return $this->_wp_term;
	}

	/**
	 * @param WP_Term $term
	 *
	 * @return $this
	 */
	public function set_wp_term( $term ) {
		$this->_wp_term = $term;

		return $this;
	}

	/**
	 * Gets course description from course overview `post_content`
	 * - or from term meta `tva_description` as fallback
	 *
	 * @return string
	 */
	public function get_description() {

		$description   = '';
		$overview_post = $this->has_overview_post();

		if ( $overview_post instanceof WP_Post ) {
			$description = $overview_post->post_content;
		}

		if ( ! \TVA\TTB\Main::uses_builder_templates() ) {
			/**
			 * If the user uses the old and deprecated skin - check also tva_description meta
			 */
			$description = ! empty( $description ) ? $description : $this->get_meta( 'tva_description' );
		}

		$description = strip_shortcodes( $description );

		/**
		 * In case of strip_tags() is called there is used a spaces between sentences(parapgraphs)
		 */
		$description = str_replace( '<', ' <', $description );

		return $description;
	}

	/**
	 * @return string
	 */
	public function get_excerpt() {
		return $this->get_meta( 'tva_excerpt' );
	}

	/**
	 * Gets comment status
	 *
	 * @return string "open"|"closed"
	 */
	public function get_comment_status() {

		$status = $this->get_meta( 'tva_comment_status' );

		if ( ! in_array( $status, $this->_allowed_comment_status, true ) ) {
			$status = 'closed'; //todo: make sure this method returns a default value which might be a general settings after Luca implements general settings
		}

		return $status;
	}

	/**
	 * Where comments are allowed for current course
	 *
	 * @return bool
	 */
	public function allows_comments() {

		return $this->get_comment_status() === 'open';
	}

	/**
	 * Checks if the current courses has video description
	 *
	 * @return bool
	 */
	public function has_video() {

		return (bool) $this->get_meta( 'tva_video_status' );
	}

	/**
	 * @return TVA_Video
	 */
	public function get_video() {

		$_defaults = array(
			'options' => array(),
			'source'  => '',
			'type'    => 'youtube',
		);

		$video = array_merge( $_defaults, array_filter( (array) $this->get_meta( 'tva_term_media' ) ) );

		return new TVA_Video(
			array(
				'options' => ! empty( $video['media_extra_options'] ) ? $video['media_extra_options'] : $video['options'],
				'source'  => ! empty( $video['media_url'] ) ? $video['media_url'] : $video['source'],
				'type'    => ! empty( $video['media_type'] ) ? $video['media_type'] : $video['type'],
			)
		);
	}

	/**
	 * Gets current's course cover image from term meta
	 *
	 * @return string
	 */
	public function get_cover_image() {

		return $this->get_meta( 'tva_cover_image' );
	}

	/**
	 * Gets the amount of lessons which are being excluded from protection
	 * and visitors have access to
	 *
	 * USED only in product migration
	 * NOT used anymore in apprentice logic
	 *
	 * @return int
	 */
	public function get_excluded() {

		return (int) $this->get_meta( 'tva_excluded' );
	}

	/**
	 * @return int
	 */
	public function get_protect_overview() {
		return (int) $this->get_meta( 'tva_protect_overview' );
	}

	/**
	 * Lessons which are free to access for courses that
	 * are protected by some rules
	 *
	 * @return TVA_Lesson[]|int[]
	 */
	public function get_excluded_lessons( $as_ids = false ) {

		$published_lessons = $this->get_ordered_published_lessons();
		$free_lessons      = array_filter( $published_lessons, static function ( $lesson ) {
			return $lesson->is_free_for_all();
		} );

		return $as_ids === true ? array_map( function ( $lesson ) {
			/** @var TVA_Lesson $lesson */
			return $lesson->ID;
		}, $free_lessons ) : $free_lessons;
	}

	/**
	 * Message which is displayed when a lesson is protected and
	 * the visitor does not have access
	 *
	 * @return string
	 */
	public function get_message() {

		return (string) $this->get_meta( 'tva_message' );
	}

	/**
	 * Preview URL for current course term
	 *
	 * @return string
	 */
	public function get_preview_url() {

		return add_query_arg(
			array(
				'preview' => 'true',
			),
			get_term_link( $this->get_id() )
		);
	}

	/**
	 * Returns the current course link
	 *
	 * @param boolean $do_extra_logic
	 *
	 * @return string
	 */
	public function get_link( $do_extra_logic = true ) {

		if ( $do_extra_logic && 1 === count( $this->get_published_lessons() ) ) {
			$lesson = current( $this->get_published_lessons() );

			return $lesson->get_url();
		}

		return get_term_link( $this->get_id() );
	}

	/**
	 * Gets posts of current course based on args
	 *
	 * @param $args
	 *
	 * @return WP_Post[]
	 */
	private function _get_items( $args ) {

		$defaults = array(
			'posts_per_page' => - 1,
			'post_status'    => TVA_Post::$accepted_statuses,
			'tax_query'      => array(
				array(
					'taxonomy' => TVA_Const::COURSE_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( $this->get_id() ),
					'operator' => 'IN',
				),
			),
			'orderby'        => 'meta_value_num', //because tva_order_item is int
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		return get_posts( $args );
	}

	/**
	 * @return TVA_Module[]
	 */
	public function get_published_modules() {

		if ( false === isset( $this->_data['published_modules'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::MODULE_POST_TYPE,
					'meta_key'    => 'tva_module_order',
				)
			);

			$modules = array();

			foreach ( $items as $item ) {
				$modules[] = TVA_Post::factory( $item );
			}

			$this->_data['published_modules'] = $modules;
		}

		return $this->_data['published_modules'];
	}

	/**
	 * @return TVA_Module[]
	 */
	public function get_visible_modules() {

		if ( false === isset( $this->_data['visible_modules'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::MODULE_POST_TYPE,
					'meta_key'    => 'tva_module_order',
				)
			);

			$modules = array();

			foreach ( $items as $item ) {
				$module = TVA_Post::factory( $item );

				if ( ! is_editor_page_raw( true ) && ! $module->is_content_visible() && tva_access_manager()->is_object_locked( $module->get_the_post() ) ) {
					/**
					 * If is a frontend request and the module is marked as hidden from DRIP we skipp the iteration
					 */
					continue;
				}

				$modules[] = $module;
			}

			$this->_data['visible_modules'] = $modules;
		}

		return $this->_data['visible_modules'];
	}

	/**
	 * @return TVA_Chapter[]
	 */
	public function get_published_chapters() {

		if ( false === isset( $this->_data['published_chapters'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::CHAPTER_POST_TYPE,
					'meta_key'    => 'tva_chapter_order',
				)
			);

			$chapters = array();

			foreach ( $items as $item ) {
				$chapters[] = TVA_Post::factory( $item );
			}

			$this->_data['published_chapters'] = $chapters;
		}

		return $this->_data['published_chapters'];
	}

	/**
	 * @return TVA_Chapter[]
	 */
	public function get_visible_chapters() {

		if ( false === isset( $this->_data['visible_chapters'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::CHAPTER_POST_TYPE,
					'meta_key'    => 'tva_chapter_order',
				)
			);

			$chapters = array();

			foreach ( $items as $item ) {
				$chapter = TVA_Post::factory( $item );

				if ( ! is_editor_page_raw( true ) && ! $chapter->is_content_visible() ) {
					/**
					 * If is a frontend request and the module is marked as hidden from DRIP we skipp the iteration
					 */
					continue;
				}

				$chapters[] = $chapter;
			}

			$this->_data['visible_chapters'] = $chapters;
		}

		return $this->_data['visible_chapters'];
	}

	/**
	 * Fetches all the lessons from DB
	 * - the lessons may not be in desired order if they are inside modules and/or chapters
	 * - e.g. of lesson orders: [0, 0, 1, 1, 2, 2]
	 *
	 * @return TVA_Lesson[]
	 */
	public function get_published_lessons() {

		if ( false === isset( $this->_data['published_lessons'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::LESSON_POST_TYPE,
					'meta_key'    => 'tva_lesson_order',
				)
			);

			$lessons = array();

			foreach ( $items as $item ) {
				$lessons[] = TVA_Post::factory( $item );
			}

			$this->_data['published_lessons'] = $lessons;
		}

		return $this->_data['published_lessons'];
	}

	/**
	 * Returns all published lessons from current course ordered by their parents
	 * - a ordered structure of lessons
	 *
	 * @return TVA_Lesson[]
	 */
	public function get_ordered_published_lessons() {

		if ( ! isset( $this->_data['ordered_published_lessons'] ) ) {
			$this->_data['ordered_published_lessons'] = array();

			/**
			 * @var TVA_Lesson $lesson
			 */
			foreach ( $this->all_lessons as $lesson ) {
				if ( $lesson->is_published() ) {
					$this->_data['ordered_published_lessons'][] = $lesson;
				}
			}
		}

		return $this->_data['ordered_published_lessons'];
	}

	/**
	 * Fetches all the visible lessons from DB
	 * - the lessons may not be in desired order if they are inside modules and/or chapters
	 * - e.g. of lesson orders: [0, 0, 1, 1, 2, 2]
	 *
	 * @return TVA_Lesson[]
	 */
	public function get_visible_lessons() {

		if ( false === isset( $this->_data['visible_lessons'] ) ) {

			$items = $this->_get_items(
				array(
					'post_status' => 'publish',
					'post_type'   => TVA_Const::LESSON_POST_TYPE,
					'meta_key'    => 'tva_lesson_order',
				)
			);

			$lessons = array();

			foreach ( $items as $item ) {
				$lesson = TVA_Post::factory( $item );

				if ( ! is_editor_page_raw( true ) && ! $lesson->is_content_visible() && tva_access_manager()->is_object_locked( $lesson->get_the_post() ) ) {
					/**
					 * If is a frontend request and the lesson is makred as hidden from DRIP we skipp the iteration
					 */
					continue;
				}

				$lessons[] = $lesson;
			}

			$this->_data['visible_lessons'] = $lessons;
		}

		return $this->_data['visible_lessons'];
	}

	/**
	 * Returns all visible lessons from current course ordered by their parents
	 * - an ordered structure of lessons
	 *
	 * @return TVA_Lesson[]
	 */
	public function get_ordered_visible_lessons() {

		if ( ! isset( $this->_data['ordered_visible_lessons'] ) ) {
			$this->_data['ordered_visible_lessons'] = array();

			/**
			 * @var TVA_Lesson $lesson
			 */
			foreach ( $this->all_lessons as $lesson ) {

				if ( ! is_editor_page_raw( true ) && ! $lesson->is_content_visible() && tva_access_manager()->is_object_locked( $lesson->get_the_post() ) ) {
					/**
					 * If is a frontend request and the lesson is marked as hidden from DRIP we skipp the iteration
					 */
					continue;
				}

				if ( $lesson->is_published() ) {
					$this->_data['ordered_visible_lessons'][] = $lesson;
				}
			}
		}

		return $this->_data['ordered_visible_lessons'];
	}

	/**
	 * Counts posts of current course based on arguments
	 *
	 * @param array $args
	 *
	 * @return int
	 */
	private function _count_items( $args = array() ) {

		return count( $this->_get_items( $args ) );
	}

	/**
	 * Count modules of current course based on arguments
	 *
	 * @param array $args
	 *
	 * @return int
	 */
	public function count_modules( $args = array() ) {

		$args = wp_parse_args( array( 'post_type' => TVA_Const::MODULE_POST_TYPE ), $args );

		return $this->_count_items( $args );
	}

	/**
	 * Counts chapters of current course based on arguments
	 *
	 * @param array $args
	 *
	 * @return int
	 */
	public function count_chapters( $args = array() ) {

		$args = wp_parse_args( array( 'post_type' => TVA_Const::CHAPTER_POST_TYPE ), $args );

		return $this->_count_items( $args );
	}

	/**
	 * Counts all lessons from current course
	 *
	 * @param array $args allows modifying the behaviour, such as counting only published lessons
	 *
	 * @return int
	 */
	public function count_lessons( $args = array() ) {

		$args = wp_parse_args( array( 'post_type' => TVA_Const::LESSON_POST_TYPE ), $args );

		return $this->_count_items( $args );
	}

	/**
	 * Lazy getter for published modules count
	 *
	 * @return int
	 */
	public function get_published_modules_count() {

		if ( false === isset( $this->_data['published_modules_count'] ) ) {
			$this->_data['published_modules_count'] = $this->count_modules( array( 'post_status' => 'publish' ) );
		}

		return $this->_data['published_modules_count'];
	}

	/**
	 * Lazy getter for published modules count
	 *
	 * @return int
	 */
	public function get_visible_modules_count() {

		if ( false === isset( $this->_data['visible_modules_count'] ) ) {
			$this->_data['visible_modules_count'] = count( $this->get_visible_modules() );
		}

		return $this->_data['visible_modules_count'];
	}

	/**
	 * Lazy getter for published chapters count
	 *
	 * @return int
	 */
	public function get_published_chapters_count() {

		if ( false === isset( $this->_data['published_chapters_count'] ) ) {
			$this->_data['published_chapters_count'] = $this->count_chapters( array( 'post_status' => 'publish' ) );
		}

		return $this->_data['published_chapters_count'];
	}

	/**
	 * Lazy getter for visible chapters count
	 *
	 * @return int
	 */
	public function get_visible_chapters_count() {

		if ( false === isset( $this->_data['visible_chapters_count'] ) ) {
			$this->_data['visible_chapters_count'] = count( $this->get_visible_chapters() );
		}

		return $this->_data['visible_chapters_count'];
	}

	/**
	 * Lazy getter for published lessons count
	 *
	 * @return int
	 */
	public function get_published_lessons_count() {

		if ( false === isset( $this->_data['published_lessons_count'] ) ) {
			$this->_data['published_lessons_count'] = count( $this->get_ordered_published_lessons() );
		}

		return $this->_data['published_lessons_count'];
	}

	/**
	 * Lazy getter for visible lessons count
	 *
	 * @return int
	 */
	public function get_visible_lessons_count() {

		if ( false === isset( $this->_data['visible_lessons_count'] ) ) {
			$this->_data['visible_lessons_count'] = count( $this->get_ordered_visible_lessons() );
		}

		return $this->_data['visible_lessons_count'];
	}

	/**
	 * Gets course order
	 *
	 * @return int
	 */
	public function get_order() {

		return (int) $this->get_meta( 'tva_order' );
	}

	/**
	 * Get the access restrictions settings array
	 *
	 * @return TVA_Access_Restriction
	 */
	public function get_access_restrictions() {

		return tva_access_restriction_settings( $this );
	}

	/**
	 * Returns all lessons that the course has
	 *
	 * @return TVA_Lesson[]
	 */
	public function get_all_lessons() {

		if ( ! isset( $this->_data['all_lessons'] ) ) {
			$this->_data['all_lessons'] = array();

			/** @var WP_Post $item */
			foreach ( TVA_Manager::get_all_lessons( $this->get_wp_term() ) as $item ) {
				$post = TVA_Post::factory( $item );
				$post->set_course_v2( $this );
				$this->_data['all_lessons'][] = $post;
			}
		}

		return $this->_data['all_lessons'];
	}

	/**
	 * Returns the first published lesson
	 *
	 * @return false|TVA_Lesson
	 */
	public function get_first_published_lesson() {
		$all_published_lessons = $this->get_ordered_published_lessons();

		if ( empty( $all_published_lessons ) ) {
			return false;
		}

		return reset( $all_published_lessons );
	}

	/**
	 * Returns the first visible lesson
	 *
	 * @return false|TVA_Lesson
	 */
	public function get_first_visible_lesson() {
		$all_visible_lessons = $this->get_ordered_visible_lessons();

		if ( empty( $all_visible_lessons ) ) {
			return false;
		}

		return reset( $all_visible_lessons );
	}

	/**
	 * Compute course type
	 *
	 * Stores in term_meta as cache
	 *
	 * @return string
	 */
	public function compute_type() {
		$type = 'general';

		if ( count( $this->ordered_published_lessons ) > 0 ) {
			$formats = array();
			/**
			 * @var TVA_Lesson $published_lesson
			 */
			foreach ( $this->ordered_published_lessons as $index => $published_lesson ) {
				$formats[] = $published_lesson->get_type();
			}

			$formats = array_unique( array_values( $formats ) );

			if ( count( $this->ordered_published_lessons ) === 1 ) {
				$type = 'guide';
			} elseif ( ! array_diff( array( 'text', 'audio', 'video' ), $formats ) ) {
				$type = 'video_audio_text';
			} elseif ( ! array_diff( array( 'text', 'audio' ), $formats ) ) {
				$type = 'audio_text';
			} elseif ( ! array_diff( array( 'text', 'video' ), $formats ) ) {
				$type = 'video_text';
			} elseif ( ! array_diff( array( 'audio', 'video' ), $formats ) ) {
				$type = 'video_audio';
			} elseif ( ! empty( $formats ) ) {
				$type = reset( $formats );
			}
		}

		update_term_meta( $this->term_id, 'tva_type', $type );

		return $type;
	}

	/**
	 * Computes the course type dynamically
	 *
	 * @return string
	 */
	public function get_type() {
		if ( ! isset( $this->_data['type'] ) ) {
			$this->_data['type'] = get_term_meta( $this->term_id, 'tva_type', true );

			if ( empty( $this->_data['type'] ) ) {
				$this->_data['type'] = $this->compute_type();
			}
		}

		return $this->_data['type'];
	}

	/**
	 * Returns the computed type label
	 *
	 * @return string
	 */
	public function get_type_label() {
		$type  = $this->type;
		$label = __( 'Undefined type', TVA_Const::T );

		if ( strlen( $type ) > 0 ) {
			$course_labels = TVA_Dynamic_Labels::get( 'course_labels' );

			if ( isset( $course_labels[ $type ]['title'] ) ) {
				$label = $course_labels[ $type ]['title'];
			}
		}

		$this->_data['type_label'] = $label;

		return $this->_data['type_label'];
	}

	/**
	 * Gets the overview post instance
	 *
	 * @param bool $ensure
	 *
	 * @return TVA_Course_Overview_Post
	 */
	public function get_overview_post( $ensure = false ) {

		$overview_post = tva_course_overview()->set_course( $this );

		if ( true === $ensure ) {
			$overview_post->ensure_post();
		}

		return $overview_post;
	}

	/**
	 * Checks if the current course has a specific meta set with a post id
	 * - on true returns the WP_Post
	 * - on false returns false
	 *
	 * @return false|WP_Post
	 */
	public function has_overview_post() {

		$post = get_post( get_term_meta( $this->term_id, 'tva_overview_post_id', true ) );

		return $post instanceof WP_Post ? $post : false;
	}

	/**
	 * Based of the overview post it returns the default archive template content or
	 * returns the overview post content which was saved by TAr
	 *
	 * @return false|string
	 */
	public function get_content() {

		global $post;

		if ( ( isset( $_GET['tve'] ) && isset( $_GET['tcbf'] ) ) || TVA_Course_Overview_Post::POST_TYPE === $post->post_type ) {

			return tva_get_file_contents(
				'templates/course-overview/content.php',
				array(
					'course'   => $this,
					'settings' => tva_get_settings_manager()->localize_values(),
					//'levels'   => tva_get_levels(),
					'levels'   => TVA_Level::get_items(),
				)
			);
		}

		return tva_get_file_contents(
			'templates/course-overview/archive.php',
			array(
				'course'   => $this,
				'settings' => tva_get_settings_manager()->localize_values(),
				//'levels'   => tva_get_levels(),
				'levels'   => TVA_Level::get_items(),
			)
		);
	}

	/**
	 * Export class data to array
	 *
	 * @return array
	 */
	public function to_array() {

		return array(
			'id'                  => $this->get_id(),
			'status'              => $this->get_status(),
			'topic'               => $this->get_topic_id(),
			'level'               => $this->get_level_id(),
			'label'               => $this->get_label_id(),
			'name'                => $this->name,
			'text'                => $this->name,
			'access'              => $this->get_access(),
			'is_private'          => $this->is_private(),
			'conversions'         => $this->count_conversions(),
			'enrolled_users'      => $this->count_enrolled_users(),
			'rules'               => $this->get_rules(),
			'author'              => $this->get_author(),
			'structure'           => $this->get_structure(),
			'slug'                => $this->slug,
			'description'         => $this->get_description(),
			'allows_comments'     => $this->allows_comments(),
			'has_video'           => $this->has_video(),
			'video'               => $this->get_video(),
			'cover_image'         => $this->get_cover_image(),
			'excluded'            => $this->get_excluded(),
			'protect_overview'    => $this->get_protect_overview(),
			'message'             => $this->get_message(),
			'preview_url'         => $this->get_preview_url(),
			'count_lessons'       => $this->count_lessons(),
			'order'               => $this->get_order(),
			'access_restrictions' => $this->get_access_restrictions()->admin_localize(),
			'overview_post'       => $this->get_overview_post(),
			'excerpt'             => $this->get_excerpt(),
			'taxonomy'            => TVA_Const::COURSE_TAXONOMY,
			'publish_date'        => $this->get_publish_date(),
			'last_edit_date'      => $this->get_last_edit_date(),
			'drip_campaigns'      => $this->get_drip_campaigns(),
			'selected_campaign'   => $this->selected_campaign,
			'products'            => $this->get_product( true ),
		);
	}

	/**
	 * Fetches a list of drip campaign posts from current course
	 *
	 * @param array $args optional, extra args for `get_posts()` call
	 *
	 * @return Campaign[]
	 */
	public function get_drip_campaigns( $args = [] ) {
		return Campaign::get_items_for_course( $this, $args );
	}

	/**
	 * Schedule publish course action
	 * It will schedule cron event responsible with course publish action
	 */
	public function schedule() {

		if ( empty( $this->get_id() ) ) {
			return;
		}

		wp_clear_scheduled_hook( 'tva_publish_future_term', array( $this->get_id() ) );

		if ( 'future' !== $this->get_status() ) {
			return;
		}

		wp_schedule_single_event( strtotime( get_gmt_from_date( $this->get_publish_date() ) . ' GMT' ), 'tva_publish_future_term', array( $this->get_id() ) );
	}

	/**
	 * Publish the course;
	 *
	 * @return bool
	 */
	public function publish() {

		if ( $this->term instanceof WP_Error ) {
			return false;
		}

		$status = count( $this->get_published_lessons() ) > 0 ? 'publish' : 'draft';

		update_term_meta( $this->get_id(), 'tva_status', $status );
		wp_clear_scheduled_hook( 'tva_publish_future_term', array( $this->get_id() ) );

		return true;
	}

	/**
	 * Returns the course edit URL
	 *
	 * @return string
	 */
	public function get_edit_link() {
		return get_admin_url() . 'admin.php?page=thrive_apprentice#courses/' . $this->get_id();
	}

	/**
	 * @return bool
	 */
	public function editable_module() {

		return (bool) $this->get_meta( 'tva_editable_module' );
	}

	/**
	 * @return mixed
	 */
	public function has_access() {
		if ( ! isset( static::$ACCESS_CACHE[ $this->get_id() ] ) ) {
			/*
			 * Access granted if:
			 *
			 * 1) user is "admin" (= has "admin" access in TA context)
			 * OR 2) course is public
			 * OR 3) the access restrictions set on the course are validated
			 */
			static::$ACCESS_CACHE[ $this->get_id() ] = TVA_Product::has_access() || ! $this->is_private() || tva_access_manager()->has_access_to_object( $this->get_wp_term() );
		}

		return static::$ACCESS_CACHE[ $this->get_id() ];
	}

	/**
	 * Check if current user has access to last lesson of current post/term;
	 * - which means it can post on current post/term
	 * - functionality transfer from class-tva-access-manager
	 *
	 * @return bool
	 */
	public function can_comment() {
		$allow       = false;
		$lessons     = $this->get_lessons();
		$last_lesson = end( $lessons );

		if ( true === $last_lesson instanceof WP_Post ) {
			$allow = tva_access_manager()->has_access_to_object( $last_lesson );
		}

		return $allow;
	}
}

global $tva_course;

/**
 * Depending on the current request
 * - tries to instantiate a course only once
 *
 * @return TVA_Course_V2
 */
function tva_course() {

	global $tva_course;

	/**
	 * if we have a course then return it
	 */
	if ( $tva_course instanceof TVA_Course_V2 && $tva_course->get_id() ) {
		return $tva_course;
	}

	/**
	 * instantiate it empty to be able to use ti
	 */
	$tva_course = new TVA_Course_V2( array() );

	$init_course = static function () {
		global $tva_course;

		$queried_object = get_queried_object();
		if ( ! $queried_object && ! empty( $_REQUEST['post_id'] ) && wp_doing_ajax() && is_editor_page_raw( true ) ) {
			/* this makes sure that the queried_object is correctly read also in ajax requests. e.g. when rendering a cloud template via ajax for an author box */
			$queried_object = get_post( $_REQUEST['post_id'] );
		}
		$terms = get_the_terms( $queried_object, TVA_Const::COURSE_TAXONOMY );

		if ( $queried_object instanceof WP_Term && $queried_object->taxonomy === TVA_Const::COURSE_TAXONOMY ) {
			//this should enter only once
			$tva_course = new TVA_Course_V2( $queried_object );
		} elseif ( ! empty( $terms ) ) {
			$tva_course = new TVA_Course_V2( $terms[0] );
		}
	};

	/**
	 * After WP is ready
	 * - try getting the course term
	 */
	add_action( 'wp', $init_course, 0 );

	/**
	 * in REST requests, setup the course if needed
	 */
	add_action( 'thrive_theme_after_query_vars', $init_course );

	/**
	 * In editor ajax requests, set the course from the `post_id` request field if present
	 */
	add_action( 'tcb_ajax_before', $init_course );

	return $tva_course;
}

tva_course();

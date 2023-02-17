<?php

namespace TVA\Architect\Dynamic_Actions;

/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Main
 *
 * @package TVA\Architect\Dynamic_Actions
 * @project : thrive-apprentice
 *
 * @property int course_count_lessons
 * @property int course_count_lessons_completed
 * @property int module_count_lessons
 * @property int module_count_lessons_completed
 * @property int chapter_count_lessons
 * @property int chapter_count_lessons_completed
 */
class Main {

	/**
	 * @var Main
	 */
	private static $instance;

	/**
	 * @var \TVA_Lesson|\TVA_Module|null
	 */
	private $active_object;

	/**
	 * @var \TVA_Lesson|null
	 */
	private $active_lesson;

	/**
	 * @var \TVA_Course_V2|null
	 */
	private $active_course;

	/**
	 * @var bool
	 */
	public static $is_editor_page = false;

	/**
	 * @var array
	 */
	private $_data = array();

	/**
	 * Main constructor.
	 */
	private function __construct() {
		$this->_includes();
	}

	public function __get( $key ) {
		$value = null;

		if ( isset( $this->_data[ $key ] ) ) {
			$value = $this->_data[ $key ];
		} elseif ( method_exists( $this, 'get_' . $key ) ) {
			$method_name = 'get_' . $key;

			$value = $this->$method_name();
		}

		return $value;
	}

	/**
	 * Singleton implementation for TCB_Custom_Fields_Shortcode
	 *
	 * @return Main
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		static::$is_editor_page = is_editor_page_raw( true );

		return self::$instance;
	}

	public function get_next_lesson_text() {
		if ( empty( $this->active_object ) ) {
			return '';
		}

		$course_nav_labels = $this->get_course_nav_labels();
		$label             = __( 'Next lesson', \TVA_Const::T );

		if ( ! empty( $course_nav_labels['next_lesson']['title'] ) ) {
			$label = $course_nav_labels['next_lesson']['title'];
		}

		if ( $this->active_object instanceof \TVA_Lesson && $this->get_active_lesson()->is_last_visible_lesson() ) {

			$label = __( 'To course page', \TVA_Const::T );

			if ( ! empty( $course_nav_labels['to_course_page']['title'] ) ) {
				$label = $course_nav_labels['to_course_page']['title'];
			}
		}

		return $label;
	}

	public function get_prev_lesson_text() {
		if ( empty( $this->active_object ) ) {
			return '';
		}

		$course_nav_labels = $this->get_course_nav_labels();
		$label             = __( 'Next lesson', \TVA_Const::T );

		if ( ! empty( $course_nav_labels['prev_lesson']['title'] ) ) {
			$label = $course_nav_labels['prev_lesson']['title'];
		}

		if ( $this->get_active_lesson()->is_first_visible_lesson() ) {

			$label = __( 'To course page', \TVA_Const::T );

			if ( ! empty( $course_nav_labels['to_course_page']['title'] ) ) {
				$label = $course_nav_labels['to_course_page']['title'];
			}
		}

		return $label;
	}

	public function get_next_lesson_link() {
		$url = 'javascript:void(0);';

		if ( empty( $this->active_object ) ) {
			return $url;
		}

		$next_lesson = $this->active_object instanceof \TVA_Module ? $this->get_active_lesson() : $this->get_active_lesson()->get_next_visible_lesson( true );

		if ( $next_lesson instanceof \TVA_Lesson ) {
			$url = $next_lesson->get_link();

			if ( ! $this->active_object instanceof \TVA_Module ) {
				/**
				 * @var \TVA_Module $next_lesson_module
				 */
				$next_lesson_module = $next_lesson->get_parent_by_type( \TVA_Const::MODULE_POST_TYPE );

				if ( ! empty( $next_lesson_module ) ) {
					$lesson_posts = $next_lesson_module->get_visible_lessons();

					/**
					 * @var \WP_Post $lesson_post
					 */
					$lesson_post = reset( $lesson_posts );

					if ( $lesson_post->ID === $next_lesson->ID ) {
						$url = $next_lesson_module->get_link();
					}
				}
			}

		} elseif ( $this->get_active_lesson()->is_last_visible_lesson() || empty( $next_lesson ) ) {
			$url = $this->active_course->get_link( false );
		}

		return $url;
	}


	public function get_previous_lesson_link() {
		$url = 'javascript:void(0);';

		if ( empty( $this->active_object ) ) {
			return $url;
		}

		$prev_lesson = $this->get_active_lesson()->get_previous_visible_lesson( true );

		if ( $prev_lesson instanceof \TVA_Lesson ) {
			$url = $prev_lesson->get_link();

			if ( ! $this->active_object instanceof \TVA_Module ) {
				/**
				 * @var \TVA_Module $prev_lesson_module
				 */
				$prev_lesson_module = $prev_lesson->get_parent_by_type( \TVA_Const::MODULE_POST_TYPE );

				if ( ! empty( $prev_lesson_module ) ) {
					$lesson_posts = $prev_lesson_module->get_visible_lessons();

					/**
					 * @var \WP_Post $lesson_post
					 */
					$lesson_post = end( $lesson_posts );

					if ( $lesson_post->ID === $prev_lesson->ID ) {
						$url = $prev_lesson_module->get_link();
					}
				}
			}

		} elseif ( $this->get_active_lesson()->is_first_visible_lesson() || empty( $prev_lesson ) ) {
			$url = $this->active_course->get_link( false );
		}

		return $url;
	}

	public function get_mark_as_complete_text() {
		$course_nav_labels = $this->get_course_nav_labels();

		return $course_nav_labels['mark_complete']['title'];
	}

	public function get_mark_as_complete_link() {
		return '#';
	}

	/**
	 * @return string
	 */
	public function get_mark_as_complete_next_text() {

		$course_nav_labels = $this->get_course_nav_labels();
		$text              = $course_nav_labels['mark_complete']['title'];

		if ( $this->active_object instanceof \TVA_Lesson && $this->active_object->is_completed() ) {
			$text = $this->get_next_lesson_text();
		}

		return $text;
	}

	public function get_mark_as_complete_next_link() {
		return '#';
	}


	/**
	 * @return mixed|string|null
	 */
	public function get_call_to_action_text() {
		return \TVA_Dynamic_Labels::get_course_cta( $this->active_course, 'single' );
	}

	/**
	 * @return string
	 */
	public function get_call_to_action_link() {
		$url = 'javascript:void(0);';

		if ( self::$is_editor_page ) {
			//We do this to avoid recursion for editor page
			return $url;
		}

		if ( empty( $this->active_object ) ) {

			/**
			 * If the course published lessons are all completed by the user the URL will be the first publish lesson
			 * Handles the case where users completed lessons are grater than published lessons
			 * (this can happen if the admin unpublished a lesson after user has completed it)
			 */
			if ( $this->active_course->published_lessons_count > 0 && $this->active_course->published_lessons_count <= count( tva_customer()->get_course_learned_lessons( $this->active_course->ID ) ) ) {
				$published_lessons = $this->active_course->get_ordered_visible_lessons();

				$next_lesson = $published_lessons[0];
			} else {
				$next_lesson = \TVA_Manager::get_next_user_uncompleted_visible_lesson( $this->active_course );
			}
		} else {
			$next_lesson = \TVA_Manager::get_next_user_uncompleted_visible_lesson( $this->active_course, $this->active_object );
		}

		if ( $next_lesson instanceof \TVA_Lesson ) {
			$url = $next_lesson->get_link();
		} else {
			$url = $this->active_course->get_link( false );
		}

		return $url;
	}


	/**
	 * Returns the index page link
	 *
	 * @return string
	 */
	public function get_index_page_link() {
		$url = tva_get_settings_manager()->factory( 'index_page' )->get_link();

		if ( ! empty( $url ) && ! empty( $_REQUEST['tva_skin_id'] ) && is_numeric( $_REQUEST['tva_skin_id'] ) ) {
			/**
			 * If tva_skin_id is present, it passes it to the URL so it will return the index page content corresponding to the active skin
			 */

			$url = add_query_arg( array(
				'tva_skin_id' => $_REQUEST['tva_skin_id'],
			), $url );
		}

		if ( empty( $url ) ) {
			$url = '#';
		}

		return $url;
	}

	/**
	 * Callback for course overview link shortcode
	 *
	 * @return string
	 */
	public function get_course_overview_link() {

		if ( static::$is_editor_page || empty( $this->active_course ) ) {
			//We do this to avoid recursion for editor page
			return 'javascript:void(0);';
		}

		return $this->active_course->get_link();
	}

	/**
	 * Cache the course navigation labels
	 *
	 * @return array|null
	 */
	public function get_course_nav_labels() {
		if ( ! isset( $this->course_nav_labels ) ) {
			$this->course_nav_labels = \TVA_Dynamic_Labels::get( 'course_navigation' );
		}

		return $this->course_nav_labels;
	}

	/**
	 * Set data to the class
	 * Used in ajax requests to get next lesson URLs
	 *
	 * @param \TVA_Lesson|\TVA_Module $object
	 *
	 * @return $this
	 */
	public function set_data( $object ) {
		$this->set_active_object( $object );
		$this->set_active_course( $object->get_course_v2() );

		return $this;
	}

	/**
	 * @param \TVA_Lesson|\TVA_Module $object
	 */
	public function set_active_object( $object ) {
		$this->active_object = $object;
	}

	/**
	 * @return \TVA_Lesson|\TVA_Module|null
	 */
	public function get_active_object() {
		return $this->active_object;
	}

	/**
	 * @param \TVA_Course_V2 $course
	 */
	public function set_active_course( $course ) {
		$this->active_course = $course;
	}

	/**
	 * @return \TVA_Course_V2|null
	 */
	public function get_active_course() {
		return $this->active_course;
	}

	/**
	 * @return \TVA_Lesson|null
	 */
	public function get_active_lesson() {

		if ( empty( $this->active_lesson ) ) {

			if ( $this->active_object instanceof \TVA_Module ) {
				$lessons = $this->active_object->get_visible_lessons();

				if ( ! empty( $lessons ) ) {
					$this->active_lesson = $lessons[0];
				}
			} else if ( $this->active_object instanceof \TVA_Lesson ) {
				$this->active_lesson = $this->active_object;
			} else {
				$first_published_lesson = $this->active_course->get_first_published_lesson();

				if ( ! empty( $first_published_lesson ) ) {
					$this->active_lesson = $first_published_lesson;
				}
			}
		}

		if ( empty( $this->active_lesson ) ) {
			$this->active_lesson = new \TVA_Lesson( \TVA\TTB\Apprentice_Wizard::get_object_or_demo_content( \TVA_Const::LESSON_POST_TYPE, 0, true ) );
		}

		return $this->active_lesson;
	}

	/**
	 * Returns dynamically a number type items with their labels (singular|plural form)
	 *
	 * Used in shortcodes for:
	 * - Apprentice Lesson List
	 * - Course List
	 * - Course Overview page
	 *
	 * @param \TVA_Post|array $parent_post_or_array
	 */
	public function get_children_count_with_label( $parent_post_or_array ) {

		if ( is_array( $parent_post_or_array ) ) {
			$posts = $parent_post_or_array;
		} else {
			$posts = $parent_post_or_array->get_direct_children();
		}

		/**
		 * On front-end we need to count only the published posts
		 */
		if ( ! Main::$is_editor_page ) {
			/**
			 * @var \TVA_Post $item
			 */
			$posts = array_values( array_filter( $posts, static function ( $item ) {
				return $item->is_published();
			} ) );
		}

		$return = Main::$is_editor_page ? '0 Items' : '';
		$labels = \TVA_Dynamic_Labels::get( 'course_structure' );
		if ( ! empty( $posts ) ) {
			$count  = count( $posts );
			$return = $count . ' ';

			if ( $posts[0] instanceof \TVA_Module ) {
				$suffix = 'module';
			} elseif ( $posts[0] instanceof \TVA_Chapter ) {
				$suffix = 'chapter';
			} else {
				$suffix = 'lesson';
			}

			$return .= $labels[ 'course_' . $suffix ][ $count === 1 ? 'singular' : 'plural' ];
		}

		return $return;
	}

	/**
	 * Returns the progress by type
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_progress_by_type( $type = 'course', $completed = - 1, $total = - 1 ) {

		if ( $completed === - 1 ) {
			$completed = $this->{$type . '_count_lessons_completed'};
		}

		if ( $total === - 1 ) {
			$total = $this->{$type . '_count_lessons'};
		}

		if ( is_int( $completed ) && is_int( $total ) && $total > 0 ) {
			$progress = ( $completed * 100 ) / $total;
		} else {
			$progress = '0';
		}

		return ( (int) $progress ) . '%';
	}

	/**
	 * Returns the total number of lessons inside the course
	 * Used inside the shortcode & localization
	 *
	 * @return int
	 */
	public function get_course_count_lessons() {
		if ( ! isset( $this->_data['course_count_lessons'] ) ) {
			$args = array();

			if ( ! Main::$is_editor_page ) {
				$args['post_status'] = array( 'publish' );
			}

			$this->_data['course_count_lessons'] = $this->active_course->count_lessons( $args );
		}

		return $this->_data['course_count_lessons'];
	}


	public function get_module_count_lessons() {
		if ( ! isset( $this->_data['module_count_lessons'] ) ) {
			$this->_data['module_count_lessons'] = $this->get_count_by_type( \TVA_Const::MODULE_POST_TYPE, false );
		}

		return $this->_data['module_count_lessons'];
	}

	public function get_chapter_count_lessons() {
		if ( ! isset( $this->_data['chapter_count_lessons'] ) ) {
			$this->_data['chapter_count_lessons'] = $this->get_count_by_type( \TVA_Const::CHAPTER_POST_TYPE, false );
		}

		return $this->_data['chapter_count_lessons'];
	}


	/**
	 * Returns the completed lessons count for the active course
	 * Used for localization and inside the shortcode
	 *
	 * @return int
	 */
	public function get_course_count_lessons_completed() {
		if ( ! isset( $this->_data['course_count_lessons_completed'] ) ) {
			if ( Main::$is_editor_page ) {
				$this->_data['course_count_lessons_completed'] = tva_count_completed_lessons( $this->active_course->get_all_lessons() );
			} else {
				$this->_data['course_count_lessons_completed'] = tva_count_completed_lessons( $this->active_course->get_published_lessons() );
			}
		}

		return $this->_data['course_count_lessons_completed'];
	}

	public function get_module_count_lessons_completed() {
		if ( ! isset( $this->_data['module_count_lessons_completed'] ) ) {
			$this->_data['module_count_lessons_completed'] = $this->get_count_by_type( \TVA_Const::MODULE_POST_TYPE, true );
		}

		return $this->_data['module_count_lessons_completed'];
	}

	public function get_chapter_count_lessons_completed() {
		if ( ! isset( $this->_data['chapter_count_lessons_completed'] ) ) {
			$this->_data['chapter_count_lessons_completed'] = $this->get_count_by_type( \TVA_Const::CHAPTER_POST_TYPE, true );
		}

		return $this->_data['chapter_count_lessons_completed'];
	}

	public function get_resources_label( $name, $type ) {
		$course_structure_labels = \TVA_Dynamic_Labels::get( 'course_structure' );

		return isset( $course_structure_labels[ $name ][ $type ] ) ? $course_structure_labels[ $name ][ $type ] : ucfirst( str_replace( '_', ' ', $name ) );
	}

	/**
	 * Counts the completed/uncompleted lessons for modules and chapters
	 *
	 * @param string $post_type
	 * @param false  $count_completed
	 *
	 * @return int|string
	 */
	private function get_count_by_type( $post_type = \TVA_Const::MODULE_POST_TYPE, $count_completed = false ) {
		$return = 0;

		if ( ! isset( $this->active_object ) ) {
			return $return;
		}

		/**
		 * @var \TVA_Module|\TVA_Chapter|null $post
		 */
		$post = $this->active_object->get_the_post()->post_type === $post_type ? $this->active_object : $this->active_object->get_parent_by_type( $post_type );

		if ( ! empty( $post ) ) {
			$lessons = $post->get_lessons();
			$return  = $count_completed ? tva_count_completed_lessons( $lessons ) : count( $lessons );
		}

		return $return;
	}

	private function _includes() {
		require_once __DIR__ . '/class-hooks.php';
		require_once __DIR__ . '/class-shortcodes.php';
	}
}

/**
 * Returns an instance of dynamic actions class
 *
 * @return Main
 */
function tcb_tva_dynamic_actions() {
	return Main::get_instance();
}

tcb_tva_dynamic_actions();

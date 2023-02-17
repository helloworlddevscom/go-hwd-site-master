<?php
/**
 * Created by PhpStorm.
 * User: dan bilauca
 * Date: 06-May-19
 * Time: 01:18 PM
 */

/**
 * Class TVA_Manager
 * - models manager
 */
class TVA_Manager {

	/**
	 * Holds a cache array for posts
	 *
	 * @var array
	 */
	public static $MANAGER_GET_POSTS_CACHE = array();

	/**
	 * Is called from functions from this class that are calling get_posts function
	 * Holds cache per request
	 *
	 * @param $args
	 *
	 * @return mixed
	 */
	public static function get_posts_from_cache( $args ) {
		$key = md5( json_encode( $args ) );

		if ( ! isset( static::$MANAGER_GET_POSTS_CACHE[ $key ] ) ) {
			static::$MANAGER_GET_POSTS_CACHE[ $key ] = get_posts( $args );
		}

		return static::$MANAGER_GET_POSTS_CACHE[ $key ];
	}

	/**
	 * Get the posts marked as demo ( modules and lessons ). Configurable via $args
	 *
	 * @return array
	 */
	public static function get_demo_posts( $args = [] ) {
		$args = (array) wp_parse_args( $args, [
			'numberposts' => - 1,
			'post_type'   => [ TVA_Const::LESSON_POST_TYPE, TVA_Const::MODULE_POST_TYPE ],
			'meta_key'    => 'tva_is_demo',
			'meta_value'  => 1,
		] );

		return static::get_posts_from_cache( $args );
	}

	/**
	 * @return array of WP_Term(s)
	 */
	public static function get_courses() {

		$courses = array();

		$args = array(
			'taxonomy'   => TVA_Const::COURSE_TAXONOMY,
			'hide_empty' => false,
			'meta_key'   => 'tva_order',
			'orderby'    => 'meta_value',
			'order'      => 'DESC',
		);

		$terms = get_terms( $args );

		if ( false === is_wp_error( $terms ) ) {
			$courses = $terms;
		}

		return $courses;
	}

	/**
	 * Gets and returns lessons at course level
	 *
	 * @param WP_Term $course
	 * @param array   $filters which will be passed to WP_Query
	 *
	 * @return array of WP_Posts
	 */
	public static function get_course_lessons( $course, $filters = array() ) {

		$lessons = array();

		if ( true === $course instanceof WP_Term ) {

			$_defaults = array(
				'posts_per_page' => - 1,
				'post_status'    => TVA_Post::$accepted_statuses,
				'post_type'      => array( TVA_Const::LESSON_POST_TYPE ),
				'meta_key'       => 'tva_lesson_order',
				'post_parent'    => 0,
				'tax_query'      => array(
					array(
						'taxonomy' => TVA_Const::COURSE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $course->term_id ),
						'operator' => 'IN',
					),
				),
				'orderby'        => 'meta_value_num', //because tva_order_item is int
				'order'          => 'ASC',
			);

			$args = wp_parse_args( $filters, $_defaults );

			$posts = static::get_posts_from_cache( $args );

			$lessons = $posts;
		}

		return $lessons;
	}

	/**
	 * Gets and return lessons at module level
	 *
	 * @param $module  WP_post
	 * @param $filters array
	 *
	 * @return array
	 */
	public static function get_module_lessons( $module, $filters = array() ) {

		$lessons = array();

		if ( true === $module instanceof WP_Post ) {
			$_defaults = array(
				'posts_per_page' => - 1,
				'post_status'    => TVA_Post::$accepted_statuses,
				'post_type'      => array( TVA_Const::LESSON_POST_TYPE ),
				'meta_key'       => 'tva_lesson_order',
				'post_parent'    => $module->ID,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			);

			$args = wp_parse_args( $filters, $_defaults );

			$posts = static::get_posts_from_cache( $args );;

			$lessons = $posts;
		}

		return $lessons;
	}

	/**
	 * Get all lessons of a module, even the module has chapters
	 *
	 * @param WP_Post $module
	 * @param array   $filters
	 *
	 * @return array
	 */
	public static function get_all_module_lessons( $module, $filters = array() ) {

		$lessons = array();

		if ( true === $module instanceof WP_Post ) {

			$lessons = self::get_module_lessons( $module, $filters );

			/**
			 * check in chapters
			 */
			if ( empty( $lessons ) ) {

				$chapters = self::get_module_chapters( $module );

				foreach ( $chapters as $chapter ) {

					$chapter_lessons = self::get_chapter_lessons( $chapter, $filters );
					$lessons         = array_merge( $lessons, $chapter_lessons );
				}
			}
		}

		return $lessons;
	}

	/**
	 * @param WP_Post $module
	 * @param array   $args
	 *
	 * @return WP_Post[]
	 */
	public static function get_module_chapters( $module, $args = array() ) {

		$chapters = array();

		if ( true === $module instanceof WP_Post ) {
			$defaults = array(
				'posts_per_page' => - 1,
				'post_status'    => TVA_Post::$accepted_statuses,
				'post_type'      => array( TVA_Const::CHAPTER_POST_TYPE ),
				'meta_key'       => 'tva_chapter_order',
				'post_parent'    => $module->ID,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			);
			$args     = wp_parse_args( $args, $defaults );
			$posts    = static::get_posts_from_cache( $args );;
			$chapters = $posts;
		}

		return $chapters;
	}

	/**
	 * Gets and return lessons at chapter level
	 *
	 * @param $chapter WP_Post
	 * @param $filters array
	 *
	 * @return array
	 */
	public static function get_chapter_lessons( $chapter, $filters = array() ) {

		$lessons = array();

		if ( true === $chapter instanceof WP_Post ) {
			$_defaults = array(
				'posts_per_page' => - 1,
				'post_status'    => TVA_Post::$accepted_statuses,
				'post_type'      => array( TVA_Const::LESSON_POST_TYPE ),
				'meta_key'       => 'tva_lesson_order',
				'post_parent'    => $chapter->ID,
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			);
			$args      = wp_parse_args( $filters, $_defaults );
			$posts     = static::get_posts_from_cache( $args );

			$lessons = $posts;
		}

		return $lessons;
	}

	/**
	 * All lessons for a Course
	 *
	 * @param $course  WP_Term
	 * @param $filters array
	 *
	 * @return array
	 */
	public static function get_all_lessons( $course, $filters = array() ) {

		$lessons = array();

		if ( true === $course instanceof WP_Term ) {

			/**
			 * get direct lessons
			 */
			$posts = self::get_course_lessons( $course, $filters );

			/**
			 * if there are no direct course lessons
			 */
			if ( empty( $posts ) ) { //check modules and chapters

				$modules = self::get_course_modules( $course );

				foreach ( $modules as $module ) {

					$module_chapters = self::get_module_chapters( $module );

					foreach ( $module_chapters as $module_chapter ) {
						$posts = array_merge( $posts, self::get_chapter_lessons( $module_chapter, $filters ) );
					}

					if ( empty( $module_chapters ) ) {
						$posts = array_merge( $posts, self::get_module_lessons( $module, $filters ) );
					}
				}

				if ( empty( $modules ) ) {

					$course_chapters = self::get_course_chapters( $course );

					foreach ( $course_chapters as $course_chapter ) {
						$posts = array_merge( $posts, self::get_chapter_lessons( $course_chapter, $filters ) );
					}
				}
			}

			foreach ( $posts as $post ) {
				$post->order = get_post_meta( $post->ID, 'tva_lesson_order', true );
			}

			$lessons = $posts;
		}

		return $lessons;
	}

	/**
	 * Gets and returns the modules of a course
	 *
	 * @param WP_Term $course
	 * @param array   $filters
	 *
	 * @return array
	 */
	public static function get_course_modules( $course, $filters = array() ) {

		$modules = array();

		if ( true === $course instanceof WP_Term ) {
			$args    = array(
				'posts_per_page' => - 1,
				'post_type'      => array( TVA_Const::MODULE_POST_TYPE ),
				'post_status'    => TVA_Post::$accepted_statuses,
				'meta_key'       => 'tva_module_order',
				'post_parent'    => 0,
				'tax_query'      => array(
					array(
						'taxonomy' => TVA_Const::COURSE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $course->term_id ),
						'operator' => 'IN',
					),
				),
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			);
			$modules = static::get_posts_from_cache( wp_parse_args( $filters, $args ) );
		}

		return $modules;
	}

	/**
	 * Gets chapters at course level
	 *
	 * @param WP_Term $course
	 *
	 * @return array
	 */
	public static function get_course_chapters( $course ) {

		$chapters = array();

		if ( true === $course instanceof WP_Term ) {
			$args = array(
				'posts_per_page' => - 1,
				'post_type'      => array( TVA_Const::CHAPTER_POST_TYPE ),
				'post_status'    => TVA_Post::$accepted_statuses,
				'meta_key'       => 'tva_chapter_order',
				'post_parent'    => 0,
				'tax_query'      => array(
					array(
						'taxonomy' => TVA_Const::COURSE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $course->term_id ),
						'operator' => 'IN',
					),
				),
				'orderby'        => 'meta_value_num',
				'order'          => 'ASC',
			);

			$chapters = static::get_posts_from_cache( $args );
		}

		return $chapters;
	}

	/**
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public static function get_children( $post ) {

		$children           = array();
		$allowed_post_types = array(
			TVA_Const::CHAPTER_POST_TYPE,
			TVA_Const::MODULE_POST_TYPE,
		);

		if ( true === $post instanceof WP_Post && true === in_array( $post->post_type, $allowed_post_types ) ) {

			switch ( $post->post_type ) {

				case TVA_Const::CHAPTER_POST_TYPE:
					$children = self::get_chapter_lessons( $post );
					break;

				case TVA_Const::MODULE_POST_TYPE:
					$children = self::get_module_chapters( $post );

					if ( empty( $children ) ) {
						$children = self::get_module_lessons( $post );
					}
					break;
			}
		}

		return $children;
	}

	/**
	 * Review status for a post
	 * - based on published children
	 * - updates status for its parent
	 *
	 * @param int|WP_Post $post
	 */
	public static function review_status( $post ) {

		if ( is_numeric( $post ) ) {
			$post = get_post( $post );
		}

		if ( false === $post instanceof WP_Post ) {
			return;
		}

		/**
		 * clear cache before updating children, to not mess up the course structure
		 */
		static::$MANAGER_GET_POSTS_CACHE = array();

		$_has_children = self::has_published_children( $post );

		$new_status = $_has_children ? 'publish' : 'draft';

		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => $new_status,
			)
		);

		if ( $post->post_parent ) {
			self::review_status( get_post( $post->post_parent ) );
		}
	}

	public static function has_published_children( $post ) {

		$_has = false;

		$children = self::get_children( $post );

		foreach ( $children as $child ) {
			if ( $child->post_status === 'publish' ) {
				$_has = true;
				break;
			}
		}

		return $_has;
	}

	/**
	 * Based on $parent review its children order
	 *
	 * @param int|WP_Post $parent
	 */
	public static function review_children_order( $parent ) {

		if ( false === $parent instanceof WP_Post ) {
			$parent = get_post( (int) $parent );
		}

		if ( false === $parent instanceof WP_Post ) {
			return;
		}

		$post_order = $parent->{$parent->post_type . '_order'};

		$children = TVA_Manager::get_children( $parent );

		/**
		 * @var int      $index
		 * @var  WP_Post $child
		 */
		foreach ( $children as $index => $child ) {

			$child_order_meta = $child->post_type . '_order';

			$new_order = $post_order . $index;

			update_post_meta( $child->ID, $child_order_meta, $new_order );

			self::review_children_order( $child );
		}
	}

	/**
	 * Based on post returns post's wp_term instance
	 *
	 * @param WP_Post $post
	 *
	 * @return WP_Term|null
	 */
	public static function get_post_term( $post ) {

		$term = null;

		if ( true === $post instanceof WP_Post ) {
			$terms = wp_get_post_terms( $post->ID, TVA_Const::COURSE_TAXONOMY );

			$term = ! empty( $terms ) ? $terms[0] : null;
		}

		return $term;
	}

	/**
	 * Fetches all the course's posts and returns it's IDs as array
	 *
	 * @param int $course_id
	 *
	 * @return array
	 */
	public static function get_course_item_ids( $course_id ) {

		$course_id = (int) $course_id;

		if ( ! $course_id ) {
			return array();
		}

		$args = array(
			'posts_per_page' => - 1,
			'post_type'      => array( TVA_Const::LESSON_POST_TYPE, TVA_Const::CHAPTER_POST_TYPE, TVA_Const::MODULE_POST_TYPE ),
			'post_status'    => TVA_Post::$accepted_statuses,
			'tax_query'      => array(
				array(
					'taxonomy' => TVA_Const::COURSE_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array( $course_id ),
					'operator' => 'IN',
				),
			),
			'order'          => 'ASC',
		);

		/** @var WP_Post[] $posts */
		$posts = static::get_posts_from_cache( $args );
		$ids   = array();

		if ( $posts ) {
			foreach ( $posts as $post ) {
				$ids[] = $post->ID;
			}
		}

		return $ids;
	}

	/**
	 * Loops through the whole lists of lessons and exclude a specific amount of ids from the beginning
	 * Lessons parents are also excluded(pushed) into excluded ids
	 *
	 * @param int $course_id
	 *
	 * @return array with Post IDs which are excluded: contains TVA_Module, TVA_Chapter, TVA_Lessons
	 */
	public static function get_excluded_course_ids( $course_id ) {

		$course_id = (int) $course_id;

		$course   = get_term( $course_id );
		$excluded = (int) get_term_meta( $course_id, 'tva_excluded', true );

		if ( ! $excluded || false === $course instanceof WP_Term ) {
			return array();
		}

		$lessons = self::get_all_lessons( $course );
		$ids     = array();

		/**
		 * loop only for exclusions
		 */
		for ( $i = 0; $i < $excluded; $i ++ ) {

			if ( ! isset( $lessons[ $i ] ) || false === $lessons[ $i ] instanceof WP_Post ) {
				break;
			}

			$lesson = TVA_Post::factory( $lessons[ $i ] );

			/**
			 * Parent can be Nothing / Module / Chapter
			 */
			$parent = $lesson->get_parent();
			if ( $parent->ID ) {
				//exclude module/chapter
				$ids[] = $parent->ID;
			}

			/**
			 * If Parent is Chapter we should get the Module's ID so that it can be set to MM access table
			 * Module Page can be accessed in frontend by visitors
			 */
			$module = $parent && $parent instanceof TVA_Chapter ? $parent->get_parent() : null;
			if ( $module ) {
				//exclude module
				$ids[] = $module->ID;
			}

			/**
			 * Push lesson to excluded IDs
			 */
			$ids[] = $lessons[ $i ]->ID;
		}

		return $ids;
	}

	/**
	 * Get all modules, chapters, lessons for a course as a flat array
	 *
	 * @param WP_Term     $course
	 * @param WP_Post     $post_parent optional, if set it will only get child items for that $post
	 * @param string|null $by_column   if sent, use this column as array keys
	 *
	 * @return WP_Post[]
	 */
	public static function get_all_content( $course, $post_parent = null, $by_column = null ) {
		$items = array();

		if ( true === $course instanceof WP_Term ) {
			$args = array(
				'posts_per_page' => - 1,
				'post_type'      => array( TVA_Const::MODULE_POST_TYPE, TVA_Const::CHAPTER_POST_TYPE, TVA_Const::LESSON_POST_TYPE ),
				'post_status'    => TVA_Post::$accepted_statuses,
				'tax_query'      => array(
					array(
						'taxonomy' => TVA_Const::COURSE_TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $course->term_id ),
						'operator' => 'IN',
					),
				),
			);
			if ( ! empty( $post_parent ) ) {
				$args['post_parent'] = $post_parent->ID;
			}
			$items = static::get_posts_from_cache( $args );
		}

		if ( $by_column && ! empty( $items[0]->{$by_column} ) ) {
			$result = [];
			foreach ( $items as $item ) {
				$result[ $item->{$by_column} ] = $item;
			}

			$items = $result;
		}

		return $items;
	}

	/**
	 * Returns the next user uncompleted published lesson
	 *
	 * @param TVA_Course_V2              $course
	 * @param null|TVA_Module|TVA_Lesson $active_object
	 * @param boolean                    $check_active
	 *
	 * @return TVA_Lesson|false
	 */
	public static function get_next_user_uncompleted_published_lesson( $course, $active_object = null, $check_active = false ) {

		if ( count( $course->get_ordered_published_lessons() ) === 0 ) {
			//All published lessons are cached on the course object
			return false;
		}

		if ( empty( $active_object ) ) {
			$lesson = $course->get_first_published_lesson();

			if ( $lesson instanceof TVA_Lesson ) {
				return static::get_next_user_uncompleted_published_lesson( $course, $lesson, true );
			} else {
				return false;
			}
		}

		if ( $active_object instanceof TVA_Module ) {
			$lesson = $active_object->get_first_lesson();

			if ( $lesson instanceof TVA_Lesson ) {
				return static::get_next_user_uncompleted_published_lesson( $course, $lesson, true );
			} else {
				return false;
			}
		}

		if ( $active_object instanceof TVA_Lesson ) {

			if ( ! $check_active || $active_object->is_completed() ) {

				$next_lesson = $active_object->get_next_published_lesson( true );

				if ( empty( $next_lesson ) ) {
					//No next lesson -> last lesson in the course
					return false;
				}

				return static::get_next_user_uncompleted_published_lesson( $course, $next_lesson, true );
			} else {
				return $active_object;
			}
		}

		return false;
	}

	/**
	 * Returns the next user uncompleted visible lesson
	 *
	 * @param TVA_Course_V2              $course
	 * @param null|TVA_Module|TVA_Lesson $active_object
	 * @param boolean                    $check_active
	 *
	 * @return TVA_Lesson|false
	 */
	public static function get_next_user_uncompleted_visible_lesson( $course, $active_object = null, $check_active = false ) {

		if ( count( $course->get_ordered_visible_lessons() ) === 0 ) {
			//All published lessons are cached on the course object
			return false;
		}

		if ( empty( $active_object ) ) {
			$lesson = $course->get_first_visible_lesson();

			if ( $lesson instanceof TVA_Lesson ) {
				return static::get_next_user_uncompleted_visible_lesson( $course, $lesson, true );
			} else {
				return false;
			}
		}

		if ( $active_object instanceof TVA_Module ) {
			$lesson = $active_object->get_first_visible_lesson();

			if ( $lesson instanceof TVA_Lesson ) {
				return static::get_next_user_uncompleted_visible_lesson( $course, $lesson, true );
			} else {
				return false;
			}
		}

		if ( $active_object instanceof TVA_Lesson ) {

			if ( ! $check_active || $active_object->is_completed() ) {

				$next_lesson = $active_object->get_next_visible_lesson( true );

				if ( empty( $next_lesson ) ) {
					//No next lesson -> last lesson in the course
					return false;
				}

				return static::get_next_user_uncompleted_visible_lesson( $course, $next_lesson, true );
			} else {
				return $active_object;
			}
		}

		return false;
	}

	/**
	 * @param array $filters
	 *                      - 's' string
	 *                      - 'lesson_type' string from [text,video,audio]
	 *                      - 'post_parent' int|int[]
	 *                      - 'courses' array of int
	 *                      - 'author' array of int
	 *
	 * @return int[]|WP_Post[]
	 */
	public static function search_for_course_items( $filters ) {

		$_defaults = array(
			'posts_per_page' => - 1,
			'post_status'    => TVA_Post::$accepted_statuses,
		);

		$parsed = array();

		if ( ! empty( $filters['order'] ) ) {
			$parsed = array_merge( $parsed, $filters['order'] );
		}

		if ( ! empty( $filters['post_type'] ) ) {
			$parsed['post_type'] = $filters['post_type'];
		}

		//search key
		if ( ! empty( $filters['s'] ) ) {
			$parsed['s'] = sanitize_text_field( $filters['s'] );
		}

		//post parent
		if ( ! empty( $filters['post_parent'] ) ) {
			$parsed['post_parent'] = $filters['post_parent'];
		}

		//lesson type: text/video/audio
		if ( ! empty( $filters['lesson_type'] ) && true === in_array( $filters['lesson_type'], TVA_Lesson::$types, true ) ) {
			$parsed['meta_key']   = 'tva_lesson_type';
			$parsed['meta_value'] = $filters['lesson_type'];
		}

		//course/s
		if ( ! empty( $filters['courses'] ) && true === is_array( $filters['courses'] ) ) {
			$parsed['tax_query'] = array(
				array(
					'taxonomy' => TVA_Const::COURSE_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $filters['courses'] ),
					'operator' => 'IN',
				),
			);
		}

		if ( ! empty( $filters['author'] ) && true === is_array( $filters['author'] ) ) {
			$parsed['author__in'] = $filters['author'];
		}

		$parsed['meta_query'] = [
			'demo_content' => [
				'key'     => 'tva_is_demo',
				'compare' => 'NOT EXISTS',
			],
		];

		$args = wp_parse_args( $_defaults, $parsed );

		return static::get_posts_from_cache( $args );
	}

	/**
	 * Search for lesson by various filters
	 *
	 * @param $filters
	 *
	 * @return int[]|WP_Post[]
	 */
	public static function search_for_lessons( $filters ) {

		$filters['post_type'] = array( TVA_Const::LESSON_POST_TYPE );

		//lesson type: text/video/audio
		if ( ! empty( $filters['lesson_type'] ) && true === in_array( $filters['lesson_type'], TVA_Lesson::$types, true ) ) {
			$parsed['meta_key']   = 'tva_lesson_type';
			$parsed['meta_value'] = $filters['lesson_type'];
		}

		return self::search_for_course_items( $filters );
	}

	/**
	 * Search for modules by various filters
	 *
	 * @param $filters
	 *
	 * @return int[]|WP_Post[]
	 */
	public static function search_for_modules( $filters ) {

		$filters['post_type'] = array( TVA_Const::MODULE_POST_TYPE );
		$filters['order']     = array(
			'meta_key' => TVA_Const::MODULE_POST_TYPE . '_order',
			'orderby'  => 'meta_value_num',
			'order'    => 'DESC',
		);

		return self::search_for_course_items( $filters );
	}

	/**
	 * Search for chapters by various filters
	 *
	 * @param $filters
	 *
	 * @return int[]|WP_Post[]
	 */
	public static function search_for_chapters( $filters ) {

		$filters['post_type'] = array( TVA_Const::CHAPTER_POST_TYPE );

		return self::search_for_course_items( $filters );
	}
}

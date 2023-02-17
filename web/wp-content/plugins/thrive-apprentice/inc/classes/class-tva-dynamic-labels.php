<?php

class TVA_Dynamic_Labels {
	const OPT = 'tva_dynamic_labels';

	/**
	 * Holds a cache of logged user's relation with an array of courses
	 *
	 * @var array
	 */
	protected static $USER_COURSE_CACHE = array();

	/**
	 * Available options for users that have access to the course
	 *
	 * @return array
	 */
	public static function get_user_switch_contexts() {
		return array(
			'not_started' => __( 'If user has access but not started the course', TVA_Const::T ),
			'in_progress' => __( 'If user has started the course', TVA_Const::T ),
			'finished'    => __( 'If user has finished the course', TVA_Const::T ),
		);
	}

	/**
	 * Available options for CTA buttons depending on user context and relation to the course
	 *
	 * @return array
	 */
	public static function get_cta_contexts() {
		return array(
			'view'        => __( 'View course details', TVA_Const::T ),
			'not_started' => __( 'If user has access to the course but not started it yet', TVA_Const::T ),
			'in_progress' => __( 'If user is midway through a course', TVA_Const::T ),
			'finished'    => __( 'If user has finished a course', TVA_Const::T ),
		);
	}

	/**
	 * Available options for Course type labels depending on user context and relation to the course
	 *
	 * @return array
	 */
	public static function get_course_type_label_contexts() {
		return array(
			'guide'            => __( 'A course that consists of only one lesson', TVA_Const::T ),
			'text'             => __( 'A course that contains only text content', TVA_Const::T ),
			'audio'            => __( 'A course that contains only audio', TVA_Const::T ),
			'video'            => __( 'A course that contains only video', TVA_Const::T ),
			'audio_text'       => __( 'A course that contains audio and text', TVA_Const::T ),
			'video_text'       => __( 'A course that contains video and text', TVA_Const::T ),
			'video_audio'      => __( 'A course that contains video and audio', TVA_Const::T ),
			'video_audio_text' => __( 'A course that contains video, audio and text', TVA_Const::T ),
		);
	}

	/**
	 * Available options for Course navigation labels
	 *
	 * @return array
	 */
	public static function get_course_navigation_contexts() {
		return array(
			'next_lesson'    => __( 'Navigate to next lesson in the course', TVA_Const::T ),
			'prev_lesson'    => __( 'Navigate to the previous lesson in the course', TVA_Const::T ),
			'to_course_page' => __( 'Navigate to the course overview', TVA_Const::T ),
			'mark_complete'  => __( 'Mark lesson complete', TVA_Const::T ),
		);
	}

	/**
	 * Available options for Course navigation warnings
	 *
	 * @return array
	 */
	public static function get_course_navigation_warnings() {
		return array(
			'mark_complete_requirements' => __( 'When lesson contains progress requirements', TVA_Const::T ),
		);
	}

	/**
	 * Available options for Course structure labels
	 *
	 * @return array
	 */
	public static function get_course_structure_contexts() {
		return array(
			'course_lesson'      => __( 'Content type that contains lesson content', TVA_Const::T ),
			'course_chapter'     => __( 'Content type that contains a group of only lessons', TVA_Const::T ),
			'course_module'      => __( 'Content type that contains a group of chapters and lessons', TVA_Const::T ),
			'course_resources'   => __( 'Supporting resources (files and links) for lessons', TVA_Const::T ),
			'resources_open'     => __( 'Button label to open a resource', TVA_Const::T ),
			'resources_download' => __( 'Button label to download a resource', TVA_Const::T ),
		);
	}

	/**
	 * Available options for Course structure labels
	 *
	 * @return array
	 */
	public static function get_course_progress_contexts() {
		return array(
			'label'            => __( 'Label for progress', TVA_Const::T ),
			'not_started'      => __( 'A course that has not been started yet', TVA_Const::T ),
			'in_progress'      => __( 'A course that is in progress', TVA_Const::T ),
			'finished'         => __( 'A course that is finished', TVA_Const::T ),
			'lesson_completed' => __( 'Lesson completed notification', TVA_Const::T ),
		);
	}

	/**
	 * Store the settings to the wp_options table
	 *
	 * @param array $settings
	 *
	 * @return array the saved array of settings
	 */
	public static function save( $settings ) {
		$defaults = static::defaults();
		$settings = array_replace_recursive( $defaults, $settings );

		/**
		 * Make sure no extra keys are saved.
		 */
		$settings = array_intersect_key( $settings, $defaults );

		update_option( static::OPT, $settings );

		return $settings;
	}

	/**
	 * Get the stored settings, with some default values
	 *
	 * @param string $key allows retrieving only a single setting
	 *
	 * @return bool|mixed|void
	 */
	public static function get( $key = null ) {
		$defaults = static::defaults();

		$db_settings = $settings = get_option( static::OPT, $defaults );

		if ( ! isset( $settings['course_labels'] ) ) {
			$settings['course_labels'] = static::get_course_type_labels();
		} else {
			$settings['course_labels'] = $settings['course_labels'] + static::get_course_type_labels();
		}

		if ( ! isset( $settings['course_navigation'] ) ) {
			$settings['course_navigation'] = static::get_course_navigation_labels();
		} else {
			$settings['course_navigation'] = $settings['course_navigation'] + static::get_course_navigation_labels();
		}

		if ( ! isset( $settings['course_structure'] ) ) {
			$settings['course_structure'] = static::get_course_structure_labels();
		} else {
			$settings['course_structure'] = $settings['course_structure'] + static::get_course_structure_labels();
		}

		if ( ! isset( $settings['course_progress'] ) ) {
			$settings['course_progress'] = static::get_course_progress_labels();
		} else {
			$settings['course_progress'] = $settings['course_progress'] + static::get_course_progress_labels();
		}

		if ( $db_settings !== $settings ) {
			update_option( static::OPT, $settings );
		}

		if ( isset( $key ) ) {
			return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
		}

		return $settings;
	}

	/**
	 * Check if a dynamic label applies to the $course
	 * If nothing found, just output the regular selected label
	 *
	 * @param WP_Term|TVA_Course_V2 $course
	 *
	 * @return null|array containing label ID, color and title
	 */
	public static function get_course_label( $course ) {
		if ( $course instanceof WP_Term ) {
			$course = new TVA_Course_V2( $course );
		}

		$settings = static::get();

		if ( ! empty( $settings['switch_labels'] ) && get_current_user_id() && $course->has_access() ) {
			/* switch label based on user's relation to the course */
			/* check if user has started the course */
			$label_key = static::get_user_course_context( $course );

			/**
			 * This should always exist, however, just to make sure no warnings will be generated, perform this extra check
			 */
			$label = isset( $settings['labels'][ $label_key ] ) ? $settings['labels'][ $label_key ] : array();

			if ( empty( $label ) || $label['opt'] === 'hide' ) {
				return null;
			}

			/**
			 * For public courses, show the "In progress" or "Completed" labels for logged users where possible
			 * Do not show the "Not started yet" label
			 */
			$should_hide = $label_key !== 'in_progress' && $label_key !== 'finished';
			if ( ! $course->is_private() && ( $should_hide || $label['opt'] !== 'show' ) ) {
				return null;
			}

			if ( $label['opt'] === 'show' ) {
				$label['ID'] = $label_key;

				return $label;
			}
		}

		/* at this point, return the selected course label - no dynamic label found, or the dynamic label has the "nochange" option selected. */
		return tva_get_labels( array( 'ID' => $course->get_label_id() ) );
	}

	/**
	 * Get the course CTA button text
	 *
	 * @param WP_Term|TVA_Course_V2 $course
	 * @param string                $context
	 * @param string                $default string to use in case no suitable CTA text is found
	 *
	 * @return mixed
	 */
	public static function get_course_cta( $course, $context = 'list', $default = null ) {
		/**
		 * The current implementation only supports the `list` $context. Moving forward, other contexts will be added.
		 * on the list of courses, the default text should be the one defined for 'view'.
		 * only possible options are:
		 *      - view
		 *      - not_started
		 *      - in_progress
		 *      - finished
		 */
		$button_key = 'view';
		if ( $context === 'list' ) {
			if ( get_current_user_id() && $course->has_access() ) {
				$button_key = static::get_user_course_context( $course );
			} else {
				$button_key = 'view';
			}
		} elseif ( $context === 'single' ) {
			$button_key = static::get_user_course_context( $course );
		}

		return static::get_cta_label( $button_key, $default );
	}

	/**
	 * Get the logged user's relation to the course. This does not check for access. To be used when user has access to a course
	 *
	 * @param WP_Term|TVA_Course_V2 $course
	 *
	 * @return string 'not_started' / 'in_progress' / 'finished'
	 */
	public static function get_user_course_context( $course ) {
		if ( $course instanceof WP_Term ) {
			$course = new TVA_Course_V2( $course );
		}

		$course_id = $course->get_id();
		if ( ! isset( static::$USER_COURSE_CACHE[ $course_id ] ) ) {
			$lessons_learnt = TVA_Shortcodes::get_learned_lessons();

			if ( empty( $lessons_learnt[ $course_id ] ) ) {
				static::$USER_COURSE_CACHE[ $course_id ] = 'not_started';
			} else {
				static::$USER_COURSE_CACHE[ $course_id ] = count( $lessons_learnt[ $course->get_id() ] ) === $course->published_lessons_count ? 'finished' : 'in_progress';
			}
		}

		return static::$USER_COURSE_CACHE[ $course_id ];
	}

	/**
	 * Output the CSS required for each dynamic label
	 */
	public static function output_css() {
		$options = static::get();
		if ( ! empty( $options['switch_labels'] ) ) {
			foreach ( $options['labels'] as $id => $label ) {
				echo sprintf(
					'.tva_members_only-%1$s { background: %2$s }.tva_members_only-%1$s:before { border-color: %2$s transparent transparent transparent }',
					$id,
					$label['color']
				);
			}
		}
	}

	/**
	 * Return the CTA set for a user context ($key)
	 *
	 * @param string $key     identifier for the value
	 * @param null   $default default value to return if nothing is found
	 *
	 * @return string
	 */
	public static function get_cta_label( $key, $default = null ) {
		$buttons = static::get( 'buttons' );

		if ( empty( $default ) ) {
			$default = $buttons['view']['title'];
		}

		return isset( $buttons[ $key ]['title'] ) ? $buttons[ $key ]['title'] : $default;
	}

	/**
	 * Get the default values for dynamic settings
	 *
	 * @return array
	 */
	public static function defaults() {
		$template = TVA_Setting::get( 'template' );

		//backwards compat -> "Start course" should read from an existing setting
		$defaults = array(
			'start_course' => isset( $template['start_course'] ) ? $template['start_course'] : TVA_Const::TVA_START,
		);

		return array(
			'switch_labels'     => false,
			'labels'            => array(
				'not_started' => array(
					'opt'   => 'show',
					'title' => __( 'Not started yet', TVA_Const::T ),
					'color' => '#58a545',
				),
				'in_progress' => array(
					'opt'   => 'show',
					'title' => __( 'In progress', TVA_Const::T ),
					'color' => '#58a545',
				),
				'finished'    => array(
					'opt'   => 'show',
					'title' => __( 'Course complete!', TVA_Const::T ),
					'color' => '#58a545',
				),
			),
			'buttons'           => array(
				'view'        => array(
					'title' => __( 'Learn more', TVA_Const::T ),
				),
				'not_started' => array(
					'title' => $defaults['start_course'],
				),
				'in_progress' => array(
					'title' => __( 'Continue course', TVA_Const::T ),
				),
				'finished'    => array(
					'title' => __( 'Revisit the course', TVA_Const::T ),
				),
			),
			'course_labels'     => static::get_course_type_labels( $template ),
			'course_navigation' => static::get_course_navigation_labels( $template ),
			'course_structure'  => static::get_course_structure_labels( $template ),
			'course_progress'   => static::get_course_progress_labels( $template ),
		);
	}

	/**
	 * Get the values for course type labels
	 * this is also used for backwards compatibility
	 *
	 * @param TVA_Setting $template
	 *
	 * @return array[]
	 */
	public static function get_course_type_labels( $template = null ) {
		if ( ! $template ) {
			$template = TVA_Setting::get( 'template' );
		}

		return array(
			'guide'            => array(
				'title' => $template['course_type_guide'],
			),
			'text'             => array(
				'title' => $template['course_type_text'],
			),
			'audio'            => array(
				'title' => $template['course_type_audio'],
			),
			'video'            => array(
				'title' => $template['course_type_video'],
			),
			'audio_text'       => array(
				'title' => $template['course_type_audio_text_mix'],
			),
			'video_text'       => array(
				'title' => $template['course_type_video_text_mix'],
			),
			'video_audio'      => array(
				'title' => $template['course_type_video_audio_mix'],
			),
			'video_audio_text' => array(
				'title' => $template['course_type_big_mix'],
			),
		);
	}

	/**
	 * Get the values for course navigation labels
	 * this is also used for backwards compatibility
	 *
	 * @param TVA_Setting $template
	 *
	 * @return array[]
	 */
	public static function get_course_navigation_labels( $template = null ) {
		if ( ! $template ) {
			$template = TVA_Setting::get( 'template' );
		}

		return array(
			'next_lesson'                => array(
				'title' => $template['next_lesson'],
			),
			'prev_lesson'                => array(
				'title' => $template['prev_lesson'],
			),
			'to_course_page'             => array(
				'title' => $template['to_course_page'],
			),
			'mark_complete'              => array(
				'title' => __( 'Mark lesson complete', TVA_Const::T ),
			),
			'mark_complete_requirements' => array(
				'title' => __( 'You must complete all requirements for this lesson in order to mark it as complete', TVA_Const::T ),
			),
		);
	}

	/**
	 * Get the values for course structure labels
	 * this is also used for backwards compatibility
	 *
	 * @param TVA_Setting $template
	 *
	 * @return array[]
	 */
	public static function get_course_structure_labels( $template = null ) {
		if ( ! $template ) {
			$template = TVA_Setting::get( 'template' );
		}

		return array(
			'course_lesson'      => array(
				'singular' => $template['course_lesson'],
				'plural'   => $template['course_lessons'],
			),
			'course_chapter'     => array(
				'singular' => $template['course_chapter'],
				'plural'   => $template['course_chapters'],
			),
			'course_module'      => array(
				'singular' => $template['course_module'],
				'plural'   => $template['course_modules'],
			),
			'course_resources'   => array(
				'plural' => isset( $template['resources_label'] ) ? $template['resources_label'] : 'Resources',
			),
			'resources_open'     => array(
				'singular' => isset( $template['resources_open'] ) ? $template['resources_open'] : 'Open',
			),
			'resources_download' => array(
				'singular' => isset( $template['resources_download'] ) ? $template['resources_download'] : 'Download',
			),
		);
	}

	/**
	 * Get the values for course progress labels
	 * this is also used for backwards compatibility
	 *
	 * @param TVA_Setting $template
	 *
	 * @return array[]
	 */
	public static function get_course_progress_labels( $template = null ) {
		if ( ! $template ) {
			$template = TVA_Setting::get( 'template' );
		}

		return array(
			'not_started'      => array(
				'title' => $template['progress_bar_not_started'],
			),
			'in_progress'      => array(
				'title' => 'In progress',
			),
			'finished'         => array(
				'title' => $template['progress_bar_finished'],
			),
			'label'            => array(
				'title' => $template['progress_bar'],
			),
			'lesson_completed' => array(
				'title' => 'Lesson completed',
			),
		);
	}
}

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
 * Class Hooks
 *
 * @package TVA\Architect\Dynamic_Actions
 * @project : thrive-apprentice
 */
class Hooks {

	/**
	 * Hooks constructor.
	 */
	public function __construct() {
		add_filter( 'tcb_editor_javascript_params', array( $this, 'tcb_editor_javascript_params' ), 10, 3 );

		add_filter( 'tcb_inline_shortcodes', array( $this, 'tcb_inline_shortcodes' ), 100 );

		add_filter( 'tcb_dynamiclink_data', array( $this, 'tcb_dynamic_links' ) );

		add_filter( 'tcb_content_allowed_shortcodes', array( $this, 'content_allowed_shortcodes' ) );

		add_filter( 'tva_get_frontend_localization', array( $this, 'get_frontend_localization' ) );
	}

	/**
	 * Adds Apprentice Dynamic Actions localization
	 *
	 * @param array  $tve_path_params
	 * @param int    $post_id
	 * @param string $post_type
	 *
	 * @return mixed
	 */
	public function tcb_editor_javascript_params( $tve_path_params, $post_id, $post_type ) {

		if ( tva_is_apprentice() && ! empty( tcb_tva_dynamic_actions()->get_active_course() ) ) {
			$tve_path_params['tva_dynamic_actions'] = [
				'course_progress'                => tcb_tva_dynamic_actions()->get_progress_by_type( 'course' ),
				'course_count_lessons'           => tcb_tva_dynamic_actions()->get_course_count_lessons(),
				'course_count_lessons_completed' => tcb_tva_dynamic_actions()->get_course_count_lessons_completed(),
			];

			if ( $post_type === \TVA_Course_Overview_Post::POST_TYPE ) {
				$tve_path_params['tva_dynamic_actions'] = array_merge( $tve_path_params['tva_dynamic_actions'], [
					'call_to_action_text'      => tcb_tva_dynamic_actions()->get_call_to_action_text(),
					'module_count_with_label'  => tcb_tva_dynamic_actions()->get_children_count_with_label( tcb_tva_dynamic_actions()->get_active_course()->get_published_modules() ),
					'chapter_count_with_label' => tcb_tva_dynamic_actions()->get_children_count_with_label( tcb_tva_dynamic_actions()->get_active_course()->get_published_chapters() ),
					'lesson_count_with_label'  => tcb_tva_dynamic_actions()->get_children_count_with_label( tcb_tva_dynamic_actions()->get_active_course()->get_published_lessons() ),
				] );
			}

			if ( in_array( $post_type, array( \TVA_Const::LESSON_POST_TYPE, \TVA_Const::MODULE_POST_TYPE ) ) ) {
				$tve_path_params['tva_dynamic_actions'] = array_merge( $tve_path_params['tva_dynamic_actions'], [
					'next_lesson_text'               => tcb_tva_dynamic_actions()->get_next_lesson_text(),
					'previous_lesson_text'           => tcb_tva_dynamic_actions()->get_prev_lesson_text(),
					'module_progress'                => tcb_tva_dynamic_actions()->get_progress_by_type( 'module' ),
					'module_count_lessons'           => tcb_tva_dynamic_actions()->get_module_count_lessons(),
					'module_count_lessons_completed' => tcb_tva_dynamic_actions()->get_module_count_lessons_completed(),
				] );
			}

			if ( $post_type === \TVA_Const::LESSON_POST_TYPE ) {
				$tve_path_params['tva_dynamic_actions'] = array_merge( $tve_path_params['tva_dynamic_actions'], [
					'mark_as_complete_text'           => tcb_tva_dynamic_actions()->get_mark_as_complete_text(),
					'mark_as_complete_next_text'      => tcb_tva_dynamic_actions()->get_mark_as_complete_next_text(),
					'chapter_progress'                => tcb_tva_dynamic_actions()->get_progress_by_type( 'chapter' ),
					'chapter_count_lessons'           => tcb_tva_dynamic_actions()->get_chapter_count_lessons(),
					'chapter_count_lessons_completed' => tcb_tva_dynamic_actions()->get_chapter_count_lessons_completed(),
					'resources_label'                 => tcb_tva_dynamic_actions()->get_resources_label( 'course_resources', 'plural' ),
					'resources_download_label'        => tcb_tva_dynamic_actions()->get_resources_label( 'resources_download', 'singular' ),
					'resources_open_label'            => tcb_tva_dynamic_actions()->get_resources_label( 'resources_open', 'singular' ),
				] );
			}
		}

		return $tve_path_params;
	}

	/**
	 * Dynamic actions inline shortcodes
	 *
	 * @param array $shortcodes
	 *
	 * @return array
	 */
	public function tcb_inline_shortcodes( $shortcodes = array() ) {

		if ( tva_is_apprentice() && ! empty( tcb_tva_dynamic_actions()->get_active_course() ) ) {
			$post_type = get_post_type();

			$inline_shortcodes = array();

			if ( $post_type === \TVA_Const::LESSON_POST_TYPE ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Mark lesson complete/next lesson', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_mark_as_complete_next_text',
						'input'  => self::go_to_next_configuration( true ),
					),
					array(
						'option' => __( 'Mark lesson complete', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_mark_as_complete_text',
						'input'  => self::go_to_next_configuration( false ),
					),
				) );
			}

			if ( in_array( $post_type, array( \TVA_Const::LESSON_POST_TYPE, \TVA_Const::MODULE_POST_TYPE ) ) ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, [
					array(
						'option' => __( 'Previous lesson', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_previous_lesson_text',
						'input'  => self::get_link_configuration(),
					),
					array(
						'option' => __( 'Next lesson', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_next_lesson_text',
						'input'  => self::get_link_configuration(),
					),
				] );
			}

			if ( $post_type === \TVA_Course_Overview_Post::POST_TYPE ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Course call to action', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_call_to_action_text',
					),
					array(
						'option' => __( 'Module count with label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_module_count_with_label',
					),
					array(
						'option' => __( 'Chapter count with label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_chapter_count_with_label',
					),
					array(
						'option' => __( 'Lesson count with label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_lesson_count_with_label',
					),
				) );
			}

			if ( in_array( $post_type, array( \TVA_Course_Overview_Post::POST_TYPE, \TVA_Const::LESSON_POST_TYPE, \TVA_Const::MODULE_POST_TYPE ) ) ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Course progress', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_course_progress',
					),
					array(
						'option' => __( 'Course lesson count', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_course_count_lessons',
					),
					array(
						'option' => __( 'Course lessons completed', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_course_count_lessons_completed',
					),
				) );
			}

			if ( in_array( $post_type, array( \TVA_Const::LESSON_POST_TYPE, \TVA_Const::MODULE_POST_TYPE ) ) ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Module progress', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_module_progress',
					),
					array(
						'option' => __( 'Module lesson count', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_module_count_lessons',
					),
					array(
						'option' => __( 'Module lessons completed', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_module_count_lessons_completed',
					),
				) );
			}

			if ( 1 === 2 && $post_type === \TVA_Const::LESSON_POST_TYPE ) { //This is for now disabled.
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Chapter progress', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_chapter_progress',
					),
					array(
						'option' => __( 'Chapter lesson count', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_chapter_count_lessons',
					),
					array(
						'option' => __( 'Chapter lessons completed', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_chapter_count_lessons_completed',
					),
				) );
			}

			if ( $post_type === \TVA_Const::LESSON_POST_TYPE ) {
				$inline_shortcodes = array_merge( $inline_shortcodes, array(
					array(
						'option' => __( 'Resources label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_resources_label',
					),
					array(
						'option' => __( 'Resource download label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_resources_download_label',
					),
					array(
						'option' => __( 'Resource open label', \TVA_Const::T ),
						'value'  => 'tva_dynamic_actions_resources_open_label',
					),
				) );
			}

			$shortcodes = array_merge_recursive( array(
				'Thrive Apprentice' => $inline_shortcodes,
			), $shortcodes );
		}

		return $shortcodes;
	}

	/**
	 * @return array[]
	 */
	public function get_link_configuration() {
		return array(
			'link' => array(
				'type'  => 'checkbox',
				'label' => __( 'Link to apprentice action', \TVA_Const::T ),
				'value' => true,
			),
		);
	}

	/**
	 * @param bool $value
	 *
	 * @return array[]
	 */
	public function go_to_next_configuration( $value = true ) {
		return array(
			'go_next_lesson' => array(
				'type'  => 'checkbox',
				'label' => __( 'Also go to next lesson', \TVA_Const::T ),
				'value' => $value,
			),
		);
	}

	/**
	 * @param array $data
	 *
	 * @return array|mixed
	 */
	public function tcb_dynamic_links( $data = array() ) {

		if ( tva_is_apprentice() ) {

			$links = array();

			if ( ! empty( tcb_tva_dynamic_actions()->get_active_course() ) ) {

				$post_type = get_post_type();

				if ( $post_type === \TVA_Const::LESSON_POST_TYPE ) {
					$links = array_merge( $links, [
						[
							'name'  => __( 'Mark lesson complete/next lesson', \TVA_Const::T ),
							'label' => __( 'Mark lesson complete/next lesson', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'mark_as_complete_next',
						],
						[
							'name'  => __( 'Mark lesson complete', \TVA_Const::T ),
							'label' => __( 'Mark lesson complete', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'mark_as_complete',
						],
					] );
				}

				if ( in_array( $post_type, array( \TVA_Const::LESSON_POST_TYPE, \TVA_Const::MODULE_POST_TYPE ) ) ) {
					$links = array_merge( $links, [
						[
							'name'  => __( 'Previous lesson', \TVA_Const::T ),
							'label' => __( 'Previous lesson', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'previous_lesson',
						],
						[
							'name'  => __( 'Next lesson', \TVA_Const::T ),
							'label' => __( 'Next lesson', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'next_lesson',
						],
						[
							'name'  => __( 'Course overview page', \TVA_Const::T ),
							'label' => __( 'Course overview page', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'course_overview',
						],
					] );
				}

				if ( $post_type === \TVA_Course_Overview_Post::POST_TYPE ) {
					$links = array_merge( $links, array(
						array(
							'name'  => __( 'Course call to action', \TVA_Const::T ),
							'label' => __( 'Course call to action', \TVA_Const::T ),
							'url'   => '',
							'show'  => true,
							'id'    => 'call_to_action',
						),
					) );
				}
			}

			$links = array_merge( $links, array(
				array(
					'name'  => __( 'School homepage', \TVA_Const::T ),
					'label' => __( 'School homepage', \TVA_Const::T ),
					'url'   => '',
					'show'  => true,
					'id'    => 'index_page',
				),
			) );

			$data['Thrive Apprentice'] = array(
				'links'     => [ $links ],
				'shortcode' => 'tva_dynamic_actions_link',
			);
		}

		return $data;
	}

	/**
	 * Allow the dynamic actions shortcodes to be rendered in the editor
	 *
	 * @param array $shortcodes
	 *
	 * @return array
	 */
	public function content_allowed_shortcodes( $shortcodes = array() ) {
		return array_merge( $shortcodes, Shortcodes::get() );
	}

	/**
	 * Returns the dynamic actions localization
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	public function get_frontend_localization( $data = array() ) {
		if ( tva_is_apprentice() ) {
			$data['visual_editing_enabled'] = \TVA\TTB\Main::uses_builder_templates();
			if ( tcb_tva_dynamic_actions()->get_active_object() instanceof \TVA_Lesson || tcb_tva_dynamic_actions()->get_active_object() instanceof \TVA_Module ) {
				$data['is_completed']     = (int) tcb_tva_dynamic_actions()->get_active_object()->is_completed();
				$data['next_lesson_url']  = tcb_tva_dynamic_actions()->get_next_lesson_link();
				$data['next_lesson_text'] = tcb_tva_dynamic_actions()->get_next_lesson_text();
			}
		}

		return $data;
	}
}

return new Hooks();

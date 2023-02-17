<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\Architect\Course_List;

use function TVA\Architect\Course\tcb_course_shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Hooks
 *
 * @package  TVA\Architect\Course_List
 * @project  : thrive-apprentice
 */
class Hooks {

	/**
	 * Hooks constructor.
	 */
	public function __construct() {
		add_filter( 'tcb_content_allowed_shortcodes', array( $this, 'content_allowed_shortcodes_filter' ) );

		add_filter( 'tcb_menu_path_course_list', array( $this, 'include_course_list_menu' ), 10, 1 );
		add_filter( 'tcb_menu_path_course_list_dropdown', array( $this, 'include_course_list_dropdown_menu' ), 10, 1 );
		add_filter( 'tcb_menu_path_course_list_item_topic_icon', array( $this, 'include_course_list_item_topic_icon_menu' ), 10, 1 );

		add_filter( 'tcb_element_instances', array( $this, 'tcb_element_instances' ) );

		add_action( 'rest_api_init', array( $this, 'integration_rest_api_init' ) );

		add_action( 'wp_print_footer_scripts', array( $this, 'wp_print_footer_scripts' ), 9 );

		add_filter( 'tcb_modal_templates', array( $this, 'include_modals' ) );

		add_filter( 'tve_frontend_options_data', array( $this, 'tve_frontend_data' ) );

		add_filter( 'tcb_inline_shortcodes', array( $this, 'inline_shortcodes' ) );

		add_filter( 'tcb_dynamiclink_data', array( $this, 'dynamic_links' ) );

		add_filter( 'tcb_waf_fields_restore', array( $this, 'waf_fields_restore' ) );
	}

	/**
	 * Allow the course shortcode to be rendered in the editor
	 *
	 * @param array $shortcodes
	 *
	 * @return array
	 */
	public function content_allowed_shortcodes_filter( $shortcodes = array() ) {

		if ( is_editor_page_raw( true ) ) {
			$shortcodes = array_merge(
				$shortcodes,
				tcb_course_list_shortcode()->get_shortcodes(),
				tcb_course_list_dropdown_shortcode()->get_shortcodes()
			);
		}

		return $shortcodes;
	}

	/**
	 * Includes the course list menu file
	 *
	 * @param $file
	 *
	 * @return mixed|string
	 */
	public function include_course_list_menu( $file ) {

		$file = \TVA_Const::plugin_path( 'tcb-bridge/editor-layouts/menus/course_list.php' );

		return $file;
	}

	/**
	 * Includes the course list dropdown menu file
	 *
	 * @param $file
	 *
	 * @return mixed|string
	 */
	public function include_course_list_dropdown_menu( $file ) {
		$file = \TVA_Const::plugin_path( 'tcb-bridge/editor-layouts/menus/course_list_dropdown.php' );

		return $file;
	}

	/**
	 * Includes the course list item topic icon menu file
	 *
	 * @param $file
	 *
	 * @return mixed|string
	 */
	public function include_course_list_item_topic_icon_menu( $file ) {
		$file = \TVA_Const::plugin_path( 'tcb-bridge/editor-layouts/menus/course-list-item-topic-icon.php' );

		return $file;
	}

	/**
	 * Include the main element and the sub-elements
	 *
	 * @param array $instances
	 *
	 * @return array mixed
	 */
	public function tcb_element_instances( $instances ) {

		$root_path = \TVA_Const::plugin_path( 'tcb-bridge/editor-elements/course-list/' );

		/* add the main element */
		$instance = require_once $root_path . '/class-tcb-course-list-element.php';

		$instances[ $instance->tag() ] = $instance;

		$dropdown_instance                      = class_exists( 'TCB_Course_List_Dropdown_Element', false ) ? tcb_elements()->element_factory( 'course-list-dropdown' ) : require_once $root_path . '/class-tcb-course-list-dropdown-element.php';
		$instances[ $dropdown_instance->tag() ] = $dropdown_instance;

		/* include this before we include the dependencies */
		require_once $root_path . '/class-abstract-course-list-sub-element.php';

		$sub_element_path = $root_path . '/sub-elements';

		$instances = array_merge( $instances, \TVA\Architect\Utils::get_tcb_elements( $root_path, $sub_element_path ) );

		return $instances;
	}

	/**
	 * Includes the REST Class
	 */
	public function integration_rest_api_init() {
		require_once \TVA_Const::plugin_path( 'tcb-bridge/rest/class-tcb-course-list-rest-controller.php' );
	}

	/**
	 * Localize the course list data on the frontend so we can use it for pagination
	 */
	public function wp_print_footer_scripts() {
		if ( ! TCB_Editor()->is_inner_frame() && ! Main::$is_editor_page && ! empty( $GLOBALS['tva_course_list_localize'] ) ) {
			foreach ( $GLOBALS['tva_course_list_localize'] as $course_list ) {

				echo \TCB_Utils::wrap_content(
					str_replace( array( '[', ']' ), array( '{({', '})}' ), $course_list['content'] ),
					'script',
					'',
					'tcb-course-list-template',
					array(
						'type'            => 'text/template',
						'data-identifier' => $course_list['template'],
					)
				);
			}

			/* remove the course content before localizing */
			$courses_localize = array_map(
				function ( $item ) {
					unset( $item['content'] );

					return $item;
				}, $GLOBALS['tva_course_list_localize'] );

			$script_contents = "var tva_course_lists=JSON.parse('" . addslashes( json_encode( $courses_localize ) ) . "');";

			echo \TCB_Utils::wrap_content( $script_contents, 'script', '', '', array( 'type' => 'text/javascript' ) );
		}
	}

	/**
	 * Add some data to the frontend localized object
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function tve_frontend_data( $data ) {

		if ( ! empty( $data['routes'] ) ) {
			$data['routes']['courses'] = get_rest_url( get_current_blog_id(), \TVA_Const::REST_NAMESPACE . '/course_list_element' );
		}

		return $data;
	}

	/**
	 * Adds the course list element inline shortcodes
	 *
	 * @param array $shortcodes
	 *
	 * @return array
	 */
	public function inline_shortcodes( $shortcodes = array() ) {

		$shortcodes = array_merge_recursive( array(
			'Apprentice course data' => array(
				array(
					'option' => __( 'Course title', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_name',
					'input'  => $this->get_link_configuration(),
				),
				array(
					'option' => __( 'Course summary', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_description',
				),
				array(
					'option' => __( 'Course author name', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_author_name',
				),
				array(
					'option' => __( 'Course type', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_type',
				),
				array(
					'option' => __( 'Course label', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_label_title',
				),
				array(
					'option' => __( 'Course call to action label', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_action_label',
				),
				array(
					'option' => __( 'Course topic', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_topic_title',
				),
				array(
					'option' => __( 'Number of lessons in course', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_lessons_number',
				),
				array(
					'option' => __( 'Course progress status', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_progress',
				),
				array(
					'option' => __( 'Course progress', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_progress_percentage',
				),
				array(
					'option' => __( 'Course difficulty level', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_difficulty_name',
				),
				array(
					'option' => __( 'Module count with label', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_module_number_with_label',
				),
				array(
					'option' => __( 'Chapter count with label', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_chapter_number_with_label',
				),
				array(
					'option' => __( 'Lesson count with label', \TVA_Const::T ),
					'value'  => 'tva_course_list_item_lesson_number_with_label',
				),
			),
		), $shortcodes );

		return $shortcodes;
	}

	/**
	 * Add the Course List Links to the list of dynamic links
	 *
	 * @param array $data
	 *
	 * @return array|mixed
	 */
	public function dynamic_links( $data = array() ) {

		$data['Apprentice Course List'] = array(
			'links'     => array(
				array(
					array(
						'name'  => __( 'Course URL', \TVA_Const::T ),
						'label' => __( 'Course URL', \TVA_Const::T ),
						'url'   => '',
						'show'  => true,
						'id'    => 'tva_course_list_item_permalink', //This ID will be replace in the frontend with the actual content ID
					),
				),
			),
			'shortcode' => 'tva_course_list_item_permalink',
		);

		return $data;
	}

	/**
	 * Pushed the course list content key for restore_post_waf_content to search in that key also
	 *
	 * @param array $field_list
	 *
	 * @return array
	 * @see TCB_Utils::restore_post_waf_content
	 */
	public function waf_fields_restore( $field_list = array() ) {
		$field_list[] = 'content';

		return $field_list;
	}


	/**
	 * Include modal files
	 *
	 * @param array $files
	 *
	 * @return array
	 */
	public function include_modals( $files = array() ) {

		$files[] = \TVA_Const::plugin_path( 'tcb-bridge/editor-layouts/modals/course-list-query.php' );

		return $files;
	}

	/**
	 * Course List inline shortcode link configuration
	 *
	 * @return array[]
	 */
	private function get_link_configuration() {
		return array(
			'link'   => array(
				'type'  => 'checkbox',
				'label' => __( 'Link to content', \TVA_Const::T ),
				'value' => true,
			),
			'target' => array(
				'type'       => 'checkbox',
				'label'      => __( 'Open in new tab', \TVA_Const::T ),
				'value'      => false,
				'disable_br' => true,
			),
			'rel'    => array(
				'type'  => 'checkbox',
				'label' => __( 'No follow', \TVA_Const::T ),
				'value' => false,
			),
		);
	}
}

new Hooks();

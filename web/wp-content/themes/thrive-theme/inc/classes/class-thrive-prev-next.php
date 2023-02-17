<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Thrive_Prev_Next
 */
class Thrive_Prev_Next {
	/**
	 * Use general singleton methods
	 */
	use Thrive_Singleton;

	/**
	 * Previous post
	 *
	 * @var string|WP_Post|null
	 */
	protected $prev_post;

	/**
	 * Next post
	 *
	 * @var string|WP_Post|null
	 */
	protected $next_post;

	/**
	 * Thrive_Prev_Next constructor.
	 */
	private function __construct() {

		/* If we are inside an ajax request the post is not set, so we need to do that manually */
		if ( isset( $_REQUEST['post_id'] ) && Thrive_Utils::during_ajax() ) {
			global $post;
			$post = get_post( $_REQUEST['post_id'] );
		}

		$this->prev_post = get_adjacent_post( false, '' );
		$this->next_post = get_adjacent_post( false, '', false );

	}

	/**
	 * Retrieves the adjacent title.
	 * Can either be next or previous title.
	 *
	 * @param array $attr     Shortcode attributes
	 * @param bool  $previous Optional. Whether to retrieve previous title. Default true
	 *
	 * @return mixed|string
	 */
	public function render_adjacent_title( $attr, $previous = true ) {
		$adjacent_post = $previous ? $this->prev_post : $this->next_post;
		$default       = empty( $attr['default'] ) ? '' : $attr['default'];

		if ( isset( $attr['static-link'] ) ) {
			$link_attr           = json_decode( htmlspecialchars_decode( $attr['static-link'] ), true );
			$link_attr['href']   = thrive_prev_next()->render_adjacent_link( [], $previous );
			$attr['static-link'] = json_encode( $link_attr );
		}

		$output = $adjacent_post ? $adjacent_post->post_title : $default;
		$output = TVD_Global_Shortcodes::maybe_link_wrap( $output, $attr );

		return $output;
	}

	/**
	 * Render both previous and next column
	 *
	 * @param string $content
	 * @param array  $attr
	 * @param bool   $previous
	 *
	 * @return string
	 */
	public function render_adjacent_column( $content, $attr = [], $previous = true ) {
		$output = '';
		$link   = thrive_prev_next()->render_adjacent_link( [], $previous );
		if ( apply_filters( 'thrive_prev_next_do_shortcode', Thrive_Utils::during_ajax() ) ) {
			$content = do_shortcode( $content );
		}


		if ( $link !== '#' || is_editor_page_raw( true ) ) {
			$output = TCB_Utils::wrap_content( $content, 'div', '', [
				'wrapper-shortcode',
				'tcb-flex-col',
				'ui-resizable',
			], $attr );
		}

		return $output;
	}

	/**
	 * Render previous or next link
	 *
	 * @param array $attr
	 * @param bool  $previous
	 *
	 * @return bool|false|string|WP_Error
	 */
	public function render_adjacent_link( $attr = [], $previous = true ) {
		if ( $previous ) {
			$post      = $this->prev_post;
			$shortcode = '[tcb_post_prev_link]';
		} else {
			$post      = $this->next_post;
			$shortcode = '[tcb_post_next_link]';
		}

		$link = empty( $post ) ? '#' : get_permalink( $post );

		return is_editor_page_raw( true ) ? $shortcode : $link;
	}

	/**
	 * Add specific classes if the previous or next post doesn't exist
	 *
	 * @return array
	 */
	public function prev_next_classes() {
		$classes = [];
		if ( ! is_editor_page_raw( true ) && thrive_template()->is_singular() ) {

			if ( ! $this->prev_post ) {
				$classes[] = 'thrive-no-prev-post';
			}

			if ( ! $this->next_post ) {
				$classes[] = 'thrive-no-next-post';
			}
		}

		return $classes;
	}

	/**
	 * When to show the element and the related shortcodes
	 *
	 * @return mixed|void
	 */
	public static function show() {
		return apply_filters( 'thrive_theme_show_prev_next', ( Thrive_Utils::is_theme_template() && thrive_template()->get_secondary() === THRIVE_POST_TEMPLATE ) || // only on theme post templates
		                                                     ( is_singular() && get_post_type() === 'post' && isset( $_GET[ THRIVE_THEME_FLAG ] ) ) ); // only on singular posts which are inside the iframe in TTB
	}
}

if ( ! function_exists( 'thrive_prev_next' ) ) {
	/**
	 * Return Thrive_Prev_Next instance
	 *
	 * @return Thrive_Prev_Next
	 */
	function thrive_prev_next() {
		return Thrive_Prev_Next::instance();
	}
}



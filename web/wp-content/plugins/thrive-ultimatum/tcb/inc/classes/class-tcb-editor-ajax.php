<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-visual-editor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden
}

use TCB\inc\helpers\FileUploadConfig;
use TCB\inc\helpers\FormSettings;
use TCB\Notifications\Main;

if ( ! class_exists( 'TCB_Editor_Ajax' ) ) {

	/**
	 * Handles all ajax interactions from the editor page
	 *
	 * Class TCB_Editor_Ajax
	 */
	class TCB_Editor_Ajax {
		const ACTION    = 'tcb_editor_ajax';
		const NONCE_KEY = 'tve-le-verify-sender-track129';

		/**
		 *
		 * Add parameters to the localization of the main frame javascript
		 *
		 * @param array $data
		 *
		 * @return array
		 */
		public function localize( $data ) {
			$data['ajax'] = array(
				'action' => self::ACTION,
			);

			return $data;
		}

		/**
		 * Init the object, during the AJAX request. Adds ajax handlers and verifies nonces
		 */
		public function init() {
			add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
		}

		/**
		 * Handles the ajax call
		 */
		public function handle() {
			if ( wp_verify_nonce( $this->param( 'nonce' ), self::NONCE_KEY ) === false ) {
				$this->error( __( 'This page has expired. Please reload and try again', 'thrive-cb' ), 403, 'nonce_expired' );
			}

			$custom = $this->param( 'custom' );
			if ( empty( $custom ) || ! method_exists( $this, 'action_' . $custom ) ) {
				$this->error( 'Invalid request.', 404 );
			}
			$action = 'action_' . $custom;
			/* restore WAF-protected fields */
			TCB_Utils::restore_post_waf_content();

			/**
			 * Action triggered before any handler code is executed
			 * Allows setting up everything needed for the request, e.g. global objects
			 *
			 * @param TCB_Editor_Ajax $instance
			 */
			do_action( 'tcb_ajax_before', $this );

			/**
			 * Action called just before the custom ajax callbacks.
			 *
			 * @param {TCB_Editor_Ajax} $this
			 */
			do_action( 'tcb_ajax_before_' . $custom, $this );

			$response = call_user_func( array( $this, $action ) );

			$response = apply_filters( 'tcb_ajax_response_' . $custom, $response, $this );

			if ( $this->param( 'expect' ) === 'html' ) {
				wp_die( $response ); // phpcs:ignore
			}

			$this->json( $response );
		}

		/**
		 * @param string $key
		 * @param mixed  $default
		 * @param bool   $sanitize whether or not to sanitize the returned value
		 *
		 * @return mixed
		 */
		protected function param( $key, $default = null, $sanitize = true ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = $_POST[ $key ]; //phpcs:ignore
			} else {
				$value = isset( $_REQUEST[ $key ] ) ? $_REQUEST[ $key ] : $default; //phpcs:ignore
			}

			return $sanitize ? map_deep( $value, 'sanitize_text_field' ) : $value;
		}

		/**
		 *
		 * @param string|WP_Error $message
		 * @param int             $code
		 * @param string          $str_code
		 */
		protected function error( $message, $code = 422, $str_code = '' ) {

			if ( is_wp_error( $message ) ) {
				$message = $message->get_error_message();
			}
			status_header( $code );

			if ( $this->param( 'expect' ) === 'html' ) {
				wp_die( esc_html( $message ), $code );
			}

			$json = array(
				'error'             => true,
				'message'           => $message,
				'tcb_default_error' => $code === 422,
			);
			if ( $str_code ) {
				$json['code'] = $str_code;
			}
			wp_send_json( $json );
		}

		/**
		 * Send a json success response
		 *
		 * Makes sure the response always contain a 'message' and a success field
		 *
		 * @param array $data
		 */
		protected function json( $data ) {
			if ( is_scalar( $data ) ) {
				$data = array(
					'message' => $data,
				);
			}
			if ( ! isset( $data['success'] ) ) {
				$data['success'] = true;
			}
			wp_send_json( $data );
		}

		/** ------------------ AJAX endpoints after this point ------------------ **/

		/**
		 * Saves the user-selected post_types to use in autocomplete search for links
		 *
		 * @return string success message
		 */
		public function action_save_link_post_types() {
			/**
			 * Make sure there is no extra data
			 */
			$all_post_types = get_post_types();
			$post_types     = $this->param( 'post_types', array() );
			update_option( 'tve_hyperlink_settings', array_intersect( $post_types, $all_post_types ) );

			return __( 'Settings saved', 'thrive-cb' );
		}

		/**
		 * Search a post ( used in quick search for link elements )
		 * Will search in a range of post types, filterable
		 *
		 */
		public function action_post_search() {
			$s = trim( wp_unslash( $this->param( 'q' ) ) );
			$s = trim( $s );

			$selected_post_types = array( 'post', 'page', 'product' );

			/**
			 * Add filter to allow hooking into the selected post types
			 */
			$selected_post_types = apply_filters( 'tcb_autocomplete_selected_post_types', $selected_post_types );

			if ( ! $this->param( 'ignore_settings' ) ) {//do not ignore user settings
				/**
				 * post types saved by the user
				 */
				$selected_post_types = maybe_unserialize( get_option( 'tve_hyperlink_settings', $selected_post_types ) );
			}

			if ( $this->param( 'search_lightbox' ) ) {
				/**
				 * Filter that allows custom post types to be included in search results for site linking
				 */
				$post_types_data = apply_filters(
					'tcb_link_search_post_types',
					array(
						'tcb_lightbox' => array(
							'name'         => __( 'TCB Lightbox', 'thrive-cb' ),
							'event_action' => 'thrive_lightbox',
						),
					)
				);

				foreach ( $post_types_data as $key => $value ) {
					/**
					 * if the key is numeric, the value is actually a post type, if not, the value is information for the post type
					 */
					$selected_post_types[] = is_numeric( $key ) ? $value : $key;
				}
			}

			$args = array(
				'post_type'   => $selected_post_types,
				'post_status' => array( 'publish', 'inherit' ), //Inherit for the attachment post type
				's'           => $s,
				'numberposts' => 20,
				'fields'      => 'ids', //we are taking ids because it resembles more with the results returned from wp search
			);

			$query     = new WP_Query();
			$found_ids = $query->query( $args );

			$posts = array();
			foreach ( $found_ids as $id ) {
				$item  = get_post( $id );
				$title = $item->post_title;
				if ( ! empty( $s ) ) {
					$quoted           = preg_quote( $s, '#' );
					$item->post_title = preg_replace( "#($quoted)#i", '<b>$0</b>', $item->post_title );
				}

				$post = array(
					'label'    => $item->post_title,
					'title'    => $title,
					'id'       => $item->ID,
					'value'    => $item->post_title,
					'url'      => $item->post_type === 'attachment' ? wp_get_attachment_url( $item->ID ) : get_permalink( $item->ID ),
					'type'     => $item->post_type,
					'is_popup' => isset( $post_types_data[ $item->post_type ] ) && ! empty( $post_types_data[ $item->post_type ]['event_action'] ),
				);
				if ( $post['is_popup'] ) {
					$post['url']            = '#' . $post_types_data[ $item->post_type ]['name'] . ': ' . $title;
					$post['event_action']   = $post_types_data[ $item->post_type ]['event_action'];
					$post['post_type_name'] = $post_types_data[ $item->post_type ]['name'];
				}

				$posts [] = $post;
			}

			$posts = apply_filters( 'tcb_autocomplete_returned_posts', $posts, $s );

			wp_send_json( $posts );
		}

		/**
		 * Saves a landing page thumbnail
		 *
		 * @return array
		 */
		public function action_save_landing_page_thumbnail() {

			$template_index = $this->param( 'template_index' );
			$landing_page   = $this->param( 'landing_page' );
			$saved_lp_meta  = get_option( 'tve_saved_landing_pages_meta', array() );
			$response       = array();


			if ( isset( $_FILES['img_data'] ) && is_numeric( $template_index ) && ! empty( $landing_page ) && ! empty( $saved_lp_meta ) && is_array( $saved_lp_meta ) ) {

				$image_name   = str_replace( '\\', '', $this->param( 'img_name' ) );
				$image_width  = $this->param( 'image_w' );
				$image_height = $this->param( 'image_h' );


				if ( ! function_exists( 'wp_handle_upload' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}

				add_filter( 'upload_dir', 'tve_filter_upload_user_template_location' );

				$moved_file = wp_handle_upload(
					$_FILES['img_data'],
					array(
						'action'                   => 'tcb_editor_ajax',
						'unique_filename_callback' => sanitize_file_name( $image_name . '.png' ),
					)
				);

				remove_filter( 'upload_dir', 'tve_filter_upload_user_template_location' );

				if ( empty( $moved_file['url'] ) ) {
					$this->error( __( 'Template could not be generated', 'thrive-cb' ) );
				} else if ( ! empty( $saved_lp_meta[ $template_index ] ) ) {
					$saved_lp_meta[ $template_index ]['preview_image'] = array(
						'w'   => $image_width,
						'h'   => $image_height,
						'url' => $moved_file['url'],
					);

					update_option( 'tve_saved_landing_pages_meta', $saved_lp_meta );

					$response['saved_lp_templates'] = tve_landing_pages_load();
				}
			}

			return $response;
		}

		public function action_save_landing_page_preview() {
			$image_name   = str_replace( '\\', '', $this->param( 'img_name' ) );
			$image_width  = $this->param( 'image_w' );
			$image_height = $this->param( 'image_h' );

			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/file.php' );
			}

			/* Filter to change the default location of the uploaded file */
			add_filter( 'upload_dir', 'tve_filter_landing_page_preview_location' );

			/* Callback for the file name(it's used for replacing the image instead of creating a new one) */
			$unique_filename_callback = static function () use ( $image_name ) {
				return $image_name;
			};

			$moved_file = wp_handle_upload(
				$_FILES['img_data'],
				array(
					'action'                   => 'tcb_editor_ajax',
					'unique_filename_callback' => $unique_filename_callback,
				)
			);

			$preview = wp_get_image_editor( $moved_file['file'] );

			/* resize to the given width while using the image's native height */
			$preview->resize( $image_width, null );

			$preview_sizes = $preview->get_size();

			/* crop to the given height ( only if the current height exceeds the required height ) */
			if ( $preview_sizes['height'] > $image_height ) {
				$width_to_crop = min( $image_width, $preview_sizes['width'] );

				$preview->crop( 0, 0, $width_to_crop, $image_height );
			}

			$preview->save( $moved_file['file'] );

			remove_filter( 'upload_dir', 'tve_filter_landing_page_preview_location' );
		}

		/**
		 * Saves user template (code and picture)
		 *
		 * @return array
		 */
		public function action_save_user_template() {
			$key                = $this->param( 'id' );
			$existing_templates = get_option( 'tve_user_templates', array() );
			$template_name      = str_replace( '\\', '', $this->param( 'template_name' ) );
			$new_template       = array(
				'name'        => $template_name,
				'content'     => $this->param( 'template_content', '', false ),
				'type'        => $this->param( 'template_type', '' ),
				'id_category' => $this->param( 'template_category' ),
				'css'         => $this->param( 'custom_css_rules', '', false ),
				'media_css'   => json_decode( stripslashes( $this->param( 'media_rules', '', false ) ), true ),
			);

			if ( $existing_templates && is_array( $existing_templates ) ) {
				foreach ( $existing_templates as $tpl ) {
					if ( is_array( $tpl ) && ! empty( $tpl['name'] ) && $tpl['name'] == $new_template['name'] ) {
						/* If the id id is set, the templates needs an update, not a save, so we do not throw an error */
						if ( ! is_numeric( $key ) ) {
							$this->error( __( 'That template name already exists, please use another name', 'thrive-cb' ) );
						}
					}
				}
			}

			if ( isset( $_FILES['img_data'] ) ) {

				if ( ! function_exists( 'wp_handle_upload' ) ) {
					require_once( ABSPATH . 'wp-admin/includes/file.php' );
				}

				add_filter( 'upload_dir', 'tve_filter_upload_user_template_location' );

				$moved_file = wp_handle_upload(
					$_FILES['img_data'],
					array(
						'action'                   => 'tcb_editor_ajax',
						'unique_filename_callback' => sanitize_file_name( $new_template['name'] . '.png' ),
					)
				);

				remove_filter( 'upload_dir', 'tve_filter_upload_user_template_location' );

				if ( empty( $moved_file['url'] ) ) {
					$this->error( __( 'Template could not be generated', 'thrive-cb' ) );
				}
				if ( file_exists( $moved_file['file'] ) ) {
					$preview = wp_get_image_editor( $moved_file['file'] );
					if ( ! is_wp_error( $preview ) ) {
						$preview->resize( 330, null );
						$preview->save( $moved_file['file'] );
					}
				}

				$new_template = tve_update_image_size( $moved_file['file'], $new_template, $moved_file['url'] );

			}

			if ( empty( $existing_templates ) || ! is_array( $existing_templates ) ) {
				$existing_templates = array();
			}

			$new_template = apply_filters( 'tcb_hook_save_user_template', $new_template );
			if ( is_numeric( $key ) ) {
				$existing_templates [ $key ] = $new_template;
			} else {
				$existing_templates [] = $new_template;
			}

			update_option( 'tve_user_templates', $existing_templates, 'no' );

			return array(
				'text'              => is_numeric( $key ) ? __( 'Template updated!', 'thrive-cb' ) : __( 'Template saved!', 'thrive-cb' ),
				'content_templates' => tcb_elements()->element_factory( 'ct' )->get_list(),
			);
		}

		public function action_save_user_template_category() {
			$template_categories = get_option( 'tve_user_templates_categories' );

			$category_name = $this->param( 'category_name' );
			if ( empty( $category_name ) ) {
				$this->error( __( 'Invalid parameters!', 'thrive-cb' ) );
			}

			if ( ! is_array( $template_categories ) ) {
				$template_categories = array();
			}

			$last_category = end( $template_categories );
			if ( ! empty( $last_category ) ) {
				$index = $last_category['id'] + 1;
			} else {
				$index = 0;
			}

			$new_category          = array(
				'id'   => $index,
				'name' => $category_name,
			);
			$template_categories[] = $new_category;

			update_option( 'tve_user_templates_categories', $template_categories, 'no' );

			$this->json( array( 'text' => __( 'Category saved!', 'thrive-cb' ), 'response' => $new_category ) );
		}

		/**
		 * process and display wp editor contents
		 * used in "Insert Shortcode" element
		 */
		public function action_render_shortcode() {
			$content = '';
			if ( empty( $_POST['content'] ) ) {
				$this->error( __( 'The content is empty. Please input some content.', 'thrive-cb' ) );
			} else {
				$content = stripslashes( $_POST['content'] ); // phpcs:ignore
			}

			/**
			 * ob_start makes sure no output is incorrectly sent to the browser during do_shortcode.
			 * There were instances where 3rd party shortcodes echo'd during do_shortcode call.
			 */
			ob_start();
			$rendered = tcb_render_wp_shortcode( $content );
			$rendered = ob_get_contents() . $rendered;
			ob_end_clean();

			$this->json(
				array(
					'text'     => __( 'Success! Your content was added.', 'thrive-cb' ),
					'response' => $rendered,
				)
			);
		}

		/**
		 * Update post visibility
		 *
		 * @return bool
		 */
		public function action_save_post_status() {
			$post_id = (int) $this->param( 'ID' );

			if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
			$post = get_post( $post_id );
			if ( ! empty( $post ) ) {
				$params = array();

				$status = $this->param( 'post_status' );

				if ( ! empty( $status ) ) {
					$params = array_merge( $params, array(
						'post_status'   => $status,
						'post_password' => $this->param( 'post_password' ),
					) );

					$params = array_merge( $params, array(
						'ID'                => $post_id,
						'post_modified'     => current_time( 'mysql' ),
						'post_modified_gmt' => current_time( 'mysql' ),
						'post_title'        => get_the_title( $post_id ),
					) );

					wp_update_post( $params );

					return true;
				}
			}

			return false;
		}

		/**
		 * Update post title
		 *
		 * @return bool
		 */
		public function action_save_post_title() {
			$post_id = (int) $this->param( 'ID' );

			if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}
			$post = get_post( $post_id );
			if ( ! empty( $post ) ) {
				$params = array();

				$title = $this->param( 'post_title' );

				$params = array_merge( $params, array(
					'ID'                => $post_id,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql' ),
					'post_title'        => $title,
				) );

				wp_update_post( $params );

				return true;
			}

			return false;
		}

		/**
		 * Update post format
		 *
		 * @return bool|array|mixed
		 */
		public function action_save_post_format() {
			$post_id = (int) $this->param( 'ID' );

			if ( empty( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				return false;
			}

			return set_post_format( $post_id, $this->param( 'post_format' ) );
		}

		/**
		 * Ajax listener to save the post in database.  Handles "Save" and "Update" buttons together.
		 * If either button pressed, then write to saved field.
		 * If publish button pressed, then write to both save and published fields
		 *
		 * @return array
		 */
		public function action_save_post() {
			@ini_set( 'memory_limit', TVE_EXTENDED_MEMORY_LIMIT ); //phpcs:ignore

			if ( ! ( $post_id = $this->param( 'post_id' ) ) || ! current_user_can( 'edit_post', $post_id ) || ! tcb_has_external_cap() ) {
				return array(
					'success' => false,
					'message' => __( 'You do not have the required permission for this action', 'thrive-cb' ),
				);
			}
			$post_id  = (int) $post_id;
			$tcb_post = tcb_post( $post_id );

			do_action( 'tcb_ajax_save_post', $post_id, $_POST );

			$landing_page_template = $this->param( 'tve_landing_page', 0 );

			$inline_rules     = $this->param( 'inline_rules', null, false );
			$clippath_pattern = '/clip-path:(.+?);/';

			$inline_rules = preg_replace_callback( $clippath_pattern, array(
				$this,
				'replace_clip_path',
			), $inline_rules );

			$response = array(
				'success' => true,
			);

			/**
			 * Post Constants - similar with tve_globals but do not depend on the Landing Page Key
			 *
			 * Usually stores flags for a particular post
			 */
			if ( ! empty( $_POST['tve_post_constants'] ) && is_array( $_POST['tve_post_constants'] ) ) {
				update_post_meta( $post_id, '_tve_post_constants', map_deep( $_POST['tve_post_constants'], 'sanitize_text_field' ) );
			}

			if ( ( $custom_action = $this->param( 'custom_action' ) ) ) {
				switch ( $custom_action ) {
					case 'landing_page': //change or remove the landing page template for this post
						tcb_landing_page( $post_id )->change_template( $landing_page_template );
						break;
					case 'normal_page_reset':
						tcb_landing_page( $post_id )->change_template( '' );
						delete_post_meta( $post_id, 'tve_custom_css' );
						delete_post_meta( $post_id, 'tve_updated_post' );

						wp_update_post( array(
							'ID'                => $post_id,
							'post_modified'     => current_time( 'mysql' ),
							'post_modified_gmt' => current_time( 'mysql' ),
							'post_content'      => '',
						) );

						break;
					case 'cloud_landing_page':
						$valid = tve_get_cloud_template_config( $landing_page_template );
						if ( $valid === false ) { /* this is not a valid cloud landing page template - most likely, some of the files were deleted */
							$current = tve_post_is_landing_page( $post_id );

							return array(
								'success'          => false,
								'current_template' => $current,
								'error'            => __( 'Some of the required files were not found. Please try re-downloading this template', 'thrive-cb' ),
								'message'          => __( 'Some of the required files were not found. Please try re-downloading this template', 'thrive-cb' ),
							);
						}
						/* if valid, go on with the regular change of template */
						tcb_landing_page( $post_id )->change_template( $landing_page_template );
						$response['message'] = __( 'All changes saved.', 'thrive-cb' );
						break;
					case 'landing_page_reset':
						/* clear the contents of the current landing page */
						if ( ! ( $landing_page_template = tve_post_is_landing_page( $post_id ) ) ) {
							break;
						}

						tcb_landing_page( $post_id, $landing_page_template )->reset();

						$response['message'] = __( 'All changes saved.', 'thrive-cb' );
						break;
					case 'landing_page_delete':
						$template_index = intval( $landing_page_template );
						$contents       = get_option( 'tve_saved_landing_pages_content' );
						$meta           = get_option( 'tve_saved_landing_pages_meta' );

						/**
						 * Delete also the generated preview image
						 */
						if ( ! empty( $meta[ $template_index ] ) && ! empty( $meta[ $template_index ]['preview_image'] ) ) {

							$upload_dir = tve_filter_upload_user_template_location( wp_upload_dir() );
							$base       = $upload_dir['basedir'] . $upload_dir['subdir'];
							$file_name  = $base . '/' . basename( $meta[ $template_index ]['preview_image']['url'] );
							@unlink( $file_name );
						}

						unset( $contents[ $template_index ], $meta[ $template_index ] );
						/* array_values - reorganize indexes */
						update_option( 'tve_saved_landing_pages_content', array_values( $contents ), 'no' );
						update_option( 'tve_saved_landing_pages_meta', array_values( $meta ) );

						$response['saved_lp_templates'] = tve_landing_pages_load();

						break;
				}

				$response['revisions'] = tve_get_post_revisions( $post_id );

				if ( isset( $_POST['header'] ) ) {
					update_post_meta( $post_id, '_tve_header', (int) $_POST['header'] );
				}
				if ( isset( $_POST['footer'] ) ) {
					update_post_meta( $post_id, '_tve_footer', (int) $_POST['footer'] );
				}

				return $response;
			}

			$key           = $landing_page_template ? ( '_' . $landing_page_template ) : '';
			$content       = $this->param( 'tve_content', null, false );
			$content_split = tve_get_extended( $content );
			$content       = str_replace( array( '<!--tvemorestart-->', '<!--tvemoreend-->' ), '', $content );
			update_post_meta( $post_id, "tve_content_before_more{$key}", $content_split['main'] );
			update_post_meta( $post_id, "tve_content_more_found{$key}", $content_split['more_found'] );
			update_post_meta( $post_id, "tve_custom_css{$key}", $inline_rules );

			/* user defined Custom CSS rules here, had to use different key because tve_custom_css was already used */
			update_post_meta( $post_id, "tve_user_custom_css{$key}", $this->param( 'tve_custom_css', null, false ) );
			tve_update_post_meta( $post_id, 'tve_page_events', $this->param( 'page_events', array(), false ) );

			if ( $this->param( 'update' ) === 'true' ) {
				update_post_meta( $post_id, "tve_updated_post{$key}", $content );
				/**
				 * If there is not WP content in the post, migrate it to TCB2-editor only mode
				 */
				$tcb_post->maybe_auto_migrate( false );
				$tcb_post->enable_editor();

				$tve_stripped_content = $this->param( 'tve_stripped_content', null, false );
				$tve_stripped_content = str_replace( array(
					'<!--tvemorestart-->',
					'<!--tvemoreend-->',
				), '', $tve_stripped_content );
				$tcb_post->update_plain_text_content( $tve_stripped_content );
			}

			/* global options for a post that are not included in the editor */
			$tve_globals             = empty( $_POST['tve_globals'] ) ? array() : map_deep( array_filter( $_POST['tve_globals'] ), 'sanitize_text_field' ); // phpcs:ignore
			$tve_globals['font_cls'] = $this->param( 'custom_font_classes', array() );
			update_post_meta( $post_id, "tve_globals{$key}", $tve_globals );
			/* custom fonts used for this post */
			tve_update_post_custom_fonts( $post_id, $tve_globals['font_cls'] );

			if ( $landing_page_template ) {
				update_post_meta( $post_id, 'tve_landing_page', $this->param( 'tve_landing_page' ) );
				/* global Scripts for landing pages */
				update_post_meta( $post_id, 'tve_global_scripts', $this->param( 'tve_global_scripts', array(), false ) );
				if ( ! empty( $_POST['tve_landing_page_save'] ) ) {

					/* save the contents of the current landing page for later use */
					$template_content = array(
						'before_more'        => $content_split['main'],
						'more_found'         => $content_split['more_found'],
						'content'            => $content,
						'inline_css'         => $this->param( 'inline_rules', null, false ),
						'custom_css'         => $this->param( 'tve_custom_css', null, false ),
						'tve_globals'        => $this->param( 'tve_globals', array(), false ),
						'tve_global_scripts' => $this->param( 'tve_global_scripts', array(), false ),
					);
					$template_meta    = array(
						'name'             => $this->param( 'tve_landing_page_save' ),
						'tags'             => $this->param( 'template_tags' ),
						'template'         => $landing_page_template,
						'theme_dependency' => get_post_meta( $post_id, 'tve_disable_theme_dependency', true ),
						'tpl_colours'      => get_post_meta( $post_id, 'thrv_lp_template_colours', true ),
						'tpl_gradients'    => get_post_meta( $post_id, 'thrv_lp_template_gradients', true ),
						'tpl_button'       => get_post_meta( $post_id, 'thrv_lp_template_button', true ),
						'tpl_section'      => get_post_meta( $post_id, 'thrv_lp_template_section', true ),
						'tpl_contentbox'   => get_post_meta( $post_id, 'thrv_lp_template_contentbox', true ),
						'tpl_palettes'     => get_post_meta( $post_id, 'thrv_lp_template_palettes', true ),
						'tpl_skin_tag'     => get_post_meta( $post_id, 'theme_skin_tag', true ),
						'date'             => date( 'Y-m-d' ),
					);
					/**
					 * if this is a cloud template, we need to store the thumbnail separately, as it has a different location
					 */
					$config = tve_get_cloud_template_config( $landing_page_template, false );
					if ( $config !== false && ! empty( $config['thumb'] ) ) {
						$template_meta['thumbnail'] = $config['thumb'];
					}
					if ( empty( $template_content['more_found'] ) ) { // save some space
						unset( $template_content['before_more'] ); // this is the same as the tve_save_post field
						unset( $template_content['more_found'] );
					}
					$templates_content = get_option( 'tve_saved_landing_pages_content' ); // this should get unserialized automatically
					$templates_meta    = get_option( 'tve_saved_landing_pages_meta' ); // this should get unserialized automatically
					if ( empty( $templates_content ) ) {
						$templates_content = array();
						$templates_meta    = array();
					}
					$templates_content [] = $template_content;
					$templates_meta []    = $template_meta;

					// make sure these are not autoloaded, as it is a potentially huge array
					add_option( 'tve_saved_landing_pages_content', null, '', 'no' );

					update_option( 'tve_saved_landing_pages_content', $templates_content, 'no' );
					update_option( 'tve_saved_landing_pages_meta', $templates_meta );

					$response['saved_lp_templates'] = tve_landing_pages_load();
				}
			} else {
				delete_post_meta( $post_id, 'tve_landing_page' );
			}
			tve_update_post_meta( $post_id, 'thrive_icon_pack', empty( $_POST['has_icons'] ) ? 0 : $_POST['has_icons'] );
			tve_update_post_meta( $post_id, 'tve_has_masonry', empty( $_POST['tve_has_masonry'] ) ? 0 : 1 );
			tve_update_post_meta( $post_id, 'tve_has_typefocus', empty( $_POST['tve_has_typefocus'] ) ? 0 : 1 );
			tve_update_post_meta( $post_id, 'tve_has_wistia_popover', empty( $_POST['tve_has_wistia_popover'] ) ? 0 : 1 );

			if ( isset( $_POST['header'] ) ) {
				update_post_meta( $post_id, '_tve_header', (int) $_POST['header'] );
			}
			if ( isset( $_POST['footer'] ) ) {
				update_post_meta( $post_id, '_tve_footer', (int) $_POST['footer'] );
			}

			/* Handle the css, js and additional saves */
			\TCB\Lightspeed\Main::handle_optimize_saves( $post_id, $_POST );
			/**
			 * Remove old unused meta
			 */
			tve_clean_up_meta_leftovers( $post_id );
			/**
			 * trigger also a post / page update for the caching plugins to know there has been a save
			 * update post here so we can have access to its meta when a revision of it is saved
			 *
			 * @see tve_save_post_callback
			 */
			if ( ! empty( $content ) ) {
				if ( $landing_page_template ) {
					remove_all_filters( 'save_post' );
					add_action( 'save_post', 'tve_save_post_callback' );
				}

				wp_update_post( array(
					'ID'                => $post_id,
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql' ),
					'post_title'        => get_the_title( $post_id ),
				) );
			}

			$response['revisions'] = tve_get_post_revisions( $post_id );

			return $response;

		}

		/**
		 * Redirects the save post to an external method
		 */
		public function action_save_post_external() {
			$external_action = $this->param( 'external_action' );
			if ( ! $external_action ) {
				$this->error( 'Invalid Request!' );
			}

			return apply_filters( 'tcb_ajax_' . $external_action, array(), $_REQUEST );
		}

		/**
		 * Update wp options
		 *
		 * @return int
		 */
		public function action_update_option() {
			$option_name  = $this->param( 'option_name' );
			$option_value = $this->param( 'option_value' );

			$allowed = apply_filters( 'tcb_allowed_ajax_options', array(
				'tve_display_save_notification',
				'tve_social_fb_app_id',
				'tve_comments_disqus_shortname',
				'tve_comments_facebook_admins',
				'tcb_pinned_elements',
				'tve_fa_kit',
			) );

			if ( ! in_array( $option_name, $allowed ) ) {
				$this->error( 'Invalid', 403 );
			}

			if ( $option_name === 'tve_comments_facebook_admins' ) {
				$tve_comments_facebook_admins_arr = explode( ';', $option_value );
				$result                           = update_option( $option_name, $tve_comments_facebook_admins_arr );
			} elseif ( $option_name === 'tcb_pinned_elements' ) {
				$result = update_user_option( get_current_user_id(), $option_name, $option_value );
			} else {
				$result = update_option( $option_name, $option_value );
			}

			return (int) $result;
		}

		/**
		 * @return array
		 */
		public function action_get_api() {
			$api_key = $this->param( 'api' );
			$force   = (bool) $this->param( 'force' );
			$extra   = $this->param( 'extra' );

			$data = [];

			if ( $api_key ) {
				$connection = Thrive_Dash_List_Manager::connection_instance( $api_key );

				$data = $connection->get_api_data( $extra, $force );
			}

			return $data;
		}

		/**
		 * Get extra fields from api
		 *
		 * @return array
		 */
		public function action_get_api_extra() {
			$api    = $this->param( 'api' );
			$extra  = $this->param( 'extra' );
			$params = $this->param( 'params' );

			if ( ! $api || ! array_key_exists( $api, Thrive_Dash_List_Manager::available() ) ) {
				return array();
			}

			$connection = Thrive_Dash_List_Manager::connection_instance( $api );

			return $connection->get_api_extra( $extra, $params );
		}

		public function action_custom_menu() {
			ob_start();
			include plugin_dir_path( dirname( __FILE__ ) ) . 'views/elements/menu-generated.php';
			$content = ob_get_contents();
			ob_end_clean();

			$this->json( array( 'response' => $content ) );
		}

		public function action_load_content_template() {
			/**
			 * Allow things to happen before running do shortcode function bellow
			 */
			do_action( 'tcb_before_load_content_template' );

			/** @var TCB_Ct_Element $ct */
			$ct       = tcb_elements()->element_factory( 'ct' );
			$template = $ct->load( (int) $this->param( 'template_key' ) );

			add_filter( 'tcb_is_editor_page_ajax', '__return_true' );

			$template['html_code'] = tve_do_wp_shortcodes( tve_thrive_shortcodes( stripslashes( $template['html_code'] ), true ), true );
			if ( ! empty( $template['media_css'][0] ) ) {
				$imports = explode( ';@import', $template['media_css'][0] );

				foreach ( $imports as $key => $import ) {
					if ( strpos( $import, '@import' ) === false ) {
						$import = '@import' . $import;
					}
					$template['imports'][ $key ] = $import;
				}
			}

			if ( ! empty( $template['media_css'][1] ) ) {
				$template['inline_rules'] = $template['media_css'][1];
			}

			return $template;
		}

		public function action_delete_content_template() {
			/** @var TCB_Ct_Element $ct */
			$ct = tcb_elements()->element_factory( 'ct' );

			return array(
				'list'    => $ct->delete( $this->param( 'key' ) ),
				'message' => __( 'Content template deleted', 'thrive-cb' ),
			);
		}

		/**
		 * Returns Current Post Revisions
		 */
		public function action_revisions() {
			$post_id = $this->param( 'post_id' );
			if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
				$this->error( __( 'Invalid Post Parameter', 'thrive-cb' ) );
			}

			$revisions = tve_get_post_revisions( $post_id );

			wp_send_json( $revisions );
		}

		/**
		 * Enables / Disables Theme CSS to Architect Page
		 */
		public function action_theme_css() {
			$post_id    = $this->param( 'post_id' );
			$disable_it = $this->param( 'disabled' );
			if ( empty( $post_id ) || ! is_numeric( $post_id ) ) {
				$this->error( __( 'Invalid Post Parameter', 'thrive-cb' ) );
			}

			update_post_meta( $post_id, 'tve_disable_theme_dependency', $disable_it ? 1 : 0 );

			$this->json( array() );
		}

		/**
		 * Updates the user wizard data
		 */
		public function action_user_settings() {
			$config = $this->param( 'config', array() );

			update_user_option( get_current_user_id(), 'tcb_u_settings', $config );

			$this->json( $config );
		}

		/**
		 * Crud Operations on global gradients
		 */
		public function action_global_gradients() {
			$name     = $this->param( 'name' );
			$gradient = $this->param( 'gradient', null, false );
			$id       = $this->param( 'id' );
			$active   = is_numeric( $this->param( 'active' ) ) ? 0 : 1;

			$custom_name = (int) $this->param( 'custom_name' );
			if ( empty( $custom_name ) || $custom_name !== 1 ) {
				$custom_name = 0;
			}

			$max_name_characters          = 50;
			$global_gradients_option_name = apply_filters( 'tcb_global_gradients_option_name', 'thrv_global_gradients' );

			if ( empty( $name ) || empty( $gradient ) || ! is_string( $gradient ) || ! is_string( $name ) ) {
				/**
				 * The color has to have a name and it must be a valid string
				 */
				$this->error( 'Invalid Parameters! A gradient must contain a name and a gradient string!' );
			}

			if ( strlen( $name ) > $max_name_characters ) {
				$this->error( 'Invalid color name! It must contain a maximum of ' . $max_name_characters . ' characters' );
			}

			$global_gradients = get_option( $global_gradients_option_name, array() );

			if ( ! is_array( $global_gradients ) ) {
				/**
				 * Security check: if the option is not empty and somehow the stored value is not an array, make it an array.
				 */
				$global_gradients = array();
			}

			if ( ! is_numeric( $id ) ) {
				/**
				 * ADD Action
				 */
				$gradient_id = count( $global_gradients );

				$global_gradients[] = array(
					'id'          => $gradient_id,
					'gradient'    => $gradient,
					'name'        => $name,
					'active'      => $active,
					'custom_name' => $custom_name,
				);

			} else {
				/**
				 *  Edit Gradient
				 */
				$index = - 1;

				foreach ( $global_gradients as $key => $global_g ) {
					if ( (int) $global_g['id'] === (int) $id ) {
						$index = $key;
						break;
					}
				}

				if ( $index > - 1 ) {
					$global_gradients[ $index ]['gradient'] = $gradient;
					$global_gradients[ $index ]['name']     = $name;
					$global_gradients[ $index ]['active']   = $active;

					if ( $custom_name ) {
						/**
						 * Update the custom name only if the value is 1
						 */
						$global_gradients[ $index ]['custom_name'] = $custom_name;
					}
				}
			}

			update_option( $global_gradients_option_name, $global_gradients );

			/**
			 * Added possibility for external functionality to hook into here
			 *
			 * - Used in the landing page builder when a new gradient is added, to add it across all palettes
			 */
			do_action( 'tcb_action_global_gradients' );

			$this->json( $global_gradients );

		}

		/**
		 * CRUD Operations on global colors
		 */
		public function action_global_colors() {
			$name        = $this->param( 'name' );
			$color       = $this->param( 'color', null, false );
			$id          = $this->param( 'id' );
			$active      = (int) $this->param( 'active', 1 );
			$linked_vars = $this->param( 'linked_variables', array() );

			$custom_name = (int) $this->param( 'custom_name' );
			if ( empty( $custom_name ) || $custom_name !== 1 ) {
				$custom_name = 0;
			}

			$max_name_characters = 50;

			if ( empty( $name ) || empty( $color ) || ! is_string( $color ) || ! is_string( $name ) ) {
				/**
				 * The color has to have a name and it must be a valid string
				 */
				$this->error( __( 'Invalid Parameters! A color must contain a name and a color string!', 'thrive-cb' ) );
			}

			if ( substr( $color, 0, 3 ) !== 'rgb' ) {
				/**
				 * The color must be a valid RGB string
				 */
				$this->error( 'Invalid color format! It must be a valid RGB string!' );
			}

			if ( strlen( $name ) > $max_name_characters ) {
				$this->error( 'Invalid color name! It must contain a maximum of ' . $max_name_characters . ' characters' );
			}

			$global_colors = tcb_color_manager()->get_list();
			if ( ! is_array( $global_colors ) ) {
				/**
				 * Security check: if the option is not empty and somehow the stored value is not an array, make it an array.
				 */
				$global_colors = array();
			}

			if ( ! is_numeric( $id ) ) {
				/**
				 * ADD Action
				 */
				$color_id = count( $global_colors );

				$global_colors[] = array(
					'id'          => $color_id,
					'color'       => $color,
					'name'        => $name,
					'active'      => $active,
					'custom_name' => $custom_name,
				);

			} else {
				/**
				 *  Edit Color
				 */
				$index = - 1;

				foreach ( $global_colors as $key => $global_c ) {
					if ( (int) $global_c['id'] === (int) $id ) {
						$index = $key;
						break;
					}
				}

				if ( $index > - 1 ) {
					$global_colors[ $index ]['color']  = $color;
					$global_colors[ $index ]['name']   = $name;
					$global_colors[ $index ]['active'] = $active;

					if ( $custom_name ) {
						/**
						 * Update the custom name only if the value is 1
						 */
						$global_colors[ $index ]['custom_name'] = $custom_name;
					}
				}

				/**
				 * Process Linked Vars
				 */
				foreach ( $linked_vars as $var_id => $new_color ) {
					$index = - 1;

					foreach ( $global_colors as $key => $global_c ) {
						if ( intval( $global_c['id'] ) === intval( $var_id ) ) {
							$index = $key;
							break;
						}
					}

					if ( $index > - 1 ) {
						$global_colors[ $index ]['color'] = $new_color;
					}
				}
			}

			tcb_color_manager()->update_list( $global_colors );

			/**
			 * Added possibility for external functionality to hook into here
			 *
			 * - Used in the landing page builder when a new color is added, to add it across all palettes
			 */
			do_action( 'tcb_action_global_colors' );

			$this->json( $global_colors );
		}

		/**
		 * Update Template Variables
		 */
		public function action_template_options() {
			$name        = $this->param( 'name' );
			$type        = $this->param( 'type', '' );
			$value       = $this->param( 'value', '', false );
			$id          = $this->param( 'id' );
			$linked_vars = $this->param( 'linked_variables', array(), false );

			$custom_name = (int) $this->param( 'custom_name' );
			if ( empty( $custom_name ) || $custom_name !== 1 ) {
				$custom_name = 0;
			}

			if ( ! in_array( $type, array( 'color', 'gradient' ) ) ) {
				$this->error( 'Invalid type' );
			}

			if ( empty( $name ) || empty( $value ) || ! is_string( $value ) || ! is_string( $name ) || ! is_numeric( $id ) ) {
				/**
				 * The Gradient has to have a name and it must be a valid string
				 */
				$this->error( 'Invalid Parameters! A color must contain a name, an id and a color string!' );
			}

			$post_id = (int) $this->param( 'post_id', 0 );

			if ( empty( $post_id ) ) {
				$this->error( 'Something went wrong! Please contact the support team!' );
			}

			if ( tve_post_is_landing_page( $post_id ) ) {

				tcb_landing_page( $post_id )->update_template_css_variable( $id, array(
					'key'                   => $type,
					'value'                 => $value,
					'name'                  => $name,
					'linked_variables'      => $linked_vars,
					'custom_name'           => $custom_name,
					'hsl_parent_dependency' => $this->param( 'hsl_parent_dependency', array(), false ),
					'hsl'                   => $this->param( 'hsl', array(), false ),
				) );
			}
		}

		/**
		 * Function used to update custom options
		 *
		 * Used for updating the custom colors (Favorites Colors)
		 * Used for updating the custom gradients (Favorites Gradients)
		 */
		public function action_custom_options() {
			$type   = $this->param( 'type', '' );
			$values = $this->param( 'values', array(), false );

			if ( ! in_array( $type, array( 'colours', 'gradients' ) ) ) {
				$this->error( 'Invalid type' );
			}

			update_option( 'thrv_custom_' . $type, $values );
		}

		/**
		 * Lazy load data in the editor so we can improve the page load speed.
		 */
		public function action_lazy_load() {
			$data = array();

			$post_id = (int) $this->param( 'post_id', 0 );
			tcb_editor()->set_post( $post_id, true );

			if ( tcb_editor()->can_use_landing_pages() ) {
				$data['lp_templates']       = class_exists( 'TCB_Landing_Page' ) ? TCB_Landing_Page::templates_v2() : array();
				$data['saved_lp_templates'] = tve_landing_pages_load();
				$data['cloud_lp_templates'] = function_exists( 'tve_get_cloud_templates' ) ? tve_get_cloud_templates() : array();
			}

			$data['blocks'] = tcb_elements()->element_factory( 'contentblock' )->get_blocks();

			$data['btn_default_templates'] = tcb_elements()->element_factory( 'button' )->get_default_templates();
			$terms                         = get_terms( array( 'slug' => array( 'headers', 'footers' ) ) );
			$terms                         = array_map( function ( $term ) {
				return $term->term_id;
			}, $terms );

			$data['symbols']           = tcb_elements()->element_factory( 'symbol' )->get_all( array( 'category__not_in' => $terms ) );
			$data['content_templates'] = tcb_elements()->element_factory( 'ct' )->get_list();

			$data['custom_icons'] = TCB_Icon_Manager::get_custom_icons( $post_id );

			$data = apply_filters( 'tcb_lazy_load_data', $data, $post_id, $this );

			$this->json( $data );
		}

		/**
		 * Lazy load for the acf dynamic colors
		 */
		public function action_dynamic_colors_lazy_load() {
			$data    = array();
			$post_id = (int) $this->param( 'post_id', 0 );

			$data = apply_filters( 'tcb_lazy_load_dynamic_colors', $data, $post_id, $this );

			$this->json( $data );
		}

		/**
		 * CRUD Operations on Global Styles
		 */
		public function action_global_styles() {
			$name       = $this->param( 'name' );
			$type       = $this->param( 'type' );
			$identifier = $this->param( 'identifier' );
			$css        = $this->param( 'css', null, false );
			$fonts      = $this->param( 'fonts', array(), false );
			$dom        = $this->param( 'dom', null, false );
			$active     = $this->param( 'active' );
			$ignore_css = $this->param( 'ignore_css', false, false );
			$post_id    = (int) $this->param( 'post_id', 0 );
			$delete     = $this->param( 'delete', false, false );

			if ( empty( $identifier ) ) {
				$this->error( 'A shared style must contain an identifier!' );
			}

			$global_options = tve_get_global_styles_option_names();
			if ( ! isset( $global_options[ $type ] ) ) {
				$this->error( 'Invalid Type!' );
			}

			if ( strpos( $identifier, 'tpl_' ) !== false ) {

				if ( tve_post_is_landing_page( $post_id ) ) {
					tcb_landing_page( $post_id )->update_template_style( $identifier, $type, $name, $css, $fonts, $ignore_css );
				}

				return;
			}

			$global_style_option_name = $global_options[ $type ];

			if ( empty( $global_style_option_name ) ) {
				/**
				 * Additional check!!
				 */
				$this->error( 'Additional check. Option Name Fail!' );
			}
			$global_styles = get_option( $global_style_option_name, array() );

			if ( ! is_array( $global_styles ) ) {
				/**
				 * Security check: if the option is not empty and somehow the stored value is not an array, make it an array.
				 */
				$global_styles = array();
			}
			$is_create = empty( $global_styles[ $identifier ] );
			if ( $delete ) {
				/* deletes the identified global style */
				unset( $global_styles[ $identifier ] );
			} elseif ( $is_create ) {

				if ( empty( $dom['attr'] ) ) {
					$dom['attr'] = array();
				}

				/**
				 * Add New Global Style
				 */
				$global_styles[ $identifier ] = array(
					'name'  => $name,
					'css'   => $css,
					'dom'   => $dom,
					'fonts' => $fonts,
				);

				$default_props = array(
					'default_css'  => $this->param( 'default_css', '', false ),
					'default_html' => $this->param( 'default_html', '', false ),
					'smart_config' => $this->param( 'smart_config', '', false ),
				);

				foreach ( $default_props as $d_key => $d_value ) {
					if ( ! empty( $d_value ) ) {
						$d_value = json_decode( stripslashes( $d_value ), true );

						$global_styles[ $identifier ][ $d_key ] = $d_value;
					}
				}
			} else {
				/**
				 * Edit Global Style
				 */
				if ( false === $ignore_css ) {
					$global_styles[ $identifier ]['css']   = $css;
					$global_styles[ $identifier ]['fonts'] = $fonts;
				}
				if ( $name ) {
					$global_styles[ $identifier ]['name'] = $name;
				}

				$smart_config = $this->param( 'smart_config', '', false );
				if ( ! empty( $smart_config ) ) {
					$global_styles[ $identifier ]['smart_config'] = json_decode( stripslashes( $smart_config ), true );
				}

				if ( is_numeric( $active ) && (int) $active === 0 ) {
					unset( $global_styles[ $identifier ] );
				}
			}

			update_option( $global_style_option_name, apply_filters( 'tcb_global_styles_before_save', $global_styles, $is_create, $_REQUEST ) );

			return tve_get_shared_styles( '', '300', false );
		}

		/**
		 * Generate post Grid Ajax Call
		 */
		public function action_post_grid() {
			require_once plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'inc/classes/class-tcb-post-grid.php';
			$post_grid = new TCB_Post_Grid( $_POST );
			$html      = $post_grid->render();

			$this->json( array( 'html' => $html ) );
		}

		/**
		 * Ajax that returns the categories for post grid elements that begins with a certain string
		 */
		public function action_post_grid_categories() {
			$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

			require_once plugin_dir_path( __FILE__ ) . 'class-tcb-element-abstract.php';
			require_once plugin_dir_path( __FILE__ ) . 'elements/class-tcb-postgrid-element.php';

			$response = TCB_Postgrid_Element::get_categories( $search_term );

			wp_send_json( $response );
		}

		/**
		 * Ajax that returns the tags for post grid elements that begins with a certain string
		 */
		public function action_post_grid_tags() {
			$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

			require_once plugin_dir_path( __FILE__ ) . 'class-tcb-element-abstract.php';
			require_once plugin_dir_path( __FILE__ ) . 'elements/class-tcb-postgrid-element.php';

			$response = TCB_Postgrid_Element::get_tags( $search_term );

			wp_send_json( $response );
		}

		/**
		 * Ajax that returns the tags for post grid elements that begins with a certain string
		 */
		public function action_post_grid_custom_taxonomies() {
			$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

			require_once plugin_dir_path( __FILE__ ) . 'class-tcb-element-abstract.php';
			require_once plugin_dir_path( __FILE__ ) . 'elements/class-tcb-postgrid-element.php';

			$response = TCB_Postgrid_Element::get_custom_taxonomies( $search_term );

			wp_send_json( $response );
		}

		/**
		 *  Ajax that returns the users for post grid elements that begins with a certain string
		 */
		public function action_post_grid_users() {
			$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

			require_once plugin_dir_path( __FILE__ ) . 'class-tcb-element-abstract.php';
			require_once plugin_dir_path( __FILE__ ) . 'elements/class-tcb-postgrid-element.php';

			$response = TCB_Postgrid_Element::get_authors( $search_term );

			wp_send_json( $response );
		}

		/**
		 *  Ajax that returns the individual posts or pages for post grid elements that begins with a certain string
		 */
		public function action_post_grid_individual_post_pages() {
			$search_term = isset( $_POST['term'] ) ? sanitize_text_field( $_POST['term'] ) : '';

			require_once plugin_dir_path( __FILE__ ) . 'class-tcb-element-abstract.php';
			require_once plugin_dir_path( __FILE__ ) . 'elements/class-tcb-postgrid-element.php';

			$response = TCB_Postgrid_Element::get_posts_list( $search_term );

			wp_send_json( $response );
		}

		/**
		 * Creates a new Thrive Lightbox
		 *
		 * @return array
		 */
		public function action_create_lightbox() {
			$post_id = $this->param( 'post_id' );
			if ( ! $post_id ) {
				return array();
			}

			$landing_page_template = tve_post_is_landing_page( $post_id );
			$lightbox_title        = $this->param( 'title' );

			if ( $landing_page_template ) {
				$tcb_landing_page = tcb_landing_page( $post_id, $landing_page_template );
				$lightbox_id      = $tcb_landing_page->new_lightbox( $lightbox_title );
			} else {
				$lightbox_id = TCB_Lightbox::create( $lightbox_title, '', array(), array() );
			}

			return array(
				'lightbox' => array(
					'id'       => $lightbox_id,
					'title'    => $lightbox_title,
					'edit_url' => tcb_get_editor_url( $lightbox_id ),
				),
				'message'  => __( 'Lightbox created', 'thrive-cb' ),
			);
		}

		/**
		 * Fetches a list of Cloud templates for an element
		 *
		 * @return array
		 */
		public function action_cloud_content_templates() {

			$type = $this->param( 'type' );

			if ( empty( $type ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			/* Allows changing the template type */
			$type     = apply_filters( 'tcb_cloud_templates_replace_featured_type', $type );
			$no_cache = (bool) $this->param( 'nocache', false );

			/** @var TCB_Cloud_Template_Element_Abstract $element */
			$element = tcb_elements()->element_factory( $type );
			if ( $element === null || ! ( $element instanceof TCB_Cloud_Template_Element_Abstract ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ) . " ({$type})", 500 );
			}

			$templates = $element->get_cloud_templates( array( 'nocache' => $no_cache ) );

			if ( is_wp_error( $templates ) ) {
				$code = $templates->get_error_data( 'tcb_error' );
				$this->error( $templates, $code ? $code : 500 );
			}

			return array(
				'success'   => true,
				'templates' => $templates,
			);
		}

		/**
		 * Fetches a list of Cloud templates when no element is available
		 *
		 * @return array
		 */
		public function action_cloud_content_templates_without_element() {
			if ( ! ( $type = $this->param( 'type' ) ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			$no_cache = (bool) $this->param( 'nocache', false );

			$templates = tve_get_cloud_content_templates( $type, array( 'nocache' => $no_cache ) );

			if ( is_wp_error( $templates ) ) {
				$this->error( $templates );
			}

			return array(
				'success'   => true,
				'templates' => $templates,
			);
		}

		/**
		 * Downloads a template from the cloud ( or fetches a template stored local )
		 *
		 * @return array
		 */
		public function action_cloud_content_template_download() {
			if ( ! ( $type = $this->param( 'type' ) ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			if ( ! ( $id = $this->param( 'id' ) ) ) {
				$this->error( __( 'Missing template id', 'thrive-cb' ) . " ({$type})", 500 );
			}

			/*Allows changing the template type*/
			$type = apply_filters( 'tcb_cloud_templates_replace_featured_type', $type );

			/** @var TCB_Cloud_Template_Element_Abstract $element */
			if ( ! ( $element = tcb_elements()->element_factory( $type ) ) || ! is_a( $element, 'TCB_Cloud_Template_Element_Abstract' ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ) . " ({$type})", 500 );
			}

			$data = $element->get_cloud_template_data( $id, array( 'type' => $type ) );

			if ( is_wp_error( $data ) ) {
				$this->error( $data );
			}

			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		/**
		 * Downloads a template from the cloud ( or fetches a template stored local )
		 * but not dependant on an element
		 *
		 * @return array
		 */
		public function action_cloud_content_template_download_without_element() {
			if ( ! ( $type = $this->param( 'type' ) ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			if ( ! ( $id = $this->param( 'id' ) ) ) {
				$this->error( __( 'Missing template id', 'thrive-cb' ) . " ({$type})", 500 );
			}

			$data = tve_get_cloud_template_data( $type, array( 'id' => $id, 'type' => $type, ) );

			if ( is_wp_error( $data ) ) {
				$this->error( $data );
			}

			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		/**
		 * Callback for preg_replace
		 * Adds vendor prefix for clip-path for safari
		 */
		public function replace_clip_path( $matches ) {
			return $matches[0] . ' -webkit-clip-path:' . $matches[1] . '; ';
		}

		/**
		 * Return all symbols
		 */
		public function action_get_symbols() {

			$element_type = ( $this->param( 'type' ) ) ? $this->param( 'type' ) : 'symbol';

			/** @var TCB_Symbol_Element $element */
			if ( ! ( $element = tcb_elements()->element_factory( $element_type ) ) || ! is_a( $element, 'TCB_Element_Abstract' ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			$args = ( $this->param( 'args' ) ) ? $this->param( 'args' ) : array();

			$symbols = $element->get_all( $args );

			if ( is_wp_error( $symbols ) ) {
				$this->error( __( 'Error when retrieving symbols', 'thrive-cb' ), 500 );
			}

			return array(
				'success' => true,
				'symbols' => $symbols,
			);
		}

		/**
		 * Get a single symbol by ID
		 */
		public function action_get_symbol() {
			$type = $this->param( 'type', 'symbol' );
			$id   = $this->param( 'id' );
			if ( ! $id ) {
				return new WP_Error( 'missing_id', __( 'Missing ID', 'thrive-cb' ), array( 'status' => 500 ) );
			}

			/** @var TCB_Symbol_Element_Abstract $element */
			if ( ! ( $element = tcb_elements()->element_factory( $type ) ) || ! is_a( $element, 'TCB_Symbol_Element_Abstract' ) ) {
				return new WP_Error( 'rest_invalid_element_type', __( 'Invalid element type', 'thrive-cb' ) . " ({$type})", array( 'status' => 500 ) );
			}

			return array(
				'success' => true,
				'data'    => $element->prepare_symbol( get_post( $id ) ),
			);
		}

		/**
		 * Save symbol when it gets edited from TAR
		 *
		 * @return array
		 */
		public function action_save_symbol() {

			/** @var TCB_Symbol_Element $element */
			if ( ! ( $element = tcb_elements()->element_factory( 'symbol' ) ) || ! is_a( $element, 'TCB_Element_Abstract' ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			$symbol_data = array(
				'id'               => $this->param( 'id' ),
				'content'          => $this->param( 'symbol_content', null, false ),
				'css'              => $this->param( 'symbol_css', null, false ),
				'term_id'          => $this->param( 'tcb_symbols_tax' ),
				'tve_globals'      => $this->param( 'tve_globals', null, false ),
				'from_existing_id' => $this->param( 'from_existing_id' ),
				'element_type'     => $this->param( 'element_type' ),
				'thumb'            => $this->param( 'thumb', null, false ),
				'has_icons'        => $this->param( 'has_icons' ),
				'class'            => $this->param( 'class' ),
			);

			if ( empty( $symbol_data['id'] ) ) {
				//if we don't have an id => we are creating a symbol, which needs to have a title
				if ( ! ( $title = $this->param( 'symbol_title' ) ) ) {
					$this->error( __( 'Missing symbol title', 'thrive-cb' ), 500 );
				}

				$symbol_data['symbol_title'] = $title;
				$data                        = $element->create_symbol( $symbol_data );

				if ( ! is_wp_error( $data ) ) {
					\TCB\Lightspeed\Main::handle_optimize_saves( $data['id'], $_REQUEST );
				}
			} else {
				$data = $element->edit_symbol( $symbol_data );

				\TCB\Lightspeed\Main::handle_optimize_saves( $symbol_data['id'], $_REQUEST );
			}

			if ( is_wp_error( $data ) ) {
				$this->error( $data );
			}

			do_action( 'tcb_after_symbol_save', array_merge( $_POST, $data ) );

			return array(
				'success' => true,
				'data'    => $data,
			);
		}

		/**
		 * When elements have extra css we need to do an extra save after we process the css for the symbol.
		 * i.e call to action element
		 */
		public function action_save_symbol_extra_css() {

			if ( ! ( $id = $this->param( 'id' ) ) ) {
				$this->error( __( 'Missing symbol id', 'thrive-cb' ), 500 );
			}

			/** @var TCB_Symbol_Element $element */
			if ( ! ( $element = tcb_elements()->element_factory( 'symbol' ) ) || ! is_a( $element, 'TCB_Element_Abstract' ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			$symbol_data = array(
				'id'  => $id,
				'css' => $this->param( 'css', null, false ),
			);

			/**
			 * Save updated css with the proper selectors after a symbol was created
			 */
			$response = $element->save_extra_css( $symbol_data );

			\TCB\Lightspeed\Main::handle_optimize_saves( $id, $_REQUEST );

			if ( is_wp_error( $response ) ) {
				$this->error( $response );
			}

			return array(
				'success' => true,
				'data'    => $response,
			);

		}

		/**
		 * Save the file resulted from the content of an html elemenet
		 *
		 * @return array
		 */
		public function action_save_content_thumb() {

			if ( ! isset( $_FILES['preview_file'] ) ) {
				$this->error( __( 'Missing preview file', 'thrive-cb' ), 500 );
			}

			/** @var TCB_Symbol_Element $element */
			if ( ! ( $element = tcb_elements()->element_factory( 'symbol' ) ) || ! is_a( $element, 'TCB_Element_Abstract' ) ) {
				$this->error( __( 'Invalid element type', 'thrive-cb' ), 500 );
			}

			$data = $element->generate_preview( $this->param( 'post_id' ), $this->param( 'element_type' ) );

			if ( is_wp_error( $data ) ) {
				$this->error( $data );
			}

			return array(
				'success' => true,
				'data'    => $data,
			);

		}

		/**
		 * Render widget for editor frame
		 *
		 * @return array
		 */
		public function action_widget_render() {
			global $wp_widget_factory;

			$widget_data = $this->param( 'data', null, false );
			$widget_type = $this->param( 'widget' );

			$content     = '';
			$widget_data = array_map( 'wp_unslash', $widget_data );

			foreach ( $wp_widget_factory->widgets as $widget ) {
				if ( $widget->option_name === $widget_type ) {

					ob_start();

					$widget->widget( tve_get_sidebar_default_args( $widget ), $widget_data );

					$content = ob_get_contents();

					ob_end_clean();
				}
			}

			$content .= sprintf( '<div class="widget-config" style="display: none;">__CONFIG_thrive_widget__%s__CONFIG_thrive_widget__</div>',
				json_encode( array_merge( $widget_data, array( 'type' => $widget_type ) ) )
			);

			return apply_filters( 'tcb_widget_data_' . $widget_type,
				array(
					'success' => true,
					'content' => $content,
				) );
		}

		/**
		 * Adds / Removes Content blocks from favorites
		 *
		 * @return array
		 */
		public function action_cb_favorite_tpl() {
			$pack     = $this->param( 'pack', 0 );
			$template = (int) $this->param( 'template' );
			$status   = (int) $this->param( 'status' );


			if ( empty( $pack ) || empty( $template ) ) {
				$this->error( __( 'Invalid arguments', 'thrive-cb' ), 500 );
			}

			$favorites = get_option( 'thrv_fav_content_blocks', array() );

			if ( ! is_array( $favorites ) ) {
				/**
				 * Security check!
				 */
				$favorites = array();
			}

			if ( empty( $favorites[ $pack ] ) || ! is_array( $favorites[ $pack ] ) ) {
				$favorites[ $pack ] = array();
			}

			if ( $status ) {
				/**
				 * Add to favorites
				 */
				$favorites[ $pack ][] = $template;
			} else {
				/**
				 * Remove from favorites
				 */
				$position = array_search( $template, $favorites[ $pack ] );
				if ( $position !== false ) {
					unset( $favorites[ $pack ][ $position ] );
				}
			}

			update_option( 'thrv_fav_content_blocks', $favorites );

			return array(
				'success' => true,
				'status'  => $status,
			);
		}

		/**
		 * Updates the template palette with the modifications from the user
		 *
		 * @return array
		 */
		public function action_template_palette() {

			$previous_id = (int) $this->param( 'previous_id' );
			$active_id   = (int) $this->param( 'active_id' );
			$post_id     = (int) $this->param( 'post_id' );

			$previous_template_data = json_decode( stripslashes( $this->param( 'previous_template_data', array(), false ) ), true );
			$active_template_data   = json_decode( stripslashes( $this->param( 'active_template_data', array(), false ) ), true );

			$whitelist = array(
				'id',
				'color',
				'gradient',
				'hsl',
				'hsl_parent_dependency',
			);

			foreach ( $previous_template_data as $type => $values ) {
				foreach ( $values as $key => $value ) {
					$filtered = array_intersect_key( $value, array_flip( $whitelist ) );

					$previous_template_data[ $type ][ $key ] = $filtered;
				}
			}

			$landing_page = tcb_landing_page( $post_id );

			$landing_page->update_template_palette( $active_id, $previous_id, $previous_template_data );


			$whitelistTemplateMeta = array(
				'id',
				'color',
				'gradient',
				'name',
				'custom_name',
				'parent',
				'hsl',
				'hsl_parent_dependency',
			);

			foreach ( $active_template_data as $type => $values ) {
				foreach ( $values as $key => $value ) {
					$filtered = array_intersect_key( $value, array_flip( $whitelistTemplateMeta ) );

					$active_template_data[ $type ][ $key ] = $filtered;
				}
			}

			$landing_page->update_template_css_variables( $active_template_data );

			return array( 'success' => true );
		}

		/**
		 * Resets the active palette
		 *
		 * Gets the original palette values and overrides them into the modified palette values
		 *
		 * @return array
		 */
		public function action_reset_template_palette() {
			$active_id = (int) $this->param( 'active_id' );
			$post_id   = (int) $this->param( 'post_id' );

			$landing_page = tcb_landing_page( $post_id );

			$palettes = $landing_page->palettes;

			$palettes['modified'][ $active_id ] = $palettes['original'][ $active_id ];

			$landing_page->update_template_palette( $active_id, $active_id, $palettes['original'][ $active_id ] );

			foreach ( $landing_page->template_vars as $type => $values ) {
				$column = $type;
				if ( $type === 'colours' ) {
					$column = 'colors';
				}

				if ( empty( $values ) || ! is_array( $values ) ) {
					continue;
				}

				foreach ( $values as $key => $value ) {
					if ( empty( $landing_page->template_vars[ $type ][ $key ] ) || empty( $palettes['original'][ $active_id ][ $column ][ $key ] ) ) {
						continue;
					}

					$landing_page->template_vars[ $type ][ $key ] = array_merge( $landing_page->template_vars[ $type ][ $key ], $palettes['original'][ $active_id ][ $column ][ $key ] );
				}
			}

			$landing_page->update_template_css_variables( array(
				'colors'    => $landing_page->template_vars['colours'],
				'gradients' => $landing_page->template_vars['gradients'],
			) );

			return array(
				'success' => true,
			);
		}

		/**
		 * Save a couple of menu item styles for a specific template
		 */
		public function action_save_menu_item_style() {
			$template_id   = (int) $this->param( 'template_id' );
			$template_name = $this->param( 'template_name' );
			$styles        = json_decode( wp_unslash( htmlspecialchars_decode( $this->param( 'styles', null, false ) ) ), true );

			$templates = tcb_elements()->element_factory( 'menu_item' )->get_templates();
			$found     = false;
			foreach ( $templates as $i => $tpl ) {
				if ( (int) $tpl['id'] === $template_id ) {
					$found = $i;
					break;
				}
			}

			if ( $template_id && $styles ) {
				$data = array(
					'id'     => $template_id,
					'name'   => $template_name,
					'styles' => $styles,
				);
				if ( $found !== false ) {
					$templates[ $found ] = $data;
				} else {
					$templates[] = $data;
				}
				update_option( 'tve_menu_item_templates', $templates, 'no' );
			}

			return array(
				'success'   => true,
				'templates' => $templates,
			);
		}

		/**
		 * Save user preference regarding distraction free mode
		 */
		public function action_froala_mode() {

			$is_on   = $this->param( 'froala_mode' );
			$user_id = get_current_user_id();

			update_user_meta( $user_id, 'froalaMode', $is_on );
			delete_user_meta( $user_id, 'distraction_free' );

			$this->json( get_user_meta( $user_id, 'froalaMode' ) );
		}


		/**
		 * Update a post meta
		 * used on lp-build mostly
		 */
		public function action_update_post_meta() {
			/* Prevent updating unwanted things */
			$allowed_meta_keys = [
				'tve_tpl_button_data',
			];

			$meta_key = $this->param( 'meta_key' );

			if ( ! in_array( $meta_key, $allowed_meta_keys, true ) ) {
				$this->error( __( 'You are not allowed to update this meta', 'thrive-cb' ) );
			}

			$value   = $this->param( 'meta_value', null, false );
			$post_id = $this->param( 'post_id', 0 );

			update_post_meta( $post_id, $meta_key, $value );

			$this->json( get_post_meta( $post_id, $meta_key, true ) );
		}

		/**
		 * Manage default styles.
		 *
		 */
		public function action_default_styles() {
			$do = $this->param( '_do', '' );

			switch ( $do ) {
				case 'save':
					$styles_api = tcb_default_style_provider();
					$styles     = $styles_api->get_styles();
					$data       = (array) $this->param( 'json_rules', array(), false );
					foreach ( $data as $type => $rules ) {
						if ( isset( $styles[ $type ] ) ) {
							$styles[ $type ] = json_decode( stripslashes( $rules ), true );
						}
					}
					/* remove unused font imports */
					$css_string = $styles_api->get_processed_styles( $styles, 'string', false );
					foreach ( $styles['@imports'] as $k => $import ) {
						$font = TCB_Utils::parse_css_import( $import );
						if ( strpos( $css_string, $font['family'] ) === false ) {
							/* font family name not found in a CSS rule => remove it */
							unset( $styles['@imports'][ $k ] );
						}
					}
					$styles['@imports'] = array_merge( $styles['@imports'], array_map( 'stripslashes', (array) $this->param( 'imports', array(), false ) ) );
					$styles['@imports'] = TCB_Utils::merge_google_fonts( $styles['@imports'] );

					$styles_api->save_styles( $styles );

					break;
				default:
					break;
			}
			/* always include default styles in this response */
			add_filter( 'tcb_output_default_styles', '__return_true' );

			return tve_get_shared_styles( '', '300', false );
		}


		/**
		 * Return all content templates and symbols
		 */
		public function action_get_ct_symbols() {
			/** @var TCB_Symbol_Element $symbol_element */
			$symbol_element = tcb_elements()->element_factory( 'symbol' );
			/** @var TCB_Ct_Element $ct_element */
			$ct_element = tcb_elements()->element_factory( 'ct' );

			$args      = $this->param( 'args', array() );
			$symbols   = $symbol_element->get_all( $args );
			$templates = $ct_element->get_list();

			if ( is_wp_error( $symbols ) ) {
				$this->error( __( 'Error when retrieving symbols', 'thrive-cb' ), 500 );
			}

			return array(
				'success' => true,
				'data'    => array(
					'symbols'           => $symbols,
					'content_templates' => $templates,
				),
			);
		}

		/**
		 * Action for deleting a symbol
		 *
		 * @return array
		 */
		public function action_delete_symbols() {
			$id      = $this->param( 'key', 0 );
			$success = true;

			if ( get_post_type( $id ) !== 'tcb_symbol' ) {
				$success = false;
			}

			$result = wp_trash_post( $id );

			return array(
				'success' => $success,
				'data'    => $result,
			);
		}

		/**
		 * Action for renaming a Symbol
		 *
		 * @return array
		 */
		public function action_rename_symbols() {
			$id       = $this->param( 'elementId', 0 );
			$new_name = $this->param( 'newName', 'Symbol Name' );
			$success  = true;
			$symbol   = get_post( $id );

			if ( ! $symbol ) {
				$success = false;
			}

			wp_update_post( array(
				'ID'         => $id,
				'post_title' => $new_name,
			) );

			$symbol->post_title = $new_name;

			return array(
				'success' => $success,
				'data'    => json_encode( $symbol ),
			);
		}

		/**
		 * Action for renaming a template
		 *
		 * @return array
		 */
		public function action_rename_content_template() {
			$id                 = $this->param( 'elementId', 0 );
			$new_name           = $this->param( 'newName', 'Template Name' );
			$existing_templates = get_option( 'tve_user_templates' );

			$existing_templates[ $id ]['name'] = $new_name;

			update_option( 'tve_user_templates', $existing_templates, 'no' );

			return array(
				'success' => true,
				'data'    => json_encode( $existing_templates[ $id ] ),
			);
		}

		/**
		 * Save a file configuration
		 */
		public function action_file_upload_config_save() {
			$file_id         = (int) $this->param( 'file_id', 0 );
			$current_post_id = (int) $this->param( 'post_id', 0 );
			$post_title      = sprintf( __( 'File upload for post %s', 'thrive-cb' ), $current_post_id );

			$instance = FileUploadConfig::get_one( $file_id )
			                            ->set_config( $this->param( 'file_setup', null, false ) )
			                            ->save( $post_title );

			if ( is_wp_error( $instance ) ) {
				return array(
					'success' => false,
					'message' => 'Something went wrong while saving the file upload configuration',
				);
			}

			return array(
				'success' => true,
				'file_id' => $instance->ID,
			);
		}

		/**
		 * Deletes a previously saved file upload configuration
		 *
		 * @return array
		 */
		public function action_file_upload_config_delete() {
			FileUploadConfig::get_one( $this->param( 'file_id', 0 ) )->delete();

			return array( 'success' => true );
		}

		/**
		 * Process TAr content before saving it.
		 * This is always called via ajax before saving a piece of content in TAr / TTB / any other plugin containing TAr
		 * It's main purpose is to save any needed data to the database and return inserted ids so those can be updated in HTML
		 *
		 * @return array
		 */
		public function action_content_pre_save() {
			/**
			 * Filters the ajax response triggered before saving the actual post/page content
			 *
			 * @param array $response
			 */
			return apply_filters( 'tcb.content_pre_save', array( 'success' => true ), $_POST );
		}

		/**
		 * Deletes a previously saved file upload configuration
		 *
		 * @return array
		 */
		public function action_form_settings_delete() {
			FormSettings::get_one( $this->param( 'settings_id', 0 ) )->delete();

			return array( 'success' => true );
		}

		/**
		 * force cloud templates
		 *
		 * @return array
		 */
		public function action_nocached_cloud_data() {
			$type = $this->param( 'type', '' );

			$tpls = array();

			tve_delete_cloud_saved_data();

			if ( $type === 'lps' ) {
				$tpls = function_exists( 'tve_get_cloud_templates' ) ? tve_get_cloud_templates( array(), array( 'nocache' => true ) ) : array();
			} elseif ( $type === 'blocks' ) {
				$tpls = tcb_elements()->element_factory( 'contentblock' )->get_blocks();
			}

			global $wpdb;
			if ( ! empty( $wpdb->last_error ) ) {
				$this->error( $wpdb->last_error );
			}

			return array(
				'success' => true,
				'tpls'    => $tpls,
			);
		}

		/**
		 * Dismiss user tooltip
		 *
		 * @return array
		 */
		public function action_dismiss_tooltip() {
			$response = array();

			$user = wp_get_current_user();
			/* double check, just to be sure */
			if ( $user ) {
				$key   = $this->param( 'meta_key' );
				$value = $this->param( 'meta_value' );
				update_user_meta( $user->ID, $key, $value );
				$response[ $key ] = $value;
			}

			return $response;
		}

		/**
		 * Get the intercom article from a specific url
		 *
		 * @return mixed
		 */
		public function action_get_intercom_article() {
			$key = $this->param( 'url_key' );

			$article_url = tve_get_intercom_article_url( $key );

			return empty( $article_url ) ? '' : tve_dash_api_remote_get( $article_url, array(
				'headers' => array(
					'Authorization' => 'Bearer dG9rOjcyYjBkZGU5X2FiZTJfNGJiM19iNTY4X2Q2NzVmNDNiOGZjMToxOjA=',
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
				),
			) );
		}

		/**
		 * Actions related to landing pages. Currently handles downloading and applying landing pages on WP pages.
		 */
		public function action_landing_page_cloud() {
			$task = $this->param( 'task' );

			if ( ! $task ) {
				$this->error( __( 'Invalid task', 'thrive-cb' ) );
			}

			/**
			 * Post Constants - similar with tve_globals but do not depend on the Landing Page Key
			 *
			 * Usually stores flags for a particular post
			 */
			if ( ! empty( $_POST['tve_post_constants'] ) && is_array( $_POST['tve_post_constants'] ) && ! empty( $_POST['post_id'] ) ) {
				update_post_meta( $_POST['post_id'], '_tve_post_constants', $_POST['tve_post_constants'] );
			}

			if ( isset( $_POST['header'] ) ) {
				update_post_meta( $_POST['post_id'], '_tve_header', (int) $_POST['header'] );
			}
			if ( isset( $_POST['footer'] ) ) {
				update_post_meta( $_POST['post_id'], '_tve_footer', (int) $_POST['footer'] );
			}

			try {
				switch ( $_POST['task'] ) {
					case 'download':
						$template = isset( $_POST['template'] ) ? $_POST['template'] : '';
						$post_id  = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';
						if ( empty( $template ) ) {
							throw new Exception( __( 'Invalid template', 'thrive-cb' ) );
						}
						TCB_Landing_Page::apply_cloud_template( $post_id, $template );

						$response = array(
							'success' => true,
						);
						break;
					default:
						$response = array(
							'success' => false,
							'message' => __( 'Invalid task', 'thrive-cb' ),
						);
						break;
				}
			} catch ( Exception $e ) {
				$response = array(
					'success' => false,
					'message' => $e->getMessage(),
				);
			}

			return $response;
		}

		public function action_content_export() {
			$response = array(
				'success' => true,
			);

			$template_name = sanitize_file_name( $this->param( 'template_name' ) );
			$post_id       = (int) $this->param( 'post_id' );

			if ( empty( $template_name ) || empty( $post_id ) ) {
				$response['success'] = false;
				$response['message'] = __( 'Invalid request', 'thrive-cb' );
			} else {
				$transfer = new TCB_Content_Handler();

				try {
					$data                = $transfer->export( $post_id, $template_name );
					$response['url']     = $data['url'];
					$response['message'] = __( 'Content exported successfully!', 'thrive-cb' );

				} catch ( Exception $e ) {
					$response['success'] = false;
					$response['message'] = $e->getMessage();
				}
			}

			return $response;
		}

		public function action_content_import() {
			$response = array(
				'success' => true,
				'message' => '',
			);

			$page_id       = (int) $this->param( 'page_id' );
			$attachment_id = (int) $this->param( 'attachment_id' );

			$allow_import = apply_filters( 'tve_allow_import_content', true, get_post_type( $page_id ) );
			if ( empty( $attachment_id ) || empty( $page_id ) || ! $allow_import ) {
				$this->error( 'Invalid attachment id', 'thrive-cb' );
			}

			$transfer = new TCB_Content_Handler();
			try {
				$file         = get_attached_file( $attachment_id, true );
				$content_data = $transfer->import( $file, $page_id );

				$content_data['content'] = tve_do_wp_shortcodes( tve_thrive_shortcodes( stripslashes( $content_data['content'] ), true ), true );
				//do shortcode to make sure that all elements are properly displayed
				$content_data['content']    = do_shortcode( $content_data['content'] );
				$content_data['inline_css'] = do_shortcode( $content_data['inline_css'] );

				$response['content_data'] = apply_filters( 'tve_import_content_data', $content_data );
				$response['message']      = __( 'Content imported successfully!', 'thrive-cb' );

			} catch ( Exception $e ) {
				$this->error( $e->getMessage() );
			}

			return $response;
		}

		/**
		 * export a Landing Page as a Zip file
		 */
		public function action_landing_page_export() {
			$response = array(
				'success' => true,
			);

			$template_name = sanitize_file_name( $this->param( 'template_name' ) );
			$post_id       = (int) $this->param( 'post_id' );

			if ( empty( $template_name ) || empty( $post_id ) || ! tve_post_is_landing_page( $post_id ) ) {
				$response['success'] = false;
				$response['message'] = __( 'Invalid request', 'thrive-cb' );
			} else {
				$transfer            = new TCB_Landing_Page_Transfer();
				$thumb_attachment_id = (int) $this->param( 'thumb_id', 0 );

				try {
					$data                = $transfer->export( $post_id, $template_name, $thumb_attachment_id );
					$response['url']     = $data['url'];
					$response['message'] = __( 'Landing Page exported successfully!', 'thrive-cb' );

				} catch ( Exception $e ) {
					$response['success'] = false;
					$response['message'] = $e->getMessage();
				}
			}

			return $response;
		}

		/**
		 * import a landing page from an attachment ID received in POST
		 * the attachment should be a .zip file created with the "Export Landing Page" functionality
		 */
		public function action_landing_page_import() {
			$response = array(
				'success' => true,
				'message' => '',
			);

			$page_id       = (int) $this->param( 'page_id' );
			$attachment_id = (int) $this->param( 'attachment_id' );

			$is_post_type_allowed = apply_filters( 'tve_allowed_post_type', true, get_post_type( $page_id ) );
			if ( empty( $attachment_id ) || empty( $page_id ) || ! $is_post_type_allowed ) {
				$this->error( 'Invalid attachment id', 'thrive-cb' );
			}

			$transfer = new TCB_Landing_Page_Transfer();
			try {
				$file                = get_attached_file( $attachment_id, true );
				$landing_page_id     = $transfer->import( $file, $page_id );
				$response['url']     = tcb_get_editor_url( $landing_page_id );
				$response['message'] = __( 'Landing Page imported successfully!', 'thrive-cb' );

			} catch ( Exception $e ) {
				$this->error( $e->getMessage() );
			}

			return $response;
		}

		public function action_get_default_notification() {
			return Main::get_default_notification_element();
		}

		public function action_update_advanced_optimization() {
			$response = array(
				'success' => true,
			);

			$post_id = (int) $this->param( 'post_id' );
			$name    = $this->param( 'option_name' );
			$value   = $this->param( 'option_value' );

			if ( ! in_array( $name, [ \TCB\Lightspeed\Gutenberg::DISABLE_GUTENBERG, \TCB\Lightspeed\Woocommerce::DISABLE_WOOCOMMERCE ] ) ) {
				$this->error( 'No access', 403 );
			}

			if ( $value === 'inherit' ) {
				delete_post_meta( $post_id, $name );
			} else {
				update_post_meta( $post_id, $name, (int) $value );
			}

			return $response;
		}

		/**
		 * Changes the active palette of a LP by changing the active ID in the config
		 *
		 * @return bool[]
		 */
		public function action_change_palette() {
			$response = array(
				'success' => true,
			);

			$previous_id = (int) $this->param( 'previous_id' );
			$active_id   = (int) $this->param( 'active_id' );
			$version     = (int) $this->param( 'version' );
			$post_id     = (int) $this->param( 'post_id' );

			if ( $previous_id === $active_id ) {
				//We do nothing here
				return $response;
			}

			$landing_page        = tcb_landing_page( $post_id );
			$lp_palette_instance = $landing_page->get_palette_instance();

			if ( $version === 2 ) {

				$config = $lp_palette_instance->get_smart_lp_palettes_v2( $post_id );

				$config['active_id'] = $active_id;

				$lp_palette_instance->update_lp_palettes_v2( $config );
				$lp_palette_instance->update_master_hsl( $config['palettes'][ $active_id ]['modified_hsl'], $active_id );
			}

			return $response;
		}

		/**
		 * Update the master color in Palette
		 * Also used for reset
		 *
		 *
		 * @return bool[] $response
		 */
		public function action_update_palette() {
			$response = array(
				'success' => true,
			);

			$hsl                 = $this->param( 'hsl' );
			$post_id             = $this->param( 'post_id' );
			$landing_page        = tcb_landing_page( $post_id );
			$lp_palette_instance = $landing_page->get_palette_instance();
			$skin_palettes       = $lp_palette_instance->get_smart_lp_palettes_v2();
			$active_id           = ! empty( $skin_palettes ) ? (int) $skin_palettes['active_id'] : 0;

			$landing_page->update_master_hsl( $hsl, $active_id );
			$landing_page->update_variables_in_config( $hsl );

			return $response;
		}

		public function action_update_auxiliary_variable() {
			$response = array(
				'success' => true,
			);

			$post_id  = (int) $this->param( 'post_id' );
			$color_id = (int) $this->param( 'id' );
			$color    = (string) $this->param( 'color' );

			tcb_landing_page( $post_id )->update_auxiliary_variable( $color_id, $color );

			return $response;
		}
	}
}
global $tcb_ajax_handler;
$tcb_ajax_handler = new TCB_Editor_Ajax();

/**
 * If ajax call, register the handler
 */
if ( wp_doing_ajax() ) {
	$tcb_ajax_handler->init();
} else {
	/* in other cases, generate nonce and assign it */
	add_filter( 'tcb_main_frame_localize', array( $tcb_ajax_handler, 'localize' ) );
}


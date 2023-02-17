<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-automator
 */

namespace Thrive\Automator;

use Exception;
use StdClass;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Update
 *
 * @package Thrive\Automator
 */
class Update {

	const THRIVE_KEY = '@#$()%*%$^&*(#@$%@#$%93827456MASDFJIK3245';

	const API_URL = 'https://service-api.thrivethemes.com/plugin/update';

	/**
	 * Update hooks
	 */
	public static function init() {
		add_filter( 'plugins_api', [ __CLASS__, 'api_call' ], 10, 3 );
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_for_update' ] );
		add_filter( 'plugin_row_meta', [ __CLASS__, 'add_check_updates_link' ], 10, 2 );
		add_action( 'all_admin_notices', [ __CLASS__, 'display_manual_check_result' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_manual_check' ] );
	}

	/**
	 * Prepare request args for api call
	 *
	 * @param $action
	 * @param $args
	 *
	 * @return array
	 */
	public static function prepare_request( $action = '', $args = [] ) {
		global $wp_version;

		return [
			'body'       => [
				'action'  => $action,
				'request' => serialize( $args ),
				'api-key' => md5( home_url() ),
			],
			'sslverify'  => false,
			'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
		];
	}

	/**
	 * Handle plugins_api call for Thrive Automator
	 *
	 * @param $def
	 * @param $action
	 * @param $args
	 *
	 * @return false|mixed|string|WP_Error
	 */
	public static function api_call( $def, $action, $args ) {
		if ( empty( $args->slug ) || $args->slug !== TAP_DOMAIN ) {
			return false;
		}

		$args->version = TAP_VERSION;

		$params  = static::prepare_request( $action, $args );
		$request = static::remote_request( $params );

		if ( is_wp_error( $request ) ) {
			$response = new WP_Error( 'thrive_automator_api_failed', __( 'An Unexpected HTTP Error occurred during the API request', TAP_DOMAIN ) . '</p> <p><a href="?" onclick="document.location.reload(); return false;"> ' . __( 'Try again', TAP_DOMAIN ) . '', $request->get_error_message() );
		} else {
			try {
				$response = json_decode( Utils::safe_unserialize( wp_remote_retrieve_body( $request ) ) );
				if ( $response === false ) {
					$response = new WP_Error( 'thrive_automator_api_failed', __( 'An unknown error occurred', TAP_DOMAIN ), $response );
				} else {
					$response = static::convert_to_wp_format( $response );
				}
			} catch ( Exception $e ) {
				$response = new WP_Error( 'thrive_automator_api_failed', __( 'An unknown error occurred', TAP_DOMAIN ), [] );
			}
		}

		return $response;
	}

	/**
	 * Calc the hash that should be sent on API's requests
	 *
	 * @param $data
	 *
	 * @return string
	 */
	private static function calc_hash( $data ): string {
		return md5( static::THRIVE_KEY . serialize( $data ) . static::THRIVE_KEY );
	}

	/**
	 * Do a post request to service api while plugin data is required
	 *
	 * @param $args
	 *
	 * @return array|WP_Error
	 */
	private static function remote_request( $args ) {
		$args = array_merge_recursive( [
			'timeout' => 10,
			'headers' => [
				'Accept' => 'application/json',
			],
			'body'    => [
				'installed_version'  => TAP_VERSION,
				'api_slug'           => TAP_SLUG,
				'client_php_version' => PHP_VERSION,
				'client_site_url'    => home_url(),
				'wp_version'         => (string) get_bloginfo( 'version' ),
			],
		], $args );


		$maybe_tpm = get_option( 'tpm_connection', array() );
		if ( ! empty( $maybe_tpm ) && is_array( $maybe_tpm ) && ! empty( $maybe_tpm['ttw_id'] ) && is_numeric( $maybe_tpm['ttw_id'] ) ) {
			$args['body']['ttw_id'] = (string) $maybe_tpm['ttw_id'];
		}

		$update_channel = get_option( 'tve_update_option', 'stable' );
		if ( ! empty( $update_channel ) && is_string( $update_channel ) ) {
			$args['body']['channel'] = $update_channel;
		}

		$url = add_query_arg( [
			'p' => static::calc_hash( $args['body'] ),
		], static::API_URL );


		return wp_remote_post( $url, $args );
	}

	/**
	 * Insert the Check for updates message on plugin row
	 *
	 * @param $plugin_meta
	 * @param $plugin_file
	 *
	 * @return mixed
	 */
	public static function add_check_updates_link( $plugin_meta, $plugin_file ) {
		$is_network_admin = is_network_admin();
		$in_plugin_admin  = ! is_multisite() || $is_network_admin;
		if ( $in_plugin_admin && $plugin_file === TAP_PLUGIN_FILE_PATH && current_user_can( 'update_plugins' ) ) {
			$link_url = wp_nonce_url(
				add_query_arg(
					[
						'puc_check_for_updates' => 1,
						'puc_slug'              => TAP_DOMAIN,
					],
					$is_network_admin ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' )
				),
				'puc_check_for_updates'
			);

			$plugin_meta[] = sprintf( '<a href="%s">%s</a>', esc_attr( $link_url ), __( 'Check for updates', TAP_DOMAIN ) );
		}

		return $plugin_meta;
	}

	/**
	 * Do API call to check if the Automator has an update
	 *
	 * @param $transient
	 *
	 * @return mixed
	 */
	public static function check_for_update( $transient = null ) {

		$args = static::prepare_request( 'plugin_update' );

		$response = static::remote_request( $args );

		if ( ! is_wp_error( $response ) ) {
			try {
				$data = json_decode( Utils::safe_unserialize( wp_remote_retrieve_body( $response ) ) );
				/* if we have a different version available */
				if ( ! empty( $data->version ) && version_compare( TAP_VERSION, $data->version, '!=' ) ) {
					$data         = static::convert_to_wp_format( $data );
					$data->plugin = TAP_DOMAIN;

					if ( version_compare( TAP_VERSION, $data->version, '<' ) ) {
						$transient->response[ TAP_PLUGIN_FILE_PATH ] = $data;
					}
				}
			} catch ( Exception $e ) {
			}
		}

		return $transient;
	}

	/**
	 * Transform the update into the format used by WordPress native plugin API.
	 *
	 * @return object
	 */
	public static function convert_to_wp_format( $data ) {
		$update = new StdClass;

		$same_format = [
			'name',
			'slug',
			'version',
			'requires',
			'tested',
			'rating',
			'upgrade_notice',
			'num_ratings',
			'downloaded',
			'homepage',
			'last_updated',
			'requires',
		];
		foreach ( $same_format as $field ) {
			if ( isset( $data->$field ) ) {
				$update->$field = $data->$field;
			} else {
				$update->$field = null;
			}
		}

		//Other fields need to be renamed and/or transformed.
		$update->package       = $data->download_url;
		$update->download_link = $data->download_url; //It is required for the beta update functionality
		$update->new_version   = $data->version;

		if ( ! empty( $data->author_homepage ) ) {
			$update->author = sprintf( '<a href="%s">%s</a>', $data->author_homepage, $data->author );
		} else {
			$update->author = $data->author;
		}


		if ( is_object( $data->sections ) ) {
			$update->sections = get_object_vars( $data->sections );
		} elseif ( is_array( $data->sections ) ) {
			$update->sections = $data->sections;
		} else {
			$update->sections = array( 'description' => '' );
		}

		if ( empty( $update->icons ) ) {
			$update->icons = [];
		}

		$update->icons['1x'] = TAP_PLUGIN_URL . 'icons/logo-icon.png';

		return $update;
	}

	/**
	 * Do the force update check
	 */
	public static function handle_manual_check() {
		$should_check
			= isset( $_GET['puc_check_for_updates'], $_GET['puc_slug'] )
			  && $_GET['puc_slug'] === TAP_DOMAIN
			  && current_user_can( 'update_plugins' )
			  && check_admin_referer( 'puc_check_for_updates' );

		if ( $should_check ) {
			$status = static::should_update() ? 'update_available' : 'no_update';

			wp_redirect( add_query_arg(
				[
					'puc_update_check_result' => $status,
					'puc_slug'                => TAP_DOMAIN,
				],
				is_network_admin() ? network_admin_url( 'plugins.php' ) : admin_url( 'plugins.php' )
			) );
		}
	}

	/**
	 * Check whether or not updates are available
	 *
	 * @return bool
	 */
	public static function should_update() {
		$response = static::remote_request( [
			'body' => [
				'checking_for_updates' => '1',
			],
		] );


		if ( ! is_wp_error( $response ) ) {
			try {
				$data = json_decode( Utils::safe_unserialize( wp_remote_retrieve_body( $response ) ) );

				/* if we have a different version available */
				if ( ! empty( $data->version ) && version_compare( $data->version, TAP_VERSION, '>' ) ) {
					if ( ! empty( $data->upgrade_notice ) ) {
						remove_action( 'in_plugin_update_message-' . TAP_PLUGIN_FILE_PATH, [ __CLASS__, 'upgrade_notice' ], 10 );
						add_action( 'in_plugin_update_message-' . TAP_PLUGIN_FILE_PATH, [ __CLASS__, 'upgrade_notice' ], 10, 2 );
					}

					return true;
				}
			} catch ( Exception $e ) {
			}
		}

		return false;
	}

	/**
	 * Output an upgrade notice
	 *
	 * @param $plugin_data
	 * @param $response
	 */
	public function upgrade_notice( $plugin_data, $response ) {
		echo $response && $response->upgrade_notice ? $response->upgrade_notice : '';
	}

	/**
	 * Display admin notice based on check result
	 */
	public static function display_manual_check_result() {
		if ( isset( $_GET['puc_update_check_result'], $_GET['puc_slug'] ) && ( $_GET['puc_slug'] === TAP_DOMAIN ) ) {
			$status = (string) $_GET['puc_update_check_result'];
			if ( $status === 'no_update' ) {
				$message = __( 'This plugin is up to date.', TAP_DOMAIN );
			} else if ( $status === 'update_available' ) {
				$message = __( 'A new version of this plugin is available.', TAP_DOMAIN );
			} else {
				$message = sprintf( 'Unknown update checker status "%s"', htmlentities( $status ) );
			}
			printf(
				'<div class="updated"><p>%s</p></div>',
				apply_filters( 'puc_manual_check_message-' . TAP_DOMAIN, $message, $status )
			);
		}
	}
}

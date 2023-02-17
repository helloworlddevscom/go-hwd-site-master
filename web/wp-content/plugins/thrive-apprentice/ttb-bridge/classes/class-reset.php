<?php
/**
 * Thrive Themes - https://thrivethemes.com
 *
 * @package thrive-apprentice
 */

namespace TVA\TTB;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Silence is golden!
}

/**
 * Class Reset
 *
 * @package TVA\TTB
 * @project : thrive-apprentice
 */
class Reset {
	public static function init() {
		add_submenu_page( null, null, null, 'manage_options', 'tva-reset', [ __CLASS__, 'menu_page' ] );

		add_action( 'wp_ajax_tva_progress_reset', [ __CLASS__, 'progress_reset' ] );
		add_action( 'wp_ajax_tva_skin_reset', [ __CLASS__, 'skin_reset' ] );
		add_action( 'wp_ajax_tva_products_reset', [ __CLASS__, 'products_reset' ] );
		add_action( 'wp_ajax_tva_remove_demo_content', [ __CLASS__, 'remove_demo_content' ] );
		add_action( 'wp_ajax_tva_create_demo_content', [ __CLASS__, 'create_demo_content' ] );
	}

	/**
	 * Admin menu page for the reset
	 */
	public static function menu_page() {
		include \TVA_Const::plugin_path( 'ttb-bridge/templates/reset-page.php' );
	}

	/**
	 * Skin Reset
	 */
	public static function skin_reset() {
		if ( is_user_logged_in() && \TVA_Product::has_access() && \TVA\TTB\Check::is_end_user_site() ) {
			update_option( 'tva_default_skin', 0 );
			Main::show_legacy_design(); // make sure this is shown
			Main::set_use_builder_templates( 0 );

			tva_palettes()->delete_palette();

			/**
			 * Reset also the Master HSL color code to the default one (ShapeShift color)
			 */
			tva_palettes()->reset_master_hsl();

			$cloud_skins = Main::get_all_skins( false, false );

			foreach ( $cloud_skins as $skin ) {
				$skin->remove();
				wp_delete_term( $skin->term_id, SKIN_TAXONOMY );
			}

			//We need to reset also the share color setting
			tva_get_settings_manager()->factory( 'share_ttb_color' )->set_value( 0 );
		}
	}

	/**
	 * Reset the admin progress
	 */
	public static function progress_reset() {
		if ( is_user_logged_in() && \TVA_Product::has_access() ) {
			setcookie( 'tva_learned_lessons', '', 1, '/' );
			$_COOKIE['tva_learned_lessons'] = '';

			delete_user_meta( get_current_user_id(), 'tva_learned_lessons' );
		}
	}

	/**
	 * Removes the demo content from the site
	 */
	public static function remove_demo_content() {
		if ( is_user_logged_in() && \TVA_Product::has_access() ) {
			tva_update_demo_content( false );
		}
	}

	/**
	 * Re-creates demo content
	 */
	public static function create_demo_content() {
		if ( is_user_logged_in() && \TVA_Product::has_access() ) {
			tva_update_demo_content();
		}
	}

	/**
	 * Reset products created from migration
	 */
	public static function products_reset() {
		if ( is_user_logged_in() && \TVA_Product::has_access() ) {
			\TVA\Product_Migration::revert_migrate();
		}
	}
}

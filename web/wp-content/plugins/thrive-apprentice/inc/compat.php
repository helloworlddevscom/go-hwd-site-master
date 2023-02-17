<?php
/**
 * Created by PhpStorm.
 * User: User
 * Date: 4/9/2019
 * Time: 13:20
 */

/**
 * Wishlist Member plugin adds a filter on `list_term_exclusions` which causes huge database load.
 * It executes sql queries for each category on each get_terms() call.
 * Apprentice (and TTB) use a lot of get_terms() function calls which in turn cause big performance hits
 *
 * We hook to the `list_term_exclusions` filter earlier, and make sure WLM does not execute its code if the get_terms() refers to a taxonomy used by TA / TTB
 */
add_filter( 'list_terms_exclusions', static function ( $exclusions, $args, $taxonomies ) {
	global $WishListMemberInstance;
	if ( ! empty( $WishListMemberInstance ) && array_intersect( $taxonomies, [ TVA_Const::COURSE_TAXONOMY, 'thrive_skin_tax', 'thrive_demo_tag', 'thrive_demo_category', \TVA\Product::TAXONOMY_NAME, TVA_Const::OLD_POST_TAXONOMY ] ) ) {
		/* this makes sure WLM does not execute all those sql queries */
		add_filter( 'wishlistmember_pre_get_option_only_show_content_for_level', '__return_zero' );

		/* Hook into the next available filter and remove the added __return_zero hook */
		add_filter( 'terms_clauses', static function ( $clauses ) {
			remove_filter( 'wishlistmember_pre_get_option_only_show_content_for_level', '__return_zero' );

			return $clauses;
		} );
	}

	return $exclusions;
}, 0, 4 );

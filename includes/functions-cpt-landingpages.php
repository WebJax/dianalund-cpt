<?php
/**
 * Resister Custom Post Type for Landingpages (landingssideer)
 *
 * All associations are registered as payed
 *
 */

/**
 * Register a landingpage post type.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */
	$labels = array(
		'name'               => _x( 'Landingssider', 'Landingssider', 'dianalund_txtdomain' ),
		'singular_name'      => _x( 'Landingsside', 'Landingsside', 'dianalund_txtdomain' ),
		'menu_name'          => _x( 'Landingssider', 'admin menu', 'dianalund_txtdomain' ),
		'name_admin_bar'     => _x( 'Landingsside', 'add new on admin bar', 'dianalund_txtdomain' ),
		'add_new'            => _x( 'Tilføj ny', 'fritid', 'dianalund_txtdomain' ),
		'add_new_item'       => __( 'Tilføj ny landingsside', 'dianalund_txtdomain' ),
		'new_item'           => __( 'Ny landingsside', 'dianalund_txtdomain' ),
		'edit_item'          => __( 'Ret landingsside', 'dianalund_txtdomain' ),
		'view_item'          => __( 'Se landingsside', 'dianalund_txtdomain' ),
		'all_items'          => __( 'Alle landingssider', 'dianalund_txtdomain' ),
		'search_items'       => __( 'Søg landingssideer', 'dianalund_txtdomain' ),
		'parent_item_colon'  => __( 'Forældre landingssider:', 'dianalund_txtdomain' ),
		'not_found'          => __( 'Ingen landingssider fundet.', 'dianalund_txtdomain' ),
		'not_found_in_trash' => __( 'Ingen landingssider fundet i skraldespand.', 'dianalund_txtdomain' )
	);

	$args = array(
		'labels'             		=> $labels,
    	'description'        		=> __( 'Opret landingssider til med specielt indhold.', 'dianalund_txtdomain' ),
		'public'             		=> true,
		'publicly_queryable' 		=> true,
		'show_ui'            		=> true,
		'show_in_menu'       		=> true,
		'query_var'          		=> true,
		'rewrite'            		=> array( 'slug' => 'landingsside' ),
		'capability_type'    		=> 'post',
		'has_archive'        		=> true,
		'hierarchical'       		=> false,
		'menu_position'      		=> 48,
		'menu_icon'					=> 'dashicons-welcome-widgets-menus',
		'supports'           		=> array( 'title', 'editor', 'thumbnail' ),
		'taxonomies' 				=> array('category')
	);

/**
 * Remove the slug from published post permalinks. Only affect our custom post type, though.
 *
 * From: https://kellenmace.com/remove-custom-post-type-slug-from-permalinks/
 *
 */
function dianalund_remove_cpt_slug( $post_link, $post, $leavename ) {
 
    if ( 'landingsside' != $post->post_type || 'publish' != $post->post_status ) {
        return $post_link;
    }
 
    $post_link = str_replace( '/' . $post->post_type . '/', '/', $post_link );
 
    return $post_link;
}
add_filter( 'post_type_link', 'dianalund_remove_cpt_slug', 10, 3 );

/**
 * Have WordPress match postname to any of our public post types (post, page, landingsside)
 * All of our public post types can have /post-name/ as the slug, so they better be unique across all posts
 * By default, core only accounts for posts and pages where the slug is /post-name/
 */
function dianalund_parse_request_trick( $query ) {
 
    // Only noop the main query
    if ( ! $query->is_main_query() )
        return;
 
    // Only noop our very specific rewrite rule match
    if ( 2 != count( $query->query ) || ! isset( $query->query['page'] ) ) {
        return;
    }
 
    // 'name' will be set if post permalinks are just post_name, otherwise the page rule will match
    if ( ! empty( $query->query['name'] ) ) {
        $query->set( 'post_type', array( 'post', 'page', 'landingsside' ) );
    }
}
add_action( 'pre_get_posts', 'dianalund_parse_request_trick' );


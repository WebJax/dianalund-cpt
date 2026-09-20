<?php
/*
Plugin Name: Dianalund-cpt
Plugin URI:  http://www.jaxweb.dk
Description: Custom Post Types til Dianalund.dk
Version:     2.0.0
Author:      jaxweb.dk
Author URI:  http://www.jaxweb.dk
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: dianalund_txtdomain
Domain Path: /languages
*/

/*
dianalund-cpt is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
dianalund-cpt is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with dianalund-cpt. If not, see {URI to Plugin License}.
*/

require_once dirname( __FILE__ ) . '/includes/functions-cvr-sync.php';

require_once dirname( __FILE__ ) . '/includes/class-cpt-gone-redirects.php';

function dianalund_cpt_boot_gone_redirects() {
    $redirects = new Cpt_Gone_Redirects(
        array(
            'butiksside'    => array( 'target' => '/butikker/', 'code' => 410 ),
            'erhvervsside'  => array( 'target' => '/erhverv/', 'code' => 410 ),
            'foreningsside' => array( 'target' => '/foreninger/', 'code' => 410),
            'landingsside'  => array( 'target' => '/landingssider/', 'code' => 410),
        )
    );
}
add_action( 'plugins_loaded', 'dianalund_cpt_boot_gone_redirects' );

function dianalund_cpt_setup_post_types()
{
    // register the four custom post types - $args are defined in the files required

    add_menu_page('', '', 'read', 'seperator-1', '', 'none', 45);

    require_once(dirname( __FILE__ ) . "/includes/functions-cpt-butikker.php");
    register_post_type( 'butiksside', $args ); // @phpantom-ignore
    add_action('save_post', 'save_butiks_meta', 999, 2); // save the custom fields
  
    require_once(dirname( __FILE__ ) . "/includes/functions-cpt-fritid.php");  
	register_post_type( 'foreningsside', $args ); // @phpantom-ignore
    add_action('save_post', 'save_forenings_meta', 999, 2); // save the custom fields
  
    require_once(dirname( __FILE__ ) . "/includes/functions-cpt-erhverv.php");  
 	register_post_type( 'erhvervsside', $args ); // @phpantom-ignore
    add_action('save_post', 'save_erhvervs_meta', 999, 2); // save the custom fields
  
    require_once(dirname( __FILE__ ) . "/includes/functions-cpt-landingpages.php");  
 	register_post_type( 'landingsside', $args ); // @phpantom-ignore
	add_action('save_post', 'save_landings_meta', 999, 2); // save the custom fields
  
	// enqueue datepicker and styles and special style to overcome minor z-index-problem with TinyMCE
	function enqueue_datepicker_ui_for_associations() {
		wp_register_script( 
			'custom-datepicker', 
			plugins_url( '/js/pubforce-admin.js', __FILE__ ), 
			array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'),
			time(),
			true
		);	
		
		$imageurl = plugins_url( '/js/date-picker.png', __FILE__ );		
		wp_localize_script ( 'custom-datepicker', 'pngurl', $imageurl );
		
		wp_enqueue_script ( 'custom-datepicker' );
		
		wp_enqueue_style( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'dianalund-cpt-zindex-styles', plugins_url('/css/dianalund-cpt-zindex-styles.css', __FILE__ ) );
		wp_enqueue_media();
		wp_enqueue_script('cpt-get-logo-cpt-js', get_template_directory_uri().'/js/get-logo-cpt.js', array('jquery'));
	}
	
	add_action('admin_enqueue_scripts','enqueue_datepicker_ui_for_associations');

	if ( ! function_exists( 'dianalund_cpt_add_page_number' ) )
	{
		function dianalund_cpt_add_page_number( $s )
		{
			global $post;
			$seo_custom_text_for_butik_og_erhverv = ". Du finder virksomheden på Dianalund.dk, byens samlende hjemmeside udviklet af Dianalund Udviklingsråd.";
			if( $post->post_type == 'butiksside' ) {
				$dianalund_cpt_payed = get_post_meta($post->ID, 'butik_payed_payedbutik', true);
				if ($dianalund_cpt_payed = "Intet") {		
					$dianalund_cpt_name = get_post_meta($post->ID, 'butik_payed_name', true);
					$dianalund_cpt_adress = get_post_meta($post->ID, 'butik_payed_adress', true);
					$dianalund_cpt_postal = get_post_meta($post->ID, 'butik_payed_postal', true);
					$dianalund_cpt_city = get_post_meta($post->ID, 'butik_payed_city', true);
					$dianalund_cpt_phone = get_post_meta($post->ID, 'butik_payed_phone', true);
					$s = $dianalund_cpt_name . ", " . $dianalund_cpt_adress . ", " . $dianalund_cpt_postal . " " . $dianalund_cpt_city . ". Telefon: " . $dianalund_cpt_phone . " " . $seo_custom_text_for_butik_og_erhverv;
				}
			} else if ( $post->post_type == 'erhvervsside' ) {
				$dianalund_cpt_payed = get_post_meta($post->ID, 'bizz_payed_payedbizz', true);
				if ($dianalund_cpt_payed = "Intet") {
					$dianalund_cpt_name = get_post_meta($post->ID, 'bizz_payed_name', true);
					$dianalund_cpt_adress = get_post_meta($post->ID, 'bizz_payed_adress', true);
					$dianalund_cpt_postal = get_post_meta($post->ID, 'bizz_payed_postal', true);
					$dianalund_cpt_city = get_post_meta($post->ID, 'bizz_payed_city', true);
					$dianalund_cpt_phone = get_post_meta($post->ID, 'bizz_payed_phone', true);
					$s = $dianalund_cpt_name . ", " . $dianalund_cpt_adress . ", " . $dianalund_cpt_postal . " " . $dianalund_cpt_city . ". Telefon: " . $dianalund_cpt_phone . " " . $seo_custom_text_for_butik_og_erhverv;
				}
			}
			
			return $s;
		}

		add_filter( 'wpseo_metadesc', 'dianalund_cpt_add_page_number', 100, 1 );
	}
	
	require_once(dirname( __FILE__ ) .  "/includes/functions-categorythumbnail.php");
	
	require_once(dirname( __FILE__ ) .  "/includes/functions-branch-shortcode.php");
	
	wp_enqueue_style( 'dianalund-cpt-frontend-styles', plugins_url('/css/dianalund-cpt-frontend-styles.css', __FILE__ ) );
	wp_enqueue_script( 'slickjs', get_template_directory_uri() . '/js/slick.min.js', array('jquery'), '1.5.9', true);
	wp_enqueue_script( 'shortcode-erhvervsliste-slider', plugins_url('/js/shortcode-erhvervsliste-slider.js', __FILE__ ), array('jquery','slickjs') );
  
  
  add_action( 'pre_get_posts', function( $query ) {
    if ( $query->is_search() ) {
      $query->set( 'orderby', 'relevance' );
    }
  } );

  add_filter( 'posts_search_orderby', function( $search_orderby ) {
      global $wpdb;
      return "{$wpdb->posts}.post_type LIKE 'erhvervsside' DESC, {$search_orderby}";
  });
  
	
}
add_action( 'init', 'dianalund_cpt_setup_post_types' );

// Register Gutenberg Block
function register_dianalund_cpt_blocks() {
    // Only load if Gutenberg is available
    if (!function_exists('register_block_type')) {
        return;
    }

    // Register block script
    wp_register_script(
        'dianalund-cpt-display-block',
        plugins_url('blocks/cpt-display/block.js', __FILE__),
        array(
            'wp-blocks',
            'wp-i18n',
            'wp-element',
            'wp-components',
            'wp-block-editor',
            'wp-api-fetch'
        ),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/cpt-display/block.js')
    );

    // Register block styles
    wp_register_style(
        'dianalund-cpt-display-style',
        plugins_url('blocks/cpt-display/style.css', __FILE__),
        array(),
        filemtime(plugin_dir_path(__FILE__) . 'blocks/cpt-display/style.css')
    );

    // Include render callback
    require_once plugin_dir_path(__FILE__) . 'blocks/cpt-display/render.php';

    // Register the block
    register_block_type('dianalund-cpt/cpt-display', array(
        'editor_script' => 'dianalund-cpt-display-block',
        'style' => 'dianalund-cpt-display-style',
        'render_callback' => 'render_dianalund_cpt_display_block',
        'attributes' => array(
            'postId' => array(
                'type' => 'string',
                'default' => ''
            ),
            'showLogo' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showName' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showFacebook' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showInstagram' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showWebsite' => array(
                'type' => 'boolean',
                'default' => true
            ),
            'showEmail' => array(
                'type' => 'boolean',
                'default' => true
            )
        )
    ));
}
add_action('init', 'register_dianalund_cpt_blocks');

// Eksponér meta fields til REST API for Gutenberg block
function expose_meta_fields_to_rest_api() {
    // Logo meta field
    register_rest_field(array('butiksside', 'erhvervsside', 'foreningsside'), 'allround-cpt_logo_id', array(
        'get_callback' => function($post) {
            return get_post_meta($post['id'], 'allround-cpt_logo_id', true);
        },
        'show_in_rest' => true,
        'schema' => array(
            'type' => 'string',
            'context' => array('view', 'edit')
        )
    ));
    
    // Social media meta fields for butikker
    $butik_fields = array('butik_payed_fb', 'butik_payed_insta', 'butik_payed_web', 'butik_payed_mail');
    foreach ($butik_fields as $field) {
        register_rest_field('butiksside', $field, array(
            'get_callback' => function($post) use ($field) {
                return get_post_meta($post['id'], $field, true);
            },
            'show_in_rest' => true,
            'schema' => array(
                'type' => 'string',
                'context' => array('view', 'edit')
            )
        ));
    }
    
    // Social media meta fields for erhverv
    $erhverv_fields = array('bizz_payed_fb', 'bizz_payed_insta', 'bizz_payed_web', 'bizz_payed_mail');
    foreach ($erhverv_fields as $field) {
        register_rest_field('erhvervsside', $field, array(
            'get_callback' => function($post) use ($field) {
                return get_post_meta($post['id'], $field, true);
            },
            'show_in_rest' => true,
            'schema' => array(
                'type' => 'string',
                'context' => array('view', 'edit')
            )
        ));
    }
    
    // Social media meta fields for foreninger
    $forening_fields = array('fritid_payed_fb', 'fritid_payed_web', 'fritid_payed_mail');
    foreach ($forening_fields as $field) {
        register_rest_field('foreningsside', $field, array(
            'get_callback' => function($post) use ($field) {
                return get_post_meta($post['id'], $field, true);
            },
            'show_in_rest' => true,
            'schema' => array(
                'type' => 'string',
                'context' => array('view', 'edit')
            )
        ));
    }
}
add_action('rest_api_init', 'expose_meta_fields_to_rest_api');

function custom_admin_css() {
	echo '<style>
		
		#adminmenu li.menu-top.toplevel_page_seperator-1 {
			min-height: 10px;
		}
		
		#adminmenu li.menu-top.toplevel_page_seperator-1 .wp-menu-image {
    		display: none;
		}
		
		</style>';
}
 
function dianalund_cpt_install()
{
    // trigger our function that registers the custom post type
    dianalund_cpt_setup_post_types();
	add_action('admin_head', 'custom_admin_css');
 
    // clear the permalinks after the post type has been registered
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dianalund_cpt_install' );
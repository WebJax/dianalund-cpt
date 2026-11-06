<?php
/**
 * Resister Custom Post Type for Shop (butik)
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */

$labels = array(
	'name'               => _x( 'Butikker', 'Butikker', 'dianalund_txtdomain' ),
	'singular_name'      => _x( 'Butik', 'Butik', 'dianalund_txtdomain' ),
	'menu_name'          => _x( 'Butikker', 'admin menu', 'dianalund_txtdomain' ),
	'name_admin_bar'     => _x( 'Butik', 'add new on admin bar', 'dianalund_txtdomain' ),
	'add_new'            => _x( 'Tilføj ny', 'butik', 'dianalund_txtdomain' ),
	'add_new_item'       => __( 'Tilføj ny butik', 'dianalund_txtdomain' ),
	'new_item'           => __( 'Ny butik', 'dianalund_txtdomain' ),
	'edit_item'          => __( 'Ret butik', 'dianalund_txtdomain' ),
	'view_item'          => __( 'Se butik', 'dianalund_txtdomain' ),
	'all_items'          => __( 'Alle butikker', 'dianalund_txtdomain' ),
	'search_items'       => __( 'Søg butikker', 'dianalund_txtdomain' ),
	'parent_item_colon'  => __( 'Forældre butikker:', 'dianalund_txtdomain' ),
	'not_found'          => __( 'Ingen butikker fundet.', 'dianalund_txtdomain' ),
	'not_found_in_trash' => __( 'Ingen butikker fundet i skraldespand.', 'dianalund_txtdomain' )
);

$args = array(
	'labels'             		=> $labels,
	'description'        		=> __( 'Opret butikker for betalende kunder.', 'dianalund_txtdomain' ),
	'public'             		=> true,
	'publicly_queryable' 		=> true,
	'show_ui'            		=> true,
	'show_in_menu'       		=> true,
	'query_var'          		=> true,
	'rewrite'            		=> array( 'slug' => 'butik' ),
	'capability_type'    		=> 'post',
	'has_archive'        		=> true,
	'hierarchical'       		=> false,
	'menu_position'      		=> 45,
	'menu_icon'					=> 'dashicons-store',
	'supports'           		=> array( 'title', 'editor', 'thumbnail' ),
	'register_meta_box_cb' 		=> 'add_butik_metaboxes',
	'taxonomies' 				=> array('category'),
	'show_in_rest'          	=> true,
);


 function add_butik_metaboxes(){
		 add_meta_box("butik_open_info", "Åbningstider", "butik_open_meta_options", "butiksside", "normal", "high");
		 add_meta_box("butik_kontakt_info", "Kontakt information", "butik_kontakt_meta_options", "butiksside", "side", "high" );
		 add_meta_box("butik_logo_pic", "Logo", "butik_logo_meta_options", "butiksside", "side", "low" );	 	
 }  

 function butik_open_meta_options(){
		global $post;
		jxw_render_hours_metabox( $post );
 }  

function butik_kontakt_meta_options(){
		global $post;
		jxw_render_contact_metabox( $post );
 }  

function butik_logo_meta_options() {
	global $post;
	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="logobutiksmeta_noncename" id="logobutiksmeta_noncename" value="' . 
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	$image_src = '';
	$image_id = get_post_meta( $post->ID, 'allround-cpt_logo_id', true );
	$image_src = wp_get_attachment_url( $image_id );
	?>
	<img id="allround-cpt-logo" src="<?php echo $image_src ?>" style="max-width:100%;" />
	<input type="hidden" name="allround-cpt_logo_id" id="allround-cpt_logo_id" value="<?php echo $image_id; ?>" />
	<p>
		<a title="<?php esc_attr_e( 'Hent logo' ) ?>" href="#" id="hent-logo" class="<?php echo ( $image_id ? 'hidden' : '' ); ?>"><?php _e( 'Hent logo' ) ?></a>
		<a title="<?php esc_attr_e( 'Fjern logo' ) ?>" href="#" id="fjern-logo" class="<?php echo ( ! $image_id ? 'hidden' : '' ); ?>"><?php _e( 'Fjern logo' ) ?></a>
	</p>
	<?php
}

 // Save the Metabox Data

function save_butiks_meta($post_id, $post) {
	
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( isset($_POST['butiksmeta_noncename']) && !wp_verify_nonce( $_POST['butiksmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['kontaktbutiksmeta_noncename']) && !wp_verify_nonce( $_POST['kontaktbutiksmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['logobutiksmeta_noncename']) && !wp_verify_nonce( $_POST['logobutiksmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}

	// Is the user allowed to edit the post or page?
	if ( !current_user_can( 'edit_post', $post->ID ))
		return $post->ID;

	// OK, we're authenticated: we need to find and save the data
	// We'll put it into an array to make it easier to loop though.
	
	if (isset($_POST['allround-cpt_logo_id'])) $events_meta['allround-cpt_logo_id'] = $_POST['allround-cpt_logo_id'];		
	
	// Add values of $events_meta as custom fields
	if (isset($events_meta)) {
		foreach ($events_meta as $key => $value) { // Cycle through the $events_meta array!
			if( $post->post_type == 'revision' ) return; // Don't store custom data twice
			$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
			if(get_post_meta($post->ID, $key, FALSE)) { // If the custom field already has a value
				update_post_meta($post->ID, $key, $value);
			} else { // If the custom field doesn't have a value
				add_post_meta($post->ID, $key, $value);
			}
			if(!$value) delete_post_meta($post->ID, $key); // Delete if blank
		}
	}

}

/* Setup listorder */
add_action("manage_butiksside_posts_custom_column",  "butik_custom_columns");
add_filter("manage_butiksside_posts_columns", "butik_edit_columns");
add_filter("manage_edit-butiksside_sortable_columns", "my_sortable_butiksside_column");

function butik_edit_columns($columns){
  $columns = array(
    "cb" => "<input type='checkbox' />",
    "title" => "Butiks Titel",
    "description" => "Beskrivelse",
		"wordcount" => "Antal ord",
    "betalt" => "Abonnement",
    "butikskategori" => "Kategori",
  );
 
  return $columns;
}

function butik_custom_columns($column){
  global $post;
 
  switch ($column) {
    case "description":
      the_excerpt();
      break;
		case "wordcount":
			echo butik_prefix_wcount();
			break;
    case "betalt":
			$abonnement = get_post_meta($post->ID, 'butik_payed_payedbutik', true);
			if ( '' === (string) $abonnement ) {
				$abonnement = jxw_is_paid( $post->ID ) ? '1' : '';
			}
			echo ("" != $abonnement) ? "<b>".$abonnement."</b>" : "Intet solgt";
      break;
    case "butikskategori":
      echo get_the_term_list($post->ID, 'category', '', ', ','');
      break;
  }
}

function butik_prefix_wcount() {
    ob_start();
    the_content();
    $content = ob_get_clean();
		$wc_size = (2 < sizeof(explode(" ", (string)$content))) ? sizeof(explode(" ", (string)$content)) : "Ingen";
    return $wc_size;
}

function my_sortable_butiksside_column( $columns ) {
    $columns['betalt'] = 'betalt';
    $columns['butikskategori'] = 'butikskategori';
 
    //To make a column 'un-sortable' remove it from the array
    //unset($columns['date']);
 
    return $columns;
}

// Add to our admin_init function
add_action('quick_edit_custom_box',  'butikker_quick_edit', 10, 2);
 
function butikker_quick_edit($column_name, $post_type) {

    if ($column_name != 'betalt') return;
	
		$sc = [
			0 => 'Intet',
			1 => 'Sølv',
			2 => 'Guld',
			3 => 'Platin',
			4 => 'Anden aftale',
		];
    ?>
    <fieldset class="inline-edit-col-left">
    <div class="inline-edit-col">
        <span class="title">Abonnement: </span>
        <input type="hidden" name="manage_butiksside_noncename" id="manage_butiksside_noncename" value="" />
        <select name='betalt_set' id='betalt_set'>
            <?php
						foreach ($sc as $value) {
							echo '<option value="'.$value.'">'.$value.'</option>';
						}
            ?>
        </select>
    </div>
    </fieldset>
    <?php
}

add_action('save_post', 'butikker_quick_edit_data');
 
function butikker_quick_edit_data($post_id) {
    // are we at the correct page
    $screen = get_current_screen();
    if (!is_admin() || !isset($screen->post_type) || $screen->post_type != "butiksside") {
      return;
    }

    // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
    // to do anything
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
        return $post_id;    
    // Check permissions
    if ( 'page' == $_POST['post_type'] ) {
        if ( !current_user_can( 'edit_page', $post_id ) )
            return $post_id;
    } else {
        if ( !current_user_can( 'edit_post', $post_id ) )
        return $post_id;
    }
    // OK, we're authenticated: we need to find and save the data
    $post = get_post($post_id);
    if (isset($_POST['betalt_set']) && ($post->post_type != 'revision')) {
        $widget_set_id = esc_attr($_POST['betalt_set']);
        if ($widget_set_id) {
            update_post_meta( $post_id, 'butik_payed_payedbutik', $widget_set_id);
            update_post_meta( $post_id, 'jxw_paid', jxw_normalize_paid_value( $widget_set_id ) );
        } else {
            delete_post_meta( $post_id, 'butik_payed_payedbutik');
            update_post_meta( $post_id, 'jxw_paid', '' );
        }     
    }       
    return $widget_set_id ?? '';  
}


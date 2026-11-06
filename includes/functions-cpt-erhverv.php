<?php
/**
 * Resister Custom Post Type for Business (erhverv)
 *
 * Payed pages
 *
 */

/**
 * Register a business post type.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */
	$labels = array(
		'name'               => _x( 'Erhverv', 'Erhverv', 'dianalund_txtdomain' ),
		'singular_name'      => _x( 'Erhverv', 'Erhverv', 'dianalund_txtdomain' ),
		'menu_name'          => _x( 'Erhverv', 'admin menu', 'dianalund_txtdomain' ),
		'name_admin_bar'     => _x( 'Erhverv', 'add new on admin bar', 'dianalund_txtdomain' ),
		'add_new'            => _x( 'Tilføj nyt', 'book', 'dianalund_txtdomain' ),
		'add_new_item'       => __( 'Tilføj nyt erhverv', 'dianalund_txtdomain' ),
		'new_item'           => __( 'Nyt erhverv', 'dianalund_txtdomain' ),
		'edit_item'          => __( 'Ret erhverv', 'dianalund_txtdomain' ),
		'view_item'          => __( 'Se erhverv', 'dianalund_txtdomain' ),
		'all_items'          => __( 'Alle erhverv', 'dianalund_txtdomain' ),
		'search_items'       => __( 'Søg erhverv', 'dianalund_txtdomain' ),
		'parent_item_colon'  => __( 'Forældre erhverv:', 'dianalund_txtdomain' ),
		'not_found'          => __( 'Ingen erhverv fundet.', 'dianalund_txtdomain' ),
		'not_found_in_trash' => __( 'Ingen erhverv fundet i skraldespand.', 'dianalund_txtdomain' )
	);

	$args = array(
		'labels'                => $labels,
    'description'           => __( 'Opret erhvervs for betalende kunder.', 'dianalund_txtdomain' ),
		'public'                => true,
		'publicly_queryable'    => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'erhverv' ),
		'capability_type'       => 'post',
		'has_archive'           => true,
		'hierarchical'          => false,
		'menu_position'         => 46,
		'menu_icon'					    => 'dashicons-building',	
		'supports'              => array( 'title', 'editor', 'thumbnail' ),
		'register_meta_box_cb'  => 'add_erhverv_metaboxes',
		'taxonomies' 						=> array('category'),
    'show_in_rest'          => true    
	);


 function add_erhverv_metaboxes(){
		 add_meta_box("erhverv_open_info", "Åbningstider", "open_meta_options", "erhvervsside", "normal", "high");
		 add_meta_box("erhverv_kontakt_info", "Kontakt information", "kontakt_meta_options", "erhvervsside", "side", "high" );
		 add_meta_box("erhverv_logo_pic", "Logo", "logo_meta_options", "erhvervsside", "side", "low" );	 	
 }  

 function open_meta_options(){
		global $post;
	 	$bizz_aabning_check = get_post_meta($post->ID, 'bizz_aabning_check', true);
		// Noncename needed to verify where the data originated
		echo '<input type="hidden" name="erhvervsmeta_noncename" id="erhvervsmeta_noncename" value="' . 
		wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

		// Get the location data if its already been entered ?>
		<label for="bizz_aabning_check">Skal åbningstider vises:</label> <input type="checkbox" <?php if(isset($bizz_aabning_check)){ if($bizz_aabning_check=="skalvises") { echo 'checked="checked"'; } } ?> value="skalvises" name="bizz_aabning_check" id="bizz_aabning_check" style="margin-top: 3px;margin-left: 10px;"/>
		<?php
	 	if ($bizz_aabning_check == 'skalvises') {
			$bizz_payed_open = get_post_meta($post->ID, 'erhverv_aabentider', true);
			if ($bizz_payed_open == "") {	
				$bizz_payed_open = "<table><tr><td>Mandag</td><td>10:00 - 18:00</td></tr><tr><td>Tirsdag</td><td>10:00 - 18:00</td></tr><tr><td>Onsdag</td><td>10:00 - 18:00</td></tr><tr><td>Torsdag</td><td>10:00 - 18:00</td></tr><tr><td>Fredag</td><td>10:00 - 18:00</td></tr><tr><td>Lørdag</td><td>10:00 - 13:00</td></tr><tr><td>Søndag</td><td>Lukket</td></tr></table>";
			}
			wp_editor( $bizz_payed_open, 'erhverv_aabentider', array(
				'wpautop'       => true,
				'media_buttons' => false,
				'textarea_name' => 'erhverv_aabentider',
				'textarea_rows' => 10,
				'teeny'					=> true
			) );		
		} 
 }  

function kontakt_meta_options(){
		global $post;
		jxw_render_contact_metabox( $post );
 }  

function logo_meta_options() {
	global $post;
	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="logoerhvervsmeta_noncename" id="logoerhvervsmeta_noncename" value="' . 
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

function save_erhvervs_meta($post_id, $post) {
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( isset($_POST['erhvervsmeta_noncename']) && !wp_verify_nonce( $_POST['erhvervsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['kontakterhvervsmeta_noncename']) && !wp_verify_nonce( $_POST['kontakterhvervsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['logoerhvervsmeta_noncename']) && !wp_verify_nonce( $_POST['logoerhvervsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}

	// Is the user allowed to edit the post or page?
	if ( !current_user_can( 'edit_post', $post->ID ))
		return $post->ID;
	
	// OK, we're authenticated: we need to find and save the data
	// We'll put it into an array to make it easier to loop though.
	
	if (isset($_POST['erhverv_aabentider'])) $events_meta['erhverv_aabentider'] = $_POST['erhverv_aabentider'];
	if (isset($_POST['bizz_aabning_check'])) {$events_meta['bizz_aabning_check'] = $_POST['bizz_aabning_check'];} else {$events_meta['bizz_aabning_check'] = "";};
	if (isset($_POST['allround-cpt_logo_id'])) $events_meta['allround-cpt_logo_id'] =$_POST['allround-cpt_logo_id'];
	
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
add_action("manage_erhvervsside_posts_custom_column",  "erhverv_custom_columns");
add_filter("manage_erhvervsside_posts_columns", "erhverv_edit_columns");
 
function erhverv_edit_columns($columns){
  $columns = array(
    "cb" => "<input type='checkbox' />",
    "title" => "Erhvervs Titel",
    "description" => "Beskrivelse",
		"word_bizz_count" => "Antal ord",
    "betalt" => "Abonnement",
    "erhvervskategori" => "Kategori",
  );
 
  return $columns;
}

function erhverv_custom_columns($column){
  global $post;
 
  switch ($column) {
    case "description":
      the_excerpt();
      break;
		case "word_bizz_count":
			echo erhverv_prefix_wcount();
			break;
    case "betalt":
			$abonnement = get_post_meta($post->ID, 'bizz_payed_payedbizz', true);
			if ( '' === (string) $abonnement ) {
				$abonnement = jxw_is_paid( $post->ID ) ? '1' : '';
			}
			echo $abonnement;
      break;
    case "erhvervskategori":
      echo get_the_term_list($post->ID, 'category', '', ', ','');
      break;
  }
}

function erhverv_prefix_wcount() {
    ob_start();
    the_content();
    $content = ob_get_clean();
		$wc_size = (2 < sizeof(explode(" ", (string)$content))) ? sizeof(explode(" ", (string)$content)) : "Ingen";
    return $wc_size;
}

function my_sortable_erhvervsside_column( $columns ) {
    $columns['betalt'] = 'betalt';
    $columns['erhvervskategori'] = 'erhvervskategori';
 
    //To make a column 'un-sortable' remove it from the array
    //unset($columns['date']);
 
    return $columns;
}

// Add to our admin_init function
add_action('quick_edit_custom_box',  'erhverv_quick_edit', 10, 2);

function erhverv_quick_edit($column_name, $post_type) {

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
        <input type="hidden" name="manage_erhvervside_noncename" id="manage_erhvervsside_noncename" value="" />
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

add_action('save_post', 'erhverv_quick_edit_data');
 
function erhverv_quick_edit_data($post_id) {
    // are we at the correct page
	$screen = get_current_screen();
    if (!is_admin() || !isset($screen->post_type) || $screen->post_type != "erhvervsside") {
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
            update_post_meta( $post_id, 'bizz_payed_payedbizz', $widget_set_id);
            update_post_meta( $post_id, 'jxw_paid', jxw_normalize_paid_value( $widget_set_id ) );
        } else {
            delete_post_meta( $post_id, 'bizz_payed_payedbizz');
            update_post_meta( $post_id, 'jxw_paid', '' );
        }     
    }

    return $widget_set_id ?? '';  
}
<?php
/**
 * Resister Custom Post Type for Associations (foreninger)
 *
 * All associations are registered as payed
 *
 */

/**
 * Register a business post type.
 *
 * @link http://codex.wordpress.org/Function_Reference/register_post_type
 */
	$labels = array(
		'name'               => _x( 'Foreninger', 'Foreninger', 'dianalund_txtdomain' ),
		'singular_name'      => _x( 'Forening', 'Forening', 'dianalund_txtdomain' ),
		'menu_name'          => _x( 'Foreninger', 'admin menu', 'dianalund_txtdomain' ),
		'name_admin_bar'     => _x( 'Forening', 'add new on admin bar', 'dianalund_txtdomain' ),
		'add_new'            => _x( 'Tilføj ny', 'fritid', 'dianalund_txtdomain' ),
		'add_new_item'       => __( 'Tilføj ny forening', 'dianalund_txtdomain' ),
		'new_item'           => __( 'Ny forening', 'dianalund_txtdomain' ),
		'edit_item'          => __( 'Ret forening', 'dianalund_txtdomain' ),
		'view_item'          => __( 'Se forening', 'dianalund_txtdomain' ),
		'all_items'          => __( 'Alle foreninger', 'dianalund_txtdomain' ),
		'search_items'       => __( 'Søg foreninger', 'dianalund_txtdomain' ),
		'parent_item_colon'  => __( 'Forældre foreninger:', 'dianalund_txtdomain' ),
		'not_found'          => __( 'Ingen foreninger fundet.', 'dianalund_txtdomain' ),
		'not_found_in_trash' => __( 'Ingen foreninger fundet i skraldespand.', 'dianalund_txtdomain' )
	);

	$args = array(
		'labels'             		=> $labels,
    	'description'        		=> __( 'Opret foreninger for alle foreninger i byen.', 'dianalund_txtdomain' ),
		'public'             		=> true,
		'publicly_queryable' 		=> true,
		'show_ui'            		=> true,
		'show_in_menu'       		=> true,
		'query_var'          		=> true,
		'rewrite'            		=> array( 'slug' => 'forening' ),
		'capability_type'    		=> 'post',
		'has_archive'        		=> true,
		'hierarchical'       		=> false,
		'menu_position'      		=> 47,
		'menu_icon'					=> 'dashicons-tickets-alt',
		'supports'           		=> array( 'title', 'editor', 'thumbnail' ),
		'register_meta_box_cb' 		=> 'add_fritid_metaboxes',
		'taxonomies' 				=> array('category'),
    	'show_in_rest'          	=> true
	);
	
  
/* Add mailoption-page */

/* Add mailoption-page */

function send_forenings_mail() {
	if (isset($_POST['forening_mail_send_noncename']) && wp_verify_nonce( $_POST['forening_mail_send_noncename'], plugin_basename(__FILE__))) {
		add_thickbox();
		$subject = get_option('forening_mail_overskrift');
		$body = get_option('foreningsmailindhold');
		$headers = array('Content-Type: text/html; charset=UTF-8','From: Dianalund.dk <webmaster@dianalund.dk>', 'Reply-To: dianalund.dk <webmaster@dianalund.dk>');
		
		
		$antalklikket = count($_POST['mailadress']);
		echo '<div id="my-content-id" style="display: none; text-align: center; margin-top: 1%;">';
		if (isset($_POST['mailadress'])) {
			$t = 0;
			$mailadresser = $_POST['mailadress'];
			$forening_link = $_POST['forening_link'];
			foreach ($mailadresser as $makey=>$adresse) {
				$patterns = array();
				$patterns[0] = '<\[forening\]>';
				$patterns[1] = '<\[forening-link\]>';
				$replacements = array();
				$replacements[0] = $adresse;
				$replacements[1] = '<a href="'.$forening_link[$makey].'">'.$forening_link[$makey].'</a>';
				$newbody = preg_replace($patterns, $replacements, $body);
				echo '<div id="mail-content" style="margin-bottom: 40px; border: 1px solid; padding: 15px;">';
				echo '	<p>Til: '.htmlspecialchars($adresse).'</p>';
				echo '  <p>Fra: '.htmlspecialchars($headers[1]).'</p>';
				echo '	<p>Svar til: '.htmlspecialchars($headers[2]).'</p>';
				echo '<hr>';
				echo '	<p>Emne: '.$subject.'</p>';
				echo '<hr>';
				echo '	<div>'.$newbody.'</div>';
				echo '</div>';
				wp_mail( $adresse, $subject, (string)$newbody, $headers );
				$t++;
			}
			echo '<hr>';
			echo '<h3>Der er sendt: '.$t.' mails</h3>';
		}
		echo '</div>';
		echo '<script type="text/javascript">
						jQuery(window).load(function() {   
			        tb_show("Send mails", "#TB_inline?height=550&inlineId=my-content-id");
				    });
					</script>';
	
	} elseif ( isset($_POST['forening_mail_noncename']) && wp_verify_nonce( $_POST['forening_mail_noncename'], plugin_basename(__FILE__) )) {
		// Gem alle oplysninger
		update_option('foreningsmailindhold', $_POST['forenings_mail_indhold']);
		update_option('forening_mail_overskrift', $_POST['forening_mail_overskrift']);
		update_option('forening_afsender_adresse', $_POST['afsenderadresse']);
	}
	
	$forening_editor_id = 'foreningsmail_indhold';
	$content = stripslashes(get_option('foreningsmailindhold'));
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
	echo '<form action="http://dianalund.dk/wp-admin/edit.php?post_type=foreningsside&page=send_forenings_mail" method="post">';
	echo '<input type="hidden" name="forening_mail_noncename" id="forening_mail_noncename" value="' . 
		wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	echo '<H2>Udsend mail til alle foreninger</H2>';
	echo '<div id="post-body" class="metabox-holder columns-2" style="padding-right: 30px;
    float: left;
    width: 75%;">';
	echo '<table style="width: 100%;">';
	echo '<tr><td><input type="text" class="forenings_mail_overskrift_input" name="forening_mail_overskrift" id="forening_mail_overskrift" value="'.get_option('forening_mail_overskrift').'" placeholder="Indtast mailoverskrift" style="
    width: 100%;
    font-size: 1.2rem;
    padding: .5rem .7rem;
    margin-bottom: 1rem;
"/>';
	$settings = array( 'media_buttons' 	=> true,
									 	 'wpautop' 				=> false,
										 'textarea_name'	=> 'forenings_mail_indhold'
									 );
	wp_editor( $content, $forening_editor_id, $settings );
	echo '<tr><td><input type="text" name="afsenderadresse" id="afsenderadresse" value="'.get_option('forening_afsender_adresse').'" placeholder="Indtast afsender mailadresse" style="
    width: 100%;
    font-size: 1.2rem;
    padding: .5rem .7rem;
    margin-bottom: 1rem;
"/></td></tr>';
	echo '<tr><td>';submit_button();echo '</td><tr>';
	echo '</table>';
	echo '</form>';
	echo '</div>';
	echo '<div id="postbox-container-1" class="postbox-container" style="padding-top: 15px;">';
	echo '<div id="side-sortables" class="meta-box-sortables ui-sortable" style=""><div id="fritid_kontakt_info" class="postbox">
		<h2><span style="    padding-left: 15px;
    margin-top: -10px;
    padding-bottom: 10px;
    display: block;
    position: relative;
    border-bottom: 1px solid #ddd;">Send mails</span></h2>
<div class="inside">
	<form action="http://dianalund.dk/wp-admin/edit.php?post_type=foreningsside&page=send_forenings_mail&sendmails=true" method="post">
	<input type="hidden" name="forening_mail_send_noncename" id="forening_mail_send_noncename" value="' . 
		wp_create_nonce( plugin_basename(__FILE__) ) . '" />
	<label for="select_all"><input type="checkbox" id="select_all"> Vælg alle</label>
	<div class="forening_alle_mailadresser">
		';
		$mail_query = new WP_Query( array( 'post_type' => 'foreningsside', 'posts_per_page'=>-1 ) );
		if ( $mail_query->have_posts() ) {
		echo '<div style="overflow: scroll; width: 220px; height: 300px; border: 1px solid grey; padding-left: 10px; margin-top: 10px;">';
		while ( $mail_query->have_posts() ) {
			$mail_query->the_post();
			$fritid_payed_mail = "";
			$fritid_payed_mail = get_post_meta($mail_query->post->ID, 'fritid_payed_mail', true);
			if ($fritid_payed_mail != "") {
				$fritid_payed_name = get_post_meta($mail_query->post->ID, 'fritid_payed_name', true);
				echo '<p><input type="checkbox" name="mailadress[]" value="'.$fritid_payed_name.' <'.$fritid_payed_mail.'>"/> '.$fritid_payed_name.'</p>';
				echo '<input type="hidden" name="forening_link[]" value="'.get_post_permalink($mail_query->post->ID).'"/>';
			}
		}
		echo '</div>';
		/* Restore original Post Data */
		wp_reset_postdata();
		} else {
			// no posts found
		}
		echo '
	</div><p class="submit" style="text-align: right;">';
	echo get_submit_button( 'Send mails', 'primary', 'submit', false);
	echo '</p></form>
</div>
</div>';
	echo '</div>';
	echo '</div>';
}

function send_forenings_mail_menu() {
	add_submenu_page( 'edit.php?post_type=foreningsside', 'Send mail til foreningerne', 'Send mail', 'manage_options', 'send_forenings_mail', 'send_forenings_mail' );
}

add_action( 'admin_menu', 'send_forenings_mail_menu' );




 function add_fritid_metaboxes(){
		 add_meta_box("fritid_open_info", "Tidspunkter", "fritid_open_meta_options", "foreningsside", "normal", "high");
		 add_meta_box("fritid_kontakt_info", "Kontakt information", "fritid_kontakt_meta_options", "foreningsside", "side", "high" );
		 add_meta_box("fritid_logo_pic", "Logo", "fritid_logo_meta_options", "foreningsside", "side", "low" );	 	
 }  

 function fritid_open_meta_options(){
		global $post;
	  $fritid_aabning_check = get_post_meta($post->ID, 'fritid_aabning_check', true);
		// Noncename needed to verify where the data originated
		echo '<input type="hidden" name="foreningsmeta_noncename" id="foreningsmeta_noncename" value="' . 
		wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

		// Get the location data if its already been entered ?>
		<label for="fritid_aabning_check">Skal Træningstider/åbningstider vises:</label> <input type="checkbox" <?php if(isset($fritid_aabning_check)){ if($fritid_aabning_check=="skalvises") { echo 'checked="checked"'; } } ?> value="skalvises" name="fritid_aabning_check" id="fritid_aabning_check" style="margin-top: 3px;margin-left: 10px;"/>
		<?php
		if ($fritid_aabning_check == 'skalvises') {
			$fritid_payed_open = get_post_meta($post->ID, 'fritid_aabentider', true);
			if ($fritid_payed_open == '') {
				$fritid_payed_open = "<table><tr><td>Mandag</td><td>10:00 - 18:00</td></tr><tr><td>Tirsdag</td><td>10:00 - 18:00</td></tr><tr><td>Onsdag</td><td>10:00 - 18:00</td></tr><tr><td>Torsdag</td><td>10:00 - 18:00</td></tr><tr><td>Fredag</td><td>10:00 - 18:00</td></tr><tr><td>Lørdag</td><td>10:00 - 13:00</td></tr><tr><td>Søndag</td><td>Lukket</td></tr></table>";
			} 
			wp_editor( $fritid_payed_open, 'fritid_aabentider', array(
				'wpautop'       => true,
				'media_buttons' => false,
				'textarea_name' => 'fritid_aabentider',
				'textarea_rows' => 10,
				'teeny'					=> true
			) );
		}
 }  	

function fritid_kontakt_meta_options(){
	
		global $post;
		// Noncename needed to verify where the data originated
		echo '<input type="hidden" name="kontaktforeningsmeta_noncename" id="kontaktforeningsmeta_noncename" value="' . 
		wp_create_nonce( plugin_basename(__FILE__) ) . '" />';

		// Get the location data if its already been entered
		$fritid_payed_name = get_post_meta($post->ID, 'fritid_payed_name', true);
		$fritid_payed_adress = get_post_meta($post->ID, 'fritid_payed_adress', true);
		$fritid_payed_postal = get_post_meta($post->ID, 'fritid_payed_postal', true);
		$fritid_payed_city = get_post_meta($post->ID, 'fritid_payed_city', true);
		$fritid_payed_phone = get_post_meta($post->ID, 'fritid_payed_phone', true);
		$fritid_payed_mail = get_post_meta($post->ID, 'fritid_payed_mail', true);
		$fritid_payed_show_mail = get_post_meta($post->ID, 'fritid_payed_show_mail', true);	
		$fritid_payed_web = get_post_meta($post->ID, 'fritid_payed_web', true);
		$fritid_payed_fb = get_post_meta($post->ID, 'fritid_payed_fb', true);
		$fritid_payed_sidstkontaktet = get_post_meta($post->ID, 'fritid_payed_sidstkontaktet', true);
		$fritid_payed_vendttilbage = get_post_meta($post->ID, 'fritid_payed_vendttilbage', true);
		?>
		<table>
			<tr><td><label for="fritid_payed_name">Foreningsnavn:</label><br/><input type="text" value="<?php echo $fritid_payed_name;?>" name="fritid_payed_name" id="fritid_payed_name"/></td></tr>
			<tr><td><label for="fritid_payed_adress">Adresse:</label><br/><input type="text" value="<?php echo $fritid_payed_adress;?>" name="fritid_payed_adress" id="fritid_payed_adress"/></td></tr>
			<tr><td><label for="fritid_payed_postal">Postnr:</label><br/><input type="text" value="<?php if ($fritid_payed_postal=='') { echo "4293"; } else { echo $fritid_payed_postal; } ?>" name="fritid_payed_postal" id="fritid_payed_postal"/></td></tr>
			<tr><td><label for="fritid_payed_city">By:</label><br/><input type="text" value="<?php if ($fritid_payed_city=='') { echo "Dianalund"; } else { echo $fritid_payed_city; }?>" name="fritid_payed_city" id="fritid_payed_city"/></td></tr>
			<tr><td><label for="fritid_payed_phone">Telefon:</label><br/><input type="text" value="<?php echo $fritid_payed_phone;?>" name="fritid_payed_phone" id="fritid_payed_phone"/></td></tr>
			<tr><td><label for="fritid_payed_mail">Email:</label><br/><input type="text" value="<?php echo $fritid_payed_mail;?>" name="fritid_payed_mail" id="fritid_payed_mail"/> Vis: <input type="checkbox" name="fritid_payed_show_mail" id="fritid_payed_show_mail" value="yes" <?php echo ($fritid_payed_show_mail == 'yes') ? 'checked' : '';?>></td></tr>
			<tr><td><label for="fritid_payed_web">Hjemmeside:</label><br/><input type="text" value="<?php echo $fritid_payed_web;?>" name="fritid_payed_web" id="fritid_payed_web"/></td></tr>
			<tr><td><label for="fritid_payed_fb">Facebook:</label><br/><input type="text" value="<?php echo $fritid_payed_fb;?>" name="fritid_payed_fb" id="fritid_payed_fb"/></td></tr>
			<tr><td><label for="fritid_payed_sidstkontaktet">Sidst kontaktet:</label><br/><input type="text" value="<?php echo $fritid_payed_sidstkontaktet;?>" name="fritid_payed_sidstkontaktet" id="fritid_payed_sidstkontaktet" class="kontaktdato"/></td></tr>
			<tr><td><label for="fritid_payed_vendttilbage">Vendt tilbage:</label><br/><input type="text" value="<?php echo $fritid_payed_vendttilbage;?>" name="fritid_payed_vendttilbage" id="fritid_payed_vendttilbage" class="kontaktdato"/></td></tr>
		</table>
<?php
 }  

function fritid_logo_meta_options() {
	global $post;
	// Noncename needed to verify where the data originated
	echo '<input type="hidden" name="logoforeningsmeta_noncename" id="logoforeningsmeta_noncename" value="' . 
	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	$image_src = '';
	$image_id = get_post_meta( $post->ID, 'allround-cpt_logo_id', true );
	$image_src = wp_get_attachment_url( $image_id );
	?>
	<img id="allround-cpt-logo" src="<?php echo $image_src ?>" style="max-width:100%;" />
	<input type="hidden" name="allround-cpt_logo_id" id="allround-cpt_logo_id" value="<?php echo $image_id; ?>" />
	<p>
		<a title="<?php esc_attr_e( 'Hent logo' ) ?>" href="#" id="hent-logo" class="<?php echo ($image_id ? 'hidden' : '' ); ?>"><?php _e( 'Hent logo' ) ?></a>
		<a title="<?php esc_attr_e( 'Fjern logo' ) ?>" href="#" id="fjern-logo" class="<?php echo ( ! $image_id ? 'hidden' : '' ); ?>"><?php _e( 'Fjern logo' ) ?></a>
	</p>
	<?php
}

 // Save the Metabox Data

function save_forenings_meta($post_id, $post) {
	
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( isset($_POST['foreningsmeta_noncename']) && !wp_verify_nonce( $_POST['foreningsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['foreningsmeta_noncename']) && !wp_verify_nonce( $_POST['kontaktforeningsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}
	if ( isset($_POST['foreningsmeta_noncename']) && !wp_verify_nonce( $_POST['logoforeningsmeta_noncename'], plugin_basename(__FILE__) )) {
		return $post->ID;
	}

	// Is the user allowed to edit the post or page?
	if ( !current_user_can( 'edit_post', $post->ID ))
		return $post->ID;

	// OK, we're authenticated: we need to find and save the data
	// We'll put it into an array to make it easier to loop though.
	
	if (isset($_POST['fritid_aabentider'])) $events_meta['fritid_aabentider'] = $_POST['fritid_aabentider'];
	if (isset($_POST['fritid_aabning_check'])) {$events_meta['fritid_aabning_check'] = $_POST['fritid_aabning_check'];} else {$events_meta['fritid_aabning_check'] = "";};
	if (isset($_POST['fritid_payed_name'])) $events_meta['fritid_payed_name'] = $_POST['fritid_payed_name'];
	if (isset($_POST['fritid_payed_adress'])) $events_meta['fritid_payed_adress'] = $_POST['fritid_payed_adress'];
	if (isset($_POST['fritid_payed_postal'])) $events_meta['fritid_payed_postal'] = $_POST['fritid_payed_postal'];
	if (isset($_POST['fritid_payed_city'])) $events_meta['fritid_payed_city'] = $_POST['fritid_payed_city'];
	if (isset($_POST['fritid_payed_phone'])) $events_meta['fritid_payed_phone'] = $_POST['fritid_payed_phone'];
	if (isset($_POST['fritid_payed_mail'])) $events_meta['fritid_payed_mail'] = $_POST['fritid_payed_mail'];
	if (isset($_POST['fritid_payed_show_mail'])) $events_meta['fritid_payed_show_mail'] = $_POST['fritid_payed_show_mail'];
	if (isset($_POST['fritid_payed_web'])) $events_meta['fritid_payed_web'] = $_POST['fritid_payed_web'];
	if (isset($_POST['fritid_payed_fb'])) $events_meta['fritid_payed_fb'] = $_POST['fritid_payed_fb'];
	if (isset($_POST['fritid_payed_sidstkontaktet'])) $events_meta['fritid_payed_sidstkontaktet'] = $_POST['fritid_payed_sidstkontaktet'];
	if (isset($_POST['fritid_payed_vendttilbage'])) $events_meta['fritid_payed_vendttilbage'] = $_POST['fritid_payed_vendttilbage'];
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
add_action("manage_foreningsside_posts_custom_column",  "foreninger_custom_columns");
add_filter("manage_foreningsside_posts_columns", "foreninger_edit_columns");
 
function foreninger_edit_columns($columns){
  $columns = array(
    "cb" => "<input type='checkbox' />",
    "title" => "Forenings Titel",
    "description" => "Beskrivelse",
    "sidstkontakt" => "Kontaktet",
    "foreningskategori" => "Kategori",
  );
 
  return $columns;
}
function foreninger_custom_columns($column){
  global $post;
 
  switch ($column) {
    case "description":
      the_excerpt();
      break;
    case "sidstkontakt":
			$fritid_payed_mail = get_post_meta($post->ID, 'fritid_payed_mail', true);
			$sk = get_post_meta($post->ID, 'fritid_payed_sidstkontaktet', true);
			$sk = ('' == $sk) ? 'Aldrig kontaktet' : 'Sidst kontaktet: '.$sk;
			$vt = get_post_meta($post->ID, 'fritid_payed_vendttilbage', true);	
			$vt = ('' == $vt) ? 'Aldrig vendt tilbage' : 'Vendt tilbage: '.$vt;
      echo $sk.' <br/>Mail: <a href="mailto:'.$fritid_payed_mail.'">'.$fritid_payed_mail.'</a><br/>'.$vt;
      break;
    case "foreningskategori":
      echo get_the_term_list($post->ID, 'category', '', ', ','');
      break;
  }
}
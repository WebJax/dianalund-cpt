<?php
// Add thumbnail for any category

function addTitleFieldToCat(){
    $image_id = get_term_meta($_POST['tag_ID'], '_thumbnail', true);
  	$image_src = wp_get_attachment_url( $image_id );
    ?> 
    <tr class="form-field">
        <th scope="row" valign="top"><label for="cat_thumbnail"><?php _e('Category Thumbnail'); ?></label></th>
        <td>
          <img id="allround-cpt-logo" src="<?php echo $image_src ?>" style="max-width:100%;" />
          <input type="hidden" name="allround-cpt_logo_id" id="allround-cpt_logo_id" value="<?php echo $image_id; ?>" />
          <p>
            <a title="<?php esc_attr_e( 'Hent logo' ) ?>" href="#" id="hent-logo" class="<?php echo ( $image_id ? 'hidden' : '' ); ?>"><button class="button button-primary button-large"><?php _e( 'Hent logo' ) ?></button></a>
            <a title="<?php esc_attr_e( 'Fjern logo' ) ?>" href="#" id="fjern-logo" class="<?php echo ( ! $image_id ? 'hidden' : '' ); ?>"><button class="button button-primary button-large"><?php _e( 'Fjern logo' ) ?></button></a>
          </p>
        </td>
    </tr>
    <?php

}
add_action ( 'edit_category_form_fields', 'addTitleFieldToCat');

function saveCategoryFields() {
    if ( isset( $_POST['cat_thumbnail'] ) ) {
        update_term_meta($_POST['tag_ID'], '_pagetitle', $_POST['cat_thumbnail']);
    }
}
add_action ( 'edited_category', 'saveCategoryFields');

function enqueue_image_loader_for_categories() {
	wp_register_script( 
		'custom-category-thumbnail', 
		plugins_url( '/js/pubforce-admin.js', __FILE__ ), 
		array('jquery'),
		time(),
		true
  );
  
  wp_enqueue_script ( 'custom-category-thumbnail' );
}

add_action('admin_enqueue_scripts','enqueue_image_loader_for_categories');


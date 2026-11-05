<?php
/**
 * Category intro text (jxw_term_intro). SEO title/description/robots belong to jxw-seo.
 *
 * @package Dianalund_CPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Auth callback for category intro term meta.
 *
 * @param bool   $allowed     Whether allowed.
 * @param string $meta_key    Meta key.
 * @param int    $object_id   Term ID.
 * @return bool
 */
function jxw_term_intro_auth_callback( $allowed, $meta_key, $object_id ) {
	unset( $allowed, $meta_key, $object_id );

	return current_user_can( 'manage_categories' );
}

/**
 * Registers term meta for category intro.
 *
 * @return void
 */
function jxw_register_term_intro_meta() {
	register_term_meta(
		'category',
		'jxw_term_intro',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'jxw_sanitize_term_intro',
			'auth_callback'     => 'jxw_term_intro_auth_callback',
		)
	);
}
add_action( 'init', 'jxw_register_term_intro_meta' );

/**
 * @param mixed $value Raw HTML.
 * @return string
 */
function jxw_sanitize_term_intro( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return wp_kses_post( (string) $value );
}

/**
 * Intro HTML for a category term.
 *
 * @param int $term_id Term ID.
 * @return string
 */
function jxw_get_term_intro( $term_id ) {
	$term_id = (int) $term_id;
	if ( $term_id < 1 ) {
		return '';
	}

	$value = get_term_meta( $term_id, 'jxw_term_intro', true );

	return is_string( $value ) ? $value : '';
}

/**
 * Add-category screen field.
 *
 * @return void
 */
function jxw_render_term_intro_add_field() {
	wp_nonce_field( 'jxw_save_term_intro', 'jxw_term_intro_nonce' );
	?>
	<div class="form-field term-jxw-intro-wrap">
		<label for="jxw_term_intro"><?php echo esc_html__( 'Introtekst', 'dianalund_txtdomain' ); ?></label>
		<textarea name="jxw_term_intro" id="jxw_term_intro" rows="5" cols="40"></textarea>
		<p class="description"><?php echo esc_html__( 'Vises øverst i kategoriarkivet. Temaet kalder jxw_get_term_intro( $term_id ).', 'dianalund_txtdomain' ); ?></p>
	</div>
	<?php
}
add_action( 'category_add_form_fields', 'jxw_render_term_intro_add_field' );

/**
 * Edit-category screen field.
 *
 * @param WP_Term $term Term.
 * @return void
 */
function jxw_render_term_intro_edit_field( $term ) {
	wp_nonce_field( 'jxw_save_term_intro', 'jxw_term_intro_nonce' );
	$value = jxw_get_term_intro( (int) $term->term_id );
	?>
	<tr class="form-field term-jxw-intro-wrap">
		<th scope="row"><label for="jxw_term_intro"><?php echo esc_html__( 'Introtekst', 'dianalund_txtdomain' ); ?></label></th>
		<td>
			<textarea name="jxw_term_intro" id="jxw_term_intro" rows="5" cols="50"><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description"><?php echo esc_html__( 'Pluginet leverer ikke en kategoriarkiv-skabelon. I temaet: echo apply_filters( \'the_content\', jxw_get_term_intro( get_queried_object_id() ) );', 'dianalund_txtdomain' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'category_edit_form_fields', 'jxw_render_term_intro_edit_field' );

/**
 * Saves category intro from add/edit screens.
 *
 * @param int $term_id Term ID.
 * @return void
 */
function jxw_save_term_intro( $term_id ) {
	if ( ! isset( $_POST['jxw_term_intro_nonce'] ) ) {
		return;
	}

	$posted = wp_unslash( $_POST );
	$nonce  = isset( $posted['jxw_term_intro_nonce'] ) ? $posted['jxw_term_intro_nonce'] : '';
	if ( ! wp_verify_nonce( $nonce, 'jxw_save_term_intro' ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$value = isset( $posted['jxw_term_intro'] ) ? jxw_sanitize_term_intro( $posted['jxw_term_intro'] ) : '';
	update_term_meta( (int) $term_id, 'jxw_term_intro', $value );
}
add_action( 'created_category', 'jxw_save_term_intro' );
add_action( 'edited_category', 'jxw_save_term_intro' );

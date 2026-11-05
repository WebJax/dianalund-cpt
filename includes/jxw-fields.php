<?php
/**
 * Shared JXW field model for butiksside, erhvervsside and foreningsside.
 *
 * @package Dianalund_CPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'JXW_FIELDS_VERSION' ) ) {
	define( 'JXW_FIELDS_VERSION', '1' );
}

/**
 * Post types that receive the shared field set.
 *
 * @return string[]
 */
function jxw_supported_post_types() {
	return array( 'butiksside', 'erhvervsside', 'foreningsside' );
}

/**
 * Returns whether a post type is in the shared field set.
 *
 * @param string $post_type Post type slug.
 * @return bool
 */
function jxw_is_supported_post_type( $post_type ) {
	return in_array( $post_type, jxw_supported_post_types(), true );
}

/**
 * Legacy meta-key prefix for a CPT.
 *
 * @param string $post_type Post type slug.
 * @return string butik|bizz|fritid or empty.
 */
function jxw_legacy_prefix( $post_type ) {
	$map = array(
		'butiksside'    => 'butik',
		'erhvervsside'  => 'bizz',
		'foreningsside' => 'fritid',
	);

	return isset( $map[ $post_type ] ) ? $map[ $post_type ] : '';
}

/**
 * Field definitions for register_post_meta and helpers.
 *
 * @return array<string, array<string, mixed>>
 */
function jxw_field_definitions() {
	return array(
		'jxw_name'            => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_text',
			'legacy_suffix'  => 'payed_name',
			'description'    => 'Offentligt navn',
		),
		'jxw_street'          => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_text',
			'legacy_suffix'  => 'payed_adress',
			'description'    => 'Gade og husnummer',
		),
		'jxw_postal'          => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_postal',
			'legacy_suffix'  => 'payed_postal',
			'description'    => 'Postnummer, 4 cifre',
		),
		'jxw_city'            => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_text',
			'legacy_suffix'  => 'payed_city',
			'description'    => 'By',
		),
		'jxw_phone'           => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_phone',
			'legacy_suffix'  => 'payed_phone',
			'description'    => 'Telefon',
		),
		'jxw_email'           => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_email_field',
			'legacy_suffix'  => 'payed_mail',
			'description'    => 'E-mail',
		),
		'jxw_web'             => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_url_field',
			'legacy_suffix'  => 'payed_web',
			'description'    => 'Hjemmeside',
		),
		'jxw_facebook'        => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_url_field',
			'legacy_suffix'  => 'payed_fb',
			'description'    => 'Facebook-URL',
		),
		'jxw_instagram'       => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_url_field',
			'legacy_suffix'  => 'payed_insta',
			'description'    => 'Instagram-URL',
		),
		'jxw_hours'           => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_hours',
			'legacy_suffix'  => '',
			'description'    => 'Åbningstider som JSON',
		),
		'jxw_paid'            => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_paid',
			'legacy_suffix'  => '',
			'description'    => 'Betalende: 1 eller tom',
		),
		'jxw_holidays_closed' => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_paid',
			'legacy_suffix'  => '',
			'description'    => 'Lukket på danske helligdage: 1 eller tom',
		),
		'jxw_lat'             => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_lat',
			'legacy_suffix'  => '',
			'description'    => 'Breddegrad',
		),
		'jxw_lng'             => array(
			'type'           => 'string',
			'sanitize'       => 'jxw_sanitize_lng',
			'legacy_suffix'  => '',
			'description'    => 'Længdegrad',
		),
	);
}

/**
 * Normalizes a field argument to a jxw_* meta key.
 *
 * @param string $field Field name or full key.
 * @return string
 */
function jxw_normalize_field_key( $field ) {
	$field = (string) $field;
	if ( 0 !== strpos( $field, 'jxw_' ) ) {
		$field = 'jxw_' . $field;
	}

	return $field;
}

/**
 * Auth callback for registered post meta. Requires edit_post on the object.
 *
 * @param bool   $allowed   Whether allowed.
 * @param string $meta_key  Meta key.
 * @param int    $object_id Post ID.
 * @return bool
 */
function jxw_meta_auth_callback( $allowed, $meta_key, $object_id ) {
	unset( $allowed, $meta_key );

	return current_user_can( 'edit_post', (int) $object_id );
}

/**
 * Registers all shared fields on the three CPTs. Not registered on landingsside.
 *
 * @return void
 */
function jxw_register_post_meta() {
	$definitions = jxw_field_definitions();

	foreach ( jxw_supported_post_types() as $post_type ) {
		foreach ( $definitions as $key => $definition ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'single'            => true,
					'type'              => $definition['type'],
					'description'       => $definition['description'],
					'show_in_rest'      => true,
					'sanitize_callback' => $definition['sanitize'],
					'auth_callback'     => 'jxw_meta_auth_callback',
				)
			);
		}
	}
}
add_action( 'init', 'jxw_register_post_meta' );

/**
 * Whether a stored meta value counts as present.
 *
 * @param mixed $value Meta value.
 * @return bool
 */
function jxw_field_has_value( $value ) {
	if ( is_array( $value ) ) {
		return ! empty( $value );
	}

	return ( null !== $value && false !== $value && '' !== $value );
}

/**
 * Legacy meta key for a new field on a given post type.
 *
 * @param string $post_type Post type.
 * @param string $field_key jxw_* key.
 * @return string Empty when there is no legacy key.
 */
function jxw_legacy_meta_key( $post_type, $field_key ) {
	$field_key = jxw_normalize_field_key( $field_key );
	$prefix    = jxw_legacy_prefix( $post_type );

	if ( 'jxw_hours' === $field_key ) {
		if ( 'butiksside' === $post_type ) {
			return 'butik_aabentider_v2';
		}

		return '';
	}

	if ( 'jxw_paid' === $field_key ) {
		if ( 'butiksside' === $post_type ) {
			return 'butik_payed_payedbutik';
		}
		if ( 'erhvervsside' === $post_type ) {
			return 'bizz_payed_payedbizz';
		}

		return '';
	}

	$definitions = jxw_field_definitions();
	if ( ! isset( $definitions[ $field_key ]['legacy_suffix'] ) ) {
		return '';
	}

	$suffix = $definitions[ $field_key ]['legacy_suffix'];
	if ( '' === $suffix || '' === $prefix ) {
		return '';
	}

	return $prefix . '_' . $suffix;
}

/**
 * Extra HTML hours key used when butik_aabentider_v2 is missing.
 *
 * @param string $post_type Post type.
 * @return string
 */
function jxw_legacy_hours_html_key( $post_type ) {
	if ( 'butiksside' === $post_type ) {
		return 'butik_aabentider';
	}

	return '';
}

/**
 * Reads a shared field. Falls back to the CPT-prefixed legacy key when empty.
 *
 * @param int    $post_id Post ID.
 * @param string $field   Field name (name) or full key (jxw_name).
 * @return string
 */
function jxw_get_field( $post_id, $field ) {
	$post_id = (int) $post_id;
	$key     = jxw_normalize_field_key( $field );
	$value   = get_post_meta( $post_id, $key, true );

	if ( jxw_field_has_value( $value ) ) {
		if ( 'jxw_paid' === $key ) {
			return jxw_normalize_paid_value( $value );
		}

		return is_string( $value ) ? $value : (string) $value;
	}

	$post_type = get_post_type( $post_id );
	$legacy    = jxw_legacy_meta_key( $post_type, $key );
	$old       = '';

	if ( '' !== $legacy ) {
		$old = get_post_meta( $post_id, $legacy, true );
	}

	if ( 'jxw_hours' === $key && ! jxw_field_has_value( $old ) ) {
		$html_key = jxw_legacy_hours_html_key( $post_type );
		if ( '' !== $html_key ) {
			$old = get_post_meta( $post_id, $html_key, true );
		}
	}

	if ( 'jxw_paid' === $key ) {
		return jxw_normalize_paid_value( $old );
	}

	if ( ! jxw_field_has_value( $old ) ) {
		return '';
	}

	return is_string( $old ) ? $old : (string) $old;
}

/**
 * Whether the post is marked as paying.
 *
 * @param int $post_id Post ID.
 * @return bool
 */
function jxw_is_paid( $post_id ) {
	return '1' === jxw_get_field( (int) $post_id, 'jxw_paid' );
}

/**
 * Normalizes paid values to "1" or empty.
 *
 * @param mixed $value Stored value.
 * @return string
 */
function jxw_normalize_paid_value( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? '1' : '';
	}

	$string = is_scalar( $value ) ? trim( (string) $value ) : '';
	if ( '' === $string ) {
		return '';
	}

	$falsy = array( '0', 'false', 'no', 'off', 'intet' );
	if ( in_array( strtolower( $string ), $falsy, true ) ) {
		return '';
	}

	return '1';
}

/**
 * Sanitize callbacks.
 */

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_text( $value ) {
	if ( ! is_scalar( $value ) ) {
		return '';
	}

	return sanitize_text_field( (string) $value );
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_postal( $value ) {
	$text = jxw_sanitize_text( $value );
	if ( '' === $text ) {
		return '';
	}

	if ( preg_match( '/\d{4}/', $text, $matches ) ) {
		return $matches[0];
	}

	return '';
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_phone( $value ) {
	$text = jxw_sanitize_text( $value );
	if ( '' === $text ) {
		return '';
	}

	return preg_replace( '/[^0-9 +\-]/', '', $text );
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_email_field( $value ) {
	$text = jxw_sanitize_text( $value );
	if ( '' === $text ) {
		return '';
	}

	$email = sanitize_email( $text );

	return is_email( $email ) ? $email : '';
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_url_field( $value ) {
	$text = jxw_sanitize_text( $value );
	if ( '' === $text ) {
		return '';
	}

	if ( ! preg_match( '#^https?://#i', $text ) ) {
		$text = 'https://' . $text;
	}

	$url = esc_url_raw( $text );

	return $url ? $url : '';
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_paid( $value ) {
	if ( is_array( $value ) ) {
		return '';
	}

	return jxw_normalize_paid_value( $value );
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_lat( $value ) {
	return jxw_sanitize_coordinate( $value, -90, 90 );
}

/**
 * @param mixed $value Raw value.
 * @return string
 */
function jxw_sanitize_lng( $value ) {
	return jxw_sanitize_coordinate( $value, -180, 180 );
}

/**
 * @param mixed $value Raw value.
 * @param float $min   Inclusive minimum.
 * @param float $max   Inclusive maximum.
 * @return string
 */
function jxw_sanitize_coordinate( $value, $min, $max ) {
	$text = jxw_sanitize_text( $value );
	if ( '' === $text ) {
		return '';
	}

	$text = str_replace( ',', '.', $text );
	if ( ! is_numeric( $text ) ) {
		return '';
	}

	$number = (float) $text;
	if ( $number < $min || $number > $max ) {
		return '';
	}

	return $text;
}

/**
 * Sanitizes opening-hours JSON. Full structural validation lives in jxw-hours.php.
 *
 * @param mixed $value Raw JSON or array.
 * @return string JSON string or empty.
 */
function jxw_sanitize_hours( $value ) {
	if ( function_exists( 'jxw_sanitize_hours_payload' ) ) {
		return jxw_sanitize_hours_payload( $value );
	}

	if ( is_array( $value ) ) {
		$encoded = wp_json_encode( $value );

		return is_string( $encoded ) ? $encoded : '';
	}

	if ( ! is_scalar( $value ) ) {
		return '';
	}

	$raw = trim( (string) $value );
	if ( '' === $raw ) {
		return '';
	}

	$decoded = json_decode( $raw, true );
	if ( ! is_array( $decoded ) ) {
		return '';
	}

	$encoded = wp_json_encode( $decoded );

	return is_string( $encoded ) ? $encoded : '';
}

/**
 * Maps a CVR field name to the shared jxw_* key.
 *
 * @param string $cvr_key CVR map key (navn, adresse, …).
 * @return string
 */
function jxw_cvr_to_field_key( $cvr_key ) {
	$map = array(
		'navn'    => 'jxw_name',
		'adresse' => 'jxw_street',
		'postnr'  => 'jxw_postal',
		'by'      => 'jxw_city',
		'telefon' => 'jxw_phone',
		'email'   => 'jxw_email',
		'website' => 'jxw_web',
	);

	return isset( $map[ $cvr_key ] ) ? $map[ $cvr_key ] : '';
}

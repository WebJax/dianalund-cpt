<?php
/**
 * Minimal WordPress stubs so hours/fields can be unit-tested with php-cli.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

$GLOBALS['jxw_test_meta']      = array();
$GLOBALS['jxw_test_post_types'] = array();
$GLOBALS['jxw_test_titles']     = array();

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	unset( $hook, $callback, $priority, $accepted_args );
}

function add_management_page() {}

function __( $text, $domain = '' ) {
	unset( $domain );
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $text, $domain = '' ) {
	return esc_html( __( $text, $domain ) );
}

function sanitize_text_field( $str ) {
	$str = strip_tags( (string) $str );
	$str = preg_replace( '/[\r\n\t]+/', ' ', $str );
	return trim( $str );
}

function wp_strip_all_tags( $string ) {
	return trim( strip_tags( (string) $string ) );
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

function wp_kses_post( $value ) {
	return $value;
}

function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function sanitize_email( $email ) {
	return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
}

function esc_url_raw( $url ) {
	$url = (string) $url;
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}
	return filter_var( $url, FILTER_SANITIZE_URL );
}

function current_user_can( $cap, $id = 0 ) {
	unset( $cap, $id );
	return true;
}

function get_post_type( $post_id ) {
	return isset( $GLOBALS['jxw_test_post_types'][ $post_id ] ) ? $GLOBALS['jxw_test_post_types'][ $post_id ] : 'butiksside';
}

function get_post_meta( $post_id, $key, $single = true ) {
	unset( $single );
	if ( ! isset( $GLOBALS['jxw_test_meta'][ $post_id ][ $key ] ) ) {
		return '';
	}
	return $GLOBALS['jxw_test_meta'][ $post_id ][ $key ];
}

function update_post_meta( $post_id, $key, $value ) {
	$GLOBALS['jxw_test_meta'][ $post_id ][ $key ] = $value;
	return true;
}

function get_the_title( $post_id ) {
	return isset( $GLOBALS['jxw_test_titles'][ $post_id ] ) ? $GLOBALS['jxw_test_titles'][ $post_id ] : 'Testbutik';
}

function get_the_ID() {
	return 1;
}

function is_singular( $types = null ) {
	unset( $types );
	return true;
}

function apply_filters( $tag, $value ) {
	unset( $tag );
	return $value;
}

function current_time( $type ) {
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	if ( 'timestamp' === $type ) {
		return time();
	}
	return gmdate( $type );
}

function wp_unslash( $value ) {
	return $value;
}

function register_post_meta() {}

function plugins_url( $path, $plugin = '' ) {
	unset( $plugin );
	return $path;
}

function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? ' selected="selected"' : '';
	if ( $echo ) {
		echo $out;
	}
	return $out;
}

function checked( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? ' checked="checked"' : '';
	if ( $echo ) {
		echo $out;
	}
	return $out;
}

function wp_nonce_field() {}

function wp_verify_nonce() {
	return true;
}

function date_i18n( $format, $timestamp = false ) {
	return gmdate( $format, false === $timestamp ? time() : $timestamp );
}

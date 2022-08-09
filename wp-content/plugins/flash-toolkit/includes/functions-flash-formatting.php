<?php
/**
 * FlashToolkit Formatting
 *
 * Functions for formatting data.
 *
 * @author   ThemeGrill
 * @category Core
 * @package  FlashToolkit/Functions
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clean variables using sanitize_text_field
 * @param  string|array $var
 * @return string
 */
function flash_clean( $var ) {
	return is_array( $var ) ? array_map( 'flash_clean', $var ) : sanitize_text_field( $var );
}

/**
 * Clean variables using wp_kses_post
 * @param  string|array $var
 * @return string
 */
function flash_clean_html( $var ) {
	return is_array( $var ) ? array_map( 'flash_clean', $var ) : wp_kses_post( $var );
}


/**
 * Sanitize a string destined to be a tooltip.
 *
 * @since  1.1.0  Tooltips are encoded with htmlspecialchars to prevent XSS. Should not be used in conjunction with esc_attr()
 * @param  string $var
 * @return string
 */
function flash_toolkit_sanitize_tooltip( $var ) {
	return htmlspecialchars( wp_kses( html_entity_decode( $var ), array(
		'br'     => array(),
		'em'     => array(),
		'strong' => array(),
		'small'  => array(),
		'span'   => array(),
		'ul'     => array(),
		'li'     => array(),
		'ol'     => array(),
		'p'      => array(),
	) ) );
}

/**
 * Sanitize permalink values before insertion into DB.
 *
 * Cannot use flash_clean because it sometimes strips % chars and breaks the user's setting.
 *
 * @param  string $value
 * @return string
 */
function flash_sanitize_permalink( $value ) {
	global $wpdb;

	$value = $wpdb->strip_invalid_text_for_column( $wpdb->options, 'option_value', $value );

	if ( is_wp_error( $value ) ) {
		$value = '';
	}

	$value = esc_url_raw( $value );
	$value = str_replace( 'http://', '', $value );
	return untrailingslashit( $value );
}

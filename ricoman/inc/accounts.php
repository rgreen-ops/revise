<?php
/**
 * Customer accounts.
 *
 * Adds first/last name + customer type to registration and the user profile, so
 * trade/specifier visitors can create an account once and then download freely
 * (the download gate bypasses logged-in users) and keep saved project lists.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Customer-type <option> list for a given current value. */
function ricoman_ctype_options( $current = '' ) {
	$types = function_exists( 'ricoman_customer_types' ) ? ricoman_customer_types() : array();
	$out   = '<option value="">' . esc_html__( 'Select…', 'ricoman' ) . '</option>';
	foreach ( $types as $t ) {
		$out .= '<option ' . selected( $current, $t, false ) . '>' . esc_html( $t ) . '</option>';
	}
	return $out;
}

/* ---------------------------------------------------------------- registration */

add_action( 'register_form', function () {
	$first = isset( $_POST['first_name'] ) ? esc_attr( wp_unslash( $_POST['first_name'] ) ) : '';
	$last  = isset( $_POST['last_name'] ) ? esc_attr( wp_unslash( $_POST['last_name'] ) ) : '';
	$type  = isset( $_POST['rm_ctype'] ) ? wp_unslash( $_POST['rm_ctype'] ) : '';
	echo '<p><label>' . esc_html__( 'First name', 'ricoman' ) . '<br><input type="text" name="first_name" class="input" value="' . $first . '" size="25"></label></p>';
	echo '<p><label>' . esc_html__( 'Last name', 'ricoman' ) . '<br><input type="text" name="last_name" class="input" value="' . $last . '" size="25"></label></p>';
	echo '<p><label>' . esc_html__( 'I am a…', 'ricoman' ) . '<br><select name="rm_ctype" class="input">' . ricoman_ctype_options( $type ) . '</select></label></p>'; // phpcs:ignore WordPress.Security.EscapeOutput
} );

add_filter( 'registration_errors', function ( $errors ) {
	if ( empty( $_POST['first_name'] ) ) {
		$errors->add( 'first_name_error', __( 'Please enter your first name.', 'ricoman' ) );
	}
	if ( empty( $_POST['rm_ctype'] ) ) {
		$errors->add( 'rm_ctype_error', __( 'Please tell us your customer type.', 'ricoman' ) );
	}
	return $errors;
}, 10, 1 );

add_action( 'user_register', function ( $user_id ) {
	$first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
	$last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
	$type  = isset( $_POST['rm_ctype'] ) ? sanitize_text_field( wp_unslash( $_POST['rm_ctype'] ) ) : '';
	if ( $first ) {
		update_user_meta( $user_id, 'first_name', $first );
	}
	if ( $last ) {
		update_user_meta( $user_id, 'last_name', $last );
	}
	if ( $type ) {
		update_user_meta( $user_id, '_ricoman_customer_type', $type );
	}
	$name = trim( $first . ' ' . $last );
	if ( $name ) {
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $name ) );
	}
} );

/* -------------------------------------------------------------------- profile */

function ricoman_ctype_profile_field( $user ) {
	$cur = get_user_meta( $user->ID, '_ricoman_customer_type', true );
	echo '<h2>' . esc_html__( 'Ricoman', 'ricoman' ) . '</h2>';
	echo '<table class="form-table"><tr><th><label for="rm_ctype">' . esc_html__( 'Customer type', 'ricoman' ) . '</label></th>';
	echo '<td><select name="rm_ctype" id="rm_ctype">' . ricoman_ctype_options( $cur ) . '</select></td></tr></table>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
add_action( 'show_user_profile', 'ricoman_ctype_profile_field' );
add_action( 'edit_user_profile', 'ricoman_ctype_profile_field' );

function ricoman_save_ctype_profile( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}
	if ( isset( $_POST['rm_ctype'] ) ) {
		update_user_meta( $user_id, '_ricoman_customer_type', sanitize_text_field( wp_unslash( $_POST['rm_ctype'] ) ) );
	}
}
add_action( 'personal_options_update', 'ricoman_save_ctype_profile' );
add_action( 'edit_user_profile_update', 'ricoman_save_ctype_profile' );

/** Customers: honour a same-site redirect (e.g. back to the product they were
 *  adding), otherwise send them to the homepage rather than wp-admin. */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'edit_posts' ) ) {
		if ( $requested ) {
			$safe = wp_validate_redirect( $requested, '' );
			if ( $safe ) {
				return $safe;
			}
		}
		return home_url( '/' );
	}
	return $redirect_to;
}, 10, 3 );

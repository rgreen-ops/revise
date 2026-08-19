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

/** Email is the identifier — generate the WP username from the email so the
 *  Username field can be removed from the form. Runs before registration validates. */
add_action( 'login_init', function () {
	if ( empty( $_POST['user_login'] ) && ! empty( $_POST['user_email'] ) ) {
		$email = sanitize_email( wp_unslash( $_POST['user_email'] ) );
		if ( $email && is_email( $email ) ) {
			$base = sanitize_user( current( explode( '@', $email ) ), true );
			if ( '' === $base ) {
				$base = 'user';
			}
			$user = $base;
			$i    = 1;
			while ( username_exists( $user ) ) {
				$user = $base . $i;
				$i++;
			}
			$_POST['user_login'] = $user;
		}
	}
} );

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

/** Hide the WordPress admin toolbar on the front end for customers (anyone who
 *  can't edit content) — they shouldn't see the wp-admin bar/icons. Staff keep it. */
add_filter( 'show_admin_bar', function ( $show ) {
	return current_user_can( 'edit_posts' ) ? $show : false;
} );

/** Where a customer (front-end-only account) lands after login / when bounced
 *  out of wp-admin. Filterable so it can point at a front-end account page. */
function ricoman_customer_home() {
	return apply_filters( 'ricoman_customer_home', home_url( '/' ) );
}

/** Customers: honour a same-site FRONT-END redirect (e.g. back to the product
 *  they were adding), but NEVER drop them in wp-admin / wp-login — send them to
 *  the front end instead. */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && ! user_can( $user, 'edit_posts' ) ) {
		if ( $requested ) {
			$safe = wp_validate_redirect( $requested, '' );
			// Only honour a front-end destination — an admin/login URL means the
			// customer would land in the back end, which we never want.
			if ( $safe && false === strpos( $safe, '/wp-admin' ) && false === strpos( $safe, 'wp-login.php' ) ) {
				return $safe;
			}
		}
		return ricoman_customer_home();
	}
	return $redirect_to;
}, 10, 3 );

/** Belt-and-braces: if a customer (anyone who can't edit content) ever opens a
 *  wp-admin screen directly, bounce them to the front end. Leaves AJAX and
 *  admin-post form handlers alone so front-end features (saved projects, the
 *  download gate, newsletter, etc.) keep working. Staff are unaffected. */
add_action( 'admin_init', function () {
	if ( wp_doing_ajax() ) {
		return;
	}
	$GLOBALS['pagenow'] = $GLOBALS['pagenow'] ?? '';
	if ( 'admin-post.php' === $GLOBALS['pagenow'] || 'admin-ajax.php' === $GLOBALS['pagenow'] ) {
		return;
	}
	if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( ricoman_customer_home() );
		exit;
	}
} );

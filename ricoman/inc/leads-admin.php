<?php
/**
 * Leads back-office — a light CRM for captured leads.
 *
 *  - Columns: Type · Name · Role · Email · Page · Source · Status · Received
 *  - Status workflow: Logged → Contacted → Quoted → Won / Lost. Editable inline
 *    from the list (a dropdown per row, saved instantly), via bulk actions, or on
 *    the lead's own screen.
 *  - Source attribution: Organic / Direct / PPC / Social / Referral / Campaign,
 *    captured first-touch when the lead is created.
 *  - Filters above the list (Status / Source / Type) + a conversion tracker bar.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The lead statuses: key => [ label, colour ]. 'logged' is the default. */
function ricoman_lead_statuses() {
	return array(
		'logged'    => array( __( 'Logged', 'ricoman' ), '#646970' ),
		'contacted' => array( __( 'Contacted', 'ricoman' ), '#2271b1' ),
		'quoted'    => array( __( 'Quoted', 'ricoman' ), '#8a6d00' ),
		'won'       => array( __( 'Won', 'ricoman' ), '#1a7f37' ),
		'lost'      => array( __( 'Lost', 'ricoman' ), '#b32d2e' ),
	);
}

/** A lead's status key (defaults to 'logged'). */
function ricoman_lead_status( $post_id ) {
	$s = (string) get_post_meta( $post_id, '_lead_status', true );
	return ( $s && array_key_exists( $s, ricoman_lead_statuses() ) ) ? $s : 'logged';
}

/* ----------------------------------------------------------------- columns */

add_filter( 'manage_lead_posts_columns', function ( $cols ) {
	return array(
		'cb'        => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />',
		'title'     => __( 'Lead', 'ricoman' ),
		'rm_type'   => __( 'Type', 'ricoman' ),
		'rm_name'   => __( 'Name', 'ricoman' ),
		'rm_role'   => __( 'Role', 'ricoman' ),
		'rm_email'  => __( 'Email', 'ricoman' ),
		'rm_page'   => __( 'Page', 'ricoman' ),
		'rm_source' => __( 'Source', 'ricoman' ),
		'rm_status' => __( 'Status', 'ricoman' ),
		'date'      => __( 'Received', 'ricoman' ),
	);
} );

add_action( 'manage_lead_posts_custom_column', function ( $col, $post_id ) {
	$g = function ( $k ) use ( $post_id ) { return (string) get_post_meta( $post_id, $k, true ); };
	switch ( $col ) {
		case 'rm_type':
			$t = $g( '_lead_type' );
			$p = $g( '_lead_product' );
			echo esc_html( ( $t ? $t : '—' ) . ( $p ? ' · ' . $p : '' ) );
			break;
		case 'rm_name':
			echo esc_html( $g( '_lead_name' ) ? $g( '_lead_name' ) : '—' );
			break;
		case 'rm_role':
			echo esc_html( $g( '_lead_role' ) ? $g( '_lead_role' ) : '—' );
			break;
		case 'rm_email':
			$e = $g( '_lead_email' );
			echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '—';
			break;
		case 'rm_page':
			$u = $g( '_lead_page' );
			if ( $u ) {
				$label = ltrim( (string) wp_parse_url( $u, PHP_URL_PATH ), '/' );
				echo '<a href="' . esc_url( $u ) . '" target="_blank" rel="noopener" title="' . esc_attr( $u ) . '">' . esc_html( $label ? $label : $u ) . '</a>';
			} else {
				echo '—';
			}
			break;
		case 'rm_source':
			$s = $g( '_lead_attr_source' );
			echo $s ? '<strong>' . esc_html( $s ) . '</strong>' : '—';
			$utm = $g( '_lead_utm' );
			if ( $utm ) {
				echo '<br><span style="color:#646970;font-size:11px">' . esc_html( $utm ) . '</span>';
			}
			break;
		case 'rm_status':
			$cur      = ricoman_lead_status( $post_id );
			$statuses = ricoman_lead_statuses();
			$lbl      = $statuses[ $cur ];
			echo '<span class="rm-lead-badge" data-lead="' . (int) $post_id . '" style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;background:' . esc_attr( $lbl[1] ) . '">' . esc_html( $lbl[0] ) . '</span>';
			echo '<select class="rm-lead-statussel" data-lead="' . (int) $post_id . '" data-nonce="' . esc_attr( wp_create_nonce( 'rm_lead_status_' . $post_id ) ) . '" style="margin-top:5px;max-width:130px">';
			foreach ( $statuses as $k => $info ) {
				echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $info[0] ) . '</option>';
			}
			echo '</select>';
			break;
	}
}, 10, 2 );

/** Inline status editing from the list (AJAX) + the small script that drives it. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-lead' !== $screen->id ) {
		return;
	}
	$colours = array();
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		$colours[ $k ] = array( 'label' => $info[0], 'colour' => $info[1] );
	}
	$js = 'jQuery(function($){'
		. 'var C=' . wp_json_encode( $colours ) . ';'
		. '$(document).on("change",".rm-lead-statussel",function(){'
		. 'var s=$(this),lead=s.data("lead"),val=s.val();'
		. 's.prop("disabled",true);'
		. '$.post(ajaxurl,{action:"rm_lead_setstatus",lead:lead,status:val,nonce:s.data("nonce")},function(r){'
		. 's.prop("disabled",false);'
		. 'if(r&&r.success&&C[val]){var b=$(".rm-lead-badge[data-lead=\""+lead+"\"]");b.text(C[val].label).css("background",C[val].colour);}'
		. '});'
		. '});'
		. '});';
	wp_add_inline_script( 'jquery-core', $js );
} );

add_action( 'wp_ajax_rm_lead_setstatus', function () {
	$lead   = isset( $_POST['lead'] ) ? (int) $_POST['lead'] : 0;
	$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
	if ( ! $lead || ! current_user_can( 'edit_post', $lead )
		|| ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['nonce'] ), 'rm_lead_status_' . $lead )
		|| ! array_key_exists( $status, ricoman_lead_statuses() ) ) {
		wp_send_json_error();
	}
	update_post_meta( $lead, '_lead_status', $status );
	wp_send_json_success();
} );

/* ----------------------------------------------------------- bulk actions */

add_filter( 'bulk_actions-edit-lead', function ( $actions ) {
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		$actions[ 'rm_status_' . $k ] = sprintf( __( 'Mark as %s', 'ricoman' ), $info[0] );
	}
	return $actions;
} );

add_filter( 'handle_bulk_actions-edit-lead', function ( $redirect, $action, $ids ) {
	if ( 0 !== strpos( $action, 'rm_status_' ) ) {
		return $redirect;
	}
	$status = substr( $action, strlen( 'rm_status_' ) );
	if ( array_key_exists( $status, ricoman_lead_statuses() ) ) {
		foreach ( (array) $ids as $id ) {
			if ( current_user_can( 'edit_post', $id ) ) {
				update_post_meta( (int) $id, '_lead_status', $status );
			}
		}
	}
	return $redirect;
}, 10, 3 );

/* --------------------------------------------------------------- filters */

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'lead' !== $post_type ) {
		return;
	}
	$cur = isset( $_GET['rm_fstatus'] ) ? sanitize_key( $_GET['rm_fstatus'] ) : '';
	echo '<select name="rm_fstatus"><option value="">' . esc_html__( 'All statuses', 'ricoman' ) . '</option>';
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $info[0] ) . '</option>';
	}
	echo '</select>';

	global $wpdb;
	foreach ( array(
		'_lead_attr_source' => __( 'All sources', 'ricoman' ),
		'_lead_source'      => __( 'All channels', 'ricoman' ),
		'_lead_type'        => __( 'All types', 'ricoman' ),
	) as $key => $all ) {
		$vals = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key=%s AND meta_value<>'' ORDER BY meta_value ASC LIMIT 100", $key ) );
		if ( ! $vals ) {
			continue;
		}
		$param = 'rm_f' . ltrim( $key, '_' );
		$cv    = isset( $_GET[ $param ] ) ? wp_unslash( $_GET[ $param ] ) : '';
		echo '<select name="' . esc_attr( $param ) . '"><option value="">' . esc_html( $all ) . '</option>';
		foreach ( $vals as $v ) {
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $cv, $v, false ) . '>' . esc_html( $v ) . '</option>';
		}
		echo '</select>';
	}
} );

add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || 'lead' !== $q->get( 'post_type' ) ) {
		return;
	}
	$meta = array();
	if ( ! empty( $_GET['rm_fstatus'] ) ) {
		$st = sanitize_key( $_GET['rm_fstatus'] );
		if ( 'logged' === $st ) {
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => '_lead_status', 'value' => 'logged' ),
				array( 'key' => '_lead_status', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$meta[] = array( 'key' => '_lead_status', 'value' => $st );
		}
	}
	foreach ( array( 'rm_flead_attr_source' => '_lead_attr_source', 'rm_flead_source' => '_lead_source', 'rm_flead_type' => '_lead_type' ) as $param => $key ) {
		if ( ! empty( $_GET[ $param ] ) ) {
			$meta[] = array( 'key' => $key, 'value' => sanitize_text_field( wp_unslash( $_GET[ $param ] ) ) );
		}
	}
	if ( $meta ) {
		if ( count( $meta ) > 1 ) {
			$meta['relation'] = 'AND';
		}
		$q->set( 'meta_query', $meta );
	}
} );

/* ------------------------------------------------- conversion tracker bar */

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-lead' !== $screen->id ) {
		return;
	}
	global $wpdb;
	$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='lead' AND post_status<>'trash'" );
	$counts = array();
	foreach ( $wpdb->get_results( "SELECT meta_value AS s, COUNT(*) AS c FROM {$wpdb->postmeta} WHERE meta_key='_lead_status' GROUP BY meta_value" ) as $r ) {
		$counts[ $r->s ] = (int) $r->c;
	}
	$with_status     = array_sum( $counts );
	$counts['logged'] = ( isset( $counts['logged'] ) ? $counts['logged'] : 0 ) + max( 0, $total - $with_status );
	$won  = isset( $counts['won'] ) ? $counts['won'] : 0;
	$rate = $total > 0 ? round( $won / $total * 100, 1 ) : 0;

	echo '<div class="notice notice-info" style="padding:12px 14px"><strong style="margin-right:18px">' . esc_html( sprintf( __( '%d leads', 'ricoman' ), $total ) ) . '</strong>';
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		$n = isset( $counts[ $k ] ) ? $counts[ $k ] : 0;
		echo '<span style="display:inline-flex;align-items:center;gap:6px;margin-right:16px"><span style="width:9px;height:9px;border-radius:50%;background:' . esc_attr( $info[1] ) . ';display:inline-block"></span><strong>' . (int) $n . '</strong> ' . esc_html( $info[0] ) . '</span>';
	}
	echo '<span style="margin-left:6px">' . esc_html( sprintf( __( 'Conversion: %s%%', 'ricoman' ), $rate ) ) . '</span>';
	echo '</div>';
} );

/* ------------------------------------------- full detail on the lead screen */

add_action( 'add_meta_boxes_lead', function () {
	add_meta_box( 'rm_lead_detail', __( 'Lead details', 'ricoman' ), function ( $post ) {
		$rows = array(
			__( 'Type', 'ricoman' )          => get_post_meta( $post->ID, '_lead_type', true ),
			__( 'Name', 'ricoman' )          => get_post_meta( $post->ID, '_lead_name', true ),
			__( 'Email', 'ricoman' )         => get_post_meta( $post->ID, '_lead_email', true ),
			__( 'Phone', 'ricoman' )         => get_post_meta( $post->ID, '_lead_phone', true ),
			__( 'Company', 'ricoman' )       => get_post_meta( $post->ID, '_lead_company', true ),
			__( 'Customer type', 'ricoman' ) => get_post_meta( $post->ID, '_lead_role', true ),
			__( 'Product', 'ricoman' )       => get_post_meta( $post->ID, '_lead_product', true ),
			__( 'Page', 'ricoman' )          => get_post_meta( $post->ID, '_lead_page', true ),
			__( 'Source', 'ricoman' )        => get_post_meta( $post->ID, '_lead_attr_source', true ),
			__( 'Landing page', 'ricoman' )  => get_post_meta( $post->ID, '_lead_landing', true ),
			__( 'Referrer', 'ricoman' )      => get_post_meta( $post->ID, '_lead_referrer', true ),
			__( 'UTM', 'ricoman' )           => get_post_meta( $post->ID, '_lead_utm', true ),
			__( 'Channel', 'ricoman' )       => get_post_meta( $post->ID, '_lead_source', true ),
			__( 'Message', 'ricoman' )       => get_post_meta( $post->ID, '_lead_message', true ),
			__( 'Project items', 'ricoman' ) => get_post_meta( $post->ID, '_lead_items', true ),
		);
		echo '<table class="form-table">';
		foreach ( $rows as $k => $v ) {
			if ( '' === trim( (string) $v ) ) {
				continue;
			}
			echo '<tr><th style="width:140px">' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( $v ) ) . '</td></tr>';
		}
		echo '</table>';

		$cur = ricoman_lead_status( $post->ID );
		wp_nonce_field( 'rm_lead_status_box', 'rm_lead_status_nonce' );
		echo '<p><label><strong>' . esc_html__( 'Status', 'ricoman' ) . '</strong> <select name="rm_lead_status">';
		foreach ( ricoman_lead_statuses() as $k => $info ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $info[0] ) . '</option>';
		}
		echo '</select></label></p>';
		$notes = (string) get_post_meta( $post->ID, '_lead_notes', true );
		echo '<p><label><strong>' . esc_html__( 'Notes', 'ricoman' ) . '</strong><br><textarea name="rm_lead_notes" rows="4" style="width:100%">' . esc_textarea( $notes ) . '</textarea></label></p>';
	}, 'lead', 'normal', 'high' );
} );

add_action( 'save_post_lead', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( empty( $_POST['rm_lead_status_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rm_lead_status_nonce'] ), 'rm_lead_status_box' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( isset( $_POST['rm_lead_status'] ) && array_key_exists( sanitize_key( $_POST['rm_lead_status'] ), ricoman_lead_statuses() ) ) {
		update_post_meta( $post_id, '_lead_status', sanitize_key( $_POST['rm_lead_status'] ) );
	}
	if ( isset( $_POST['rm_lead_notes'] ) ) {
		update_post_meta( $post_id, '_lead_notes', sanitize_textarea_field( wp_unslash( $_POST['rm_lead_notes'] ) ) );
	}
} );

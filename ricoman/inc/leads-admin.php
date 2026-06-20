<?php
/**
 * Leads back-office — a light CRM for captured leads.
 *
 *  - Columns: Type · Email · Product/Company · Source · Status · Received
 *  - Status workflow: New → Contacted → Quoted → Won / Lost (set inline per row
 *    and via bulk actions), with coloured badges.
 *  - Filters: by Status, Source and Type (dropdowns above the list).
 *  - Conversion tracker: a summary bar (totals + a conversion rate) above the list.
 *
 * Status is stored in `_lead_status` on the `lead` post.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The lead statuses: key => [ label, colour ]. */
function ricoman_lead_statuses() {
	return array(
		'new'       => array( __( 'New', 'ricoman' ), '#2271b1' ),
		'contacted' => array( __( 'Contacted', 'ricoman' ), '#8a6d00' ),
		'quoted'    => array( __( 'Quoted', 'ricoman' ), '#6c3fb5' ),
		'won'       => array( __( 'Won', 'ricoman' ), '#1a7f37' ),
		'lost'      => array( __( 'Lost', 'ricoman' ), '#b32d2e' ),
	);
}

/** A lead's status key (defaults to 'new'). */
function ricoman_lead_status( $post_id ) {
	$s = (string) get_post_meta( $post_id, '_lead_status', true );
	return $s ? $s : 'new';
}

/* ----------------------------------------------------------------- columns */

add_filter( 'manage_lead_posts_columns', function ( $cols ) {
	return array(
		'cb'        => isset( $cols['cb'] ) ? $cols['cb'] : '<input type="checkbox" />',
		'title'     => __( 'Lead', 'ricoman' ),
		'rm_type'   => __( 'Type', 'ricoman' ),
		'rm_email'  => __( 'Email', 'ricoman' ),
		'rm_extra'  => __( 'Product / Company', 'ricoman' ),
		'rm_source' => __( 'Source', 'ricoman' ),
		'rm_status' => __( 'Status', 'ricoman' ),
		'date'      => __( 'Received', 'ricoman' ),
	);
} );

add_action( 'manage_lead_posts_custom_column', function ( $col, $post_id ) {
	$g = function ( $k ) use ( $post_id ) { return (string) get_post_meta( $post_id, $k, true ); };
	switch ( $col ) {
		case 'rm_type':
			echo esc_html( $g( '_lead_type' ) ? $g( '_lead_type' ) : ( $g( '_lead_role' ) ? $g( '_lead_role' ) : '—' ) );
			break;
		case 'rm_email':
			$e = $g( '_lead_email' );
			echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '—';
			break;
		case 'rm_extra':
			$x = $g( '_lead_product' ) ? $g( '_lead_product' ) : $g( '_lead_company' );
			echo esc_html( $x ? $x : '—' );
			break;
		case 'rm_source':
			echo esc_html( $g( '_lead_source' ) ? $g( '_lead_source' ) : '—' );
			break;
		case 'rm_status':
			$cur      = ricoman_lead_status( $post_id );
			$statuses = ricoman_lead_statuses();
			$lbl      = isset( $statuses[ $cur ] ) ? $statuses[ $cur ] : array( ucfirst( $cur ), '#646970' );
			echo '<span class="rm-lead-badge" style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;background:' . esc_attr( $lbl[1] ) . '">' . esc_html( $lbl[0] ) . '</span>';
			// Quick-set links.
			$nonce = wp_create_nonce( 'rm_lead_status_' . $post_id );
			$links = array();
			foreach ( $statuses as $k => $info ) {
				if ( $k === $cur ) {
					continue;
				}
				$url     = admin_url( 'admin-post.php?action=rm_lead_status&lead=' . $post_id . '&status=' . $k . '&_wpnonce=' . $nonce );
				$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html( $info[0] ) . '</a>';
			}
			echo '<div class="row-actions" style="white-space:normal">' . implode( ' · ', $links ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			break;
	}
}, 10, 2 );

/** Set a single lead's status (from the quick links). */
add_action( 'admin_post_rm_lead_status', function () {
	$lead   = isset( $_GET['lead'] ) ? (int) $_GET['lead'] : 0;
	$status = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';
	if ( ! $lead || ! current_user_can( 'edit_post', $lead ) || ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'rm_lead_status_' . $lead )
		|| ! array_key_exists( $status, ricoman_lead_statuses() ) ) {
		wp_safe_redirect( admin_url( 'edit.php?post_type=lead' ) );
		exit;
	}
	update_post_meta( $lead, '_lead_status', $status );
	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=lead' ) );
	exit;
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
	return add_query_arg( 'rm_bulk_status', count( (array) $ids ), $redirect );
}, 10, 3 );

/* --------------------------------------------------------------- filters */

add_action( 'restrict_manage_posts', function ( $post_type ) {
	if ( 'lead' !== $post_type ) {
		return;
	}
	// Status filter.
	$cur = isset( $_GET['rm_fstatus'] ) ? sanitize_key( $_GET['rm_fstatus'] ) : '';
	echo '<select name="rm_fstatus"><option value="">' . esc_html__( 'All statuses', 'ricoman' ) . '</option>';
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $info[0] ) . '</option>';
	}
	echo '</select>';
	// Source + Type filters (distinct values from the leads).
	foreach ( array( '_lead_source' => __( 'All sources', 'ricoman' ), '_lead_type' => __( 'All types', 'ricoman' ) ) as $key => $all ) {
		global $wpdb;
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
		if ( 'new' === $st ) {
			// "New" = no status set yet OR explicitly 'new'.
			$meta[] = array(
				'relation' => 'OR',
				array( 'key' => '_lead_status', 'value' => 'new' ),
				array( 'key' => '_lead_status', 'compare' => 'NOT EXISTS' ),
			);
		} else {
			$meta[] = array( 'key' => '_lead_status', 'value' => $st );
		}
	}
	if ( ! empty( $_GET['rm_flead_source'] ) ) {
		$meta[] = array( 'key' => '_lead_source', 'value' => sanitize_text_field( wp_unslash( $_GET['rm_flead_source'] ) ) );
	}
	if ( ! empty( $_GET['rm_flead_type'] ) ) {
		$meta[] = array( 'key' => '_lead_type', 'value' => sanitize_text_field( wp_unslash( $_GET['rm_flead_type'] ) ) );
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
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='lead' AND post_status<>'trash'" );
	$counts = array();
	foreach ( $wpdb->get_results( "SELECT meta_value AS s, COUNT(*) AS c FROM {$wpdb->postmeta} WHERE meta_key='_lead_status' GROUP BY meta_value" ) as $r ) {
		$counts[ $r->s ] = (int) $r->c;
	}
	$with_status = array_sum( $counts );
	$counts['new'] = isset( $counts['new'] ) ? $counts['new'] : 0;
	$counts['new'] += max( 0, $total - $with_status ); // leads with no status = New.
	$won  = isset( $counts['won'] ) ? $counts['won'] : 0;
	$rate = $total > 0 ? round( $won / $total * 100, 1 ) : 0;

	$chip = function ( $label, $n, $colour ) {
		return '<span style="display:inline-flex;align-items:center;gap:6px;margin-right:16px"><span style="width:9px;height:9px;border-radius:50%;background:' . esc_attr( $colour ) . ';display:inline-block"></span><strong>' . (int) $n . '</strong> ' . esc_html( $label ) . '</span>';
	};
	echo '<div class="notice notice-info" style="padding:12px 14px"><strong style="margin-right:18px">' . esc_html( sprintf( __( '%d leads', 'ricoman' ), $total ) ) . '</strong>';
	foreach ( ricoman_lead_statuses() as $k => $info ) {
		echo $chip( $info[0], isset( $counts[ $k ] ) ? $counts[ $k ] : 0, $info[1] ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<span style="margin-left:6px">' . esc_html( sprintf( __( 'Conversion: %s%%', 'ricoman' ), $rate ) ) . '</span>';
	echo '</div>';
} );

/* ------------------------------------------- full detail on the lead screen */

add_action( 'add_meta_boxes_lead', function () {
	add_meta_box( 'rm_lead_detail', __( 'Lead details', 'ricoman' ), function ( $post ) {
		$rows = array(
			__( 'Name', 'ricoman' )     => get_post_meta( $post->ID, '_lead_name', true ),
			__( 'Email', 'ricoman' )    => get_post_meta( $post->ID, '_lead_email', true ),
			__( 'Phone', 'ricoman' )    => get_post_meta( $post->ID, '_lead_phone', true ),
			__( 'Company', 'ricoman' )  => get_post_meta( $post->ID, '_lead_company', true ),
			__( 'Customer type', 'ricoman' ) => get_post_meta( $post->ID, '_lead_role', true ),
			__( 'Product', 'ricoman' )  => get_post_meta( $post->ID, '_lead_product', true ),
			__( 'Source', 'ricoman' )   => get_post_meta( $post->ID, '_lead_source', true ),
			__( 'Message', 'ricoman' )  => get_post_meta( $post->ID, '_lead_message', true ),
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
		echo '<p><label><strong>' . esc_html__( 'Status', 'ricoman' ) . '</strong> ';
		echo '<select name="rm_lead_status">';
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

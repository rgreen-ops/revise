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

/* ------------------------- gamified dashboard atop the Leads list --------- */

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'edit-lead' !== $screen->id ) {
		return;
	}
	// Only on the main list view (not search/filtered drill-downs is fine too).
	ricoman_leads_dashboard_render();
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

/* ============================================================ Leads dashboard
 * Rendered at the top of the single "Leads" list screen (via admin_notices), so
 * there's one gamified Leads screen with the full list right below it.
 * ------------------------------------------------------------------------- */

function ricoman_leads_dashboard_render() {
	global $wpdb;
	$statuses = ricoman_lead_statuses();

	// --- Date range (scopes the dashboard metrics) ---
	$range = isset( $_GET['rm_range'] ) ? sanitize_key( $_GET['rm_range'] ) : 'all';
	$now   = current_time( 'timestamp' );
	$from  = '';
	$to    = '';
	switch ( $range ) {
		case 'mtd': $from = gmdate( 'Y-m-01 00:00:00', $now ); break;
		case '30d': $from = gmdate( 'Y-m-d 00:00:00', strtotime( '-30 days', $now ) ); break;
		case 'qtd': $qm = ( (int) floor( ( (int) gmdate( 'n', $now ) - 1 ) / 3 ) * 3 ) + 1; $from = gmdate( sprintf( 'Y-%02d-01 00:00:00', $qm ), $now ); break;
		case 'ytd': $from = gmdate( 'Y-01-01 00:00:00', $now ); break;
		case 'custom':
			$from = ! empty( $_GET['rm_from'] ) ? sanitize_text_field( wp_unslash( $_GET['rm_from'] ) ) . ' 00:00:00' : '';
			$to   = ! empty( $_GET['rm_to'] ) ? sanitize_text_field( wp_unslash( $_GET['rm_to'] ) ) . ' 23:59:59' : '';
			break;
		default: $range = 'all';
	}
	$date_sql = '';
	if ( $from ) {
		$date_sql .= $wpdb->prepare( ' AND p.post_date >= %s', $from );
	}
	if ( $to ) {
		$date_sql .= $wpdb->prepare( ' AND p.post_date <= %s', $to );
	}

	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type='lead' AND p.post_status NOT IN ('trash','auto-draft')" . $date_sql ); // phpcs:ignore WordPress.DB.PreparedSQL

	$metaq = function ( $key ) use ( $wpdb, $date_sql ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT m.meta_value s, COUNT(*) c FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_type='lead' AND p.post_status NOT IN ('trash','auto-draft')" . $date_sql . "
			 GROUP BY m.meta_value ORDER BY c DESC", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out = array();
		foreach ( $rows as $r ) {
			$out[ $r->s ] = (int) $r->c;
		}
		return $out;
	};
	$sc           = $metaq( '_lead_status' );
	$sc['logged'] = ( isset( $sc['logged'] ) ? $sc['logged'] : 0 ) + max( 0, $total - array_sum( $sc ) );
	$src          = $metaq( '_lead_attr_source' );

	$this_month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='lead' AND post_status NOT IN ('trash','auto-draft') AND post_date>=%s", gmdate( 'Y-m-01 00:00:00', $now ) ) );
	$last_month = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='lead' AND post_status NOT IN ('trash','auto-draft') AND post_date>=%s AND post_date<%s", gmdate( 'Y-m-01 00:00:00', strtotime( 'first day of last month', $now ) ), gmdate( 'Y-m-01 00:00:00', $now ) ) );
	$week       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='lead' AND post_status NOT IN ('trash','auto-draft') AND post_date>=%s", gmdate( 'Y-m-d 00:00:00', strtotime( '-7 days', $now ) ) ) );

	$won      = isset( $sc['won'] ) ? $sc['won'] : 0;
	$open     = ( isset( $sc['contacted'] ) ? $sc['contacted'] : 0 ) + ( isset( $sc['quoted'] ) ? $sc['quoted'] : 0 );
	$rate     = $total > 0 ? round( $won / $total * 100, 1 ) : 0;
	$delta    = $this_month - $last_month;
	$goal     = (int) apply_filters( 'ricoman_leads_monthly_goal', (int) get_option( 'ricoman_leads_goal', 50 ) );
	$goal     = max( 1, $goal );
	$goal_pct = min( 100, round( $this_month / $goal * 100 ) );

	$range_labels = array(
		'all' => __( 'All time', 'ricoman' ),
		'mtd' => __( 'This month', 'ricoman' ),
		'30d' => __( 'Last 30 days', 'ricoman' ),
		'qtd' => __( 'This quarter', 'ricoman' ),
		'ytd' => __( 'This year', 'ricoman' ),
		'custom' => __( 'Custom', 'ricoman' ),
	);
	$range_label = isset( $range_labels[ $range ] ) ? $range_labels[ $range ] : $range_labels['all'];

	$trend = $delta > 0 ? '<span class="rm-up">▲ ' . (int) $delta . '</span>' : ( $delta < 0 ? '<span class="rm-down">▼ ' . abs( (int) $delta ) . '</span>' : '<span class="rm-flat">— 0</span>' );

	// Conversion gauge (SVG donut).
	$circ = 2 * M_PI * 52;
	$dash = $circ * ( $rate / 100 );
	?>
	<style>
	.rm-dash{max-width:1200px;margin:18px 20px 40px 0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
	.rm-dash *{box-sizing:border-box}
	.rm-dash-hero{background:linear-gradient(120deg,#4f46e5,#7c3aed 55%,#a21caf);border-radius:20px;padding:26px 30px;color:#fff;display:flex;justify-content:space-between;align-items:center;gap:24px;box-shadow:0 16px 40px rgba(79,70,229,.28)}
	.rm-dash-hero h1,.rm-dash-title{color:#fff;font-size:1.7rem;margin:0 0 4px;font-weight:700}
	.rm-dash-hero p{color:rgba(255,255,255,.82);margin:0;font-size:.95rem}
	.rm-gauge{position:relative;flex:0 0 auto;text-align:center}
	.rm-gauge svg{transform:rotate(-90deg)}
	.rm-gauge .rm-gauge-n{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
	.rm-gauge .rm-gauge-n b{font-size:1.5rem;line-height:1}
	.rm-gauge .rm-gauge-n span{font-size:.62rem;letter-spacing:.08em;text-transform:uppercase;opacity:.85}
	.rm-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-top:18px}
	.rm-kpi{background:#fff;border-radius:16px;padding:18px;box-shadow:0 4px 18px rgba(0,0,0,.06);border-top:4px solid #4f46e5;position:relative}
	.rm-kpi .rm-kpi-ic{font-size:1.2rem}
	.rm-kpi .rm-kpi-v{font-size:2rem;font-weight:800;line-height:1.1;margin:6px 0 2px;color:#16161a}
	.rm-kpi .rm-kpi-l{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:#646970;font-weight:600}
	.rm-kpi.green{border-top-color:#1a7f37}.rm-kpi.amber{border-top-color:#d68100}.rm-kpi.blue{border-top-color:#2271b1}.rm-kpi.pink{border-top-color:#a21caf}
	.rm-up{color:#1a7f37;font-weight:700;font-size:.8rem}.rm-down{color:#b32d2e;font-weight:700;font-size:.8rem}.rm-flat{color:#646970;font-size:.8rem}
	.rm-grid2{display:grid;grid-template-columns:1.4fr 1fr;gap:16px;margin-top:16px}
	.rm-card{background:#fff;border-radius:16px;padding:20px 22px;box-shadow:0 4px 18px rgba(0,0,0,.06)}
	.rm-card h2{font-size:1.05rem;margin:0 0 14px;color:#16161a}
	.rm-bar-row{display:flex;align-items:center;gap:12px;margin:10px 0}
	.rm-bar-row .rm-bl{flex:0 0 120px;font-size:.85rem;font-weight:600;color:#16161a;display:flex;align-items:center;gap:7px}
	.rm-bar-row .rm-dot{width:9px;height:9px;border-radius:50%;display:inline-block}
	.rm-bar-track{flex:1;background:#eef0f4;border-radius:999px;height:14px;overflow:hidden}
	.rm-bar-fill{height:100%;border-radius:999px;transition:width 1s ease}
	.rm-bar-row .rm-bn{flex:0 0 46px;text-align:right;font-weight:700;font-size:.9rem}
	.rm-goal{margin-top:16px;background:linear-gradient(120deg,#0f172a,#1e293b);color:#fff;border-radius:16px;padding:20px 22px}
	.rm-goal h2{color:#fff}
	.rm-goal-track{background:rgba(255,255,255,.15);border-radius:999px;height:22px;overflow:hidden;margin-top:6px}
	.rm-goal-fill{height:100%;border-radius:999px;background:linear-gradient(90deg,#22c55e,#86efac);display:flex;align-items:center;justify-content:flex-end;padding-right:10px;color:#06310f;font-weight:800;font-size:.75rem;transition:width 1.1s ease}
	.rm-recent{margin-top:16px}
	.rm-recent table{width:100%;border-collapse:collapse}
	.rm-recent td{padding:11px 6px;border-top:1px solid #eef0f4;font-size:.88rem}
	.rm-badge{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;color:#fff}
	.rm-daterow{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:16px}
	.rm-dr-label{font-weight:600;color:#16161a;margin-right:2px}
	.rm-chip{display:inline-block;padding:6px 14px;border-radius:999px;background:#fff;border:1px solid #dcdde1;color:#16161a;text-decoration:none;font-size:.82rem;font-weight:600}
	.rm-chip:hover{border-color:#4f46e5;color:#4f46e5}
	.rm-chip.on{background:#4f46e5;border-color:#4f46e5;color:#fff}
	.rm-dr-custom{display:inline-flex;align-items:center;gap:6px;margin-left:6px}
	.rm-dr-custom input[type=date]{padding:4px 8px;border-radius:8px;border:1px solid #dcdde1}
	@media(max-width:1100px){.rm-kpis{grid-template-columns:repeat(2,1fr)}.rm-grid2{grid-template-columns:1fr}.rm-dash-hero{flex-direction:column;align-items:flex-start}}
	</style>
	<div class="rm-dash">
		<div class="rm-dash-hero">
			<div>
				<div class="rm-dash-title">🚀 <?php esc_html_e( 'Leads', 'ricoman' ); ?></div>
				<p><?php echo esc_html( sprintf( __( '%1$s · %2$d leads · %3$d won · %4$s%% conversion', 'ricoman' ), $range_label, $total, $won, $rate ) ); ?></p>
			</div>
			<div class="rm-gauge">
				<svg width="128" height="128"><circle cx="64" cy="64" r="52" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="12"/>
				<circle cx="64" cy="64" r="52" fill="none" stroke="#fff" stroke-width="12" stroke-linecap="round" stroke-dasharray="<?php echo esc_attr( round( $dash, 1 ) . ' ' . round( $circ, 1 ) ); ?>"/></svg>
				<div class="rm-gauge-n"><b><?php echo esc_html( $rate ); ?>%</b><span><?php esc_html_e( 'Won', 'ricoman' ); ?></span></div>
			</div>
		</div>

		<?php $base_url = admin_url( 'edit.php?post_type=lead' ); ?>
		<div class="rm-daterow">
			<span class="rm-dr-label">📅 <?php esc_html_e( 'Period:', 'ricoman' ); ?></span>
			<?php foreach ( array( 'all', 'mtd', '30d', 'qtd', 'ytd' ) as $rk ) : ?>
				<a class="rm-chip<?php echo $range === $rk ? ' on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'rm_range', $rk, $base_url ) ); ?>"><?php echo esc_html( $range_labels[ $rk ] ); ?></a>
			<?php endforeach; ?>
			<form method="get" class="rm-dr-custom">
				<input type="hidden" name="post_type" value="lead">
				<input type="hidden" name="rm_range" value="custom">
				<input type="date" name="rm_from" value="<?php echo esc_attr( isset( $_GET['rm_from'] ) ? sanitize_text_field( wp_unslash( $_GET['rm_from'] ) ) : '' ); ?>">
				<span>–</span>
				<input type="date" name="rm_to" value="<?php echo esc_attr( isset( $_GET['rm_to'] ) ? sanitize_text_field( wp_unslash( $_GET['rm_to'] ) ) : '' ); ?>">
				<button class="button button-small"><?php esc_html_e( 'Apply', 'ricoman' ); ?></button>
			</form>
		</div>

		<div class="rm-kpis">
			<div class="rm-kpi blue"><div class="rm-kpi-ic">📥</div><div class="rm-kpi-v"><?php echo esc_html( number_format_i18n( $total ) ); ?></div><div class="rm-kpi-l"><?php echo esc_html( __( 'Leads', 'ricoman' ) . ' · ' . $range_label ); ?></div></div>
			<div class="rm-kpi"><div class="rm-kpi-ic">📆</div><div class="rm-kpi-v"><?php echo esc_html( $this_month ); ?></div><div class="rm-kpi-l"><?php esc_html_e( 'This month', 'ricoman' ); ?> <?php echo $trend; // phpcs:ignore ?></div></div>
			<div class="rm-kpi amber"><div class="rm-kpi-ic">🔥</div><div class="rm-kpi-v"><?php echo esc_html( $week ); ?></div><div class="rm-kpi-l"><?php esc_html_e( 'Last 7 days', 'ricoman' ); ?></div></div>
			<div class="rm-kpi pink"><div class="rm-kpi-ic">🤝</div><div class="rm-kpi-v"><?php echo esc_html( $open ); ?></div><div class="rm-kpi-l"><?php esc_html_e( 'In pipeline', 'ricoman' ); ?></div></div>
			<div class="rm-kpi green"><div class="rm-kpi-ic">🏆</div><div class="rm-kpi-v"><?php echo esc_html( $won ); ?></div><div class="rm-kpi-l"><?php esc_html_e( 'Won', 'ricoman' ); ?></div></div>
		</div>

		<div class="rm-grid2">
			<div class="rm-card">
				<h2><?php esc_html_e( 'Pipeline', 'ricoman' ); ?></h2>
				<?php
				$max = max( 1, max( array_values( $sc ) ) );
				foreach ( $statuses as $k => $info ) {
					$n = isset( $sc[ $k ] ) ? $sc[ $k ] : 0;
					$w = round( $n / $max * 100 );
					echo '<div class="rm-bar-row"><span class="rm-bl"><span class="rm-dot" style="background:' . esc_attr( $info[1] ) . '"></span>' . esc_html( $info[0] ) . '</span>'
						. '<span class="rm-bar-track"><span class="rm-bar-fill" style="width:' . (int) $w . '%;background:' . esc_attr( $info[1] ) . '"></span></span>'
						. '<span class="rm-bn">' . (int) $n . '</span></div>';
				}
				?>
			</div>
			<div class="rm-card">
				<h2><?php esc_html_e( 'Where leads come from', 'ricoman' ); ?></h2>
				<?php
				$palette = array( 'Organic' => '#1a7f37', 'Direct' => '#646970', 'PPC' => '#d68100', 'Social' => '#a21caf', 'Referral' => '#2271b1' );
				// Always show the standard channels (even at 0), plus any campaign sources.
				$src_display = array();
				foreach ( array_keys( $palette ) as $chan ) {
					$src_display[ $chan ] = isset( $src[ $chan ] ) ? $src[ $chan ] : 0;
				}
				foreach ( $src as $s => $n ) {
					if ( ! isset( $src_display[ $s ] ) ) {
						$src_display[ $s ] = $n;
					}
				}
				$smax    = max( 1, max( array_values( $src_display ) ) );
				$has_any = array_sum( $src_display ) > 0;
				foreach ( $src_display as $s => $n ) {
					$key = preg_replace( '/\s*·.*/', '', $s );
					$col = isset( $palette[ $key ] ) ? $palette[ $key ] : '#7c3aed';
					$w   = round( $n / $smax * 100 );
					echo '<div class="rm-bar-row"><span class="rm-bl" title="' . esc_attr( $s ) . '">' . esc_html( $s ) . '</span>'
						. '<span class="rm-bar-track"><span class="rm-bar-fill" style="width:' . (int) $w . '%;background:' . esc_attr( $col ) . '"></span></span>'
						. '<span class="rm-bn">' . (int) $n . '</span></div>';
				}
				if ( ! $has_any ) {
					echo '<p style="color:#646970;margin-top:10px;font-size:.82rem">' . esc_html__( 'Channels fill in as new leads arrive (existing leads predate source tracking).', 'ricoman' ) . '</p>';
				}
				?>
			</div>
		</div>

		<div class="rm-goal">
			<h2>🎯 <?php echo esc_html( sprintf( __( 'Monthly goal — %1$d of %2$d leads', 'ricoman' ), $this_month, $goal ) ); ?></h2>
			<div class="rm-goal-track"><div class="rm-goal-fill" style="width:<?php echo (int) $goal_pct; ?>%"><?php echo (int) $goal_pct; ?>%</div></div>
			<p style="margin:10px 0 0;color:rgba(255,255,255,.75);font-size:.85rem"><?php echo $goal_pct >= 100 ? esc_html__( '🎉 Smashed it! Goal reached this month.', 'ricoman' ) : esc_html( sprintf( __( '%d to go to hit this month’s target.', 'ricoman' ), max( 0, $goal - $this_month ) ) ); ?></p>
		</div>

		<p style="margin:14px 2px 4px;color:#646970;font-weight:600">↓ <?php esc_html_e( 'All leads', 'ricoman' ); ?></p>
	</div>
	<?php
}

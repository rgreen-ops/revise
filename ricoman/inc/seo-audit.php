<?php
/**
 * Site-wide SEO audit (read-only).
 *
 * Ricoman → SEO Audit: scans the editorial content (pages, news articles,
 * projects) and rolls up the per-post SEO scores into a single report — average
 * score, how many items have issues, and a table of the lowest-scoring items
 * with the specific problems and an edit link. Built for the "check & enhance
 * SEO" roadmap step and for Richard's report. It changes nothing; it only reads
 * the same ricoman_seo_score() the editor panel uses.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Post types worth auditing for editorial SEO, label => type. Filterable. */
function ricoman_seo_audit_types() {
	return apply_filters( 'ricoman_seo_audit_types', array(
		'Pages'    => 'page',
		'News'     => 'news',
		'Projects' => 'project',
	) );
}

/**
 * Gather audit rows. Caps each type so the scan stays fast on large sites.
 *
 * @return array{rows:array<int,array>,summary:array<string,int>}
 */
function ricoman_seo_audit_collect() {
	$rows    = array();
	$per_type = (int) apply_filters( 'ricoman_seo_audit_limit', 300 );

	foreach ( ricoman_seo_audit_types() as $label => $type ) {
		if ( ! post_type_exists( $type ) ) {
			continue;
		}
		$ids = get_posts( array(
			'post_type'      => $type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_type,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		foreach ( $ids as $id ) {
			$r       = ricoman_seo_score( $id );
			$issues  = array();
			foreach ( $r['checks'] as $c ) {
				if ( ! $c['pass'] ) {
					$issues[] = $c['label'];
				}
			}
			$noindex = (bool) get_post_meta( $id, '_ricoman_seo_noindex', true );
			$rows[]  = array(
				'id'      => $id,
				'type'    => $label,
				'title'   => get_the_title( $id ),
				'score'   => $r['score'],
				'issues'  => $issues,
				'noindex' => $noindex,
			);
		}
	}

	// Lowest score first so the work-list is at the top.
	usort( $rows, function ( $a, $b ) {
		return $a['score'] - $b['score'];
	} );

	$sum = array( 'count' => count( $rows ), 'total' => 0, 'poor' => 0, 'no_title' => 0, 'no_desc' => 0, 'no_img' => 0, 'noindex' => 0 );
	foreach ( $rows as $row ) {
		$sum['total'] += $row['score'];
		if ( $row['score'] < 70 ) {
			$sum['poor']++;
		}
		if ( '' === trim( (string) get_post_meta( $row['id'], '_ricoman_seo_title', true ) ) ) {
			$sum['no_title']++;
		}
		if ( '' === trim( (string) get_post_meta( $row['id'], '_ricoman_seo_desc', true ) ) ) {
			$sum['no_desc']++;
		}
		if ( in_array( __( 'Featured / social image is set', 'ricoman' ), $row['issues'], true ) ) {
			$sum['no_img']++;
		}
		if ( $row['noindex'] ) {
			$sum['noindex']++;
		}
	}
	$sum['avg'] = $sum['count'] ? (int) round( $sum['total'] / $sum['count'] ) : 0;

	return array( 'rows' => $rows, 'summary' => $sum );
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'SEO Audit', 'ricoman' ),
		__( 'SEO Audit', 'ricoman' ),
		'edit_posts',
		'ricoman-seo-audit',
		'ricoman_render_seo_audit'
	);
}, 30 );

function ricoman_render_seo_audit() {
	$data = ricoman_seo_audit_collect();
	$s    = $data['summary'];
	$rows = $data['rows'];

	$col = function ( $score ) {
		if ( $score >= 80 ) {
			return '#1a7f37';
		}
		return $score >= 60 ? '#996800' : '#b32d2e';
	};

	echo '<div class="wrap"><h1>' . esc_html__( 'SEO Audit', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'A read-only health check of your pages, news articles and projects, using the same scoring as the editor’s SEO panel. Fix the lowest-scoring items first. (Products are templated and scored separately.)', 'ricoman' ) . '</p>';

	// Summary cards.
	$card = function ( $big, $label, $color = '#1d2327' ) {
		return '<div style="flex:1;min-width:150px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px">'
			. '<div style="font-size:1.9rem;font-weight:700;color:' . esc_attr( $color ) . '">' . esc_html( (string) $big ) . '</div>'
			. '<div style="color:#646970">' . esc_html( $label ) . '</div></div>';
	};
	echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0 22px">';
	echo $card( $s['count'], __( 'Items scanned', 'ricoman' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $card( $s['avg'] . '%', __( 'Average score', 'ricoman' ), $col( $s['avg'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $card( $s['poor'], __( 'Below 70%', 'ricoman' ), $s['poor'] ? '#b32d2e' : '#1a7f37' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $card( $s['no_desc'], __( 'Missing meta description', 'ricoman' ), $s['no_desc'] ? '#996800' : '#1a7f37' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $card( $s['no_img'], __( 'Missing social image', 'ricoman' ), $s['no_img'] ? '#996800' : '#1a7f37' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo $card( $s['noindex'], __( 'Set to noindex', 'ricoman' ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</div>';

	if ( $s['no_desc'] ) {
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=ricoman-page-seo' ) ) . '">' . esc_html__( 'Seed starter SEO copy (Page SEO) →', 'ricoman' ) . '</a></p>';
	}

	if ( ! $rows ) {
		echo '<p>' . esc_html__( 'No published content to audit yet.', 'ricoman' ) . '</p></div>';
		return;
	}

	echo '<table class="widefat striped" style="margin-top:8px"><thead><tr>'
		. '<th style="width:60px">' . esc_html__( 'Score', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Title', 'ricoman' ) . '</th>'
		. '<th style="width:90px">' . esc_html__( 'Type', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Issues', 'ricoman' ) . '</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$edit = get_edit_post_link( $row['id'] );
		echo '<tr>';
		echo '<td style="font-weight:700;color:' . esc_attr( $col( $row['score'] ) ) . '">' . esc_html( (string) $row['score'] ) . '</td>';
		echo '<td><a href="' . esc_url( $edit ) . '">' . esc_html( $row['title'] ? $row['title'] : __( '(no title)', 'ricoman' ) ) . '</a>'
			. ( $row['noindex'] ? ' <span style="color:#b32d2e;font-size:.8em">' . esc_html__( '(noindex)', 'ricoman' ) . '</span>' : '' ) . '</td>';
		echo '<td>' . esc_html( $row['type'] ) . '</td>';
		echo '<td style="color:#646970">' . ( $row['issues'] ? esc_html( implode( ' · ', $row['issues'] ) ) : '<span style="color:#1a7f37">' . esc_html__( 'No issues', 'ricoman' ) . '</span>' ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

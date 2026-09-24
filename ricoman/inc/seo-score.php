<?php
/**
 * SEO scoring + back-office "SEO & Speed" dashboard.
 *
 *  - ricoman_seo_score(): a 0–100 score for any post from its content + SEO
 *    fields (title, description, focus keyphrase, image, headings, links).
 *  - Live score panel + social preview inside the post editor (assets/js).
 *  - A "SEO health" dashboard widget (lowest-scoring pages, site average).
 *  - Tools → "SEO & Speed": pulls Google PageSpeed Insights scores for key
 *    pages (performance, SEO, accessibility, best practices), cached.
 *
 * Loaded after inc/seo.php; reuses ricoman_seo_post_types() / ricoman_seo_opt().
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Score a post 0–100 against on-page SEO best practices.
 *
 * @param int $post_id Post ID.
 * @return array{score:int,checks:array<int,array{label:string,pass:bool,weight:int}>}
 */
function ricoman_seo_score( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array( 'score' => 0, 'checks' => array() );
	}

	$title = (string) get_post_meta( $post_id, '_ricoman_seo_title', true );
	if ( '' === $title ) {
		$title = get_the_title( $post_id );
	}
	$desc = (string) get_post_meta( $post_id, '_ricoman_seo_desc', true );
	if ( '' === $desc && has_excerpt( $post_id ) ) {
		$desc = get_the_excerpt( $post_id );
	}
	$focus = trim( (string) get_post_meta( $post_id, '_ricoman_seo_focus', true ) );

	$raw   = (string) $post->post_content;
	$text  = wp_strip_all_tags( strip_shortcodes( $raw ) );
	$words = str_word_count( $text );

	$tlen = function_exists( 'mb_strlen' ) ? mb_strlen( $title ) : strlen( $title );
	$dlen = function_exists( 'mb_strlen' ) ? mb_strlen( $desc ) : strlen( $desc );

	$has_img  = has_post_thumbnail( $post_id ) || (bool) preg_match( '/<img|wp:image/i', $raw );
	$has_head = (bool) preg_match( '/<h[23]|wp:heading/i', $raw );
	$has_link = (bool) preg_match( '/<a\s/i', $raw );

	$kw         = ( '' !== $focus );
	$in_title   = $kw && false !== stripos( $title, $focus );
	$in_desc    = $kw && false !== stripos( $desc, $focus );
	$in_content = $kw && false !== stripos( $text, $focus );

	$checks = array(
		array( 'label' => __( 'Title is 40–60 characters', 'ricoman' ), 'pass' => ( $tlen >= 40 && $tlen <= 60 ), 'weight' => 12 ),
		array( 'label' => __( 'Meta description is 120–160 characters', 'ricoman' ), 'pass' => ( $dlen >= 120 && $dlen <= 160 ), 'weight' => 14 ),
		array( 'label' => __( 'Featured / social image is set', 'ricoman' ), 'pass' => $has_img, 'weight' => 10 ),
		array( 'label' => __( 'At least 300 words of content', 'ricoman' ), 'pass' => ( $words >= 300 ), 'weight' => 12 ),
		array( 'label' => __( 'Has a subheading (H2/H3)', 'ricoman' ), 'pass' => $has_head, 'weight' => 8 ),
		array( 'label' => __( 'Contains at least one link', 'ricoman' ), 'pass' => $has_link, 'weight' => 6 ),
		array( 'label' => __( 'Focus keyphrase is set', 'ricoman' ), 'pass' => $kw, 'weight' => 8 ),
	);
	if ( $kw ) {
		$checks[] = array( 'label' => __( 'Keyphrase in the SEO title', 'ricoman' ), 'pass' => $in_title, 'weight' => 12 );
		$checks[] = array( 'label' => __( 'Keyphrase in the meta description', 'ricoman' ), 'pass' => $in_desc, 'weight' => 8 );
		$checks[] = array( 'label' => __( 'Keyphrase used in the content', 'ricoman' ), 'pass' => $in_content, 'weight' => 6 );
	}

	$total = 0;
	$got   = 0;
	foreach ( $checks as $c ) {
		$total += $c['weight'];
		if ( $c['pass'] ) {
			$got += $c['weight'];
		}
	}
	$score = $total ? (int) round( $got / $total * 100 ) : 0;

	return array( 'score' => $score, 'checks' => $checks );
}

/* ---- Live score panel in the editor ---- */
add_action( 'enqueue_block_editor_assets', function () {
	if ( ricoman_seo_plugin_active() ) {
		return;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || ! in_array( $screen->post_type, ricoman_seo_post_types(), true ) ) {
		return;
	}
	wp_enqueue_script(
		'ricoman-seo-editor',
		get_theme_file_uri( 'assets/js/seo-editor.js' ),
		array( 'wp-data', 'wp-dom-ready' ),
		RICOMAN_VERSION,
		true
	);
} );

/* ---- "SEO health" dashboard widget ---- */
add_action( 'wp_dashboard_setup', function () {
	if ( ricoman_seo_plugin_active() || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	wp_add_dashboard_widget( 'ricoman_seo_health', __( 'SEO health (Ricoman)', 'ricoman' ), 'ricoman_seo_dashboard_widget' );
} );

function ricoman_seo_dashboard_widget() {
	$q = new WP_Query( array(
		'post_type'      => ricoman_seo_post_types(),
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		'no_found_rows'  => true,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	) );

	if ( ! $q->have_posts() ) {
		echo '<p>' . esc_html__( 'No published content to score yet.', 'ricoman' ) . '</p>';
		return;
	}

	$rows = array();
	$sum  = 0;
	foreach ( $q->posts as $p ) {
		$r      = ricoman_seo_score( $p->ID );
		$sum   += $r['score'];
		$rows[] = array( 'score' => $r['score'], 'post' => $p );
	}
	wp_reset_postdata();

	$avg = (int) round( $sum / count( $rows ) );
	usort( $rows, function ( $a, $b ) {
		return $a['score'] - $b['score'];
	} );

	$col = function ( $s ) {
		return $s >= 80 ? '#008a20' : ( $s >= 50 ? '#dba617' : '#b32d2e' );
	};

	echo '<p style="font-size:13px;margin:0 0 10px">' . esc_html__( 'Site average', 'ricoman' )
		. ' <strong style="color:' . esc_attr( $col( $avg ) ) . '">' . esc_html( (string) $avg ) . '/100</strong> '
		. esc_html( sprintf( /* translators: %d: number of pages. */ __( 'across %d pages.', 'ricoman' ), count( $rows ) ) ) . '</p>';

	echo '<table class="widefat striped" style="border:0"><tbody>';
	$shown = 0;
	foreach ( $rows as $row ) {
		if ( $shown++ >= 8 ) {
			break;
		}
		$p = $row['post'];
		echo '<tr><td style="font-weight:700;color:' . esc_attr( $col( $row['score'] ) ) . ';width:48px">' . esc_html( (string) $row['score'] ) . '</td>'
			. '<td><a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></td></tr>';
	}
	echo '</tbody></table>';
	echo '<p style="margin:10px 0 0"><a href="' . esc_url( admin_url( 'tools.php?page=ricoman-speed' ) ) . '">' . esc_html__( 'Open SEO & Speed dashboard →', 'ricoman' ) . '</a></p>';
}

/* ---- Tools → SEO & Speed (PageSpeed Insights) ---- */
add_action( 'admin_menu', function () {
	add_management_page(
		__( 'SEO & Speed', 'ricoman' ),
		__( 'SEO & Speed', 'ricoman' ),
		'manage_options',
		'ricoman-speed',
		'ricoman_speed_page'
	);
} );

function ricoman_speed_urls() {
	$urls = array( __( 'Home', 'ricoman' ) => home_url( '/' ) );
	$pa   = get_post_type_archive_link( 'product' );
	if ( $pa ) {
		$urls[ __( 'Products', 'ricoman' ) ] = $pa;
	}
	$pj = get_post_type_archive_link( 'project' );
	if ( $pj ) {
		$urls[ __( 'Projects', 'ricoman' ) ] = $pj;
	}
	$sample = get_posts( array( 'post_type' => 'product', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'publish' ) );
	if ( $sample ) {
		$urls[ __( 'Sample product', 'ricoman' ) ] = get_permalink( $sample[0] );
	}
	return apply_filters( 'ricoman_speed_urls', $urls );
}

function ricoman_run_psi() {
	$key = ricoman_seo_opt( 'psi_key' );
	$out = array( 'ts' => time(), 'rows' => array() );

	foreach ( ricoman_speed_urls() as $label => $url ) {
		// Build manually because the PSI API repeats the "category" parameter.
		$api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile'
			. '&category=performance&category=seo&category=accessibility&category=best-practices'
			. '&url=' . rawurlencode( $url );
		if ( $key ) {
			$api .= '&key=' . rawurlencode( $key );
		}

		$resp = wp_remote_get( $api, array( 'timeout' => 60 ) );
		if ( is_wp_error( $resp ) ) {
			$out['rows'][ $label ] = array( 'url' => $url, 'error' => $resp->get_error_message() );
			continue;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $body['lighthouseResult']['categories'] ) ) {
			$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'No data returned (quota or unreachable URL).', 'ricoman' );
			$out['rows'][ $label ] = array( 'url' => $url, 'error' => $msg );
			continue;
		}
		$cats = $body['lighthouseResult']['categories'];
		$pick = function ( $k ) use ( $cats ) {
			return isset( $cats[ $k ]['score'] ) ? (int) round( $cats[ $k ]['score'] * 100 ) : null;
		};
		$out['rows'][ $label ] = array(
			'url'    => $url,
			'scores' => array(
				'performance'    => $pick( 'performance' ),
				'seo'            => $pick( 'seo' ),
				'accessibility'  => $pick( 'accessibility' ),
				'best-practices' => $pick( 'best-practices' ),
			),
		);
	}
	set_transient( 'ricoman_psi_scores', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

function ricoman_speed_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$data = get_transient( 'ricoman_psi_scores' );
	if ( isset( $_POST['ricoman_run_psi'] ) && check_admin_referer( 'ricoman_psi' ) ) {
		$data = ricoman_run_psi();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'PageSpeed scores refreshed.', 'ricoman' ) . '</p></div>';
	}

	$badge = function ( $v ) {
		if ( null === $v ) {
			return '<span style="color:#646970">—</span>';
		}
		$c = $v >= 90 ? '#008a20' : ( $v >= 50 ? '#dba617' : '#b32d2e' );
		return '<strong style="color:' . esc_attr( $c ) . '">' . esc_html( (string) $v ) . '</strong>';
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SEO & Speed', 'ricoman' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Live Google PageSpeed Insights scores for your key pages. Run from a public URL (a live or staging site, not localhost). A PageSpeed API key (Settings → Ricoman SEO) avoids rate limits.', 'ricoman' ); ?></p>

		<form method="post" style="margin:14px 0">
			<?php wp_nonce_field( 'ricoman_psi' ); ?>
			<button type="submit" name="ricoman_run_psi" value="1" class="button button-primary"><?php esc_html_e( 'Run / refresh scores', 'ricoman' ); ?></button>
			<?php if ( is_array( $data ) && ! empty( $data['ts'] ) ) : ?>
				<span class="description" style="margin-left:10px"><?php echo esc_html( sprintf( /* translators: %s: human time diff. */ __( 'Last run %s ago.', 'ricoman' ), human_time_diff( $data['ts'] ) ) ); ?></span>
			<?php endif; ?>
		</form>

		<table class="widefat striped">
			<thead><tr>
				<th><?php esc_html_e( 'Page', 'ricoman' ); ?></th>
				<th><?php esc_html_e( 'Performance', 'ricoman' ); ?></th>
				<th><?php esc_html_e( 'SEO', 'ricoman' ); ?></th>
				<th><?php esc_html_e( 'Accessibility', 'ricoman' ); ?></th>
				<th><?php esc_html_e( 'Best practices', 'ricoman' ); ?></th>
				<th></th>
			</tr></thead>
			<tbody>
			<?php if ( is_array( $data ) && ! empty( $data['rows'] ) ) : ?>
				<?php foreach ( $data['rows'] as $label => $row ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $label ); ?></strong></td>
						<?php if ( isset( $row['error'] ) ) : ?>
							<td colspan="4" style="color:#b32d2e"><?php echo esc_html( $row['error'] ); ?></td>
						<?php else : ?>
							<td><?php echo $badge( $row['scores']['performance'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td><?php echo $badge( $row['scores']['seo'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td><?php echo $badge( $row['scores']['accessibility'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
							<td><?php echo $badge( $row['scores']['best-practices'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
						<?php endif; ?>
						<td><a href="<?php echo esc_url( 'https://pagespeed.web.dev/report?url=' . rawurlencode( $row['url'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Full report ↗', 'ricoman' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No scores yet — click “Run / refresh scores”.', 'ricoman' ); ?></td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

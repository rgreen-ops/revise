<?php
/**
 * Core Web Vitals — Old vs New tracker.
 *
 * Runs Google PageSpeed Insights against the OLD live ricoman.com page and the
 * matching NEW (staging) page, side by side, and shows the before/after for the
 * Core Web Vitals + performance score. Built for Richard's marketing report on the
 * new website ("faster than the old site") — and as an ongoing regression check.
 *
 * Reuses the PageSpeed API key from Settings → Ricoman SEO (ricoman_seo_opt) and
 * the same v5 endpoint as the SEO & Speed dashboard. Each run snapshots the result
 * (option ricoman_cwv_last) and appends to a short history (ricoman_cwv_history) so
 * improvement over time is visible.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The page pairs to compare: label => [ old (live ricoman.com), new (this site) ]. Editable + filterable. */
function ricoman_cwv_pairs() {
	$saved = get_option( 'ricoman_cwv_pairs', null );
	if ( is_array( $saved ) && $saved ) {
		return apply_filters( 'ricoman_cwv_pairs', $saved );
	}
	$new_products = get_post_type_archive_link( 'product' );
	$new_projects = get_post_type_archive_link( 'project' );
	$pairs = array(
		array( 'label' => 'Home',     'old' => 'https://ricoman.com/',          'new' => home_url( '/' ) ),
		array( 'label' => 'Products', 'old' => 'https://ricoman.com/products/',  'new' => $new_products ? $new_products : home_url( '/products/' ) ),
		array( 'label' => 'Projects', 'old' => 'https://ricoman.com/projects/',  'new' => $new_projects ? $new_projects : home_url( '/projects/' ) ),
	);
	return apply_filters( 'ricoman_cwv_pairs', $pairs );
}

/** Run PageSpeed Insights for one URL (mobile) and return the CWV lab + field metrics. */
function ricoman_cwv_fetch( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'url' => $url, 'error' => __( 'No URL.', 'ricoman' ) );
	}
	$key = function_exists( 'ricoman_seo_opt' ) ? ricoman_seo_opt( 'psi_key' ) : '';
	$api = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile'
		. '&category=performance&url=' . rawurlencode( $url );
	if ( $key ) {
		$api .= '&key=' . rawurlencode( $key );
	}
	$resp = wp_remote_get( $api, array( 'timeout' => 60 ) );
	if ( is_wp_error( $resp ) ) {
		return array( 'url' => $url, 'error' => $resp->get_error_message() );
	}
	$body = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( empty( $body['lighthouseResult'] ) ) {
		$msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'No data (quota or unreachable URL).', 'ricoman' );
		return array( 'url' => $url, 'error' => $msg );
	}
	$lh    = $body['lighthouseResult'];
	$audit = function ( $id ) use ( $lh ) {
		return isset( $lh['audits'][ $id ]['numericValue'] ) ? (float) $lh['audits'][ $id ]['numericValue'] : null;
	};
	$score = isset( $lh['categories']['performance']['score'] ) ? (int) round( $lh['categories']['performance']['score'] * 100 ) : null;

	// Field data (real Chrome users, CrUX) — present for the live old site, usually
	// absent for a fresh staging URL until it gathers traffic.
	$field   = array( 'lcp' => null, 'cls' => null, 'inp' => null, 'overall' => null );
	$metrics = isset( $body['loadingExperience']['metrics'] ) ? $body['loadingExperience']['metrics'] : array();
	if ( isset( $metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'] ) ) {
		$field['lcp'] = (float) $metrics['LARGEST_CONTENTFUL_PAINT_MS']['percentile'];
	}
	if ( isset( $metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] ) ) {
		$field['cls'] = (float) $metrics['CUMULATIVE_LAYOUT_SHIFT_SCORE']['percentile'] / 100;
	}
	if ( isset( $metrics['INTERACTION_TO_NEXT_PAINT']['percentile'] ) ) {
		$field['inp'] = (float) $metrics['INTERACTION_TO_NEXT_PAINT']['percentile'];
	}
	if ( isset( $body['loadingExperience']['overall_category'] ) ) {
		$field['overall'] = $body['loadingExperience']['overall_category'];
	}

	return array(
		'url'   => $url,
		'score' => $score,
		'lab'   => array(
			'lcp' => $audit( 'largest-contentful-paint' ),
			'cls' => $audit( 'cumulative-layout-shift' ),
			'tbt' => $audit( 'total-blocking-time' ),
			'fcp' => $audit( 'first-contentful-paint' ),
			'si'  => $audit( 'speed-index' ),
		),
		'field' => $field,
	);
}

/** Run the full old-vs-new comparison, snapshot it, and append to history. */
function ricoman_cwv_run() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- best-effort.
	}
	$rows = array();
	foreach ( ricoman_cwv_pairs() as $pair ) {
		$label = isset( $pair['label'] ) ? $pair['label'] : ( isset( $pair['new'] ) ? $pair['new'] : '' );
		$rows[] = array(
			'label' => $label,
			'old'   => ricoman_cwv_fetch( isset( $pair['old'] ) ? $pair['old'] : '' ),
			'new'   => ricoman_cwv_fetch( isset( $pair['new'] ) ? $pair['new'] : '' ),
		);
	}
	$out = array( 'ts' => time(), 'rows' => $rows );
	update_option( 'ricoman_cwv_last', $out, false );

	// Trim history to the new-site performance score per page over time.
	$hist = get_option( 'ricoman_cwv_history', array() );
	if ( ! is_array( $hist ) ) {
		$hist = array();
	}
	$snap = array( 'ts' => $out['ts'], 'scores' => array() );
	foreach ( $rows as $r ) {
		$snap['scores'][ $r['label'] ] = isset( $r['new']['score'] ) ? $r['new']['score'] : null;
	}
	$hist[] = $snap;
	if ( count( $hist ) > 12 ) {
		$hist = array_slice( $hist, -12 );
	}
	update_option( 'ricoman_cwv_history', $hist, false );

	return $out;
}

/* ----------------------------------------------------------- formatting bits */

/** The metrics shown per page, in order. lower_better = improvement is a decrease. */
function ricoman_cwv_metrics() {
	return array(
		'score' => array( 'label' => __( 'Performance score', 'ricoman' ), 'fmt' => 'score', 'lower_better' => false ),
		'lcp'   => array( 'label' => __( 'LCP (largest paint)', 'ricoman' ), 'fmt' => 'sec', 'lower_better' => true ),
		'cls'   => array( 'label' => __( 'CLS (layout shift)', 'ricoman' ), 'fmt' => 'cls', 'lower_better' => true ),
		'tbt'   => array( 'label' => __( 'TBT (blocking time)', 'ricoman' ), 'fmt' => 'ms', 'lower_better' => true ),
	);
}

/** Pull a metric value from a fetch result by key (score is top-level; rest are lab). */
function ricoman_cwv_value( $res, $key ) {
	if ( ! is_array( $res ) || isset( $res['error'] ) ) {
		return null;
	}
	if ( 'score' === $key ) {
		return isset( $res['score'] ) ? $res['score'] : null;
	}
	return isset( $res['lab'][ $key ] ) ? $res['lab'][ $key ] : null;
}

/** Human-format a metric value. */
function ricoman_cwv_fmt( $val, $fmt ) {
	if ( null === $val ) {
		return '—';
	}
	switch ( $fmt ) {
		case 'score':
			return (string) (int) $val;
		case 'sec':
			return number_format( $val / 1000, $val < 1000 ? 2 : 1 ) . ' s';
		case 'ms':
			return number_format( $val ) . ' ms';
		case 'cls':
			return number_format( $val, 3 );
	}
	return (string) $val;
}

/** Delta between old and new → ['text'=>'−71%', 'color'=>'#008a20', 'better'=>true]. */
function ricoman_cwv_delta( $old, $new, $lower_better ) {
	if ( null === $old || null === $new ) {
		return array( 'text' => '', 'color' => '#646970', 'better' => null );
	}
	$diff   = $new - $old;
	$better = $lower_better ? ( $diff < 0 ) : ( $diff > 0 );
	$worse  = $lower_better ? ( $diff > 0 ) : ( $diff < 0 );
	$color  = $better ? '#008a20' : ( $worse ? '#b32d2e' : '#646970' );
	if ( abs( $diff ) < 0.0001 ) {
		return array( 'text' => __( 'no change', 'ricoman' ), 'color' => $color, 'better' => null );
	}
	// Percentage change (guard divide-by-zero); for score show absolute points.
	if ( $old > 0 ) {
		$pct  = round( ( $diff / $old ) * 100 );
		$text = ( $diff > 0 ? '+' : '−' ) . abs( $pct ) . '%';
	} else {
		$text = ( $diff > 0 ? '+' : '−' ) . ( abs( $diff ) >= 1 ? number_format( abs( $diff ) ) : number_format( abs( $diff ), 3 ) );
	}
	return array( 'text' => ( $better ? '▼ ' : ( $worse ? '▲ ' : '' ) ) . $text, 'color' => $color, 'better' => $better );
}

/** Field-data Core Web Vitals badge (FAST / AVERAGE / SLOW / no data). */
function ricoman_cwv_overall_badge( $cat ) {
	if ( ! $cat ) {
		return '<span style="color:#646970">' . esc_html__( 'no field data yet', 'ricoman' ) . '</span>';
	}
	$map = array(
		'FAST'    => array( __( 'Pass (Good)', 'ricoman' ), '#008a20' ),
		'AVERAGE' => array( __( 'Needs improvement', 'ricoman' ), '#dba617' ),
		'SLOW'    => array( __( 'Fail (Poor)', 'ricoman' ), '#b32d2e' ),
	);
	$m = isset( $map[ $cat ] ) ? $map[ $cat ] : array( $cat, '#646970' );
	return '<strong style="color:' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</strong>';
}

/* ------------------------------------------------------------------ admin UI */

add_action( 'admin_menu', function () {
	add_management_page(
		__( 'Speed: Old vs New', 'ricoman' ),
		__( 'Speed: Old vs New', 'ricoman' ),
		'manage_options',
		'ricoman-cwv',
		'ricoman_cwv_page'
	);
} );

/** Save the edited page pairs. */
add_action( 'admin_post_ricoman_cwv_pairs', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ricoman_cwv' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$rows = isset( $_POST['p'] ) && is_array( $_POST['p'] ) ? wp_unslash( $_POST['p'] ) : array();
	$out  = array();
	foreach ( $rows as $r ) {
		$old = isset( $r['old'] ) ? esc_url_raw( trim( $r['old'] ) ) : '';
		$new = isset( $r['new'] ) ? esc_url_raw( trim( $r['new'] ) ) : '';
		if ( '' === $old && '' === $new ) {
			continue;
		}
		$out[] = array(
			'label' => isset( $r['label'] ) ? sanitize_text_field( $r['label'] ) : '',
			'old'   => $old,
			'new'   => $new,
		);
	}
	update_option( 'ricoman_cwv_pairs', $out, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-cwv', 'saved' => 1 ), admin_url( 'tools.php' ) ) );
	exit;
} );

/** CSV export of the latest comparison. */
add_action( 'admin_post_ricoman_cwv_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ricoman_cwv_export' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$data = get_option( 'ricoman_cwv_last', array() );
	$rows = isset( $data['rows'] ) ? $data['rows'] : array();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=ricoman-cwv-old-vs-new.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Page', 'Metric', 'Old (ricoman.com)', 'New', 'Change' ) );
	foreach ( $rows as $r ) {
		foreach ( ricoman_cwv_metrics() as $k => $m ) {
			$o = ricoman_cwv_value( $r['old'], $k );
			$n = ricoman_cwv_value( $r['new'], $k );
			$d = ricoman_cwv_delta( $o, $n, $m['lower_better'] );
			fputcsv( $out, array(
				$r['label'],
				$m['label'],
				ricoman_cwv_fmt( $o, $m['fmt'] ),
				ricoman_cwv_fmt( $n, $m['fmt'] ),
				wp_strip_all_tags( str_replace( array( '▼', '▲' ), array( 'better', 'worse' ), $d['text'] ) ),
			) );
		}
	}
	fclose( $out );
	exit;
} );

function ricoman_cwv_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$data = get_option( 'ricoman_cwv_last', array() );
	if ( isset( $_POST['ricoman_run_cwv'] ) && check_admin_referer( 'ricoman_cwv_run' ) ) {
		$data = ricoman_cwv_run();
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Old vs new comparison refreshed.', 'ricoman' ) . '</p></div>';
	}
	$pairs   = ricoman_cwv_pairs();
	$metrics = ricoman_cwv_metrics();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Speed: Old site vs New site', 'ricoman' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Pages saved.', 'ricoman' ); ?></p></div><?php endif; ?>
		<p class="description" style="max-width:820px"><?php esc_html_e( 'Google PageSpeed Insights (mobile) for the OLD live ricoman.com page and the matching NEW page, side by side — the before/after for your marketing report. Lower LCP / CLS / blocking time and a higher score are better. A PageSpeed API key (Settings → Ricoman SEO) avoids rate limits. The new pages must be on a public URL.', 'ricoman' ); ?></p>

		<form method="post" style="margin:14px 0">
			<?php wp_nonce_field( 'ricoman_cwv_run' ); ?>
			<button type="submit" name="ricoman_run_cwv" value="1" class="button button-primary"><?php esc_html_e( 'Run / refresh comparison', 'ricoman' ); ?></button>
			<?php if ( is_array( $data ) && ! empty( $data['ts'] ) ) : ?>
				<span class="description" style="margin-left:10px"><?php echo esc_html( sprintf( /* translators: %s: human time diff. */ __( 'Last run %s ago.', 'ricoman' ), human_time_diff( $data['ts'] ) ) ); ?></span>
				<a class="button" style="margin-left:8px" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_cwv_export' ), 'ricoman_cwv_export' ) ); ?>"><?php esc_html_e( 'Export CSV', 'ricoman' ); ?></a>
			<?php endif; ?>
			<span class="description" style="margin-left:10px"><?php esc_html_e( 'A run calls PageSpeed twice per page and can take a minute.', 'ricoman' ); ?></span>
		</form>

		<?php if ( is_array( $data ) && ! empty( $data['rows'] ) ) : ?>
			<div style="display:grid;gap:16px;max-width:980px">
			<?php foreach ( $data['rows'] as $r ) :
				$err_old = isset( $r['old']['error'] ) ? $r['old']['error'] : '';
				$err_new = isset( $r['new']['error'] ) ? $r['new']['error'] : '';
				?>
				<div style="border:1px solid #dcdce0;border-radius:10px;background:#fff;padding:16px 18px">
					<div style="display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap;margin-bottom:10px">
						<h2 style="margin:0;font-size:16px"><?php echo esc_html( $r['label'] ); ?></h2>
						<span class="description" style="font-size:12px">
							<a href="<?php echo esc_url( $r['old']['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'old ↗', 'ricoman' ); ?></a> ·
							<a href="<?php echo esc_url( $r['new']['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'new ↗', 'ricoman' ); ?></a>
						</span>
					</div>
					<?php if ( $err_old || $err_new ) : ?>
						<p class="description" style="color:#b32d2e;margin:0 0 8px">
							<?php echo $err_old ? esc_html__( 'Old: ', 'ricoman' ) . esc_html( $err_old ) . '  ' : ''; ?>
							<?php echo $err_new ? esc_html__( 'New: ', 'ricoman' ) . esc_html( $err_new ) : ''; ?>
						</p>
					<?php endif; ?>
					<table class="widefat" style="border:0;box-shadow:none">
						<thead><tr>
							<th style="width:38%"><?php esc_html_e( 'Metric', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'Old (ricoman.com)', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'New', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'Change', 'ricoman' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $metrics as $k => $m ) :
							$o = ricoman_cwv_value( $r['old'], $k );
							$n = ricoman_cwv_value( $r['new'], $k );
							$d = ricoman_cwv_delta( $o, $n, $m['lower_better'] );
							?>
							<tr>
								<td><?php echo esc_html( $m['label'] ); ?></td>
								<td><?php echo esc_html( ricoman_cwv_fmt( $o, $m['fmt'] ) ); ?></td>
								<td><strong><?php echo esc_html( ricoman_cwv_fmt( $n, $m['fmt'] ) ); ?></strong></td>
								<td><span style="color:<?php echo esc_attr( $d['color'] ); ?>;font-weight:600"><?php echo esc_html( $d['text'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
						<tr>
							<td><?php esc_html_e( 'Real-user Core Web Vitals', 'ricoman' ); ?></td>
							<td><?php echo wp_kses_post( ricoman_cwv_overall_badge( isset( $r['old']['field']['overall'] ) ? $r['old']['field']['overall'] : null ) ); ?></td>
							<td><?php echo wp_kses_post( ricoman_cwv_overall_badge( isset( $r['new']['field']['overall'] ) ? $r['new']['field']['overall'] : null ) ); ?></td>
							<td></td>
						</tr>
						</tbody>
					</table>
				</div>
			<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'No comparison yet — set your page pairs below and click “Run / refresh comparison”.', 'ricoman' ); ?></p>
		<?php endif; ?>

		<hr style="margin:28px 0 18px">
		<h2><?php esc_html_e( 'Pages to compare', 'ricoman' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Set the OLD live URL and the matching NEW URL for each page. Clear both to remove a row.', 'ricoman' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ricoman_cwv_pairs">
			<?php wp_nonce_field( 'ricoman_cwv' ); ?>
			<table class="widefat striped" style="max-width:980px">
				<thead><tr>
					<th style="width:18%"><?php esc_html_e( 'Label', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'Old URL (ricoman.com)', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'New URL', 'ricoman' ); ?></th>
				</tr></thead>
				<tbody id="rm-cwv-rows">
				<?php
				$rows_out = $pairs;
				$rows_out[] = array( 'label' => '', 'old' => '', 'new' => '' ); // one blank spare.
				foreach ( $rows_out as $i => $p ) :
					?>
					<tr>
						<td><input type="text" name="p[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( isset( $p['label'] ) ? $p['label'] : '' ); ?>" style="width:100%"></td>
						<td><input type="url" name="p[<?php echo (int) $i; ?>][old]" value="<?php echo esc_attr( isset( $p['old'] ) ? $p['old'] : '' ); ?>" style="width:100%" placeholder="https://ricoman.com/…"></td>
						<td><input type="url" name="p[<?php echo (int) $i; ?>][new]" value="<?php echo esc_attr( isset( $p['new'] ) ? $p['new'] : '' ); ?>" style="width:100%" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>…"></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'Save pages', 'ricoman' ) ); ?>
		</form>
	</div>
	<?php
}

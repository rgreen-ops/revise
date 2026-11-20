<?php
/**
 * Text cleanup — repair "mojibake" left in product + variant data by an old CSV
 * import that double-encoded UTF-8 as Windows-1252 (e.g. Ã˜ instead of Ø, Ã—
 * instead of ×, Â° instead of °, plus smart quotes and accents).
 *
 * Ricoman → Clean Import Text: a dry-run scan (preview) + one-click repair.
 * The repair reverses ONE layer of Windows-1252/UTF-8 double-encoding and only
 * accepts the result when re-encoding it exactly reproduces the stored value —
 * a lossless round-trip — so it can never corrupt legitimate text.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ricoman_textclean_cap() {
	return apply_filters( 'ricoman_textclean_cap', 'manage_options' );
}

/**
 * Reverse one layer of Windows-1252 → UTF-8 double-encoding. Returns the repaired
 * string, or the original unchanged when there is nothing to fix or the reversal
 * would not be lossless (safety: it never guesses).
 */
function ricoman_fix_mojibake_text( $s ) {
	$s = (string) $s;
	if ( '' === $s || ! function_exists( 'mb_convert_encoding' ) ) {
		return $s;
	}
	// Fast reject: double-encoding always leaves a Ã / Â / â lead character.
	if ( false === strpos( $s, 'Ã' ) && false === strpos( $s, 'Â' ) && false === strpos( $s, 'â' ) ) {
		return $s;
	}
	$conv = @mb_convert_encoding( $s, 'Windows-1252', 'UTF-8' );
	if ( is_string( $conv ) && '' !== $conv && $conv !== $s
		&& mb_check_encoding( $conv, 'UTF-8' )
		&& @mb_convert_encoding( $conv, 'UTF-8', 'Windows-1252' ) === $s ) {
		return $conv;
	}
	return $s;
}

/**
 * Scan (and optionally repair) mojibake across product + variant-product
 * post_title, post_content and non-serialized meta. Returns stats + samples.
 * Serialized meta is skipped for safety (byte-length prefixes would break).
 */
function ricoman_textclean_run( $apply = false, $max = 4000 ) {
	global $wpdb;
	$types_in = "'product','variant-product'";

	$out = array(
		'meta_changed'    => 0,
		'title_changed'   => 0,
		'content_changed' => 0,
		'serialized_skip' => 0,
		'remaining'       => 0,
		'samples'         => array(),
		'affected'        => array(),
	);

	// ---- meta (candidates: any value carrying a Ã / Â / â lead byte) ----
	$mrows = $wpdb->get_results(
		"SELECT pm.meta_id, pm.post_id, pm.meta_key, pm.meta_value
		 FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE p.post_type IN ($types_in)
		   AND ( pm.meta_value LIKE BINARY '%Ã%' OR pm.meta_value LIKE BINARY '%Â%' OR pm.meta_value LIKE BINARY '%â%' )
		 LIMIT " . ( (int) $max + 1 )
	);
	$processed = 0;
	foreach ( (array) $mrows as $r ) {
		if ( $processed >= $max ) {
			$out['remaining']++;
			continue;
		}
		if ( is_serialized( $r->meta_value ) ) {
			$out['serialized_skip']++;
			continue;
		}
		$fixed = ricoman_fix_mojibake_text( $r->meta_value );
		if ( $fixed === $r->meta_value ) {
			continue;
		}
		$processed++;
		$out['meta_changed']++;
		$out['affected'][ (int) $r->post_id ] = 1;
		if ( count( $out['samples'] ) < 25 ) {
			$out['samples'][] = array( 'id' => (int) $r->post_id, 'field' => $r->meta_key, 'before' => $r->meta_value, 'after' => $fixed );
		}
		if ( $apply ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $fixed ), array( 'meta_id' => (int) $r->meta_id ) );
		}
	}

	// ---- post_title / post_content ----
	$prows = $wpdb->get_results(
		"SELECT ID, post_title, post_content FROM {$wpdb->posts}
		 WHERE post_type IN ($types_in)
		   AND ( post_title LIKE BINARY '%Ã%' OR post_title LIKE BINARY '%Â%' OR post_title LIKE BINARY '%â%'
		      OR post_content LIKE BINARY '%Ã%' OR post_content LIKE BINARY '%Â%' OR post_content LIKE BINARY '%â%' )
		 LIMIT 4000"
	);
	foreach ( (array) $prows as $p ) {
		$nt  = ricoman_fix_mojibake_text( $p->post_title );
		$nc  = ricoman_fix_mojibake_text( $p->post_content );
		$chg = array();
		if ( $nt !== $p->post_title ) {
			$chg['post_title'] = $nt;
			$out['title_changed']++;
		}
		if ( $nc !== $p->post_content ) {
			$chg['post_content'] = $nc;
			$out['content_changed']++;
		}
		if ( ! $chg ) {
			continue;
		}
		$out['affected'][ (int) $p->ID ] = 1;
		if ( count( $out['samples'] ) < 25 && isset( $chg['post_title'] ) ) {
			$out['samples'][] = array( 'id' => (int) $p->ID, 'field' => 'title', 'before' => $p->post_title, 'after' => $nt );
		}
		if ( $apply ) {
			$wpdb->update( $wpdb->posts, $chg, array( 'ID' => (int) $p->ID ) );
		}
	}

	if ( $apply && $out['affected'] ) {
		foreach ( array_keys( $out['affected'] ) as $pid ) {
			clean_post_cache( $pid );
		}
		// Bust product section caches (variant tables etc.) — rm_cfgimg_ver is part
		// of the section-cache key, so bumping it refreshes every product at once.
		update_option( 'rm_cfgimg_ver', (int) get_option( 'rm_cfgimg_ver', 0 ) + 1 );
	}
	return $out;
}

/* ------------------------------------------------------------------- menu */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Clean Import Text', 'ricoman' ),
		__( 'Clean Import Text', 'ricoman' ),
		ricoman_textclean_cap(),
		'ricoman-text-cleanup',
		'ricoman_textclean_render'
	);
}, 41 );

function ricoman_textclean_render() {
	if ( ! current_user_can( ricoman_textclean_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	$done  = isset( $_GET['cleaned'] ) ? array_map( 'intval', (array) $_GET['cleaned'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$scan  = ricoman_textclean_run( false );
	$total = $scan['meta_changed'] + $scan['title_changed'] + $scan['content_changed'];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Clean Import Text', 'ricoman' ); ?></h1>
		<p style="max-width:820px">Repairs mangled characters left by the old CSV import — e.g. <code>Ã˜</code>&nbsp;→&nbsp;<code>Ø</code>, <code>Ã—</code>&nbsp;→&nbsp;<code>×</code>, <code>Â°</code>&nbsp;→&nbsp;<code>°</code>, plus smart quotes and accents — across <strong>product</strong> and <strong>variant</strong> titles, content and fields. It only changes values it can repair losslessly, so genuine text is never touched.</p>
		<?php if ( is_array( $done ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( 'Repaired %d field(s), %d title(s) and %d content block(s). Product caches refreshed.', isset( $done[0] ) ? $done[0] : 0, isset( $done[1] ) ? $done[1] : 0, isset( $done[2] ) ? $done[2] : 0 ) ); ?></p></div>
		<?php endif; ?>

		<h2 style="margin-top:1.4em">
			<?php echo $total ? esc_html( sprintf( _n( '%s value to repair', '%s values to repair', $total, 'ricoman' ), number_format_i18n( $total ) ) ) : esc_html__( 'Nothing to repair — all clean ✓', 'ricoman' ); ?>
			<?php if ( $scan['remaining'] ) { echo ' <span style="font-weight:400;font-size:13px;color:#787c82">(more remain beyond the first batch — just run Repair again afterwards)</span>'; } ?>
		</h2>
		<?php if ( $scan['serialized_skip'] ) : ?>
			<p><em><?php echo esc_html( sprintf( '%d complex (serialized) value(s) were skipped for safety.', $scan['serialized_skip'] ) ); ?></em></p>
		<?php endif; ?>

		<?php if ( $total ) : ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead><tr><th style="width:26%">Product</th><th style="width:18%">Field</th><th>Before</th><th>After</th></tr></thead>
				<tbody>
				<?php foreach ( $scan['samples'] as $s ) : ?>
					<tr>
						<td><?php echo esc_html( get_the_title( $s['id'] ) ? get_the_title( $s['id'] ) : ( '#' . $s['id'] ) ); ?></td>
						<td><code><?php echo esc_html( $s['field'] ); ?></code></td>
						<td style="color:#b32d2e"><?php echo esc_html( mb_strimwidth( (string) $s['before'], 0, 90, '…' ) ); ?></td>
						<td style="color:#1a7f37"><?php echo esc_html( mb_strimwidth( (string) $s['after'], 0, 90, '…' ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p style="opacity:.7"><?php esc_html_e( 'Showing a sample of the changes; the button repairs every match.', 'ricoman' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Repair the mangled characters in all product + variant data? This edits the stored values.');">
				<input type="hidden" name="action" value="ricoman_textclean_apply">
				<?php wp_nonce_field( 'ricoman_textclean_apply' ); ?>
				<button type="submit" class="button button-primary button-hero"><?php echo esc_html( sprintf( 'Repair %s value(s)', number_format_i18n( $total ) ) ); ?></button>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'admin_post_ricoman_textclean_apply', function () {
	if ( ! current_user_can( ricoman_textclean_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_textclean_apply' );
	@set_time_limit( 300 ); // phpcs:ignore
	$res = ricoman_textclean_run( true );
	wp_safe_redirect( add_query_arg(
		array(
			'page'    => 'ricoman-text-cleanup',
			'cleaned' => array( (int) $res['meta_changed'], (int) $res['title_changed'], (int) $res['content_changed'] ),
		),
		admin_url( 'admin.php' )
	) );
	exit;
} );

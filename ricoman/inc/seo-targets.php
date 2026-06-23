<?php
/**
 * SEO Targets — the back-office register for the focus keywords, with an
 * automated on-page coverage review.
 *
 * Holds the team's target search terms (seeded with the 20 from the keyword
 * study), each mapped to the page that should rank for it, and audits coverage:
 * is the term in the page's SEO title, meta description, H1, body, URL, and does
 * the page have FAQ schema? Produces a per-term score + RAG flag and surfaces
 * GAPS (terms with no page yet) so the keyword list and the content plan live in
 * one place. Pure on-page review — no external API. (Real ranking/impression
 * data would come from a separate Google Search Console connection.)
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The starter set of focus terms (the 20 from the study). */
function ricoman_seo_targets_default() {
	return array(
		array( 'term' => 'commercial LED lighting manufacturer UK', 'intent' => 'Identity', 'priority' => 'High', 'url' => '/about/' ),
		array( 'term' => 'made in britain LED lighting', 'intent' => 'Identity', 'priority' => 'High', 'url' => '/made-in-britain/' ),
		array( 'term' => 'LED linear lighting', 'intent' => 'Commercial', 'priority' => 'High', 'url' => '/products/estrella-linear-lighting/' ),
		array( 'term' => 'UGR19 office linear lighting', 'intent' => 'Specifier', 'priority' => 'High', 'url' => '' ),
		array( 'term' => 'curved linear lighting', 'intent' => 'Specifier', 'priority' => 'High', 'url' => '/flow-designer/' ),
		array( 'term' => 'suspended linear lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'commercial LED track lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/product-category/48v-track/' ),
		array( 'term' => 'office lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'gym sports hall lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'healthcare antimicrobial lighting', 'intent' => 'Sector', 'priority' => 'High', 'url' => '/antimicrobial-protection/' ),
		array( 'term' => 'school education lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'retail lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'warehouse high bay lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'amenity lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/product-category/outdoor-exterior-lighting/' ),
		array( 'term' => 'feature lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '' ),
		array( 'term' => 'emergency lighting', 'intent' => 'Compliance', 'priority' => 'Medium', 'url' => '/fire-safety/' ),
		array( 'term' => 'free lighting design service', 'intent' => 'Investigation', 'priority' => 'High', 'url' => '/lighting-design/' ),
		array( 'term' => 'photometric IES LDT files', 'intent' => 'Download', 'priority' => 'Medium', 'url' => '/downloads/' ),
		array( 'term' => 'BIM Revit lighting files', 'intent' => 'Download', 'priority' => 'Medium', 'url' => '/downloads/' ),
		array( 'term' => 'human centric lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/human-centric-lighting/' ),
	);
}

/** The saved targets (seeded with the default set on first use). */
function ricoman_seo_targets() {
	$saved = get_option( 'ricoman_seo_targets', null );
	if ( ! is_array( $saved ) ) {
		$saved = ricoman_seo_targets_default();
		update_option( 'ricoman_seo_targets', $saved, false );
	}
	return $saved;
}

/* ----------------------------------------------------------------- matching */

function ricoman_seo_norm( $s ) {
	return trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^a-z0-9 ]+/', ' ', strtolower( (string) $s ) ) ) );
}
/** Exact (normalised) phrase present? */
function ricoman_seo_has_phrase( $hay, $needle ) {
	$n = ricoman_seo_norm( $needle );
	return '' !== $n && false !== strpos( ' ' . ricoman_seo_norm( $hay ) . ' ', ' ' . $n . ' ' )
		|| ( '' !== $n && false !== strpos( ricoman_seo_norm( $hay ), $n ) );
}
/** Fraction of the term's significant words present in the text (0..1). */
function ricoman_seo_word_cov( $hay, $needle ) {
	$h     = ' ' . ricoman_seo_norm( $hay ) . ' ';
	$stop  = array( 'the', 'and', 'for', 'uk', 'led' );
	$words = array_values( array_unique( array_filter( explode( ' ', ricoman_seo_norm( $needle ) ), function ( $w ) use ( $stop ) {
		return strlen( $w ) > 2 && ! in_array( $w, $stop, true );
	} ) ) );
	if ( ! $words ) {
		return 0.0;
	}
	$hit = 0;
	foreach ( $words as $w ) {
		if ( false !== strpos( $h, ' ' . $w . ' ' ) || false !== strpos( $h, $w ) ) {
			$hit++;
		}
	}
	return $hit / count( $words );
}

/* --------------------------------------------------------------- resolution */

/** Resolve a target URL/slug to a post or term we can audit. */
function ricoman_seo_target_resolve( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'type' => 'gap' );
	}
	$full = ( 0 === strpos( $url, 'http' ) ) ? $url : home_url( $url );
	$pid  = url_to_postid( $full );
	if ( $pid ) {
		return array( 'type' => 'post', 'id' => (int) $pid );
	}
	$path = trim( (string) wp_parse_url( $full, PHP_URL_PATH ), '/' );
	$seg  = explode( '/', $path );
	$last = end( $seg );
	foreach ( array( 'product-cat', 'product_cat', 'category', 'project-cat' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$term = get_term_by( 'slug', $last, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				return array( 'type' => 'term', 'term' => $term );
			}
		}
	}
	return array( 'type' => 'unresolved' );
}

/** The text fields we audit a term against, for a resolved target. */
function ricoman_seo_target_fields( $resolved ) {
	$f = array( 'title' => '', 'seo_title' => '', 'meta' => '', 'body' => '', 'slug' => '', 'faq' => false, 'label' => '', 'edit' => '' );
	if ( 'post' === $resolved['type'] ) {
		$id            = $resolved['id'];
		$f['title']    = get_the_title( $id );
		$f['seo_title'] = (string) get_post_meta( $id, '_yoast_wpseo_title', true );
		if ( '' === $f['seo_title'] ) {
			$f['seo_title'] = (string) get_post_meta( $id, '_ricoman_seo_title', true );
		}
		if ( '' === $f['seo_title'] && function_exists( 'ricoman_seo_post_title' ) ) {
			$f['seo_title'] = ricoman_seo_post_title( $id );
		}
		$f['meta'] = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
		if ( '' === $f['meta'] ) {
			$f['meta'] = (string) get_post_meta( $id, '_ricoman_seo_desc', true );
		}
		// Body: post content + key product fields (products render from meta, not content).
		$body = (string) get_post_field( 'post_content', $id );
		foreach ( array( 'product_sort_description', 'specification', 'key_features', 'product_subname' ) as $mk ) {
			$mv = get_post_meta( $id, $mk, true );
			if ( is_string( $mv ) ) {
				$body .= ' ' . $mv;
			}
		}
		$f['body'] = wp_strip_all_tags( strip_shortcodes( $body ) );
		$f['slug'] = (string) get_post_field( 'post_name', $id );
		$f['faq']  = ( '' !== trim( (string) get_post_meta( $id, '_ricoman_faq', true ) ) ) || false !== stripos( $body, 'ricoman_faq' );
		$f['label'] = get_the_title( $id );
		$f['edit']  = get_edit_post_link( $id, '' );
	} elseif ( 'term' === $resolved['type'] ) {
		$term          = $resolved['term'];
		$f['title']    = $term->name;
		$tax           = get_option( 'wpseo_taxonomy_meta', array() );
		$f['seo_title'] = isset( $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_title'] ) ? $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_title'] : '';
		$f['meta']     = isset( $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_desc'] ) ? $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_desc'] : '';
		$body          = (string) $term->description;
		if ( function_exists( 'ricoman_cat_seo' ) ) {
			$body .= ' ' . (string) ricoman_cat_seo( $term->term_id, 'intro' ) . ' ' . (string) ricoman_cat_seo( $term->term_id, 'body' );
		}
		$f['body'] = wp_strip_all_tags( strip_shortcodes( $body ) );
		$f['slug'] = $term->slug;
		$f['faq']  = false !== stripos( $body, 'ricoman_faq' );
		$f['label'] = $term->name . ' (category)';
		$f['edit']  = get_edit_term_link( $term->term_id, $term->taxonomy );
	}
	return $f;
}

/** Audit one target → score (0-100), RAG, and the list of checks. */
function ricoman_seo_target_audit( $target ) {
	$resolved = ricoman_seo_target_resolve( isset( $target['url'] ) ? $target['url'] : '' );
	$out      = array( 'resolved' => $resolved, 'score' => 0, 'rag' => 'red', 'checks' => array(), 'label' => '', 'edit' => '' );
	if ( 'post' !== $resolved['type'] && 'term' !== $resolved['type'] ) {
		$msg = ( 'gap' === $resolved['type'] ) ? 'No target page set — create one and assign it' : 'URL doesn’t resolve to a page';
		$out['checks'][] = array( $msg, false );
		$out['gap']      = true;
		return $out;
	}
	$f         = ricoman_seo_target_fields( $resolved );
	$out['label'] = $f['label'];
	$out['edit']  = $f['edit'];
	$term      = $target['term'];
	// weight => [label, text, exact-required]
	$tests = array(
		25 => array( 'SEO title', $f['seo_title'] ),
		20 => array( 'H1 / page title', $f['title'] ),
		15 => array( 'Meta description', $f['meta'] ),
		15 => array( 'Body copy', $f['body'] ),
		10 => array( 'URL slug', $f['slug'] ),
	);
	$score = 0;
	foreach ( $tests as $w => $t ) {
		list( $label, $text ) = $t;
		$pass = ricoman_seo_has_phrase( $text, $term );
		$cov  = ricoman_seo_word_cov( $text, $term );
		if ( $pass ) {
			$score += $w;
			$out['checks'][] = array( $label . ' ✓', true );
		} elseif ( $cov >= 0.6 ) {
			$score += (int) round( $w * 0.5 );
			$out['checks'][] = array( $label . ' — partial (' . round( $cov * 100 ) . '% of words)', null );
		} else {
			$out['checks'][] = array( $label . ' — missing', false );
		}
	}
	// FAQ schema bonus.
	if ( $f['faq'] ) {
		$score += 5;
		$out['checks'][] = array( 'FAQ schema present ✓', true );
	} else {
		$out['checks'][] = array( 'No FAQ schema (add [ricoman_faq])', false );
	}
	$out['score'] = min( 100, $score );
	$out['rag']   = $out['score'] >= 70 ? 'green' : ( $out['score'] >= 40 ? 'amber' : 'red' );
	return $out;
}

/* -------------------------------------------------------------- admin screen */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'SEO Targets', 'ricoman' ),
		__( 'SEO Targets', 'ricoman' ),
		'edit_posts',
		'ricoman-seo-targets',
		'ricoman_seo_targets_page'
	);
}, 29 );

/** Save handler. */
add_action( 'admin_post_ricoman_seo_targets_save', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ricoman_seo_targets' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$rows = isset( $_POST['t'] ) && is_array( $_POST['t'] ) ? wp_unslash( $_POST['t'] ) : array();
	$out  = array();
	foreach ( $rows as $r ) {
		$termv = isset( $r['term'] ) ? sanitize_text_field( $r['term'] ) : '';
		if ( '' === trim( $termv ) ) {
			continue;
		}
		$out[] = array(
			'term'     => $termv,
			'intent'   => isset( $r['intent'] ) ? sanitize_text_field( $r['intent'] ) : '',
			'priority' => isset( $r['priority'] ) ? sanitize_text_field( $r['priority'] ) : 'Medium',
			'url'      => isset( $r['url'] ) ? esc_url_raw( trim( $r['url'] ), array( 'http', 'https' ) ) : '',
		);
	}
	update_option( 'ricoman_seo_targets', $out, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function ricoman_seo_targets_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$targets = ricoman_seo_targets();
	$audits  = array();
	$green   = 0;
	$amber   = 0;
	$red     = 0;
	foreach ( $targets as $i => $t ) {
		$a            = ricoman_seo_target_audit( $t );
		$audits[ $i ] = $a;
		if ( 'green' === $a['rag'] ) {
			$green++;
		} elseif ( 'amber' === $a['rag'] ) {
			$amber++;
		} else {
			$red++;
		}
	}
	$intents    = array( 'Identity', 'Commercial', 'Specifier', 'Sector', 'Investigation', 'Download', 'Compliance' );
	$priorities = array( 'High', 'Medium', 'Low' );
	?>
	<div class="wrap rm-seotargets">
		<h1><?php esc_html_e( 'SEO Targets', 'ricoman' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Targets saved.', 'ricoman' ); ?></p></div>
		<?php endif; ?>
		<p class="description" style="max-width:820px"><?php esc_html_e( 'Your focus search terms and how well each target page covers them on-page (title, meta, H1, body, URL, FAQ schema). Edit the terms or the page each one targets; “gaps” are terms with no page yet — create the page, then assign its URL here. This reviews ON-PAGE coverage only; live ranking/impression data would come from a Google Search Console connection (separate setup).', 'ricoman' ); ?></p>

		<div class="rm-seo-sum">
			<span class="rm-seo-pill green"><?php echo (int) $green; ?> <?php esc_html_e( 'well covered', 'ricoman' ); ?></span>
			<span class="rm-seo-pill amber"><?php echo (int) $amber; ?> <?php esc_html_e( 'partial', 'ricoman' ); ?></span>
			<span class="rm-seo-pill red"><?php echo (int) $red; ?> <?php esc_html_e( 'gap / weak', 'ricoman' ); ?></span>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ricoman_seo_targets_save">
			<?php wp_nonce_field( 'ricoman_seo_targets' ); ?>
			<table class="widefat striped rm-seo-table">
				<thead><tr>
					<th style="width:22%"><?php esc_html_e( 'Target term', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'Intent', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'ricoman' ); ?></th>
					<th style="width:22%"><?php esc_html_e( 'Target page (URL/path)', 'ricoman' ); ?></th>
					<th style="width:9%"><?php esc_html_e( 'Coverage', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'What to fix', 'ricoman' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $targets as $i => $t ) :
					$a   = $audits[ $i ];
					$rag = $a['rag'];
					$gap = ! empty( $a['gap'] ); ?>
					<tr>
						<td><input type="text" name="t[<?php echo (int) $i; ?>][term]" value="<?php echo esc_attr( $t['term'] ); ?>" style="width:100%"></td>
						<td><select name="t[<?php echo (int) $i; ?>][intent]"><?php foreach ( $intents as $opt ) { echo '<option' . selected( $t['intent'], $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><select name="t[<?php echo (int) $i; ?>][priority]"><?php foreach ( $priorities as $opt ) { echo '<option' . selected( $t['priority'], $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><input type="text" name="t[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $t['url'] ); ?>" placeholder="/page-slug/" style="width:78%">
							<?php if ( $a['edit'] ) : ?> <a href="<?php echo esc_url( $a['edit'] ); ?>" title="Edit page">✎</a><?php endif; ?>
						</td>
						<td><span class="rm-seo-score rm-<?php echo esc_attr( $rag ); ?>"><?php echo $gap ? '—' : (int) $a['score'] . '%'; ?></span></td>
						<td class="rm-seo-checks">
							<?php
							if ( $gap ) {
								echo '<strong style="color:#b32d2e">' . esc_html__( 'No page yet — create one and add its URL.', 'ricoman' ) . '</strong>';
							} else {
								$bad = array();
								foreach ( $a['checks'] as $c ) {
									if ( false === $c[1] ) {
										$bad[] = $c[0];
									}
								}
								echo $bad ? esc_html( implode( ' · ', $bad ) ) : '<span style="color:#1a7f37">' . esc_html__( 'All on-page signals present.', 'ricoman' ) . '</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr class="rm-seo-add">
						<td><input type="text" name="t[new][term]" placeholder="<?php esc_attr_e( 'add a new term…', 'ricoman' ); ?>" style="width:100%"></td>
						<td><select name="t[new][intent]"><?php foreach ( $intents as $opt ) { echo '<option>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><select name="t[new][priority]"><?php foreach ( $priorities as $opt ) { echo '<option' . selected( 'Medium', $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><input type="text" name="t[new][url]" placeholder="/page-slug/" style="width:78%"></td>
						<td>—</td><td><?php esc_html_e( '(new row)', 'ricoman' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p><?php submit_button( __( 'Save targets', 'ricoman' ), 'primary', 'submit', false ); ?>
				<span class="description" style="margin-left:10px"><?php esc_html_e( 'Coverage re-checks automatically each time you open this page.', 'ricoman' ); ?></span></p>
		</form>

		<style>
		.rm-seo-sum{margin:10px 0 14px}
		.rm-seo-pill{display:inline-block;padding:5px 12px;border-radius:999px;color:#fff;font-weight:600;margin-right:8px;font-size:13px}
		.rm-seo-pill.green{background:#1a7f37}.rm-seo-pill.amber{background:#b8860b}.rm-seo-pill.red{background:#b32d2e}
		.rm-seo-table input[type=text],.rm-seo-table select{font-size:13px}
		.rm-seo-score{display:inline-block;min-width:42px;text-align:center;padding:3px 8px;border-radius:6px;font-weight:700;color:#fff}
		.rm-seo-score.rm-green{background:#1a7f37}.rm-seo-score.rm-amber{background:#b8860b}.rm-seo-score.rm-red{background:#b32d2e}
		.rm-seo-checks{font-size:12.5px;color:#555}
		.rm-seo-add td{background:#f6f7f9}
		</style>
	</div>
	<?php
}

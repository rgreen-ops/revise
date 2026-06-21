<?php
/**
 * Links & Redirects — a back-office tool for the team to manage 301 redirects
 * (and understand canonicals) without touching code or a plugin.
 *
 * Ricoman → Links & Redirects: add an old URL → new URL in seconds, with
 * plain-English guidance on what to do, when and why.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Saved redirect map: from-path (no leading slash) => destination URL. */
function ricoman_redirects_get() {
	$map = get_option( 'ricoman_redirects', null );
	if ( null === $map ) {
		// Seed once with the office-lighting vanity redirect so it's editable.
		$map = array( 'office-lighting' => '/sector/office-lighting/' );
		add_option( 'ricoman_redirects', $map );
	}
	return is_array( $map ) ? $map : array();
}

/** Normalise a "from" value to a bare path (no host, no leading/trailing slash). */
function ricoman_redirect_norm_from( $from ) {
	$from = (string) $from;
	$path = wp_parse_url( $from, PHP_URL_PATH );
	if ( ! $path ) {
		$path = $from;
	}
	return trim( (string) $path, '/ ' );
}

/* -------------------------------------------------------- the redirect engine */
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$map = ricoman_redirects_get();
	if ( ! isset( $map[ $path ] ) ) {
		return;
	}
	$to = (string) $map[ $path ];
	$target = preg_match( '#^https?://#i', $to ) ? $to : home_url( '/' . ltrim( $to, '/' ) );
	wp_safe_redirect( $target, 301 );
	exit;
}, 0 );

/* ---- Old single-product URLs (/product/{slug}) → new /products/{slug} ----
 * The old site used /product/ (singular); products now live at /products/
 * (plural), and some slugs were shortened during migration. On a 404 under
 * /product/, send the visitor to the matching product: first by the old slug
 * (WordPress records _wp_old_slug when a slug changes), then by the current
 * slug (covers the case where only the /product → /products base changed). */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! is_404() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path || 0 !== strpos( $path, 'product/' ) ) {
		return;
	}
	$slug = sanitize_title( basename( $path ) );
	if ( '' === $slug ) {
		return;
	}
	$found = get_posts( array(
		'post_type'     => 'product',
		'post_status'   => 'publish',
		'numberposts'   => 1,
		'fields'        => 'ids',
		'no_found_rows' => true,
		'meta_query'    => array( array( 'key' => '_wp_old_slug', 'value' => $slug ) ),
	) );
	if ( ! $found ) {
		$p = get_page_by_path( $slug, OBJECT, 'product' );
		if ( $p ) {
			$found = array( $p->ID );
		}
	}
	if ( $found ) {
		wp_safe_redirect( get_permalink( (int) $found[0] ), 301 );
		exit;
	}
}, 1 );

/**
 * Does this path now resolve to a real, published destination? Used to clear
 * fixed links from the 404 log. Conservative — only returns true when we can
 * confidently resolve it (so a genuinely broken link is never silently dropped).
 */
function ricoman_path_resolves( $path ) {
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return true;
	}
	// Covered by a manual redirect, or by the /product → /products rule above.
	$map = ricoman_redirects_get();
	if ( isset( $map[ $path ] ) ) {
		return true;
	}
	if ( 0 === strpos( $path, 'product/' ) ) {
		$slug = sanitize_title( basename( $path ) );
		if ( $slug && ( get_page_by_path( $slug, OBJECT, 'product' )
			|| get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_wp_old_slug', 'value' => $slug ) ) ) ) ) ) {
			return true;
		}
	}
	// Resolves to a post/page/CPT via the actual rewrite rules (respects the URL base).
	if ( url_to_postid( home_url( '/' . $path . '/' ) ) || url_to_postid( home_url( '/' . $path ) ) ) {
		return true;
	}
	if ( get_page_by_path( $path ) ) {
		return true;
	}
	// A public taxonomy term whose real URL equals this path.
	$slug = basename( $path );
	foreach ( array_values( get_taxonomies( array( 'public' => true ), 'names' ) ) as $tx ) {
		$term = get_term_by( 'slug', $slug, $tx );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term, $tx );
			if ( ! is_wp_error( $link ) && trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' ) === $path ) {
				return true;
			}
		}
	}
	return false;
}

/* ---------------------------------------------------------- 404 watch (logger) */
add_action( 'template_redirect', function () {
	if ( is_admin() || ! is_404() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	// Ignore assets, wp-* paths and anything already on the ignore list.
	if ( preg_match( '#\.(css|js|png|jpe?g|gif|webp|avif|svg|ico|woff2?|ttf|map|xml|txt|php)$#i', $path ) ) {
		return;
	}
	if ( 0 === strpos( $path, 'wp-' ) || false !== strpos( $path, 'wp-content' ) || false !== strpos( $path, 'wp-json' ) ) {
		return;
	}
	if ( in_array( $path, (array) get_option( 'ricoman_404_ignored', array() ), true ) ) {
		return;
	}
	$log = (array) get_option( 'ricoman_404_log', array() );
	if ( ! isset( $log[ $path ] ) ) {
		$log[ $path ] = array( 'hits' => 0, 'last' => 0, 'ref' => '' );
	}
	$log[ $path ]['hits']++;
	$log[ $path ]['last'] = time();
	$ref = wp_get_referer();
	if ( $ref ) {
		$log[ $path ]['ref'] = esc_url_raw( $ref );
	}
	if ( count( $log ) > 300 ) {
		uasort( $log, function ( $a, $b ) { return $b['last'] - $a['last']; } );
		$log = array_slice( $log, 0, 300, true );
	}
	update_option( 'ricoman_404_log', $log, false );
}, 5 );

/** Flag unresolved 404s on every admin screen so they get sorted promptly. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'ricoman_page_ricoman-redirects' === $screen->id ) {
		return; // already on the fix-it page.
	}
	$n = count( (array) get_option( 'ricoman_404_log', array() ) );
	if ( $n < 1 ) {
		return;
	}
	$url = admin_url( 'admin.php?page=ricoman-redirects' );
	echo '<div class="notice notice-warning"><p>🔗 <strong>'
		. esc_html( sprintf( _n( '%s broken link (404) needs attention', '%s broken links (404s) need attention', $n, 'ricoman' ), number_format_i18n( $n ) ) )
		. '</strong> — ' . esc_html__( 'visitors hit pages that don\'t exist. Add a redirect so they reach the right place.', 'ricoman' )
		. ' <a href="' . esc_url( $url ) . '" class="button button-small">' . esc_html__( 'Review & fix', 'ricoman' ) . '</a></p></div>';
} );

/* ------------------------------------------------------------------ admin page */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Links & Redirects', 'ricoman' ),
		__( 'Links & Redirects', 'ricoman' ),
		'manage_options',
		'ricoman-redirects',
		'ricoman_redirects_page'
	);
}, 33 );

function ricoman_redirects_page() {
	$map  = ricoman_redirects_get();
	$home = home_url( '/' );
	echo '<div class="wrap"><h1>' . esc_html__( 'Links & Redirects', 'ricoman' ) . '</h1>';

	if ( isset( $_GET['rm_r'] ) ) {
		$msgs = array(
			'added'   => __( 'Redirect added.', 'ricoman' ),
			'deleted' => __( 'Redirect removed.', 'ricoman' ),
			'bad'     => __( 'Please enter a valid "from" path and "to" URL.', 'ricoman' ),
		);
		$k = sanitize_key( wp_unslash( $_GET['rm_r'] ) );
		if ( isset( $msgs[ $k ] ) ) {
			$cls = 'bad' === $k ? 'notice-error' : 'notice-success';
			echo '<div class="notice ' . esc_attr( $cls ) . ' is-dismissible"><p>' . esc_html( $msgs[ $k ] ) . '</p></div>';
		}
	}
	if ( isset( $_GET['rm_recheck'] ) ) {
		$removed = max( 0, (int) $_GET['rm_recheck'] );
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html( sprintf( _n( 'Re-checked: %s link now works and was cleared.', 'Re-checked: %s links now work and were cleared.', $removed, 'ricoman' ), number_format_i18n( $removed ) ) )
			. '</p></div>';
	}

	// ---- Guidance: what / when / why ----
	echo '<div class="card" style="max-width:820px;padding:4px 20px 16px">';
	echo '<h2>' . esc_html__( 'Redirects — what, when & why', 'ricoman' ) . '</h2>';
	echo '<p><strong>' . esc_html__( 'What:', 'ricoman' ) . '</strong> ' . esc_html__( 'a 301 redirect permanently sends an old web address to a new one.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'When to add one:', 'ricoman' ) . '</strong></p><ul style="list-style:disc;margin:0 0 1em 20px">';
	echo '<li>' . esc_html__( 'You delete or rename a page (so the old link doesn\'t 404).', 'ricoman' ) . '</li>';
	echo '<li>' . esc_html__( 'You merge two pages into one (point the old one at the keeper).', 'ricoman' ) . '</li>';
	echo '<li>' . esc_html__( 'You want a short, memorable "vanity" link (e.g. /office-lighting/ → the full sector page).', 'ricoman' ) . '</li></ul>';
	echo '<p><strong>' . esc_html__( 'Why it matters:', 'ricoman' ) . '</strong> ' . esc_html__( 'it keeps your Google rankings and any existing backlinks pointing at the old address, and stops visitors hitting a dead "page not found".', 'ricoman' ) . '</p>';
	echo '<p style="color:#555"><strong>' . esc_html__( 'Tip:', 'ricoman' ) . '</strong> ' . esc_html__( 'redirect a URL to its closest equivalent, not just the home page — Google passes more value, and it\'s a better experience.', 'ricoman' ) . '</p>';
	echo '</div>';

	// ---- Add form ----
	echo '<h2 style="margin-top:24px">' . esc_html__( 'Add a redirect', 'ricoman' ) . '</h2>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_redirect_add">';
	wp_nonce_field( 'ricoman_redirect_add' );
	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th scope="row"><label for="rm_from">' . esc_html__( 'Old address (from)', 'ricoman' ) . '</label></th><td>';
	echo '<code>' . esc_html( $home ) . '</code> <input name="from" id="rm_from" type="text" class="regular-text" placeholder="old-page" required>';
	echo '<p class="description">' . esc_html__( 'Just the path after the domain, e.g. "old-products" or "office-lighting".', 'ricoman' ) . '</p></td></tr>';
	echo '<tr><th scope="row"><label for="rm_to">' . esc_html__( 'New destination (to)', 'ricoman' ) . '</label></th><td>';
	echo '<input name="to" id="rm_to" type="text" class="regular-text" placeholder="/sector/office-lighting/  or  https://…" required>';
	echo '<p class="description">' . esc_html__( 'A path on this site (start with /) or a full https:// URL.', 'ricoman' ) . '</p></td></tr>';
	echo '</tbody></table>';
	submit_button( __( 'Add redirect', 'ricoman' ) );
	echo '</form>';

	// ---- Current redirects ----
	echo '<h2 style="margin-top:24px">' . esc_html__( 'Active redirects', 'ricoman' ) . '</h2>';
	if ( ! $map ) {
		echo '<p>' . esc_html__( 'No redirects yet.', 'ricoman' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr>'
			. '<th>' . esc_html__( 'From', 'ricoman' ) . '</th><th>' . esc_html__( 'To', 'ricoman' ) . '</th><th></th><th></th></tr></thead><tbody>';
		foreach ( $map as $from => $to ) {
			$test = home_url( '/' . $from . '/' );
			$del  = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_redirect_delete&from=' . rawurlencode( $from ) ), 'ricoman_redirect_delete_' . $from );
			echo '<tr><td><code>/' . esc_html( $from ) . '/</code></td>'
				. '<td><code>' . esc_html( $to ) . '</code></td>'
				. '<td><a href="' . esc_url( $test ) . '" target="_blank" rel="noopener">' . esc_html__( 'Test ↗', 'ricoman' ) . '</a></td>'
				. '<td><a href="' . esc_url( $del ) . '" style="color:#b32d2e" onclick="return confirm(\'' . esc_js( __( 'Remove this redirect?', 'ricoman' ) ) . '\')">' . esc_html__( 'Delete', 'ricoman' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	// ---- Broken links (404s) detected ----
	// Auto-clean: drop any logged link that now resolves (fixed page, manual
	// redirect, or the automatic /product → /products rule) so the list shows
	// only genuinely-broken URLs without anyone clicking re-check.
	$log     = (array) get_option( 'ricoman_404_log', array() );
	$cleaned = false;
	foreach ( array_keys( $log ) as $p ) {
		if ( ricoman_path_resolves( $p ) ) {
			unset( $log[ $p ] );
			$cleaned = true;
		}
	}
	if ( $cleaned ) {
		update_option( 'ricoman_404_log', $log, false );
	}
	uasort( $log, function ( $a, $b ) { return ( $b['hits'] <=> $a['hits'] ) ?: ( $b['last'] <=> $a['last'] ); } );
	echo '<h2 style="margin-top:28px">' . esc_html__( 'Broken links detected (404s)', 'ricoman' );
	if ( $log ) {
		echo ' <span class="awaiting-mod" style="background:#d63638;color:#fff;border-radius:9px;padding:1px 8px;font-size:12px">' . esc_html( number_format_i18n( count( $log ) ) ) . '</span>';
	}
	echo '</h2>';
	echo '<p class="description">' . esc_html__( 'Addresses visitors (or Google) requested that don\'t exist. Point each at the closest live page, or ignore it if it\'s junk/spam.', 'ricoman' ) . '</p>';
	if ( $log ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0 0 14px">';
		echo '<input type="hidden" name="action" value="ricoman_404_recheck">';
		wp_nonce_field( 'ricoman_404_recheck' );
		echo '<button class="button">' . esc_html__( '↻ Re-check & clear links that now work', 'ricoman' ) . '</button>';
		echo ' <span class="description">' . esc_html__( 'Removes any flagged link that now resolves (e.g. fixed, redirected, or back online).', 'ricoman' ) . '</span>';
		echo '</form>';
	}
	if ( ! $log ) {
		echo '<p>✅ ' . esc_html__( 'No broken links recorded. Nice.', 'ricoman' ) . '</p>';
	} else {
		echo '<table class="widefat striped" style="max-width:1000px"><thead><tr>'
			. '<th>' . esc_html__( 'Broken URL', 'ricoman' ) . '</th><th>' . esc_html__( 'Hits', 'ricoman' ) . '</th><th>' . esc_html__( 'Last seen', 'ricoman' ) . '</th>'
			. '<th>' . esc_html__( 'Redirect to', 'ricoman' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $log as $path => $info ) {
			$ago = human_time_diff( (int) $info['last'], time() );
			$ig  = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_404_ignore&path=' . rawurlencode( $path ) ), 'ricoman_404_ignore_' . $path );
			echo '<tr><td><a href="' . esc_url( home_url( '/' . $path . '/' ) ) . '" target="_blank" rel="noopener"><code>/' . esc_html( $path ) . '/</code></a>'
				. ( ! empty( $info['ref'] ) ? '<br><small style="color:#777">' . esc_html__( 'from', 'ricoman' ) . ' ' . esc_html( $info['ref'] ) . '</small>' : '' ) . '</td>'
				. '<td>' . esc_html( number_format_i18n( (int) $info['hits'] ) ) . '</td>'
				. '<td>' . esc_html( $ago ) . ' ' . esc_html__( 'ago', 'ricoman' ) . '</td>'
				. '<td><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px">'
				. '<input type="hidden" name="action" value="ricoman_redirect_add">'
				. wp_nonce_field( 'ricoman_redirect_add', '_wpnonce', true, false )
				. '<input type="hidden" name="from" value="' . esc_attr( $path ) . '">'
				. '<input type="text" name="to" class="regular-text" placeholder="/sector/… or https://…" style="width:230px" required>'
				. '<button class="button button-primary">' . esc_html__( 'Redirect', 'ricoman' ) . '</button></form></td>'
				. '<td><a href="' . esc_url( $ig ) . '" style="color:#777">' . esc_html__( 'Ignore', 'ricoman' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	// ---- Canonicals guidance ----
	echo '<div class="card" style="max-width:820px;padding:4px 20px 16px;margin-top:28px">';
	echo '<h2>' . esc_html__( 'Canonical links — what, when & why', 'ricoman' ) . '</h2>';
	echo '<p><strong>' . esc_html__( 'What:', 'ricoman' ) . '</strong> ' . esc_html__( 'a canonical tag tells Google which page is the "main" one when very similar content exists on more than one URL.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'When to set one:', 'ricoman' ) . '</strong> ' . esc_html__( 'when two pages target the same topic (e.g. a commercial sector page and a how-to article both about "office lighting"), or the same content is reachable at more than one address. Point both at the page you want to rank.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'Why:', 'ricoman' ) . '</strong> ' . esc_html__( 'it stops the two pages competing and splitting their Google ranking (known as "keyword cannibalisation").', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html__( 'How (fast):', 'ricoman' ) . '</strong> ' . esc_html__( 'edit the page → in the Yoast SEO box scroll to Advanced → "Canonical URL" → paste the address of the primary page. Leave blank on the primary page itself.', 'ricoman' ) . '</p>';
	echo '<p style="color:#555">' . esc_html__( 'Rule of thumb: redirect when the old page should no longer exist; use a canonical when both pages should stay live but only one should rank.', 'ricoman' ) . '</p>';
	echo '</div>';

	echo '</div>';
}

add_action( 'admin_post_ricoman_redirect_add', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_redirect_add' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$from = ricoman_redirect_norm_from( wp_unslash( $_POST['from'] ?? '' ) );
	$to   = trim( (string) wp_unslash( $_POST['to'] ?? '' ) );
	$status = 'bad';
	if ( '' !== $from && '' !== $to ) {
		// Allow a relative path or an absolute http(s) URL.
		if ( preg_match( '#^https?://#i', $to ) ) {
			$to = esc_url_raw( $to );
		} else {
			$to = '/' . ltrim( esc_url_raw( $to, array( 'http', 'https' ) ) ? sanitize_text_field( $to ) : $to, '/' );
		}
		if ( '' !== $to ) {
			$map          = ricoman_redirects_get();
			$map[ $from ] = $to;
			update_option( 'ricoman_redirects', $map );
			// Clear it from the 404 watch — it's handled now.
			$log = (array) get_option( 'ricoman_404_log', array() );
			if ( isset( $log[ $from ] ) ) {
				unset( $log[ $from ] );
				update_option( 'ricoman_404_log', $log, false );
			}
			$status = 'added';
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-redirects&rm_r=' . $status ) );
	exit;
} );

add_action( 'admin_post_ricoman_404_ignore', function () {
	$path = ricoman_redirect_norm_from( wp_unslash( $_GET['path'] ?? '' ) );
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_404_ignore_' . $path ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$ignored = (array) get_option( 'ricoman_404_ignored', array() );
	if ( ! in_array( $path, $ignored, true ) ) {
		$ignored[] = $path;
		update_option( 'ricoman_404_ignored', $ignored, false );
	}
	$log = (array) get_option( 'ricoman_404_log', array() );
	unset( $log[ $path ] );
	update_option( 'ricoman_404_log', $log, false );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-redirects' ) );
	exit;
} );

add_action( 'admin_post_ricoman_redirect_delete', function () {
	$from = ricoman_redirect_norm_from( wp_unslash( $_GET['from'] ?? '' ) );
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_redirect_delete_' . $from ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$map = ricoman_redirects_get();
	unset( $map[ $from ] );
	update_option( 'ricoman_redirects', $map );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-redirects&rm_r=deleted' ) );
	exit;
} );

/** Re-check every logged 404 and clear the ones that now resolve. */
add_action( 'admin_post_ricoman_404_recheck', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_404_recheck' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$log     = (array) get_option( 'ricoman_404_log', array() );
	$removed = 0;
	foreach ( array_keys( $log ) as $path ) {
		if ( ricoman_path_resolves( $path ) ) {
			unset( $log[ $path ] );
			$removed++;
		}
	}
	update_option( 'ricoman_404_log', $log, false );
	wp_safe_redirect( admin_url( 'admin.php?page=ricoman-redirects&rm_recheck=' . $removed ) );
	exit;
} );

/* Daily: auto-prune the 404 log of links that now resolve, so the admin
   warning count stays accurate without anyone opening the page. */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ricoman_404_prune' ) ) {
		wp_schedule_event( time() + 600, 'daily', 'ricoman_404_prune' );
	}
} );
add_action( 'ricoman_404_prune', function () {
	$log = (array) get_option( 'ricoman_404_log', array() );
	$ch  = false;
	foreach ( array_keys( $log ) as $p ) {
		if ( ricoman_path_resolves( $p ) ) {
			unset( $log[ $p ] );
			$ch = true;
		}
	}
	if ( $ch ) {
		update_option( 'ricoman_404_log', $log, false );
	}
} );

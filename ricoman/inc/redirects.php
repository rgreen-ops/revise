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

/* ---- Old-site "/back-end/" prefix repair ----------------------------------
 * The previous ricoman.com served ALL wp-content under a /back-end/ prefix, e.g.
 * /back-end/wp-content/uploads/2020/01/Modus-Installation-Instructions.pdf.
 * PRINTED QR codes on product packaging (installation manuals) point at those
 * old URLs, so they 404 after the move. The new site serves the identical files
 * at the standard path with no prefix, so strip "/back-end" and 301 to the real
 * file. One rule repairs every label + any legacy /back-end/ link (PDFs, images,
 * datasheets…). Runs at a very early priority so it beats the page cache and the
 * 404 resolver. */
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	if ( '' === $uri || 0 !== strpos( $uri, '/back-end/' ) ) {
		return;
	}
	$target = substr( $uri, strlen( '/back-end' ) ); // keeps the leading slash → "/wp-content/..."
	if ( '' === $target || '/' !== $target[0] ) {
		$target = '/' . ltrim( (string) $target, '/' );
	}
	wp_safe_redirect( $target, 301 );
	exit;
}, -999 );

/* ---- Old permalinks → current destination (general 404 resolver) ----------
 * The old site used different URL bases: /product/ (singular), category-prefixed
 * product URLs like /track-lighting/{slug}/, and older project/news slugs that
 * were renamed during migration. On ANY 404, resolve the final slug to a real
 * published product / project / news / page (by current slug, then by
 * _wp_old_slug for renamed posts, then a public taxonomy term) and 301 to its
 * canonical URL. This fixes every old-base / renamed link automatically — no
 * manual entry needed — and stops these 404s recurring. */
function ricoman_resolve_old_path( $path ) {
	$path = trim( (string) $path, '/' );
	if ( '' === $path ) {
		return '';
	}
	static $cache = array();
	if ( isset( $cache[ $path ] ) ) {
		return $cache[ $path ];
	}
	$slug = sanitize_title( basename( $path ) );
	if ( '' === $slug ) {
		return $cache[ $path ] = '';
	}
	$types = array_values( array_filter( array( 'product', 'project', 'news', 'page', 'post' ), 'post_type_exists' ) );
	// 1) A current slug on one of our post types.
	foreach ( $types as $t ) {
		$p = get_page_by_path( $slug, OBJECT, $t );
		if ( $p && 'publish' === get_post_status( $p ) ) {
			return $cache[ $path ] = get_permalink( $p );
		}
	}
	// 2) A slug renamed during/after migration (WordPress records _wp_old_slug).
	$found = get_posts( array(
		'post_type'     => $types,
		'post_status'   => 'publish',
		'numberposts'   => 1,
		'fields'        => 'ids',
		'no_found_rows' => true,
		'meta_query'    => array( array( 'key' => '_wp_old_slug', 'value' => $slug ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	if ( $found ) {
		return $cache[ $path ] = get_permalink( (int) $found[0] );
	}
	// 3) A public taxonomy term with that slug (old category URLs).
	foreach ( array_values( get_taxonomies( array( 'public' => true ), 'names' ) ) as $tx ) {
		$term = get_term_by( 'slug', $slug, $tx );
		if ( $term && ! is_wp_error( $term ) ) {
			$link = get_term_link( $term, $tx );
			if ( ! is_wp_error( $link ) ) {
				return $cache[ $path ] = $link;
			}
		}
	}
	// 4) A variant-product (order-code row) → its parent product page. These
	// individual variant URLs have no page of their own.
	if ( post_type_exists( 'variant-product' ) ) {
		$vp = get_page_by_path( $slug, OBJECT, 'variant-product' );
		if ( ! $vp ) {
			$vf = get_posts( array( 'post_type' => 'variant-product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_wp_old_slug', 'value' => $slug ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$vp = $vf ? get_post( (int) $vf[0] ) : null;
		}
		if ( $vp ) {
			$parent = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $vp->ID, 'parent_product' ) : get_post_meta( $vp->ID, 'parent_product', true );
			$pid    = is_array( $parent ) ? ( isset( $parent['ID'] ) ? (int) $parent['ID'] : 0 ) : (int) $parent;
			if ( $pid && 'publish' === get_post_status( $pid ) ) {
				return $cache[ $path ] = get_permalink( $pid );
			}
		}
	}
	// 5) Near-miss slug (renamed without _wp_old_slug recorded) — e.g.
	// /product/astrowave/ → "astrowave-neon-rope-light". Only a UNIQUE prefix
	// match on a product/project is accepted, so we never guess between two.
	if ( strlen( $slug ) >= 4 ) {
		global $wpdb;
		foreach ( array( 'product', 'project' ) as $t ) {
			if ( ! post_type_exists( $t ) ) {
				continue;
			}
			$rows = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND post_name LIKE %s LIMIT 2",
				$t, $wpdb->esc_like( $slug ) . '-%'
			) );
			if ( 1 === count( $rows ) ) {
				return $cache[ $path ] = get_permalink( (int) $rows[0] );
			}
		}
	}
	return $cache[ $path ] = '';
}

/**
 * Belt-and-braces: rewrite old-base links INSIDE rendered content so they point
 * straight at the canonical URL (no redirect hop). Catches links left in
 * migrated page / project / news content: /product/{slug} (singular) and
 * category-prefixed product URLs like /track-lighting/{slug}. Only touches
 * links that look old and resolve to a real product — current links are skipped.
 */
function ricoman_rewrite_old_links( $html ) {
	if ( ! is_string( $html ) || false === strpos( $html, 'href=' ) ) {
		return $html;
	}
	$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$skip = array( 'products', 'projects', 'news', 'sector', 'sectors', 'product-category', 'category', 'tag', 'author', 'wp-content', 'wp-json', 'feed', 'page', 'blog' );
	return preg_replace_callback( '#href=(["\'])(.*?)\1#i', function ( $m ) use ( $home, $skip ) {
		$q = $m[1]; $url = $m[2];
		if ( '' === $url || '#' === $url[0] || preg_match( '#^(mailto:|tel:|javascript:|data:)#i', $url ) ) {
			return $m[0];
		}
		$pp = wp_parse_url( $url );
		if ( ! empty( $pp['host'] ) && strtolower( $pp['host'] ) !== $home ) {
			return $m[0]; // external link.
		}
		$path = trim( (string) ( $pp['path'] ?? '' ), '/' );
		if ( '' === $path || preg_match( '#\.[a-z0-9]{2,5}$#i', $path ) ) {
			return $m[0]; // empty or a file.
		}
		$segs = explode( '/', $path );
		$target = '';
		if ( 'product' === $segs[0] && count( $segs ) >= 2 ) {
			// Old singular base — resolve via the shared resolver.
			$target = ricoman_resolve_old_path( $path );
		} elseif ( 2 === count( $segs ) && ! in_array( $segs[0], $skip, true ) ) {
			// Category-prefixed product URL (e.g. /track-lighting/{slug}) — product only.
			$slug = sanitize_title( $segs[1] );
			$p    = $slug ? get_page_by_path( $slug, OBJECT, 'product' ) : null;
			if ( $p && 'publish' === get_post_status( $p ) ) {
				$target = get_permalink( $p );
			} elseif ( $slug ) {
				$f = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true, 'meta_query' => array( array( 'key' => '_wp_old_slug', 'value' => $slug ) ) ) ); // phpcs:ignore
				if ( $f ) {
					$target = get_permalink( (int) $f[0] );
				}
			}
		}
		if ( $target && trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' ) !== $path ) {
			return 'href=' . $q . esc_url( $target ) . $q;
		}
		return $m[0];
	}, $html );
}
add_filter( 'the_content', 'ricoman_rewrite_old_links', 9 );
// Old links also survive in nav menus, widgets/footer and excerpts — fix those
// on render too, so a stale URL anywhere points at the live destination.
add_filter( 'wp_nav_menu', 'ricoman_rewrite_old_links', 9 );
add_filter( 'the_excerpt', 'ricoman_rewrite_old_links', 9 );
add_filter( 'widget_text', 'ricoman_rewrite_old_links', 9 );
add_filter( 'render_block', function ( $html, $block ) {
	// Navigation + html/button blocks can carry hardcoded old URLs.
	if ( is_string( $html ) && false !== strpos( $html, 'href=' ) ) {
		$name = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		if ( in_array( $name, array( 'core/navigation', 'core/navigation-link', 'core/html', 'core/buttons', 'core/button' ), true ) ) {
			return ricoman_rewrite_old_links( $html );
		}
	}
	return $html;
}, 9, 2 );

add_action( 'template_redirect', function () {
	if ( is_admin() || ! is_404() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return;
	}
	$target = ricoman_resolve_old_path( $path );
	if ( $target ) {
		// Never redirect a path onto itself (avoids loops).
		if ( trim( (string) wp_parse_url( $target, PHP_URL_PATH ), '/' ) !== $path ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
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
	// Covered by a manual redirect, or by the general old-path resolver above.
	$map = ricoman_redirects_get();
	if ( isset( $map[ $path ] ) ) {
		return true;
	}
	if ( '' !== ricoman_resolve_old_path( $path ) ) {
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

/**
 * Obvious bot / scanner / junk 404s that aren't real links on the site — used to
 * keep the Links & Redirects list to genuine issues only. Matches secret/config
 * file probes (.env, .git, wp-config, backups…), admin-panel guesses, malformed
 * paths (wildcards, emails stuck in a URL) and crawler .well-known conventions.
 * Real page slugs (about, product-warranty, modern-slavery-statement…) never hit
 * these, so genuine broken links are preserved.
 */
function ricoman_404_is_noise( $path ) {
	$p = strtolower( (string) $path );
	if ( '' === $p ) {
		return false;
	}
	if ( false !== strpos( $p, '@' ) || false !== strpos( $p, '*' ) ) {
		return true; // email or wildcard in the path = bot/junk.
	}
	if ( 0 === strpos( $p, '.well-known/' ) ) {
		return true; // protocol/crawler conventions, never site content.
	}
	if ( 'admin' === $p || 0 === strpos( $p, 'admin/' ) || 0 === strpos( $p, 'administrator' ) ) {
		return true; // admin-panel guesses (our real admin is /wp-admin/).
	}
	$needles = array( '.env', '.git', '.svn', '.htaccess', '.aws', '.ssh', 'wp-config', 'phpinfo', 'phpunit', 'phpmyadmin', 'vendor/', 'eval-stdin', 'xmlrpc', '.sql', '.bak', '.old', '.backup' );
	foreach ( $needles as $n ) {
		if ( false !== strpos( $p, $n ) ) {
			return true;
		}
	}
	// Ahrefs / SEO-crawler verification tokens (e.g. /ahrefs_<hash>/).
	if ( 0 === strpos( $p, 'ahrefs' ) || false !== strpos( $p, '/ahrefs' ) ) {
		return true;
	}
	// App-server exploit probes (Spring Boot Actuator, Jenkins, Solr, …).
	if ( false !== strpos( $p, 'actuator' ) || false !== strpos( $p, 'jenkins' ) || false !== strpos( $p, '/solr' ) ) {
		return true;
	}
	// Old feed / tag archive URLs left over from the previous site.
	if ( preg_match( '#(^|/)(feed|tag)(/|$)#', $p ) || preg_match( '#\.(feed|rss)$#', $p ) ) {
		return true;
	}
	// Old paginated archive URLs (…/page/2/) — the live archives paginate fine,
	// so a 404 here is always a stale link, not a real page.
	if ( preg_match( '#/page/[0-9]+$#', $p ) ) {
		return true;
	}
	// A long hex-only path segment = a scanner/verification token, never a slug.
	foreach ( explode( '/', $p ) as $seg ) {
		if ( preg_match( '#^[a-f0-9]{24,}$#', $seg ) ) {
			return true;
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
	if ( ricoman_404_is_noise( $path ) ) {
		return; // don't log bot/scanner junk.
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

/**
 * Drop any logged 404 that now resolves (renamed slugs, added redirects, the
 * general resolver) and persist the trimmed log. Throttled so the resolver
 * doesn't run on every admin page load. Returns the unresolved count.
 */
function ricoman_404_prune_resolved( $force = false ) {
	$log = (array) get_option( 'ricoman_404_log', array() );
	if ( ! $log ) {
		return 0;
	}
	$changed = false;
	// Always drop bot/scanner noise (cheap; not gated by the resolve throttle),
	// so junk clears from the list immediately and never accumulates.
	foreach ( array_keys( $log ) as $path ) {
		if ( ricoman_404_is_noise( $path ) ) {
			unset( $log[ $path ] );
			$changed = true;
		}
	}
	if ( ! $force && get_transient( 'ricoman_404_pruned' ) ) {
		// Recently pruned — trust the stored log without re-resolving.
		if ( $changed ) {
			update_option( 'ricoman_404_log', $log, false );
		}
		return count( $log );
	}
	foreach ( array_keys( $log ) as $path ) {
		if ( ricoman_path_resolves( $path ) ) {
			unset( $log[ $path ] );
			$changed = true;
		}
	}
	if ( $changed ) {
		update_option( 'ricoman_404_log', $log, false );
	}
	set_transient( 'ricoman_404_pruned', 1, 10 * MINUTE_IN_SECONDS );
	return count( $log );
}

/** Flag unresolved 404s on every admin screen so they get sorted promptly. */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'ricoman_page_ricoman-redirects' === $screen->id ) {
		return; // already on the fix-it page.
	}
	$n = ricoman_404_prune_resolved();
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

	// ---- One-click: rewrite old links baked into saved content ----
	if ( isset( $_GET['rm_fixed'] ) ) {
		$f = max( 0, (int) $_GET['rm_fixed'] );
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html( sprintf( _n( 'Rewrote old links in %s page/post.', 'Rewrote old links in %s pages/posts.', $f, 'ricoman' ), number_format_i18n( $f ) ) )
			. '</p></div>';
	}
	echo '<h2 style="margin-top:24px">' . esc_html__( 'Fix old links in your content', 'ricoman' ) . '</h2>';
	echo '<p class="description" style="max-width:820px">' . esc_html__( 'Visitors are auto-redirected from old addresses, but old links can still sit inside your pages, posts, projects and news (e.g. /product/… or category-prefixed URLs from the old site). This rewrites those links in the saved content so they point straight at the live page — no redirect hop, better for SEO. Safe: it only changes links it can confidently match.', 'ricoman' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Rewrite old links across all your content now?', 'ricoman' ) ) . '\');">';
	echo '<input type="hidden" name="action" value="ricoman_fix_links">';
	wp_nonce_field( 'ricoman_fix_links' );
	submit_button( __( 'Rewrite old links in content', 'ricoman' ), 'secondary', 'submit', false );
	echo '</form>';

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
	ricoman_404_prune_resolved( true );
	$log = (array) get_option( 'ricoman_404_log', array() );
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

/** One-click: rewrite old-base links inside all saved content (fix at source). */
add_action( 'admin_post_ricoman_fix_links', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_fix_links' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$types = array_values( array_filter( array( 'page', 'post', 'product', 'project', 'news' ), 'post_type_exists' ) );
	$ids   = get_posts( array(
		'post_type'      => $types,
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		's'              => '', // no search; we filter by content below.
	) );
	$fixed = 0;
	foreach ( $ids as $id ) {
		$c = get_post_field( 'post_content', $id );
		if ( '' === $c || false === strpos( $c, 'href=' ) ) {
			continue;
		}
		$new = ricoman_rewrite_old_links( $c );
		if ( $new !== $c ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => $new ) );
			$fixed++;
		}
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-redirects', 'rm_fixed' => $fixed ), admin_url( 'admin.php' ) ) );
	exit;
} );

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

/* ---- Launch 301 map (from the migrated old-URL list) ----------------------
 * Products renamed / merged during the rebuild to slugs the general resolver
 * can't guess (e.g. /product/twisted-linear-lighting → Flowplus), plus a few
 * old category-prefixed bases. Old→new confirmed by the Ricoman team from
 * Search Console. Seeded ONCE per site into the editable redirect map (below),
 * add-only — so they appear in Ricoman → Links & Redirects, can be edited or
 * removed there, and a manual edit is never overwritten. Because the manual
 * engine runs before the general 404 resolver, an entry here also overrides a
 * wrong auto-guess (e.g. estrella-pro-round-aperture). Keys are bare paths (no
 * leading/trailing slash); destinations are root-relative. */
function ricoman_launch_redirect_map() {
	return array(
		// Renamed / merged products — old singular /product/ base.
		'product/e-pro-recessed-linear-downlight'        => '/products/surface-cellular-downlight/',
		'product/twisted-linear-lighting'                => '/products/flowplus/',
		'product/abstract-suspended-curved-luminaire'    => '/products/flowplus/',
		'product/flow-arc-inwards'                       => '/products/flowplus/',
		'product/flow-2-5m-straight-line'                => '/products/flowplus/',
		'product/downwards-ring-led-light'               => '/products/flowplus/',
		'product/flow-ring-downwards'                    => '/products/flowplus/',
		'product/easy-installation-bulkhead'             => '/products/nexus-switchable-wattage-bulkhead/',
		'product/wall-wash-modular-panel-o2-wallwasher'  => '/products/duoline/',
		'product/estrella-rgb-linear'                    => '/products/rgb-linear-light-estrella-pro/',
		'product/estrella-pro-round-aperture'            => '/products/architectural-linear-lighting-estrella-pro-square-aperture/',
		'product/r3-firerated-ip65-tunable-downlight'    => '/products/tunable-white-ip65-downlight/',
		'product/verve-panel-1200x300-ugr'               => '/products/core-ugr-1200x300/',
		// Old category-prefixed / old-base URLs.
		'led-panel-lights/pure-panel-1200x600-led-panel' => '/products/core-ugr-1200x600/',
		'led-panel-lights/pure-panel-600x600'            => '/products/pure-panel-500x500-led-panel/',
		'linear-lighting/estrella-recessed'              => '/products/linear-led-lighting-system-estrella-pro-opal/',
		'product-cat/tunable-white-lighting'             => '/products/',
		// Slugs renamed again / retired under the new /products/ base.
		'products/estrella'                              => '/products/estrella-linear-lighting/',
		'products/square-led-wallwasher-vela'            => '/product-category/led-downlights/',
		'products/kontor-free-standing-light'            => '/products/',
		'products/fusion-ii'                             => '/products/ugr19-tpa-backlit-panel-core-ugr/',
	);
}
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_launch_redirects_v1' ) ) {
		return;
	}
	$launch = ricoman_launch_redirect_map();
	$map    = ricoman_redirects_get();
	foreach ( $launch as $from => $to ) {
		if ( ! isset( $map[ $from ] ) ) { // add-only: never clobber a manual edit.
			$map[ $from ] = $to;
		}
	}
	update_option( 'ricoman_redirects', $map );
	// These are handled now — clear them from the 404 watch log if present.
	$log     = (array) get_option( 'ricoman_404_log', array() );
	$changed = false;
	foreach ( array_keys( $launch ) as $from ) {
		if ( isset( $log[ $from ] ) ) {
			unset( $log[ $from ] );
			$changed = true;
		}
	}
	if ( $changed ) {
		update_option( 'ricoman_404_log', $log, false );
	}
	update_option( 'ricoman_launch_redirects_v1', 1 );
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

<?php
/**
 * Estrella range hub — turns the consolidated Estrella product page into a rich
 * "hero" range page and aggregates downloads across every optic.
 *
 * The team consolidated all Estrella optics (Opal, Square Aperture, Wallwash,
 * Dark/White Louvre, Microprismatic, RGBW) onto ONE product, with every order
 * code as a variant-product of that page. This file:
 *
 *   1. Renames the legacy wallwash slug/title to "Estrella Linear Lighting"
 *      (one-time, with an automatic 301 from the old URL).
 *   2. Adds a brochure-derived range section (intro + applications, an optics
 *      comparison grid, smart-lighting / HCL / Casambi, and mounting / finishes
 *      / spec / warranty) — rendered as the product's "range" section.
 *   3. Streams a single "all optics" LDT ZIP (rm_ldtzip) gathering the LDT file
 *      from every variant on the page, and adds the download link to the hero.
 *
 * Content is sourced from the Estrella Pro brochure (v.6).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * Hub detection
 * ======================================================================== */

/** Slugs that identify the Estrella range hub product (filterable). */
function ricoman_estrella_hub_slugs() {
	return apply_filters( 'ricoman_estrella_hub_slugs', array(
		'estrella-linear-lighting',
		'linear-led-wall-washer-estrella-pro-wallwash',
	) );
}

/** Is this product the consolidated Estrella range hub? */
function ricoman_estrella_is_hub( $pid ) {
	$pid = (int) $pid;
	if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
		return false;
	}
	if ( '1' === (string) get_post_meta( $pid, '_ricoman_estrella_hub', true ) ) {
		return true;
	}
	return in_array( (string) get_post_field( 'post_name', $pid ), ricoman_estrella_hub_slugs(), true );
}

/* ===========================================================================
 * One-time slug / title migration  (issue 1)
 * ======================================================================== */

add_action( 'admin_init', function () {
	if ( get_option( 'rm_estrella_setup_v1' ) ) {
		return;
	}
	$p = get_page_by_path( 'linear-led-wall-washer-estrella-pro-wallwash', OBJECT, 'product' );
	if ( $p ) {
		$old_slug = $p->post_name;
		wp_update_post( array(
			'ID'         => $p->ID,
			'post_title' => 'Estrella Linear Lighting',
			'post_name'  => 'estrella-linear-lighting',
		) );
		// Preserve the old URL: WordPress' own old-slug redirect reads this meta,
		// and our 404 resolver (inc/redirects.php) uses it too.
		if ( $old_slug && 'estrella-linear-lighting' !== $old_slug ) {
			add_post_meta( $p->ID, '_wp_old_slug', $old_slug );
		}
		update_post_meta( $p->ID, '_ricoman_estrella_hub', '1' );
		update_post_meta( $p->ID, '_rm_secver', time() );
	}
	update_option( 'rm_estrella_setup_v1', 1 );
} );

/* ===========================================================================
 * All-optics LDT ZIP  (issue 3)
 * ======================================================================== */

/** Resolve a file URL to a local absolute path, or '' if not local. */
function ricoman_url_to_path( $url ) {
	$url = (string) $url;
	if ( '' === $url ) {
		return '';
	}
	$att = attachment_url_to_postid( $url );
	if ( $att ) {
		$p = get_attached_file( $att );
		if ( $p && is_file( $p ) ) {
			return $p;
		}
	}
	$up = wp_upload_dir();
	if ( ! empty( $up['baseurl'] ) && 0 === strpos( $url, $up['baseurl'] ) ) {
		$p = $up['basedir'] . substr( $url, strlen( $up['baseurl'] ) );
		if ( is_file( $p ) ) {
			return $p;
		}
	}
	return '';
}

/**
 * Collect ZIP entries (one per distinct LDT file) from every variant on a hub
 * product, named by their part code. Local files are added by path; remote URLs
 * are fetched (small files, capped) so migrated/external LDTs still pack.
 */
function ricoman_estrella_ldt_entries( $pid ) {
	if ( ! post_type_exists( 'variant-product' ) ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'meta_query'     => array( array( 'key' => 'parent_product', 'value' => (string) (int) $pid ) ),
	) );
	$entries = array();
	$seen    = array();
	$names   = array();
	$remote  = 0;
	foreach ( $q->posts as $vp ) {
		$vid = $vp->ID;
		$url = function_exists( 'ricoman_pf_fileurl' ) ? ricoman_pf_fileurl( ricoman_pf_get( $vid, 'download_led' ) ) : '';
		if ( '' === (string) $url || isset( $seen[ $url ] ) ) {
			continue;
		}
		$seen[ $url ] = true;

		$code = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $vid, 'part_code' ) : '';
		if ( '' === (string) $code ) {
			$code = ricoman_pf_get( $vid, 'order_code' );
		}
		if ( '' === (string) $code ) {
			$code = get_the_title( $vid );
		}
		$ext  = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$ext  = $ext ? $ext : 'ldt';
		$base = sanitize_file_name( (string) $code );
		$name = $base . '.' . $ext;
		$i    = 2;
		while ( isset( $names[ strtolower( $name ) ] ) ) {
			$name = $base . '-' . $i . '.' . $ext;
			$i++;
		}
		$names[ strtolower( $name ) ] = true;

		$path = ricoman_url_to_path( $url );
		if ( $path ) {
			$entries[] = array( 'path' => $name, 'file' => $path );
		} elseif ( $remote < 200 ) {
			// Remote/migrated file — fetch it (LDT/IES files are tiny).
			$res = wp_remote_get( $url, array( 'timeout' => 8, 'sslverify' => false ) );
			$body = is_wp_error( $res ) ? '' : wp_remote_retrieve_body( $res );
			if ( '' !== $body && strlen( $body ) < 5 * 1024 * 1024 ) {
				$entries[] = array( 'path' => $name, 'data' => $body );
				$remote++;
			}
		}
	}
	return $entries;
}

/** Does the hub have any LDT files to offer? (cheap existence check) */
function ricoman_estrella_has_ldt( $pid ) {
	if ( ! post_type_exists( 'variant-product' ) ) {
		return false;
	}
	$q = new WP_Query( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			'relation' => 'AND',
			array( 'key' => 'parent_product', 'value' => (string) (int) $pid ),
			array( 'key' => 'download_led', 'value' => '', 'compare' => '!=' ),
		),
	) );
	return ! empty( $q->posts );
}

/** Stream the all-optics LDT ZIP. Gated client-side like other downloads. */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['rm_ldtzip'] ) ) {
		return;
	}
	$pid = (int) $_GET['rm_ldtzip'];
	if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
		wp_die( esc_html__( 'Product not found.', 'ricoman' ) );
	}
	if ( ! function_exists( 'ricoman_build_pack_zip' ) ) {
		wp_die( esc_html__( 'Download unavailable.', 'ricoman' ) );
	}
	$entries = ricoman_estrella_ldt_entries( $pid );
	if ( ! $entries ) {
		wp_die( esc_html__( 'No LDT files are on file for this range yet.', 'ricoman' ) );
	}
	$entries[] = array(
		'path' => '00 - read me.txt',
		'data' => get_the_title( $pid ) . " — photometric (LDT) files\n" . str_repeat( '=', 50 ) . "\n\n"
			. "One LDT file per optic / order code in this range.\n"
			. 'Generated ' . date_i18n( 'j M Y H:i' ) . ' — ' . get_bloginfo( 'name' ) . "\n",
	);
	$zip = ricoman_build_pack_zip( $entries );
	if ( ! $zip ) {
		wp_die( esc_html__( 'Could not build the LDT pack.', 'ricoman' ) );
	}
	while ( ob_get_level() ) {
		ob_end_clean();
	}
	$fname = sanitize_file_name( get_the_title( $pid ) ) . ' - LDT files.zip';
	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
	header( 'Content-Length: ' . strlen( $zip ) );
	echo $zip; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

/** Add the "all optics LDT" link to the hub's hero downloads list. */
add_filter( 'ricoman_pf_downloads_html', function ( $dl, $pid ) {
	if ( ! ricoman_estrella_is_hub( $pid ) || ! ricoman_estrella_has_ldt( $pid ) ) {
		return $dl;
	}
	$url  = add_query_arg( 'rm_ldtzip', (int) $pid, home_url( '/' ) );
	$link = '<a href="' . esc_url( $url ) . '" download rel="noopener"><span class="rm-dl-lbl">'
		. esc_html__( 'All optics — LDT files', 'ricoman' ) . '</span> <span class="rm-dl-sub">'
		. esc_html__( '(ZIP) · every optic', 'ricoman' ) . '</span></a>';
	return $link . $dl;
}, 10, 2 );

/* ===========================================================================
 * Brochure-derived range content  (issues 2 + 4)
 * ======================================================================== */

/** The Estrella range section HTML (empty unless this product is the hub). */
function ricoman_estrella_range_section( $pid ) {
	if ( ! ricoman_estrella_is_hub( $pid ) ) {
		return '';
	}
	$css = ricoman_estrella_styles();

	/* --- 1. Range intro + applications --------------------------------- */
	$apps = array( 'Office', 'Commercial', 'Meeting', 'Residential', 'Hospitality', 'Education', 'Retail', '& more' );
	$appchips = '';
	foreach ( $apps as $a ) {
		$appchips .= '<li>' . esc_html( $a ) . '</li>';
	}
	$pillars = array(
		array( 'Contemporary design', 'A slim architectural linear profile for standalone luminaires or continuous light-lines.' ),
		array( 'Flexible solutions', 'Seven optics, four lengths, L / T / + shapes, surface, suspended, recessed, track or wall.' ),
		array( 'Intelligent controls', 'Tunable white 2700K–6500K with Casambi for human-centric, circadian lighting.' ),
	);
	$pillhtml = '';
	foreach ( $pillars as $p ) {
		$pillhtml .= '<div class="rm-es-pillar"><h3>' . esc_html( $p[0] ) . '</h3><p>' . esc_html( $p[1] ) . '</p></div>';
	}
	$intro = '<div class="rm-section rm-es-intro"><div class="rm-pp-wrap">'
		. '<p class="rm-eyebrow">The linear lighting evolution</p>'
		. '<h2 class="rm-shead">One range, every linear lighting solution</h2>'
		. '<p class="rm-es-lead">Estrella Pro is the evolution of our hugely popular Estrella linear range — the ultimate in linear profile luminaires. Used as a continuous lighting system or as standalone luminaires, it can be configured to deliver architectural lighting for the majority of applications, with seven optical solutions, an optional direct/indirect output and an integrated tiltable spotlight.</p>'
		. '<div class="rm-es-pillars">' . $pillhtml . '</div>'
		. '<div class="rm-es-apps"><span class="rm-es-apps-lbl">Designed for</span><ul>' . $appchips . '</ul></div>'
		. '</div></div>';

	/* --- 2. Optics comparison grid ------------------------------------- */
	$optics = array(
		array( 'Opal', 'Standard opal diffuser for general-area lighting with high uniformity.', '120', '&lt;24', '100°' ),
		array( 'Square Aperture', 'Cellular system of square glare-control cells for optimal glare control.', '125', '&lt;14', '90°' ),
		array( 'Wallwash', 'Reflector optics for effective wall-washer distribution onto a wall or focal point.', '116', '&lt;22', '60°' ),
		array( 'Dark Louvre', 'CAT2-style dark louvre grid for optimal glare control.', '75', '&lt;17', '70°' ),
		array( 'White Louvre', 'CAT2-style white louvre grid for a high level of glare control.', '104', '&lt;21', '70°' ),
		array( 'Microprismatic', 'Microprismatic diffuser, ideal for office and education glare control.', '100', '&lt;19', '100°' ),
		array( 'RGBW', 'Continuous or standalone RGBW colour-changing linear system.', '—', '—', '100°' ),
	);
	$ocards = '';
	foreach ( $optics as $o ) {
		$ocards .= '<div class="rm-es-optic"><h3>' . esc_html( $o[0] ) . '</h3><p>' . esc_html( $o[1] ) . '</p>'
			. '<dl class="rm-es-ometrics">'
			. '<div><dt>Efficacy</dt><dd>' . ( '—' === $o[2] ? '—' : esc_html( $o[2] ) . ' lm/W' ) . '</dd></div>'
			. '<div><dt>UGR</dt><dd>' . wp_kses( $o[3], array() ) . '</dd></div>'
			. '<div><dt>Beam</dt><dd>' . esc_html( $o[4] ) . '</dd></div>'
			. '</dl></div>';
	}
	$opticsec = '<div class="rm-section rm-es-optics"><div class="rm-pp-wrap">'
		. '<h2 class="rm-shead">Seven optics, your specification</h2>'
		. '<p class="rm-es-sub">Choose the optic that suits the space — then configure length, output, colour temperature, finish and controls in the table above.</p>'
		. '<div class="rm-es-opticgrid">' . $ocards . '</div>'
		. '</div></div>';

	/* --- 3. Smart lighting / HCL / Casambi ----------------------------- */
	$casambi = array(
		array( 'Grouping', 'Group luminaires as easily as grouping apps on your phone.' ),
		array( 'Scenes', 'Create lighting situations for different occasions.' ),
		array( 'Animations', 'Fade from one scene to the next with set durations.' ),
		array( 'Daylight sensor', 'Dim when daylight is available to save energy.' ),
		array( 'Occupancy sensor', 'Light only when needed and cut energy bills.' ),
		array( 'Calendar & timer', 'Activate scenes on a schedule, season by season.' ),
	);
	$casitems = '';
	foreach ( $casambi as $c ) {
		$casitems .= '<div class="rm-es-feat"><h4>' . esc_html( $c[0] ) . '</h4><p>' . esc_html( $c[1] ) . '</p></div>';
	}
	$hcl = array(
		array( 'Ergonomic', 'Mimics daylight to enhance wellbeing and circadian rhythm.' ),
		array( 'Biological', 'Uses light of different colours to create ideal environments.' ),
		array( 'Therapeutic', 'Supports wellbeing in care and clinical settings.' ),
	);
	$hclitems = '';
	foreach ( $hcl as $h ) {
		$hclitems .= '<div class="rm-es-feat"><h4>' . esc_html( $h[0] ) . '</h4><p>' . esc_html( $h[1] ) . '</p></div>';
	}
	$smartsec = '<div class="rm-section rm-es-smart"><div class="rm-pp-wrap">'
		. '<p class="rm-eyebrow">Smart &amp; human-centric</p>'
		. '<h2 class="rm-shead">Intelligent control with Casambi</h2>'
		. '<p class="rm-es-sub">Specify tunable white (2700K–6500K) with Casambi to deliver human-centric lighting that works with the body’s circadian rhythm.</p>'
		. '<div class="rm-es-feats">' . $casitems . '</div>'
		. '<h3 class="rm-es-subhead">Human-centric lighting</h3>'
		. '<div class="rm-es-feats rm-es-feats--3">' . $hclitems . '</div>'
		. '</div></div>';

	/* --- 4. Mounting / finishes / spec / warranty ---------------------- */
	$mounts = array(
		array( 'Ceiling mounted', 'Surface mounting clips', 'R33-20' ),
		array( 'Suspended', '3m suspension kit', 'R33-07' ),
		array( 'Track mounted', 'Track mounting kit', 'R33-09' ),
		array( 'Wall mounted', 'Side wall mounting kit', 'R33-10' ),
		array( 'Solid rod', 'Solid rod mounting kit', 'R33-12' ),
		array( 'Grid ceiling', 'T-bar grid ceiling kit', 'R33-08' ),
	);
	$mhtml = '';
	foreach ( $mounts as $m ) {
		$mhtml .= '<div class="rm-es-mount"><h4>' . esc_html( $m[0] ) . '</h4><p>' . esc_html( $m[1] ) . '</p><span class="rm-es-code">' . esc_html( $m[2] ) . '</span></div>';
	}
	$finishes = array(
		array( 'Matte White', 'RAL 9016' ),
		array( 'Matte Black', 'RAL 9005' ),
		array( 'Custom RAL', 'Made to order' ),
	);
	$fhtml = '';
	foreach ( $finishes as $f ) {
		$fhtml .= '<div class="rm-es-finish"><span class="rm-es-fsw rm-es-fsw--' . sanitize_html_class( strtolower( str_replace( ' ', '', $f[0] ) ) ) . '"></span><strong>' . esc_html( $f[0] ) . '</strong><span>' . esc_html( $f[1] ) . '</span></div>';
	}
	$spec = array(
		array( 'IP rating', 'IP40 (IP54 optional, single units)' ),
		array( 'Warranty', '5 years' ),
		array( 'CRI', '&gt;90 Ra' ),
		array( 'LEDs', 'Single-bin Samsung' ),
		array( 'Lifetime', 'L80 B50 — 50,000 hours' ),
		array( 'Dimming', 'DALI available' ),
		array( 'Diffuser', 'Polycarbonate, TP(a) rated' ),
		array( 'Voltage', 'AC 220–240V' ),
		array( 'Operating temp', '−20°C to +45°C' ),
		array( 'Impact', 'IK08' ),
		array( 'Colour temp', '3000K / 4000K / 5000K / Tunable / RGBW' ),
		array( 'Emergency', '3hr, DALI (EMPRO) or Z10 options' ),
	);
	$shtml = '';
	foreach ( $spec as $s ) {
		$shtml .= '<div class="rm-es-specrow"><dt>' . esc_html( $s[0] ) . '</dt><dd>' . wp_kses( $s[1], array() ) . '</dd></div>';
	}
	$badges = array( '5 yr warranty', 'Flicker free', 'RoHS', 'IK08', 'IP40', 'TP(a)' );
	$bhtml  = '';
	foreach ( $badges as $b ) {
		$bhtml .= '<li>' . esc_html( $b ) . '</li>';
	}
	$techsec = '<div class="rm-section rm-es-tech"><div class="rm-pp-wrap">'
		. '<h2 class="rm-shead">Mounting, finishes &amp; specification</h2>'
		. '<h3 class="rm-es-subhead">Multiple mounting options</h3><div class="rm-es-mounts">' . $mhtml . '</div>'
		. '<div class="rm-es-twocol">'
		. '<div><h3 class="rm-es-subhead">Finishes</h3><div class="rm-es-finishes">' . $fhtml . '</div></div>'
		. '<div><h3 class="rm-es-subhead">Technical specification</h3><dl class="rm-es-spec">' . $shtml . '</dl></div>'
		. '</div>'
		. '<ul class="rm-es-badges">' . $bhtml . '</ul>'
		. '</div></div>';

	return $css . '<div class="rm-es-range">' . $intro . $opticsec . $smartsec . $techsec . '</div>';
}

/** Range section styles (printed once). */
function ricoman_estrella_styles() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style>'
		. '.rm-es-range .rm-shead{font-size:clamp(1.5rem,3vw,2.1rem);margin:0 0 .4em}'
		. '.rm-es-range .rm-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.78rem;font-weight:700;color:#b8862f;margin:0 0 .4em}'
		. '.rm-es-lead,.rm-es-sub{font-size:1.05rem;line-height:1.6;color:#3c4250;max-width:62ch}'
		. '.rm-es-pillars{display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin:28px 0 8px}'
		. '.rm-es-pillar{background:#fff;border:1px solid #e7e9ee;border-radius:14px;padding:20px 22px}'
		. '.rm-es-pillar h3{margin:0 0 .35em;font-size:1.08rem}'
		. '.rm-es-pillar p{margin:0;color:#5a6170;font-size:.95rem;line-height:1.55}'
		. '.rm-es-apps{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin-top:22px}'
		. '.rm-es-apps-lbl{font-weight:700;color:#16161a;margin-right:4px}'
		. '.rm-es-apps ul{display:flex;flex-wrap:wrap;gap:8px;list-style:none;margin:0;padding:0}'
		. '.rm-es-apps li{background:#16161a;color:#fff;border-radius:999px;padding:5px 14px;font-size:.82rem;font-weight:600}'
		. '.rm-es-optics{background:#f6f7f9}'
		. '.rm-es-opticgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:18px;margin-top:24px}'
		. '.rm-es-optic{background:#fff;border:1px solid #e7e9ee;border-radius:14px;padding:20px}'
		. '.rm-es-optic h3{margin:0 0 .4em;font-size:1.12rem}'
		. '.rm-es-optic p{margin:0 0 16px;color:#5a6170;font-size:.92rem;line-height:1.5;min-height:3.8em}'
		. '.rm-es-ometrics{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0}'
		. '.rm-es-ometrics dt{font-size:.66rem;text-transform:uppercase;letter-spacing:.06em;color:#8a909c;margin:0}'
		. '.rm-es-ometrics dd{margin:2px 0 0;font-weight:700;font-size:.95rem;color:#16161a}'
		. '.rm-es-feats{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:22px}'
		. '.rm-es-feats--3{grid-template-columns:repeat(3,1fr)}'
		. '.rm-es-feat{border-left:3px solid #b8862f;padding:2px 0 2px 14px}'
		. '.rm-es-feat h4{margin:0 0 .3em;font-size:1rem}'
		. '.rm-es-feat p{margin:0;color:#5a6170;font-size:.9rem;line-height:1.5}'
		. '.rm-es-subhead{font-size:1.15rem;margin:30px 0 14px}'
		. '.rm-es-mounts{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}'
		. '.rm-es-mount{background:#fff;border:1px solid #e7e9ee;border-radius:12px;padding:16px}'
		. '.rm-es-mount h4{margin:0 0 .25em;font-size:1rem}'
		. '.rm-es-mount p{margin:0 0 10px;color:#5a6170;font-size:.88rem}'
		. '.rm-es-code{display:inline-block;background:#f0f1f4;border-radius:6px;padding:2px 8px;font:600 .8rem/1.4 monospace;color:#16161a}'
		. '.rm-es-twocol{display:grid;grid-template-columns:1fr 1.4fr;gap:30px;margin-top:8px}'
		. '.rm-es-finishes{display:flex;flex-direction:column;gap:12px}'
		. '.rm-es-finish{display:flex;align-items:center;gap:12px}'
		. '.rm-es-fsw{width:30px;height:30px;border-radius:8px;border:1px solid #cfd3da;flex:0 0 auto}'
		. '.rm-es-fsw--mattewhite{background:#f4f4f2}.rm-es-fsw--matteblack{background:#1c1c1c}'
		. '.rm-es-fsw--customral{background:linear-gradient(135deg,#e54d4d,#4d7be5,#4de58f)}'
		. '.rm-es-finish strong{font-size:.95rem}.rm-es-finish span{color:#8a909c;font-size:.85rem;margin-left:auto}'
		. '.rm-es-spec{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;margin:0}'
		. '.rm-es-specrow{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-bottom:1px solid #ebedf1}'
		. '.rm-es-specrow dt{color:#8a909c;font-size:.85rem;margin:0}'
		. '.rm-es-specrow dd{margin:0;font-weight:600;font-size:.88rem;text-align:right}'
		. '.rm-es-badges{display:flex;flex-wrap:wrap;gap:10px;list-style:none;margin:26px 0 0;padding:0}'
		. '.rm-es-badges li{border:1.5px solid #16161a;border-radius:8px;padding:6px 14px;font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}'
		. '@media(max-width:860px){.rm-es-pillars,.rm-es-feats,.rm-es-feats--3,.rm-es-spec,.rm-es-twocol{grid-template-columns:1fr}}'
		. '</style>';
}

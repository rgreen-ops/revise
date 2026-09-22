<?php
/**
 * Bespoke prospect / partner landing pages.
 *
 * Standalone, highly-designed "why partner with Ricoman" pages served at a
 * top-level slug (e.g. /OBI, /office-innovations) and sent directly to a named
 * account. Rendered entirely in code (self-contained HTML) so they don't touch
 * the CMS, are version-controlled, and deploy with the theme. Always noindexed
 * (private outreach, not for public search); the slugs are also in
 * ricoman_seo_noindex_page_uris() as belt-and-braces.
 *
 * To add a prospect: add one row to ricoman_partner_pages(). That's it.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The prospects. Key = the URL slug (lower-case). */
function ricoman_partner_pages() {
	return array(
		'obi' => array(
			'name'  => 'OBI',
			'logo'  => 'partners/obi.png',       // drop the prospect's logo here; falls back to their name if absent.
			'intro' => 'Four teams working as one across transactions, consultancy, workplace design and studio, building workspaces people are proud of. We make the lighting that finishes them, engineered in-house in Manchester, right on your doorstep, and kind to people and the planet.',
		),
		'office-innovations' => array(
			'name'  => 'Office Innovations',
			'logo'  => 'partners/office-innovations.png',
			'intro' => 'You turn empty floors into workspaces that work. We make the lighting that brings them to life, engineered in-house, in Manchester, right on your doorstep.',
		),
	);
}

/** The prospect's own logo (on a light chip) if we have the file, else their name. */
function ricoman_partner_logo_html( array $p, $h = 40 ) {
	$file = isset( $p['logo'] ) ? (string) $p['logo'] : '';
	if ( '' !== $file && file_exists( get_theme_file_path( 'assets/' . $file ) ) ) {
		return '<img class="plogo" style="height:' . (int) $h . 'px" src="' . esc_url( get_theme_file_uri( 'assets/' . $file ) ) . '" alt="' . esc_attr( $p['name'] ) . '">';
	}
	return '<span class="pname">' . esc_html( $p['name'] ) . '</span>';
}

/** Real project cards (office sector first), each: title + image URL + link. */
function ricoman_partner_project_cards( $limit = 9, $slugs = array() ) {
	if ( ! post_type_exists( 'project' ) ) {
		return array();
	}
	// Hand-picked mode: resolve the given project slugs, in order.
	if ( ! empty( $slugs ) ) {
		$fb    = array( 'hero-betfred-1.webp', 'rico-office-fitout.webp', 'rico-kingsgate.webp', 'rico-betfred7.webp', 'office2.webp', 'rico-office-render.webp', 'office3.webp', 'estrella-lounge.webp', 'rico-office.webp' );
		$cards = array();
		$i     = 0;
		foreach ( $slugs as $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'project' );
			if ( ! $post ) { continue; }
			$url = get_the_post_thumbnail_url( $post->ID, 'large' );
			if ( ! $url ) { $url = get_theme_file_uri( 'assets/images/' . $fb[ $i % count( $fb ) ] ); }
			$cards[] = array( 'title' => get_the_title( $post ), 'url' => $url, 'link' => get_permalink( $post ) );
			$i++;
		}
		return array_slice( $cards, 0, $limit );
	}
	$args = array( 'post_type' => 'project', 'post_status' => 'publish', 'numberposts' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true );
	// Prefer the office sector if the taxonomy + term exist.
	$tax = '';
	foreach ( array( 'sector', 'project-cat', 'project_cat', 'project-sector', 'sectors' ) as $t ) {
		if ( taxonomy_exists( $t ) ) { $tax = $t; break; }
	}
	if ( $tax ) {
		foreach ( array( 'office-lighting', 'office', 'offices', 'workspace', 'workplace' ) as $s ) {
			$term = get_term_by( 'slug', $s, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				$args['tax_query'] = array( array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $term->term_id ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				break;
			}
		}
	}
	$posts = get_posts( $args );
	if ( count( $posts ) < $limit ) { // top up with the newest projects of any sector.
		$more  = get_posts( array( 'post_type' => 'project', 'post_status' => 'publish', 'numberposts' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true, 'exclude' => wp_list_pluck( $posts, 'ID' ) ) );
		$posts = array_slice( array_merge( $posts, $more ), 0, $limit );
	}
	$fb    = array( 'hero-betfred-1.webp', 'rico-office-fitout.webp', 'rico-kingsgate.webp', 'rico-betfred7.webp', 'office2.webp', 'rico-office-render.webp', 'office3.webp', 'estrella-lounge.webp', 'rico-office.webp' );
	$cards = array();
	$i     = 0;
	foreach ( $posts as $post ) {
		$url = get_the_post_thumbnail_url( $post->ID, 'large' );
		if ( ! $url ) {
			$url = get_theme_file_uri( 'assets/images/' . $fb[ $i % count( $fb ) ] );
		}
		$cards[] = array( 'title' => get_the_title( $post ), 'url' => $url, 'link' => get_permalink( $post ) );
		$i++;
	}
	return $cards;
}

/** A product's own featured image by slug, with a theme fallback. */
function ricoman_product_image_url( $slug, $fallback = '' ) {
	$p = get_page_by_path( $slug, OBJECT, 'product' );
	if ( $p ) {
		$u = get_the_post_thumbnail_url( $p->ID, 'large' );
		if ( $u ) {
			return $u;
		}
	}
	return $fallback ? get_theme_file_uri( 'assets/images/' . $fallback ) : '';
}

/** Intercept the request early and render the bespoke page. */
add_action( 'template_redirect', function () {
	$path = strtolower( trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) );
	$all  = ricoman_partner_pages();
	if ( '' === $path || ! isset( $all[ $path ] ) ) {
		return;
	}
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
		nocache_headers();
	}
	status_header( 200 );
	ricoman_render_partner_page( $all[ $path ] );
	exit;
}, 1 );

/** Handle the on-page contact form; emails the enquiry to Richard. */
add_action( 'admin_post_nopriv_ricoman_partner_contact', 'ricoman_partner_contact_submit' );
add_action( 'admin_post_ricoman_partner_contact', 'ricoman_partner_contact_submit' );
function ricoman_partner_contact_submit() {
	$source = isset( $_POST['rp_source'] ) ? esc_url_raw( wp_unslash( $_POST['rp_source'] ) ) : '';
	$back   = wp_validate_redirect( $source, wp_get_referer() ? wp_get_referer() : home_url( '/' ) );
	if ( ! isset( $_POST['rp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rp_nonce'] ) ), 'ricoman_partner_contact' ) ) {
		wp_safe_redirect( $back );
		exit;
	}
	// Honeypot: real people leave this blank.
	if ( ! empty( $_POST['company_url'] ) ) {
		wp_safe_redirect( add_query_arg( 'sent', '1', $back ) );
		exit;
	}
	$name    = sanitize_text_field( wp_unslash( $_POST['rp_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['rp_email'] ?? '' ) );
	$company = sanitize_text_field( wp_unslash( $_POST['rp_company'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['rp_message'] ?? '' ) );
	$subject = 'Website enquiry: ' . ( '' !== $company ? $company : $name );
	$body    = "New enquiry from your prospect landing page:\n\n"
		. 'Name: ' . $name . "\n"
		. 'Company: ' . $company . "\n"
		. 'Email: ' . $email . "\n"
		. 'Page: ' . $source . "\n\n"
		. "Message:\n" . $message . "\n";
	$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( is_email( $email ) ) {
		$headers[] = 'Reply-To: ' . $name . ' <' . $email . '>';
	}
	wp_mail( 'rgreen@ricoman.com', $subject, $body, $headers );
	wp_safe_redirect( add_query_arg( 'sent', '1', $back ) . '#contact' );
	exit;
}

/** Output the full standalone landing page. */
function ricoman_render_partner_page( array $p ) {
	$name  = $p['name'];
	$intro = $p['intro'];
	$img   = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	$font  = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/fonts/' . $f ) ); };
	$home  = esc_url( home_url( '/' ) );
	$logo  = esc_url( home_url( '/wp-content/uploads/2025/09/header-logo.png' ) );
	$tel   = '0161 877 1399';
	$mail  = 'sales@ricoman.com';
	$wa    = '447802832849'; // WhatsApp (Richard), international format for wa.me.
	// Your named point of contact on the page.
	$rep       = 'Richard Green';
	$rep_mob   = '07802 832 849';
	$rep_mobrw = '07802832849';
	$rep_email = 'rgreen@ricoman.com';
	$self      = home_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/' );
	$sent      = ! empty( $_GET['sent'] ); // phpcs:ignore WordPress.Security.NonceVerification

	// Real projects from the site (office sector first), each with its own photo,
	// name and a link to the live project page. Nine = three rows.
	// Hand-picked project showcase (office / workspace led). Edit this list to
	// change which projects appear, and in what order.
	$curated = array(
		'betfred-headquarters-lighting',
		'gamesroom-gym-lighting-waterhouse-gardens',
		'allianz-leeds',
		'tower-court-coventry',
		'crown-house',
		'st-paul-square',
		'flour-patisserie',
		'flow',
		'rgbw-casambi-gym-lighting',
	);
	$gallery = ricoman_partner_project_cards( 9, $curated );

	// Optional personalisation for 1:1 outreach: add ?to=Andy (or ?name=) to the
	// link you paste into an email/LinkedIn message and the hero greets them by name.
	$greeting = '';
	foreach ( array( 'to', 'name', 'hi' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) { $greeting = (string) wp_unslash( $_GET[ $k ] ); break; } // phpcs:ignore WordPress.Security.NonceVerification
	}
	$greeting = trim( preg_replace( '/\s+/', ' ', preg_replace( "/[^A-Za-z '\-]/", '', $greeting ) ) );
	if ( '' !== $greeting && strlen( $greeting ) <= 32 ) {
		$greeting = ucwords( $greeting );
	} else {
		$greeting = '';
	}

	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ricoman for <?php echo esc_html( $name ); ?> · Commercial lighting, made in Manchester</title>
<style>
@font-face{font-family:Poppins;font-weight:400;font-display:swap;src:url(<?php echo $font( 'poppins-400.woff2' ); ?>) format('woff2')}
@font-face{font-family:Poppins;font-weight:600;font-display:swap;src:url(<?php echo $font( 'poppins-600.woff2' ); ?>) format('woff2')}
@font-face{font-family:Poppins;font-weight:700;font-display:swap;src:url(<?php echo $font( 'poppins-700.woff2' ); ?>) format('woff2')}
:root{--blue:#004899;--ink:#16161a;--paper:#f6f7f9;--line:#e5e8ec}
*{box-sizing:border-box;margin:0}
html{scroll-behavior:smooth}
body{font-family:Poppins,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:var(--ink);background:#fff;line-height:1.6;-webkit-font-smoothing:antialiased}
img{max-width:100%;display:block}
a{color:inherit}
.wrap{max-width:1180px;margin:0 auto;padding:0 24px}
.btn{display:inline-block;padding:15px 30px;border-radius:40px;font-weight:600;text-decoration:none;font-size:1rem;transition:.2s;border:2px solid transparent}
.btn-primary{background:var(--blue);color:#fff}.btn-primary:hover{background:#003471;transform:translateY(-2px)}
.btn-ghost{border-color:rgba(255,255,255,.7);color:#fff}.btn-ghost:hover{background:#fff;color:var(--ink)}
.btn-dark{background:var(--ink);color:#fff}.btn-dark:hover{background:#000;transform:translateY(-2px)}
.btn-wa{background:#25d366;color:#fff}.btn-wa:hover{background:#1ebe5d;transform:translateY(-2px)}
.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:.78rem;font-weight:700;color:var(--blue)}
h1,h2,h3{line-height:1.08;font-weight:700}
/* top bar */
.bar{position:sticky;top:0;z-index:20;background:rgba(22,22,26,.92);backdrop-filter:blur(6px);border-bottom:1px solid rgba(255,255,255,.08)}
.bar .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}
.bar img{height:34px}
.bar .r{display:flex;align-items:center;gap:18px;color:#fff;font-size:.92rem}
.bar .r a{color:#fff;text-decoration:none;font-weight:600}
.bar .pill{background:rgba(255,255,255,.12);padding:6px 14px;border-radius:30px;font-size:.8rem;font-weight:600}
@media(max-width:640px){.bar .pill{display:none}}
/* hero */
.hero{position:relative;min-height:88vh;display:flex;align-items:flex-end;color:#fff;background:#0d0d10}
.hero .bg{position:absolute;inset:0;object-fit:cover;width:100%;height:100%;opacity:.55}
.hero .grad{position:absolute;inset:0;background:linear-gradient(180deg,rgba(13,13,16,.35) 0%,rgba(13,13,16,.15) 40%,rgba(13,13,16,.9) 100%)}
.hero .wrap{position:relative;padding:64px 24px 72px}
.hero h1{font-size:clamp(2.6rem,5.6vw,4.6rem);font-weight:600;letter-spacing:-.02em;line-height:1.06;margin:.12em 0 .5em;max-width:18ch}
.hero p.sub{font-size:clamp(1.05rem,1.8vw,1.3rem);max-width:50ch;color:rgba(255,255,255,.9);font-weight:400;margin-bottom:2em}
.hero .cta{display:flex;gap:14px;flex-wrap:wrap}
.hello{font-size:clamp(1.5rem,3.4vw,2.5rem);font-weight:400;color:#fff;line-height:1.12;letter-spacing:.005em;margin-bottom:.7em}
.who{box-shadow:inset 0 -2px 0 rgba(140,185,255,.9);padding-bottom:1px}
.hello .who{font-weight:600}
.hero h1 .who{box-shadow:inset 0 -5px 0 rgba(140,185,255,.95);padding-bottom:2px}
.kicker{display:inline-block;text-transform:uppercase;letter-spacing:.22em;font-size:.78rem;font-weight:600;color:rgba(255,255,255,.72);margin-bottom:1.1em}
.prep{display:inline-flex;align-items:center;gap:18px;background:#fff;color:var(--ink);padding:12px 22px;border-radius:12px;margin-bottom:30px;box-shadow:0 12px 34px rgba(0,0,0,.28)}
.prep .lbl{font-size:.68rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#8a9099;border-right:1px solid var(--line);padding-right:18px}
.prep .pname{font-weight:700;font-size:clamp(2rem,5vw,2.9rem);color:var(--ink);line-height:1}
.prep .plogo{display:block;width:auto}
/* co-brand line in the closing CTA */
.duo{display:flex;align-items:center;justify-content:center;gap:24px;margin:0 auto 34px;flex-wrap:wrap}
.duo .x{color:#9aa0aa;font-size:2rem}
.duo .lock{display:inline-flex;align-items:center;border-radius:14px;padding:16px 26px}
.duo .rlock{background:var(--blue)}
.duo .rlock img{height:34px;display:block}
.duo .plock{background:#fff;box-shadow:0 10px 30px rgba(0,0,0,.1)}
.duo .pname{font-weight:700;font-size:clamp(1.8rem,4vw,2.4rem);color:var(--ink)}
/* product range grid */
.prodgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:34px}
.prodgrid a{display:block;text-decoration:none;color:inherit}
.prodgrid figure{position:relative;border-radius:14px;overflow:hidden;aspect-ratio:4/5;background:#e9edf1}
.prodgrid img{width:100%;height:100%;object-fit:cover;transition:.5s}
.prodgrid a:hover img{transform:scale(1.06)}
.prodgrid figcaption{position:absolute;left:0;right:0;bottom:0;padding:30px 16px 15px;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.8))}
.prodgrid .pt{display:block;font-weight:700;font-size:1.02rem}
.prodgrid .ps{display:block;font-size:.8rem;opacity:.85;margin-top:2px}
@media(max-width:820px){.prodgrid{grid-template-columns:repeat(2,1fr)}}
/* contact form */
.contactsec{background:var(--paper)}
.wrap.narrow{max-width:720px}
.cintro{font-size:1.12rem;color:#3c4350}
.cform{margin-top:24px;display:grid;gap:16px}
.cform label{display:block;font-weight:600;font-size:.9rem;color:var(--ink)}
.cform input,.cform textarea{width:100%;margin-top:6px;padding:13px 15px;border:1px solid var(--line);border-radius:10px;font:inherit;background:#fff;color:var(--ink)}
.cform input:focus,.cform textarea:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px rgba(0,72,153,.12)}
.cform .row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:560px){.cform .row2{grid-template-columns:1fr}}
.cform .hp{display:none}
.cform button{justify-self:start;margin-top:4px;cursor:pointer}
.sent{background:#e7f6ec;border:1px solid #b6e2c4;color:#1a7f3c;padding:13px 16px;border-radius:10px;font-weight:600;margin-top:18px}
/* stat strip */
.stats{background:var(--blue);color:#fff}
.stats .wrap{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:34px 24px;text-align:center}
.stats .n{font-size:clamp(1.6rem,3.4vw,2.6rem);font-weight:700;line-height:1}
.stats .l{font-size:.86rem;opacity:.9;margin-top:6px}
@media(max-width:720px){.stats .wrap{grid-template-columns:repeat(2,1fr);gap:26px 8px}}
/* section */
section.pad{padding:78px 0}
.lead{max-width:760px}
.lead h2{font-size:clamp(1.9rem,4vw,2.9rem);margin:.25em 0 .5em}
.lead p{font-size:1.15rem;color:#3c4350}
/* projects grid */
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:38px}
.grid .pcard{display:block;text-decoration:none}
.grid figure{position:relative;border-radius:14px;overflow:hidden;aspect-ratio:4/3;background:#e9edf1}
.grid img{width:100%;height:100%;object-fit:cover;transition:.5s}
.grid figure:hover img{transform:scale(1.06)}
.grid figcaption{position:absolute;left:0;right:0;bottom:0;padding:26px 16px 12px;color:#fff;font-size:.85rem;font-weight:600;background:linear-gradient(transparent,rgba(0,0,0,.7))}
@media(max-width:820px){.grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:520px){.grid{grid-template-columns:1fr}}
/* capabilities alternating rows */
.cap{display:grid;grid-template-columns:1fr 1fr;gap:52px;align-items:center;margin:72px 0}
.cap:nth-child(even){direction:rtl}.cap:nth-child(even)>*{direction:ltr}
.cap img{border-radius:16px;aspect-ratio:5/4;object-fit:cover;width:100%;box-shadow:0 20px 50px rgba(16,20,40,.14)}
.cap h3{font-size:clamp(1.5rem,2.8vw,2.1rem);margin:.2em 0 .45em}
.cap p{color:#3c4350;font-size:1.08rem}
.cap .tag{color:var(--blue);font-weight:700;font-size:.8rem;letter-spacing:.12em;text-transform:uppercase}
.cap ul{margin:1em 0 0;padding-left:1.1em;color:#3c4350}
.cap li{margin:.3em 0}
@media(max-width:860px){.cap,.cap:nth-child(even){grid-template-columns:1fr;direction:ltr;gap:26px;margin:52px 0}}
/* biophilic showcase */
.biophilic{background:#0c1a30;color:#fff;overflow:hidden}
.biophilic .wrap{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center;padding:0 24px}
.biophilic .txt{padding:80px 0}
.biophilic .eyebrow{color:#86b3ff}
.biophilic h2{font-size:clamp(2rem,4.4vw,3rem);margin:.3em 0 .5em}
.biophilic p{color:#c4d2ea;font-size:1.12rem;max-width:48ch;margin:0 0 1em}
.biophilic .media{position:relative;align-self:stretch;min-height:460px}
.biophilic .media img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
@media(max-width:860px){.biophilic .wrap{grid-template-columns:1fr;gap:0}.biophilic .media{min-height:340px;order:-1}.biophilic .txt{padding:52px 0}}
/* why band */
.band{background:var(--ink);color:#fff}
.band .wrap{padding:74px 24px;text-align:center}
.band h2{font-size:clamp(1.9rem,4vw,3rem);max-width:20ch;margin:0 auto .5em}
.band p{max-width:60ch;margin:0 auto 1.6em;opacity:.9;font-size:1.12rem}
/* cta */
.close{background:var(--paper);text-align:center}
.close .wrap{padding:86px 24px}
.close h2{font-size:clamp(2rem,4.5vw,3.2rem);margin-bottom:.35em}
.close p{font-size:1.2rem;color:#3c4350;max-width:56ch;margin:0 auto 2em}
.close .cta{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
/* footer */
footer{background:#0d0d10;color:#aeb4bd;font-size:.92rem}
footer .wrap{padding:52px 24px;display:flex;flex-wrap:wrap;gap:24px;justify-content:space-between;align-items:center}
footer .flogo{height:34px;opacity:.95}
footer a{color:#fff;text-decoration:none}
footer .links a{margin-left:20px}
</style>
</head>
<body>

<div class="bar"><div class="wrap">
	<a href="<?php echo $home; ?>"><img src="<?php echo $logo; ?>" alt="Ricoman Lighting"></a>
	<div class="r"><span class="pill">🏭 Made in Manchester</span><a href="tel:<?php echo esc_attr( $rep_mobrw ); ?>"><?php echo esc_html( $rep_mob ); ?></a></div>
</div></div>

<header class="hero">
	<img class="bg" src="<?php echo $img( 'rico-betfred-flow.webp' ); ?>" alt="">
	<div class="grad"></div>
	<div class="wrap">
		<div class="prep"><span class="lbl">Prepared for</span> <?php echo ricoman_partner_logo_html( $p, 60 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<?php if ( $greeting ) : ?><p class="hello">Hello <span class="who"><?php echo esc_html( $greeting ); ?></span>,</p><?php endif; ?>
		<span class="kicker">Commercial lighting · designed &amp; made in Manchester</span>
		<h1>Lighting that finishes <span class="who"><?php echo esc_html( $name ); ?></span>'s fit-outs, beautifully.</h1>
		<p class="sub">In-house linear, acoustic and biophilic luminaires, engineered around the way you specify and delivered in two to three weeks.</p>
		<div class="cta">
			<a class="btn btn-primary" href="#contact">Book a factory visit</a>
			<a class="btn btn-ghost" href="#work">See our work</a>
		</div>
	</div>
</header>

<div class="stats"><div class="wrap">
	<div><div class="n">1,000,000+</div><div class="l">linear variants, built to order</div></div>
	<div><div class="n">2-3 wks</div><div class="l">typical project delivery</div></div>
	<div><div class="n">In-house</div><div class="l">Manchester manufacturing</div></div>
	<div><div class="n">UK-made</div><div class="l">no import lead times</div></div>
</div></div>

<section class="pad"><div class="wrap">
	<div class="lead">
		<span class="eyebrow">Why <?php echo esc_html( $name ); ?> + Ricoman</span>
		<h2>Made round the corner, not shipped round the world.</h2>
		<p><?php echo esc_html( $intro ); ?> Because we design, bend and build our linear on our own floor, we can tailor a run exactly to your space and turn it around in <strong>2-3 weeks</strong>. No eight-week import wait, no "sorry, that's a special".</p>
	</div>
</div></section>

<section class="pad" id="work" style="padding-top:0"><div class="wrap">
	<span class="eyebrow">Recent work</span>
	<h2 style="font-size:clamp(1.9rem,4vw,2.9rem);margin:.25em 0 0">Offices &amp; workspaces we've lit</h2>
	<div class="grid">
		<?php foreach ( $gallery as $g ) : ?>
			<a class="pcard" href="<?php echo esc_url( $g['link'] ); ?>"><figure><img loading="lazy" src="<?php echo esc_url( $g['url'] ); ?>" alt="<?php echo esc_attr( $g['title'] ); ?>"><figcaption><?php echo esc_html( $g['title'] ); ?></figcaption></figure></a>
		<?php endforeach; ?>
	</div>
</div></section>

<section class="pad" style="padding-top:0"><div class="wrap">

	<div class="cap">
		<img loading="lazy" src="<?php echo $img( 'rico-mfg.webp' ); ?>" alt="Ricoman manufacturing floor, Manchester">
		<div>
			<span class="tag">In-house manufacturing</span>
			<h3>Our own bending machine, here in Manchester.</h3>
			<p>Curves, corners, custom lengths: our in-house bending machine and assembly line mean your linear scheme is made to your drawing, not forced to fit a catalogue.</p>
			<ul>
				<li><strong>1,000,000+</strong> linear configurations, built to order</li>
				<li>Bespoke shapes &amp; lengths as standard, not a surcharge</li>
				<li>One point of contact from spec to site</li>
			</ul>
		</div>
	</div>

	<div class="cap">
		<a href="<?php echo esc_url( home_url( '/products/sounds-like-light-baffle/' ) ); ?>"><img loading="lazy" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/01/RICOMAN-Lighting-product-soundslikelight02.webp' ) ); ?>" alt="Sounds Like Light acoustic baffle"></a>
		<div>
			<span class="tag">Acoustic lighting</span>
			<h3>Sounds Like Light: quieter, calmer offices.</h3>
			<p>Light and sound absorption in one fitting. Cut the reverb in open-plan floors, breakouts and meeting spaces while the lighting still looks the part.</p>
		</div>
	</div>

	<div class="cap">
		<img loading="lazy" src="<?php echo $img( 'rico-lightingdesign.webp' ); ?>" alt="Ricoman lighting design service">
		<div>
			<span class="tag">Lighting design service</span>
			<h3>A free scheme, before you commit.</h3>
			<p>Our in-house design team produces the lux calculations, layouts and visuals for your project, so you win the pitch with a scheme that's already proven to work.</p>
			<ul>
				<li>DIALux lux plans &amp; compliance</li>
				<li>Photoreal visuals for client sign-off</li>
				<li>Full spec &amp; drawing support</li>
			</ul>
		</div>
	</div>

</div></section>

<section class="pad" style="padding-top:0"><div class="wrap">
	<span class="eyebrow">The range</span>
	<h2 style="font-size:clamp(1.9rem,4vw,2.9rem);margin:.25em 0 0">Product families designers reach for</h2>
	<div class="prodgrid">
		<a href="<?php echo esc_url( home_url( '/products/flowplus/' ) ); ?>"><figure><img loading="lazy" src="<?php echo $img( 'rico-betfred-flow.webp' ); ?>" alt="Ricoman Flow+ curved linear lighting"><figcaption><span class="pt">Flow+</span><span class="ps">Curved &amp; ring linear</span></figcaption></figure></a>
		<a href="<?php echo esc_url( home_url( '/products/estrella-linear-lighting/' ) ); ?>"><figure><img loading="lazy" src="<?php echo $img( 'estrella-lounge.webp' ); ?>" alt="Ricoman Estrella linear lighting"><figcaption><span class="pt">Estrella</span><span class="ps">Modular linear system</span></figcaption></figure></a>
		<a href="<?php echo esc_url( home_url( '/products/astrowave-neon-rope-light/' ) ); ?>"><figure><img loading="lazy" src="<?php echo $img( 'rico-astrowave-banner.webp' ); ?>" alt="Ricoman Astrowave neon rope light"><figcaption><span class="pt">Astrowave</span><span class="ps">Neon-effect rope</span></figcaption></figure></a>
		<a href="<?php echo esc_url( home_url( '/products/sounds-like-light-baffle/' ) ); ?>"><figure><img loading="lazy" src="<?php echo esc_url( home_url( '/wp-content/uploads/2024/01/RICOMAN-Lighting-product-soundslikelight05.webp' ) ); ?>" alt="Ricoman Sounds Like Light acoustic baffle"><figcaption><span class="pt">Sounds Like Light</span><span class="ps">Acoustic + light</span></figcaption></figure></a>
	</div>
	<p style="margin-top:22px"><a class="btn btn-dark" href="<?php echo esc_url( home_url( '/products/' ) ); ?>">See the full range</a></p>
</div></section>

<section class="biophilic"><div class="wrap">
	<div class="txt">
		<span class="eyebrow">Biophilic lighting</span>
		<h2>Lighting that makes a space feel alive.</h2>
		<p>Our bespoke biophilic light trees bring greenery, warmth and a moment of calm into a reception or breakout, a living centrepiece people stop and look at.</p>
		<p>Paired with warm, human-centric linear and downlighting, it turns a workspace into somewhere people genuinely want to be. Designed with you and built in Manchester.</p>
	</div>
	<div class="media"><img loading="lazy" src="<?php echo $img( 'biophilic-tree.webp' ); ?>" alt="Ricoman bespoke biophilic light tree"></div>
</div></section>

<div class="band"><div class="wrap">
	<h2>Everything a fit-out needs, from one Manchester supplier.</h2>
	<p>Linear, downlights, track, acoustic, biophilic and emergency, all designed, made and delivered in-house. Fewer suppliers, faster lead times, one team that answers the phone.</p>
	<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/products/' ) ); ?>">Explore the range</a>
</div></div>

<section class="pad contactsec" id="contact"><div class="wrap narrow">
	<span class="eyebrow">Get in touch</span>
	<h2>Talk to <?php echo esc_html( $rep ); ?>.</h2>
	<p class="cintro">Drop me a line and I'll come straight back, whether that's a scheme for a live enquiry, budget prices, or a look round the factory.<br>
		<a href="mailto:<?php echo esc_attr( $rep_email ); ?>"><?php echo esc_html( $rep_email ); ?></a> &nbsp;·&nbsp; <a href="tel:<?php echo esc_attr( $rep_mobrw ); ?>"><?php echo esc_html( $rep_mob ); ?></a></p>
	<?php if ( $sent ) : ?><p class="sent">✅ Thanks, your message is on its way to <?php echo esc_html( $rep ); ?>. I'll be in touch shortly.</p><?php endif; ?>
	<form class="cform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ricoman_partner_contact">
		<input type="hidden" name="rp_source" value="<?php echo esc_url( $self ); ?>">
		<?php wp_nonce_field( 'ricoman_partner_contact', 'rp_nonce' ); ?>
		<div class="row2">
			<label>Your name<input type="text" name="rp_name" required></label>
			<label>Company<input type="text" name="rp_company" value="<?php echo esc_attr( $name ); ?>"></label>
		</div>
		<label>Email<input type="email" name="rp_email" required></label>
		<label>Message<textarea name="rp_message" rows="4" placeholder="Tell me about your project…"></textarea></label>
		<input type="text" name="company_url" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true">
		<button type="submit" class="btn btn-primary">Send to <?php echo esc_html( $rep ); ?></button>
	</form>
</div></section>

<div class="close"><div class="wrap">
	<div class="duo"><span class="lock rlock"><img src="<?php echo $logo; ?>" alt="Ricoman Lighting"></span><span class="x">×</span><span class="lock plock"><?php echo ricoman_partner_logo_html( $p, 60 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></div>
	<span class="eyebrow">Let's work together</span>
	<h2>Let's light <?php echo esc_html( $name ); ?>'s next project.</h2>
	<p>Come and see the machine that makes it: a 20-minute walk round our Manchester factory, or a scheme designed for your live enquiry. Whichever's more use to you.</p>
	<div class="cta">
		<a class="btn btn-primary" href="mailto:<?php echo esc_attr( $rep_email ); ?>">Email <?php echo esc_html( $rep ); ?></a>
		<a class="btn btn-wa" href="https://wa.me/<?php echo esc_attr( $wa ); ?>?text=<?php echo rawurlencode( 'Hi Richard, I saw the ' . $name . ' lighting page and would like to chat.' ); ?>" target="_blank" rel="noopener">💬 WhatsApp me</a>
		<a class="btn btn-dark" href="tel:<?php echo esc_attr( $rep_mobrw ); ?>">Call <?php echo esc_html( $rep_mob ); ?></a>
	</div>
</div></div>

<footer><div class="wrap">
	<div>
		<img class="flogo" src="<?php echo $logo; ?>" alt="Ricoman Lighting"><br><br>
		RICOMAN Lighting · Salford Quays, Manchester<br>
		Office <?php echo esc_html( $tel ); ?><br>Your contact: <strong><?php echo esc_html( $rep ); ?></strong><br><a href="tel:<?php echo esc_attr( $rep_mobrw ); ?>"><?php echo esc_html( $rep_mob ); ?></a> &middot; <a href="mailto:<?php echo esc_attr( $rep_email ); ?>"><?php echo esc_html( $rep_email ); ?></a>
	</div>
	<div class="links">
		<a href="<?php echo esc_url( home_url( '/products/' ) ); ?>">Products</a>
		<a href="<?php echo esc_url( home_url( '/projects/' ) ); ?>">Projects</a>
		<a href="<?php echo $home; ?>">ricoman.com</a>
	</div>
</div></footer>

</body>
</html>
<?php
}

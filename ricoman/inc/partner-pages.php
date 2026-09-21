<?php
/**
 * Bespoke prospect / partner landing pages.
 *
 * Standalone, highly-designed "why partner with Ricoman" pages served at a
 * top-level slug (e.g. /OBI, /office-innovations) and sent directly to a named
 * account. Rendered entirely in code (self-contained HTML) so they don't touch
 * the CMS, are version-controlled, and deploy with the theme. Always noindexed
 * (private outreach — not for public search); the slugs are also in
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
			'intro' => 'You design and build workspaces people are proud to walk into. We make the lighting that finishes them — engineered in-house, in Manchester, right on your doorstep.',
		),
		'office-innovations' => array(
			'name'  => 'Office Innovations',
			'logo'  => 'partners/office-innovations.png',
			'intro' => 'You turn empty floors into workspaces that work. We make the lighting that brings them to life — engineered in-house, in Manchester, right on your doorstep.',
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
function ricoman_partner_project_cards( $limit = 9 ) {
	if ( ! post_type_exists( 'project' ) ) {
		return array();
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

	// Real projects from the site (office sector first), each with its own photo,
	// name and a link to the live project page. Nine = three rows.
	$gallery = ricoman_partner_project_cards( 9 );

	header( 'Content-Type: text/html; charset=utf-8' );
	?><!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ricoman × <?php echo esc_html( $name ); ?> — Commercial lighting, made in Manchester</title>
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
.eyebrow{text-transform:uppercase;letter-spacing:.14em;font-size:.78rem;font-weight:700;color:var(--blue)}
h1,h2,h3{line-height:1.08;font-weight:700}
/* top bar */
.bar{position:sticky;top:0;z-index:20;background:rgba(22,22,26,.92);backdrop-filter:blur(6px);border-bottom:1px solid rgba(255,255,255,.08)}
.bar .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}
.bar img{height:26px}
.bar .r{display:flex;align-items:center;gap:18px;color:#fff;font-size:.92rem}
.bar .r a{color:#fff;text-decoration:none;font-weight:600}
.bar .pill{background:rgba(255,255,255,.12);padding:6px 14px;border-radius:30px;font-size:.8rem;font-weight:600}
@media(max-width:640px){.bar .pill{display:none}}
/* hero */
.hero{position:relative;min-height:88vh;display:flex;align-items:flex-end;color:#fff;background:#0d0d10}
.hero .bg{position:absolute;inset:0;object-fit:cover;width:100%;height:100%;opacity:.55}
.hero .grad{position:absolute;inset:0;background:linear-gradient(180deg,rgba(13,13,16,.35) 0%,rgba(13,13,16,.15) 40%,rgba(13,13,16,.9) 100%)}
.hero .wrap{position:relative;padding:64px 24px 72px}
.hero h1{font-size:clamp(2.6rem,6.4vw,5rem);margin:.35em 0 .3em;max-width:16ch}
.hero p.sub{font-size:clamp(1.05rem,2vw,1.35rem);max-width:52ch;opacity:.95;margin-bottom:1.8em}
.hero .cta{display:flex;gap:14px;flex-wrap:wrap}
.hero .for{display:inline-block;background:var(--blue);color:#fff;padding:7px 16px;border-radius:30px;font-weight:700;font-size:.82rem;letter-spacing:.04em}
.prep{display:inline-flex;align-items:center;gap:22px;background:#fff;color:var(--ink);padding:20px 34px;border-radius:16px;margin-bottom:26px;box-shadow:0 18px 48px rgba(0,0,0,.32)}
.prep .lbl{font-size:.74rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#8a9099;border-right:1px solid var(--line);padding-right:22px}
.prep .pname{font-weight:700;font-size:clamp(2rem,5vw,2.9rem);color:var(--ink);line-height:1}
.prep .plogo{display:block;width:auto}
/* co-brand line in the closing CTA */
.duo{display:flex;align-items:center;justify-content:center;gap:26px;margin:0 auto 30px;flex-wrap:wrap}
.duo img{height:48px}.duo .x{color:#9aa0aa;font-size:2rem}
.duo .pname{font-weight:700;font-size:clamp(1.8rem,4vw,2.4rem);color:var(--ink)}
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
footer img{height:24px;opacity:.9}
footer a{color:#fff;text-decoration:none}
footer .links a{margin-left:20px}
</style>
</head>
<body>

<div class="bar"><div class="wrap">
	<a href="<?php echo $home; ?>"><img src="<?php echo $logo; ?>" alt="Ricoman Lighting"></a>
	<div class="r"><span class="pill">🏭 Made in Manchester</span><a href="tel:<?php echo preg_replace( '/\s/', '', $tel ); ?>"><?php echo esc_html( $tel ); ?></a></div>
</div></div>

<header class="hero">
	<img class="bg" src="<?php echo $img( 'rico-betfred-flow.webp' ); ?>" alt="">
	<div class="grad"></div>
	<div class="wrap">
		<div class="prep"><span class="lbl">Prepared for</span> <?php echo ricoman_partner_logo_html( $p, 40 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<span class="for">A lighting partnership for <?php echo esc_html( $name ); ?></span>
		<h1>Lighting that finishes <?php echo esc_html( $name ); ?>'s fit-outs — beautifully.</h1>
		<p class="sub">UK commercial lighting, engineered and manufactured in-house in Manchester. Bespoke linear, acoustic and biophilic — on spec, on time, on budget.</p>
		<div class="cta">
			<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Book a factory visit</a>
			<a class="btn btn-ghost" href="#work">See our work</a>
		</div>
	</div>
</header>

<div class="stats"><div class="wrap">
	<div><div class="n">1,000,000+</div><div class="l">linear variants, built to order</div></div>
	<div><div class="n">2–3 wks</div><div class="l">typical project delivery</div></div>
	<div><div class="n">In-house</div><div class="l">Manchester manufacturing</div></div>
	<div><div class="n">UK-made</div><div class="l">no import lead times</div></div>
</div></div>

<section class="pad"><div class="wrap">
	<div class="lead">
		<span class="eyebrow">Why <?php echo esc_html( $name ); ?> + Ricoman</span>
		<h2>Made round the corner, not shipped round the world.</h2>
		<p><?php echo esc_html( $intro ); ?> Because we design, bend and build our linear on our own floor, we can tailor a run exactly to your space and turn it around in <strong>2–3 weeks</strong> — no eight-week import wait, no "sorry, that's a special".</p>
	</div>
</div></section>

<section class="pad" id="work" style="padding-top:0"><div class="wrap">
	<span class="eyebrow">Recent work</span>
	<h2 style="font-size:clamp(1.9rem,4vw,2.9rem);margin:.25em 0 0">Offices &amp; workspaces we've lit</h2>
	<div class="grid">
		<?php foreach ( $gallery as $g ) : ?>
			<figure><img loading="lazy" src="<?php echo $img( $g[0] ); ?>" alt="<?php echo esc_attr( $g[1] ); ?>"><figcaption><?php echo esc_html( $g[1] ); ?></figcaption></figure>
		<?php endforeach; ?>
	</div>
</div></section>

<section class="pad" style="padding-top:0"><div class="wrap">

	<div class="cap">
		<img loading="lazy" src="<?php echo $img( 'rico-mfg.webp' ); ?>" alt="Ricoman manufacturing floor, Manchester">
		<div>
			<span class="tag">In-house manufacturing</span>
			<h3>Our own bending machine — in Manchester.</h3>
			<p>Curves, corners, custom lengths — our in-house bending machine and assembly line mean your linear scheme is made to your drawing, not forced to fit a catalogue.</p>
			<ul>
				<li><strong>1,000,000+</strong> linear configurations, built to order</li>
				<li>Bespoke shapes &amp; lengths as standard, not a surcharge</li>
				<li>One point of contact from spec to site</li>
			</ul>
		</div>
	</div>

	<div class="cap">
		<a href="<?php echo esc_url( home_url( '/products/sounds-like-light-baffle/' ) ); ?>"><img loading="lazy" src="<?php echo esc_url( home_url( '/wp-content/uploads/2026/08/Acoustic-Baffle-1-2.webp' ) ); ?>" alt="Sounds Like Light acoustic baffle"></a>
		<div>
			<span class="tag">Acoustic lighting</span>
			<h3>Sounds Like Light — quieter, calmer offices.</h3>
			<p>Light and sound absorption in one fitting. Cut the reverb in open-plan floors, breakouts and meeting spaces while the lighting still looks the part.</p>
		</div>
	</div>

	<div class="cap">
		<img loading="lazy" src="<?php echo $img( 'rico-breakout-lounge.webp' ); ?>" alt="Ricoman biophilic lighting">
		<div>
			<span class="tag">Biophilic lighting</span>
			<h3>Lighting that makes a space feel alive.</h3>
			<p>Warm, human-centric schemes for breakouts, lounges and wellbeing zones — the finishing touch that makes a workspace somewhere people actually want to be.</p>
		</div>
	</div>

	<div class="cap">
		<img loading="lazy" src="<?php echo $img( 'rico-lightingdesign.webp' ); ?>" alt="Ricoman lighting design service">
		<div>
			<span class="tag">Lighting design service</span>
			<h3>A free scheme, before you commit.</h3>
			<p>Our in-house design team produces the lux calculations, layouts and visuals for your project — so you win the pitch with a scheme that's already proven to work.</p>
			<ul>
				<li>DIALux lux plans &amp; compliance</li>
				<li>Photoreal visuals for client sign-off</li>
				<li>Full spec &amp; drawing support</li>
			</ul>
		</div>
	</div>

</div></section>

<div class="band"><div class="wrap">
	<h2>Everything a fit-out needs, from one Manchester supplier.</h2>
	<p>Linear, downlights, track, acoustic, biophilic and emergency — designed, made and delivered in-house. Fewer suppliers, faster lead times, one team that answers the phone.</p>
	<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/products/' ) ); ?>">Explore the range</a>
</div></div>

<div class="close"><div class="wrap">
	<div class="duo"><span style="font-weight:700;font-size:1.5rem;color:var(--blue);letter-spacing:.03em">RICOMAN</span><span class="x">×</span><?php echo ricoman_partner_logo_html( $p, 34 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	<span class="eyebrow">Let's work together</span>
	<h2>Let's light <?php echo esc_html( $name ); ?>'s next project.</h2>
	<p>Come and see the machine that makes it — a 20-minute walk round our Manchester factory, or a scheme designed for your live enquiry. Whichever's more use to you.</p>
	<div class="cta">
		<a class="btn btn-primary" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get in touch</a>
		<a class="btn btn-dark" href="tel:<?php echo preg_replace( '/\s/', '', $tel ); ?>">Call <?php echo esc_html( $tel ); ?></a>
	</div>
</div></div>

<footer><div class="wrap">
	<div>
		<img src="<?php echo $logo; ?>" alt="Ricoman Lighting"><br><br>
		RICOMAN Lighting · Salford Quays, Manchester<br>
		<?php echo esc_html( $tel ); ?> · <a href="mailto:<?php echo $mail; ?>"><?php echo esc_html( $mail ); ?></a>
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

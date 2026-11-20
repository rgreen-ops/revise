<?php
/**
 * /flow-install — Flow+ installation videos (target of the QR code in the printed booklet).
 *
 * Standalone page, deliberately hidden from search: noindex meta + X-Robots-Tag,
 * not a WP post (so never in the Yoast sitemap) and not linked from any menu.
 * Videos/posters live in assets/flow-install/ (their own .htaccess adds X-Robots-Tag too).
 *
 * @package ricoman
 */

defined( 'ABSPATH' ) || exit;

add_action( 'wp_loaded', function () {
	$path = strtolower( trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) );
	if ( 'flow-install' !== $path ) {
		return;
	}
	$base = get_theme_file_uri( 'assets/flow-install/' );
	$logo = get_theme_file_uri( 'assets/images/ricoman-logo-black.png' );
	status_header( 200 );
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'Cache-Control: public, max-age=3600' );
	?>
<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Flow+ installation videos | Ricoman</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;font-family:Poppins,Segoe UI,Arial,sans-serif;color:#1d1d1b;background:#f2f3f4}
header{background:#fff;padding:18px 20px;border-bottom:1px solid #e3e3e3}header img{height:22px;display:block}
main{max-width:960px;margin:0 auto;padding:24px 16px 40px}
h1{font-weight:500;font-size:28px;margin:4px 0 6px}.lead{color:#555;margin:0 0 22px}
.v{background:#fff;border-radius:10px;padding:16px;margin-bottom:22px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.v h2{font-size:19px;font-weight:600;margin:0 0 4px}.v p{margin:0 0 12px;color:#555;font-size:14px}
.tag{display:inline-block;background:#2f6fd6;color:#fff;font-size:12px;font-weight:600;border-radius:20px;padding:3px 10px;margin-left:6px;vertical-align:2px}
.tag.long{background:#555}
video{width:100%;border-radius:6px;background:#000;display:block;aspect-ratio:16/9}
.help{background:#fff;border-radius:10px;padding:16px;font-size:15px}.help b{color:#2f6fd6}
.dl{display:inline-block;margin-top:6px;color:#2f6fd6;font-weight:500}
</style>
</head>
<body>
<header><img src="<?php echo esc_url( $logo ); ?>" alt="Ricoman" width="201" height="23"></header>
<main>
	<h1>Flow+ installation</h1>
	<p class="lead">Watch these before you start. Sound on helps, but the key points are on screen too.</p>

	<section class="v">
		<h2>Quick guide <span class="tag">2 min</span></h2>
		<p>The key steps, start to finish. Watch this first.</p>
		<video controls playsinline preload="metadata" poster="<?php echo esc_url( $base . 'poster-quick.jpg' ); ?>">
			<source src="<?php echo esc_url( $base . 'flow-install-quick.mp4' ); ?>" type="video/mp4">
		</video>
	</section>

	<section class="v">
		<h2>Full step-by-step <span class="tag long">13 min</span></h2>
		<p>Every step in the booklet, in order. Use the chapter you need.</p>
		<video controls playsinline preload="none" poster="<?php echo esc_url( $base . 'poster-full.jpg' ); ?>">
			<source src="<?php echo esc_url( $base . 'flow-install-full.mp4' ); ?>" type="video/mp4">
		</video>
	</section>

	<div class="help">
		Need help? Call <b>0161 877 1399</b> and ask for <b>Technical</b>, or email <b>technical@ricoman.com</b>.<br>
		<a class="dl" href="<?php echo esc_url( $base . 'flow-installation-booklet.pdf' ); ?>">Download the installation booklet (PDF)</a>
	</div>
</main>
</body>
</html>
	<?php
	exit;
}, 0 );

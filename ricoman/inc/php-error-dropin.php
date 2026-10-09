<?php
/**
 * RICOMAN_ERROR_DROPIN v1
 *
 * Friendly front-end fatal-error page. WordPress loads this from
 * wp-content/php-error.php (a "drop-in") whenever the site hits a fatal error,
 * instead of its bare "There has been a critical error" message. The Ricoman
 * theme copies this file into place (see inc/error-page.php); editing it here
 * and re-deploying updates the live page.
 *
 * Runs in a degraded context (something already crashed), so it assumes nothing
 * and guards every WordPress function with function_exists().
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Not a directly-viewable page.
}

if ( ! headers_sent() ) {
	if ( function_exists( 'status_header' ) ) {
		status_header( 500 );
	} else {
		header( 'HTTP/1.1 500 Internal Server Error' );
	}
	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}
	header( 'Content-Type: text/html; charset=utf-8' );
}

$rm_home = function_exists( 'home_url' ) ? home_url( '/' ) : '/';
$rm_home = htmlspecialchars( $rm_home, ENT_QUOTES, 'UTF-8' );
?><!DOCTYPE html>
<html lang="en-GB">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, follow">
	<title>Ricoman &mdash; we&rsquo;ll be right back</title>
	<style>
		*{box-sizing:border-box}
		html,body{height:100%}
		body{margin:0;background:#16161a;color:#fff;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;line-height:1.5}
		.rm-err{max-width:520px}
		.rm-err__logo{font-weight:700;letter-spacing:.06em;font-size:1.25rem;margin-bottom:40px;opacity:.95}
		.rm-err__logo span{opacity:.6;font-size:.7em;vertical-align:super}
		.rm-err h1{font-size:clamp(1.6rem,5vw,2.2rem);font-weight:500;line-height:1.15;margin:0 0 16px}
		.rm-err p{color:rgba(255,255,255,.72);font-size:1.02rem;margin:0 auto 32px;max-width:420px}
		.rm-err__btn{display:inline-block;background:#fff;color:#16161a;text-decoration:none;font-weight:600;font-size:.95rem;padding:14px 28px;border-radius:10px;transition:opacity .2s}
		.rm-err__btn:hover{opacity:.85}
		.rm-err__sub{margin-top:28px;font-size:.82rem;color:rgba(255,255,255,.4)}
	</style>
</head>
<body>
	<div class="rm-err">
		<div class="rm-err__logo">RICOMAN<span>&reg;</span></div>
		<h1>Oops &mdash; the lights seem to have gone out.</h1>
		<p>Something went wrong loading this page. Our team has been notified and we&rsquo;re getting it fixed. Please try again in a few minutes.</p>
		<a class="rm-err__btn" href="<?php echo $rm_home; ?>">Return to Ricoman</a>
		<div class="rm-err__sub">If this keeps happening, email <a href="mailto:sales@ricoman.com" style="color:rgba(255,255,255,.6)">sales@ricoman.com</a>.</div>
	</div>
</body>
</html>

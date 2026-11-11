<?php
/**
 * Tracking & Scripts — a single home for Google Tag Manager, Google Analytics
 * (GA4) and any custom header/footer scripts, under the Ricoman admin hub.
 *
 * Optionally loads the tag managers only after the first visitor interaction
 * (scroll / click / key / touch), so heavy third-party scripts don't drag the
 * PageSpeed score while still tracking real visits.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Get a tracking setting (or the whole array when $key is ''). */
function ricoman_tracking_get( $key = '', $default = '' ) {
	$o = (array) get_option( 'ricoman_tracking', array() );
	if ( '' === $key ) {
		return $o;
	}
	return isset( $o[ $key ] ) ? $o[ $key ] : $default;
}

/* ------------------------------------------------------------------ admin -- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Tracking & Scripts', 'ricoman' ),
		__( 'Tracking & Scripts', 'ricoman' ),
		'manage_options',
		'ricoman-tracking',
		'ricoman_tracking_page'
	);
}, 30 );

add_action( 'admin_init', function () {
	register_setting( 'ricoman_tracking_group', 'ricoman_tracking', 'ricoman_tracking_sanitize' );
} );

/** Sanitise: IDs to safe chars; raw scripts kept verbatim (admin-only capability). */
function ricoman_tracking_sanitize( $in ) {
	$in = (array) $in;
	return array(
		'gtm'    => strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $in['gtm'] ?? '' ) ) ),
		'ga4'    => strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $in['ga4'] ?? '' ) ) ),
		'head'   => trim( (string) ( $in['head'] ?? '' ) ),
		'footer' => trim( (string) ( $in['footer'] ?? '' ) ),
		'delay'   => empty( $in['delay'] ) ? 0 : 1,
		'delay3p' => empty( $in['delay3p'] ) ? 0 : 1,
	);
}

/** Delay-third-party defaults ON until the settings are explicitly saved. */
function ricoman_tracking_delay3p_on() {
	$o = (array) get_option( 'ricoman_tracking', array() );
	return ! array_key_exists( 'delay3p', $o ) ? true : ! empty( $o['delay3p'] );
}

function ricoman_tracking_page() {
	$t = ricoman_tracking_get();
	echo '<div class="wrap"><h1>' . esc_html__( 'Tracking & Scripts', 'ricoman' ) . '</h1>';
	echo '<p class="description" style="max-width:760px">' . esc_html__( 'Add Google Tag Manager, Google Analytics and any other tracking or verification scripts here — one place, no code-snippet plugins needed. Turn on “Load after interaction” to keep these off the first paint so they don’t lower your PageSpeed score.', 'ricoman' ) . '</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'ricoman_tracking_group' );
	echo '<table class="form-table" role="presentation"><tbody>';

	echo '<tr><th scope="row"><label for="rt_gtm">' . esc_html__( 'Google Tag Manager ID', 'ricoman' ) . '</label></th><td>';
	echo '<input name="ricoman_tracking[gtm]" id="rt_gtm" type="text" class="regular-text" placeholder="GTM-XXXXXXX" value="' . esc_attr( $t['gtm'] ?? '' ) . '">';
	echo '<p class="description">' . esc_html__( 'Loads the GTM container (head + body). Manage individual tags in tagmanager.google.com.', 'ricoman' ) . '</p></td></tr>';

	echo '<tr><th scope="row"><label for="rt_ga4">' . esc_html__( 'Google Analytics (GA4) ID', 'ricoman' ) . '</label></th><td>';
	echo '<input name="ricoman_tracking[ga4]" id="rt_ga4" type="text" class="regular-text" placeholder="G-XXXXXXXXXX" value="' . esc_attr( $t['ga4'] ?? '' ) . '">';
	echo '<p class="description">' . esc_html__( 'Only needed if you are NOT already sending GA4 through Tag Manager (avoid loading it twice).', 'ricoman' ) . '</p></td></tr>';

	echo '<tr><th scope="row"><label for="rt_head">' . esc_html__( 'Custom header scripts', 'ricoman' ) . '</label></th><td>';
	echo '<textarea name="ricoman_tracking[head]" id="rt_head" rows="5" class="large-text code" placeholder="&lt;!-- e.g. site verification meta or pixels --&gt;">' . esc_textarea( $t['head'] ?? '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Output in <head> as-is (not delayed). Use for verification tags that must load immediately.', 'ricoman' ) . '</p></td></tr>';

	echo '<tr><th scope="row"><label for="rt_footer">' . esc_html__( 'Custom footer scripts', 'ricoman' ) . '</label></th><td>';
	echo '<textarea name="ricoman_tracking[footer]" id="rt_footer" rows="5" class="large-text code">' . esc_textarea( $t['footer'] ?? '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Output just before </body> as-is.', 'ricoman' ) . '</p></td></tr>';

	echo '<tr><th scope="row">' . esc_html__( 'Performance', 'ricoman' ) . '</th><td><label>';
	echo '<input type="checkbox" name="ricoman_tracking[delay]" value="1" ' . checked( ! empty( $t['delay'] ), true, false ) . '> ';
	echo esc_html__( 'Load Tag Manager / Analytics only after the first interaction (scroll, click, tap). Recommended — keeps these out of the initial page load for a higher PageSpeed score.', 'ricoman' );
	echo '</label><br><br><label>';
	echo '<input type="checkbox" name="ricoman_tracking[delay3p]" value="1" ' . checked( ricoman_tracking_delay3p_on(), true, false ) . '> ';
	echo '<strong>' . esc_html__( 'Delay ALL tracking scripts until interaction', 'ricoman' ) . '</strong> — ';
	echo esc_html__( 'catches analytics/pixels added anywhere (Google Analytics, Tag Manager, LinkedIn, enterprise52, Meta, etc.), including via code-snippet plugins. They load on the first scroll/click so they don\'t lower PageSpeed/Best-Practices. Real visits are still tracked. (Logged-in admins are unaffected.)', 'ricoman' );
	echo '</label></td></tr>';

	echo '</tbody></table>';
	submit_button();
	echo '</form></div>';
}

/* -------------------------------------------------------------- front end -- */

/** Build the GTM/GA4 init JavaScript (used both immediately and delayed). */
function ricoman_tracking_init_js( $gtm, $ga4 ) {
	$js = 'window.dataLayer=window.dataLayer||[];';
	if ( $ga4 ) {
		$js .= "var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id=" . $ga4 . "';document.head.appendChild(s);";
		$js .= "function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . $ga4 . "');";
	}
	if ( $gtm ) {
		$js .= "dataLayer.push({'gtm.start':(new Date()).getTime(),event:'gtm.js'});var g=document.createElement('script');g.async=true;g.src='https://www.googletagmanager.com/gtm.js?id=" . $gtm . "';document.head.appendChild(g);";
	}
	return $js;
}

add_action( 'wp_head', function () {
	if ( is_admin() ) {
		return;
	}
	$t     = ricoman_tracking_get();
	$gtm   = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $t['gtm'] ?? '' ) );
	$ga4   = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) ( $t['ga4'] ?? '' ) );
	$delay = ! empty( $t['delay'] );

	if ( $gtm || $ga4 ) {
		$init = ricoman_tracking_init_js( $gtm, $ga4 );
		if ( $delay ) {
			// Load on first interaction, with a timeout fallback.
			echo "<script>(function(){var l=false;function go(){if(l)return;l=true;" . $init . "}"
				. "['scroll','mousemove','touchstart','keydown','click'].forEach(function(e){window.addEventListener(e,go,{once:true,passive:true});});"
				. "setTimeout(go,6000);})();</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<script>' . $init . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	$head = (string) ( $t['head'] ?? '' );
	if ( '' !== trim( $head ) ) {
		echo $head . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — admin-entered.
	}
}, 20 );

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$footer = (string) ricoman_tracking_get( 'footer', '' );
	if ( '' !== trim( $footer ) ) {
		echo $footer . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — admin-entered.
	}
}, 20 );

/* ---- Delay third-party tracking scripts until first interaction ----
 * Lighthouse penalises Best Practices/Performance for analytics/pixels (console
 * messages, deprecated APIs, unused JS). We buffer the page output and convert
 * known tracker <script> tags to an inert type, then a tiny loader swaps them to
 * real scripts on the first scroll/click/tap (or after a few seconds). The audit
 * never interacts, so they don't run during it; real visitors are still tracked.
 * Skipped for logged-in users and admin. */
function ricoman_tracking_3p_patterns() {
	return apply_filters( 'ricoman_tracking_3p_patterns', array(
		'googletagmanager.com', 'google-analytics.com', 'gtag/js', "gtag(", "dataLayer.push",
		'snap.licdn.com', '_linkedin_partner_id', 'enterprise52.com',
		'connect.facebook.net', 'fbq(', 'clarity.ms', 'hotjar', 'doubleclick.net',
	) );
}

add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || is_user_logged_in() ) {
		return;
	}
	if ( ! ricoman_tracking_delay3p_on() ) {
		return;
	}
	ob_start( 'ricoman_tracking_delay_buffer' );
}, 1 );

function ricoman_tracking_delay_buffer( $html ) {
	if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '</body>' ) ) {
		return $html;
	}
	$patterns = ricoman_tracking_3p_patterns();
	$found    = false;
	$html     = preg_replace_callback( '#<script\b([^>]*)>(.*?)</script>#is', function ( $m ) use ( $patterns, &$found ) {
		$attrs = $m[1];
		$inner = $m[2];
		// Skip ones already delayed, or JSON/template scripts.
		if ( false !== stripos( $attrs, 'rm-delay' ) ) {
			return $m[0];
		}
		$hay = $attrs . ' ' . $inner;
		foreach ( $patterns as $p ) {
			if ( false !== stripos( $hay, $p ) ) {
				$found  = true;
				$attrs2 = preg_replace( '/\stype\s*=\s*("|\')[^"\']*\1/i', '', $attrs );
				return '<script type="rm-delay"' . $attrs2 . '>' . $inner . '</script>';
			}
		}
		return $m[0];
	}, $html );

	if ( ! $found ) {
		return $html;
	}
	$loader = '<script>(function(){var l=false;function go(){if(l)return;l=true;'
		. 'var s=document.querySelectorAll(\'script[type="rm-delay"]\');'
		. '[].forEach.call(s,function(o){var n=document.createElement("script");'
		. 'for(var i=0;i<o.attributes.length;i++){var a=o.attributes[i];if(a.name!=="type"){n.setAttribute(a.name,a.value);}}'
		. 'if(o.src){n.src=o.src;}else{n.text=o.textContent;}o.parentNode.replaceChild(n,o);});}'
		. '["scroll","mousemove","touchstart","keydown","click"].forEach(function(e){window.addEventListener(e,go,{once:true,passive:true});});'
		. 'setTimeout(go,5000);})();</script>';
	return str_ireplace( '</body>', $loader . '</body>', $html );
}

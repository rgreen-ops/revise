<?php
/**
 * Google Search Console connector — real ranking / impression data for the SEO
 * Targets screen.
 *
 * Uses a Google service account (paste the JSON key + the GSC property) so there's
 * no OAuth redirect dance: we mint a signed JWT, exchange it for an access token,
 * and query the Search Analytics API for the last 28 days. Results are matched to
 * each target term (position, clicks, impressions) and power the "opportunity"
 * finder (page-2 terms) + "new query" discovery.
 *
 * One-time setup (by whoever manages the Google account):
 *   1. Google Cloud → create a service account → create a JSON key.
 *   2. Enable the "Search Console API" for that project.
 *   3. In Search Console → Settings → Users, add the service-account email as a
 *      user (Full or Restricted).
 *   4. Paste the JSON + property (e.g. "sc-domain:ricoman.com" or the https URL)
 *      on Ricoman → SEO Targets.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is GSC configured? */
function ricoman_gsc_ready() {
	$sa = json_decode( (string) get_option( 'ricoman_gsc_sa', '' ), true );
	return is_array( $sa ) && ! empty( $sa['client_email'] ) && ! empty( $sa['private_key'] ) && '' !== (string) get_option( 'ricoman_gsc_site', '' );
}

/** base64url helper. */
function ricoman_gsc_b64( $d ) {
	return rtrim( strtr( base64_encode( $d ), '+/', '-_' ), '=' );
}

/** Get a cached OAuth access token via the service-account JWT grant. */
function ricoman_gsc_token() {
	$cached = get_transient( 'ricoman_gsc_token' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$sa = json_decode( (string) get_option( 'ricoman_gsc_sa', '' ), true );
	if ( ! is_array( $sa ) || empty( $sa['client_email'] ) || empty( $sa['private_key'] ) || ! function_exists( 'openssl_sign' ) ) {
		return '';
	}
	$now    = time();
	$header = ricoman_gsc_b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claim  = ricoman_gsc_b64( wp_json_encode( array(
		'iss'   => $sa['client_email'],
		'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
		'aud'   => 'https://oauth2.googleapis.com/token',
		'iat'   => $now,
		'exp'   => $now + 3600,
	) ) );
	$sig = '';
	if ( ! openssl_sign( $header . '.' . $claim, $sig, $sa['private_key'], 'SHA256' ) ) {
		return '';
	}
	$jwt = $header . '.' . $claim . '.' . ricoman_gsc_b64( $sig );
	$res = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
		'timeout' => 15,
		'body'    => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ),
	) );
	if ( is_wp_error( $res ) ) {
		return '';
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	$tok  = isset( $body['access_token'] ) ? (string) $body['access_token'] : '';
	if ( '' !== $tok ) {
		set_transient( 'ricoman_gsc_token', $tok, 50 * MINUTE_IN_SECONDS );
	}
	return $tok;
}

/** Query Search Analytics (last 28 days, by query). Cached 12h. Returns rows. */
function ricoman_gsc_rows( $force = false ) {
	if ( ! $force ) {
		$cached = get_transient( 'ricoman_gsc_rows' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}
	$tok = ricoman_gsc_token();
	if ( '' === $tok ) {
		return array();
	}
	$site = (string) get_option( 'ricoman_gsc_site', '' );
	$url  = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $site ) . '/searchAnalytics/query';
	$res  = wp_remote_post( $url, array(
		'timeout' => 20,
		'headers' => array( 'Authorization' => 'Bearer ' . $tok, 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( array(
			'startDate'  => gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			'endDate'    => gmdate( 'Y-m-d' ),
			'dimensions' => array( 'query' ),
			'rowLimit'   => 1000,
		) ),
	) );
	if ( is_wp_error( $res ) ) {
		return array();
	}
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	$rows = isset( $body['rows'] ) && is_array( $body['rows'] ) ? $body['rows'] : array();
	set_transient( 'ricoman_gsc_rows', $rows, 12 * HOUR_IN_SECONDS );
	return $rows;
}

/** GSC metrics for a target term: best-matching query's position/clicks/impressions. */
function ricoman_gsc_term_data( $term ) {
	if ( ! ricoman_gsc_ready() ) {
		return null;
	}
	$rows = ricoman_gsc_rows();
	if ( ! $rows ) {
		return null;
	}
	$n     = function_exists( 'ricoman_seo_norm' ) ? ricoman_seo_norm( $term ) : strtolower( $term );
	$best  = null;
	foreach ( $rows as $r ) {
		$q  = isset( $r['keys'][0] ) ? $r['keys'][0] : '';
		$qn = function_exists( 'ricoman_seo_norm' ) ? ricoman_seo_norm( $q ) : strtolower( $q );
		if ( '' === $qn ) {
			continue;
		}
		// Match if the query contains the term or vice-versa (normalised).
		if ( false !== strpos( $qn, $n ) || false !== strpos( $n, $qn ) ) {
			if ( null === $best || $r['impressions'] > $best['impressions'] ) {
				$best = array(
					'query'       => $q,
					'position'    => round( (float) $r['position'], 1 ),
					'clicks'      => (int) $r['clicks'],
					'impressions' => (int) $r['impressions'],
				);
			}
		}
	}
	return $best;
}

/** "Opportunity" + "new query" finders for the SEO Targets screen. */
function ricoman_gsc_opportunities( $targets ) {
	$out = array( 'page2' => array(), 'new' => array() );
	if ( ! ricoman_gsc_ready() ) {
		return $out;
	}
	$rows = ricoman_gsc_rows();
	if ( ! $rows ) {
		return $out;
	}
	$target_norms = array();
	foreach ( (array) $targets as $t ) {
		$target_norms[] = function_exists( 'ricoman_seo_norm' ) ? ricoman_seo_norm( $t['term'] ) : strtolower( $t['term'] );
	}
	$is_targeted = function ( $qn ) use ( $target_norms ) {
		foreach ( $target_norms as $tn ) {
			if ( '' !== $tn && ( false !== strpos( $qn, $tn ) || false !== strpos( $tn, $qn ) ) ) {
				return true;
			}
		}
		return false;
	};
	foreach ( $rows as $r ) {
		$q   = isset( $r['keys'][0] ) ? $r['keys'][0] : '';
		$pos = (float) $r['position'];
		$imp = (int) $r['impressions'];
		$qn  = function_exists( 'ricoman_seo_norm' ) ? ricoman_seo_norm( $q ) : strtolower( $q );
		// Page-2 quick wins among targeted terms.
		if ( $pos >= 11 && $pos <= 20.5 && $imp >= 20 && $is_targeted( $qn ) ) {
			$out['page2'][] = array( 'query' => $q, 'position' => round( $pos, 1 ), 'impressions' => $imp );
		}
		// New high-impression queries not in the target list.
		if ( $imp >= 50 && ! $is_targeted( $qn ) ) {
			$out['new'][] = array( 'query' => $q, 'position' => round( $pos, 1 ), 'impressions' => $imp );
		}
	}
	usort( $out['page2'], function ( $a, $b ) { return $b['impressions'] - $a['impressions']; } );
	usort( $out['new'], function ( $a, $b ) { return $b['impressions'] - $a['impressions']; } );
	$out['page2'] = array_slice( $out['page2'], 0, 15 );
	$out['new']   = array_slice( $out['new'], 0, 20 );
	return $out;
}

/* ----------------------------------------------------------- settings save */

add_action( 'admin_post_ricoman_gsc_save', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ricoman_gsc' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$json = isset( $_POST['gsc_sa'] ) ? trim( (string) wp_unslash( $_POST['gsc_sa'] ) ) : '';
	$site = isset( $_POST['gsc_site'] ) ? sanitize_text_field( wp_unslash( $_POST['gsc_site'] ) ) : '';
	if ( '' !== $json ) {
		$dec = json_decode( $json, true );
		if ( is_array( $dec ) && ! empty( $dec['client_email'] ) ) {
			update_option( 'ricoman_gsc_sa', wp_json_encode( $dec ), false );
		}
	} elseif ( isset( $_POST['gsc_clear'] ) ) {
		delete_option( 'ricoman_gsc_sa' );
	}
	update_option( 'ricoman_gsc_site', $site, false );
	delete_transient( 'ricoman_gsc_token' );
	delete_transient( 'ricoman_gsc_rows' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'gsc' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/** Render the GSC connect panel (called from the SEO Targets screen). */
function ricoman_gsc_settings_panel() {
	$ready = ricoman_gsc_ready();
	$site  = (string) get_option( 'ricoman_gsc_site', '' );
	$sa    = json_decode( (string) get_option( 'ricoman_gsc_sa', '' ), true );
	$email = is_array( $sa ) && ! empty( $sa['client_email'] ) ? $sa['client_email'] : '';
	echo '<details class="rm-gsc-panel" style="margin:14px 0;border:1px solid #dcdce0;border-radius:8px;padding:8px 14px"' . ( $ready ? '' : ' open' ) . '>';
	echo '<summary style="cursor:pointer;font-weight:600">' . ( $ready ? '🟢 ' : '⚪ ' ) . esc_html__( 'Google Search Console — live rankings', 'ricoman' ) . ( $ready ? ' (' . esc_html__( 'connected', 'ricoman' ) . ')' : ' (' . esc_html__( 'not connected', 'ricoman' ) . ')' ) . '</summary>';
	if ( isset( $_GET['gsc'] ) ) {
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Search Console settings saved.', 'ricoman' ) . ( $ready ? '' : ' ' . esc_html__( 'Check the service-account JSON + property if no data appears.', 'ricoman' ) ) . '</p></div>';
	}
	echo '<p class="description" style="max-width:760px">' . esc_html__( 'Paste a Google service-account JSON key and your Search Console property to show real position, clicks & impressions per term + the opportunity finder. One-time setup: create a service account + JSON key, enable the Search Console API, and add the service-account email as a user in Search Console (Settings → Users).', 'ricoman' ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_gsc_save">';
	wp_nonce_field( 'ricoman_gsc' );
	echo '<p><label><strong>' . esc_html__( 'Property', 'ricoman' ) . '</strong><br><input type="text" name="gsc_site" value="' . esc_attr( $site ) . '" class="regular-text" placeholder="sc-domain:ricoman.com  or  https://ricoman.com/"></label></p>';
	echo '<p><label><strong>' . esc_html__( 'Service-account JSON', 'ricoman' ) . '</strong>' . ( $email ? ' <span class="description">(' . esc_html__( 'on file:', 'ricoman' ) . ' ' . esc_html( $email ) . ')</span>' : '' ) . '<br><textarea name="gsc_sa" rows="4" style="width:100%;font-family:monospace;font-size:12px" placeholder="' . ( $email ? esc_attr__( 'leave blank to keep current key', 'ricoman' ) : '{ &quot;type&quot;: &quot;service_account&quot;, … }' ) . '"></textarea></label></p>';
	echo '<p>' . get_submit_button( __( 'Save & connect', 'ricoman' ), 'secondary', 'submit', false );
	if ( $email ) {
		echo ' <label style="margin-left:10px"><input type="checkbox" name="gsc_clear" value="1"> ' . esc_html__( 'disconnect', 'ricoman' ) . '</label>';
	}
	echo '</p></form></details>';
}

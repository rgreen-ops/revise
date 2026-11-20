<?php
/**
 * One-time: create the two form thank-you pages.
 *
 * Publishes "Lighting Design — Thank You" and "Trade Account — Thank You" (if they
 * don't already exist) with a simple on-brand confirmation message and a Custom
 * HTML block marked for the team's ad conversion tag. The forms can then redirect
 * to these on success (via the shortcodes' `thankyou` attribute) so a dedicated URL
 * loads for conversion tracking.
 *
 * Create-only + run-once (option flag); never overwrites an existing page.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Centered confirmation content for a thank-you page. */
function ricoman_thankyou_content( $what ) {
	$tick = '<!-- wp:html --><div style="text-align:center;margin-bottom:10px"><span class="rm-tradeform-tick" aria-hidden="true"></span></div><!-- /wp:html -->';
	$head = '<!-- wp:heading {"textAlign":"center","level":1,"className":"rm-shead"} --><h1 class="wp-block-heading has-text-align-center rm-shead">Thank you — your ' . esc_html( $what ) . ' has been received.</h1><!-- /wp:heading -->';
	$para = '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Our team will review it and be in touch shortly.</p><!-- /wp:paragraph -->';
	$btn  = '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/products/">Back to products</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	$tag  = '<!-- wp:html -->' . "\n" . '<!-- Paste your ad conversion tracking tag (Google Ads / GA4 / Meta Pixel) here — it fires when this page loads. -->' . "\n" . '<!-- /wp:html -->';
	return '<!-- wp:group {"align":"full","className":"rm-section","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section">'
		. $tick . $head . $para . $btn . $tag
		. '</div><!-- /wp:group -->';
}

/** Create the pages (create-only). Returns slug => page ID map. */
function ricoman_create_thankyou_pages() {
	$defs = array(
		'lighting-design-thank-you' => array( 'Lighting Design — Thank You', 'enquiry' ),
		'trade-account-thank-you'   => array( 'Trade Account — Thank You', 'application' ),
	);
	$report = array();
	foreach ( $defs as $slug => $d ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			$report[ $slug ] = (int) $existing->ID; // leave any existing page alone.
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $d[0],
			'post_name'    => $slug,
			'post_content' => wp_slash( ricoman_thankyou_content( $d[1] ) ),
			'meta_input'   => array(
				'_wp_page_template'    => 'page-no-title', // no auto title above the message.
				'_ricoman_seo_noindex' => '1',             // conversion pages shouldn't be indexed.
			),
		) );
		if ( ! is_wp_error( $id ) ) {
			$report[ $slug ] = (int) $id;
		}
	}
	update_option( 'ricoman_thankyou_pages_report', $report, false );
	return $report;
}

/** Fire once on a normal request (flag claimed up front; released on error to retry). */
add_action( 'init', function () {
	if ( get_option( 'ricoman_thankyou_pages_v1' ) ) {
		return;
	}
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	update_option( 'ricoman_thankyou_pages_v1', time(), false );
	try {
		ricoman_create_thankyou_pages();
	} catch ( \Throwable $e ) {
		delete_option( 'ricoman_thankyou_pages_v1' );
	}
}, 99 );

/**
 * v2: nest each thank-you page under its form's page (so the URL reads
 * /lighting-design/thank-you/ and /new-trade-page/thank-you/) and wire the form
 * shortcodes to redirect there on success. Create-only / fill-only; idempotent.
 */
function ricoman_nest_and_wire_thankyou() {
	// [ thank-you page slug (v1 top-level), parent page slug, wizard/trade shortcode, shortcode+attr ]
	$defs = array(
		array( 'lighting-design-thank-you', 'lighting-design', '[ricoman_project_wizard]', '[ricoman_project_wizard thankyou="/lighting-design/thank-you/"]' ),
		array( 'trade-account-thank-you', 'new-trade-page', '[ricoman_trade_form]', '[ricoman_trade_form thankyou="/new-trade-page/thank-you/"]' ),
	);
	foreach ( $defs as $d ) {
		list( $ty_slug, $parent_slug, $sc, $sc_attr ) = $d;
		$parent = get_page_by_path( $parent_slug, OBJECT, 'page' );
		if ( ! $parent ) {
			continue; // form page not found — skip.
		}
		// Nest the thank-you page (find it top-level, or already nested).
		$ty = get_page_by_path( $ty_slug, OBJECT, 'page' );
		if ( ! $ty ) {
			$ty = get_page_by_path( $parent_slug . '/thank-you', OBJECT, 'page' );
		}
		if ( $ty && ( (int) $ty->post_parent !== (int) $parent->ID || 'thank-you' !== $ty->post_name ) ) {
			wp_update_post( array( 'ID' => (int) $ty->ID, 'post_parent' => (int) $parent->ID, 'post_name' => 'thank-you' ) );
		}
		// Wire the form's shortcode on the parent page (add the redirect attribute).
		$content = (string) $parent->post_content;
		if ( false !== strpos( $content, $sc ) && false === strpos( $content, $sc_attr ) ) {
			wp_update_post( array( 'ID' => (int) $parent->ID, 'post_content' => wp_slash( str_replace( $sc, $sc_attr, $content ) ) ) );
		}
	}
}
add_action( 'init', function () {
	if ( get_option( 'ricoman_thankyou_pages_v2' ) ) {
		return;
	}
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	update_option( 'ricoman_thankyou_pages_v2', time(), false );
	try {
		ricoman_nest_and_wire_thankyou();
	} catch ( \Throwable $e ) {
		delete_option( 'ricoman_thankyou_pages_v2' );
	}
}, 101 );

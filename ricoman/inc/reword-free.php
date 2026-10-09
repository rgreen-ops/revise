<?php
/**
 * One-time content migration — reword lighting-design copy on ALREADY-BUILT pages.
 *
 * The theme code was reworded so lighting-design copy leads with the service and
 * says "complimentary on commercial projects" instead of "free". Pages that were
 * seeded once have the old wording baked into their saved content, which a code
 * change can't retro-edit. This migration performs TARGETED phrase swaps across
 * saved page / project content + the category-SEO copy so the built pages match.
 *
 * Deliberately surgical: it only swaps the specific phrases below, so every other
 * part of a page — including hand-added sections like the Contact "Technical
 * support" band — is left exactly as it is. Behind-the-scenes SEO titles/meta keep
 * the "free lighting design" keyword and are NOT touched.
 *
 * Idempotent + run-once via an option flag; bump the flag suffix to re-run.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ordered old => new phrase map. Order matters: the most specific entries run
 * first so a later general swap can't pre-empt them (e.g. the eyebrow variant of
 * "Free Scheme Design" is handled before the generic button label).
 */
function ricoman_reword_free_pairs() {
	return array(
		// Eyebrow / kicker label (before the generic "Free Scheme Design" swap).
		'rm-eyebrow">Free Scheme Design' => 'rm-eyebrow">Lighting Design Service',
		'kick lt">Free Scheme Design'    => 'kick lt">Lighting Design Service',

		// Lighting Design hero headline + intro.
		'Your scheme, fully designed — at no cost.' => 'Your commercial scheme, fully designed.',
		'Send us a drawing or a finishes schedule and our in-house lighting designers return a fully specified, photometric-backed and costed scheme. Usually within 3–5 days.'
			=> 'For architects, specifiers, contractors and fit-out teams. Send us a drawing or a finishes schedule and our in-house lighting designers return a fully specified, photometric-backed and costed scheme — complimentary on commercial projects, usually within 3–5 days.',

		// FAQ answers — drop "free, " and add the complimentary note.
		'returns a free, costed, DIALux-backed scheme, usually within 3-5 working days'
			=> 'returns a costed, DIALux-backed scheme, complimentary on commercial projects, usually within 3-5 working days',
		'send a plan and our team returns a free, costed photometric scheme'
			=> 'send a plan and our team returns a costed photometric scheme, complimentary on commercial projects',
		'send your plans and our team returns a free, costed scheme, usually within 3-5 days'
			=> 'send your plans and our team returns a costed scheme, complimentary on commercial projects, usually within 3-5 days',
		'send a plan and our team returns a free, costed scheme, usually within 3-5 days'
			=> 'send a plan and our team returns a costed scheme, complimentary on commercial projects, usually within 3-5 days',
		'send a plan and our team returns a free, costed scheme.'
			=> 'send a plan and our team returns a costed scheme, complimentary on commercial projects.',
		'Yes — send a floor plan and our in-house team returns a free, costed, DIALux-backed scheme, usually within 3-5 working days.'
			=> 'Yes — send a floor plan and our in-house team returns a costed, DIALux-backed scheme, complimentary on commercial projects, usually within 3-5 working days.',

		// FAQ questions.
		'Is lighting design really free?'                       => 'Is the lighting design service really complimentary?',
		'Is the lighting design service really free?'           => 'Is the lighting design service really complimentary?',
		'Do you offer free lighting design for linear schemes?' => 'Do you offer a lighting design service for linear schemes?',
		'Do you offer free lighting design?'                    => 'Do you offer a lighting design service?',

		// Buttons / arrows.
		'Free Scheme Design ↓'           => 'Lighting Design ↓',
		'Get A Free Scheme →'            => 'Request a Scheme Design →',
		'Request a free lighting design' => 'Request a lighting design',
		'request a free lighting scheme' => 'request a lighting scheme',
		'request a free lighting design' => 'request a lighting design',

		// Phrases (specific before general).
		'Get free lighting advice' => 'Get expert lighting advice',
		'Free help'                => 'Design support',
		'Free layout design'       => 'In-house layout design',
		'free layout design'       => 'in-house layout design',
		'free photometric design'  => 'in-house photometric design',
		'free design service'      => 'in-house design service',
		'free design support'      => 'in-house design support',
		'Free lighting design'     => 'In-house lighting design',
		'free lighting design'     => 'in-house lighting design',
		'Free scheme design'       => 'In-house scheme design',
		'free scheme design'       => 'in-house scheme design',
		'Free Scheme Design'       => 'Lighting Design',
		'free of charge'           => 'complimentary on commercial projects',

		// Trailing "— free." / ", free." tails on CTA sub-lines + checklists.
		'scheme — free.'               => 'scheme — complimentary on commercial projects.',
		'that sells — free.'           => 'that sells — complimentary on commercial projects.',
		'the savings modelled — free.' => 'the savings modelled — complimentary on commercial projects.',
		'days, free.'                  => 'days, complimentary on commercial projects.',
	);
}

/** Apply the phrase map to a string. Returns the (possibly) rewritten string. */
function ricoman_reword_free_apply( $text ) {
	if ( ! is_string( $text ) || '' === $text ) {
		return $text;
	}
	foreach ( ricoman_reword_free_pairs() as $from => $to ) {
		if ( false !== strpos( $text, $from ) ) {
			$text = str_replace( $from, $to, $text );
		}
	}
	return $text;
}

/**
 * Run the migration across pages, project case studies and category-SEO copy.
 * Returns a summary array (also stored in the ricoman_reword_free_report option).
 */
function ricoman_reword_free_run() {
	$report = array( 'posts' => array(), 'terms' => 0, 'products_seo' => false );

	// Pages + project case studies (baked block / HTML content).
	$ids = get_posts( array(
		'post_type'      => array( 'page', 'project' ),
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	foreach ( $ids as $id ) {
		$content = (string) get_post_field( 'post_content', $id );
		$new     = ricoman_reword_free_apply( $content );
		if ( $new !== $content ) {
			wp_update_post( array( 'ID' => (int) $id, 'post_content' => $new ) );
			$report['posts'][] = array( 'id' => (int) $id, 'title' => get_the_title( $id ) );
		}
	}

	// Category SEO body (per product-cat term).
	$terms = get_terms( array( 'taxonomy' => 'product-cat', 'hide_empty' => false, 'fields' => 'ids' ) );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $tid ) {
			$changed = false;
			foreach ( array( '_rm_cat_seo_body', '_rm_cat_seo_intro' ) as $mk ) {
				$val = (string) get_term_meta( $tid, $mk, true );
				$new = ricoman_reword_free_apply( $val );
				if ( $new !== $val ) {
					update_term_meta( $tid, $mk, $new );
					$changed = true;
				}
			}
			if ( $changed ) {
				$report['terms']++;
			}
		}
	}

	// Products-archive SEO body (single option).
	$pbody = (string) get_option( 'rm_products_seo_body', '' );
	if ( '' !== $pbody ) {
		$new = ricoman_reword_free_apply( $pbody );
		if ( $new !== $pbody ) {
			update_option( 'rm_products_seo_body', $new );
			update_option( 'rm_products_ver', (string) time(), false ); // refresh cached SEO body.
			$report['products_seo'] = true;
		}
	}

	update_option( 'ricoman_reword_free_report', $report, false );
	return $report;
}

/**
 * Fire once, early, on a normal (non-AJAX/cron/REST) request. The flag is set
 * before running so a concurrent request can't double-run it.
 */
add_action( 'init', function () {
	if ( get_option( 'ricoman_reword_free_v1' ) ) {
		return;
	}
	if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}
	update_option( 'ricoman_reword_free_v1', time(), false ); // claim, so concurrent requests don't pile up.
	try {
		ricoman_reword_free_run();
	} catch ( \Throwable $e ) {
		delete_option( 'ricoman_reword_free_v1' ); // release so it retries on a later request.
	}
}, 99 );

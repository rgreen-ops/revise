<?php
/**
 * Sector landing pages (project-cat term archives at /sector/<slug>/).
 *
 * These are the crawlable, rankable pages for sector keywords ("office
 * lighting", etc). This file adds the conversion + SEO sections used by
 * templates/taxonomy-project-cat.html:
 *   - [ricoman_sector_products] : recommended product ranges (links to the
 *     product-category pages — internal links + conversion).
 *   - [ricoman_sector_leadgen]  : a compact name/email capture (reuses the
 *     lead pipeline) plus the lighting-design CTA.
 *   - [ricoman_sector_faq]      : an editable FAQ + FAQPage schema.
 * Plus a Topics-style FAQ editor on the term so the team can tune each page.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vanity short-URL → canonical target 301 redirects (path => target). Lets the
 * clean /office-lighting/ URL send its authority to the commercial sector hub.
 * Runs early so it wins over a 404; filterable so the team can add more.
 */
function ricoman_vanity_redirects() {
	return apply_filters( 'ricoman_vanity_redirects', array(
		'office-lighting' => '/sector/office-lighting/',
	) );
}
add_action( 'template_redirect', function () {
	if ( is_admin() ) {
		return;
	}
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	$map  = ricoman_vanity_redirects();
	if ( '' !== $path && isset( $map[ $path ] ) ) {
		wp_safe_redirect( home_url( $map[ $path ] ), 301 );
		exit;
	}
}, 0 );

/**
 * Funnel news articles to the sector hubs: add every sector's phrase to the
 * article auto-linker, so a mention of "office lighting", "gym lighting", etc.
 * links to that sector landing page (informational article -> commercial hub).
 */
add_filter( 'ricoman_news_link_map', function ( $map ) {
	if ( ! taxonomy_exists( 'project-cat' ) ) {
		return $map;
	}
	$terms = get_terms( array( 'taxonomy' => 'project-cat', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return $map;
	}
	$sector = array();
	foreach ( $terms as $t ) {
		$link = get_term_link( $t );
		if ( is_wp_error( $link ) ) {
			continue;
		}
		// Phrase from the slug, e.g. "office-lighting" -> "office lighting".
		$kw = trim( str_replace( '-', ' ', $t->slug ) );
		if ( '' !== $kw ) {
			$sector[ $kw ] = $link;
		}
	}
	// Sector phrases first so they win over generic product terms when both match.
	return array_merge( $sector, $map );
}, 5 );

/** Product-category slugs to recommend for a sector (filterable). */
function ricoman_sector_product_cats( $sector_slug ) {
	$map = array(
		'office-lighting'                => array( 'led-linear-lighting', 'led-panel-lights', 'led-downlights' ),
		'hospitality-leisure-lighting'   => array( 'pendants', 'led-track-lights', 'led-downlights' ),
		'education-lighting'             => array( 'led-panel-lights', 'led-linear-lighting', 'led-emergency' ),
		'healthcare-lighting'            => array( 'led-panel-lights', 'led-downlights', 'led-emergency' ),
		'care-home-lighting'             => array( 'led-downlights', 'led-panel-lights', 'led-emergency' ),
		'industrial-warehouse-lighting'  => array( 'industrial-led-lighting', 'led-linear-lighting', 'led-bulkheads' ),
		'warehouse-lighting'             => array( 'industrial-led-lighting', 'led-linear-lighting' ),
		'factory-lighting'               => array( 'industrial-led-lighting', 'led-linear-lighting' ),
		'retail-lighting'                => array( 'led-track-lights', 'led-downlights', 'pendants' ),
		'gym-lighting'                   => array( 'led-linear-lighting', 'industrial-led-lighting', 'led-panel-lights' ),
		'residential-lighting'           => array( 'led-downlights', 'pendants', 'led-strip' ),
		'refrigeration-lighting'         => array( 'led-linear-lighting', 'led-strip' ),
	);
	$cats = isset( $map[ $sector_slug ] ) ? $map[ $sector_slug ] : array( 'led-linear-lighting', 'led-downlights', 'led-panel-lights' );
	return apply_filters( 'ricoman_sector_product_cats', $cats, $sector_slug );
}

/** Representative image for a product-category term (first product's image). */
function ricoman_pcat_image( $term ) {
	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array( 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ) ),
	) );
	if ( $q->posts && function_exists( 'ricoman_product_img' ) ) {
		return ricoman_product_img( (int) $q->posts[0] );
	}
	return '';
}

/** Recommended product ranges for the current sector. [ricoman_sector_products] */
add_shortcode( 'ricoman_sector_products', function () {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$ptax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$cards = '';
	foreach ( ricoman_sector_product_cats( $term->slug ) as $slug ) {
		$pterm = get_term_by( 'slug', $slug, $ptax );
		if ( ! $pterm || is_wp_error( $pterm ) ) {
			continue;
		}
		$img   = ricoman_pcat_image( $pterm );
		$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$cards .= '<a class="rm-secprod-card" href="' . esc_url( get_term_link( $pterm ) ) . '">'
			. '<span class="rm-secprod-img"' . $style . '></span>'
			. '<span class="rm-secprod-body"><span class="rm-secprod-t">' . esc_html( $pterm->name ) . '</span>'
			. '<span class="rm-secprod-go">View range →</span></span></a>';
	}
	if ( '' === $cards ) {
		return '';
	}
	return '<div class="rm-section rm-secprod"><div class="rm-pp-wrap">'
		. '<p class="rm-eyebrow">Specify with confidence</p>'
		. '<h2 class="rm-shead">Recommended ranges for ' . esc_html( $term->name ) . '</h2>'
		. '<div class="rm-secprod-grid">' . $cards . '</div>'
		. '<p class="rm-secprod-all"><a href="/products/">Browse the full product range →</a></p>'
		. '</div></div>';
} );

/** Compact name/email lead capture + lighting-design CTA. [ricoman_sector_leadgen] */
add_shortcode( 'ricoman_sector_leadgen', function () {
	$term  = get_queried_object();
	$label = ( $term instanceof WP_Term ) ? $term->name : 'your project';
	$sent  = isset( $_GET['lead'] ) && 'ok' === sanitize_key( wp_unslash( $_GET['lead'] ) );
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$here   = esc_url( get_term_link( $term ) );

	$form = $sent
		? '<p class="rm-secld-thanks">Thanks — we\'ll be in touch shortly with ' . esc_html( strtolower( $label ) ) . ' guidance.</p>'
		: '<form class="rm-secld-form" method="post" action="' . $action . '">'
			. '<input type="hidden" name="action" value="ricoman_lead">'
			. '<input type="hidden" name="lead_source" value="Sector: ' . esc_attr( $label ) . '">'
			. '<input type="hidden" name="redirect_to" value="' . $here . '">'
			. '<div aria-hidden="true" style="position:absolute;left:-9999px"><label>Website<input type="text" name="ricoman_hp" tabindex="-1" autocomplete="off"></label></div>'
			. '<input type="text" name="lead_name" placeholder="Your name" aria-label="Your name" required>'
			. '<input type="email" name="lead_email" placeholder="Work email" aria-label="Work email" required>'
			. '<button type="submit" class="btn btn-solid">Get lighting advice</button>'
			. '</form>'
			. '<p class="rm-secld-small">No spam — just expert ' . esc_html( strtolower( $label ) ) . ' guidance from our UK design team.</p>';

	return '<div class="rm-section rm-secld"><div class="rm-pp-wrap"><div class="rm-secld-in">'
		. '<div class="rm-secld-copy"><p class="rm-eyebrow" style="color:rgba(255,255,255,.7)">Free help</p>'
		. '<h2 class="rm-secld-h">Planning ' . esc_html( strtolower( $label ) ) . '?</h2>'
		. '<p class="rm-secld-p">Tell us where to send it and our in-house designers will help you specify the right scheme — beam, output, finish and controls. Prefer to send drawings? <a href="/lighting-design/">Request a free lighting design →</a></p></div>'
		. '<div class="rm-secld-formwrap">' . $form . '</div>'
		. '</div></div></div>';
} );

/* ------------------------------------------------------------------ FAQ */

/** FAQ items for a sector: term meta ("Q :: A" per line) or sensible defaults. */
function ricoman_sector_faq_items( $term ) {
	$raw  = $term instanceof WP_Term ? (string) get_term_meta( $term->term_id, '_rm_faq', true ) : '';
	$name = $term instanceof WP_Term ? $term->name : 'lighting';
	$out  = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$parts = array_map( 'trim', explode( '::', $line, 2 ) );
		if ( count( $parts ) === 2 && '' !== $parts[0] && '' !== $parts[1] ) {
			$out[] = array( $parts[0], $parts[1] );
		}
	}
	if ( $out ) {
		return $out;
	}
	// Defaults — useful, keyword-bearing, conversion-oriented.
	return array(
		array( 'How do I get a lighting design for my ' . strtolower( $name ) . ' project?', 'Send us your drawings or a finishes schedule and our in-house team returns a fully specified, costed scheme — usually within 3–5 days. Start at our <a href="/lighting-design/">lighting design</a> page.' ),
		array( 'Which products do you recommend for ' . strtolower( $name ) . '?', 'It depends on the space and the look you want — explore our <a href="/products/">product ranges</a> or ask our team and we\'ll recommend the right fittings, beam angles and finishes.' ),
		array( 'Are your luminaires made in the UK?', 'Yes — we design and manufacture in Manchester, which means short lead times, UK stock and close support on spares and specials.' ),
	);
}

/** FAQ accordion + FAQPage schema for the current sector. [ricoman_sector_faq] */
add_shortcode( 'ricoman_sector_faq', function () {
	$term  = get_queried_object();
	$items = ricoman_sector_faq_items( $term );
	if ( ! $items ) {
		return '';
	}
	$html  = '';
	$schema = array();
	foreach ( $items as $i => $qa ) {
		$html .= '<details class="rm-faq-item"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $qa[0] ) . '</summary>'
			. '<div class="rm-faq-a">' . wp_kses_post( wpautop( $qa[1] ) ) . '</div></details>';
		$schema[] = array(
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( $qa[0] ),
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( $qa[1] ) ),
		);
	}
	$ld = wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return '<div class="rm-section rm-faq"><div class="rm-pp-wrap rm-faq-in">'
		. '<h2 class="rm-shead">' . esc_html( $term instanceof WP_Term ? $term->name . ' — FAQs' : 'FAQs' ) . '</h2>'
		. $html . '</div></div>'
		. '<script type="application/ld+json">' . $ld . '</script>';
} );

/* -------------------------------------------------- term FAQ editor (admin) */
add_action( 'project-cat_edit_form_fields', function ( $term ) {
	$val = esc_textarea( (string) get_term_meta( $term->term_id, '_rm_faq', true ) );
	echo '<tr class="form-field"><th scope="row"><label for="rm_faq">' . esc_html__( 'FAQs (SEO)', 'ricoman' ) . '</label></th><td>';
	echo '<textarea name="rm_faq" id="rm_faq" rows="6" class="large-text" placeholder="Question :: Answer (one per line)">' . $val . '</textarea>';
	echo '<p class="description">' . esc_html__( 'One per line as "Question :: Answer". Shown as an FAQ on the sector page with FAQ schema. Leave blank to use sensible defaults.', 'ricoman' ) . '</p></td></tr>';
} );
add_action( 'edited_project-cat', function ( $term_id ) {
	if ( isset( $_POST['rm_faq'] ) ) {
		update_term_meta( $term_id, '_rm_faq', sanitize_textarea_field( wp_unslash( $_POST['rm_faq'] ) ) );
	}
} );

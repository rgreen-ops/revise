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

/** Let products be tagged with sectors too — adds a "Sectors" tick-box panel to
 * the product editor, so the team controls which products feature on each
 * sector page. (The taxonomy itself is registered for projects in post-types.) */
add_action( 'init', function () {
	if ( taxonomy_exists( 'project-cat' ) ) {
		register_taxonomy_for_object_type( 'project-cat', 'product' );
	}
}, 12 );

/* Redirects are managed in inc/redirects.php (Ricoman → Links & Redirects). */

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

/** Big visual hero for a sector — leads with real project imagery. [ricoman_sector_hero] */
add_shortcode( 'ricoman_sector_hero', function () {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$img = '';
	$q   = new WP_Query( array(
		'post_type'      => 'project',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'tax_query'      => array( array( 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ) ),
	) );
	if ( $q->posts && function_exists( 'ricoman_project_img' ) ) {
		$img = ricoman_project_img( (int) $q->posts[0] );
	}
	if ( ! $img ) {
		$img = get_theme_file_uri( 'assets/images/office1.webp' );
	}
	$hook  = sprintf( 'See how we light %s across the UK — specified, manufactured and delivered by our Manchester team.', strtolower( $term->name ) );
	$crumb = function_exists( 'ricoman_breadcrumbs_html' ) ? ricoman_breadcrumbs_html() : '';
	return '<div class="rm-sechero" style="background-image:url(' . esc_url( $img ) . ')">'
		. '<span class="rm-sechero-scrim" aria-hidden="true"></span>'
		. '<div class="rm-pp-wrap rm-sechero-in">'
		. ( $crumb ? '<div class="rm-sechero-crumb">' . $crumb . '</div>' : '' )
		. '<p class="rm-eyebrow rm-sechero-eyebrow">Projects by sector</p>'
		. '<h1 class="rm-sechero-title">' . esc_html( $term->name ) . '</h1>'
		. '<p class="rm-sechero-hook">' . esc_html( $hook ) . '</p>'
		. '<div class="rm-sechero-cta"><a class="btn btn-solid" href="#enquire">Get expert lighting advice</a> '
		. '<a class="btn btn-line" href="#projects">See the projects ↓</a></div>'
		. '</div></div>';
} );

/** Sector intro / selling copy — term description, else a useful default. [ricoman_sector_intro] */
add_shortcode( 'ricoman_sector_intro', function () {
	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$desc = trim( (string) term_description( $term ) );
	if ( '' === $desc ) {
		$name = strtolower( $term->name );
		$desc = '<p>' . esc_html( sprintf( 'Lighting for %s has to perform — the right output and comfort for the people using the space, low glare, and fittings that last. As a UK manufacturer, Ricoman designs, makes and delivers complete %s schemes: photometrically specified, delivered on short lead times and backed by a 5-year warranty.', $name, $name ) ) . '</p>'
			. '<p>' . esc_html( sprintf( 'Explore the ranges and real %s projects below — or tell us about your scheme and our in-house designers will spec it for you, complimentary on commercial projects.', $name ) ) . '</p>';
	}
	return '<div class="rm-section rm-sectorintro"><div class="rm-pp-wrap rm-sectorintro-in">'
		. '<div class="rm-sector-desc">' . wp_kses_post( $desc ) . '</div>'
		. '</div></div>';
} );

/** Grid of every sector, linking to its hub. [ricoman_sectors_grid] */
add_shortcode( 'ricoman_sectors_grid', function () {
	$tax   = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$cards = '';
	foreach ( $terms as $t ) {
		$img = '';
		$q   = new WP_Query( array(
			'post_type'      => 'project',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => $t->term_id ) ),
		) );
		if ( $q->posts ) {
			$img = function_exists( 'ricoman_project_img' ) ? ricoman_project_img( (int) $q->posts[0] ) : get_the_post_thumbnail_url( (int) $q->posts[0], 'large' );
		}
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$cards .= '<a class="rm-projcard" href="' . esc_url( get_term_link( $t ) ) . '"' . $style . '><span class="rm-projcard-ov">'
			. '<span class="rm-projcard-t">' . esc_html( $t->name ) . '</span>'
			. '<span class="rm-projcard-loc">' . (int) $t->count . ' ' . esc_html( _n( 'project', 'projects', (int) $t->count, 'ricoman' ) ) . '</span>'
			. '</span></a>';
	}
	return '<div class="rm-pp-wrap rm-projwide"><div class="rm-projgrid">' . $cards . '</div></div>';
} );

/** Big, bold, sharp sector tiles for the showcase. [ricoman_sectors_showcase] */
add_shortcode( 'ricoman_sectors_showcase', function () {
	$tax   = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$tiles = '';
	foreach ( $terms as $t ) {
		$img = '';
		$q   = new WP_Query( array(
			'post_type'      => 'project',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => $t->term_id ) ),
		) );
		if ( $q->posts && function_exists( 'ricoman_project_img' ) ) {
			$img = ricoman_project_img( (int) $q->posts[0] );
		}
		if ( ! $img ) {
			$img = get_theme_file_uri( 'assets/images/office1.webp' );
		}
		$tag = trim( (string) get_term_meta( $t->term_id, '_rm_tagline', true ) );
		if ( '' === $tag ) {
			$tag = sprintf( _n( '%d project', '%d projects', (int) $t->count, 'ricoman' ), (int) $t->count );
		}
		$tiles .= '<a class="rm-secshow-tile" href="' . esc_url( get_term_link( $t ) ) . '" style="background-image:url(' . esc_url( $img ) . ')">'
			. '<span class="rm-secshow-ov"><span class="rm-secshow-tag">' . esc_html( $tag ) . '</span>'
			. '<span class="rm-secshow-t">' . esc_html( $t->name ) . '</span>'
			. '<span class="rm-secshow-go">View sector →</span></span></a>';
	}
	return '<div class="rm-pp-wrap rm-secshow-wrap"><div class="rm-secshow-grid">' . $tiles . '</div></div>';
} );

/** Editable per-sector call-out (tagline) on the project-cat term screen. */
add_action( 'project-cat_edit_form_fields', function ( $term ) {
	$val = esc_attr( (string) get_term_meta( $term->term_id, '_rm_tagline', true ) );
	echo '<tr class="form-field"><th scope="row"><label for="rm_tagline">' . esc_html__( 'Showcase call-out', 'ricoman' ) . '</label></th><td>';
	echo '<input type="text" name="rm_tagline" id="rm_tagline" class="large-text" value="' . $val . '" placeholder="e.g. Light that performs under pressure">';
	echo '<p class="description">' . esc_html__( 'Short, punchy line shown over this sector\'s tile on the Lighting by Sector page.', 'ricoman' ) . '</p></td></tr>';
} );
add_action( 'edited_project-cat', function ( $term_id ) {
	if ( isset( $_POST['rm_tagline'] ) ) {
		update_term_meta( $term_id, '_rm_tagline', sanitize_text_field( wp_unslash( $_POST['rm_tagline'] ) ) );
	}
} );

/** Build/refresh the "Lighting by Sector" showcase page (slug: sectors). */
add_action( 'admin_init', function () {
	$version = 3; // bump to rebuild the page design once.
	$cur     = (int) get_option( 'ricoman_sectors_page', 0 );
	if ( $cur >= $version ) {
		return;
	}
	$cta  = esc_url( get_theme_file_uri( 'assets/images/office1.webp' ) );
	$cover = function ( $url, $inner, $min, $dim, $pos = 'bottom left' ) {
		$poscls = 'center center' === $pos ? '' : ' has-custom-content-position is-position-' . str_replace( ' ', '-', $pos );
		return '<!-- wp:cover {"url":"' . $url . '","dimRatio":' . $dim . ',"overlayColor":"ink","minHeight":' . $min . ',"minHeightUnit":"vh","contentPosition":"' . $pos . '","align":"full","textColor":"base"} --><div class="wp-block-cover alignfull has-base-color has-text-color' . $poscls . '" style="min-height:' . $min . 'vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-' . $dim . ' has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container">' . $inner . '</div></div><!-- /wp:cover -->';
	};

	// Match the Projects screen: editorial header + masonry card grid + CTA.
	$content  = '<!-- wp:group {"align":"full","className":"rm-section rm-projintro","layout":{"type":"constrained"}} --><div class="wp-block-group alignfull rm-section rm-projintro">'
		. '<!-- wp:paragraph {"className":"rm-eyebrow"} --><p class="rm-eyebrow">By Application</p><!-- /wp:paragraph -->'
		. '<!-- wp:heading {"level":1,"className":"rm-projintro-h","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.4rem,5vw,4rem)","lineHeight":"1.02","letterSpacing":"-0.02em"}}} --><h1 class="wp-block-heading rm-projintro-h" style="font-size:clamp(2.4rem,5vw,4rem);font-weight:500;letter-spacing:-0.02em;line-height:1.02">Lighting by sector</h1><!-- /wp:heading -->'
		. '<!-- wp:paragraph {"className":"rm-projintro-sub","textColor":"muted"} --><p class="rm-projintro-sub has-muted-color has-text-color">From workplace and hospitality to healthcare, retail and industrial — explore our lighting solutions and real project case studies by sector.</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
	$content .= '<!-- wp:group {"align":"full","className":"rm-section rm-projwide-outer","layout":{"type":"default"}} --><div class="wp-block-group alignfull rm-section rm-projwide-outer"><!-- wp:shortcode -->[ricoman_sectors_grid]<!-- /wp:shortcode --></div><!-- /wp:group -->';

	$content .= $cover(
		$cta,
		'<!-- wp:heading {"textAlign":"center","level":2} --><h2 class="wp-block-heading has-text-align-center">Have a project on the board?</h2><!-- /wp:heading -->'
		. '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">Tell us about your scheme and our in-house designers will spec the range, beam and finish — and return a costed scheme, usually within 3–5 days.</p><!-- /wp:paragraph -->'
		. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons is-content-justification-center"><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/lighting-design/">Start a project</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="/contact/">Talk to the team →</a></div><!-- /wp:button --></div><!-- /wp:buttons -->',
		48, 70, 'center center'
	);

	$page = get_page_by_path( 'sectors' );
	if ( $page ) {
		wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ) );
	} else {
		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Lighting by Sector',
			'post_name'    => 'sectors',
			'post_content' => $content,
		) );
	}
	update_option( 'ricoman_sectors_page', $version );
} );

/** Trust / credibility bar for the sector landing (conversion signals). */
add_shortcode( 'ricoman_sector_trust', function () {
	$items = array(
		array( 'UK manufactured', 'Designed &amp; built in Manchester' ),
		array( '5-year warranty', 'On every luminaire, as standard' ),
		array( 'In-house lighting design', 'Fully specified scheme in 3–5 days' ),
		array( 'UK stock', 'Short lead times, held in Manchester' ),
	);
	$cells = '';
	foreach ( $items as $it ) {
		$cells .= '<div class="rm-sectrust-item"><span class="rm-sectrust-h">' . wp_kses_post( $it[0] ) . '</span>'
			. '<span class="rm-sectrust-s">' . wp_kses_post( $it[1] ) . '</span></div>';
	}
	return '<div class="rm-section rm-sectrust"><div class="rm-pp-wrap"><div class="rm-sectrust-row">' . $cells . '</div></div></div>';
} );

/** Product-category slugs to recommend for a sector (filterable). */
function ricoman_sector_product_cats( $sector_slug ) {
	$map = array(
		'office-lighting'                => array( 'led-linear-lighting', 'led-panel-lights', 'led-downlights', 'led-track-lights', 'pendants', 'led-emergency' ),
		'hospitality-leisure-lighting'   => array( 'pendants', 'led-track-lights', 'led-downlights', 'led-linear-lighting', 'led-strip', 'led-emergency' ),
		'education-lighting'             => array( 'led-panel-lights', 'led-linear-lighting', 'led-downlights', 'led-emergency', 'led-bulkheads', 'led-track-lights' ),
		'healthcare-lighting'            => array( 'led-panel-lights', 'led-downlights', 'led-linear-lighting', 'led-emergency', 'led-bulkheads', 'led-track-lights' ),
		'care-home-lighting'             => array( 'led-downlights', 'led-panel-lights', 'led-linear-lighting', 'led-emergency', 'pendants', 'led-bulkheads' ),
		'industrial-warehouse-lighting'  => array( 'industrial-led-lighting', 'led-linear-lighting', 'led-bulkheads', 'led-emergency', 'led-panel-lights', 'outdoor-exterior-lighting' ),
		'warehouse-lighting'             => array( 'industrial-led-lighting', 'led-linear-lighting', 'led-bulkheads', 'led-emergency', 'led-panel-lights', 'outdoor-exterior-lighting' ),
		'factory-lighting'               => array( 'industrial-led-lighting', 'led-linear-lighting', 'led-bulkheads', 'led-emergency', 'led-panel-lights', 'led-downlights' ),
		'retail-lighting'                => array( 'led-track-lights', 'led-downlights', 'pendants', 'led-linear-lighting', 'led-strip', 'led-panel-lights' ),
		'gym-lighting'                   => array( 'led-linear-lighting', 'industrial-led-lighting', 'led-panel-lights', 'led-downlights', 'led-strip', 'led-emergency' ),
		'residential-lighting'           => array( 'led-downlights', 'pendants', 'led-strip', 'led-linear-lighting', 'led-track-lights', 'outdoor-exterior-lighting' ),
		'refrigeration-lighting'         => array( 'led-linear-lighting', 'led-strip', 'led-bulkheads', 'led-panel-lights' ),
	);
	$cats = isset( $map[ $sector_slug ] ) ? $map[ $sector_slug ] : array( 'led-linear-lighting', 'led-downlights', 'led-panel-lights', 'led-track-lights', 'pendants', 'led-emergency' );
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

/** Recommended products + ranges for the current sector. [ricoman_sector_products sector="slug"] */
add_shortcode( 'ricoman_sector_products', function ( $atts ) {
	$atts = shortcode_atts( array( 'sector' => '' ), $atts, 'ricoman_sector_products' );
	$tax  = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';
	$term = $atts['sector'] ? get_term_by( 'slug', $atts['sector'], $tax ) : get_queried_object();
	if ( ! ( $term instanceof WP_Term ) ) {
		return '';
	}
	$out = '';

	// 1) Products tagged to this sector (tick a product's "Sectors" box to feature
	//    it here). Shown first when present.
	$pq = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 8,
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'tax_query'      => array( array( 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ) ),
	) );
	if ( $pq->have_posts() ) {
		$cards = '';
		foreach ( $pq->posts as $p ) {
			$img   = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $p->ID ) : get_the_post_thumbnail_url( $p->ID, 'large' );
			$sub   = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $p->ID, 'product_subname' ) : '';
			$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
			$cards .= '<a class="rm-secprod-card" href="' . esc_url( get_permalink( $p ) ) . '">'
				. '<span class="rm-secprod-img"' . $style . '></span>'
				. '<span class="rm-secprod-body">'
				. ( $sub ? '<span class="rm-secprod-sub">' . esc_html( $sub ) . '</span>' : '' )
				. '<span class="rm-secprod-t">' . esc_html( get_the_title( $p ) ) . '</span>'
				. '<span class="rm-secprod-go">View product →</span></span></a>';
		}
		$out .= '<div class="rm-section rm-secprod"><div class="rm-pp-wrap">'
			. '<p class="rm-eyebrow">Specified for this sector</p>'
			. '<h2 class="rm-shead">Recommended products for ' . esc_html( $term->name ) . '</h2>'
			. '<div class="rm-secprod-grid">' . $cards . '</div></div></div>';
	}

	// The "Specify with confidence · Explore the ranges" product-category grid was
	// removed from the sector (By Application) pages on request. The recommended
	// PRODUCTS above (when a sector has tagged products) stay.
	return $out;
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

	return '<div class="rm-section rm-secld" id="enquire"><div class="rm-pp-wrap"><div class="rm-secld-in">'
		. '<div class="rm-secld-copy"><p class="rm-eyebrow" style="color:rgba(255,255,255,.7)">Design support</p>'
		. '<h2 class="rm-secld-h">Planning ' . esc_html( strtolower( $label ) ) . '?</h2>'
		. '<p class="rm-secld-p">Tell us where to send it and our in-house designers will help you specify the right scheme — beam, output, finish and controls. Prefer to send drawings? <a href="/lighting-design/">Request a lighting design →</a></p></div>'
		. '<div class="rm-secld-formwrap">' . $form . '</div>'
		. '</div></div></div>';
} );

/**
 * Reusable pattern so the team can drop the "products, then projects" sector
 * block onto ANY page (＋ → Patterns → Ricoman — Page → "Sector · Products →
 * Projects") and edit it in the editor. Set the sector slug in each Shortcode
 * block (e.g. sector="retail-lighting"); on a sector landing page itself the
 * shortcodes auto-detect the current sector, so the slug is only needed elsewhere.
 */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$tax     = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';
	$terms   = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true, 'number' => 1 ) );
	$example = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->slug : 'office-lighting';
	register_block_pattern( 'ricoman/sector-products-projects', array(
		'title'       => __( 'Sector · Products → Projects', 'ricoman' ),
		'description' => __( 'Recommended products for a sector, followed by that sector’s projects. Set the sector slug in each shortcode block.', 'ricoman' ),
		'categories'  => array( 'ricoman-page' ),
		'content'     => '<!-- wp:shortcode -->[ricoman_sector_products sector="' . esc_attr( $example ) . '"]<!-- /wp:shortcode -->'
			. "\n\n" . '<!-- wp:shortcode -->[ricoman_sector_projects sector="' . esc_attr( $example ) . '"]<!-- /wp:shortcode -->',
	) );
}, 13 );

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

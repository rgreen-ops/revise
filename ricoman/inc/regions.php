<?php
/**
 * Regional landing pages (targeted-ad microsites).
 *
 * Adds a shared `region` taxonomy to Projects and Team members so a single tag
 * drives regional marketing pages (e.g. /north-west-lighting-consultant/):
 *   - [ricoman_region_hero region="north-west"]     : big project-led hero.
 *   - [ricoman_region_intro region="north-west"]    : editable selling copy.
 *   - [ricoman_region_projects region="north-west"] : auto-grid of that region's projects.
 *   - [ricoman_region_agents region="north-west"]   : the local agent card(s),
 *       falling back to a default contact when a region has no team member yet.
 * A "Region · Landing page" pattern ties them together; set the region slug once
 * per page. Tag a project or team member with its region and it appears on the
 * matching page automatically. The taxonomy is admin-only (no /region/ archive) —
 * the public pages are ordinary Pages you build with the pattern, so you keep
 * full control of the URL, copy and SEO.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * Taxonomy: region (Projects + Team). Admin-only tagging, no public archive.
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	$objects = array_values( array_filter( array( 'project', 'staff' ), 'post_type_exists' ) );
	register_taxonomy( 'region', $objects, array(
		'labels'            => array(
			'name'          => __( 'Regions', 'ricoman' ),
			'singular_name' => __( 'Region', 'ricoman' ),
			'menu_name'     => __( 'Regions', 'ricoman' ),
			'all_items'     => __( 'All regions', 'ricoman' ),
			'edit_item'     => __( 'Edit region', 'ricoman' ),
			'add_new_item'  => __( 'Add region', 'ricoman' ),
			'search_items'  => __( 'Search regions', 'ricoman' ),
		),
		'public'            => false,
		'show_ui'           => true,
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'hierarchical'      => false,
		'rewrite'           => false,
	) );
}, 11 );

/**
 * The regions Ricoman sells into. Slug => display name. Filterable so the team
 * can add/rename in code, but they can also add terms in the admin (Projects →
 * Regions). Seeded create-only, so admin edits are never overwritten.
 */
function ricoman_regions_default() {
	return apply_filters( 'ricoman_regions_default', array(
		'north-west'       => 'North West',
		'midlands'         => 'Midlands',
		'london'           => 'London',
		'south'            => 'South',
		'wales'            => 'Wales',
		'scotland'         => 'Scotland',
		'northern-ireland' => 'Northern Ireland',
		'channel-islands'  => 'Channel Islands',
		'middle-east'      => 'Middle East',
	) );
}

// Seed the region terms once (create-only — never clobbers renamed/added terms).
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_regions_seeded_v1' ) ) {
		return;
	}
	if ( ! taxonomy_exists( 'region' ) ) {
		return;
	}
	foreach ( ricoman_regions_default() as $slug => $name ) {
		if ( ! term_exists( $slug, 'region' ) ) {
			wp_insert_term( $name, 'region', array( 'slug' => $slug ) );
		}
	}
	update_option( 'ricoman_regions_seeded_v1', '1' );
} );

/* ---------------------------------------------------------------------------
 * Helpers.
 * ------------------------------------------------------------------------- */

/** Resolve a region attribute (slug OR name) to a WP_Term, or null. */
function ricoman_region_term( $region ) {
	$region = trim( (string) $region );
	if ( '' === $region || ! taxonomy_exists( 'region' ) ) {
		return null;
	}
	$term = get_term_by( 'slug', sanitize_title( $region ), 'region' );
	if ( ! $term ) {
		$term = get_term_by( 'name', $region, 'region' );
	}
	return $term instanceof WP_Term ? $term : null;
}

/**
 * The staff member(s) who cover a region. Falls back to a configured default
 * contact (option `ricoman_region_default_agent`, filter overridable), and
 * finally to the first published team member — so a page always shows someone.
 */
function ricoman_region_agent_ids( $term ) {
	$ids = array();
	if ( $term instanceof WP_Term ) {
		$ids = get_posts( array(
			'post_type'      => 'staff',
			'post_status'    => 'publish',
			'numberposts'    => 12,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => 'region', 'terms' => $term->term_id ) ),
			'suppress_filters' => false,
		) );
		$ids = array_map( 'intval', (array) $ids );
	}
	if ( $ids ) {
		return $ids;
	}
	// Fallback 1: an explicit default contact.
	$default = (int) apply_filters( 'ricoman_region_default_agent', (int) get_option( 'ricoman_region_default_agent', 0 ), $term );
	if ( $default && 'staff' === get_post_type( $default ) && 'publish' === get_post_status( $default ) ) {
		return array( $default );
	}
	// Fallback 2: the first published team member, so the page is never empty.
	$first = get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'numberposts' => 1, 'orderby' => 'menu_order title', 'order' => 'ASC', 'fields' => 'ids' ) );
	return $first ? array( (int) $first[0] ) : array();
}

/* ---------------------------------------------------------------------------
 * Shortcodes.
 * ------------------------------------------------------------------------- */

/** Project-led hero for a region. [ricoman_region_hero region="north-west" title="…" hook="…"] */
add_shortcode( 'ricoman_region_hero', function ( $atts ) {
	$atts = shortcode_atts( array( 'region' => '', 'title' => '', 'hook' => '', 'image' => '' ), $atts, 'ricoman_region_hero' );
	$term = ricoman_region_term( $atts['region'] );
	$name = $term ? $term->name : ( $atts['title'] ? $atts['title'] : 'the UK' );

	$img = '';
	// 1) Explicit image attribute (a media URL or an attachment ID).
	$iv = trim( (string) $atts['image'] );
	if ( '' !== $iv ) {
		$img = is_numeric( $iv ) ? (string) wp_get_attachment_image_url( (int) $iv, 'full' ) : $iv;
	}
	// 2) The page's own Featured image — the easiest way for the team to set it.
	if ( ! $img ) {
		$pid = get_the_ID();
		if ( $pid && has_post_thumbnail( $pid ) ) {
			$img = (string) get_the_post_thumbnail_url( $pid, 'full' );
		}
	}
	// 3) A project photo from this region.
	if ( ! $img && $term ) {
		$q = new WP_Query( array(
			'post_type'      => 'project',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
			'tax_query'      => array( array( 'taxonomy' => 'region', 'terms' => $term->term_id ) ),
		) );
		if ( $q->posts && function_exists( 'ricoman_project_img' ) ) {
			$img = ricoman_project_img( (int) $q->posts[0] );
		}
	}
	// 4) Theme default.
	if ( ! $img ) {
		$img = get_theme_file_uri( 'assets/images/office1.webp' );
	}

	$title = $atts['title'] ? $atts['title'] : sprintf( 'Your lighting partner in %s', $name );
	$hook  = $atts['hook'] ? $atts['hook'] : sprintf( 'UK-manufactured LED lighting, specified and supported by your local team in %s. See recent %s projects and talk to the person who covers your area.', $name, strtolower( $name ) );

	return '<div class="rm-sechero" style="background-image:url(' . esc_url( $img ) . ')">'
		. '<span class="rm-sechero-scrim" aria-hidden="true"></span>'
		. '<div class="rm-pp-wrap rm-sechero-in">'
		. '<p class="rm-eyebrow rm-sechero-eyebrow">' . esc_html( $name ) . '</p>'
		. '<h2 class="rm-sechero-title">' . esc_html( $title ) . '</h2>'
		. '<p class="rm-sechero-hook">' . esc_html( $hook ) . '</p>'
		. '<div class="rm-sechero-cta"><a class="btn btn-solid" href="#agent">Talk to your local team</a> '
		. '<a class="btn btn-line" href="#projects">See the projects ↓</a></div>'
		. '</div></div>';
} );

/** Editable region selling copy — region description, else a useful default. [ricoman_region_intro region="…"] */
add_shortcode( 'ricoman_region_intro', function ( $atts ) {
	$atts = shortcode_atts( array( 'region' => '' ), $atts, 'ricoman_region_intro' );
	$term = ricoman_region_term( $atts['region'] );
	$name = $term ? $term->name : 'your region';
	$desc = $term ? trim( (string) term_description( $term ) ) : '';
	if ( '' === $desc ) {
		$desc = '<p>' . esc_html( sprintf( 'Ricoman supplies specifiers, contractors and end users across %s. As a UK manufacturer we design, make and deliver complete LED lighting schemes — photometrically specified, held in UK stock on short lead times, and backed by a 5-year warranty.', $name ) ) . '</p>'
			. '<p>' . esc_html( sprintf( 'Browse our recent %s projects below, or speak to the team who look after your area — they can spec a scheme, arrange samples and support you from design to delivery.', strtolower( $name ) ) ) . '</p>';
	}
	return '<div class="rm-section rm-sectorintro"><div class="rm-pp-wrap rm-sectorintro-in">'
		. '<div class="rm-sector-desc">' . wp_kses_post( $desc ) . '</div>'
		. '</div></div>';
} );

/** Grid of projects tagged with a region. [ricoman_region_projects region="north-west" limit="12" heading="…"] */
add_shortcode( 'ricoman_region_projects', function ( $atts ) {
	$atts = shortcode_atts( array( 'region' => '', 'limit' => 12, 'heading' => '' ), $atts, 'ricoman_region_projects' );
	$term = ricoman_region_term( $atts['region'] );
	if ( ! $term ) {
		// In the editor, help the team; on the front end, stay silent.
		return is_admin() ? '<p><em>Set a valid region slug on this block.</em></p>' : '';
	}
	$q = new WP_Query( array(
		'post_type'      => 'project',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) $atts['limit'] ),
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'tax_query'      => array( array( 'taxonomy' => 'region', 'terms' => $term->term_id ) ),
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$heading = '' !== $atts['heading'] ? $atts['heading'] : sprintf( '%s projects', $term->name );
	$cards   = '';
	foreach ( $q->posts as $p ) {
		$img   = function_exists( 'ricoman_project_img' ) ? ricoman_project_img( $p->ID ) : get_the_post_thumbnail_url( $p->ID, 'large' );
		$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$cards .= '<a class="rm-projcard" href="' . esc_url( get_permalink( $p ) ) . '"' . $style . '><span class="rm-projcard-ov">'
			. '<span class="rm-eyebrow">' . esc_html__( 'Project', 'ricoman' ) . '</span>'
			. '<span class="rm-projcard-t">' . esc_html( get_the_title( $p ) ) . '</span>'
			. '</span></a>';
	}
	return '<div class="rm-section" id="projects"><div class="rm-pp-wrap">'
		. '<h2 class="rm-shead">' . esc_html( $heading ) . '</h2>'
		. '<div class="rm-projgrid rm-prodgrid">' . $cards . '</div></div></div>';
} );

/** Local agent card(s) for a region, with a default-contact fallback. [ricoman_region_agents region="…" heading="…"] */
add_shortcode( 'ricoman_region_agents', function ( $atts ) {
	$atts = shortcode_atts( array( 'region' => '', 'heading' => '', 'fields' => 'photo,name,title,email,phone' ), $atts, 'ricoman_region_agents' );
	$term = ricoman_region_term( $atts['region'] );
	$ids  = ricoman_region_agent_ids( $term );
	if ( ! $ids || ! function_exists( 'ricoman_staff_card' ) ) {
		return '';
	}
	$name    = $term ? $term->name : '';
	$heading = '' !== $atts['heading'] ? $atts['heading'] : ( $name ? sprintf( 'Your %s team', $name ) : 'Talk to our team' );
	$cards   = '';
	foreach ( $ids as $sid ) {
		$cards .= ricoman_staff_card( (int) $sid, $atts['fields'] );
	}
	if ( '' === $cards ) {
		return '';
	}
	return '<div class="rm-section rm-staff-credit" id="agent"><div class="rm-pp-wrap">'
		. '<h2 class="rm-shead">' . esc_html( $heading ) . '</h2>'
		. '<div class="rm-team-grid">' . $cards . '</div></div></div>';
} );

/* ---------------------------------------------------------------------------
 * Insertable landing-page pattern.
 * ------------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	$slug = 'north-west';
	$defaults = ricoman_regions_default();
	if ( $defaults ) {
		$slug = key( $defaults );
	}
	// Each section is wrapped in a full-width group so it breaks out of the page's
	// content column and runs edge-to-edge (the banner + backgrounds match the
	// About page); the inner rm-pp-wrap keeps the text itself nicely constrained.
	$full = function ( $shortcode ) {
		return '<!-- wp:group {"align":"full","layout":{"type":"default"}} --><div class="wp-block-group alignfull">'
			. '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->'
			. '</div><!-- /wp:group -->';
	};
	$content  = $full( '[ricoman_region_hero region="' . esc_attr( $slug ) . '"]' );
	$content .= "\n\n" . $full( '[ricoman_region_intro region="' . esc_attr( $slug ) . '"]' );
	$content .= "\n\n" . $full( '[ricoman_sector_trust]' );
	$content .= "\n\n" . $full( '[ricoman_region_projects region="' . esc_attr( $slug ) . '"]' );
	$content .= "\n\n" . $full( '[ricoman_region_agents region="' . esc_attr( $slug ) . '"]' );
	$content .= "\n\n" . $full( '[ricoman_design_cta]' );

	register_block_pattern( 'ricoman/region-landing', array(
		'title'       => __( 'Region · Landing page', 'ricoman' ),
		'description' => __( 'A regional ad landing page: hero, intro, trust bar, that region’s projects, the local agent, and a CTA. Set the region slug in each shortcode block (e.g. region="north-west").', 'ricoman' ),
		'categories'  => array( 'ricoman-page' ),
		'content'     => $content,
	) );
}, 13 );

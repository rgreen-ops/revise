<?php
/**
 * Custom post types & taxonomies: Products, Projects and Leads.
 *
 * These power the "editable product / project / page templates" and
 * "product data / variant structure" parts of the build. They use block
 * templates from /templates so editors can lay them out visually.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the Product and Project post types and their taxonomies.
 */
function ricoman_register_post_types() {

	// --- Products -------------------------------------------------------
	register_post_type(
		'product',
		array(
			'labels'        => array(
				'name'               => __( 'Products', 'ricoman' ),
				'singular_name'      => __( 'Product', 'ricoman' ),
				'add_new_item'       => __( 'Add New Product', 'ricoman' ),
				'edit_item'          => __( 'Edit Product', 'ricoman' ),
				'new_item'           => __( 'New Product', 'ricoman' ),
				'view_item'          => __( 'View Product', 'ricoman' ),
				'search_items'       => __( 'Search Products', 'ricoman' ),
				'not_found'          => __( 'No products found', 'ricoman' ),
				'all_items'          => __( 'All Products', 'ricoman' ),
				'menu_name'          => __( 'Products', 'ricoman' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-lightbulb',
			'menu_position' => 20,
			'rewrite'       => array( 'slug' => 'products', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'page-attributes', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	// --- Projects (case studies) ---------------------------------------
	register_post_type(
		'project',
		array(
			'labels'        => array(
				'name'               => __( 'Projects', 'ricoman' ),
				'singular_name'      => __( 'Project', 'ricoman' ),
				'add_new_item'       => __( 'Add New Project', 'ricoman' ),
				'edit_item'          => __( 'Edit Project', 'ricoman' ),
				'all_items'          => __( 'All Projects', 'ricoman' ),
				'menu_name'          => __( 'Projects', 'ricoman' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-portfolio',
			'menu_position' => 21,
			'rewrite'       => array( 'slug' => 'projects', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	// --- Leads (private, stores form submissions) ----------------------
	register_post_type(
		'lead',
		array(
			'labels'        => array(
				'name'          => __( 'Leads', 'ricoman' ),
				'singular_name' => __( 'Lead', 'ricoman' ),
				'menu_name'     => __( 'Leads', 'ricoman' ),
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-email-alt',
			'menu_position' => 22,
			'capability_type' => 'post',
			'supports'      => array( 'title' ),
			'show_in_rest'  => false,
		)
	);

	// --- News (migrated articles) ------------------------------------------
	register_post_type(
		'news',
		array(
			'labels'        => array(
				'name'          => __( 'News', 'ricoman' ),
				'singular_name' => __( 'News Article', 'ricoman' ),
				'menu_name'     => __( 'News', 'ricoman' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-megaphone',
			'menu_position' => 24,
			'rewrite'       => array( 'slug' => 'news', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	// --- Variant products (the order-code rows for the Configure table) -----
	// Each is linked to its parent product via the ACF `parent_product` field.
	register_post_type(
		'variant-product',
		array(
			'labels'        => array(
				'name'          => __( 'Variant Products', 'ricoman' ),
				'singular_name' => __( 'Variant Product', 'ricoman' ),
				'menu_name'     => __( 'Variant Products', 'ricoman' ),
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-screenoptions',
			'menu_position' => 23,
			'rewrite'       => false,
			'supports'      => array( 'title', 'custom-fields' ),
			'show_in_rest'  => false,
		)
	);
}
add_action( 'init', 'ricoman_register_post_types' );

/**
 * Register taxonomies for products and projects.
 */
function ricoman_register_taxonomies() {

	// Product category (Downlights, Panels, Track, etc.).
	// NB: the live ricoman.com data model uses these exact (hyphenated) taxonomy
	// names, so the theme registers them verbatim — otherwise migrated products
	// have categories/sectors that nothing can display.
	register_taxonomy(
		'product-cat',
		'product',
		array(
			'labels'            => array(
				'name'          => __( 'Product Categories', 'ricoman' ),
				'singular_name' => __( 'Product Category', 'ricoman' ),
				'menu_name'     => __( 'Categories', 'ricoman' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'product-category', 'with_front' => false ),
		)
	);

	// Application / sector for products (Retail, Office, Hospitality, Healthcare…).
	register_taxonomy(
		'applycation-type',
		'product',
		array(
			'labels'            => array(
				'name'          => __( 'Applications', 'ricoman' ),
				'singular_name' => __( 'Application', 'ricoman' ),
				'menu_name'     => __( 'Applications', 'ricoman' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'application', 'with_front' => false ),
		)
	);

	// Project / sector category (used by projects, and shared onto products).
	register_taxonomy(
		'project-cat',
		array( 'project', 'product' ),
		array(
			'labels'            => array(
				'name'          => __( 'Project Categories', 'ricoman' ),
				'singular_name' => __( 'Project Category', 'ricoman' ),
				'menu_name'     => __( 'Sectors', 'ricoman' ),
			),
			'hierarchical'      => true,
			'public'            => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'rewrite'           => array( 'slug' => 'sector', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'ricoman_register_taxonomies' );

/**
 * Register the variant "axis" taxonomies the old data model used (wattage,
 * colour temperature, colour, beam angle, IP rating, dimming, size, …). The
 * variant order codes carry their specs as terms in these taxonomies. The old
 * plugin registered them; our theme must too, otherwise the migrated terms are
 * invisible (wp_get_post_terms can't read an unregistered taxonomy) and specs
 * like Wattage never appear. Attached to products and variant products.
 */
function ricoman_variant_axis_taxonomies() {
	return array(
		'wattage'            => __( 'Wattage', 'ricoman' ),
		'temperature'        => __( 'Colour Temperature', 'ricoman' ),
		'color'              => __( 'Colour', 'ricoman' ),
		'beam-angle'         => __( 'Beam Angle', 'ricoman' ),
		'iprating'           => __( 'IP Rating', 'ricoman' ),
		'size'               => __( 'Size', 'ricoman' ),
		'dimming'            => __( 'Dimming', 'ricoman' ),
		'lighting-direction' => __( 'Lighting Direction', 'ricoman' ),
		'emergency'          => __( 'Emergency', 'ricoman' ),
		'glare-control'      => __( 'Glare Control', 'ricoman' ),
		'microwave'          => __( 'Microwave', 'ricoman' ),
		'pir'                => __( 'PIR', 'ricoman' ),
		'reflector'          => __( 'Reflector', 'ricoman' ),
		'reflector-finish'   => __( 'Reflector Finish', 'ricoman' ),
		'reflector-colour'   => __( 'Reflector Colour', 'ricoman' ),
		'bezel-finish'       => __( 'Bezel Finish', 'ricoman' ),
		'diffuser-material'  => __( 'Diffuser Material', 'ricoman' ),
		'application-area'   => __( 'Application Area', 'ricoman' ),
		'legend-required'    => __( 'Legend Required', 'ricoman' ),
		'fitting-type'       => __( 'Fitting Type', 'ricoman' ),
		'lamp-type'          => __( 'Lamp Type', 'ricoman' ),
		'module'             => __( 'Module', 'ricoman' ),
		'model'              => __( 'Model', 'ricoman' ),
	);
}

function ricoman_register_variant_axes() {
	foreach ( ricoman_variant_axis_taxonomies() as $slug => $label ) {
		if ( taxonomy_exists( $slug ) ) {
			continue;
		}
		register_taxonomy(
			$slug,
			array( 'variant-product' ), // per-variant specs; keeps the product editor uncluttered.
			array(
				'labels'       => array( 'name' => $label, 'singular_name' => $label, 'menu_name' => $label ),
				'hierarchical' => false,
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false, // managed via the Spec Axes hub.
				'show_in_rest' => true,
				'rewrite'      => false,
				'query_var'    => false,
			)
		);
	}
}
add_action( 'init', 'ricoman_register_variant_axes' );

/**
 * Flush rewrite rules once on theme activation so the new CPT permalinks work.
 * (Switching themes fires this; admins can also just re-save Permalinks.)
 */
function ricoman_flush_rewrites() {
	ricoman_register_post_types();
	ricoman_register_taxonomies();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'ricoman_flush_rewrites' );

/**
 * Self-healing permalinks. Uploading a new theme ZIP (rather than switching
 * themes) doesn't fire after_switch_theme, so the rewrite rules for the
 * product-category / sector taxonomies can be missing — which makes clicking a
 * (sub)category do nothing. If our taxonomy rules aren't present, flush once.
 */
add_action( 'wp_loaded', function () {
	if ( get_transient( 'ricoman_rw_ok' ) ) {
		return;
	}
	$rules = get_option( 'rewrite_rules' );
	$has   = false;
	if ( is_array( $rules ) ) {
		foreach ( array_keys( $rules ) as $k ) {
			if ( false !== strpos( $k, 'product-category' ) ) {
				$has = true;
				break;
			}
		}
	}
	if ( ! $has ) {
		flush_rewrite_rules( false );
	}
	set_transient( 'ricoman_rw_ok', 1, HOUR_IN_SECONDS );
}, 99 );

/**
 * Dynamic grid of real Project posts — every tile links to a live permalink, so
 * the listing always works regardless of what was seeded or imported.
 * Use: [ricoman_projects_grid count="12"]
 */
add_shortcode( 'ricoman_projects_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 12 ), $atts, 'ricoman_projects_grid' );
	$args = array(
		'post_type'      => 'project',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	);
	// Hide the theme's seeded demo case studies once real projects are imported,
	// so the listing shows genuine ricoman.com work — not the placeholder set.
	// Non-destructive: the demo posts stay in the DB, they're just filtered out.
	$exclude = ricoman_demo_project_ids();
	if ( $exclude ) {
		$real = new WP_Query( array(
			'post_type'      => 'project',
			'post_status'    => 'publish',
			'post__not_in'   => $exclude,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		// Only exclude when genuine projects remain (otherwise show the demo set).
		if ( $real->have_posts() ) {
			$args['post__not_in'] = $exclude;
		}
	}
	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		return '<p class="rm-config-note">Projects will appear here once published.</p>';
	}
	$tax = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';

	// Sector dropdown — cleaner than a wall of chips when there are many sectors.
	$opts  = '';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) && $terms ) {
		foreach ( $terms as $t ) {
			$opts .= '<option value="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</option>';
		}
	}

	// Build the cards first so we can show an accurate total in the toolbar.
	$cards = '';
	$total = 0;
	while ( $q->have_posts() ) {
		$q->the_post();
		$pid    = get_the_ID();
		$img    = function_exists( 'ricoman_project_img' ) ? ricoman_project_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
		$sector = ricoman_first_term_name( $pid, array( 'project-cat', 'application' ) );
		$slugs  = wp_get_post_terms( $pid, $tax, array( 'fields' => 'slugs' ) );
		$cats   = ( ! is_wp_error( $slugs ) && $slugs ) ? implode( ' ', $slugs ) : '';
		$loc    = trim( wp_strip_all_tags( (string) get_post_meta( $pid, 'area', true ) ) );
		// Searchable haystack: title + sector + location + product names used.
		$hay    = strtolower( get_the_title() . ' ' . $sector . ' ' . $loc . ' ' . ricoman_project_products_text( $pid ) );
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$cards .= '<a class="rm-projcard" data-cats="' . esc_attr( $cats ) . '" data-search="' . esc_attr( $hay ) . '" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
			. ( $sector ? '<span class="rm-eyebrow">' . esc_html( $sector ) . '</span>' : '' )
			. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span>'
			. ( $loc ? '<span class="rm-projcard-loc">' . esc_html( $loc ) . '</span>' : '' )
			. '</span></a>';
		$total++;
	}
	wp_reset_postdata();

	// Toolbar: live search (title / sector / location / product) + sector filter.
	$tools = '<div class="rm-projtools">'
		. '<div class="rm-projsearch"><svg class="rm-projsearch-ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
		. '<input type="search" class="rm-projq" placeholder="Search projects, sectors or products…" aria-label="Search projects"></div>'
		. ( $opts ? '<div class="rm-projselwrap"><select class="rm-projsel" aria-label="Filter by sector"><option value="">All sectors</option>' . $opts . '</select></div>' : '' )
		. '<span class="rm-projcount" data-total="' . (int) $total . '">' . (int) $total . ' projects</span>'
		. '</div>';

	return $tools . '<div class="rm-projwide"><div class="rm-projgrid">' . $cards . '</div>'
		. '<p class="rm-projempty" hidden>No projects match your search. <button type="button" class="rm-projreset">Clear filters</button></p></div>';
} );

/** Flat text of the product names used on a project (for search). */
function ricoman_project_products_text( $pid ) {
	$txt = '';
	if ( function_exists( 'have_rows' ) && have_rows( 'product_use', $pid ) ) {
		while ( have_rows( 'product_use', $pid ) ) {
			the_row();
			$txt .= ' ' . (string) get_sub_field( 'name' );
		}
	}
	$fam = get_post_meta( $pid, '_ricoman_family', true );
	if ( $fam ) {
		$txt .= ' ' . $fam;
	}
	return trim( $txt );
}

/**
 * Slugs of the demo case studies seeded by the theme installer (demo-setup.php).
 * Kept in one place so the public Projects grid can hide them once real projects
 * are imported. Filterable, so the team can adjust the set without code edits.
 */
function ricoman_demo_project_slugs() {
	return apply_filters( 'ricoman_demo_project_slugs', array(
		'allianz-hq', 'flagship-store', 'acoustic-ceiling', 'breakout-lounge',
		'boutique-hotel', 'betfred-hq', 'kingsgate', 'estrella-canteen',
		'campus-library', 'studio-hq',
	) );
}

/** Resolve the demo project slugs to post IDs (only ones that exist). */
function ricoman_demo_project_ids() {
	$ids = array();
	foreach ( ricoman_demo_project_slugs() as $slug ) {
		$p = get_page_by_path( $slug, OBJECT, 'project' );
		if ( $p ) {
			$ids[] = (int) $p->ID;
		}
	}
	return $ids;
}

/** Dynamic grid of real Product posts (same idea). [ricoman_products_grid] */
add_shortcode( 'ricoman_products_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 12 ), $atts, 'ricoman_products_grid' );
	$q    = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<p class="rm-config-note">Products will appear here once published.</p>';
	}
	$out = '<div class="rm-projgrid rm-prodgrid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$img    = ricoman_product_img( get_the_ID() );
		$cat    = ricoman_first_term_name( get_the_ID(), array( 'product-cat', 'product_cat' ) );
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$out   .= '<a class="rm-projcard" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
			. ( $cat ? '<span class="rm-eyebrow">' . esc_html( $cat ) . '</span>' : '' )
			. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span></span></a>';
	}
	wp_reset_postdata();
	return $out . '</div>';
} );

/** First term name found across a list of taxonomies (for migrated + demo data). */
function ricoman_first_term_name( $pid, $taxes ) {
	foreach ( (array) $taxes as $tax ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$terms = get_the_terms( $pid, $tax );
		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0]->name;
		}
	}
	return '';
}

/** Display image for a product: featured image, else first ACF gallery image. */
function ricoman_product_img( $pid ) {
	$img = get_the_post_thumbnail_url( $pid, 'large' );
	if ( $img ) {
		return $img;
	}
	// Real ricoman.com products keep images in the ACF gallery, not the thumbnail.
	if ( function_exists( 'ricoman_pf_get' ) && function_exists( 'ricoman_pf_imgurl' ) ) {
		$g = ricoman_pf_get( $pid, 'product_gallery_image' );
		if ( is_array( $g ) && ! empty( $g ) ) {
			$u = ricoman_pf_imgurl( reset( $g ) );
			if ( $u ) {
				return $u;
			}
		} elseif ( $g ) {
			$u = ricoman_pf_imgurl( $g );
			if ( $u ) {
				return $u;
			}
		}
	}
	return '';
}

/**
 * The real product catalogue: category quick-nav + faceted filter bar (light
 * output / power sliders + feature tick-boxes) + every product grouped by
 * category. Powers the /products/ archive. [ricoman_catalogue]
 */
add_shortcode( 'ricoman_catalogue', function ( $atts ) {
	$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );

	// No categorised products yet — fall back to a flat grid of everything.
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return do_shortcode( '[ricoman_products_grid count="60"]' );
	}

	// Category quick-nav.
	$nav = '<div class="rm-catnav">';
	foreach ( $terms as $t ) {
		$nav .= '<a class="rm-catnav-item" href="' . esc_url( get_term_link( $t ) ) . '">'
			. esc_html( $t->name ) . ' <span>' . (int) $t->count . '</span></a>';
	}
	$nav .= '</div>';

	// Build the category sections, collecting facet ranges as we go.
	$sections = '';
	$maxlm    = 0;
	$maxw     = 0;
	$allfeat  = array();
	$total    = 0;
	foreach ( $terms as $t ) {
		$q = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => $t->term_id, 'include_children' => false ) ),
		) );
		if ( ! $q->have_posts() ) {
			continue;
		}
		$cnt    = $q->post_count;
		$total += $cnt;
		$cards  = '';
		while ( $q->have_posts() ) {
			$q->the_post();
			$pid   = get_the_ID();
			$mx    = function_exists( 'ricoman_pf_metrics' ) ? ricoman_pf_metrics( $pid ) : array( 'lm' => 0, 'w' => 0, 'feats' => array() );
			$maxlm = max( $maxlm, $mx['lm'] );
			$maxw  = max( $maxw, $mx['w'] );
			$fslug = array();
			foreach ( $mx['feats'] as $f ) {
				$allfeat[ $f ] = true;
				$fslug[]       = sanitize_title( $f );
			}
			$img   = ricoman_product_img( $pid );
			$sub   = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '';
			$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
			$meta  = array();
			if ( $mx['lm'] ) { $meta[] = number_format( $mx['lm'] ) . ' lm'; }
			if ( $mx['w'] ) { $meta[] = $mx['w'] . 'W'; }
			$cards .= '<a class="rm-projcard rm-fcard" href="' . esc_url( get_permalink() ) . '"'
				. ' data-lm="' . (int) $mx['lm'] . '" data-w="' . (int) $mx['w'] . '" data-feat="' . esc_attr( implode( ' ', $fslug ) ) . '"' . $style . '>'
				. '<span class="rm-projcard-ov">'
				. ( $sub ? '<span class="rm-eyebrow">' . esc_html( $sub ) . '</span>' : '' )
				. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span>'
				. ( $meta ? '<span class="rm-fcard-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' )
				. '</span></a>';
		}
		wp_reset_postdata();
		$sections .= '<section class="rm-catsec" data-cat="' . esc_attr( $t->slug ) . '"><div class="rm-catsec-head"><h2 class="rm-shead">' . esc_html( $t->name )
			. ' <span class="rm-catarch-count">' . (int) $cnt . '</span></h2>'
			. '<a class="rm-catsec-all" href="' . esc_url( get_term_link( $t ) ) . '">Filter &amp; order codes →</a></div>'
			. '<div class="rm-projgrid rm-prodgrid rm-fgrid">' . $cards . '</div></section>';
	}

	// Filter bar (rounded up sensible ranges).
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;
	ksort( $allfeat );
	$excluded = function_exists( 'ricoman_pf_excluded_features' ) ? ricoman_pf_excluded_features() : array();
	$ticks    = '';
	foreach ( array_keys( $allfeat ) as $f ) {
		// Skip junk labels (numbers / single chars) and the excluded set.
		if ( ! preg_match( '/[a-z]{2,}/i', (string) $f ) || in_array( $f, $excluded, true ) ) {
			continue;
		}
		$ticks .= '<label class="rm-ftick"><input type="checkbox" value="' . esc_attr( sanitize_title( $f ) ) . '"> ' . esc_html( $f ) . '</label>';
	}
	// Dual-range (min + max) sliders for light output and power.
	$lmS = $maxlm ? '<div class="rm-frange rm-dual"><label>Light output <b class="rm-lm-lo">0</b> – <b class="rm-lm-hi">' . $maxlm . '</b> lm</label>'
		. '<div class="rm-dual-track">'
		. '<input type="range" class="rm-lm-min" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wS  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> – <b class="rm-w-hi">' . $maxw . '</b> W</label>'
		. '<div class="rm-dual-track">'
		. '<input type="range" class="rm-w-min" min="0" max="' . $maxw . '" step="1" value="0">'
		. '<input type="range" class="rm-w-max" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div></div>' : '';
	$filter = ( $lmS || $wS || $ticks )
		? '<div class="rm-catfilter"><div class="rm-catfilter-ranges">' . $lmS . $wS . '</div>'
			. ( $ticks ? '<div class="rm-catfilter-ticks"><span class="rm-facets-sub">Features</span>' . $ticks . '</div>' : '' )
			. '<button type="button" class="rm-fclear">Clear</button></div>'
		: '';
	// Collapsible on mobile (open by default on desktop via CSS), so the product
	// grid is reachable without scrolling past the whole filter panel.
	$bar = $filter
		? '<details class="rm-catfilter-d"><summary class="rm-catfilter-sum"><span>Filter products</span><span class="rm-catfilter-caret" aria-hidden="true"></span></summary>' . $filter . '</details>'
		: '';

	$out  = '<div class="rm-pp-wrap rm-catwide">' . $nav . $bar
		. '<p class="rm-fcount"><b>' . (int) $total . '</b> products</p>' . $sections
		. '<p class="rm-fnone" hidden>No products match those filters. <button type="button" class="rm-fclear">Clear filters</button></p></div>';

	// Filtering is wired up by the enqueued product-gallery.js (rmCatFilterInit),
	// keyed off .rm-catwide — reliable regardless of where the markup lands.
	return $out;
} );


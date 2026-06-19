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
 * Dynamic grid of real Project posts — every tile links to a live permalink, so
 * the listing always works regardless of what was seeded or imported.
 * Use: [ricoman_projects_grid count="12"]
 */
add_shortcode( 'ricoman_projects_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 12 ), $atts, 'ricoman_projects_grid' );
	$q    = new WP_Query( array(
		'post_type'      => 'project',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<p class="rm-config-note">Projects will appear here once published.</p>';
	}
	$out = '<div class="rm-projgrid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$img    = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		$sector = ricoman_first_term_name( get_the_ID(), array( 'project-cat', 'application' ) );
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$out   .= '<a class="rm-projcard" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
			. ( $sector ? '<span class="rm-eyebrow">' . esc_html( $sector ) . '</span>' : '' )
			. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span></span></a>';
	}
	wp_reset_postdata();
	return $out . '</div>';
} );

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
 * The real product catalogue: category tiles + a grid of products per category,
 * built from the live data model (product-cat). Powers the /products/ archive so
 * every migrated product is reachable. [ricoman_catalogue per_cat="8"]
 */
add_shortcode( 'ricoman_catalogue', function ( $atts ) {
	$atts  = shortcode_atts( array( 'per_cat' => 8 ), $atts, 'ricoman_catalogue' );
	$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );

	// No categorised products yet — fall back to a flat grid of everything.
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return do_shortcode( '[ricoman_products_grid count="60"]' );
	}

	// Category quick-nav.
	$out = '<div class="rm-catnav">';
	foreach ( $terms as $t ) {
		$out .= '<a class="rm-catnav-item" href="' . esc_url( get_term_link( $t ) ) . '">'
			. esc_html( $t->name ) . ' <span>' . (int) $t->count . '</span></a>';
	}
	$out .= '</div>';

	// Every product in each category — no truncation. include_children=false so a
	// product only appears under its own (sub)category, never duplicated under a parent.
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
		$shown = $q->post_count;
		$out  .= '<section class="rm-catsec"><div class="rm-catsec-head"><h2 class="rm-shead">' . esc_html( $t->name ) . ' <span class="rm-catarch-count">' . (int) $shown . '</span></h2>'
			. '<a class="rm-catsec-all" href="' . esc_url( get_term_link( $t ) ) . '">Filter &amp; order codes →</a></div>'
			. '<div class="rm-projgrid rm-prodgrid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$img   = ricoman_product_img( get_the_ID() );
			$sub   = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( get_the_ID(), 'product_subname' ) : '';
			$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
			$out  .= '<a class="rm-projcard" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
				. ( $sub ? '<span class="rm-eyebrow">' . esc_html( $sub ) . '</span>' : '' )
				. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span></span></a>';
		}
		$out .= '</div></section>';
		wp_reset_postdata();
	}
	return $out;
} );

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
	register_taxonomy(
		'product_cat',
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
			'rewrite'           => array( 'slug' => 'product-category' ),
		)
	);

	// Application / sector (Retail, Office, Hospitality, Healthcare…).
	register_taxonomy(
		'application',
		array( 'product', 'project' ),
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
			'rewrite'           => array( 'slug' => 'application' ),
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
		$terms  = get_the_terms( get_the_ID(), 'application' );
		$sector = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
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
		$img    = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		$terms  = get_the_terms( get_the_ID(), 'product_cat' );
		$cat    = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$out   .= '<a class="rm-projcard" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
			. ( $cat ? '<span class="rm-eyebrow">' . esc_html( $cat ) . '</span>' : '' )
			. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span></span></a>';
	}
	wp_reset_postdata();
	return $out . '</div>';
} );

<?php
/**
 * One-time front-of-site scaffolding.
 *
 * On first run after the theme is active, this creates the core pages the
 * navigation and homepage link to (Products, Projects, Lighting Design,
 * Manufacturing, About, My Project), fills each with its matching design
 * pattern, assigns the full-bleed template, and switches the site to pretty
 * permalinks so the links resolve. It runs exactly once (guarded by an option)
 * and never overwrites a page that already exists.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'ricoman_scaffold_pages', 20 );

function ricoman_scaffold_pages() {
	if ( get_option( 'ricoman_pages_scaffolded' ) ) {
		return;
	}

	// Pretty permalinks, so /products/, /about/ etc. work.
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// slug => [ Title, pattern slug ].
	$pages = array(
		'products'        => array( 'Products', 'ricoman/products-archive' ),
		'projects'        => array( 'Projects', 'ricoman/projects-archive' ),
		'lighting-design' => array( 'Lighting Design', 'ricoman/page-lighting' ),
		'manufacturing'   => array( 'Manufacturing', 'ricoman/page-manufacturing' ),
		'about'           => array( 'About', 'ricoman/page-about' ),
		'my-project'      => array( 'My Project', 'ricoman/page-my-project' ),
	);

	foreach ( $pages as $slug => $info ) {
		if ( get_page_by_path( $slug ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $info[0],
				'post_name'    => $slug,
				'post_content' => '<!-- wp:pattern {"slug":"' . $info[1] . '"} /-->',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_wp_page_template', 'page-plain' );
		}
	}

	// Flush rewrite rules now the pages + permalink structure exist.
	flush_rewrite_rules( false );

	update_option( 'ricoman_pages_scaffolded', 1 );
}

<?php
/**
 * Turnkey site scaffolding.
 *
 * On first run the theme builds a complete, cross-linked demo site:
 *   - the homepage (editable native blocks) set as the static front page,
 *   - the core pages (Lighting Design, Manufacturing, About, My Project),
 *   - product categories + sample products (Flow+, Estrella, Neptune),
 *   - sample projects, with products <-> projects linked both ways,
 *   - pretty permalinks, flushed.
 * /products/ and /projects/ are the post-type archives (no pages created for
 * them). Runs once, never overwrites anything that already exists.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_loaded', 'ricoman_scaffold_site', 20 );

/** Resolve a registered pattern's markup (so pages contain real, editable blocks). */
function ricoman_pattern_content( $slug ) {
	$reg = WP_Block_Patterns_Registry::get_instance();
	if ( $reg->is_registered( $slug ) ) {
		$p = $reg->get_registered( $slug );
		if ( ! empty( $p['content'] ) ) {
			return $p['content'];
		}
	}
	return '<!-- wp:pattern {"slug":"' . esc_attr( $slug ) . '"} /-->';
}

/** Create a page, or refresh its content if it already exists. Returns the ID. */
function ricoman_upsert_page( $title, $slug, $pattern_slug, $template = '' ) {
	$content  = ricoman_pattern_content( $pattern_slug );
	$existing = get_page_by_path( $slug );
	if ( $existing && 'page' === $existing->post_type ) {
		wp_update_post( array( 'ID' => $existing->ID, 'post_content' => $content ) );
		if ( $template ) {
			update_post_meta( $existing->ID, '_wp_page_template', $template );
		}
		return (int) $existing->ID;
	}
	return ricoman_make_post( 'page', $title, $slug, $content, $template );
}

/** Create a post once (idempotent by slug + type). Returns the ID or 0. */
function ricoman_make_post( $type, $title, $slug, $content, $template = '' ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		if ( $template ) {
			update_post_meta( $id, '_wp_page_template', $template );
		}
		return (int) $id;
	}
	return 0;
}

function ricoman_scaffold_site() {
	if ( get_option( 'ricoman_scaffold_v4' ) ) {
		return;
	}

	$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };

	// Pretty permalinks so every URL resolves.
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}

	// Remove earlier auto-created Products/Projects PAGES — they clash with the
	// product/project post-type archives that own /products/ and /projects/.
	foreach ( array( 'products', 'projects' ) as $old ) {
		$page = get_page_by_path( $old );
		if ( $page && 'page' === $page->post_type ) {
			wp_trash_post( $page->ID );
		}
	}

	// ---- Pages (create, or refresh to the latest native-block design) ----
	$home_id = ricoman_upsert_page( 'Home', 'home', 'ricoman/home' );
	if ( $home_id ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home_id );
	}
	$pages = array(
		'lighting-design' => array( 'Lighting Design', 'ricoman/page-lighting' ),
		'manufacturing'   => array( 'Manufacturing', 'ricoman/page-manufacturing' ),
		'about'           => array( 'About', 'ricoman/page-about' ),
		'my-project'      => array( 'My Project', 'ricoman/page-my-project' ),
	);
	foreach ( $pages as $slug => $info ) {
		ricoman_upsert_page( $info[0], $slug, $info[1], 'page-plain' );
	}

	// ---- Product categories ----
	foreach ( array( 'Linear Lighting', 'Pendants', 'Downlights' ) as $cat ) {
		if ( ! term_exists( $cat, 'product_cat' ) ) {
			wp_insert_term( $cat, 'product_cat' );
		}
	}

	// ---- Products (cross-linked to projects) ----
	$products = array(
		'flow-plus' => array(
			'Flow+',
			'Linear Lighting',
			'light-a.jpg',
			'A flexible linear system that bends to any architectural line — continuous, dot-free and made to order in Manchester to your exact geometry.',
			'allianz-hq',
			'Allianz HQ fit-out',
		),
		'estrella'  => array(
			'Estrella',
			'Pendants',
			'estrella-lounge.webp',
			'A configurable architectural pendant — choose form, finish and colour temperature, built to spec with CRI 90+ light quality.',
			'flagship-store',
			'our flagship retail scheme',
		),
		'neptune'   => array(
			'Neptune',
			'Downlights',
			'ceiling.jpg',
			'A fire-rated, IP65 downlight with switchable CCT and a clean trimless aperture — built for offices, healthcare and education.',
			'allianz-hq',
			'Allianz HQ fit-out',
		),
	);
	foreach ( $products as $slug => $p ) {
		$content  = '<!-- wp:image --><figure class="wp-block-image size-large"><img src="' . $img( $p[2] ) . '" alt="' . esc_attr( $p[0] ) . '"/></figure><!-- /wp:image -->';
		$content .= '<!-- wp:paragraph --><p>' . esc_html( $p[3] ) . '</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Seen in</h3><!-- /wp:heading -->';
		$content .= '<!-- wp:paragraph --><p>See ' . esc_html( $p[0] ) . ' in <a href="/projects/' . $p[4] . '/">' . esc_html( $p[5] ) . '</a>.</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/my-project/">Add to My Project</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/products/">All products</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		$pid      = ricoman_make_post( 'product', $p[0], $slug, $content );
		if ( $pid ) {
			wp_set_object_terms( $pid, $p[1], 'product_cat' );
		}
	}

	// ---- Projects (cross-linked back to products) ----
	$projects = array(
		'allianz-hq'     => array(
			'Allianz HQ Fit-out',
			'office1.jpg',
			'A commercial workplace fit-out lit with continuous linear runs and trimless downlights for a clean, low-glare ceiling.',
			array( 'flow-plus' => 'Flow+', 'neptune' => 'Neptune' ),
		),
		'flagship-store' => array(
			'Flagship Retail Store',
			'retail.jpg',
			'A retail flagship using accent and decorative pendants to bring warmth and focus to the merchandising.',
			array( 'estrella' => 'Estrella' ),
		),
	);
	foreach ( $projects as $slug => $pr ) {
		$content  = '<!-- wp:image --><figure class="wp-block-image size-large"><img src="' . $img( $pr[1] ) . '" alt="' . esc_attr( $pr[0] ) . '"/></figure><!-- /wp:image -->';
		$content .= '<!-- wp:paragraph --><p>' . esc_html( $pr[2] ) . '</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Products used</h3><!-- /wp:heading -->';
		$links    = array();
		foreach ( $pr[3] as $ps => $pn ) {
			$links[] = '<a href="/products/' . $ps . '/">' . esc_html( $pn ) . '</a>';
		}
		$content .= '<!-- wp:paragraph --><p>' . implode( ' · ', $links ) . '</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/projects/">All projects</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
		ricoman_make_post( 'project', $pr[0], $slug, $content );
	}

	flush_rewrite_rules( true );
	update_option( 'ricoman_scaffold_v4', 1 );
}

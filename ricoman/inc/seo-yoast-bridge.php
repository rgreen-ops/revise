<?php
/**
 * Yoast bridge — feed the theme's keyword-optimised SEO into Yoast.
 *
 * With Yoast active, Yoast owns the <title>, meta description and social tags.
 * Migrated products/pages often have stale or empty Yoast fields, so the site
 * wasn't using the theme's optimised copy. This file:
 *
 *   • Generates a keyword-targeted title + description per content type
 *     (product → name · category · brand; project, category, archives…).
 *   • Feeds those to Yoast at runtime, but ONLY when Yoast has no manual value
 *     for that post (manual Yoast edits always win) — so the whole site gets
 *     good titles/descriptions/social tags without per-post editing.
 *   • Provides a one-click bulk apply that writes the computed values into
 *     Yoast's own meta (so the Yoast snippet/admin reflects them too).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Brand suffix used in generated titles. */
function ricoman_seo_brand() {
	return (string) apply_filters( 'ricoman_seo_brand', 'Ricoman' );
}

/** Tidy + collapse whitespace in a title/description. */
function ricoman_seo_tidy( $s ) {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $s ) ) );
}

/**
 * Keyword-optimised SEO title for a post (defaults to the current singular).
 * Respects a hand-set theme title (_ricoman_seo_title) first.
 */
function ricoman_seo_post_title( $post = null ) {
	$post = $post ? get_post( $post ) : get_post( get_queried_object_id() );
	if ( ! $post ) {
		return '';
	}
	$ov = get_post_meta( $post->ID, '_ricoman_seo_title', true );
	if ( $ov ) {
		return ricoman_seo_tidy( $ov );
	}
	$brand = ricoman_seo_brand();
	$title = get_the_title( $post );
	switch ( $post->post_type ) {
		case 'product':
			$terms = get_the_terms( $post->ID, 'product-cat' );
			$cat   = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0]->name : '';
			// Make sure "lighting" appears for the search term, without duplicating it.
			$mid = $cat ? $cat : 'Commercial LED Lighting';
			if ( false === stripos( $title . ' ' . $mid, 'light' ) ) {
				$mid .= ' Lighting';
			}
			return ricoman_seo_tidy( $title . ' | ' . $mid . ' | ' . $brand );
		case 'project':
			return ricoman_seo_tidy( $title . ' | Lighting Project | ' . $brand );
		case 'news':
			return ricoman_seo_tidy( $title . ' | ' . $brand . ' Lighting' );
		default:
			return ricoman_seo_tidy( $title . ' | ' . $brand );
	}
}

/** Keyword-optimised title for the current request (incl. taxonomies/archives). */
function ricoman_seo_request_title() {
	$brand = ricoman_seo_brand();
	if ( is_front_page() ) {
		return ''; // let Yoast's homepage setting stand.
	}
	if ( is_singular() ) {
		return ricoman_seo_post_title();
	}
	if ( is_tax( 'product-cat' ) || is_category() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && ! empty( $term->name ) ) {
			$name = $term->name;
			$suf  = ( false === stripos( $name, 'light' ) ) ? ' Lighting' : '';
			return ricoman_seo_tidy( $name . $suf . ' | ' . $brand );
		}
	}
	if ( is_post_type_archive( 'product' ) ) {
		return 'Commercial LED Lighting | UK Made | ' . $brand;
	}
	if ( is_post_type_archive( 'project' ) ) {
		return 'Lighting Projects & Case Studies | ' . $brand;
	}
	return '';
}

/** Computed description for a post (for bulk apply; runtime uses ricoman_seo_description). */
function ricoman_seo_post_desc( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$ov = get_post_meta( $post->ID, '_ricoman_seo_desc', true );
	if ( $ov ) {
		return ricoman_seo_tidy( $ov );
	}
	$ex = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	$src = $ex ? $ex : strip_shortcodes( wp_strip_all_tags( (string) $post->post_content ) );
	$src = ricoman_seo_tidy( $src );
	if ( '' === $src ) {
		$src = get_the_title( $post ) . ' — ' . get_bloginfo( 'description' );
	}
	return wp_trim_words( $src, 30, '…' );
}

/* ----------------------------------------------------- runtime Yoast filters */

/** Does this request have a manual Yoast title we must not override? */
function ricoman_yoast_has_manual_title() {
	if ( ! is_singular() ) {
		return false;
	}
	return '' !== trim( (string) get_post_meta( get_queried_object_id(), '_yoast_wpseo_title', true ) );
}

/** Fill Yoast's title with our optimised one when there's no manual override. */
add_filter( 'wpseo_title', function ( $title ) {
	if ( ricoman_yoast_has_manual_title() ) {
		return $title;
	}
	$c = ricoman_seo_request_title();
	return $c ? $c : $title;
}, 15 );

/** Same optimised title for social cards (fill only when empty). */
$rm_fill_social_title = function ( $title ) {
	if ( '' !== trim( (string) $title ) ) {
		return $title;
	}
	$c = ricoman_seo_request_title();
	return $c ? $c : $title;
};
add_filter( 'wpseo_opengraph_title', $rm_fill_social_title );
add_filter( 'wpseo_twitter_title', $rm_fill_social_title );

/** Fill the OG/Twitter image from the theme's resolver when Yoast has none. */
$rm_fill_social_image = function ( $img ) {
	if ( '' !== trim( (string) $img ) ) {
		return $img;
	}
	$i = function_exists( 'ricoman_seo_image' ) ? ricoman_seo_image() : '';
	return $i ? $i : $img;
};
add_filter( 'wpseo_opengraph_image', $rm_fill_social_image );
add_filter( 'wpseo_twitter_image', $rm_fill_social_image );

/** Native (non-Yoast) title: also use the computed title when no meta override. */
add_filter( 'pre_get_document_title', function ( $title ) {
	if ( ricoman_seo_plugin_active() ) {
		return $title;
	}
	if ( is_singular() && '' !== trim( (string) get_post_meta( get_queried_object_id(), '_ricoman_seo_title', true ) ) ) {
		return $title; // already handled in inc/seo.php.
	}
	$c = ricoman_seo_request_title();
	return $c ? $c : $title;
}, 8 );

/* ------------------------------------------------- bulk apply to Yoast meta */

/**
 * Write the computed title/description into Yoast's own meta for every product,
 * project, page, news post and product category — filling EMPTIES only (never
 * clobbering hand-edited Yoast fields). Returns the number of fields written.
 */
function ricoman_seo_apply_to_yoast() {
	$written = 0;
	$types   = array( 'product', 'project', 'news', 'page' );
	foreach ( $types as $pt ) {
		if ( ! post_type_exists( $pt ) ) {
			continue;
		}
		$ids = get_posts( array(
			'post_type'      => $pt,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		) );
		foreach ( $ids as $id ) {
			if ( '' === trim( (string) get_post_meta( $id, '_yoast_wpseo_title', true ) ) ) {
				$t = ricoman_seo_post_title( $id );
				if ( $t ) {
					update_post_meta( $id, '_yoast_wpseo_title', $t );
					$written++;
				}
			}
			if ( '' === trim( (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true ) ) ) {
				$d = ricoman_seo_post_desc( $id );
				if ( $d ) {
					update_post_meta( $id, '_yoast_wpseo_metadesc', $d );
					$written++;
				}
			}
		}
	}
	// Product categories → Yoast term meta (stored in the wpseo_taxonomy_meta option).
	if ( taxonomy_exists( 'product-cat' ) ) {
		$tax  = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! is_array( $tax ) ) {
			$tax = array();
		}
		$terms = get_terms( array( 'taxonomy' => 'product-cat', 'hide_empty' => false ) );
		if ( ! is_wp_error( $terms ) ) {
			$brand = ricoman_seo_brand();
			foreach ( $terms as $term ) {
				$cur = isset( $tax['product-cat'][ $term->term_id ] ) ? $tax['product-cat'][ $term->term_id ] : array();
				if ( empty( $cur['wpseo_title'] ) ) {
					$suf = ( false === stripos( $term->name, 'light' ) ) ? ' Lighting' : '';
					$cur['wpseo_title'] = ricoman_seo_tidy( $term->name . $suf . ' | ' . $brand );
					$written++;
				}
				if ( empty( $cur['wpseo_desc'] ) ) {
					$intro = function_exists( 'ricoman_cat_seo' ) ? ricoman_cat_seo( $term->term_id, 'intro' ) : '';
					$d = $intro ? $intro : ( $term->description ? $term->description : $term->name . ' lighting from ' . $brand . ' — browse the range.' );
					$cur['wpseo_desc'] = wp_trim_words( ricoman_seo_tidy( $d ), 30, '…' );
					$written++;
				}
				$tax['product-cat'][ $term->term_id ] = $cur;
			}
			update_option( 'wpseo_taxonomy_meta', $tax );
		}
	}
	return $written;
}

/** Hook the bulk apply onto the Page SEO screen (admin-post action). */
add_action( 'admin_post_ricoman_seo_apply_yoast', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ricoman_seo_apply_yoast' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$n = ricoman_seo_apply_to_yoast();
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-page-seo', 'rm_yoast' => (int) $n ), admin_url( 'admin.php' ) ) );
	exit;
} );

/** Notice + button on the Page SEO screen. */
add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'ricoman_page_ricoman-page-seo' !== $screen->id ) {
		return;
	}
	if ( isset( $_GET['rm_yoast'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Optimised SEO written into Yoast — %d fields filled (empties only).', 'ricoman' ), (int) $_GET['rm_yoast'] ) ) . '</p></div>';
	}
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_seo_apply_yoast' ), 'ricoman_seo_apply_yoast' );
	echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Yoast bridge:', 'ricoman' ) . '</strong> '
		. esc_html__( 'optimised titles & descriptions are served to Yoast automatically (empties only). To also fill Yoast’s own fields so they show in the Yoast editor/snippet, click:', 'ricoman' )
		. ' <a class="button button-primary" href="' . esc_url( $url ) . '">' . esc_html__( 'Apply optimised SEO to Yoast', 'ricoman' ) . '</a></p></div>';
} );

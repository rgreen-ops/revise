<?php
/**
 * Product category archive with live faceting.
 *
 * Renders the products in the current product-cat term as a full-width grid,
 * with client-side filters: a light-output (lumens) slider, a power (watts)
 * slider and feature tick-boxes (Casambi, RGB, Customisable, Emergency…).
 * Metrics + feature flags are derived from each product's ACF key_features /
 * specification text, so it works on the migrated catalogue with no extra data.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Feature keywords -> display label. First match wins; labels de-duplicate. */
function ricoman_pf_feature_map() {
	return array(
		'casambi'      => 'Casambi',
		'rgbw'         => 'RGB',
		'rgb'          => 'RGB',
		'tunable'      => 'Tunable White',
		'cct'          => 'CCT Switchable',
		'switchable'   => 'Switchable',
		'dali'         => 'DALI',
		'dimmable'     => 'Dimmable',
		'emergency'    => 'Emergency',
		'sensor'       => 'Sensor',
		'microwave'    => 'Sensor',
		'ip65'         => 'IP65',
		'ip66'         => 'IP66',
		'fire'         => 'Fire-rated',
		'customis'     => 'Customisable',
		'bespoke'      => 'Customisable',
		'acoustic'     => 'Acoustic',
	);
}

/**
 * Feature labels to hide from the catalogue / category filters (kept in one
 * filterable place). They may still appear elsewhere (e.g. product spec lists);
 * this only removes them as filter tick-boxes.
 */
function ricoman_pf_excluded_features() {
	return apply_filters( 'ricoman_pf_excluded_features', array(
		'Acoustic', 'CCT Switchable', 'DALI', 'Dimmable', 'IP66', 'Switchable',
	) );
}

/**
 * Light output (lumens) + power (watts) + feature flags for a product.
 *
 * The real ricoman.com catalogue keeps lumens/wattage on the per-variant
 * `variant-product` posts (lumens as meta, wattage as a taxonomy), not on the
 * parent — so we aggregate the variants (taking the maximum, since a family
 * spans a range) and fall back to parsing the parent's ACF text. Results are
 * cached per product because the catalogue calls this for every product.
 */
function ricoman_pf_metrics( $pid ) {
	static $memo = array();
	if ( isset( $memo[ $pid ] ) ) {
		return $memo[ $pid ];
	}

	// Parent ACF text metrics — always fast, computed inline on the request.
	$lm    = 0;
	$w     = 0;
	$feats = array();
	$text  = '';
	foreach ( array( 'key_features', 'specification', 'product_sort_description', 'product_subname' ) as $f ) {
		$v = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, $f ) : get_post_meta( $pid, $f, true );
		if ( is_array( $v ) ) {
			$v = wp_json_encode( $v );
		}
		$text .= ' ' . (string) $v;
	}
	$lc = strtolower( wp_strip_all_tags( $text ) );
	if ( preg_match_all( '/([0-9][0-9,\.]*)\s*(?:lm|lumens)\b/i', $lc, $m ) ) {
		foreach ( $m[1] as $n ) {
			$lm = max( $lm, (int) str_replace( array( ',', '.' ), '', $n ) );
		}
	}
	if ( preg_match_all( '/([0-9]+(?:\.[0-9]+)?)\s*w\b/i', $lc, $m ) ) {
		foreach ( $m[1] as $n ) {
			$w = max( $w, (int) ceil( (float) $n ) );
		}
	}
	foreach ( ricoman_pf_feature_map() as $kw => $label ) {
		if ( false !== strpos( $lc, $kw ) ) {
			$feats[ $label ] = true;
		}
	}

	// Merge variant-derived lumens/wattage/features. These are pre-computed by a
	// background job (never on the page request — aggregating thousands of
	// variants live would time the catalogue out). If not built yet, schedule it.
	$pre = get_post_meta( $pid, '_rm_pfm', true );
	if ( is_array( $pre ) ) {
		$lm = max( $lm, (int) ( $pre['lm'] ?? 0 ) );
		$w  = max( $w, (int) ( $pre['w'] ?? 0 ) );
		foreach ( (array) ( $pre['feats'] ?? array() ) as $f ) {
			$feats[ $f ] = true;
		}
	} else {
		// Metrics not built yet. Do NOT schedule a per-product cron event here:
		// inside the catalogue loop that rewrites the whole cron-array option once
		// per product (~500×), an O(n^2) storm that made /products/ take ~40s on
		// every request. Schedule ONE batched build per request instead.
		static $sched = false;
		if ( ! $sched ) {
			$sched = true;
			if ( ! wp_next_scheduled( 'ricoman_pf_build_all' ) ) {
				wp_schedule_single_event( time() + 5, 'ricoman_pf_build_all' );
			}
		}
	}

	$res = array( 'lm' => $lm, 'w' => $w, 'feats' => array_keys( $feats ) );
	return $memo[ $pid ] = $res;
}

/** Compute + store a product's variant metrics on its parent. */
function ricoman_pf_rebuild( $pid ) {
	$vm = ricoman_pf_variant_metrics( (int) $pid );
	update_post_meta( (int) $pid, '_rm_pfm', $vm );
	return $vm;
}

/** Background job: build one product's variant metrics (single product). */
add_action( 'ricoman_pf_build', function ( $pid ) {
	ricoman_pf_rebuild( (int) $pid );
} );

/**
 * Background job: build variant metrics for every product still missing them,
 * in capped batches so a single cron run never blows the time limit. Reschedules
 * itself until the whole catalogue is precomputed, then stops. Replaces the old
 * one-event-per-product scheduling that overwhelmed the cron array.
 */
add_action( 'ricoman_pf_build_all', 'ricoman_pf_build_all' );
function ricoman_pf_build_all() {
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 80,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'cache_results'  => false,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'meta_query'     => array(
			array( 'key' => '_rm_pfm', 'compare' => 'NOT EXISTS' ),
		),
	) );
	foreach ( $ids as $pid ) {
		ricoman_pf_rebuild( (int) $pid );
	}
	// More still missing? Come back for the next batch shortly.
	if ( count( $ids ) >= 80 && ! wp_next_scheduled( 'ricoman_pf_build_all' ) ) {
		wp_schedule_single_event( time() + 30, 'ricoman_pf_build_all' );
	}
	// New products since this option means the catalogue's facet ranges changed.
	if ( $ids ) {
		update_option( 'rm_products_ver', (string) time(), false );
	}
}

/**
 * Aggregate lumens / wattage / feature flags from a product's variants.
 * Lumens live in variant meta; wattage in the `wattage` taxonomy; feature
 * flags can be read from variant taxonomies (dimming, emergency, pir…).
 * Returns the family maximums so the slider ranges cover every variant.
 */
function ricoman_pf_variant_metrics( $pid ) {
	global $wpdb;
	$lm    = 0;
	$w     = 0;
	$feats = array();
	if ( ! post_type_exists( 'variant-product' ) ) {
		return array( 'lm' => 0, 'w' => 0, 'feats' => $feats );
	}

	// Variant IDs for this product (one indexed meta query).
	$ids = get_posts( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'cache_results'  => false,
		'meta_query'     => array( array( 'key' => 'parent_product', 'value' => (string) $pid ) ),
	) );
	if ( ! $ids ) {
		return array( 'lm' => 0, 'w' => 0, 'feats' => $feats );
	}

	// Lumens: one MAX query restricted by post_id (indexed) — no table scan.
	$ids_in   = implode( ',', array_map( 'absint', $ids ) );
	$lm_keys  = array( 'lumens', 'lumen', 'lumen_output', 'lumens_output', 'total_lumens', 'output_lumens', 'lumen_value' );
	$keys_in  = implode( ',', array_fill( 0, count( $lm_keys ), '%s' ) );
	$lm = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT MAX(CAST(REPLACE(REPLACE(meta_value, ',', ''), ' ', '') AS UNSIGNED))
		 FROM {$wpdb->postmeta}
		 WHERE post_id IN ($ids_in) AND meta_key IN ($keys_in)",
		$lm_keys
	) );

	// Wattage + lumen taxonomies + feature flags — one query per taxonomy across
	// every variant (wp_get_object_terms issues a single IN(...) query).
	foreach ( array( 'lumen', 'lumens' ) as $ltx ) {
		if ( ! taxonomy_exists( $ltx ) ) {
			continue;
		}
		$names = wp_get_object_terms( $ids, $ltx, array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $names ) && $names && preg_match_all( '/([0-9][0-9,\.]+|[0-9]+)/', implode( ' ', $names ), $m ) ) {
			foreach ( $m[1] as $n ) {
				$lm = max( $lm, (int) str_replace( array( ',', '.' ), '', $n ) );
			}
		}
	}
	if ( taxonomy_exists( 'wattage' ) ) {
		$wt = wp_get_object_terms( $ids, 'wattage', array( 'fields' => 'names' ) );
		if ( ! is_wp_error( $wt ) && $wt && preg_match_all( '/([0-9]+(?:\.[0-9]+)?)/', implode( ' ', $wt ), $m ) ) {
			foreach ( $m[1] as $n ) {
				$w = max( $w, (int) ceil( (float) $n ) );
			}
		}
	}

	$featblob = '';
	foreach ( array( 'dimming', 'emergency', 'pir', 'microwave', 'iprating', 'color' ) as $tx ) {
		if ( taxonomy_exists( $tx ) ) {
			$tn = wp_get_object_terms( $ids, $tx, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $tn ) && $tn ) {
				$featblob .= ' ' . strtolower( implode( ' ', $tn ) );
			}
		}
	}
	if ( '' !== $featblob ) {
		foreach ( ricoman_pf_feature_map() as $kw => $label ) {
			if ( false !== strpos( $featblob, $kw ) ) {
				$feats[ $label ] = true;
			}
		}
	}
	return array( 'lm' => $lm, 'w' => $w, 'feats' => $feats );
}

/** Rebuild a product's variant metrics when it (or a variant) is saved. */
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	$target = 0;
	if ( 'product' === $post->post_type ) {
		$target = (int) $post_id;
	} elseif ( 'variant-product' === $post->post_type ) {
		$target = (int) get_post_meta( $post_id, 'parent_product', true );
	}
	if ( $target ) {
		delete_post_meta( $target, '_rm_pfm' );
		if ( ! wp_next_scheduled( 'ricoman_pf_build', array( $target ) ) ) {
			wp_schedule_single_event( time() + 5, 'ricoman_pf_build', array( $target ) );
		}
	}
}, 10, 2 );

/**
 * The category archive grid + filter UI. Reads the current queried product-cat
 * term (or a cat="slug" attribute). [ricoman_cat_filter]
 */
add_shortcode( 'ricoman_cat_filter', function ( $atts ) {
	$atts = shortcode_atts( array( 'cat' => '' ), $atts, 'ricoman_cat_filter' );
	$tax  = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';

	$term = $atts['cat'] ? get_term_by( 'slug', $atts['cat'], $tax ) : get_queried_object();
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	);
	$title = 'All products';
	if ( $term instanceof WP_Term ) {
		$args['tax_query'] = array( array( 'taxonomy' => $tax, 'terms' => $term->term_id ) );
		$title             = $term->name;
	}
	$q = new WP_Query( $args );
	if ( ! $q->have_posts() ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">No products in this category yet.</p></div>';
	}

	$cards   = '';
	$maxlm   = 0;
	$maxw    = 0;
	$allfeat = array();
	while ( $q->have_posts() ) {
		$q->the_post();
		$pid   = get_the_ID();
		$mx    = ricoman_pf_metrics( $pid );
		$maxlm = max( $maxlm, $mx['lm'] );
		$maxw  = max( $maxw, $mx['w'] );
		$fslug = array();
		foreach ( $mx['feats'] as $f ) {
			$allfeat[ $f ] = true;
			$fslug[]       = sanitize_title( $f );
		}
		$img   = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
		$sub   = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '';
		$style = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		// Lumens/wattage stay as data-attributes for the filter, but are NOT shown
		// on the card: a product spans many variants with different output/power,
		// so a single figure on the card would mislead.
		$cards .= '<a class="rm-projcard rm-fcard" href="' . esc_url( get_permalink() ) . '"'
			. ' data-lm="' . (int) $mx['lm'] . '" data-w="' . (int) $mx['w'] . '" data-feat="' . esc_attr( implode( ' ', $fslug ) ) . '"' . $style . '>'
			. '<span class="rm-projcard-ov">'
			. ( $sub ? '<span class="rm-eyebrow">' . esc_html( $sub ) . '</span>' : '' )
			. '<span class="rm-projcard-t">' . esc_html( get_the_title() ) . '</span>'
			. '</span></a>';
	}
	wp_reset_postdata();

	$total = $q->post_count;
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;

	// Feature tick-boxes (skip junk labels + the excluded set).
	ksort( $allfeat );
	$excluded = ricoman_pf_excluded_features();
	$ticks    = '';
	foreach ( array_keys( $allfeat ) as $f ) {
		if ( ! preg_match( '/[a-z]{2,}/i', (string) $f ) || in_array( $f, $excluded, true ) ) {
			continue;
		}
		$slug   = sanitize_title( $f );
		$ticks .= '<label class="rm-ftick"><input type="checkbox" value="' . esc_attr( $slug ) . '"> ' . esc_html( $f ) . '</label>';
	}

	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );

	// Dual-range (min + max) sliders for light output and power.
	$lmslider = $maxlm ? '<div class="rm-frange rm-dual"><label>Light output <b class="rm-lm-lo">0</b> – <b class="rm-lm-hi">' . $maxlm . '</b> lm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-lm-min" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> – <b class="rm-w-hi">' . $maxw . '</b> W</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-w-min" min="0" max="' . $maxw . '" step="1" value="0">'
		. '<input type="range" class="rm-w-max" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div></div>' : '';

	$out  = '<div class="rm-pp-wrap rm-catarch">';
	$out .= '<div class="rm-pp-crumb">' . $crumb . '</div>';
	$out .= '<h1 class="rm-catarch-title">' . esc_html( $title ) . ' <span class="rm-catarch-count">' . (int) $total . '</span></h1>';
	$out .= '<div class="rm-catgrid-wrap"><aside class="rm-facets">'
		. ( $lmslider || $wslider || $ticks ? '<p class="rm-facets-head">Filter</p>' : '' )
		. $lmslider . $wslider
		. ( $ticks ? '<div class="rm-fgroup"><p class="rm-facets-sub">Features</p>' . $ticks . '</div>' : '' )
		. ( $lmslider || $wslider || $ticks ? '<button type="button" class="rm-fclear">Clear filters</button>' : '' )
		. '</aside>';
	$out .= '<div class="rm-catgrid"><p class="rm-fcount"><b>' . (int) $total . '</b> products</p>'
		. '<div class="rm-projgrid rm-prodgrid rm-fgrid">' . $cards . '</div>'
		. '<p class="rm-fnone" hidden>No products match those filters. <button type="button" class="rm-fclear">Clear filters</button></p></div>';
	$out .= '</div></div>';

	// Filtering is wired up by the enqueued product-gallery.js (rmCatFilterInit),
	// keyed off .rm-catarch — reliable regardless of where the markup lands.
	return $out;
} );

/* ---------------------------------------------------------------------------
 * Admin: rebuild the lumens/wattage filter data on demand.
 *
 * Variant metrics are normally built lazily by a background job as catalogue
 * pages are viewed. This button forces a full recompute across every product
 * so the filter ranges are correct immediately (e.g. after a data import).
 * ------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Filter Data', 'ricoman' ),
		__( 'Filter Data', 'ricoman' ),
		'manage_options',
		'ricoman-filter-data',
		'ricoman_render_filter_data'
	);
}, 31 );

function ricoman_render_filter_data() {
	$built = 0;
	if ( function_exists( 'wp_count_posts' ) ) {
		$counts = wp_count_posts( 'product' );
		$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;
	} else {
		$total = 0;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'Filter Data', 'ricoman' ) . '</h1>';
	if ( isset( $_GET['built'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Rebuilt light-output / power filter data for %d product(s).', 'ricoman' ), (int) $_GET['built'] )
			. '</p></div>';
	}
	echo '<p>' . esc_html__( 'The product catalogue filters (light output and power) read their ranges from each product\'s variants. This is computed automatically in the background as pages are viewed; use this button to rebuild it for every product right now — handy after importing or editing variant data.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html( sprintf( __( '%d published products', 'ricoman' ), $total ) ) . '</strong></p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	echo '<input type="hidden" name="action" value="ricoman_rebuild_filters">';
	wp_nonce_field( 'ricoman_rebuild_filters' );
	submit_button( __( 'Rebuild filter data now', 'ricoman' ), 'primary' );
	echo '</form></div>';
}

add_action( 'admin_post_ricoman_rebuild_filters', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_rebuild_filters' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$built = 0;
	foreach ( $ids as $pid ) {
		ricoman_pf_rebuild( $pid );
		$built++;
	}
	wp_safe_redirect( add_query_arg( 'built', $built, admin_url( 'admin.php?page=ricoman-filter-data' ) ) );
	exit;
} );

/**
 * A representative image URL for a product category: the term's own image meta if
 * set, else the first in-category product's image. Cached per request.
 */
function ricoman_category_image( $term_id, $tax = 'product-cat' ) {
	static $cache = array();
	if ( isset( $cache[ $term_id ] ) ) {
		return $cache[ $term_id ];
	}
	$img = '';
	foreach ( array( 'thumbnail_id', 'image_id', 'image' ) as $k ) {
		$v = get_term_meta( $term_id, $k, true );
		if ( $v && is_numeric( $v ) ) {
			$img = wp_get_attachment_image_url( (int) $v, 'large' );
			if ( $img ) {
				break;
			}
		} elseif ( is_string( $v ) && false !== strpos( $v, '://' ) ) {
			$img = $v;
			break;
		}
	}
	if ( ! $img ) {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 12,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => (int) $term_id ) ),
		) );
		foreach ( $ids as $pid ) {
			$cand = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
			if ( $cand ) {
				$img = $cand;
				break;
			}
		}
	}
	$cache[ $term_id ] = $img;
	return $img;
}

/**
 * Dynamic category cards (real categories + real images) for the homepage range
 * grid, replacing the old hard-coded placeholder cards.
 */
add_shortcode( 'ricoman_category_cards', function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 8, 'exclude' => 'Accessories' ), $atts, 'ricoman_category_cards' );
	// Cache the rendered cards (image lookups query products); invalidated whenever
	// a product/variant changes (shared version), with a 12h backstop.
	$ver    = function_exists( 'ricoman_products_ver' ) ? ricoman_products_ver() : '1';
	$ckey   = 'rm_catcards_' . md5( wp_json_encode( $atts ) . $ver );
	$cached = get_transient( $ckey );
	if ( false !== $cached ) {
		return $cached;
	}
	$tax  = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array(
		'taxonomy'   => $tax,
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
	) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$excl  = array_filter( array_map( 'trim', explode( ',', (string) $atts['exclude'] ) ) );
	$limit = (int) $atts['limit'];
	$cards = '';
	$n     = 0;
	foreach ( $terms as $t ) {
		if ( in_array( $t->name, $excl, true ) ) {
			continue;
		}
		$img = ricoman_category_image( $t->term_id, $tax );
		if ( ! $img ) {
			$img = get_theme_file_uri( 'assets/images/ceiling.webp' );
		}
		$cards .= '<a class="rm-catcard" href="' . esc_url( get_term_link( $t ) ) . '">'
			. '<span class="rm-catcard-img" style="background-image:url(' . esc_url( $img ) . ')"></span>'
			. '<span class="rm-catcard-meta"><span class="rm-catcard-t">' . esc_html( $t->name ) . '</span>'
			. '<span class="rm-catcard-c">' . esc_html( sprintf( _n( '%d product', '%d products', $t->count, 'ricoman' ), $t->count ) ) . '</span></span></a>';
		$n++;
		if ( $limit && $n >= $limit ) {
			break;
		}
	}
	$out = $cards ? '<div class="rm-catcards">' . $cards . '</div>' : '';
	set_transient( $ckey, $out, 12 * HOUR_IN_SECONDS );
	return $out;
} );

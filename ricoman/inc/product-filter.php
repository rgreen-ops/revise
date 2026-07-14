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
	$pid = (int) $pid;
	if ( isset( $memo[ $pid ] ) ) {
		return $memo[ $pid ];
	}

	// Fast path: a precomputed record exists. Building this live means ~5 ACF
	// get_field() calls per product, which across ~500 products in the catalogue
	// loop cost ~40s per page load. The full record (lumens, watts, features,
	// subtitle and image) is precomputed by a background job and read back as a
	// single meta value here — zero ACF calls on the request.
	$pre = get_post_meta( $pid, '_rm_pfm', true );
	if ( is_array( $pre ) && ! empty( $pre['_v'] ) ) {
		return $memo[ $pid ] = $pre;
	}

	// Not built (or old variant-only format): schedule ONE batched build and
	// return a cheap placeholder — NEVER compute live here. The live computation
	// is ~5 ACF get_field() calls per product; across the catalogue loop that was
	// the ~40s cost, and on hosts that run WP-Cron synchronously even the
	// background rebuild could block a visitor. Real lumens/watts/features fill in
	// once ricoman_pf_build_all has run (it bumps the version, refreshing caches).
	static $sched = false;
	if ( ! $sched ) {
		$sched = true;
		if ( ! wp_next_scheduled( 'ricoman_pf_build_all' ) ) {
			wp_schedule_single_event( time() + 5, 'ricoman_pf_build_all' );
		}
	}
	return $memo[ $pid ] = array( 'lm' => 0, 'w' => 0, 'feats' => array() );
}

/**
 * Live computation of a product's metrics (parent ACF text + variant data).
 * Heavy (ACF get_field + variant queries) — only ever run in the background
 * builder or as a one-off fallback, never repeatedly in the catalogue loop.
 */
function ricoman_pf_compute_metrics( $pid ) {
	$pid   = (int) $pid;
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
	// Cut-out diameter from the parent text (e.g. "68mm cut-out" / "cut-out Ø68").
	$co = 0;
	if ( preg_match_all( '/cut[\s-]?out\D{0,8}(\d{2,3})/i', $lc, $m ) ) {
		foreach ( $m[1] as $n ) {
			$co = max( $co, (int) $n );
		}
	}
	// Merge variant-derived lumens/wattage/cut-out/features.
	$vm = ricoman_pf_variant_metrics( $pid );
	$lm = max( $lm, (int) ( $vm['lm'] ?? 0 ) );
	$w  = max( $w, (int) ( $vm['w'] ?? 0 ) );
	$co = max( $co, (int) ( $vm['co'] ?? 0 ) );
	foreach ( (array) ( $vm['feats'] ?? array() ) as $f => $on ) {
		// variant_metrics returns feats as label=>true; normalise to label keys.
		$feats[ is_int( $f ) ? $on : $f ] = true;
	}
	return array( 'lm' => $lm, 'w' => $w, 'co' => $co, 'feats' => array_keys( $feats ) );
}

/**
 * Compute + store a product's full precomputed catalogue record. Includes the
 * card subtitle and image URL so the catalogue render needs no ACF calls at all.
 */
function ricoman_pf_rebuild( $pid ) {
	$pid  = (int) $pid;
	$rec  = ricoman_pf_compute_metrics( $pid );
	$rec['sub'] = (string) ( function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '' );
	$rec['img'] = (string) ( function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : '' );
	$rec['_v']  = 2;
	update_post_meta( $pid, '_rm_pfm', $rec );
	return $rec;
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
		'posts_per_page' => 25,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'cache_results'  => false,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		// Rebuild products with NO record AND those still in the old variant-only
		// format (missing the "_v" marker / precomputed sub+img). Without this the
		// old records satisfy NOT EXISTS and never get upgraded, so the catalogue
		// keeps falling back to the slow live ACF path.
		'meta_query'     => array(
			'relation' => 'OR',
			array( 'key' => '_rm_pfm', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_rm_pfm', 'value' => '"_v";', 'compare' => 'NOT LIKE' ),
		),
	) );
	foreach ( $ids as $pid ) {
		ricoman_pf_rebuild( (int) $pid );
	}
	// More still missing? Come back for the next batch shortly. Small batches so
	// that on a synchronous-cron host a single run can never block a visitor long.
	if ( count( $ids ) >= 25 && ! wp_next_scheduled( 'ricoman_pf_build_all' ) ) {
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
	$co    = 0;
	$feats = array();
	if ( ! post_type_exists( 'variant-product' ) ) {
		return array( 'lm' => 0, 'w' => 0, 'co' => 0, 'feats' => $feats );
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
		return array( 'lm' => 0, 'w' => 0, 'co' => 0, 'feats' => $feats );
	}

	// Lumens: one MAX query restricted by post_id (indexed) — no table scan.
	$ids_in   = implode( ',', array_map( 'absint', $ids ) );
	// Cut-out diameter (mm) — MAX across variants (strip "mm"/spaces/commas).
	$co = (int) $wpdb->get_var(
		"SELECT MAX(CAST(REPLACE(REPLACE(REPLACE(LOWER(meta_value),'mm',''),' ',''),',','') AS UNSIGNED))
		 FROM {$wpdb->postmeta}
		 WHERE post_id IN ($ids_in) AND meta_key = 'cut_out'"
	);
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
	return array( 'lm' => $lm, 'w' => $w, 'co' => $co, 'feats' => $feats );
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
	// Honour each product's "Order" (menu_order) page attribute first, so the team
	// can promote products within a category; fall back to alphabetical.
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'no_found_rows'  => true,
	);
	$title = 'All products';
	if ( $term instanceof WP_Term ) {
		$args['tax_query'] = array( array( 'taxonomy' => $tax, 'terms' => $term->term_id ) );
		$title             = $term->name;
	}
	$groupby_cb = function() { global $wpdb; return "$wpdb->posts.ID"; };
	add_filter( 'posts_groupby', $groupby_cb );
	$q = new WP_Query( $args );
	remove_filter( 'posts_groupby', $groupby_cb );
	if ( ! $q->have_posts() ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">No products in this category yet.</p></div>';
	}

	// Deduplicate posts by ID in case plugin JOINs (e.g. Yoast) return duplicates.
	$seen_ids = array();
	$posts    = array_filter( $q->posts, function( $p ) use ( &$seen_ids ) {
		if ( isset( $seen_ids[ $p->ID ] ) ) { return false; }
		$seen_ids[ $p->ID ] = true;
		return true;
	} );

	$cards   = '';
	$maxlm   = 0;
	$maxw    = 0;
	$maxco   = 0;
	$allfeat = array();
	foreach ( $posts as $post ) {
		setup_postdata( $post );
		$pid   = $post->ID;
		$mx    = ricoman_pf_metrics( $pid );
		$maxlm = max( $maxlm, $mx['lm'] );
		$maxw  = max( $maxw, $mx['w'] );
		$co    = (int) ( $mx['co'] ?? 0 );
		$maxco = max( $maxco, $co );
		$fslug = array();
		foreach ( $mx['feats'] as $f ) {
			$allfeat[ $f ] = true;
			$fslug[]       = sanitize_title( $f );
		}
		$img  = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
		$sub  = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '';
		$cats = wp_get_post_terms( $pid, $pcat_tax, array( 'fields' => 'slugs' ) );
		$cats = is_wp_error( $cats ) ? array() : $cats;
		$mts  = wp_get_post_terms( $pid, 'mounting-method', array( 'fields' => 'slugs' ) );
		$mts  = is_wp_error( $mts ) ? array() : $mts;
		$fins = ricoman_pcard_finish_slugs( $pid );
		$isnw = get_post_meta( $pid, '_ricoman_is_new', true ) ? true : false;
		$cards .= ricoman_pcard_html( get_permalink(), $pid, $img, $sub, $mx, $co, $fslug, $cats, $mts, $fins, $isnw );
	}
	wp_reset_postdata();

	$total = count( $posts );
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;
	$maxco = $maxco > 0 ? (int) ( ceil( $maxco / 5 ) * 5 ) : 0;

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
		. '<div class="rm-dual-track"><input type="range" class="rm-lm-min" aria-label="Minimum light output (lumens)" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" aria-label="Maximum light output (lumens)" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> – <b class="rm-w-hi">' . $maxw . '</b> W</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-w-min" aria-label="Minimum power (watts)" min="0" max="' . $maxw . '" step="1" value="0">'
		. '<input type="range" class="rm-w-max" aria-label="Maximum power (watts)" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div></div>' : '';
	// Cut-out slider — only when this category's products actually have cut-out data.
	$coslider = $maxco ? '<div class="rm-frange rm-dual"><label>Cut-out <b class="rm-co-lo">0</b> – <b class="rm-co-hi">' . $maxco . '</b> mm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-co-min" aria-label="Minimum cut-out (mm)" min="0" max="' . $maxco . '" step="1" value="0">'
		. '<input type="range" class="rm-co-max" aria-label="Maximum cut-out (mm)" min="0" max="' . $maxco . '" step="1" value="' . $maxco . '"></div></div>' : '';

	// Per-category SEO copy (editable on the category screen): intro above the
	// grid, body/FAQ below it.
	$seo_intro = '';
	$seo_body  = '';
	if ( $term instanceof WP_Term && function_exists( 'ricoman_cat_seo' ) ) {
		$intro = ricoman_cat_seo( $term->term_id, 'intro' );
		$body  = ricoman_cat_seo( $term->term_id, 'body' );
		if ( '' !== trim( $intro ) ) {
			$seo_intro = '<div class="rm-catarch-intro">' . wpautop( wp_kses_post( $intro ) ) . '</div>';
		}
		if ( '' !== trim( $body ) ) {
			// Run shortcodes (e.g. [ricoman_faq], which adds FAQPage schema), using
			// the same autop/shortcode order as the_content so block shortcodes
			// aren't wrapped in stray <p> tags.
			$rendered = do_shortcode( shortcode_unautop( wpautop( wp_kses_post( $body ) ) ) );
			$seo_body = '<div class="rm-catarch-body">' . $rendered . '</div>';
		}
	}

	$out  = '<style>'
		. '.rm-pcard{display:flex!important;flex-direction:column;text-decoration:none;color:inherit;border-radius:0;background:none;overflow:visible}'
		. '.rm-pcard-img{position:relative;aspect-ratio:4/5;background:#f2f2f2;border-radius:12px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}'
		. '.rm-pcard-img img{width:82%;height:82%;object-fit:contain;display:block}'
		. '.rm-pcard-body{padding:14px 4px 0;text-align:center}'
		. '.rm-pcard-eyebrow{display:block;font-size:.82rem;letter-spacing:0;text-transform:none;font-family:Poppins;font-weight:400;color:#888;margin-top:6px;text-align:center;line-height:1.4}'
		. '.rm-pcard-title{display:block;font-size:1.1rem;font-weight:600;font-family:Poppins;line-height:1.3;text-align:center}'
		. '.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px}'
		. '@media(max-width:1100px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:repeat(3,1fr)}}'
		. '@media(max-width:600px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:1fr 1fr}}'
		. '@media(max-width:400px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:1fr}}'
		. '.rm-allpgrid>p,.rm-allpgrid>br{display:none!important}'
		. '[style*="display: none"]{display:none!important}'
		. '</style>';
	$out .= '<div class="rm-pp-wrap rm-catarch rm-allprods">';
	$out .= '<div class="rm-pp-crumb">' . $crumb . '</div>';
	$out .= '<h1 class="rm-catarch-title">' . esc_html( $title ) . ' <span class="rm-catarch-count" aria-hidden="true">' . (int) $total . '</span></h1>';
	$out .= $seo_intro;
	$out .= '<div class="rm-catgrid-wrap"><aside class="rm-facets">'
		. ( $lmslider || $wslider || $coslider || $ticks ? '<p class="rm-facets-head">Filter</p>' : '' )
		. $lmslider . $wslider . $coslider
		. ( $ticks ? '<div class="rm-fgroup"><p class="rm-facets-sub">Features</p>' . $ticks . '</div>' : '' )
		. ( $lmslider || $wslider || $coslider || $ticks ? '<button type="button" class="rm-fclear">Clear filters</button>' : '' )
		. '</aside>';
	$out .= '<div class="rm-catgrid">';
	$out .= '<div class="rm-catgrid-top"><p class="rm-fcount"><b>' . (int) $total . '</b> products</p><div class="rm-pgn rm-pgn-top" aria-label="Products pagination top"></div></div>';
	$out .= '<div class="rm-allpgrid rm-fgrid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:24px">' . $cards . '</div>';
	$out .= '<p class="rm-fnone" hidden>No products match those filters. <button type="button" class="rm-fclear">Clear filters</button></p>';
	$out .= '<div class="rm-pgn rm-pgn-bot" aria-label="Products pagination"></div>';
	$out .= '</div></div>';
	$out .= $seo_body;
	// Inline pagination — runs immediately, bypasses W3Speedster JS lazy-loading.
	$out .= '<script>(function(){var w=document.currentScript&&document.currentScript.closest?document.currentScript.closest(".rm-allprods"):null;if(!w)w=document.querySelector(".rm-allprods:not([data-rmpgn])");if(!w||w.dataset.rmpgn)return;w.dataset.rmpgn="1";var g=w.querySelector(".rm-allpgrid");if(g){[].slice.call(g.children).forEach(function(p){if(p.tagName==="P"){while(p.firstChild){g.insertBefore(p.firstChild,p);}g.removeChild(p);}});}var P=20,pg=1,all=[].slice.call(w.querySelectorAll(".rm-fcard"));if(!all.length)return;function show(){var s=(pg-1)*P;all.forEach(function(c){c.style.display="none";});all.slice(s,s+P).forEach(function(c){c.style.display="";});render();}function render(){var pages=Math.ceil(all.length/P);[".rm-pgn-top",".rm-pgn-bot"].forEach(function(sel){var el=w.querySelector(sel);if(!el)return;if(pages<=1){el.innerHTML="";return;}var h="";if(pg>1)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg-1)+"\">← Prev</button>";h+="<span class=\"rm-pgn-info\">Page "+pg+" of "+pages+"</span>";if(pg<pages)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg+1)+"\">Next →</button>";el.innerHTML=h;el.querySelectorAll(".rm-pgn-btn").forEach(function(b){b.addEventListener("click",function(){pg=+b.dataset.p;show();w.scrollIntoView({behavior:"smooth",block:"start"});});});});}show();})()</script>';
	$out .= '</div>';

	// Full filter+pagination wired by product-gallery.js (rmCatFilterInit) when loaded.
	return $out;
} );

/* ---------------------------------------------------------------------------
 * Shared product card helpers.
 * ------------------------------------------------------------------------- */

/** Return sanitized finish slugs from _ricoman_finishes meta. */
function ricoman_pcard_finish_slugs( $pid ) {
	$raw = get_post_meta( $pid, '_ricoman_finishes', true );
	if ( ! $raw ) {
		return array();
	}
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		return array();
	}
	$slugs = array();
	foreach ( $data as $row ) {
		if ( ! empty( $row['name'] ) ) {
			$slugs[] = sanitize_title( $row['name'] );
		}
	}
	return $slugs;
}

/** Build a portrait product card <a> element. */
function ricoman_pcard_html( $url, $pid, $img, $sub, $mx, $co, $fslug, $cats, $mts, $fins, $isnew = false, $is_accessory = false, $is_default = false ) {
	$h  = '<a class="rm-fcard rm-pcard" href="' . esc_url( $url ) . '"'
		. ' data-lm="' . (int) $mx['lm'] . '" data-w="' . (int) $mx['w'] . '" data-co="' . (int) $co . '"'
		. ' data-feat="' . esc_attr( implode( ' ', $fslug ) ) . '"'
		. ' data-cat="' . esc_attr( implode( ' ', $cats ) ) . '"'
		. ' data-mount="' . esc_attr( implode( ' ', $mts ) ) . '"'
		. ' data-fin="' . esc_attr( implode( ' ', $fins ) ) . '"'
		. ( $is_accessory ? ' data-accessory="1"' : '' )
		. ( $is_default  ? ' data-default="1"'   : '' ) . '>';
	$h .= '<div class="rm-pcard-img">';
	if ( $img ) {
		$h .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title( $pid ) ) . '" loading="lazy">';
	}
	if ( $isnew ) {
		$h .= '<span class="rm-pcard-badge">New</span>';
	}
	$h .= '</div>';
	$h .= '<div class="rm-pcard-body">';
	$h .= '<span class="rm-pcard-title">' . esc_html( get_the_title( $pid ) ) . '</span>';
	if ( $sub ) {
		$h .= '<span class="rm-pcard-eyebrow">' . esc_html( $sub ) . '</span>';
	}
	$h .= '</div>';
	$h .= '</a>';
	return $h;
}

/* ---------------------------------------------------------------------------
 * All-products archive with extended faceted filter. [ricoman_all_products]
 * Shows every published product with sidebar filters: Category, Mounting
 * Method, Finish, Lumens, Wattage. Client-side JS pagination (24 per page).
 * ------------------------------------------------------------------------- */
add_shortcode( 'ricoman_all_products', function () {
	// Resolve which product category taxonomy is active on this install.
	$pcat_tax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';

	// Resolve the Accessories category slug so JS can exclude it from default view.
	$acy_term = get_term_by( 'name', 'Accessories', $pcat_tax );
	$acy_slug = $acy_term ? $acy_term->slug : 'accessories';

	$groupby_cb = function() { global $wpdb; return "$wpdb->posts.ID"; };
	add_filter( 'posts_groupby', $groupby_cb );
	$posts = get_posts( array(
		'post_type'        => 'product',
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	) );
	remove_filter( 'posts_groupby', $groupby_cb );
	// Dedup by ID in case plugin JOINs return duplicate rows.
	$seen_ids = array();
	$posts    = array_values( array_filter( $posts, function( $p ) use ( &$seen_ids ) {
		if ( isset( $seen_ids[ $p->ID ] ) ) { return false; }
		$seen_ids[ $p->ID ] = true;
		return true;
	} ) );
	if ( empty( $posts ) ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">No products yet.</p></div>';
	}

	$cards    = '';
	$maxlm    = 0;
	$maxw     = 0;
	$maxco    = 0;
	$allfeat  = array();
	$all_cats = array();
	$all_mts  = array();
	$all_fins = array();

	foreach ( $posts as $post ) {
		setup_postdata( $post );
		$pid   = $post->ID;
		$mx    = ricoman_pf_metrics( $pid );
		$maxlm = max( $maxlm, $mx['lm'] );
		$maxw  = max( $maxw, $mx['w'] );
		$co    = (int) ( $mx['co'] ?? 0 );
		$maxco = max( $maxco, $co );
		$fslug = array();
		foreach ( $mx['feats'] as $f ) {
			$allfeat[ $f ] = true;
			$fslug[]       = sanitize_title( $f );
		}
		$img  = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
		$sub  = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_subname' ) : '';
		$cats = wp_get_post_terms( $pid, $pcat_tax, array( 'fields' => 'slugs' ) );
		$cats = is_wp_error( $cats ) ? array() : $cats;
		$mts  = wp_get_post_terms( $pid, 'mounting-method', array( 'fields' => 'slugs' ) );
		$mts  = is_wp_error( $mts ) ? array() : $mts;
		$fins  = ricoman_pcard_finish_slugs( $pid );
		$isnw  = get_post_meta( $pid, '_ricoman_is_new', true ) ? true : false;
		$isacy = in_array( get_post_meta( $pid, 'is_accessories_product', true ), array( '1', 'yes', 'true' ), true );

		foreach ( $cats as $s ) {
			if ( ! isset( $all_cats[ $s ] ) ) {
				$t = get_term_by( 'slug', $s, $pcat_tax );
				$all_cats[ $s ] = $t ? $t->name : ucwords( str_replace( '-', ' ', $s ) );
			}
		}
		foreach ( $mts as $s ) {
			if ( ! isset( $all_mts[ $s ] ) ) {
				$t = get_term_by( 'slug', $s, 'mounting-method' );
				$all_mts[ $s ] = $t ? $t->name : ucwords( str_replace( '-', ' ', $s ) );
			}
		}
		foreach ( $fins as $s ) {
			$all_fins[ $s ] = ucwords( str_replace( '-', ' ', $s ) );
		}

		$cards .= ricoman_pcard_html( get_permalink( $pid ), $pid, $img, $sub, $mx, $co, $fslug, $cats, $mts, $fins, $isnw, $isacy );
	}
	wp_reset_postdata();

	$total = count( $posts );
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;
	$maxco = $maxco > 0 ? (int) ( ceil( $maxco / 5 ) * 5 ) : 0;

	// Sliders.
	$lmslider = $maxlm ? '<div class="rm-frange rm-dual"><label>Light output <b class="rm-lm-lo">0</b> – <b class="rm-lm-hi">' . $maxlm . '</b> lm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-lm-min" aria-label="Minimum lumens" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" aria-label="Maximum lumens" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> – <b class="rm-w-hi">' . $maxw . '</b> W</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-w-min" aria-label="Minimum watts" min="0" max="' . $maxw . '" step="1" value="0">'
		. '<input type="range" class="rm-w-max" aria-label="Maximum watts" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div></div>' : '';

	// Feature tick-boxes.
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

	// Category checkboxes.
	asort( $all_cats );
	$cat_ticks = '';
	foreach ( $all_cats as $slug => $label ) {
		$cat_ticks .= '<label class="rm-ftick"><input type="checkbox" class="rm-fcat-cb" value="' . esc_attr( $slug ) . '"> ' . esc_html( $label ) . '</label>';
	}

	// Mounting method checkboxes.
	asort( $all_mts );
	$mt_ticks = '';
	foreach ( $all_mts as $slug => $label ) {
		$mt_ticks .= '<label class="rm-ftick"><input type="checkbox" class="rm-fmount-cb" value="' . esc_attr( $slug ) . '"> ' . esc_html( $label ) . '</label>';
	}

	// Finish checkboxes.
	asort( $all_fins );
	$fin_ticks = '';
	foreach ( $all_fins as $slug => $label ) {
		$fin_ticks .= '<label class="rm-ftick"><input type="checkbox" class="rm-ffin-cb" value="' . esc_attr( $slug ) . '"> ' . esc_html( $label ) . '</label>';
	}

	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );

	// Category dropdown.
	$cat_opts = '<option value="">All Categories</option>';
	asort( $all_cats );
	foreach ( $all_cats as $slug => $label ) {
		$cat_opts .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
	}

	// Mounting method dropdown.
	$mt_opts = '<option value="">All Mounting Methods</option>';
	asort( $all_mts );
	foreach ( $all_mts as $slug => $label ) {
		$mt_opts .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
	}

	$sidebar  = '<p class="rm-facets-head">Filter</p>';
	if ( $all_cats ) {
		$sidebar .= '<div class="rm-fgroup rm-fgroup-sel"><label class="rm-facets-sub" for="rm-fcat-sel">Category</label>'
			. '<select id="rm-fcat-sel" class="rm-fcat-sel rm-fselect">' . $cat_opts . '</select></div>';
	}
	if ( $all_mts ) {
		$sidebar .= '<div class="rm-fgroup rm-fgroup-sel"><label class="rm-facets-sub" for="rm-fmount-sel">Mounting Method</label>'
			. '<select id="rm-fmount-sel" class="rm-fmount-sel rm-fselect">' . $mt_opts . '</select></div>';
	}
	$sidebar .= $lmslider . $wslider;
	if ( $fin_ticks ) {
		$sidebar .= '<div class="rm-fgroup"><p class="rm-facets-sub">Finish</p>' . $fin_ticks . '</div>';
	}
	if ( $ticks ) {
		$sidebar .= '<div class="rm-fgroup"><p class="rm-facets-sub">Features</p>' . $ticks . '</div>';
	}
	$sidebar .= '<button type="button" class="rm-fclear">Clear filters</button>';

	// Inline critical CSS so card layout works even if the stylesheet is cached.
	$out  = '<style>'
		. '.rm-pcard{display:flex!important;flex-direction:column;text-decoration:none;color:inherit;border-radius:0;background:none;overflow:visible}'
		. '.rm-pcard-img{position:relative;aspect-ratio:4/5;background:#f2f2f2;border-radius:12px;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}'
		. '.rm-pcard-img img{width:82%;height:82%;object-fit:contain;display:block}'
		. '.rm-pcard-body{padding:14px 4px 0;text-align:center}'
		. '.rm-pcard-eyebrow{display:block;font-size:.82rem;letter-spacing:0;text-transform:none;font-family:Poppins;font-weight:400;color:#888;margin-top:6px;text-align:center;line-height:1.4}'
		. '.rm-pcard-title{display:block;font-size:1.1rem;font-weight:600;font-family:Poppins;line-height:1.3;text-align:center}'
		. '.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:24px}'
		. '@media(max-width:1100px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:repeat(3,1fr)}}'
		. '@media(max-width:600px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:1fr 1fr}}'
		. '@media(max-width:400px){.rm-allprods .rm-prodgrid,.rm-allprods .rm-allpgrid{grid-template-columns:1fr}}'
		. '.rm-allpgrid>p,.rm-allpgrid>br{display:none!important}'
		. '[style*="display: none"]{display:none!important}'
		. '</style>';
	$out .= '<div class="rm-pp-wrap rm-catarch rm-allprods" data-acy-cat="' . esc_attr( $acy_slug ) . '">';
	$out .= '<div class="rm-pp-crumb">' . $crumb . '</div>';
	$out .= '<h1 class="rm-catarch-title">All Products <span class="rm-catarch-count" aria-hidden="true">' . (int) $total . '</span></h1>';
	$out .= '<div class="rm-catgrid-wrap"><aside class="rm-facets">' . $sidebar . '</aside>';
	$out .= '<div class="rm-catgrid">';
	$out .= '<div class="rm-catgrid-top"><p class="rm-fcount"><b>' . (int) $total . '</b> products</p><div class="rm-pgn rm-pgn-top" aria-label="Products pagination top"></div></div>';
	$out .= '<div class="rm-allpgrid" style="display:grid;grid-template-columns:repeat(4,1fr);grid-auto-rows:auto;gap:24px">' . $cards . '</div>';
	$out .= '<p class="rm-fnone" hidden>No products match those filters. <button type="button" class="rm-fclear">Clear filters</button></p>';
	$out .= '<div class="rm-pgn rm-pgn-bot" aria-label="Products pagination"></div>';
	// Inline pagination — runs immediately, bypasses W3Speedster JS lazy-loading.
	$out .= '<script>(function(){var w=document.currentScript&&document.currentScript.closest?document.currentScript.closest(".rm-allprods"):null;if(!w)w=document.querySelector(".rm-allprods:not([data-rmpgn])");if(!w||w.dataset.rmpgn)return;w.dataset.rmpgn="1";var g=w.querySelector(".rm-allpgrid");if(g){[].slice.call(g.children).forEach(function(p){if(p.tagName==="P"){while(p.firstChild){g.insertBefore(p.firstChild,p);}g.removeChild(p);}});}var P=20,pg=1,all=[].slice.call(w.querySelectorAll(".rm-fcard"));if(!all.length)return;function show(){var s=(pg-1)*P;all.forEach(function(c){c.style.display="none";});all.slice(s,s+P).forEach(function(c){c.style.display="";});render();}function render(){var pages=Math.ceil(all.length/P);[".rm-pgn-top",".rm-pgn-bot"].forEach(function(sel){var el=w.querySelector(sel);if(!el)return;if(pages<=1){el.innerHTML="";return;}var h="";if(pg>1)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg-1)+"\">← Prev</button>";h+="<span class=\"rm-pgn-info\">Page "+pg+" of "+pages+"</span>";if(pg<pages)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg+1)+"\">Next →</button>";el.innerHTML=h;el.querySelectorAll(".rm-pgn-btn").forEach(function(b){b.addEventListener("click",function(){pg=+b.dataset.p;show();w.scrollIntoView({behavior:"smooth",block:"start"});});});});}show();})()</script>';
	$out .= '</div></div></div>';

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
			'posts_per_page' => 40,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => (int) $term_id ) ),
		) );
		foreach ( $ids as $pid ) {
			$cand = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( $pid ) : get_the_post_thumbnail_url( $pid, 'large' );
			// Skip a candidate whose LOCAL file is missing (migrated-but-not-on-disk),
			// otherwise the tile shows a broken/empty background. Remote URLs (served
			// via the live-origin fallback) are accepted as-is.
			if ( $cand && ricoman_image_usable( $cand ) ) {
				$img = $cand;
				break;
			}
		}
	}
	$cache[ $term_id ] = $img;
	return $img;
}

/** True if an image URL is safe to use: a remote URL, or a local file that
 *  actually exists on disk (so we don't paint a broken background image). */
function ricoman_image_usable( $url ) {
	$up = wp_get_upload_dir();
	if ( 0 !== strpos( $url, $up['baseurl'] ) ) {
		return true; // remote / live-origin fallback URL — assume usable.
	}
	$path = str_replace( $up['baseurl'], $up['basedir'], strtok( $url, '?' ) );
	return $path && file_exists( $path );
}

/**
 * Dynamic category cards (real categories + real images) for the homepage range
 * grid, replacing the old hard-coded placeholder cards.
 */
add_shortcode( 'ricoman_category_cards', function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 8, 'exclude' => 'Accessories', 'wide' => 0 ), $atts, 'ricoman_category_cards' );
	// Cache the rendered cards (image lookups query products); invalidated whenever
	// a product/variant changes (shared version), with a 12h backstop.
	$ver    = function_exists( 'ricoman_products_ver' ) ? ricoman_products_ver() : '1';
	// 'cm4' markup version: bump to invalidate cached cards when card markup OR
	// ordering changes (here: manual per-category display order, then product count).
	$ckey   = 'rm_catcards_' . md5( 'cm5' . wp_json_encode( $atts ) . $ver );
	$cached = get_transient( $ckey );
	if ( false !== $cached ) {
		return $cached;
	}
	$tax  = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array(
		'taxonomy'   => $tax,
		'hide_empty' => true,
	) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	// Manual display order first (set per category), then most products.
	if ( function_exists( 'ricoman_cat_sort_terms' ) ) {
		$terms = ricoman_cat_sort_terms( $terms );
	}
	$excl  = array_filter( array_map( 'trim', explode( ',', (string) $atts['exclude'] ) ) );
	$limit = (int) $atts['limit'];
	$cards = '';
	$n     = 0;
	foreach ( $terms as $t ) {
		if ( in_array( $t->name, $excl, true ) ) {
			continue;
		}
		// In-situ (default) + Studio images; each falls back to the auto image.
		$insitu = function_exists( 'ricoman_cat_img' ) ? ricoman_cat_img( $t->term_id, 'insitu', $tax ) : ricoman_category_image( $t->term_id, $tax );
		$studio = function_exists( 'ricoman_cat_img' ) ? ricoman_cat_img( $t->term_id, 'studio', $tax ) : $insitu;
		$fallback = get_theme_file_uri( 'assets/images/ceiling.webp' );
		if ( ! $insitu ) { $insitu = $studio ? $studio : $fallback; }
		if ( ! $studio ) { $studio = $insitu; }
		// Lazy: images set on scroll by rmCatImages (data-* attrs, no inline url).
		$cards .= '<a class="rm-catcard" href="' . esc_url( get_term_link( $t ) ) . '">'
			. '<span class="rm-catcard-img" data-insitu="' . esc_url( $insitu ) . '" data-studio="' . esc_url( $studio ) . '"></span>'
			. '<span class="rm-catcard-meta"><span class="rm-catcard-t">' . esc_html( $t->name ) . '</span>'
			. '<span class="rm-catcard-c">' . esc_html( sprintf( _n( '%d product', '%d products', $t->count, 'ricoman' ), $t->count ) ) . '</span></span></a>';
		$n++;
		if ( $limit && $n >= $limit ) {
			break;
		}
	}
	if ( ! $cards ) {
		$out = '';
	} elseif ( (int) $atts['wide'] ) {
		// Wide archive layout (the /products/ tiles): full-width wrapper, more
		// columns, and a Studio/In-situ image toggle (In-situ selected by default).
		$toggle = '<div class="rm-cattoggle" role="group" aria-label="Image style">'
			. '<button type="button" data-mode="insitu" class="on">In-situ</button>'
			. '<button type="button" data-mode="studio">Studio</button></div>';
		$out = '<div class="rm-pp-wrap rm-catarch">' . $toggle . '<div class="rm-catcards rm-catcards-wide">' . $cards . '</div></div>';
	} else {
		$out = '<div class="rm-catcards">' . $cards . '</div>';
	}
	set_transient( $ckey, $out, 12 * HOUR_IN_SECONDS );
	return $out;
} );

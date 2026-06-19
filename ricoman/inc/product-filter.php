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
	$cached = get_transient( 'rm_pfm_' . $pid );
	if ( is_array( $cached ) && isset( $cached['lm'], $cached['w'], $cached['feats'] ) ) {
		return $memo[ $pid ] = $cached;
	}

	// 1) Variant-derived metrics (the authoritative source when present).
	$vm    = ricoman_pf_variant_metrics( $pid );
	$lm    = (int) $vm['lm'];
	$w     = (int) $vm['w'];
	$feats = $vm['feats'];

	// 2) Parent ACF text — fills the gaps / covers products with no variants.
	$text = '';
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

	$res = array( 'lm' => $lm, 'w' => $w, 'feats' => array_keys( $feats ) );
	set_transient( 'rm_pfm_' . $pid, $res, 6 * HOUR_IN_SECONDS );
	return $memo[ $pid ] = $res;
}

/**
 * Aggregate lumens / wattage / feature flags from a product's variants.
 * Lumens live in variant meta; wattage in the `wattage` taxonomy; feature
 * flags can be read from variant taxonomies (dimming, emergency, pir…).
 * Returns the family maximums so the slider ranges cover every variant.
 */
function ricoman_pf_variant_metrics( $pid ) {
	$lm    = 0;
	$w     = 0;
	$feats = array();
	if ( ! post_type_exists( 'variant-product' ) ) {
		return array( 'lm' => 0, 'w' => 0, 'feats' => $feats );
	}
	$ids = get_posts( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => 'parent_product', 'value' => (string) $pid ) ),
	) );
	if ( ! $ids ) {
		return array( 'lm' => 0, 'w' => 0, 'feats' => $feats );
	}

	// Lumens live in variant meta — prime the cache so the loop is query-free
	// even for families with thousands of variants.
	update_meta_cache( 'post', $ids );
	$lm_keys = array( 'lumens', 'lumen', 'lumen_output', 'lumens_output', 'total_lumens', 'output_lumens', 'lumen_value', 'lm' );
	foreach ( $ids as $vid ) {
		foreach ( $lm_keys as $k ) {
			$lv = get_post_meta( $vid, $k, true );
			if ( is_scalar( $lv ) && '' !== trim( (string) $lv ) ) {
				if ( preg_match_all( '/([0-9][0-9,\.]+|[0-9]+)/', (string) $lv, $m ) ) {
					foreach ( $m[1] as $n ) {
						$lm = max( $lm, (int) str_replace( array( ',', '.' ), '', $n ) );
					}
				}
				break; // first populated lumens key wins for this variant.
			}
		}
	}

	// Wattage + feature flags come from taxonomies — one bulk query each across
	// all of the product's variants (not one query per variant).
	$wtxt = '';
	foreach ( array( 'wattage', 'lumen', 'lumens' ) as $ltx ) {
		// 'wattage' parsed below; lumen taxonomies feed the lumens max here.
		if ( ! taxonomy_exists( $ltx ) ) {
			continue;
		}
		$names = wp_get_object_terms( $ids, $ltx, array( 'fields' => 'names' ) );
		if ( is_wp_error( $names ) || ! $names ) {
			continue;
		}
		if ( 'wattage' === $ltx ) {
			$wtxt = implode( ' ', $names );
		} elseif ( preg_match_all( '/([0-9][0-9,\.]+|[0-9]+)/', implode( ' ', $names ), $m ) ) {
			foreach ( $m[1] as $n ) {
				$lm = max( $lm, (int) str_replace( array( ',', '.' ), '', $n ) );
			}
		}
	}
	if ( preg_match_all( '/([0-9]+(?:\.[0-9]+)?)/', $wtxt, $m ) ) {
		foreach ( $m[1] as $n ) {
			$w = max( $w, (int) ceil( (float) $n ) );
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

/** Invalidate cached metrics when a product or one of its variants is saved. */
add_action( 'save_post', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'product' === $post->post_type ) {
		delete_transient( 'rm_pfm_' . $post_id );
	} elseif ( 'variant-product' === $post->post_type ) {
		$parent = (int) get_post_meta( $post_id, 'parent_product', true );
		if ( $parent ) {
			delete_transient( 'rm_pfm_' . $parent );
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

	$total = $q->post_count;
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;

	// Feature tick-boxes (always include the headline three if present anywhere).
	ksort( $allfeat );
	$ticks = '';
	foreach ( array_keys( $allfeat ) as $f ) {
		$slug   = sanitize_title( $f );
		$ticks .= '<label class="rm-ftick"><input type="checkbox" value="' . esc_attr( $slug ) . '"> ' . esc_html( $f ) . '</label>';
	}

	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );

	// Sliders (single-thumb: min light output, max power).
	$lmslider = $maxlm ? '<div class="rm-frange"><label>Min. light output <b class="rm-lm-val">0</b> lm</label>'
		. '<input type="range" class="rm-lm" min="0" max="' . $maxlm . '" step="100" value="0"></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange"><label>Max. power <b class="rm-w-val">' . $maxw . '</b> W</label>'
		. '<input type="range" class="rm-w" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div>' : '';

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

	// Client-side filtering.
	$out .= <<<'JS'
<script>(function(){
 var w=document.currentScript.previousElementSibling;if(!w)return;
 var grid=w.querySelector('.rm-fgrid'),cards=[].slice.call(w.querySelectorAll('.rm-fcard'));
 var lm=w.querySelector('.rm-lm'),pw=w.querySelector('.rm-w');
 var lmv=w.querySelector('.rm-lm-val'),wv=w.querySelector('.rm-w-val');
 var count=w.querySelector('.rm-fcount b'),none=w.querySelector('.rm-fnone');
 function ticks(){return [].slice.call(w.querySelectorAll('.rm-ftick input:checked')).map(function(i){return i.value;});}
 function apply(){
  var minLm=lm?+lm.value:0,maxW=pw?+pw.value:1e9,want=ticks(),shown=0;
  if(lmv&&lm)lmv.textContent=(+lm.value).toLocaleString();
  if(wv&&pw)wv.textContent=pw.value;
  cards.forEach(function(c){
   var clm=+c.dataset.lm||0,cw=+c.dataset.w||0,cf=(c.dataset.feat||'').split(' ');
   var ok=true;
   if(minLm>0&&clm>0&&clm<minLm)ok=false;
   if(pw&&maxW<(+pw.max)&&cw>0&&cw>maxW)ok=false;
   want.forEach(function(f){if(cf.indexOf(f)<0)ok=false;});
   c.hidden=!ok;if(ok)shown++;
  });
  if(count)count.textContent=shown;
  if(none)none.hidden=shown>0;
 }
 [lm,pw].forEach(function(el){if(el)el.addEventListener('input',apply);});
 w.querySelectorAll('.rm-ftick input').forEach(function(i){i.addEventListener('change',apply);});
 w.querySelectorAll('.rm-fclear').forEach(function(b){b.addEventListener('click',function(){
  if(lm)lm.value=0;if(pw)pw.value=pw.max;
  w.querySelectorAll('.rm-ftick input').forEach(function(i){i.checked=false;});apply();
 });});
 apply();
})();</script>
JS;
	return $out;
} );

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

/** Extract {lm, w, feats[]} from a product's ACF text fields. */
function ricoman_pf_metrics( $pid ) {
	$text = '';
	foreach ( array( 'key_features', 'specification', 'product_sort_description', 'product_subname' ) as $f ) {
		$v = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, $f ) : get_post_meta( $pid, $f, true );
		if ( is_array( $v ) ) {
			$v = wp_json_encode( $v );
		}
		$text .= ' ' . (string) $v;
	}
	$lc = strtolower( wp_strip_all_tags( $text ) );

	$lm = 0;
	if ( preg_match_all( '/([0-9][0-9,\.]*)\s*(?:lm|lumens)\b/i', $lc, $m ) ) {
		foreach ( $m[1] as $n ) {
			$lm = max( $lm, (int) str_replace( array( ',', '.' ), '', $n ) );
		}
	}
	$w = 0;
	if ( preg_match_all( '/([0-9]+(?:\.[0-9]+)?)\s*w\b/i', $lc, $m ) ) {
		foreach ( $m[1] as $n ) {
			$w = max( $w, (int) ceil( (float) $n ) );
		}
	}
	$feats = array();
	foreach ( ricoman_pf_feature_map() as $kw => $label ) {
		if ( false !== strpos( $lc, $kw ) ) {
			$feats[ $label ] = true;
		}
	}
	return array( 'lm' => $lm, 'w' => $w, 'feats' => array_keys( $feats ) );
}

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

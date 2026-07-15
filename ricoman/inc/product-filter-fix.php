<?php
/**
 * Shortcode overrides — loaded after product-filter.php.
 *
 * Re-registers ricoman_cat_filter and ricoman_all_products with two fixes:
 *   1. Direct $wpdb->get_col() queries instead of WP_Query — bypasses plugin
 *      JOIN duplication (Yoast SEO etc.) that caused ~713 cards for ~238 products.
 *   2. Explicit get_permalink( $pid ) — prevents all cards linking to the same URL
 *      when internal sub-queries reset the global $post mid-loop.
 */

remove_shortcode( 'ricoman_cat_filter' );
add_shortcode( 'ricoman_cat_filter', function ( $atts ) {
	$atts = shortcode_atts( array( 'cat' => '' ), $atts, 'ricoman_cat_filter' );
	$tax  = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';

	$term  = $atts['cat'] ? get_term_by( 'slug', $atts['cat'], $tax ) : get_queried_object();
	$title = 'All products';
	global $wpdb;
	if ( $term instanceof WP_Term ) {
		$title = $term->name;
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			 WHERE p.post_type = 'product' AND p.post_status = 'publish'
			   AND tt.taxonomy = %s AND tt.term_id = %d
			 ORDER BY p.menu_order ASC, p.post_title ASC",
			$tax, $term->term_id
		) );
	} else {
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'
			 ORDER BY menu_order ASC, post_title ASC"
		);
	}
	if ( empty( $ids ) ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">No products in this category yet.</p></div>';
	}
	$posts = array_filter( array_map( 'get_post', $ids ) );

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
		$cats = wp_get_post_terms( $pid, $tax, array( 'fields' => 'slugs' ) );
		$cats = is_wp_error( $cats ) ? array() : $cats;
		$mts  = wp_get_post_terms( $pid, 'mounting-method', array( 'fields' => 'slugs' ) );
		$mts  = is_wp_error( $mts ) ? array() : $mts;
		$fins = ricoman_pcard_finish_slugs( $pid );
		$isnw = get_post_meta( $pid, '_ricoman_is_new', true ) ? true : false;
		$cards .= ricoman_pcard_html( get_permalink( $pid ), $pid, $img, $sub, $mx, $co, $fslug, $cats, $mts, $fins, $isnw );
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

	// Dual-range sliders for light output and power.
	$lmslider = $maxlm ? '<div class="rm-frange rm-dual"><label>Light output <b class="rm-lm-lo">0</b> &#8211; <b class="rm-lm-hi">' . $maxlm . '</b> lm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-lm-min" aria-label="Minimum light output (lumens)" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" aria-label="Maximum light output (lumens)" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> &#8211; <b class="rm-w-hi">' . $maxw . '</b> W</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-w-min" aria-label="Minimum power (watts)" min="0" max="' . $maxw . '" step="1" value="0">'
		. '<input type="range" class="rm-w-max" aria-label="Maximum power (watts)" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div></div>' : '';
	$coslider = $maxco ? '<div class="rm-frange rm-dual"><label>Cut-out <b class="rm-co-lo">0</b> &#8211; <b class="rm-co-hi">' . $maxco . '</b> mm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-co-min" aria-label="Minimum cut-out (mm)" min="0" max="' . $maxco . '" step="1" value="0">'
		. '<input type="range" class="rm-co-max" aria-label="Maximum cut-out (mm)" min="0" max="' . $maxco . '" step="1" value="' . $maxco . '"></div></div>' : '';

	$seo_intro = '';
	$seo_body  = '';
	if ( $term instanceof WP_Term && function_exists( 'ricoman_cat_seo' ) ) {
		$intro = ricoman_cat_seo( $term->term_id, 'intro' );
		$body  = ricoman_cat_seo( $term->term_id, 'body' );
		if ( '' !== trim( $intro ) ) {
			$seo_intro = '<div class="rm-catarch-intro">' . wpautop( wp_kses_post( $intro ) ) . '</div>';
		}
		if ( '' !== trim( $body ) ) {
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
	$out .= '<script>(function(){var w=document.currentScript&&document.currentScript.closest?document.currentScript.closest(".rm-allprods"):null;if(!w)w=document.querySelector(".rm-allprods:not([data-rmpgn])");if(!w||w.dataset.rmpgn)return;w.dataset.rmpgn="1";var g=w.querySelector(".rm-allpgrid");if(g){[].slice.call(g.children).forEach(function(p){if(p.tagName==="P"){while(p.firstChild){g.insertBefore(p.firstChild,p);}g.removeChild(p);}});}var P=20,pg=1,all=[].slice.call(w.querySelectorAll(".rm-fcard"));if(!all.length)return;function show(){var s=(pg-1)*P;all.forEach(function(c){c.style.display="none";});all.slice(s,s+P).forEach(function(c){c.style.display="";});render();}function render(){var pages=Math.ceil(all.length/P);[".rm-pgn-top",".rm-pgn-bot"].forEach(function(sel){var el=w.querySelector(sel);if(!el)return;if(pages<=1){el.innerHTML="";return;}var h="";if(pg>1)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg-1)+"\">&#8592; Prev</button>";h+="<span class=\"rm-pgn-info\">Page "+pg+" of "+pages+"</span>";if(pg<pages)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg+1)+"\">Next &#8594;</button>";el.innerHTML=h;el.querySelectorAll(".rm-pgn-btn").forEach(function(b){b.addEventListener("click",function(){pg=+b.dataset.p;show();w.scrollIntoView({behavior:"smooth",block:"start"});});});});}show();})()</script>';
	$out .= '</div>';

	return $out;
} );

remove_shortcode( 'ricoman_all_products' );
add_shortcode( 'ricoman_all_products', function () {
	$pcat_tax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$acy_term = get_term_by( 'name', 'Accessories', $pcat_tax );
	$acy_slug = $acy_term ? $acy_term->slug : 'accessories';

	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_type = 'product' AND post_status = 'publish'
		 ORDER BY menu_order ASC, post_title ASC"
	);
	$posts = array_filter( array_map( 'get_post', $ids ) );
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
	$lmslider = $maxlm ? '<div class="rm-frange rm-dual"><label>Light output <b class="rm-lm-lo">0</b> &#8211; <b class="rm-lm-hi">' . $maxlm . '</b> lm</label>'
		. '<div class="rm-dual-track"><input type="range" class="rm-lm-min" aria-label="Minimum lumens" min="0" max="' . $maxlm . '" step="100" value="0">'
		. '<input type="range" class="rm-lm-max" aria-label="Maximum lumens" min="0" max="' . $maxlm . '" step="100" value="' . $maxlm . '"></div></div>' : '';
	$wslider  = $maxw ? '<div class="rm-frange rm-dual"><label>Power <b class="rm-w-lo">0</b> &#8211; <b class="rm-w-hi">' . $maxw . '</b> W</label>'
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
	$out .= '<script>(function(){var w=document.currentScript&&document.currentScript.closest?document.currentScript.closest(".rm-allprods"):null;if(!w)w=document.querySelector(".rm-allprods:not([data-rmpgn])");if(!w||w.dataset.rmpgn)return;w.dataset.rmpgn="1";var g=w.querySelector(".rm-allpgrid");if(g){[].slice.call(g.children).forEach(function(p){if(p.tagName==="P"){while(p.firstChild){g.insertBefore(p.firstChild,p);}g.removeChild(p);}});}var P=20,pg=1,all=[].slice.call(w.querySelectorAll(".rm-fcard"));if(!all.length)return;function show(){var s=(pg-1)*P;all.forEach(function(c){c.style.display="none";});all.slice(s,s+P).forEach(function(c){c.style.display="";});render();}function render(){var pages=Math.ceil(all.length/P);[".rm-pgn-top",".rm-pgn-bot"].forEach(function(sel){var el=w.querySelector(sel);if(!el)return;if(pages<=1){el.innerHTML="";return;}var h="";if(pg>1)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg-1)+"\">&#8592; Prev</button>";h+="<span class=\"rm-pgn-info\">Page "+pg+" of "+pages+"</span>";if(pg<pages)h+="<button class=\"rm-pgn-btn\" data-p=\""+(pg+1)+"\">Next &#8594;</button>";el.innerHTML=h;el.querySelectorAll(".rm-pgn-btn").forEach(function(b){b.addEventListener("click",function(){pg=+b.dataset.p;show();w.scrollIntoView({behavior:"smooth",block:"start"});});});});}show();})()</script>';
	$out .= '</div></div></div>';

	return $out;
} );

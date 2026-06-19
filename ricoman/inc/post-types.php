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

	// --- News (migrated articles) ------------------------------------------
	register_post_type(
		'news',
		array(
			'labels'        => array(
				'name'          => __( 'News', 'ricoman' ),
				'singular_name' => __( 'News Article', 'ricoman' ),
				'menu_name'     => __( 'News', 'ricoman' ),
			),
			'public'        => true,
			'has_archive'   => true,
			'menu_icon'     => 'dashicons-megaphone',
			'menu_position' => 24,
			'rewrite'       => array( 'slug' => 'news', 'with_front' => false ),
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'show_in_rest'  => true,
		)
	);

	// --- Variant products (the order-code rows for the Configure table) -----
	// Each is linked to its parent product via the ACF `parent_product` field.
	register_post_type(
		'variant-product',
		array(
			'labels'        => array(
				'name'          => __( 'Variant Products', 'ricoman' ),
				'singular_name' => __( 'Variant Product', 'ricoman' ),
				'menu_name'     => __( 'Variant Products', 'ricoman' ),
			),
			'public'        => false,
			'show_ui'       => true,
			'menu_icon'     => 'dashicons-screenoptions',
			'menu_position' => 23,
			'rewrite'       => false,
			'supports'      => array( 'title', 'custom-fields' ),
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
 * Register the variant "axis" taxonomies the old data model used (wattage,
 * colour temperature, colour, beam angle, IP rating, dimming, size, …). The
 * variant order codes carry their specs as terms in these taxonomies. The old
 * plugin registered them; our theme must too, otherwise the migrated terms are
 * invisible (wp_get_post_terms can't read an unregistered taxonomy) and specs
 * like Wattage never appear. Attached to products and variant products.
 */
function ricoman_variant_axis_taxonomies() {
	return array(
		'wattage'            => __( 'Wattage', 'ricoman' ),
		'temperature'        => __( 'Colour Temperature', 'ricoman' ),
		'color'              => __( 'Colour', 'ricoman' ),
		'beam-angle'         => __( 'Beam Angle', 'ricoman' ),
		'iprating'           => __( 'IP Rating', 'ricoman' ),
		'size'               => __( 'Size', 'ricoman' ),
		'dimming'            => __( 'Dimming', 'ricoman' ),
		'lighting-direction' => __( 'Lighting Direction', 'ricoman' ),
		'emergency'          => __( 'Emergency', 'ricoman' ),
		'glare-control'      => __( 'Glare Control', 'ricoman' ),
		'microwave'          => __( 'Microwave', 'ricoman' ),
		'pir'                => __( 'PIR', 'ricoman' ),
		'reflector'          => __( 'Reflector', 'ricoman' ),
		'reflector-finish'   => __( 'Reflector Finish', 'ricoman' ),
		'reflector-colour'   => __( 'Reflector Colour', 'ricoman' ),
		'bezel-finish'       => __( 'Bezel Finish', 'ricoman' ),
		'diffuser-material'  => __( 'Diffuser Material', 'ricoman' ),
		'application-area'   => __( 'Application Area', 'ricoman' ),
		'legend-required'    => __( 'Legend Required', 'ricoman' ),
		'fitting-type'       => __( 'Fitting Type', 'ricoman' ),
		'lamp-type'          => __( 'Lamp Type', 'ricoman' ),
		'module'             => __( 'Module', 'ricoman' ),
		'model'              => __( 'Model', 'ricoman' ),
	);
}

function ricoman_register_variant_axes() {
	foreach ( ricoman_variant_axis_taxonomies() as $slug => $label ) {
		if ( taxonomy_exists( $slug ) ) {
			continue;
		}
		register_taxonomy(
			$slug,
			array( 'variant-product' ), // per-variant specs; keeps the product editor uncluttered.
			array(
				'labels'       => array( 'name' => $label, 'singular_name' => $label, 'menu_name' => $label ),
				'hierarchical' => false,
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false, // managed via the Spec Axes hub.
				'show_in_rest' => true,
				'rewrite'      => false,
				'query_var'    => false,
			)
		);
	}
}
add_action( 'init', 'ricoman_register_variant_axes' );

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
 * Self-healing permalinks. Uploading a new theme ZIP (rather than switching
 * themes) doesn't fire after_switch_theme, so the rewrite rules for the
 * product-category / sector taxonomies can be missing — which makes clicking a
 * (sub)category do nothing. If our taxonomy rules aren't present, flush once.
 */
add_action( 'wp_loaded', function () {
	if ( get_transient( 'ricoman_rw_ok' ) ) {
		return;
	}
	$rules = get_option( 'rewrite_rules' );
	$has   = false;
	if ( is_array( $rules ) ) {
		foreach ( array_keys( $rules ) as $k ) {
			if ( false !== strpos( $k, 'product-category' ) ) {
				$has = true;
				break;
			}
		}
	}
	if ( ! $has ) {
		flush_rewrite_rules( false );
	}
	set_transient( 'ricoman_rw_ok', 1, HOUR_IN_SECONDS );
}, 99 );

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
	$tax = taxonomy_exists( 'project-cat' ) ? 'project-cat' : 'application';

	// Category facet chips (only sectors that actually have projects).
	$chips = '';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) && $terms ) {
		$chips = '<div class="rm-projfilters"><button type="button" class="rm-projchip on" data-cat="">All</button>';
		foreach ( $terms as $t ) {
			$chips .= '<button type="button" class="rm-projchip" data-cat="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</button>';
		}
		$chips .= '</div>';
	}

	$out = $chips . '<div class="rm-projgrid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$img    = function_exists( 'ricoman_project_img' ) ? ricoman_project_img( get_the_ID() ) : get_the_post_thumbnail_url( get_the_ID(), 'large' );
		$sector = ricoman_first_term_name( get_the_ID(), array( 'project-cat', 'application' ) );
		$slugs  = wp_get_post_terms( get_the_ID(), $tax, array( 'fields' => 'slugs' ) );
		$cats   = ( ! is_wp_error( $slugs ) && $slugs ) ? implode( ' ', $slugs ) : '';
		$style  = $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '';
		$out   .= '<a class="rm-projcard" data-cats="' . esc_attr( $cats ) . '" href="' . esc_url( get_permalink() ) . '"' . $style . '><span class="rm-projcard-ov">'
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
 * The real product catalogue: category quick-nav + faceted filter bar (light
 * output / power sliders + feature tick-boxes) + every product grouped by
 * category. Powers the /products/ archive. [ricoman_catalogue]
 */
add_shortcode( 'ricoman_catalogue', function ( $atts ) {
	$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );

	// No categorised products yet — fall back to a flat grid of everything.
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return do_shortcode( '[ricoman_products_grid count="60"]' );
	}

	// Category quick-nav.
	$nav = '<div class="rm-catnav">';
	foreach ( $terms as $t ) {
		$nav .= '<a class="rm-catnav-item" href="' . esc_url( get_term_link( $t ) ) . '">'
			. esc_html( $t->name ) . ' <span>' . (int) $t->count . '</span></a>';
	}
	$nav .= '</div>';

	// Build the category sections, collecting facet ranges as we go.
	$sections = '';
	$maxlm    = 0;
	$maxw     = 0;
	$allfeat  = array();
	$total    = 0;
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
		$cnt    = $q->post_count;
		$total += $cnt;
		$cards  = '';
		while ( $q->have_posts() ) {
			$q->the_post();
			$pid   = get_the_ID();
			$mx    = function_exists( 'ricoman_pf_metrics' ) ? ricoman_pf_metrics( $pid ) : array( 'lm' => 0, 'w' => 0, 'feats' => array() );
			$maxlm = max( $maxlm, $mx['lm'] );
			$maxw  = max( $maxw, $mx['w'] );
			$fslug = array();
			foreach ( $mx['feats'] as $f ) {
				$allfeat[ $f ] = true;
				$fslug[]       = sanitize_title( $f );
			}
			$img   = ricoman_product_img( $pid );
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
		$sections .= '<section class="rm-catsec" data-cat="' . esc_attr( $t->slug ) . '"><div class="rm-catsec-head"><h2 class="rm-shead">' . esc_html( $t->name )
			. ' <span class="rm-catarch-count">' . (int) $cnt . '</span></h2>'
			. '<a class="rm-catsec-all" href="' . esc_url( get_term_link( $t ) ) . '">Filter &amp; order codes →</a></div>'
			. '<div class="rm-projgrid rm-prodgrid rm-fgrid">' . $cards . '</div></section>';
	}

	// Filter bar (rounded up sensible ranges).
	$maxlm = $maxlm > 0 ? (int) ( ceil( $maxlm / 500 ) * 500 ) : 0;
	$maxw  = $maxw > 0 ? (int) ( ceil( $maxw / 5 ) * 5 ) : 0;
	ksort( $allfeat );
	$ticks = '';
	foreach ( array_keys( $allfeat ) as $f ) {
		$ticks .= '<label class="rm-ftick"><input type="checkbox" value="' . esc_attr( sanitize_title( $f ) ) . '"> ' . esc_html( $f ) . '</label>';
	}
	$lmS = $maxlm ? '<div class="rm-frange"><label>Min. light output <b class="rm-lm-val">0</b> lm</label><input type="range" class="rm-lm" min="0" max="' . $maxlm . '" step="100" value="0"></div>' : '';
	$wS  = $maxw ? '<div class="rm-frange"><label>Max. power <b class="rm-w-val">' . $maxw . '</b> W</label><input type="range" class="rm-w" min="0" max="' . $maxw . '" step="1" value="' . $maxw . '"></div>' : '';
	$bar = ( $lmS || $wS || $ticks )
		? '<div class="rm-catfilter"><div class="rm-catfilter-ranges">' . $lmS . $wS . '</div>'
			. ( $ticks ? '<div class="rm-catfilter-ticks"><span class="rm-facets-sub">Features</span>' . $ticks . '</div>' : '' )
			. '<button type="button" class="rm-fclear">Clear</button></div>'
		: '';

	$out  = '<div class="rm-pp-wrap rm-catwide">' . $nav . $bar
		. '<p class="rm-fcount"><b>' . (int) $total . '</b> products</p>' . $sections
		. '<p class="rm-fnone" hidden>No products match those filters. <button type="button" class="rm-fclear">Clear filters</button></p></div>';

	// Live filtering across every section.
	$out .= <<<'JS'
<script>(function(){
 var w=document.currentScript.previousElementSibling;if(!w)return;
 var cards=[].slice.call(w.querySelectorAll('.rm-fcard'));
 var lm=w.querySelector('.rm-lm'),pw=w.querySelector('.rm-w');
 var lmv=w.querySelector('.rm-lm-val'),wv=w.querySelector('.rm-w-val');
 var total=w.querySelector('.rm-fcount b'),none=w.querySelector('.rm-fnone');
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
  [].slice.call(w.querySelectorAll('.rm-catsec')).forEach(function(s){
   var vis=s.querySelectorAll('.rm-fcard:not([hidden])').length;
   s.hidden=vis===0;
   var cc=s.querySelector('.rm-catarch-count');if(cc)cc.textContent=vis;
  });
  if(total)total.textContent=shown;
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


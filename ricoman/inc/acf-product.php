<?php
/**
 * ACF-driven product page.
 *
 * The live ricoman.com products store ALL their content in ACF fields and have
 * an empty post_content. So when a product has no block content, the theme
 * renders the whole product page straight from those fields — in the new design.
 * Nothing is migrated or duplicated: the theme reads the existing fields, so
 * theme updates only restyle, never touch content.
 *
 * Reads (ACF if active, else post meta), mapped from the existing ACF group:
 *   product_subname · product_sort_description · product_code · key_features ·
 *   specification · product_gallery_image · show_variant · download_section ·
 *   download_led_or_details · download_family_datasheet · product_video
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ACF field-group sync: load (and save) field groups from the theme's acf-json
 * folder, so the product field definitions travel with the theme. Export your
 * field group from the old site (ACF → Field Groups → Export → Generate JSON,
 * or just drop the acf-json file in) and place it in /acf-json.
 */
add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = get_template_directory() . '/acf-json';
	return $paths;
} );
add_filter( 'acf/settings/save_json', function ( $path ) {
	$dir = get_template_directory() . '/acf-json';
	return is_dir( $dir ) ? $dir : $path;
} );

/** ACF-aware field getter — get_field() when ACF is active, else raw meta. */
function ricoman_pf_get( $pid, $key, $default = '' ) {
	// Live builder preview: use the unsaved draft field values.
	if ( isset( $GLOBALS['rm_pe_preview'] ) && (int) $GLOBALS['rm_pe_preview']['pid'] === (int) $pid
		&& isset( $GLOBALS['rm_pe_preview']['fields'][ $key ] ) && '' !== $GLOBALS['rm_pe_preview']['fields'][ $key ] ) {
		return $GLOBALS['rm_pe_preview']['fields'][ $key ];
	}
	if ( function_exists( 'get_field' ) ) {
		$v = get_field( $key, $pid );
		if ( null !== $v && '' !== $v ) {
			return $v;
		}
	}
	$m = get_post_meta( $pid, $key, true );
	return ( '' !== $m && null !== $m ) ? $m : $default;
}

/**
 * Turn a key-features value into a clean bullet list. The ACF field often holds
 * raw HTML (<ul class="animatable fadeInUp"><li>…</li></ul>) — pull just the item
 * text so the page never shows literal <ul>/<li> tags.
 */
function ricoman_pf_features_items( $kf, $max = 0 ) {
	$items = array();
	if ( is_array( $kf ) ) {
		foreach ( $kf as $row ) {
			$t = is_array( $row ) ? implode( ' ', array_filter( $row, 'is_scalar' ) ) : $row;
			$t = trim( wp_strip_all_tags( (string) $t ) );
			if ( '' !== $t ) {
				$items[] = $t;
			}
		}
	} else {
		$kf = (string) $kf;
		if ( false !== stripos( $kf, '<li' ) && preg_match_all( '/<li[^>]*>(.*?)<\/li>/is', $kf, $m ) ) {
			foreach ( $m[1] as $t ) {
				$t = trim( wp_strip_all_tags( html_entity_decode( $t ) ) );
				if ( '' !== $t ) {
					$items[] = $t;
				}
			}
		} else {
			foreach ( preg_split( '/\r\n|\r|\n/', wp_strip_all_tags( $kf ) ) as $line ) {
				$line = trim( ltrim( $line, "•-*\t " ) );
				if ( '' !== $line ) {
					$items[] = $line;
				}
			}
		}
	}
	if ( $max > 0 ) {
		$items = array_slice( $items, 0, $max );
	}
	return $items;
}

/** Clean bullet list from a key-features value. */
function ricoman_pf_features_list( $kf, $max = 0 ) {
	$items = ricoman_pf_features_items( $kf, $max );
	if ( ! $items ) {
		return '';
	}
	$li = '';
	foreach ( $items as $t ) {
		$li .= '<li>' . esc_html( $t ) . '</li>';
	}
	return '<ul class="rm-ul rm-pp-features">' . $li . '</ul>';
}

/** Hero feature highlights with a check icon (title:description split if present). */
function ricoman_pf_highlights( $kf, $max = 4 ) {
	$items = ricoman_pf_features_items( $kf, $max );
	if ( ! $items ) {
		return '';
	}
	$ic = '<svg class="rm-hi-ic" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.6"/><path d="M8 12.2l2.6 2.6L16 9.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	$li = '';
	foreach ( $items as $t ) {
		// "Title: description" -> bold title + text.
		if ( preg_match( '/^([^:]{3,40}):\s*(.+)$/s', $t, $m ) ) {
			$body = '<strong>' . esc_html( trim( $m[1] ) ) . ':</strong> ' . esc_html( trim( $m[2] ) );
		} else {
			$body = esc_html( $t );
		}
		$li .= '<li>' . $ic . '<span>' . $body . '</span></li>';
	}
	return '<ul class="rm-hi">' . $li . '</ul>';
}

/** "You may also like" grid — other products in the same category. */
function ricoman_pf_related( $pid, $max = 3 ) {
	$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = wp_get_post_terms( $pid, $tax, array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		return '';
	}
	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $max,
		'post__not_in'   => array( $pid ),
		'orderby'        => 'rand',
		'no_found_rows'  => true,
		'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => $terms ) ),
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$cards = '';
	while ( $q->have_posts() ) {
		$q->the_post();
		$img    = function_exists( 'ricoman_product_img' ) ? ricoman_product_img( get_the_ID() ) : get_the_post_thumbnail_url( get_the_ID(), 'large' );
		$sub    = ricoman_pf_get( get_the_ID(), 'product_subname' );
		$cards .= '<a class="rm-rel-card" href="' . esc_url( get_permalink() ) . '"><span class="rm-rel-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>'
			. '<span class="rm-rel-t">' . esc_html( get_the_title() ) . '</span>'
			. ( $sub ? '<span class="rm-rel-s">' . esc_html( $sub ) . '</span>' : '' ) . '</a>';
	}
	wp_reset_postdata();
	return '<div class="rm-section"><div class="rm-pp-wrap"><h2 class="rm-shead">You may also like</h2><div class="rm-relgrid">' . $cards . '</div></div></div>';
}

/** Collapsible accordion row (native <details>, no JS needed). */
function ricoman_pf_acc( $title, $content, $open = false ) {
	if ( '' === trim( (string) $content ) ) {
		return '';
	}
	return '<details class="rm-acc"' . ( $open ? ' open' : '' ) . '><summary class="rm-acc-h">' . esc_html( $title )
		. '<span class="rm-acc-ic" aria-hidden="true"></span></summary><div class="rm-acc-body">' . $content . '</div></details>';
}

/** Resolve an ACF image value (ID, URL, or array) to a URL. */
function ricoman_pf_imgurl( $v ) {
	$u = '';
	if ( is_numeric( $v ) ) {
		$u = wp_get_attachment_image_url( (int) $v, 'large' );
	} elseif ( is_array( $v ) ) {
		if ( ! empty( $v['url'] ) ) {
			$u = $v['url'];
		} elseif ( ! empty( $v['sizes']['large'] ) ) {
			$u = $v['sizes']['large'];
		} elseif ( ! empty( $v['ID'] ) ) {
			$u = (string) wp_get_attachment_image_url( (int) $v['ID'], 'large' );
		}
	} elseif ( is_string( $v ) ) {
		$u = $v;
	}
	$u = $u ? $u : '';
	// Borrow from the live origin if the file is missing locally (staging).
	return ( $u && function_exists( 'ricoman_img_fallback' ) ) ? ricoman_img_fallback( $u ) : $u;
}

/** Extract [name, mainImage, swatch] from one variant row of unknown sub-field names. */
function ricoman_pf_variant_row( $row ) {
	if ( ! is_array( $row ) ) {
		return null;
	}
	$vals = array_values( $row );
	if ( 1 === count( $row ) && is_array( $vals[0] ) ) {
		$row = $vals[0]; // descend a single wrapping group (e.g. "color").
	}
	$name = '';
	$img  = '';
	$sw   = '';
	foreach ( $row as $k => $v ) {
		$kl = strtolower( (string) $k );
		if ( '' === $name && is_string( $v ) && ( false !== strpos( $kl, 'name' ) || false !== strpos( $kl, 'title' ) ) ) {
			$name = $v;
		} elseif ( '' === $img && ( false !== strpos( $kl, 'main' ) || false !== strpos( $kl, 'image' ) || false !== strpos( $kl, 'photo' ) ) ) {
			$img = ricoman_pf_imgurl( $v );
		} elseif ( '' === $sw && ( false !== strpos( $kl, 'icon' ) || false !== strpos( $kl, 'swatch' ) || false !== strpos( $kl, 'colour' ) || false !== strpos( $kl, 'color' ) ) ) {
			$sw = ricoman_pf_imgurl( $v );
		}
	}
	if ( '' === $img ) {
		foreach ( $row as $v ) {
			$u = ricoman_pf_imgurl( $v );
			if ( $u ) {
				$img = $u;
				break;
			}
		}
	}
	return ( $name || $img ) ? array( $name, $img, $sw ) : null;
}

/** Gallery image URLs from product_gallery_image (array of IDs / arrays / urls). */
function ricoman_pf_gallery( $pid ) {
	$g   = ricoman_pf_get( $pid, 'product_gallery_image', array() );
	$out = array();
	if ( is_array( $g ) ) {
		foreach ( $g as $item ) {
			$u = ricoman_pf_imgurl( $item );
			if ( $u ) {
				$out[] = $u;
			}
		}
	}
	return $out;
}

/* ----------------------------------------------------- the full product page */
/**
 * Pull a product's fully-resolved data from the site's own headless API
 * (get_product_details_data) via an internal REST dispatch — no HTTP, and it
 * returns real image URLs + every section (swatches, paragraphs, zig-zag, …).
 * That endpoint is provided by the site's API plugin, so it survives the theme
 * switch. Returns null if unavailable (then we fall back to reading ACF/meta).
 */
function ricoman_pf_endpoint( $slug ) {
	if ( ! $slug || ! function_exists( 'rest_do_request' ) ) {
		return null;
	}
	$req = new WP_REST_Request( 'GET', '/wp/v2/get_product_details_data' );
	$req->set_param( 'slug', $slug );
	$res = rest_do_request( $req );
	if ( ! ( $res instanceof WP_REST_Response ) || $res->is_error() ) {
		return null;
	}
	$d = $res->get_data();
	return ( is_array( $d ) && ! empty( $d['product_title'] ) ) ? $d : null;
}

/** Build a localised product permalink from a slug. */
function ricoman_pf_permalink( $slug ) {
	$p = get_page_by_path( $slug, OBJECT, 'product' );
	return $p ? get_permalink( $p ) : home_url( '/products/' . $slug . '/' );
}

/** Render the whole product page from the resolved endpoint data, in the new design. */
function ricoman_pf_render_endpoint( $d, $pid ) {
	$e   = function ( $s ) { return esc_html( (string) $s ); };
	$cat = ( ! empty( $d['product_categories'][0]['product_cat_name'] ) ) ? $d['product_categories'][0]['product_cat_name'] : '';
	$hero = ! empty( $d['featured_image_url'] ) ? $d['featured_image_url'] : '';

	// Swatches (colour variants) — variant_name / main_image / variant_icon.
	$sw = '';
	if ( ! empty( $d['get_swatch_product_data'] ) && is_array( $d['get_swatch_product_data'] ) ) {
		foreach ( $d['get_swatch_product_data'] as $i => $v ) {
			$icon = ! empty( $v['variant_icon'] ) ? $v['variant_icon'] : '';
			$mimg = ! empty( $v['main_image'] ) ? $v['main_image'] : '';
			if ( '' === $hero && $mimg ) {
				$hero = $mimg;
			}
			$style = $icon ? 'background-image:url(' . esc_url( $icon ) . ')' : '';
			$sw   .= '<button type="button" class="rm-cv-sw' . ( 0 === $i ? ' on' : '' ) . '" data-img="' . esc_url( $mimg ) . '" style="' . $style . '" aria-label="' . esc_attr( $v['variant_name'] ) . '"><span>' . $e( $v['variant_name'] ) . '</span></button>';
		}
	}
	// Gallery thumbs.
	$thumbs = '';
	if ( ! empty( $d['product_gallery_image'] ) && is_array( $d['product_gallery_image'] ) ) {
		foreach ( $d['product_gallery_image'] as $j => $g ) {
			$u = is_array( $g ) ? ( $g['url'] ?? '' ) : $g;
			if ( $u ) {
				$thumbs .= '<button type="button" class="rm-cfg-thumb' . ( 0 === $j ? ' on' : '' ) . '" data-img="' . esc_url( $u ) . '"><img src="' . esc_url( $u ) . '" alt="" loading="lazy" onerror="this.parentNode.style.display=\'none\'"></button>';
			}
		}
	}
	if ( '' === $hero ) {
		$hero = esc_url( get_theme_file_uri( 'assets/images/ceiling.webp' ) );
	}

	// CTA buttons (from the product's own fields).
	$ldl  = ! empty( $d['lighting_design_button_link'] ) ? $d['lighting_design_button_link'] : '/lighting-design/';
	$ldt  = ! empty( $d['lighting_design_button_title'] ) ? $d['lighting_design_button_title'] : 'Request a Lighting Design';
	$trl  = ! empty( $d['trade_button_link'] ) ? $d['trade_button_link'] : '/contact/';
	$trt  = ! empty( $d['trade_button_title'] ) ? $d['trade_button_title'] : 'Apply for a Trade Account';
	$acts = '<div class="rm-cfg-acts"><a class="btn btn-solid" href="' . esc_url( home_url( '/my-project/' ) ) . '">＋ Add to My Project</a> <a class="btn btn-line-d" href="' . esc_url( $ldl ) . '">' . $e( $ldt ) . '</a> <a class="btn btn-line-d" href="' . esc_url( $trl ) . '">' . $e( $trt ) . '</a></div>';

	$spec = ! empty( $d['specification'] ) ? '<div class="rm-spechtml">' . wp_kses_post( wpautop( $d['specification'] ) ) . '</div>' : '';

	// Breadcrumb (Products › Category › Name).
	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );

	// Key features — clean bullets for the hero (full spec lives below).
	$feat = ricoman_pf_features_list( ! empty( $d['key_features'] ) ? $d['key_features'] : '' );

	// Order code + jump links.
	$code = ! empty( $d['product_code'] ) ? '<p class="rm-pp-code"><span class="rm-cfg-code">' . $e( $d['product_code'] ) . '</span></p>' : '';
	$jump = '<p class="rm-pp-jump">' . ( $spec ? '<a href="#specification">Specification</a>' : '' )
		. '<a href="#downloads">Downloads &amp; Resources</a><a href="#variants">Configure</a></p>';

	// ---- Split hero (concise; the detailed tech lives below) ----
	$out  = ( $crumb ? '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap rm-pp-crumb">' . $crumb . '</div></div>' : '' )
		. '<div class="rm-cfghero-wrap"><div class="rm-cfghero">'
		. '<div class="rm-cfg-stage"><div class="rm-cfg-viz"><img class="rm-cfg-img" src="' . esc_url( $hero ) . '" alt="' . esc_attr( $d['product_title'] ) . '"></div>'
		. ( $sw ? '<div class="rm-cv-swatches">' . $sw . '</div>' : '' )
		. ( $thumbs ? '<div class="rm-cfg-thumbs">' . $thumbs . '</div>' : '' )
		. '</div><div class="rm-cfg-panel">'
		. ( $cat ? '<p class="rm-eyebrow">' . $e( $cat ) . '</p>' : '' )
		. '<h1 class="rm-cfg-name">' . $e( $d['product_title'] ) . '</h1>'
		. ( ! empty( $d['product_subname'] ) ? '<p class="rm-cfg-desc">' . $e( $d['product_subname'] ) . '</p>' : '' )
		. ( ! empty( $d['product_sort_description'] ) ? '<p>' . $e( $d['product_sort_description'] ) . '</p>' : '' )
		. $feat . $code . $acts . $jump . '</div></div></div>';

	// Specification — moved out of the hero, into its own section below.
	if ( $spec ) {
		$out .= '<div class="rm-section" id="specification"><div class="rm-pp-wrap"><h2 class="rm-shead">Specification</h2>' . $spec . '</div></div>';
	}

	// Paragraph info → "Why specify" style band.
	if ( ! empty( $d['get_paragraph_info_section'] ) && is_array( $d['get_paragraph_info_section'] ) ) {
		$pp = '';
		foreach ( $d['get_paragraph_info_section'] as $p ) {
			$txt = is_array( $p ) ? ( $p['paragraph_content'] ?? '' ) : $p;
			if ( $txt ) {
				$pp .= '<div class="rm-sp-card"><p>' . $e( $txt ) . '</p></div>';
			}
		}
		if ( $pp ) {
			$out .= '<div class="rm-sp"><div class="rm-sp-inner"><p class="rm-eyebrow rm-sp-kick">Why specify ' . $e( $d['product_title'] ) . '</p><div class="rm-sp-grid">' . $pp . '</div></div></div>';
		}
	}

	// Zig-zag (image/video + text + button), up to two boxes.
	$z = isset( $d['product_image_video_sec_data'] ) && is_array( $d['product_image_video_sec_data'] ) ? $d['product_image_video_sec_data'] : array();
	$zz = '';
	foreach ( array( 'first', 'second' ) as $bi => $box ) {
		$im = ! empty( $z[ 'upload_' . $box . '_media_image' ] ) ? $z[ 'upload_' . $box . '_media_image' ] : '';
		$vd = ! empty( $z[ 'upload_' . $box . '_media_video' ] ) ? $z[ 'upload_' . $box . '_media_video' ] : '';
		$bc = ! empty( $z[ $box . '_box_content' ] ) ? $z[ $box . '_box_content' ] : '';
		if ( ! $im && ! $vd && ! $bc ) {
			continue;
		}
		$media = $vd ? '<video controls playsinline src="' . esc_url( is_array( $vd ) ? ( $vd['url'] ?? '' ) : $vd ) . '"></video>' : ( $im ? '<img src="' . esc_url( is_array( $im ) ? ( $im['url'] ?? '' ) : $im ) . '" alt="" loading="lazy">' : '' );
		$bt    = ! empty( $z[ $box . '_box_button_title' ] ) ? '<a class="btn btn-line-d" href="' . esc_url( $z[ $box . '_box_button_link' ] ?? '#' ) . '">' . $e( $z[ $box . '_box_button_title' ] ) . '</a>' : '';
		$zz   .= '<div class="rm-zz-row' . ( 0 === $bi % 2 ? '' : ' rev' ) . '"><div class="rm-zz-media">' . $media . '</div><div class="rm-zz-body">' . ( $bc ? wp_kses_post( wpautop( $bc ) ) : '' ) . $bt . '</div></div>';
	}
	if ( $zz ) {
		$out .= '<div class="rm-section"><div class="rm-zz">' . $zz . '</div></div>';
	}

	// Order codes & variants — RICOBOT live (this is what replaces the CSV).
	// Only shown when the product is linked to a RICOBOT family in Product Builder.
	if ( '' !== (string) get_post_meta( $pid, '_ricoman_family', true ) ) {
		$out .= '<div class="rm-section" id="variants"><div class="rm-pp-wrap"><h2 class="rm-shead">Configure &amp; order codes</h2>' . do_shortcode( '[ricoman_family]' ) . '</div></div>';
	}

	// Downloads.
	if ( ! empty( $d['download_section'] ) && is_array( $d['download_section'] ) ) {
		$dl = '';
		foreach ( $d['download_section'] as $row ) {
			$file = is_array( $row ) ? ( $row['download-file'] ?? '' ) : '';
			$dt   = is_array( $row ) ? ( $row['download-title'] ?? 'Download' ) : 'Download';
			if ( $file ) {
				$dl .= '<li><a href="' . esc_url( $file ) . '" target="_blank" rel="noopener">' . $e( $dt ) . ' &darr;</a></li>';
			}
		}
		if ( $dl ) {
			$out .= '<div class="rm-section" id="downloads"><div class="rm-pp-wrap"><div class="rm-prod-downloads"><h3 class="rm-shead">Downloads &amp; Resources</h3><ul>' . $dl . '</ul></div></div></div>';
		}
	}

	// Related products.
	if ( ! empty( $d['related_products'] ) && is_array( $d['related_products'] ) ) {
		$rc = '';
		foreach ( array_slice( $d['related_products'], 0, 3 ) as $rp ) {
			$img  = ! empty( $rp['image'] ) ? $rp['image'] : '';
			$href = ricoman_pf_permalink( $rp['slug'] ?? '' );
			$rc  .= '<div class="wp-block-column"><div class="wp-block-group rm-card"><figure class="wp-block-image size-large"><a href="' . esc_url( $href ) . '"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $rp['title'] ?? '' ) . '" loading="lazy"></a></figure><h3 class="wp-block-heading"><a href="' . esc_url( $href ) . '">' . $e( $rp['title'] ?? '' ) . '</a></h3>' . ( ! empty( $rp['sub_name'] ) ? '<p class="has-muted-color has-text-color has-small-font-size">' . $e( $rp['sub_name'] ) . '</p>' : '' ) . '</div></div>';
		}
		if ( $rc ) {
			$out .= '<div class="rm-section"><div class="rm-pp-wrap"><p class="rm-eyebrow">More from the range</p><h2 class="rm-shead">You may also like</h2><div class="wp-block-columns">' . $rc . '</div></div></div>';
		}
	}

	$out .= '<script>(function(){var w=document.currentScript.previousElementSibling;if(!w)return;var im=w.querySelector(".rm-cfg-img");function bind(sel){w.querySelectorAll(sel).forEach(function(b){b.addEventListener("click",function(){if(b.dataset.img&&im){im.src=b.dataset.img;}var p=b.parentNode;p.querySelectorAll(sel).forEach(function(x){x.classList.remove("on");});b.classList.add("on");});});}bind(".rm-cv-sw");bind(".rm-cfg-thumb");w.querySelectorAll(".rm-gtab").forEach(function(t){t.addEventListener("click",function(){if(t.disabled)return;w.querySelectorAll(".rm-gtab").forEach(function(x){x.classList.remove("on");});t.classList.add("on");var tab=t.dataset.tab;w.querySelectorAll(".rm-gthumbs .rm-cfg-thumb").forEach(function(th){th.style.display=(tab==="all"||th.dataset.tab===tab)?"":"none";});});});var lb=w.querySelector(".rm-lightbox"),lbi=lb?lb.querySelector(".rm-lightbox-img"):null;if(lb&&lbi&&im){im.addEventListener("click",function(){lbi.src=im.src;lb.hidden=false;document.body.style.overflow="hidden";});function cl(){lb.hidden=true;document.body.style.overflow="";}lb.addEventListener("click",function(e){if(e.target===lb||e.target.classList.contains("rm-lightbox-x"))cl();});document.addEventListener("keydown",function(e){if(e.key==="Escape")cl();});}})();</script>';
	return $out;
}

/** Dimension diagram images (ACF `dimension_diagrams` repeater, sub-field `picture`). */
function ricoman_pf_dimension_diagrams( $pid ) {
	$imgs = array();
	$v    = ricoman_pf_get( $pid, 'dimension_diagrams' );
	if ( is_array( $v ) ) {
		foreach ( $v as $row ) {
			$pic = is_array( $row ) ? ( isset( $row['picture'] ) ? $row['picture'] : ( isset( $row['image'] ) ? $row['image'] : '' ) ) : $row;
			$u   = ricoman_pf_imgurl( $pic );
			if ( $u ) {
				$imgs[] = $u;
			}
		}
	}
	return $imgs;
}

/** Colour/size variants (Product Variation By Color): name + main image + icon. */
function ricoman_pf_color_variants( $pid ) {
	$rows = array();
	foreach ( array( 'product_variation_by_color', 'variation_by_color', 'product_color_variation', 'get_swatch_product_data', 'color_variant', 'product_variant_color', 'show_swatch_product_data' ) as $fname ) {
		$v = ricoman_pf_get( $pid, $fname );
		if ( ! is_array( $v ) || ! $v ) {
			continue;
		}
		foreach ( $v as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = '';
			$main = '';
			$icon = '';
			foreach ( $row as $k => $val ) {
				$lk = strtolower( (string) $k );
				if ( false !== strpos( $lk, 'icon' ) ) {
					$icon = ricoman_pf_imgurl( $val );
				} elseif ( false !== strpos( $lk, 'main' ) || false !== strpos( $lk, 'image' ) ) {
					$main = ricoman_pf_imgurl( $val );
				} elseif ( false !== strpos( $lk, 'name' ) && is_scalar( $val ) ) {
					$name = (string) $val;
				}
			}
			if ( $name || $main || $icon ) {
				$rows[] = array( 'name' => $name, 'main' => $main, 'icon' => $icon );
			}
		}
		if ( $rows ) {
			return $rows;
		}
	}
	return $rows;
}

/** Paragraph Info Section -> array of marketing highlight strings. */
function ricoman_pf_paragraphs( $pid ) {
	foreach ( array( 'paragraph_info_section', 'get_paragraph_info_section', 'paragraph_section', 'product_paragraph_info', 'product_image_video_paragraph' ) as $fname ) {
		$v = ricoman_pf_get( $pid, $fname );
		if ( ! is_array( $v ) || ! $v ) {
			continue;
		}
		$out = array();
		foreach ( $v as $row ) {
			if ( is_array( $row ) ) {
				foreach ( $row as $k => $val ) {
					if ( false !== strpos( strtolower( (string) $k ), 'content' ) && is_scalar( $val ) && '' !== trim( (string) $val ) ) {
						$out[] = trim( (string) $val );
					}
				}
			} elseif ( is_scalar( $row ) && '' !== trim( (string) $row ) ) {
				$out[] = trim( (string) $row );
			}
		}
		if ( $out ) {
			return $out;
		}
	}
	return array();
}

/** Resolve an ACF file value (ID / array / URL) to a URL. */
function ricoman_pf_fileurl( $v ) {
	$u = '';
	if ( is_numeric( $v ) ) {
		$u = (string) wp_get_attachment_url( (int) $v );
	} elseif ( is_array( $v ) ) {
		$u = isset( $v['url'] ) ? (string) $v['url'] : '';
	} elseif ( is_string( $v ) ) {
		$u = $v;
	}
	return ( $u && function_exists( 'ricoman_img_fallback' ) ) ? ricoman_img_fallback( $u ) : $u;
}

/**
 * Resolve a variant's lumens from whichever field/taxonomy the migrated data
 * used, so the Configure table isn't blank when it's stored under an alt key.
 */
function ricoman_variant_lumens( $vid ) {
	foreach ( array( 'lumens', 'lumen', 'lumen_output', 'lumens_output', 'total_lumens', 'output_lumens', 'lumen_value', 'lm' ) as $k ) {
		$v = ricoman_pf_get( $vid, $k );
		if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
			return (string) $v;
		}
	}
	foreach ( array( 'lumen', 'lumens', 'lumen-output', 'lumen_output', 'wattage', 'watt' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$terms = wp_get_post_terms( $vid, $tax, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				return implode( ', ', $terms );
			}
		}
	}
	return '';
}

/**
 * Configure / order-codes table, built from the linked `variant-product` posts
 * (ACF `parent_product` == this product). This is the staging-data equivalent of
 * the old site's Configure Your Product table.
 */
function ricoman_pf_variant_table( $pid ) {
	if ( ! post_type_exists( 'variant-product' ) ) {
		return '';
	}
	$q = new WP_Query( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'meta_query'     => array( array( 'key' => 'parent_product', 'value' => (string) $pid ) ),
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$datasheet = ricoman_pf_fileurl( ricoman_pf_get( $pid, 'download_family_datasheet' ) );
	$rows      = '';
	while ( $q->have_posts() ) {
		$q->the_post();
		$vid  = get_the_ID();
		$code = ricoman_pf_get( $vid, 'part_code' );
		if ( '' === (string) $code ) {
			$code = ricoman_pf_get( $vid, 'order_code' );
		}
		$desc = ricoman_pf_get( $vid, 'product_sort_description' );
		if ( '' === trim( (string) $desc ) ) {
			$desc = get_the_title();
		}
		$lm   = ricoman_variant_lumens( $vid );
		$dim  = ricoman_pf_get( $vid, 'dimensions' );
		$ldt  = ricoman_pf_fileurl( ricoman_pf_get( $vid, 'download_led' ) );
		$img  = ricoman_pf_imgurl( ricoman_pf_get( $vid, 'product_main_image' ) );
		if ( ! $img ) {
			$img = ricoman_pf_imgurl( ricoman_pf_get( $vid, 'product_gallery_image' ) );
		}
		$thumb = $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '';
		// Datasheet is generated on the fly from this line's own data.
		$ds_url = function_exists( 'ricoman_variant_datasheet_url' ) ? ricoman_variant_datasheet_url( $vid, $pid ) : $datasheet;
		$rows .= '<tr>'
			. '<td class="vt-thumb">' . $thumb . '</td>'
			. '<td class="vt-code">' . esc_html( $code ) . '</td>'
			. '<td class="vt-desc">' . esc_html( wp_strip_all_tags( (string) $desc ) ) . '</td>'
			. '<td class="vt-lm">' . esc_html( $lm ) . '</td>'
			. '<td class="vt-dim">' . esc_html( $dim ) . '</td>'
			. '<td class="vt-dl">' . ( $ldt ? '<a href="' . esc_url( $ldt ) . '" target="_blank" rel="noopener" aria-label="LDT file">LDT ↓</a>' : '—' ) . '</td>'
			. '<td class="vt-dl"><a href="' . esc_url( $ds_url ) . '" target="_blank" rel="noopener" aria-label="Datasheet">Datasheet ↓</a></td>'
			. '</tr>';
	}
	wp_reset_postdata();
	return '<div class="rm-vptable-wrap"><table class="rm-vptable"><thead><tr>'
		. '<th></th><th>Part Code</th><th>Description</th><th>Lumens</th><th>Dimensions</th><th>LDT</th><th>Datasheet</th>'
		. '</tr></thead><tbody>' . $rows . '</tbody></table></div>';
}

/** In-situ images for a product — its own In-situ gallery + related projects' galleries. */
function ricoman_pf_insitu_images( $pid ) {
	$out = array();
	// 1. Photos tagged directly on the product (insitu_gallery field).
	$own = ricoman_pf_get( $pid, 'insitu_gallery' );
	if ( is_array( $own ) ) {
		foreach ( $own as $g ) {
			$u = ricoman_pf_imgurl( $g );
			if ( $u ) {
				$out[] = $u;
			}
		}
	}
	// 2. Photos pulled from linked projects' galleries (Option A).
	$rp = ricoman_pf_get( $pid, 'related_projects' );
	$ids = array();
	if ( is_array( $rp ) ) {
		foreach ( $rp as $v ) {
			if ( is_numeric( $v ) ) {
				$ids[] = (int) $v;
			} elseif ( is_string( $v ) && '' !== $v ) {
				$p = get_page_by_path( $v, OBJECT, 'project' );
				if ( $p ) {
					$ids[] = $p->ID;
				}
			}
		}
	}
	foreach ( $ids as $proj ) {
		$im = ricoman_pf_imgurl( get_post_meta( $proj, 'project_image', true ) );
		if ( $im ) {
			$out[] = $im;
		}
		$gal = get_post_meta( $proj, 'project_gallery', true );
		if ( is_array( $gal ) ) {
			foreach ( $gal as $g ) {
				$u = ricoman_pf_imgurl( $g );
				if ( $u ) {
					$out[] = $u;
				}
			}
		}
	}
	return array_values( array_unique( array_filter( $out ) ) );
}

/** Hero gallery block: main image (with finish swatches + order code chip), All/Studio/In-situ tabs, thumbnails. */
function ricoman_pf_gallery_block( $pid, $title, $code, $sw_html ) {
	$studio = ricoman_pf_gallery( $pid ); // product_gallery_image.
	foreach ( ricoman_pf_color_variants( $pid ) as $cv ) {
		if ( $cv['main'] ) {
			$studio[] = $cv['main'];
		}
	}
	$studio = array_values( array_unique( array_filter( (array) $studio ) ) );
	$insitu = ricoman_pf_insitu_images( $pid );
	$main   = $studio ? $studio[0] : ( $insitu ? $insitu[0] : esc_url( get_theme_file_uri( 'assets/images/ceiling.webp' ) ) );

	$thumb = function ( $u, $tab, $on ) {
		return '<button type="button" class="rm-cfg-thumb' . ( $on ? ' on' : '' ) . '" data-tab="' . esc_attr( $tab ) . '" data-img="' . esc_url( $u ) . '"><img src="' . esc_url( $u ) . '" alt="" loading="lazy" onerror="this.parentNode.style.display=\'none\'"></button>';
	};
	$thumbs = '';
	$first  = true;
	foreach ( $studio as $u ) {
		$thumbs .= $thumb( $u, 'studio', $first );
		$first   = false;
	}
	foreach ( $insitu as $u ) {
		$thumbs .= $thumb( $u, 'insitu', false );
	}

	// Always show the All / Studio / In-situ buttons; grey out any with no images.
	$tabs = '<button type="button" class="rm-gtab on" data-tab="all">All</button>'
		. '<button type="button" class="rm-gtab' . ( $studio ? '' : ' rm-gtab--off' ) . '" data-tab="studio"' . ( $studio ? '' : ' disabled' ) . '>Studio</button>'
		. '<button type="button" class="rm-gtab' . ( $insitu ? '' : ' rm-gtab--off' ) . '" data-tab="insitu"' . ( $insitu ? '' : ' disabled' ) . '>In-situ</button>';

	return '<div class="rm-cfg-stage rm-pdp-gallery">'
		. '<div class="rm-cfg-viz"><img class="rm-cfg-img rm-zoomable" src="' . esc_url( $main ) . '" alt="' . esc_attr( $title ) . '">'
		. ( $sw_html ? '<div class="rm-cv-swatches rm-pdp-sw">' . $sw_html . '</div>' : '' )
		. '<span class="rm-zoom-hint" aria-hidden="true">⤢</span>'
		. '</div>'
		. ( $thumbs ? '<div class="rm-gtabs">' . $tabs . '</div>' : '' )
		. ( $thumbs ? '<div class="rm-cfg-thumbs rm-gthumbs">' . $thumbs . '</div>' : '' )
		. '<div class="rm-lightbox" hidden><button type="button" class="rm-lightbox-x" aria-label="Close">&times;</button><img class="rm-lightbox-img" src="" alt=""></div>'
		. '</div>';
}

/**
 * The shared gallery / swatch / thumbnail-tab / lightbox script. Scoped to the
 * hero wrapper via document.currentScript.previousElementSibling, so it works
 * whether the hero is rendered as part of the whole page or dropped in on its
 * own as a composable section block.
 */
function ricoman_pf_gallery_js() {
	// Gallery interactions now live in the enqueued assets/js/product-gallery.js
	// (delegated on document, so it can't be broken by markup position or load
	// order). Kept as a no-op so existing callers stay valid.
	return '';
}

/**
 * Build every product-page section from the ACF/meta fields, returned as a map
 * of named HTML fragments:
 *   hero · specs · configure · accessories · related · cta
 *
 * This is the single source of truth for the product layout. The whole-page
 * renderer simply concatenates the fragments in order; the composable section
 * blocks ([ricoman_section_hero] etc.) each emit just one fragment, so an admin
 * can reorder them and drop their own patterns into the gaps. Result is cached
 * per-product per-request so multiple section blocks don't rebuild it.
 */
function ricoman_pf_sections( $pid ) {
	static $cache = array();
	if ( isset( $cache[ $pid ] ) ) {
		return $cache[ $pid ];
	}
	$title   = get_the_title( $pid );
	$subname = ricoman_pf_get( $pid, 'product_subname' );
	$sortd   = ricoman_pf_get( $pid, 'product_sort_description' );
	$code    = ricoman_pf_get( $pid, 'product_code' );
	$terms   = get_the_term_list( $pid, 'product-cat', '', ' · ' );
	$hero    = get_the_post_thumbnail_url( $pid, 'large' );

	// Colour/size variants (Product Variation By Color) — these drive the swatches
	// AND the image switching (click Ø600 / Black -> main image updates).
	$cvars   = ricoman_pf_color_variants( $pid );
	$gallery = ricoman_pf_gallery( $pid );
	if ( ! $hero ) {
		$hero = ( $cvars && $cvars[0]['main'] ) ? $cvars[0]['main'] : ( $gallery ? $gallery[0] : esc_url( get_theme_file_uri( 'assets/images/ceiling.webp' ) ) );
	} elseif ( $cvars && $cvars[0]['main'] ) {
		$hero = $cvars[0]['main'];
	}

	// Swatches with per-variant image (icon shown, main image swapped on click).
	$sw = '';
	foreach ( $cvars as $i => $cv ) {
		$icon  = $cv['icon'] ? $cv['icon'] : $cv['main'];
		$style = $icon ? 'background-image:url(' . esc_url( $icon ) . ')' : '';
		$sw   .= '<button type="button" class="rm-cv-sw' . ( 0 === $i ? ' on' : '' ) . '" data-img="' . esc_url( $cv['main'] ) . '" style="' . $style . '" aria-label="' . esc_attr( $cv['name'] ) . '"><span>' . esc_html( $cv['name'] ) . '</span></button>';
	}
	$thumbs = '';
	foreach ( $gallery as $j => $g ) {
		$thumbs .= '<button type="button" class="rm-cfg-thumb' . ( 0 === $j ? ' on' : '' ) . '" data-img="' . esc_url( $g ) . '"><img src="' . esc_url( $g ) . '" alt="" loading="lazy" onerror="this.parentNode.style.display=\'none\'"></button>';
	}

	// Specification (HTML with <strong> headings + · lines) — render faithfully.
	$spec = (string) ricoman_pf_get( $pid, 'specification' );
	$spec = $spec ? '<div class="rm-spechtml">' . wp_kses_post( wpautop( $spec ) ) . '</div>' : '';

	// Key features — clean bullets (handles ACF fields that hold raw <ul>/<li> HTML).
	$feat = ricoman_pf_features_list( ricoman_pf_get( $pid, 'key_features' ) );

	// Downloads (download_section rows + brochure + family datasheet).
	$dls = ricoman_pf_get( $pid, 'download_section', array() );
	$dl  = '';
	if ( is_array( $dls ) ) {
		foreach ( $dls as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			$dtitle = '';
			$dfile  = '';
			foreach ( $d as $k => $v ) {
				if ( false !== strpos( strtolower( (string) $k ), 'title' ) ) {
					$dtitle = $v;
				} elseif ( false !== strpos( strtolower( (string) $k ), 'file' ) ) {
					$dfile = is_numeric( $v ) ? wp_get_attachment_url( (int) $v ) : ( is_array( $v ) && ! empty( $v['url'] ) ? $v['url'] : $v );
				}
			}
			if ( $dfile ) {
				$dl .= '<li><a href="' . esc_url( $dfile ) . '" target="_blank" rel="noopener">' . esc_html( $dtitle ? $dtitle : 'Download' ) . ' &darr;</a></li>';
			}
		}
	}
	foreach ( array( 'download_led_or_details' => 'Brochure', 'download_family_datasheet' => 'Family datasheet' ) as $fk => $flabel ) {
		$fv = ricoman_pf_get( $pid, $fk );
		$fu = is_numeric( $fv ) ? wp_get_attachment_url( (int) $fv ) : ( is_array( $fv ) && ! empty( $fv['url'] ) ? $fv['url'] : $fv );
		if ( $fu ) {
			$dl .= '<li><a href="' . esc_url( $fu ) . '" target="_blank" rel="noopener">' . esc_html( $flabel ) . ' &darr;</a></li>';
		}
	}
	$downloads = $dl ? '<div class="rm-prod-downloads"><h3 class="rm-shead">Downloads</h3><ul>' . $dl . '</ul></div>' : '';

	// CTA buttons (LD + trade) from the structured fields, with fallbacks.
	$ld    = ricoman_pf_get( $pid, '_ricoman_ld_btn', 'Request a Lighting Design' );
	$ldu   = ricoman_pf_get( $pid, '_ricoman_ld_url', '/lighting-design/' );
	$tr    = ricoman_pf_get( $pid, '_ricoman_trade_btn', 'Apply for a Trade Account' );
	$tru   = ricoman_pf_get( $pid, '_ricoman_trade_url', '/contact/' );
	$enq   = esc_url( home_url( '/my-project/' ) );
	$acts  = '<div class="rm-cfg-acts"><a class="btn btn-solid" href="' . $enq . '">＋ Add to My Project</a>';
	$acts .= ' <a class="btn btn-line-d" href="' . esc_url( $ldu ) . '">' . esc_html( $ld ) . '</a>';
	$acts .= ' <a class="btn btn-line-d" href="' . esc_url( $tru ) . '">' . esc_html( $tr ) . '</a></div>';

	$crumb = do_shortcode( '[ricoman_breadcrumbs]' );

	// Hero highlights — prefer the Paragraph Info Section (rich "Title: desc"
	// items); fall back to the first key features. Full key features go in the
	// Features accordion.
	$kfraw      = ricoman_pf_get( $pid, 'key_features' );
	$paras      = ricoman_pf_paragraphs( $pid );
	$highlights = $paras ? ricoman_pf_highlights( $paras, 4 ) : ricoman_pf_highlights( $kfraw, 4 );
	$feat_full  = ricoman_pf_features_list( $kfraw );

	// Dimensions accordion — diagram images (ACF dimension_diagrams) + any text.
	$dimimgs = ricoman_pf_dimension_diagrams( $pid );
	$dims    = '';
	if ( $dimimgs ) {
		$dims = '<div class="rm-dimgrid">';
		foreach ( $dimimgs as $du ) {
			$dims .= '<img src="' . esc_url( $du ) . '" alt="' . esc_attr( $title . ' dimensions' ) . '" loading="lazy">';
		}
		$dims .= '</div>';
	}
	$dimtext = (string) ricoman_pf_get( $pid, 'product_dimension' );
	if ( '' === $dimtext ) {
		$dimtext = (string) ricoman_pf_get( $pid, 'dimensions' );
	}
	if ( '' !== $dimtext ) {
		$dims .= '<div class="rm-spechtml">' . wp_kses_post( wpautop( $dimtext ) ) . '</div>';
	}
	$has_fam = '' !== (string) ricoman_pf_get( $pid, '_ricoman_family' );

	$jump = '<p class="rm-pp-jump">' . ( $spec ? '<a href="#specification">Specification</a>' : '' )
		. ( $dl ? '<a href="#downloads">Downloads and Resources</a>' : '' )
		. ( $has_fam ? '<a href="#variants">Configure Product</a>' : '' ) . '</p>';

	$desc = $sortd ? $sortd : $subname;

	// ---- Hero: tabbed gallery (All/Studio/In-situ) + main image | panel ----
	// The hero carries the gallery script so it works even when dropped in alone.
	$hero_html = ( $crumb ? '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap rm-pp-crumb">' . $crumb . '</div></div>' : '' )
		. '<div class="rm-cfghero-wrap"><div class="rm-cfghero rm-pdp">'
		. ricoman_pf_gallery_block( $pid, $title, $code, $sw )
		. '<div class="rm-cfg-panel">'
		. '<h1 class="rm-cfg-name">' . esc_html( $title ) . '</h1>'
		. ( $desc ? '<p class="rm-cfg-desc">' . esc_html( $desc ) . '</p>' : '' )
		. $highlights
		. '<div class="rm-cfg-acts"><a class="btn btn-solid" href="' . esc_url( $ldu ) . '">' . esc_html( $ld ) . ' →</a>'
		. ' <a class="btn btn-line-d" href="' . esc_url( $tru ) . '">' . esc_html( $tr ) . ' →</a></div>'
		. $jump
		. '</div></div></div>'
		. ricoman_pf_gallery_js();

	// ---- Accordions: Specification / Dimensions / Features / Downloads (closed) ----
	$acc  = ricoman_pf_acc( 'Specification', $spec, false );
	$acc .= ricoman_pf_acc( 'Dimensions', $dims );
	$acc .= ricoman_pf_acc( 'Features', $feat_full );
	$acc .= ricoman_pf_acc( 'Downloads and Resources', $dl ? '<ul class="rm-acc-dl">' . $dl . '</ul>' : '' );
	$acc_sec = $acc ? '<div class="rm-section" id="specification"><div class="rm-pp-wrap"><div class="rm-accs">' . $acc . '</div></div></div>' : '';

	// ---- Configure Your Product ----
	// Prefer the linked variant-product rows (migrated staging data); fall back to
	// the RICOBOT family table when the product is linked to a RICOBOT family.
	$vtable    = ricoman_pf_variant_table( $pid );
	$var_inner = $vtable ? $vtable : ( $has_fam ? do_shortcode( '[ricoman_family]' ) : '' );
	$var_sec   = $var_inner
		? '<div class="rm-section" id="variants"><div class="rm-pp-wrap"><h2 class="rm-shead">Configure Your Product</h2>' . $var_inner . '</div></div>'
		: '';

	// ---- Accessories (RICOBOT live, when available) ----
	$acc_live  = do_shortcode( '[ricoman_accessories_live]' );
	$acc_block = ( $acc_live && false === strpos( $acc_live, 'rm-config-note' ) )
		? '<div class="rm-section"><div class="rm-pp-wrap">' . $acc_live . '</div></div>' : '';

	// ---- You may also like ----
	$related = ricoman_pf_related( $pid );

	$cta = '<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:46vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( get_theme_file_uri( 'assets/images/office1.webp' ) ) . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><h2 class="wp-block-heading has-text-align-center" style="text-align:center">Specify this product</h2><p class="has-text-align-center" style="text-align:center">Add it to your project or request a free lighting scheme.</p><div class="wp-block-buttons is-content-justification-center" style="display:flex;justify-content:center;gap:10px"><a class="btn btn-line" href="' . $enq . '">Add to My Project</a> <a class="btn btn-solid" href="' . esc_url( $ldu ) . '">' . esc_html( $ld ) . '</a></div></div></div>';

	$cache[ $pid ] = array(
		'hero'        => $hero_html,
		'specs'       => $acc_sec,
		'configure'   => $var_sec,
		'accessories' => $acc_block,
		'related'     => $related,
		'cta'         => $cta,
	);
	return $cache[ $pid ];
}

add_shortcode( 'ricoman_product_page', function () {
	$pid = get_the_ID();
	if ( ! $pid ) {
		return '';
	}
	// Primary: render from the site's resolved product API (marketing content),
	// with RICOBOT supplying the live variants/specs.
	$d = ricoman_pf_endpoint( get_post_field( 'post_name', $pid ) );
	if ( $d ) {
		return ricoman_pf_render_endpoint( $d, $pid );
	}
	// Fallback: read ACF/meta fields directly, assembled from the section map.
	$s = ricoman_pf_sections( $pid );
	return $s['hero'] . $s['specs'] . $s['configure'] . $s['accessories'] . $s['related'] . $s['cta'];
} );

/* When a product has no block content (the ACF products), render the field page. */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( ! empty( $GLOBALS['rm_pe_preview'] ) ) {
		return $content; // the builder preview filter already rendered the page.
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		return $content; // has real (block) content — leave it.
	}
	// A product following a template / with custom layout renders that layout.
	$pid = get_the_ID();
	if ( function_exists( 'ricoman_pe_has_managed_layout' ) && ricoman_pe_has_managed_layout( $pid ) ) {
		return ricoman_pe_render_layout( $pid );
	}
	return do_shortcode( '[ricoman_product_page]' );
}, 9 );

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
	if ( function_exists( 'get_field' ) ) {
		$v = get_field( $key, $pid );
		if ( null !== $v && '' !== $v ) {
			return $v;
		}
	}
	$m = get_post_meta( $pid, $key, true );
	return ( '' !== $m && null !== $m ) ? $m : $default;
}

/** Resolve an ACF image value (ID, URL, or array) to a URL. */
function ricoman_pf_imgurl( $v ) {
	if ( is_numeric( $v ) ) {
		$u = wp_get_attachment_image_url( (int) $v, 'large' );
		return $u ? $u : '';
	}
	if ( is_array( $v ) ) {
		if ( ! empty( $v['url'] ) ) {
			return $v['url'];
		}
		if ( ! empty( $v['sizes']['large'] ) ) {
			return $v['sizes']['large'];
		}
		if ( ! empty( $v['ID'] ) ) {
			return (string) wp_get_attachment_image_url( (int) $v['ID'], 'large' );
		}
	}
	return is_string( $v ) ? $v : '';
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

	// Key features — concise bullets for the hero (full spec lives below).
	$feat = '';
	$kf   = ! empty( $d['key_features'] ) ? $d['key_features'] : '';
	if ( is_array( $kf ) ) {
		$li = '';
		foreach ( $kf as $row ) {
			$t = is_array( $row ) ? implode( ' ', array_filter( $row, 'is_scalar' ) ) : $row;
			if ( $t ) {
				$li .= '<li>' . $e( $t ) . '</li>';
			}
		}
		if ( $li ) {
			$feat = '<ul class="rm-ul rm-pp-features">' . $li . '</ul>';
		}
	} elseif ( $kf ) {
		$feat = '<div class="rm-pp-features rm-spechtml">' . wp_kses_post( wpautop( $kf ) ) . '</div>';
	}

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
		. $code . $feat . $acts . $jump . '</div></div></div>';

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
	$out .= '<div class="rm-section" id="variants"><div class="rm-pp-wrap"><h2 class="rm-shead">Configure &amp; order codes</h2>' . do_shortcode( '[ricoman_family]' ) . '</div></div>';

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

	$out .= '<script>(function(){var w=document.currentScript.previousElementSibling;if(!w)return;var im=w.querySelector(".rm-cfg-img");function bind(sel){w.querySelectorAll(sel).forEach(function(b){b.addEventListener("click",function(){if(b.dataset.img&&im){im.src=b.dataset.img;}var p=b.parentNode;p.querySelectorAll(sel).forEach(function(x){x.classList.remove("on");});b.classList.add("on");});});}bind(".rm-cv-sw");bind(".rm-cfg-thumb");})();</script>';
	return $out;
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
	// Fallback: read ACF/meta fields directly.
	$title   = get_the_title( $pid );
	$subname = ricoman_pf_get( $pid, 'product_subname' );
	$sortd   = ricoman_pf_get( $pid, 'product_sort_description' );
	$code    = ricoman_pf_get( $pid, 'product_code' );
	$terms   = get_the_term_list( $pid, 'product-cat', '', ' · ' );
	$hero    = get_the_post_thumbnail_url( $pid, 'large' );

	// Variants (swatches) + gallery (thumbs).
	$variants = ricoman_pf_get( $pid, 'show_variant', array() );
	$vrows    = array();
	if ( is_array( $variants ) ) {
		foreach ( $variants as $v ) {
			$r = ricoman_pf_variant_row( $v );
			if ( $r ) {
				$vrows[] = $r;
			}
		}
	}
	$gallery = ricoman_pf_gallery( $pid );
	if ( ! $hero ) {
		$hero = ( $vrows && $vrows[0][1] ) ? $vrows[0][1] : ( $gallery ? $gallery[0] : esc_url( get_theme_file_uri( 'assets/images/ceiling.webp' ) ) );
	}

	// Swatches.
	$sw = '';
	foreach ( $vrows as $i => $r ) {
		if ( '' === $r[1] && '' === $r[2] ) {
			continue;
		}
		$style = ( $r[2] && '#' === substr( $r[2], 0, 1 ) ) ? 'background:' . esc_attr( $r[2] ) : ( $r[2] ? 'background-image:url(' . esc_url( $r[2] ) . ')' : '' );
		$sw   .= '<button type="button" class="rm-cv-sw' . ( 0 === $i ? ' on' : '' ) . '" data-img="' . esc_url( $r[1] ) . '" style="' . $style . '" aria-label="' . esc_attr( $r[0] ) . '"><span>' . esc_html( $r[0] ) . '</span></button>';
	}
	$thumbs = '';
	foreach ( $gallery as $j => $g ) {
		$thumbs .= '<button type="button" class="rm-cfg-thumb' . ( 0 === $j ? ' on' : '' ) . '" data-img="' . esc_url( $g ) . '"><img src="' . esc_url( $g ) . '" alt="" loading="lazy" onerror="this.parentNode.style.display=\'none\'"></button>';
	}

	// Specification (HTML with <strong> headings + · lines) — render faithfully.
	$spec = (string) ricoman_pf_get( $pid, 'specification' );
	$spec = $spec ? '<div class="rm-spechtml">' . wp_kses_post( wpautop( $spec ) ) . '</div>' : '';

	// Key features (bullets).
	$kf   = (string) ricoman_pf_get( $pid, 'key_features' );
	$kfli = '';
	foreach ( preg_split( '/\r\n|\r|\n/', $kf ) as $line ) {
		$line = trim( ltrim( $line, '•-* ' ) );
		if ( '' !== $line ) {
			$kfli .= '<li>' . esc_html( $line ) . '</li>';
		}
	}
	$features = $kfli ? '<div class="rm-section rm-soft"><div class="rm-pp-wrap"><p class="rm-eyebrow">Key features</p><ul class="rm-ul rm-pp-features">' . $kfli . '</ul></div></div>' : '';

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
	$jump  = '<p class="rm-pp-jump">' . ( $spec ? '<a href="#specification">Specification</a>' : '' )
		. ( $dl ? '<a href="#downloads">Downloads &amp; Resources</a>' : '' ) . '<a href="#variants">Configure</a></p>';

	// ---- Split hero (concise; spec / downloads / variants live below) ----
	$hero_html = ( $crumb ? '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap rm-pp-crumb">' . $crumb . '</div></div>' : '' )
		. '<div class="rm-cfghero-wrap"><div class="rm-cfghero">'
		. '<div class="rm-cfg-stage"><div class="rm-cfg-viz"><img class="rm-cfg-img" src="' . esc_url( $hero ) . '" alt="' . esc_attr( $title ) . '"></div>'
		. ( $sw ? '<div class="rm-cv-swatches">' . $sw . '</div>' : '' )
		. ( $thumbs ? '<div class="rm-cfg-thumbs">' . $thumbs . '</div>' : '' )
		. '</div>'
		. '<div class="rm-cfg-panel">'
		. ( $terms ? '<p class="rm-eyebrow">' . wp_kses_post( $terms ) . '</p>' : '' )
		. '<h1 class="rm-cfg-name">' . esc_html( $title ) . '</h1>'
		. ( $subname ? '<p class="rm-cfg-desc">' . esc_html( $subname ) . '</p>' : '' )
		. ( $sortd ? '<p>' . esc_html( $sortd ) . '</p>' : '' )
		. ( $code ? '<p class="rm-pp-code"><span class="rm-cfg-lbl">Order code</span> <span class="rm-cfg-code">' . esc_html( $code ) . '</span></p>' : '' )
		. $acts
		. $jump
		. '</div></div></div>';

	// Specification, variants (RICOBOT) and downloads — below the hero.
	$spec_sec = $spec ? '<div class="rm-section" id="specification"><div class="rm-pp-wrap"><h2 class="rm-shead">Specification</h2>' . $spec . '</div></div>' : '';
	$var_sec  = '<div class="rm-section" id="variants"><div class="rm-pp-wrap"><h2 class="rm-shead">Configure &amp; order codes</h2>' . do_shortcode( '[ricoman_family]' ) . '</div></div>';
	$dl_sec   = $downloads ? '<div class="rm-section" id="downloads"><div class="rm-pp-wrap">' . $downloads . '</div></div>' : '';

	// Zig-zag + paragraphs (structured fields), then CTA band.
	$zig = function_exists( 'ricoman_pf_lines' ) && get_post_meta( $pid, '_ricoman_zigzag', true ) ? do_shortcode( '[ricoman_zigzag]' ) : '';
	$zig = $zig ? '<div class="rm-section">' . $zig . '</div>' : '';

	$cta = '<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:46vh"><span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span><img class="wp-block-cover__image-background" alt="" src="' . esc_url( get_theme_file_uri( 'assets/images/office1.webp' ) ) . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"><h2 class="wp-block-heading has-text-align-center" style="text-align:center">Specify this product</h2><p class="has-text-align-center" style="text-align:center">Add it to your project or request a free lighting scheme.</p><div class="wp-block-buttons is-content-justification-center" style="display:flex;justify-content:center;gap:10px"><a class="btn btn-line" href="' . $enq . '">Add to My Project</a> <a class="btn btn-solid" href="' . esc_url( $ldu ) . '">' . esc_html( $ld ) . '</a></div></div></div>';

	$out  = $hero_html . $spec_sec . $features . $zig . $var_sec . $dl_sec . $cta;
	$out .= '<script>(function(){var w=document.currentScript.previousElementSibling;if(!w)return;var im=w.querySelector(".rm-cfg-img");function bind(sel){w.querySelectorAll(sel).forEach(function(b){b.addEventListener("click",function(){if(b.dataset.img&&im){im.src=b.dataset.img;}var p=b.parentNode;p.querySelectorAll(sel).forEach(function(x){x.classList.remove("on");});b.classList.add("on");});});}bind(".rm-cv-sw");bind(".rm-cfg-thumb");})();</script>';
	return $out;
} );

/* When a product has no block content (the ACF products), render the field page. */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'product' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		return $content; // has real (block) content — leave it.
	}
	return do_shortcode( '[ricoman_product_page]' );
}, 9 );

<?php
/**
 * Project (case study) display from the migrated "Projects Information" ACF.
 * Renders the single project (short / long content templates) and powers the
 * listing thumbnails from the ACF project_image / project_banner_image.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Best thumbnail for a project: featured image, else ACF project images. */
function ricoman_project_img( $pid ) {
	$img = get_the_post_thumbnail_url( $pid, 'large' );
	if ( $img ) {
		return $img;
	}
	if ( function_exists( 'ricoman_pf_imgurl' ) ) {
		foreach ( array( 'project_image', 'project_banner_image' ) as $f ) {
			$u = ricoman_pf_imgurl( get_post_meta( $pid, $f, true ) );
			if ( $u ) {
				return $u;
			}
		}
	}
	return '';
}

/** Resolve a product page URL from a product name (slug match, then title). */
function ricoman_find_product_url( $name ) {
	$name = trim( (string) $name );
	if ( '' === $name ) {
		return '';
	}
	static $cache = array();
	$key = strtolower( $name );
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}
	$url = '';
	// Try the slug derived from the name first (fast, exact).
	$p = get_page_by_path( sanitize_title( $name ), OBJECT, 'product' );
	if ( ! $p ) {
		// Fall back to a title match.
		$q = new WP_Query( array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'title'                  => $name,
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		) );
		if ( $q->have_posts() ) {
			$p = $q->posts[0];
		}
	}
	if ( $p ) {
		$url = (string) get_permalink( $p );
	}
	return $cache[ $key ] = $url;
}

/** Single project body, rendered from ACF (short + long content templates). */
/**
 * "Products used" items for a project. Each item = [href, img, name, sub].
 * Prefers the products picked in the ACF "Products used" relationship; falls back
 * to the legacy product_use repeater, resolving each row to a real product where
 * possible so it shows the same auto-populated card.
 */
function ricoman_project_products_used_items( $pid ) {
	$pid   = (int) $pid;
	$items = array();

	$picked = function_exists( 'get_field' ) ? get_field( 'ricoman_products_used', $pid ) : get_post_meta( $pid, 'ricoman_products_used', true );
	$picked = array_values( array_filter( array_map( 'absint', (array) $picked ) ) );
	if ( $picked ) {
		foreach ( $picked as $ppid ) {
			if ( 'product' === get_post_type( $ppid ) && 'publish' === get_post_status( $ppid ) ) {
				$items[] = ricoman_project_product_card_data( $ppid );
			}
		}
		return $items;
	}

	// Legacy fallback: the old manual repeater (auto-upgraded to a product card
	// when the row resolves to a real product, otherwise shown as entered).
	if ( function_exists( 'have_rows' ) && have_rows( 'product_use', $pid ) ) {
		while ( have_rows( 'product_use', $pid ) ) {
			the_row();
			$name = trim( (string) get_sub_field( 'name' ) );
			$link = get_sub_field( 'link' );
			$href = is_array( $link ) ? ( $link['url'] ?? '' ) : (string) $link;
			$rid  = ricoman_project_resolve_product_id( $name, $href );
			if ( $rid ) {
				$items[] = ricoman_project_product_card_data( $rid );
			} else {
				$items[] = array(
					'href' => '' !== trim( $href ) ? $href : ( function_exists( 'ricoman_find_product_url' ) ? (string) ricoman_find_product_url( $name ) : '' ),
					'img'  => function_exists( 'ricoman_pf_imgurl' ) ? (string) ricoman_pf_imgurl( get_sub_field( 'image' ) ) : '',
					'name' => $name,
					'sub'  => '',
				);
			}
		}
	}
	return $items;
}

/** Card data (href/img/name/sub) auto-pulled from a product post. */
function ricoman_project_product_card_data( $ppid ) {
	$ppid = (int) $ppid;
	$img  = function_exists( 'ricoman_product_img' ) ? (string) ricoman_product_img( $ppid ) : (string) get_the_post_thumbnail_url( $ppid, 'large' );
	$sub  = '';
	if ( function_exists( 'ricoman_pf_metrics' ) ) {
		$rec = ricoman_pf_metrics( $ppid );
		if ( is_array( $rec ) && ! empty( $rec['sub'] ) ) {
			$sub = (string) $rec['sub'];
		}
	}
	if ( '' === $sub && function_exists( 'ricoman_pf_get' ) ) {
		$sub = (string) ricoman_pf_get( $ppid, 'product_subname' );
	}
	return array(
		'href' => (string) get_permalink( $ppid ),
		'img'  => $img,
		'name' => (string) get_the_title( $ppid ),
		'sub'  => trim( wp_strip_all_tags( $sub ) ),
	);
}

/** Best-effort resolve a legacy row (name + link) to a real product ID. Handles
 *  old-domain links by matching the /product/<slug> segment. Returns 0 if none. */
function ricoman_project_resolve_product_id( $name, $href ) {
	$name = trim( (string) $name );
	$href = trim( (string) $href );
	$try  = function ( $url ) {
		if ( '' === $url ) {
			return 0;
		}
		$id = (int) url_to_postid( $url );
		if ( $id && 'product' === get_post_type( $id ) ) {
			return $id;
		}
		if ( preg_match( '#/product/([^/?\#]+)#', $url, $m ) ) {
			$p = get_page_by_path( $m[1], OBJECT, 'product' );
			if ( $p ) {
				return (int) $p->ID;
			}
		}
		return 0;
	};
	$id = $try( $href );
	if ( $id ) {
		return $id;
	}
	if ( '' !== $name && function_exists( 'ricoman_find_product_url' ) ) {
		$id = $try( (string) ricoman_find_product_url( $name ) );
		if ( $id ) {
			return $id;
		}
	}
	return 0;
}

/** "Products used" product picker on the project edit screen (auto-fills the cards). */
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	acf_add_local_field_group( array(
		'key'      => 'group_ricoman_products_used',
		'title'    => 'Products used',
		'fields'   => array(
			array(
				'key'           => 'field_ricoman_products_used',
				'label'         => 'Products used',
				'name'          => 'ricoman_products_used',
				'type'          => 'relationship',
				'instructions'  => 'Search and click to add the products featured in this project. Each card’s image, name and tagline are pulled automatically from the product — nothing to upload. Drag to reorder.',
				'post_type'     => array( 'product' ),
				'filters'       => array( 'search' ),
				'return_format' => 'id',
				'elements'      => array( 'featured_image' ),
			),
		),
		'location'   => array( array( array( 'param' => 'post_type', 'operator' => '==', 'value' => 'project' ) ) ),
		'menu_order' => 3,
		'position'   => 'normal',
	) );
} );

// Hide the old manual "Products used" repeater — the picker above replaces it.
// (Any legacy rows still render on the front end as a fallback until re-picked.)
add_filter( 'acf/prepare_field/name=product_use', '__return_false' );

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'project' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$pid   = get_the_ID();
	$meta  = function ( $k ) use ( $pid ) { return (string) get_post_meta( $pid, $k, true ); };
	$imgof = function ( $k ) use ( $pid ) { return function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_post_meta( $pid, $k, true ) ) : ''; };

	$title   = $meta( 'lighting_project_title' ) ? $meta( 'lighting_project_title' ) : get_the_title( $pid );
	$banner  = $imgof( 'project_banner_image' );
	$sector  = function_exists( 'ricoman_first_term_name' ) ? ricoman_first_term_name( $pid, array( 'project-cat', 'application' ) ) : '';
	$loc     = trim( wp_strip_all_tags( $meta( 'area' ) ) );

	// Gather the gallery up front — its size helps decide simple vs feature.
	$gallery = get_post_meta( $pid, 'project_gallery', true );
	$gimgs   = array();
	if ( is_array( $gallery ) ) {
		foreach ( $gallery as $im ) {
			$gu = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( $im ) : '';
			if ( $gu ) {
				$gimgs[] = $gu;
			}
		}
	}

	// Feature layout = long ACF template, the "single-project" page template, or
	// simply a project rich enough to warrant it (3+ gallery images). Everything
	// else uses the simple one/two-image layout.
	$tpl       = $meta( '_wp_page_template' );
	$isfeature = ( 'long content template' === $meta( 'template_type' ) )
		|| ( 'single-project' === $tpl )
		|| ( count( $gimgs ) >= 3 );

	// Intro line: ACF excerpt-style field, else the raw post excerpt. (Use the raw
	// field, not get_the_excerpt(), which regenerates from the content and would
	// re-run this the_content filter — infinite recursion / 500 on projects with
	// no manual excerpt.)
	$intro = trim( wp_strip_all_tags( $meta( 'pi_description_1' ) ) );
	if ( '' === $intro ) {
		$intro = trim( wp_strip_all_tags( (string) get_post_field( 'post_excerpt', $pid ) ) );
	}

	$out = '';

	// ---- Compact editorial header (no full-bleed hero) --------------------
	$out .= '<div class="rm-section rm-projhead"><div class="rm-pp-wrap rm-projhead-in">';
	if ( function_exists( 'shortcode_exists' ) && shortcode_exists( 'ricoman_breadcrumbs' ) ) {
		$out .= do_shortcode( '[ricoman_breadcrumbs]' );
	}
	if ( $sector ) {
		// Link the sector to its archive of projects.
		$sector_link = '';
		foreach ( array( 'project-cat', 'application' ) as $stax ) {
			if ( ! taxonomy_exists( $stax ) ) {
				continue;
			}
			$sterms = get_the_terms( $pid, $stax );
			if ( $sterms && ! is_wp_error( $sterms ) ) {
				$tl = get_term_link( $sterms[0] );
				if ( ! is_wp_error( $tl ) ) {
					$sector_link = $tl;
				}
				break;
			}
		}
		$out .= $sector_link
			? '<a class="rm-eyebrow rm-projhead-eyebrow rm-projhead-sectorlink" href="' . esc_url( $sector_link ) . '">' . esc_html( $sector ) . ' &rsaquo;</a>'
			: '<p class="rm-eyebrow rm-projhead-eyebrow">' . esc_html( $sector ) . '</p>';
	}
	$out .= '<h1 class="rm-apage-title rm-projhead-title">' . esc_html( $title ) . '</h1>';
	if ( '' !== $intro ) {
		$out .= '<p class="rm-projhead-intro">' . esc_html( wp_trim_words( $intro, 48 ) ) . '</p>';
	}
	// Inline fact row (location / store type / design / contractor).
	$facts = array(
		'Location'              => $loc,
		'Store type'            => trim( wp_strip_all_tags( $meta( 'store_type' ) ) ),
		'Lighting design'       => trim( wp_strip_all_tags( $meta( 'lighting_design' ) ) ),
		'Electrical contractor' => trim( wp_strip_all_tags( $meta( 'electrical_contractor' ) ) ),
	);
	$fr = '';
	foreach ( $facts as $k => $v ) {
		if ( '' !== $v ) {
			$fr .= '<div><span class="rm-flabel">' . esc_html( $k ) . '</span><span>' . esc_html( $v ) . '</span></div>';
		}
	}
	if ( $fr ) {
		$out .= '<div class="rm-projhead-facts">' . $fr . '</div>';
	}
	// Specification consultant(s) — linked to their project archive (/spc/<name>/).
	if ( taxonomy_exists( 'spc' ) ) {
		$spc = get_the_terms( $pid, 'spc' );
		if ( $spc && ! is_wp_error( $spc ) ) {
			$links = array();
			foreach ( $spc as $st ) {
				$stl = get_term_link( $st );
				$links[] = is_wp_error( $stl ) ? esc_html( $st->name ) : '<a href="' . esc_url( $stl ) . '">' . esc_html( $st->name ) . '</a>';
			}
			if ( $links ) {
				$out .= '<p class="rm-projhead-spc"><span class="rm-flabel">' . esc_html__( 'Specification consultant', 'ricoman' ) . '</span> ' . implode( ', ', $links ) . '</p>';
			}
		}
	}
	$out .= '</div></div>';

	// ---- Lead image: contained + rounded, never a 70vh wall ---------------
	$lead          = $banner;
	$lead_from_gal = false;
	if ( ! $lead ) {
		$lead = (string) get_the_post_thumbnail_url( $pid, 'full' );
	}
	if ( ! $lead && $gimgs ) {
		$lead          = $gimgs[0];
		$lead_from_gal = true;
	}
	if ( $lead ) {
		$out .= '<div class="rm-section rm-projlead-sec"><div class="rm-pp-wrap"><img class="rm-projlead" src="' . esc_url( $lead ) . '" alt="' . esc_attr( $title ) . '"></div></div>';
	}

	// ---- Body -------------------------------------------------------------
	$out .= '<div class="rm-section rm-projbody-sec"><div class="rm-pp-wrap rm-proj-single">';
	$body = $meta( 'pi_description_2' );
	$autolink = function ( $html ) {
		return function_exists( 'ricoman_news_autolink' ) ? ricoman_news_autolink( $html ) : $html;
	};
	if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
		$out .= '<div class="rm-apage-wysiwyg rm-proj-body">' . $autolink( wp_kses_post( $body ) ) . '</div>';
	} elseif ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		$out .= '<div class="rm-proj-body">' . $autolink( $content ) . '</div>';
	}

	// Feature: customer quote (pulled up so it breaks the text nicely).
	if ( $isfeature ) {
		$cc = $meta( 'customer_comment' ) ? $meta( 'customer_comment' ) : $meta( 'description_for_customer_comment' );
		if ( '' !== trim( wp_strip_all_tags( $cc ) ) ) {
			$out .= '<blockquote class="rm-proj-quote">' . wp_kses_post( $cc )
				. ( $meta( 'customer_name' ) ? '<cite>' . esc_html( $meta( 'customer_name' ) ) . ( $meta( 'customer_designation' ) ? ', ' . esc_html( $meta( 'customer_designation' ) ) : '' ) . '</cite>' : '' )
				. '</blockquote>';
		}
		$arch = $meta( 'headline' );
		if ( '' !== trim( wp_strip_all_tags( $arch ) ) ) {
			$out .= '<div class="rm-apage-wysiwyg">' . ( $meta( 'headline_title' ) ? '<h2 class="rm-shead">' . esc_html( $meta( 'headline_title' ) ) . '</h2>' : '' ) . wp_kses_post( $arch ) . '</div>';
		}
	}
	$out .= '</div></div>';

	// ---- Gallery ----------------------------------------------------------
	// Simple: a tidy one/two-image strip. Feature: a full masonry gallery.
	// Skip whichever image we already used as the lead (when there's no banner).
	$gimgs_show = $gimgs;
	if ( $lead_from_gal && $gimgs_show ) {
		array_shift( $gimgs_show );
	}
	if ( $gimgs_show ) {
		if ( $isfeature ) {
			$g = '';
			foreach ( $gimgs_show as $u ) {
				$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
			}
			$out .= '<div class="rm-section rm-projgal-sec"><div class="rm-pp-wrap"><div class="rm-apage-gallery rm-projgal-feature">' . $g . '</div></div></div>';
		} else {
			$g = '';
			foreach ( array_slice( $gimgs_show, 0, 2 ) as $u ) {
				$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
			}
			$out .= '<div class="rm-section rm-projgal-sec"><div class="rm-pp-wrap"><div class="rm-projgal-simple">' . $g . '</div></div></div>';
		}
	}

	// ---- Products used ----------------------------------------------------
	// Cards come from the products picked on the project ("Products used" picker);
	// image, name and tagline are pulled automatically from each product. Legacy
	// manually-added rows are resolved to real products where possible, else kept.
	$pu_items = ricoman_project_products_used_items( $pid );
	if ( $pu_items ) {
		$cards = '';
		foreach ( $pu_items as $it ) {
			$imgh   = '<span class="rm-pucard-img">' . ( $it['img'] ? '<img src="' . esc_url( $it['img'] ) . '" alt="' . esc_attr( $it['name'] ) . '" loading="lazy">' : '' ) . '</span>';
			$body   = '<span class="rm-pucard-body"><span class="rm-pucard-title">' . esc_html( $it['name'] ) . '</span>'
				. ( '' !== $it['sub'] ? '<span class="rm-pucard-sub">' . esc_html( $it['sub'] ) . '</span>' : '' ) . '</span>';
			$pu_lbl = function_exists( 'ricoman_acard_label' ) ? ricoman_acard_label( $it['name'], $it['href'] ) : ( $it['name'] ? $it['name'] : 'View product' );
			$cards .= $it['href']
				? '<a class="rm-pucard" href="' . esc_url( $it['href'] ) . '" aria-label="' . esc_attr( $pu_lbl ) . '">' . $imgh . $body . '</a>'
				: '<div class="rm-pucard">' . $imgh . $body . '</div>';
		}
		$out .= '<div class="rm-section rm-projprod-sec"><div class="rm-pp-wrap"><h2 class="rm-shead">Products used</h2><div class="rm-pugrid">' . $cards . '</div></div></div>';
	}

	// Full-width closing banner — the same lead banner used on the news articles
	// (uses its default copy, since the _rmn_cta_* fields are news-only).
	if ( function_exists( 'ricoman_news_cta' ) ) {
		$out .= ricoman_news_cta( $pid );
	}

	return $out;
}, 9 );

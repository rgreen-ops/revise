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

	// Intro line: ACF excerpt-style field, else the post excerpt.
	$intro = trim( wp_strip_all_tags( $meta( 'pi_description_1' ) ) );
	if ( '' === $intro ) {
		$intro = trim( wp_strip_all_tags( get_the_excerpt( $pid ) ) );
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
	if ( function_exists( 'have_rows' ) && have_rows( 'product_use', $pid ) ) {
		$cards = '';
		while ( have_rows( 'product_use', $pid ) ) {
			the_row();
			$pu_img  = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_sub_field( 'image' ) ) : '';
			$pu_name = (string) get_sub_field( 'name' );
			$pu_link = get_sub_field( 'link' );
			$pu_href = is_array( $pu_link ) ? ( $pu_link['url'] ?? '' ) : (string) $pu_link;
			// Always try to link to the real product page: explicit link first,
			// otherwise match a product post by name.
			if ( '' === trim( $pu_href ) ) {
				$pu_href = ricoman_find_product_url( $pu_name );
			}
			$inner   = ( $pu_img ? '<div class="rm-acard-img" style="background-image:url(' . esc_url( $pu_img ) . ')"></div>' : '' )
				. '<div class="rm-acard-body"><h3>' . esc_html( $pu_name ) . '</h3></div>';
			$cards  .= $pu_href ? '<a class="rm-acard" href="' . esc_url( $pu_href ) . '">' . $inner . '</a>' : '<div class="rm-acard">' . $inner . '</div>';
		}
		if ( $cards ) {
			$out .= '<div class="rm-section rm-projprod-sec"><div class="rm-pp-wrap"><h2 class="rm-shead">Products used</h2><div class="rm-acards">' . $cards . '</div></div></div>';
		}
	}

	return $out;
}, 9 );

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
	$islong  = 'long content template' === $meta( 'template_type' );

	$out = '';
	// Hero.
	if ( $banner ) {
		$out .= '<div class="wp-block-cover alignfull rm-apage-hero has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:54vh">'
			. '<span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span>'
			. '<img class="wp-block-cover__image-background" alt="" src="' . esc_url( $banner ) . '" data-object-fit="cover"/>'
			. '<div class="wp-block-cover__inner-container"><p class="rm-eyebrow" style="color:rgba(255,255,255,.7)">Project</p><h1 class="rm-apage-htitle">' . esc_html( $title ) . '</h1></div></div>';
	} else {
		$out .= '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap"><h1 class="rm-apage-title">' . esc_html( $title ) . '</h1></div></div>';
	}

	$out .= '<div class="rm-section"><div class="rm-pp-wrap rm-proj-single">';

	// Meta strip (location / contractor / designer).
	$meta_pairs = array(
		'Location'              => $meta( 'area' ),
		'Store type'            => $meta( 'store_type' ),
		'Lighting design'       => $meta( 'lighting_design' ),
		'Electrical contractor' => $meta( 'electrical_contractor' ),
	);
	$mp = '';
	foreach ( $meta_pairs as $k => $v ) {
		if ( '' !== trim( wp_strip_all_tags( $v ) ) ) {
			$mp .= '<div><span class="rm-flabel">' . esc_html( $k ) . '</span><span>' . wp_kses_post( $v ) . '</span></div>';
		}
	}
	if ( $mp ) {
		$out .= '<div class="rm-proj-meta">' . $mp . '</div>';
	}

	// Body.
	$body = $meta( 'pi_description_2' );
	if ( '' !== trim( wp_strip_all_tags( $body ) ) ) {
		$out .= '<div class="rm-apage-wysiwyg rm-proj-body">' . wp_kses_post( $body ) . '</div>';
	} elseif ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		$out .= '<div class="rm-proj-body">' . $content . '</div>';
	}

	// Products used.
	if ( function_exists( 'have_rows' ) && have_rows( 'product_use', $pid ) ) {
		$cards = '';
		while ( have_rows( 'product_use', $pid ) ) {
			the_row();
			$pu_img  = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_sub_field( 'image' ) ) : '';
			$pu_name = (string) get_sub_field( 'name' );
			$pu_link = get_sub_field( 'link' );
			$pu_href = is_array( $pu_link ) ? ( $pu_link['url'] ?? '' ) : (string) $pu_link;
			$inner   = ( $pu_img ? '<div class="rm-acard-img" style="background-image:url(' . esc_url( $pu_img ) . ')"></div>' : '' )
				. '<div class="rm-acard-body"><h3>' . esc_html( $pu_name ) . '</h3></div>';
			$cards  .= $pu_href ? '<a class="rm-acard" href="' . esc_url( $pu_href ) . '">' . $inner . '</a>' : '<div class="rm-acard">' . $inner . '</div>';
		}
		if ( $cards ) {
			$out .= '<h2 class="rm-shead">Products used</h2><div class="rm-acards">' . $cards . '</div>';
		}
	}

	// Gallery.
	$gallery = get_post_meta( $pid, 'project_gallery', true );
	if ( is_array( $gallery ) && $gallery ) {
		$g = '';
		foreach ( $gallery as $im ) {
			$u = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( $im ) : '';
			if ( $u ) {
				$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
			}
		}
		if ( $g ) {
			$out .= '<div class="rm-apage-gallery">' . $g . '</div>';
		}
	}

	// Long template: customer comment + architect block.
	if ( $islong ) {
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
	return $out;
}, 9 );

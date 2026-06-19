<?php
/**
 * News post type display — master listing + single article, rendered natively
 * from the migrated data (the old site used the `news` post type + ACF
 * "News Others Info" / "Post Tag Line").
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Master / listing grid. [ricoman_news_grid count="12"] */
add_shortcode( 'ricoman_news_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 24 ), $atts, 'ricoman_news_grid' );
	$q    = new WP_Query( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">News articles will appear here.</p></div>';
	}
	$out = '<div class="rm-pp-wrap"><div class="rm-newsgrid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$img = get_the_post_thumbnail_url( get_the_ID(), 'large' );
		if ( ! $img && function_exists( 'ricoman_pf_imgurl' ) ) {
			$img = ricoman_pf_imgurl( get_post_meta( get_the_ID(), 'news_bottom_image', true ) );
		}
		$tag = (string) get_post_meta( get_the_ID(), 'news_tag_line', true );
		$out .= '<a class="rm-newscard" href="' . esc_url( get_permalink() ) . '">'
			. '<span class="rm-newscard-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>'
			. '<span class="rm-newscard-body">'
			. ( $tag ? '<span class="rm-eyebrow">' . esc_html( $tag ) . '</span>' : '' )
			. '<span class="rm-newscard-t">' . esc_html( get_the_title() ) . '</span>'
			. '<span class="rm-newscard-date">' . esc_html( get_the_date() ) . '</span>'
			. '</span></a>';
	}
	wp_reset_postdata();
	return $out . '</div></div>';
} );

/** Single news article body (title + featured image come from the template). */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'news' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$pid  = get_the_ID();
	$tag  = (string) get_post_meta( $pid, 'news_tag_line', true );
	$out  = '<div class="rm-pp-wrap rm-news-single">';
	$out .= '<div class="rm-pp-crumb">' . do_shortcode( '[ricoman_breadcrumbs]' ) . '</div>';
	if ( $tag ) {
		$out .= '<p class="rm-eyebrow">' . esc_html( $tag ) . '</p>';
	}
	$out .= '<h1 class="rm-news-title">' . esc_html( get_the_title() ) . '</h1>';
	$out .= '<p class="rm-news-date">' . esc_html( get_the_date() ) . '</p>';
	$hero = get_the_post_thumbnail_url( $pid, 'large' );
	if ( $hero ) {
		$out .= '<figure class="rm-news-hero"><img src="' . esc_url( $hero ) . '" alt="' . esc_attr( get_the_title() ) . '"></figure>';
	}
	// The article body (classic content stored on the post).
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		$out .= '<div class="rm-news-body">' . $content . '</div>';
	}
	// Optional bottom heading/description + gallery from "News Others Info".
	$bh = (string) get_post_meta( $pid, 'news_bottom_heading', true );
	$bd = (string) get_post_meta( $pid, 'news_bottom_description', true );
	if ( $bh || $bd ) {
		$out .= '<div class="rm-news-extra">' . ( $bh ? '<h2 class="rm-shead">' . esc_html( $bh ) . '</h2>' : '' )
			. ( $bd ? '<p>' . esc_html( $bd ) . '</p>' : '' ) . '</div>';
	}
	if ( function_exists( 'have_rows' ) && have_rows( 'news_details_galary', $pid ) ) {
		$g = '';
		while ( have_rows( 'news_details_galary', $pid ) ) {
			the_row();
			$u = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_sub_field( 'galary_image' ) ) : '';
			if ( $u ) {
				$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
			}
		}
		if ( $g ) {
			$out .= '<div class="rm-apage-gallery">' . $g . '</div>';
		}
	}
	return $out . '</div>';
}, 9 );

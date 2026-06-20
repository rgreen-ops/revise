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

/** Display image for a news article (featured, else ACF bottom image). */
function ricoman_news_img( $pid, $size = 'large' ) {
	$img = get_the_post_thumbnail_url( $pid, $size );
	if ( ! $img && function_exists( 'ricoman_pf_imgurl' ) ) {
		$img = ricoman_pf_imgurl( get_post_meta( $pid, 'news_bottom_image', true ) );
	}
	return $img;
}

/** Short dek/excerpt for a news article. */
function ricoman_news_excerpt( $pid, $words = 24 ) {
	$ex = has_excerpt( $pid ) ? get_the_excerpt( $pid ) : '';
	if ( '' === trim( $ex ) ) {
		$ex = wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) );
	}
	if ( '' === trim( $ex ) ) {
		$ex = (string) get_post_meta( $pid, 'news_bottom_description', true );
	}
	return wp_trim_words( wp_strip_all_tags( $ex ), $words, '…' );
}

/** Estimated read time in minutes from the article body. */
function ricoman_news_readtime( $pid ) {
	$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
	return max( 1, (int) round( $words / 200 ) );
}

/** One news card (used for the grid and the featured lead). */
function ricoman_news_card( $pid, $featured = false ) {
	$img = ricoman_news_img( $pid, $featured ? 'large' : 'medium_large' );
	$tag = (string) get_post_meta( $pid, 'news_tag_line', true );
	$cls = $featured ? 'rm-newscard rm-newscard--lead' : 'rm-newscard';
	return '<a class="' . $cls . '" href="' . esc_url( get_permalink( $pid ) ) . '">'
		. '<span class="rm-newscard-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>'
		. '<span class="rm-newscard-body">'
		. ( $tag ? '<span class="rm-eyebrow">' . esc_html( $tag ) . '</span>' : '' )
		. '<span class="rm-newscard-t">' . esc_html( get_the_title( $pid ) ) . '</span>'
		. ( $featured ? '<span class="rm-newscard-dek">' . esc_html( ricoman_news_excerpt( $pid, 34 ) ) . '</span>' : '' )
		. '<span class="rm-newscard-meta"><span class="rm-newscard-date">' . esc_html( get_the_date( '', $pid ) ) . '</span>'
		. '<span class="rm-newscard-read">' . (int) ricoman_news_readtime( $pid ) . ' min read</span></span>'
		. '<span class="rm-newscard-go">Read article →</span>'
		. '</span></a>';
}

/** Master / listing grid — featured lead + card grid. [ricoman_news_grid count="24"] */
add_shortcode( 'ricoman_news_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 24, 'featured' => '1' ), $atts, 'ricoman_news_grid' );
	$q    = new WP_Query( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">News articles will appear here once published.</p></div>';
	}
	$ids = wp_list_pluck( $q->posts, 'ID' );
	wp_reset_postdata();

	$out  = '<div class="rm-pp-wrap rm-newswrap">';
	$lead = '';
	if ( '1' === (string) $atts['featured'] && count( $ids ) > 0 ) {
		$lead = array_shift( $ids );
		$out .= '<div class="rm-news-lead">' . ricoman_news_card( $lead, true ) . '</div>';
	}
	if ( $ids ) {
		$out .= '<div class="rm-newsgrid">';
		foreach ( $ids as $pid ) {
			$out .= ricoman_news_card( $pid, false );
		}
		$out .= '</div>';
	}
	return $out . '</div>';
} );

/**
 * The /news/ landing page was generated with a placeholder paragraph instead
 * of the article list. Swap that placeholder for the real listing (keeping the
 * page's hero + CTA), so visitors actually see the articles.
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || ! is_page() ) {
		return $content;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( ! in_array( $slug, array( 'news', 'our-news', 'insights' ), true ) ) {
		return $content;
	}
	$grid = do_shortcode( '[ricoman_news_grid]' );
	if ( false !== strpos( $content, 'latest news and articles will appear' ) ) {
		return preg_replace( '#<p>\s*Our latest news and articles will appear here\.?\s*</p>#i', $grid, $content, 1 );
	}
	return $content . $grid;
}, 8 );

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

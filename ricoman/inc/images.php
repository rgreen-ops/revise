<?php
/**
 * Automatic web-ready images.
 *
 * On upload, WordPress is steered to produce the best modern format and a full
 * set of responsive sizes, so the front end serves the right image to every
 * device automatically (via native srcset/sizes + lazy-loading):
 *  - Sub-sizes are written as AVIF (if the server supports it) or WebP, falling
 *    back to the original format.
 *  - Sensible quality + a cap on enormous originals.
 *  - Extra responsive sizes for cards / wide / hero crops.
 *  - SEO: auto-fills empty alt text from a tidied version of the title.
 *
 * Note: this affects NEW uploads. Use a "regenerate thumbnails" tool once to
 * convert an existing media library.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Best modern output format the server can actually write.
 *
 * @return string Mime type, or '' if neither AVIF nor WebP is supported.
 */
function ricoman_best_image_mime() {
	foreach ( array( 'image/avif', 'image/webp' ) as $mime ) {
		if ( wp_image_editor_supports( array( 'mime_type' => $mime ) ) ) {
			return $mime;
		}
	}
	return '';
}

/**
 * Output generated sub-sizes in a modern format (AVIF/WebP). Originals are
 * preserved by WordPress for download/fallback.
 *
 * @param array $formats Map of source mime => output mime.
 * @return array
 */
add_filter( 'image_editor_output_format', function ( $formats ) {
	$best = ricoman_best_image_mime();
	if ( $best ) {
		$formats['image/jpeg'] = $best;
		$formats['image/png']  = $best;
	}
	return $formats;
} );

/* ---- Quality: balanced for web ---- */
add_filter( 'jpeg_quality', function () {
	return 82;
} );
add_filter( 'wp_editor_set_quality', function ( $quality, $mime ) {
	if ( 'image/webp' === $mime ) {
		return 80;
	}
	if ( 'image/avif' === $mime ) {
		return 60; // AVIF looks great at lower numbers.
	}
	return 82;
}, 10, 2 );

/* ---- Cap absurdly large originals (keeps the "-scaled" original sane) ---- */
add_filter( 'big_image_size_threshold', function () {
	return 2048;
} );

/* ---- Extra responsive crops for the templates ---- */
add_action( 'after_setup_theme', function () {
	add_image_size( 'ricoman-card', 800, 800, true );   // square product/news cards
	add_image_size( 'ricoman-wide', 1600, 900, true );  // 16:9 feature blocks
	add_image_size( 'ricoman-hero', 2000, 1120, true ); // full-bleed heroes
} );

// Make the custom sizes selectable in the editor's image-size dropdown.
add_filter( 'image_size_names_choose', function ( $sizes ) {
	return array_merge( $sizes, array(
		'ricoman-card' => __( 'Card (square)', 'ricoman' ),
		'ricoman-wide' => __( 'Wide (16:9)', 'ricoman' ),
		'ricoman-hero' => __( 'Hero (full-bleed)', 'ricoman' ),
	) );
} );

/* ---- SEO: auto-fill alt text from a tidied title on upload ---- */
add_action( 'add_attachment', function ( $post_id ) {
	if ( ! wp_attachment_is_image( $post_id ) ) {
		return;
	}
	$existing = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
	if ( $existing ) {
		return;
	}
	$title = get_the_title( $post_id );
	$title = preg_replace( '/[-_]+/', ' ', (string) $title );
	// Drop common camera / export noise tokens.
	$title = preg_replace( '/\b(img|image|dsc|dscf|pxl|screenshot|photo|final|copy|edit|scaled|\d{3,})\b/i', '', $title );
	$title = trim( preg_replace( '/\s+/', ' ', $title ) );
	if ( '' !== $title ) {
		update_post_meta( $post_id, '_wp_attachment_image_alt', ucfirst( $title ) );
	}
} );

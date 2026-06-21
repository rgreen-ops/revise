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

/** Tidy a raw title/filename into human alt text (drops camera/export noise). */
function ricoman_alt_tidy( $title ) {
	$title = preg_replace( '/[-_]+/', ' ', (string) $title );
	// Drop common camera / export noise tokens.
	$title = preg_replace( '/\b(img|image|dsc|dscf|pxl|screenshot|photo|final|copy|edit|scaled|\d{3,})\b/i', '', $title );
	$title = trim( preg_replace( '/\s+/', ' ', (string) $title ) );
	return '' !== $title ? ucfirst( $title ) : '';
}

/**
 * Best alt text for an attachment: its own tidied title, falling back to the
 * tidied title of the post it's attached to (e.g. the product name) so migrated
 * gallery images get meaningful, contextual alt text rather than a filename.
 */
function ricoman_alt_for_attachment( $post_id ) {
	$alt = ricoman_alt_tidy( get_the_title( $post_id ) );
	if ( '' === $alt ) {
		$parent = (int) get_post_field( 'post_parent', $post_id );
		if ( $parent ) {
			$alt = ricoman_alt_tidy( get_the_title( $parent ) );
		}
	}
	return $alt;
}

add_action( 'add_attachment', function ( $post_id ) {
	if ( ! wp_attachment_is_image( $post_id ) ) {
		return;
	}
	$existing = get_post_meta( $post_id, '_wp_attachment_image_alt', true );
	if ( $existing ) {
		return;
	}
	$alt = ricoman_alt_for_attachment( $post_id );
	if ( '' !== $alt ) {
		update_post_meta( $post_id, '_wp_attachment_image_alt', $alt );
	}
} );

/* ---------------------------------------------------------------------------
 * Image Alt Text — backfill missing alt across the migrated library.
 *
 * The upload hook only covers new uploads; images imported with the old site
 * never ran through it, so most have empty alt text (an SEO + accessibility
 * gap). This admin tool counts images missing alt and fills them in resumable
 * batches, using the same tidy-title (+ parent post) logic as the upload hook.
 * ------------------------------------------------------------------------- */

/**
 * Count image attachments still to process: empty alt text and not yet scanned.
 * The `_rm_alt_scanned` flag lets us skip un-nameable images (no usable title
 * anywhere) so the batch loop always terminates instead of re-checking them.
 */
function ricoman_alt_missing_count() {
	global $wpdb;
	return (int) $wpdb->get_var(
		"SELECT COUNT(*) FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON ( m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt' )
		 LEFT JOIN {$wpdb->postmeta} s ON ( s.post_id = p.ID AND s.meta_key = '_rm_alt_scanned' )
		 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
		   AND ( m.meta_id IS NULL OR TRIM(m.meta_value) = '' )
		   AND s.meta_id IS NULL"
	);
}

/** Fill alt text for up to $limit unprocessed images. Returns count filled. */
function ricoman_alt_fill_batch( $limit = 400 ) {
	global $wpdb;
	$limit = max( 1, (int) $limit );
	$ids   = $wpdb->get_col( $wpdb->prepare(
		"SELECT p.ID FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} m ON ( m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt' )
		 LEFT JOIN {$wpdb->postmeta} s ON ( s.post_id = p.ID AND s.meta_key = '_rm_alt_scanned' )
		 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'
		   AND ( m.meta_id IS NULL OR TRIM(m.meta_value) = '' )
		   AND s.meta_id IS NULL
		 LIMIT %d",
		$limit
	) );
	$filled = 0;
	foreach ( (array) $ids as $id ) {
		$id  = (int) $id;
		$alt = ricoman_alt_for_attachment( $id );
		if ( '' !== $alt ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt );
			$filled++;
		}
		// Mark scanned either way so un-nameable images aren't re-checked forever.
		update_post_meta( $id, '_rm_alt_scanned', 1 );
	}
	return $filled;
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Image Alt Text', 'ricoman' ),
		__( 'Image Alt Text', 'ricoman' ),
		'manage_options',
		'ricoman-image-alt',
		'ricoman_render_image_alt'
	);
}, 33 );

function ricoman_render_image_alt() {
	$missing = ricoman_alt_missing_count();
	echo '<div class="wrap"><h1>' . esc_html__( 'Image Alt Text', 'ricoman' ) . '</h1>';
	if ( isset( $_GET['filled'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Added alt text to %d image(s).', 'ricoman' ), (int) $_GET['filled'] )
			. '</p></div>';
	}
	echo '<p>' . esc_html__( 'Alt text describes an image for search engines and screen readers. New uploads get alt text automatically; this tool backfills the migrated library, deriving alt from each image\'s title (or the product/post it belongs to).', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html( sprintf( _n( '%d image still to process.', '%d images still to process.', $missing, 'ricoman' ), $missing ) ) . '</strong></p>';
	if ( $missing > 0 ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="ricoman_fill_image_alt">';
		wp_nonce_field( 'ricoman_fill_image_alt' );
		submit_button( __( 'Add alt text to the next batch', 'ricoman' ), 'primary' );
		echo '</form>';
		echo '<p class="description">' . esc_html__( 'Runs in batches of 400 so it never times out — keep clicking until it reaches zero.', 'ricoman' ) . '</p>';
	} else {
		echo '<p>' . esc_html__( 'All images processed. 🎉', 'ricoman' ) . '</p>';
	}
	echo '</div>';
}

add_action( 'admin_post_ricoman_fill_image_alt', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'ricoman_fill_image_alt' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$filled = ricoman_alt_fill_batch( 400 );
	wp_safe_redirect( add_query_arg( 'filled', $filled, admin_url( 'admin.php?page=ricoman-image-alt' ) ) );
	exit;
} );

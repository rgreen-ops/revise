<?php
/**
 * Bulk Product FAQ importer (Ricoman → Import Product FAQs).
 *
 * One-time / re-runnable tool that writes the FAQ content authored in the
 * "Ricoman_Product_FAQ_Tracker" sheet onto each product's `_ricoman_faq` meta
 * (the same field the Product FAQs metabox uses, which renders the FAQ section +
 * FAQPage schema). Data lives in data/product-faqs.json — an array of:
 *   { "name": <sheet product name>, "ids": [<product post IDs>], "faq": "Q:…\nA:…" }
 * Some ranges map to several product pages (E-Pro, Zodiac, Aquavance, …), so `ids`
 * is a list. Shows a full PREVIEW first; only writes when the button is pressed.
 * Overwrites existing FAQ content by design (confirmed with marketing).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Capability required to run the importer. */
function ricoman_faq_import_cap() {
	return 'edit_posts';
}

// TEMP diagnostic: expose the byte-length of each product's _ricoman_faq via REST
// (length only, no content) so we can confirm exactly which products the import
// wrote. Remove once the FAQ import is verified.
add_action( 'rest_api_init', function () {
	if ( ! function_exists( 'register_rest_field' ) ) {
		return;
	}
	register_rest_field( 'product', 'rm_faq_len', array(
		'get_callback' => function ( $obj ) {
			$id = is_array( $obj ) && isset( $obj['id'] ) ? (int) $obj['id'] : 0;
			return strlen( (string) get_post_meta( $id, '_ricoman_faq', true ) );
		},
	) );
} );

/** Read + decode the import data file (array of rows). */
function ricoman_faq_import_data() {
	$file = get_theme_file_path( 'data/product-faqs.json' );
	if ( ! file_exists( $file ) ) {
		return array();
	}
	$json = file_get_contents( $file );
	$data = json_decode( $json, true );
	return is_array( $data ) ? $data : array();
}

/** First question in a Q:/A: block, for the preview. */
function ricoman_faq_first_q( $faq ) {
	if ( preg_match( '/^\s*Q[:.\)]\s*(.+)$/mi', (string) $faq, $m ) ) {
		return trim( $m[1] );
	}
	return '';
}

/* ------------------------------------------------------------------- menu */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Import Product FAQs', 'ricoman' ),
		__( 'Import Product FAQs', 'ricoman' ),
		ricoman_faq_import_cap(),
		'ricoman-faq-import',
		'ricoman_faq_import_render'
	);
}, 40 );

/* ---------------------------------------------------------------- preview */

function ricoman_faq_import_render() {
	if ( ! current_user_can( ricoman_faq_import_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	$rows = ricoman_faq_import_data();

	echo '<div class="wrap"><h1>' . esc_html__( 'Import Product FAQs', 'ricoman' ) . '</h1>';

	// Result notice after an import run.
	if ( ! empty( $_GET['done'] ) ) {
		$pages = isset( $_GET['pages'] ) ? (int) $_GET['pages'] : 0;
		$prods = isset( $_GET['prods'] ) ? (int) $_GET['prods'] : 0;
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf(
				/* translators: 1: FAQ set count, 2: page count */
				esc_html__( 'Done — imported %1$d FAQ sets onto %2$d product pages. Open a product to check, and the FAQ section will show on the live pages.', 'ricoman' ),
				$prods,
				$pages
			) . '</p></div>';
	}

	if ( ! $rows ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Import file not found (data/product-faqs.json). Nothing to import.', 'ricoman' ) . '</p></div></div>';
		return;
	}

	// Tally.
	$total_pages = 0;
	$total_q     = 0;
	$missing     = 0;
	foreach ( $rows as $r ) {
		$total_q += isset( $r['count'] ) ? (int) $r['count'] : 0;
		foreach ( (array) ( isset( $r['ids'] ) ? $r['ids'] : array() ) as $id ) {
			if ( 'product' === get_post_type( (int) $id ) ) {
				$total_pages++;
			} else {
				$missing++;
			}
		}
	}

	echo '<p class="description" style="font-size:14px;max-width:820px">'
		. esc_html__( 'This writes the FAQ content from your sheet onto each product below. Review the matches first — nothing is written until you press the button. Existing FAQs on these products will be replaced.', 'ricoman' )
		. '</p>';

	echo '<p><strong>' . esc_html( sprintf(
		/* translators: 1: FAQ set count, 2: page count, 3: Q&A count */
		__( '%1$d FAQ sets → %2$d product pages → %3$d questions total.', 'ricoman' ),
		count( $rows ),
		$total_pages,
		$total_q
	) ) . '</strong></p>';

	if ( $missing ) {
		echo '<div class="notice notice-warning inline"><p>'
			. esc_html( sprintf( __( '%d target page(s) below no longer exist and will be skipped (shown in red).', 'ricoman' ), $missing ) )
			. '</p></div>';
	}

	// Import button.
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0">';
	wp_nonce_field( 'ricoman_faq_import' );
	echo '<input type="hidden" name="action" value="ricoman_faq_import">';
	$confirm = esc_js( __( 'Import all FAQs now? This overwrites existing FAQ content on the listed products.', 'ricoman' ) );
	echo '<button type="submit" class="button button-primary button-hero" onclick="return confirm(\'' . $confirm . '\')">'
		. esc_html__( 'Import all FAQs now', 'ricoman' ) . '</button>';
	echo '</form>';

	// Preview table.
	echo '<table class="widefat striped"><thead><tr>'
		. '<th>' . esc_html__( 'From sheet', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Goes onto these product pages', 'ricoman' ) . '</th>'
		. '<th style="width:70px">' . esc_html__( 'Q&amp;As', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'First question (preview)', 'ricoman' ) . '</th>'
		. '</tr></thead><tbody>';

	foreach ( $rows as $r ) {
		$name  = isset( $r['name'] ) ? $r['name'] : '';
		$ids   = isset( $r['ids'] ) ? (array) $r['ids'] : array();
		$count = isset( $r['count'] ) ? (int) $r['count'] : 0;
		$firstq = ricoman_faq_first_q( isset( $r['faq'] ) ? $r['faq'] : '' );

		$targets = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( 'product' === get_post_type( $id ) ) {
				$edit  = admin_url( 'admin.php?page=ricoman-product-editor&product=' . $id );
				$has   = '' !== trim( (string) get_post_meta( $id, '_ricoman_faq', true ) );
				$targets[] = '<a href="' . esc_url( $edit ) . '" target="_blank">' . esc_html( get_the_title( $id ) ) . '</a>'
					. ( $has ? ' <span style="color:#b26a00" title="' . esc_attr__( 'Has existing FAQs — will be replaced', 'ricoman' ) . '">•</span>' : '' );
			} else {
				$targets[] = '<span style="color:#b32d2e">#' . $id . ' ' . esc_html__( '(missing — skipped)', 'ricoman' ) . '</span>';
			}
		}

		echo '<tr>'
			. '<td><strong>' . esc_html( $name ) . '</strong></td>'
			. '<td>' . implode( '<br>', $targets ) . '</td>'
			. '<td>' . (int) $count . '</td>'
			. '<td><em>' . esc_html( function_exists( 'mb_strimwidth' ) ? mb_strimwidth( $firstq, 0, 90, '…' ) : ( strlen( $firstq ) > 90 ? substr( $firstq, 0, 89 ) . '…' : $firstq ) ) . '</em></td>'
			. '</tr>';
	}
	echo '</tbody></table>';
	echo '<p class="description" style="margin-top:6px">'
		. esc_html__( 'The amber dot marks a product that already has FAQs (they will be replaced).', 'ricoman' )
		. '</p>';
	echo '</div>';
}

/* ----------------------------------------------------------------- import */

add_action( 'admin_post_ricoman_faq_import', function () {
	if ( ! current_user_can( ricoman_faq_import_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.', 'ricoman' ) );
	}
	check_admin_referer( 'ricoman_faq_import' );

	$rows  = ricoman_faq_import_data();
	$pages = 0;
	$prods = 0;

	foreach ( $rows as $r ) {
		$faq = isset( $r['faq'] ) ? (string) $r['faq'] : '';
		$ids = isset( $r['ids'] ) ? (array) $r['ids'] : array();
		if ( '' === trim( $faq ) || ! $ids ) {
			continue;
		}
		$clean = wp_kses_post( $faq );
		$did   = false;
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( 'product' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				continue;
			}
			update_post_meta( $id, '_ricoman_faq', $clean );
			// Bust THIS product's section cache so the FAQ appears immediately.
			// The section-cache key (ricoman_pf_sections) is keyed on _rm_secver +
			// post-modified time, NOT rm_products_ver — writing meta directly leaves
			// a stale cached page, so bump _rm_secver like a normal product save does.
			update_post_meta( $id, '_rm_secver', time() );
			clean_post_cache( $id );
			$pages++;
			$did = true;
		}
		if ( $did ) {
			$prods++;
		}
	}

	// Refresh product-derived caches so the FAQ section shows immediately
	// (mirrors the Product FAQs metabox save).
	update_option( 'rm_products_ver', (string) time(), false );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'ricoman-faq-import', 'done' => 1, 'pages' => $pages, 'prods' => $prods ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

<?php
/**
 * Register the original ricoman.com ACF field groups.
 *
 * The live site defined all its product / page fields with ACF. Those
 * definitions live in inc/acf/ricoman-fields.json (exported from the old site).
 * Registering them here does two things:
 *   1. Recreates the exact editing experience (the same metaboxes: Product
 *      Variation By Color, Paragraph Info Section, Product General Information,
 *      Variant Products, etc.).
 *   2. Makes ACF *repeater* fields readable on the front end — without the field
 *      group registered, get_field() can't resolve repeaters (variants, the
 *      paragraph highlights, dimension diagrams, downloads), so they came back
 *      empty after the migration.
 *
 * The data itself already lives in the database (migrated); this only supplies
 * the field definitions.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'acf/init', 'ricoman_register_legacy_acf_fields' );

function ricoman_register_legacy_acf_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}
	$file = get_theme_file_path( 'inc/acf/ricoman-fields.json' );
	if ( ! is_readable( $file ) ) {
		return;
	}
	$groups = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $groups ) ) {
		return;
	}
	foreach ( $groups as $group ) {
		if ( is_array( $group ) && ! empty( $group['key'] ) ) {
			acf_add_local_field_group( $group );
		}
	}
}

/**
 * Now the original ACF product fields are back, hide the theme's own product
 * metaboxes ("Product Builder" + "Product page content") so the editor matches
 * the familiar, simpler back-end instead of showing two editing systems at once.
 */
add_action( 'add_meta_boxes', function () {
	remove_meta_box( 'ricoman_product_details', 'product', 'normal' ); // Product Builder.
	remove_meta_box( 'ricoman_pf', 'product', 'normal' );              // Structured content.
}, 99 );

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

/**
 * The original product field groups target custom ACF location rule types the
 * old theme defined (`product_template`, `product_page_template`,
 * `taxonomy_term_child`). Without them registered, ACF can't evaluate the rule
 * and HIDES the product metaboxes. Register them so the fields show again.
 *
 * Products store no template selection in our theme, so the "Product Page
 * Information" / "Product Variation By Color" groups (rule: product_template !=
 * flow) always match, while the Flow/Astrowave group (== flow) only matches
 * when a product's template meta is explicitly that.
 */
add_filter( 'acf/location/rule_types', function ( $choices ) {
	$choices['Product']['product_template']      = 'Product Template';
	$choices['Product']['product_page_template'] = 'Product Page Template';
	$choices['Forms']['taxonomy_term_child']     = 'Taxonomy Term (child)';
	return $choices;
} );

function ricoman_acf_product_template( $pid ) {
	if ( ! $pid ) {
		return '';
	}
	foreach ( array( '_product_template', 'product_template', '_wp_page_template' ) as $k ) {
		$v = (string) get_post_meta( $pid, $k, true );
		if ( '' !== $v && 'default' !== $v ) {
			return $v;
		}
	}
	return '';
}

function ricoman_acf_rule_match_template( $match, $rule, $options ) {
	$pid     = ! empty( $options['post_id'] ) ? (int) $options['post_id'] : ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
	$current = ricoman_acf_product_template( $pid );
	return ( '!=' === $rule['operator'] ) ? ( $current !== $rule['value'] ) : ( $current === $rule['value'] );
}
add_filter( 'acf/location/rule_match/product_template', 'ricoman_acf_rule_match_template', 10, 3 );
add_filter( 'acf/location/rule_match/product_page_template', 'ricoman_acf_rule_match_template', 10, 3 );

/**
 * Make the product "Related Projects" field selectable. In the export its choices
 * are empty (the old theme populated them dynamically), so nothing could be
 * picked. Populate it with every project, multi-select — and because each product
 * chooses its own projects, the same project (and its in-situ photos) can be
 * attached to many products. The product page's In-situ gallery tab reads this.
 */
add_filter( 'acf/load_field/name=related_projects', function ( $field ) {
	$field['type']     = 'post_object';
	$field['post_type'] = array( 'project' );
	$field['multiple']  = 1;
	$field['ui']        = 1;
	$field['return_format'] = 'id';
	$field['allow_null']    = 1;
	$field['choices']   = array();
	return $field;
} );

// Family fields target child terms of product-cat — match any product-cat term.
add_filter( 'acf/location/rule_match/taxonomy_term_child', function ( $match, $rule, $options ) {
	$tax = '';
	if ( ! empty( $options['taxonomy'] ) ) {
		$tax = $options['taxonomy'];
	} elseif ( ! empty( $options['term_id'] ) ) {
		$t = get_term( (int) $options['term_id'] );
		$tax = ( $t && ! is_wp_error( $t ) ) ? $t->taxonomy : '';
	}
	return ( '!=' === $rule['operator'] ) ? ( $tax !== $rule['value'] ) : ( $tax === $rule['value'] );
}, 10, 3 );


<?php
/**
 * Front-end product shortcodes.
 *
 * Used inside block templates via the Shortcode block so editors can move them
 * around the product layout freely.
 *
 *   [ricoman_product_specs]      Specification table
 *   [ricoman_product_variants]   Variant table
 *   [ricoman_datasheet_button]   "Download datasheet" button
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the product ID in the current context.
 *
 * @param array $atts Shortcode attributes (optional `id`).
 * @return int
 */
function ricoman_resolve_product_id( $atts ) {
	if ( ! empty( $atts['id'] ) ) {
		return (int) $atts['id'];
	}
	return (int) get_the_ID();
}

/**
 * Specification table.
 */
function ricoman_sc_product_specs( $atts ) {
	$id = ricoman_resolve_product_id( (array) $atts );
	if ( ! $id ) {
		return '';
	}

	$rows = '';
	foreach ( ricoman_product_spec_fields() as $key => $label ) {
		$val = (string) get_post_meta( $id, $key, true );
		if ( '' === $val ) {
			continue;
		}
		$rows .= '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( $val ) . '</td></tr>';
	}
	if ( '' === $rows ) {
		return '';
	}
	return '<table class="ricoman-spec-table"><tbody>' . $rows . '</tbody></table>';
}
add_shortcode( 'ricoman_product_specs', 'ricoman_sc_product_specs' );

/**
 * Variant table.
 */
function ricoman_sc_product_variants( $atts ) {
	$id       = ricoman_resolve_product_id( (array) $atts );
	$variants = $id ? ricoman_get_variants( $id ) : array();
	if ( empty( $variants ) ) {
		return '';
	}

	$body = '';
	foreach ( $variants as $v ) {
		$body .= '<tr>'
			. '<td>' . esc_html( $v['sku'] ) . '</td>'
			. '<td>' . esc_html( $v['description'] ) . '</td>'
			. '<td>' . esc_html( $v['wattage'] ) . '</td>'
			. '<td>' . esc_html( $v['lumens'] ) . '</td>'
			. '<td>' . esc_html( $v['cct'] ) . '</td>'
			. '</tr>';
	}

	return '<table class="ricoman-spec-table"><thead><tr>'
		. '<th>' . esc_html__( 'Order code', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Description', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Watts', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'Lumens', 'ricoman' ) . '</th>'
		. '<th>' . esc_html__( 'CCT', 'ricoman' ) . '</th>'
		. '</tr></thead><tbody>' . $body . '</tbody></table>';
}
add_shortcode( 'ricoman_product_variants', 'ricoman_sc_product_variants' );

/**
 * Datasheet download button.
 */
function ricoman_sc_datasheet_button( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0, 'label' => __( 'Download datasheet (PDF)', 'ricoman' ) ), $atts, 'ricoman_datasheet_button' );
	$id   = ricoman_resolve_product_id( $atts );
	if ( ! $id ) {
		return '';
	}
	return '<a class="wp-block-button__link wp-element-button is-style-pill" href="' . esc_url( ricoman_datasheet_url( $id ) ) . '" target="_blank" rel="noopener">' . esc_html( $atts['label'] ) . '</a>';
}
add_shortcode( 'ricoman_datasheet_button', 'ricoman_sc_datasheet_button' );

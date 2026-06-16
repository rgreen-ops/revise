<?php
/**
 * Product data & variant structure.
 *
 * Registers typed product meta (specs) plus a simple variant table, exposed in
 * the REST API so they can be read by the datasheet, schema and front-end
 * templates. A lightweight meta box keeps editing usable in any editor.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The product specification fields.
 *
 * @return array<string,string> Map of meta key => human label.
 */
function ricoman_product_spec_fields() {
	return array(
		'_ricoman_sku'        => __( 'SKU / Order code', 'ricoman' ),
		'_ricoman_wattage'    => __( 'Wattage (W)', 'ricoman' ),
		'_ricoman_lumens'     => __( 'Output (lm)', 'ricoman' ),
		'_ricoman_efficacy'   => __( 'Efficacy (lm/W)', 'ricoman' ),
		'_ricoman_cct'        => __( 'Colour temperature (CCT)', 'ricoman' ),
		'_ricoman_cri'        => __( 'CRI', 'ricoman' ),
		'_ricoman_beam'       => __( 'Beam angle', 'ricoman' ),
		'_ricoman_ip'         => __( 'IP rating', 'ricoman' ),
		'_ricoman_dimensions' => __( 'Dimensions', 'ricoman' ),
		'_ricoman_warranty'   => __( 'Warranty', 'ricoman' ),
	);
}

/**
 * Register product meta with the REST API.
 */
function ricoman_register_product_meta() {
	foreach ( array_keys( ricoman_product_spec_fields() ) as $key ) {
		register_post_meta(
			'product',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	// Variants stored as a newline-delimited table; parsed on read.
	register_post_meta(
		'product',
		'_ricoman_variants',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'ricoman_sanitize_variants',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'ricoman_register_product_meta' );

/**
 * Sanitize the variants textarea (one variant per line).
 *
 * @param string $value Raw textarea value.
 * @return string
 */
function ricoman_sanitize_variants( $value ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	$clean = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$clean[] = implode( ' | ', array_map( 'sanitize_text_field', array_map( 'trim', explode( '|', $line ) ) ) );
		}
	}
	return implode( "\n", $clean );
}

/**
 * Parse the variants meta into structured rows.
 *
 * Each line: SKU | Description | Wattage | Lumens | CCT
 *
 * @param int $post_id Product ID.
 * @return array<int,array<string,string>>
 */
function ricoman_get_variants( $post_id ) {
	$raw = (string) get_post_meta( $post_id, '_ricoman_variants', true );
	if ( '' === $raw ) {
		return array();
	}
	$cols = array( 'sku', 'description', 'wattage', 'lumens', 'cct' );
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$row   = array();
		foreach ( $cols as $i => $col ) {
			$row[ $col ] = isset( $parts[ $i ] ) ? $parts[ $i ] : '';
		}
		$rows[] = $row;
	}
	return $rows;
}

/* -------------------------------------------------------------------------
 * Editing UI: a simple meta box (works in classic and block editors).
 * ---------------------------------------------------------------------- */

/**
 * Add the product details meta box.
 */
function ricoman_add_product_metabox() {
	add_meta_box(
		'ricoman_product_details',
		__( 'Product details & variants', 'ricoman' ),
		'ricoman_render_product_metabox',
		'product',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'ricoman_add_product_metabox' );

/**
 * Render the product meta box.
 *
 * @param WP_Post $post Current product.
 */
function ricoman_render_product_metabox( $post ) {
	wp_nonce_field( 'ricoman_save_product', 'ricoman_product_nonce' );
	echo '<style>.ricoman-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.ricoman-meta-grid label{display:block;font-weight:600;margin-bottom:4px}.ricoman-meta-grid input{width:100%}.ricoman-meta-full{margin-top:14px}.ricoman-meta-full textarea{width:100%}</style>';
	echo '<div class="ricoman-meta-grid">';
	foreach ( ricoman_product_spec_fields() as $key => $label ) {
		$val = esc_attr( (string) get_post_meta( $post->ID, $key, true ) );
		printf(
			'<div><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="%3$s" /></div>',
			esc_attr( $key ),
			esc_html( $label ),
			$val
		);
	}
	echo '</div>';

	$variants = esc_textarea( (string) get_post_meta( $post->ID, '_ricoman_variants', true ) );
	echo '<div class="ricoman-meta-full">';
	echo '<label for="_ricoman_variants" style="font-weight:600;display:block;margin-bottom:4px">' . esc_html__( 'Variants — one per line: SKU | Description | Wattage | Lumens | CCT', 'ricoman' ) . '</label>';
	echo '<textarea id="_ricoman_variants" name="_ricoman_variants" rows="6" placeholder="RM-DL-08 | 8W fixed downlight | 8W | 800lm | 3000/4000/6000K">' . $variants . '</textarea>';
	echo '</div>';
}

/**
 * Save the product meta box values.
 *
 * @param int $post_id Product ID.
 */
function ricoman_save_product_meta( $post_id ) {
	if ( ! isset( $_POST['ricoman_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_product_nonce'] ), 'ricoman_save_product' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array_keys( ricoman_product_spec_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['_ricoman_variants'] ) ) {
		update_post_meta( $post_id, '_ricoman_variants', ricoman_sanitize_variants( wp_unslash( $_POST['_ricoman_variants'] ) ) );
	}
}
add_action( 'save_post_product', 'ricoman_save_product_meta' );

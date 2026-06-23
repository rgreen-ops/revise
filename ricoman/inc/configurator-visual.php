<?php
/**
 * Visual configurator — a guided, tap-through alternative to the variant table.
 *
 * Each spec axis (Model, Colour Temp, Wattage, Finish, Beam angle, …) shows as a
 * row of option tiles; choosing one narrows the rest (unavailable options grey
 * out), and once a single variant is pinned you see its full details + downloads.
 * Runs on the SAME variant data as the configure table. Per-product toggle.
 *
 * Option tiles use each option's representative variant image automatically where
 * the image varies by that axis (e.g. Finish, Model); otherwise text tiles.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is the visual configurator turned on for this product? */
function ricoman_pf_visual_config_enabled( $pid ) {
	// Live builder preview override.
	if ( ! empty( $GLOBALS['rm_pe_preview'] ) && (int) $GLOBALS['rm_pe_preview']['pid'] === (int) $pid && isset( $GLOBALS['rm_pe_preview']['configvisual'] ) ) {
		return (bool) $GLOBALS['rm_pe_preview']['configvisual'];
	}
	return '1' === (string) get_post_meta( (int) $pid, '_ricoman_config_visual', true );
}

/** Build the visual configurator markup + embedded data, or '' to fall back. */
function ricoman_pf_visual_config( $pid ) {
	if ( ! post_type_exists( 'variant-product' ) ) {
		return '';
	}
	$family_ids  = function_exists( 'ricoman_pf_family_products' ) ? ricoman_pf_family_products( $pid ) : array( (int) $pid );
	$is_family   = count( $family_ids ) > 1;
	$type_labels = ( $is_family && function_exists( 'ricoman_pf_type_labels' ) ) ? ricoman_pf_type_labels( $family_ids ) : array();
	$mq = $is_family
		? array( array( 'key' => 'parent_product', 'value' => array_map( 'strval', $family_ids ), 'compare' => 'IN' ) )
		: array( array( 'key' => 'parent_product', 'value' => (string) $pid ) );
	$q = new WP_Query( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => 2000,
		'no_found_rows'  => true,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'meta_query'     => $mq,
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$parent_img = ricoman_pf_imgurl( ricoman_pf_get( $pid, 'product_main_image' ) );
	if ( ! $parent_img ) {
		$parent_img = ricoman_pf_imgurl( ricoman_pf_get( $pid, 'product_gallery_image' ) );
	}

	$order = array();
	foreach ( ricoman_variant_spec_defs() as $def ) {
		if ( ! in_array( $def[2], $order, true ) ) {
			$order[] = $def[2];
		}
	}

	$variants = array();
	$dist     = array(); // label => [value => true]
	while ( $q->have_posts() ) {
		$q->the_post();
		$vid  = get_the_ID();
		$code = ricoman_pf_get( $vid, 'part_code' );
		if ( '' === (string) $code ) {
			$code = ricoman_pf_get( $vid, 'order_code' );
		}
		$img = ricoman_pf_imgurl( ricoman_pf_get( $vid, 'product_main_image' ) );
		if ( ! $img ) {
			$img = ricoman_pf_imgurl( ricoman_pf_get( $vid, 'product_gallery_image' ) );
		}
		if ( ! $img ) {
			$img = $parent_img;
		}
		$pairs = ricoman_variant_spec_pairs( $vid );
		if ( $is_family ) {
			$vpar = (int) ricoman_pf_get( $vid, 'parent_product' );
			if ( isset( $type_labels[ $vpar ] ) ) {
				$pairs = array( 'Type' => $type_labels[ $vpar ] ) + $pairs;
			}
		}
		foreach ( $pairs as $label => $v ) {
			if ( '' !== (string) $v ) {
				$dist[ $label ][ (string) $v ] = true;
			}
		}
		$variants[] = array(
			'code'  => (string) $code,
			'img'   => $img,
			'ds'    => function_exists( 'ricoman_variant_datasheet_url' ) ? ricoman_variant_datasheet_url( $vid, $pid ) : '',
			'ldt'   => ricoman_pf_fileurl( ricoman_pf_get( $vid, 'download_led' ) ),
			'pairs' => $pairs,
		);
	}
	wp_reset_postdata();
	if ( ! $variants ) {
		return '';
	}

	// Axes = labels with more than one distinct value (Type first for families).
	$labels = array();
	if ( $is_family && isset( $dist['Type'] ) && count( $dist['Type'] ) > 1 ) {
		$labels[] = 'Type';
	}
	foreach ( $order as $label ) {
		if ( 'Type' === $label ) {
			continue;
		}
		if ( isset( $dist[ $label ] ) && count( $dist[ $label ] ) > 1 ) {
			$labels[] = $label;
		}
	}
	if ( ! $labels ) {
		return ''; // nothing meaningful to configure — use the table instead.
	}
	$slug = function ( $l ) {
		return preg_replace( '/[^a-z0-9]+/', '-', strtolower( $l ) );
	};

	$axes = array();
	foreach ( $labels as $label ) {
		$vals = array_keys( $dist[ $label ] );
		natcasesort( $vals );
		$vals = array_values( $vals );
		$imgs = array();
		$set  = array();
		foreach ( $vals as $v ) {
			$rep = '';
			foreach ( $variants as $vt ) {
				if ( isset( $vt['pairs'][ $label ] ) && (string) $vt['pairs'][ $label ] === $v && $vt['img'] ) {
					$rep = $vt['img'];
					break;
				}
			}
			$imgs[ $v ] = $rep;
			if ( $rep ) {
				$set[ $rep ] = true;
			}
		}
		$image_based = count( $set ) > 1; // images differ across this axis = worth showing.
		$opts        = array();
		foreach ( $vals as $v ) {
			$opts[] = array( 'v' => $v, 'img' => $image_based ? $imgs[ $v ] : '' );
		}
		$axes[] = array( 'key' => $slug( $label ), 'label' => $label, 'image' => $image_based, 'options' => $opts );
	}

	$vout = array();
	foreach ( $variants as $vt ) {
		$vals = array();
		foreach ( $axes as $ax ) {
			$vals[ $ax['key'] ] = isset( $vt['pairs'][ $ax['label'] ] ) ? (string) $vt['pairs'][ $ax['label'] ] : '';
		}
		$vout[] = array( 'code' => $vt['code'], 'img' => $vt['img'], 'ds' => $vt['ds'], 'ldt' => $vt['ldt'], 'vals' => $vals, 'specs' => $vt['pairs'] );
	}
	$data = wp_json_encode( array( 'axes' => $axes, 'variants' => $vout ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

	return '<div class="rm-vcfg" data-fallback="' . esc_attr( $parent_img ) . '">'
		. '<script type="application/json" class="rm-vcfg-data">' . $data . '</script>'
		. '<div class="rm-vcfg-steps"></div>'
		. '<div class="rm-vcfg-result" hidden></div>'
		. '</div>';
}

/** Enqueue the configurator script on product pages. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'product' ) ) {
		return;
	}
	wp_enqueue_script(
		'ricoman-visual-config',
		get_theme_file_uri( 'assets/js/visual-config.js' ),
		array(),
		function_exists( 'ricoman_asset_ver' ) ? ricoman_asset_ver( 'assets/js/visual-config.js' ) : false,
		true
	);
} );

/* ---------------------------------------------------------------------------
 * Per-product toggle: table vs visual configurator.
 * ------------------------------------------------------------------------- */
add_action( 'add_meta_boxes_product', function () {
	add_meta_box( 'ricoman_config_style', __( 'Configure display', 'ricoman' ), 'ricoman_config_style_box', 'product', 'side', 'default' );
} );

function ricoman_config_style_box( $post ) {
	wp_nonce_field( 'ricoman_config_style', 'ricoman_config_style_nonce' );
	$visual = ricoman_pf_visual_config_enabled( $post->ID );
	echo '<p class="description">' . esc_html__( 'How customers pick a variant in the “Configure Your Product” section.', 'ricoman' ) . '</p>';
	echo '<p><label><input type="radio" name="ricoman_config_visual" value="0"' . checked( ! $visual, true, false ) . '> ' . esc_html__( 'Table with filters (default)', 'ricoman' ) . '</label></p>';
	echo '<p><label><input type="radio" name="ricoman_config_visual" value="1"' . checked( $visual, true, false ) . '> ' . esc_html__( 'Visual configurator (tap-through tiles)', 'ricoman' ) . '</label></p>';
}

add_action( 'save_post_product', function ( $pid ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['ricoman_config_style_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ricoman_config_style_nonce'] ) ), 'ricoman_config_style' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $pid ) ) {
		return;
	}
	if ( isset( $_POST['ricoman_config_visual'] ) && '1' === (string) $_POST['ricoman_config_visual'] ) {
		update_post_meta( $pid, '_ricoman_config_visual', '1' );
	} else {
		delete_post_meta( $pid, '_ricoman_config_visual' );
	}
	update_option( 'rm_products_ver', (string) time(), false );
} );

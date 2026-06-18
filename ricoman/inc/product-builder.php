<?php
/**
 * Product Builder — RICOBOT spec sync + render shortcodes.
 *
 * Powers the "Sync specs from RICOBOT" button in the Product Builder meta box
 * (maps a RICOBOT product record onto the spec fields) and renders the extra
 * product-only fields (tagline, lead time, key features, finishes) on the
 * product templates via shortcodes.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- RICOBOT → product fields mapper ---- */
function ricoman_ricobot_map( $d ) {
	if ( isset( $d['product'] ) && is_array( $d['product'] ) ) {
		$d = $d['product'];
	}
	$grab = function ( $keys ) use ( $d ) {
		foreach ( (array) $keys as $k ) {
			if ( isset( $d[ $k ] ) && is_scalar( $d[ $k ] ) && '' !== (string) $d[ $k ] ) {
				return (string) $d[ $k ];
			}
		}
		return '';
	};
	$out = array(
		'_ricoman_sku'        => $grab( array( 'code', 'sku', 'orderCode' ) ),
		'_ricoman_wattage'    => $grab( array( 'wattage', 'watts', 'power' ) ),
		'_ricoman_lumens'     => $grab( array( 'lumens', 'output', 'lumenOutput' ) ),
		'_ricoman_efficacy'   => $grab( array( 'efficacy', 'lmw' ) ),
		'_ricoman_cct'        => $grab( array( 'cct', 'colourTemperature', 'colorTemperature' ) ),
		'_ricoman_cri'        => $grab( array( 'cri', 'ra' ) ),
		'_ricoman_beam'       => $grab( array( 'beam', 'beamAngle' ) ),
		'_ricoman_ip'         => $grab( array( 'ip', 'ipRating' ) ),
		'_ricoman_dimensions' => $grab( array( 'dimensions', 'size' ) ),
		'_ricoman_warranty'   => $grab( array( 'warranty' ) ),
		'_ricoman_datasheet'  => $grab( array( 'datasheetUrl', 'datasheet', 'datasheet_url' ) ),
		'_ricoman_tagline'    => $grab( array( 'tagline', 'strapline', 'summary' ) ),
		'_ricoman_lead'       => $grab( array( 'leadTime', 'availability', 'lead_time' ) ),
	);
	// Generic specs array fallback: [{label/name, value}, ...].
	if ( isset( $d['specs'] ) && is_array( $d['specs'] ) ) {
		$match = array(
			'watt'   => '_ricoman_wattage',
			'lumen'  => '_ricoman_lumens',
			'output' => '_ricoman_lumens',
			'efficac'=> '_ricoman_efficacy',
			'cct'    => '_ricoman_cct',
			'colour' => '_ricoman_cct',
			'cri'    => '_ricoman_cri',
			'beam'   => '_ricoman_beam',
			'ip'     => '_ricoman_ip',
			'dimens' => '_ricoman_dimensions',
			'warrant'=> '_ricoman_warranty',
		);
		foreach ( $d['specs'] as $s ) {
			$label = strtolower( (string) ( isset( $s['label'] ) ? $s['label'] : ( isset( $s['name'] ) ? $s['name'] : '' ) ) );
			$value = (string) ( isset( $s['value'] ) ? $s['value'] : '' );
			if ( '' === $value ) {
				continue;
			}
			foreach ( $match as $needle => $field ) {
				if ( '' === $out[ $field ] && false !== strpos( $label, $needle ) ) {
					$out[ $field ] = $value;
				}
			}
		}
	}
	if ( isset( $d['features'] ) && is_array( $d['features'] ) ) {
		$out['_ricoman_features'] = implode( "\n", array_map( 'strval', $d['features'] ) );
	}
	if ( isset( $d['finishes'] ) && is_array( $d['finishes'] ) ) {
		$out['_ricoman_finishes'] = implode( "\n", array_map( 'strval', $d['finishes'] ) );
	}
	return array_filter( $out, function ( $v ) { return '' !== $v; } );
}

/* ---- AJAX: list RICOBOT products (for the link dropdown) ---- */
add_action( 'wp_ajax_ricoman_ricobot_list', function () {
	check_ajax_referer( 'ricoman_rb_sync', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Not allowed.' );
	}
	if ( ! function_exists( 'ricoman_ricobot_products' ) ) {
		wp_send_json_error( 'RICOBOT client unavailable.' );
	}
	// Page through the whole catalogue (731 products) — the list endpoint paginates.
	$list  = array();
	$page  = 1;
	$guard = 0;
	do {
		$data = ricoman_ricobot_get( 'api/public/products?page=' . $page );
		if ( is_wp_error( $data ) ) {
			if ( 1 === $page ) {
				wp_send_json_error( $data->get_error_message() );
			}
			break;
		}
		$items = ( isset( $data['products'] ) && is_array( $data['products'] ) ) ? $data['products'] : ( is_array( $data ) ? $data : array() );
		$list  = array_merge( $list, $items );
		$total = isset( $data['total'] ) ? (int) $data['total'] : count( $list );
		$page++;
		$guard++;
	} while ( count( $items ) > 0 && count( $list ) < $total && $guard < 50 );

	$out = array();
	foreach ( $list as $p ) {
		$code = isset( $p['code'] ) ? $p['code'] : ( isset( $p['sku'] ) ? $p['sku'] : '' );
		if ( '' === $code ) {
			continue;
		}
		$name   = isset( $p['name'] ) ? $p['name'] : $code;
		$family = isset( $p['family'] ) ? $p['family'] : '';
		$out[]  = array( 'code' => (string) $code, 'name' => (string) $name, 'family' => (string) $family );
	}
	wp_send_json_success( $out );
} );

/* ---- AJAX: sync from RICOBOT ---- */
add_action( 'wp_ajax_ricoman_ricobot_sync', function () {
	check_ajax_referer( 'ricoman_rb_sync', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Not allowed.' );
	}
	$sku = isset( $_POST['sku'] ) ? sanitize_text_field( wp_unslash( $_POST['sku'] ) ) : '';
	if ( '' === $sku ) {
		wp_send_json_error( 'Enter an Order code / SKU first.' );
	}
	if ( ! function_exists( 'ricoman_ricobot_product' ) ) {
		wp_send_json_error( 'RICOBOT client unavailable.' );
	}
	$data = ricoman_ricobot_product( $sku );
	if ( is_wp_error( $data ) ) {
		wp_send_json_error( $data->get_error_message() );
	}
	$map = ricoman_ricobot_map( $data );
	if ( empty( $map ) ) {
		wp_send_json_error( 'Connected, but no matching fields found for that code.' );
	}
	wp_send_json_success( $map );
} );

/* ---- Render shortcodes for the extra fields ---- */
add_shortcode( 'ricoman_product_tagline', function () {
	$v = get_post_meta( get_the_ID(), '_ricoman_tagline', true );
	return $v ? '<p class="rm-prod-tagline">' . esc_html( $v ) . '</p>' : '';
} );

add_shortcode( 'ricoman_product_lead', function () {
	$v = get_post_meta( get_the_ID(), '_ricoman_lead', true );
	return $v ? '<p class="rm-prod-lead"><span class="dot"></span>' . esc_html( $v ) . '</p>' : '';
} );

add_shortcode( 'ricoman_product_features', function () {
	$raw = (string) get_post_meta( get_the_ID(), '_ricoman_features', true );
	$lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $raw ) ) );
	if ( ! $lines ) {
		return '';
	}
	$out = '<div class="rm-prod-features"><h3 class="rm-shead">Key features</h3><ul class="rm-ul">';
	foreach ( $lines as $l ) {
		$out .= '<li>' . esc_html( $l ) . '</li>';
	}
	return $out . '</ul></div>';
} );

add_shortcode( 'ricoman_product_downloads', function () {
	if ( ! function_exists( 'ricoman_parse_pairs' ) ) {
		return '';
	}
	$rows = ricoman_parse_pairs( (string) get_post_meta( get_the_ID(), '_ricoman_downloads', true ) );
	if ( ! $rows ) {
		return '';
	}
	$out = '<div class="rm-prod-downloads"><h3 class="rm-shead">Downloads</h3><ul>';
	foreach ( $rows as $r ) {
		$ext = strtoupper( pathinfo( wp_parse_url( $r[1], PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$out .= '<li><a href="' . esc_url( $r[1] ) . '" target="_blank" rel="noopener"><span class="dl-name">' . esc_html( $r[0] ) . '</span>' . ( $ext ? '<span class="dl-ext">' . esc_html( $ext ) . '</span>' : '' ) . '<span class="dl-arrow">↓</span></a></li>';
	}
	return $out . '</ul></div>';
} );

add_shortcode( 'ricoman_product_finishes', function () {
	$raw = (string) get_post_meta( get_the_ID(), '_ricoman_finishes', true );
	$items = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $raw ) ) );
	if ( ! $items ) {
		return '';
	}
	$out = '<div class="rm-prod-finishes"><h3 class="rm-shead">Finishes</h3><div class="chips">';
	foreach ( $items as $i ) {
		$out .= '<span class="chip">' . esc_html( $i ) . '</span>';
	}
	return $out . '</div></div>';
} );

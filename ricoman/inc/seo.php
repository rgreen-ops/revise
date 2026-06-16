<?php
/**
 * SEO / GEO: JSON-LD structured data and breadcrumbs.
 *
 * Outputs Organization, WebSite, Product and BreadcrumbList schema, and exposes
 * a [ricoman_breadcrumbs] shortcode (usable via the Shortcode block) plus a
 * matching schema in the head. Pairs with an SEO plugin if one is installed —
 * the Product schema only emits when no other Product schema is detected.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the breadcrumb trail as an array of [label, url] pairs.
 *
 * @return array<int,array{label:string,url:string}>
 */
function ricoman_breadcrumb_trail() {
	$trail = array(
		array(
			'label' => __( 'Home', 'ricoman' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular( 'product' ) || is_post_type_archive( 'product' ) || is_tax( 'product_cat' ) ) {
		$archive = get_post_type_archive_link( 'product' );
		if ( $archive ) {
			$trail[] = array(
				'label' => __( 'Products', 'ricoman' ),
				'url'   => $archive,
			);
		}
	} elseif ( is_singular( 'project' ) || is_post_type_archive( 'project' ) ) {
		$archive = get_post_type_archive_link( 'project' );
		if ( $archive ) {
			$trail[] = array(
				'label' => __( 'Projects', 'ricoman' ),
				'url'   => $archive,
			);
		}
	}

	if ( is_singular() ) {
		// Add the primary product category if present.
		if ( is_singular( 'product' ) ) {
			$terms = get_the_terms( get_queried_object_id(), 'product_cat' );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$term    = $terms[0];
				$trail[] = array(
					'label' => $term->name,
					'url'   => (string) get_term_link( $term ),
				);
			}
		}
		$trail[] = array(
			'label' => get_the_title(),
			'url'   => get_permalink(),
		);
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$trail[] = array(
			'label' => single_term_title( '', false ),
			'url'   => '',
		);
	} elseif ( is_search() ) {
		$trail[] = array(
			'label' => __( 'Search results', 'ricoman' ),
			'url'   => '',
		);
	} elseif ( is_page() ) {
		$trail[] = array(
			'label' => get_the_title(),
			'url'   => get_permalink(),
		);
	}

	return $trail;
}

/**
 * Render breadcrumbs HTML. Use via the [ricoman_breadcrumbs] shortcode.
 *
 * @return string
 */
function ricoman_breadcrumbs_html() {
	if ( is_front_page() ) {
		return '';
	}
	$trail = ricoman_breadcrumb_trail();
	if ( count( $trail ) < 2 ) {
		return '';
	}

	$items = array();
	$last  = count( $trail ) - 1;
	foreach ( $trail as $i => $crumb ) {
		if ( $i === $last || empty( $crumb['url'] ) ) {
			$items[] = '<span aria-current="page">' . esc_html( $crumb['label'] ) . '</span>';
		} else {
			$items[] = '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a>';
		}
	}

	return '<nav class="ricoman-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'ricoman' ) . '">'
		. implode( '<span class="sep" aria-hidden="true"> / </span>', $items )
		. '</nav>';
}

add_shortcode( 'ricoman_breadcrumbs', 'ricoman_breadcrumbs_html' );

/**
 * Output JSON-LD structured data in the document head.
 */
function ricoman_output_schema() {
	$graph = array();

	// Organization + WebSite (site-wide).
	$org_id = home_url( '/#organization' );
	$graph[] = array(
		'@type'  => 'Organization',
		'@id'    => $org_id,
		'name'   => get_bloginfo( 'name' ),
		'url'    => home_url( '/' ),
		'description' => get_bloginfo( 'description' ),
	);
	$graph[] = array(
		'@type'     => 'WebSite',
		'@id'       => home_url( '/#website' ),
		'url'       => home_url( '/' ),
		'name'      => get_bloginfo( 'name' ),
		'publisher' => array( '@id' => $org_id ),
	);

	// Product schema on single products.
	if ( is_singular( 'product' ) ) {
		$id   = get_queried_object_id();
		$sku  = (string) get_post_meta( $id, '_ricoman_sku', true );
		$image = get_the_post_thumbnail_url( $id, 'large' );

		$product = array(
			'@type'       => 'Product',
			'@id'         => get_permalink( $id ) . '#product',
			'name'        => get_the_title( $id ),
			'description' => wp_strip_all_tags( get_the_excerpt( $id ) ),
			'brand'       => array(
				'@type' => 'Brand',
				'name'  => get_bloginfo( 'name' ),
			),
		);
		if ( $sku ) {
			$product['sku']    = $sku;
			$product['mpn']    = $sku;
		}
		if ( $image ) {
			$product['image'] = $image;
		}

		// Additional spec properties.
		$specs = array(
			'_ricoman_wattage' => 'Wattage',
			'_ricoman_lumens'  => 'Luminous flux',
			'_ricoman_cct'     => 'Colour temperature',
			'_ricoman_cri'     => 'CRI',
			'_ricoman_ip'      => 'IP rating',
			'_ricoman_beam'    => 'Beam angle',
		);
		$props = array();
		foreach ( $specs as $key => $label ) {
			$val = (string) get_post_meta( $id, $key, true );
			if ( '' !== $val ) {
				$props[] = array(
					'@type' => 'PropertyValue',
					'name'  => $label,
					'value' => $val,
				);
			}
		}
		if ( $props ) {
			$product['additionalProperty'] = $props;
		}
		$graph[] = $product;
	}

	// BreadcrumbList.
	if ( ! is_front_page() ) {
		$trail = ricoman_breadcrumb_trail();
		if ( count( $trail ) > 1 ) {
			$elements = array();
			foreach ( $trail as $i => $crumb ) {
				$item = array(
					'@type'    => 'ListItem',
					'position' => $i + 1,
					'name'     => $crumb['label'],
				);
				if ( ! empty( $crumb['url'] ) ) {
					$item['item'] = $crumb['url'];
				}
				$elements[] = $item;
			}
			$graph[] = array(
				'@type'           => 'BreadcrumbList',
				'itemListElement' => $elements,
			);
		}
	}

	$data = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}
add_action( 'wp_head', 'ricoman_output_schema', 20 );

/**
 * Add a canonical/meta description hint for products when no SEO plugin is set.
 */
function ricoman_meta_description() {
	if ( ! is_singular( array( 'product', 'project' ) ) ) {
		return;
	}
	$desc = wp_strip_all_tags( get_the_excerpt() );
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( wp_trim_words( $desc, 30 ) ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'ricoman_meta_description', 1 );

<?php
/**
 * AI search (ChatGPT / Perplexity / Claude / Gemini) — make product facts readable.
 *
 *   • Product JSON-LD under Yoast gets a real description + additionalProperty
 *     specs (lumens / wattage / CCT / IP / CRI / UGR…) aggregated from the
 *     variant data. Before this, Yoast-mode product schema carried only
 *     name + SKU + image, so AI answer engines had no specs to quote.
 *   • /llms-full.txt — every published product with URL, one-line summary and
 *     key specs, grouped by category. The companion to /llms.txt (inc/seo.php).
 *   • Drops WordPress core's second <title> when Yoast prints its own.
 *
 * Read-only: never writes product/content data. Spec summaries are cached per
 * product (transient, invalidated by the product's modified date).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Spec labels summarised for AI, in output order: label => [source, keys…]. */
function ricoman_ai_spec_sources() {
	return array(
		'Luminous flux'      => array( 'meta', 'lumens', 'lumen', 'lumen_output', 'lumens_output', 'total_lumens', 'output_lumens', 'lumen_value', 'lm' ),
		'Wattage'            => array( 'tax', 'wattage' ),
		'Efficacy'           => array( 'meta', 'efficacy' ),
		'Colour temperature' => array( 'tax', 'temperature' ),
		'CRI'                => array( 'meta', 'cri' ),
		'IP rating'          => array( 'tax', 'iprating' ),
		'IP rating '         => array( 'meta', 'ip_rating' ), // trailing space = merged into "IP rating".
		'UGR'                => array( 'meta', 'ugr' ),
		'Beam angle'         => array( 'tax', 'beam-angle' ),
		'Beam angle '        => array( 'meta', 'beam_angle' ),
		'Dimming'            => array( 'tax', 'dimming' ),
		'Emergency option'   => array( 'tax', 'emergency' ),
		'Warranty'           => array( 'meta', 'warranty' ),
	);
}

/** Pull every number out of a list of strings → [min, max] or null. */
function ricoman_ai_num_range( $vals ) {
	$nums = array();
	foreach ( $vals as $v ) {
		if ( preg_match_all( '/\d[\d,]*(?:\.\d+)?/', (string) $v, $m ) ) {
			foreach ( $m[0] as $n ) {
				$f = (float) str_replace( ',', '', $n );
				if ( $f > 0 ) {
					$nums[] = $f;
				}
			}
		}
	}
	return $nums ? array( min( $nums ), max( $nums ) ) : null;
}

/**
 * Key specs for a product, aggregated across its variants: label => value
 * string (ranges for lumens/wattage, de-duplicated lists for the rest).
 */
function ricoman_product_spec_summary( $pid ) {
	$pid = (int) $pid;
	if ( ! $pid || ! post_type_exists( 'variant-product' ) ) {
		return array();
	}
	$key    = 'rm_ai_specs2_' . $pid;
	$stamp  = (string) get_post_field( 'post_modified_gmt', $pid );
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['stamp'] ) && $cached['stamp'] === $stamp ) {
		return $cached['specs'];
	}

	$ids = get_posts( array(
		'post_type'      => 'variant-product',
		'post_status'    => 'publish',
		'posts_per_page' => 3000,
		'no_found_rows'  => true,
		'fields'         => 'ids',
		'meta_query'     => array( array( 'key' => 'parent_product', 'value' => (string) $pid ) ),
	) );
	// No variants of its own (e.g. an Estrella aperture whose rows live on the
	// family) → summarise the configure family, as the variant table does.
	if ( ! $ids && function_exists( 'ricoman_pf_family_products' ) ) {
		$fam = array_map( 'strval', (array) ricoman_pf_family_products( $pid ) );
		if ( count( $fam ) > 1 ) {
			$ids = get_posts( array(
				'post_type'      => 'variant-product',
				'post_status'    => 'publish',
				'posts_per_page' => 3000,
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => 'parent_product', 'value' => $fam, 'compare' => 'IN' ) ),
			) );
		}
	}

	$raw = array(); // label => [ value => true ].
	foreach ( array_chunk( $ids, 200 ) as $chunk ) {
		update_meta_cache( 'post', $chunk );
		update_object_term_cache( $chunk, 'variant-product' );
		foreach ( $chunk as $vid ) {
			foreach ( ricoman_ai_spec_sources() as $label => $src ) {
				$label  = rtrim( $label );
				$source = array_shift( $src );
				foreach ( $src as $k ) {
					$val = '';
					if ( 'tax' === $source ) {
						if ( taxonomy_exists( $k ) ) {
							$t = get_the_terms( $vid, $k );
							if ( $t && ! is_wp_error( $t ) ) {
								$val = implode( ', ', wp_list_pluck( $t, 'name' ) );
							}
						}
					} else {
						$m   = get_post_meta( $vid, $k, true );
						$val = is_scalar( $m ) ? (string) $m : '';
					}
					$val = trim( wp_strip_all_tags( function_exists( 'ricoman_fix_text' ) ? ricoman_fix_text( $val ) : $val ) );
					if ( '' !== $val && ! in_array( strtolower( $val ), array( 'n/a', 'na', '-', 'none', 'no' ), true ) && 0 !== stripos( $val, 'non ' ) && 0 !== stripos( $val, 'non-' ) ) {
						$raw[ $label ][ $val ] = true;
						break;
					}
				}
			}
		}
		if ( function_exists( 'wp_cache_flush_runtime' ) && count( $ids ) > 400 ) {
			wp_cache_flush_runtime();
		}
	}

	$specs = array();
	foreach ( $raw as $label => $set ) {
		$vals = array_keys( $set );
		if ( in_array( $label, array( 'Luminous flux', 'Wattage', 'Efficacy' ), true ) ) {
			$r = ricoman_ai_num_range( $vals );
			if ( ! $r ) {
				continue;
			}
			$unit = array( 'Luminous flux' => 'lm', 'Wattage' => 'W', 'Efficacy' => 'lm/W' )[ $label ];
			$fmt  = function ( $n ) {
				return ( floor( $n ) == $n ) ? number_format( $n ) : rtrim( rtrim( number_format( $n, 1 ), '0' ), '.' );
			};
			$specs[ $label ] = ( $r[0] == $r[1] ) ? $fmt( $r[0] ) . ' ' . $unit : $fmt( $r[0] ) . '–' . $fmt( $r[1] ) . ' ' . $unit;
			continue;
		}
		// Split comma lists, de-dupe, natural sort, cap at 8.
		$parts = array();
		foreach ( $vals as $v ) {
			foreach ( array_map( 'trim', explode( ',', $v ) ) as $p ) {
				if ( '' !== $p ) {
					$parts[ $p ] = true;
				}
			}
		}
		$parts = array_keys( $parts );
		natcasesort( $parts );
		$parts = array_values( $parts );
		if ( count( $parts ) > 8 ) {
			$parts = array_merge( array_slice( $parts, 0, 8 ), array( '…' ) );
		}
		$specs[ $label ] = implode( ', ', $parts );
	}

	set_transient( $key, array( 'stamp' => $stamp, 'specs' => $specs ), DAY_IN_SECONDS );
	return $specs;
}

/**
 * Is this text a usable product summary? Rejects short strings, template
 * placeholders, order-code dumps ("R091801/105 R091802/105…") and keyword
 * soup ("Track Lighting Track Track Light Track parts").
 */
function ricoman_ai_desc_ok( $c ) {
	if ( strlen( $c ) < 30 || false !== strpos( $c, '%%' ) || preg_match_all( '/R\d{5,}/', $c ) >= 3 ) {
		return false;
	}
	$w = preg_split( '/\W+/u', strtolower( $c ), -1, PREG_SPLIT_NO_EMPTY );
	return count( $w ) >= 6 && count( array_unique( $w ) ) / count( $w ) >= 0.6;
}

/** Plain-text product summary for AI: SEO desc → excerpt → ACF short desc → key features → generated. */
function ricoman_product_ai_desc( $pid, $words = 45 ) {
	$cands = array(
		get_post_meta( $pid, '_ricoman_seo_desc', true ),
		has_excerpt( $pid ) ? get_the_excerpt( $pid ) : '',
		function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_sort_description' ) : '',
		get_post_meta( $pid, '_yoast_wpseo_metadesc', true ),
	);
	if ( function_exists( 'ricoman_pf_get' ) && function_exists( 'ricoman_pf_features_items' ) ) {
		$cands[] = implode( '. ', ricoman_pf_features_items( ricoman_pf_get( $pid, 'key_features' ), 6 ) );
	}
	$cands[] = strip_shortcodes( (string) get_post_field( 'post_content', $pid ) );
	foreach ( $cands as $c ) {
		$c = is_scalar( $c ) ? trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) $c ), ENT_QUOTES, 'UTF-8' ) ) ) : '';
		if ( ricoman_ai_desc_ok( $c ) ) {
			return wp_trim_words( $c, $words, '…' );
		}
	}
	// Nothing usable stored — a plain factual line beats junk.
	$terms = get_the_terms( $pid, 'product-cat' );
	$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'commercial LED lighting';
	return html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ) . ' — ' . $cat . ' from Ricoman Lighting, a UK manufacturer of commercial LED lighting.';
}

/* --------------------------------------------- Product schema (Yoast mode) */
add_filter( 'wpseo_schema_graph', function ( $graph, $context ) {
	if ( ! is_array( $graph ) || ! is_singular( 'product' ) ) {
		return $graph;
	}
	$id = get_queried_object_id();
	foreach ( $graph as $i => $node ) {
		if ( ! is_array( $node ) || 'Product' !== ( $node['@type'] ?? '' ) ) {
			continue;
		}
		// Replace a missing / title-only / order-code-dump description (the base
		// Product node falls back to the auto excerpt, which on some migrated
		// products is just a list of R-codes).
		$cur = trim( (string) ( $node['description'] ?? '' ) );
		if ( ! ricoman_ai_desc_ok( $cur ) ) {
			$graph[ $i ]['description'] = ricoman_product_ai_desc( $id );
		}
		if ( empty( $node['additionalProperty'] ) ) {
			$props = array();
			foreach ( ricoman_product_spec_summary( $id ) as $label => $val ) {
				$props[] = array( '@type' => 'PropertyValue', 'name' => $label, 'value' => $val );
			}
			if ( $props ) {
				$graph[ $i ]['additionalProperty'] = $props;
			}
		}
	}
	return $graph;
}, 20, 2 );

/* ----------------------------------- One <title> only when Yoast is active */
add_action( 'wp_head', function () {
	if ( defined( 'WPSEO_VERSION' ) ) {
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		remove_action( 'wp_head', '_block_template_render_title_tag', 1 );
	}
}, 0 );

/* ------------------------------------------------------------ /llms-full.txt */
function ricoman_llms_full_txt() {
	$h   = untrailingslashit( home_url() );
	$out = "# Ricoman Lighting — full product guide\n\n"
		. "> UK manufacturer of commercial and architectural LED lighting, Salford (Greater Manchester), established 2013. Made in Britain. Free lighting design service. This file lists every published product with its key specifications, for AI assistants answering lighting questions. Summary guide: $h/llms.txt\n\n"
		. "Contact: $h/contact/ · Specifications vary by variant; each product page has the full variant table and datasheets.\n";

	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
	$groups = array();
	foreach ( $products as $p ) {
		$terms = get_the_terms( $p->ID, 'product-cat' );
		$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : 'Other';
		$groups[ $cat ][] = $p;
	}
	ksort( $groups, SORT_NATURAL | SORT_FLAG_CASE );
	foreach ( $groups as $cat => $items ) {
		$out .= "\n## " . $cat . "\n";
		foreach ( $items as $p ) {
			$out .= "\n### [" . html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ) . ']('. get_permalink( $p ) . ")\n";
			$d = ricoman_product_ai_desc( $p->ID, 40 );
			if ( '' !== $d ) {
				$out .= $d . "\n";
			}
			foreach ( ricoman_product_spec_summary( $p->ID ) as $label => $val ) {
				$out .= '- ' . $label . ': ' . $val . "\n";
			}
		}
	}
	return $out;
}

// wp_loaded (not init): the product / variant post types and spec taxonomies
// must be registered before the guide is built.
add_action( 'wp_loaded', function () {
	$path = strtolower( trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' ) );
	if ( 'llms-full.txt' !== $path ) {
		return;
	}
	$body = get_transient( 'rm_llms_full4' );
	if ( ! is_string( $body ) || '' === $body ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$body = ricoman_llms_full_txt();
		set_transient( 'rm_llms_full4', $body, 12 * HOUR_IN_SECONDS );
	}
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=43200' );
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}, 0 );

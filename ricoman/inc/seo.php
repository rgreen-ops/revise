<?php
/**
 * SEO / GEO engine for the Ricoman theme.
 *
 * Goals:
 *  - Good SEO automatically, with zero configuration, for every page.
 *  - Editable overrides so content can be optimised over time (per-post SEO
 *    fields + a site-wide SEO settings screen).
 *  - An "improve over time" surface: a SEO status column in the admin lists.
 *  - Plays nice: if a dedicated SEO plugin (Yoast, Rank Math, AIOSEO, SEOPress)
 *    is active, this engine stands down for meta/schema to avoid duplicates and
 *    only keeps the breadcrumbs helper.
 *
 * Outputs (when no SEO plugin is active):
 *  - <title> override, meta description, canonical, robots
 *  - Open Graph + Twitter Card tags (auto image fallback chain)
 *  - JSON-LD @graph: Organization (logo, address, contact, sameAs), WebSite
 *    (+ SearchAction), WebPage/CollectionPage, Product, Article, CreativeWork
 *    (projects), BreadcrumbList, ItemList for archives.
 *  - Sitemap + robots.txt hints.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------- */

/**
 * Is a dedicated SEO plugin handling output already?
 *
 * @return bool
 */
function ricoman_seo_plugin_active() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| function_exists( 'aioseo' );
}

/**
 * Read a site-wide SEO option.
 *
 * @param string $key     Option key.
 * @param string $default Fallback.
 * @return string
 */
function ricoman_seo_opt( $key, $default = '' ) {
	$o = get_option( 'ricoman_seo', array() );
	return ( is_array( $o ) && isset( $o[ $key ] ) && '' !== $o[ $key ] ) ? $o[ $key ] : $default;
}

/**
 * The best available description for the current request.
 *
 * Override (per post) -> excerpt -> trimmed content -> term/archive description
 * -> site tagline.
 *
 * @return string
 */
function ricoman_seo_description() {
	$desc = '';

	if ( is_singular() ) {
		$id = get_queried_object_id();
		$ov = get_post_meta( $id, '_ricoman_seo_desc', true );
		if ( $ov ) {
			return wp_strip_all_tags( $ov );
		}
		$ex = get_the_excerpt( $id );
		if ( $ex ) {
			$desc = $ex;
		} else {
			$content = get_post_field( 'post_content', $id );
			$desc    = strip_shortcodes( wp_strip_all_tags( (string) $content ) );
		}
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		$cat_intro = ( $term && function_exists( 'ricoman_cat_seo' ) ) ? ricoman_cat_seo( $term->term_id, 'intro' ) : '';
		if ( $term && ! empty( $term->description ) ) {
			$desc = $term->description;
		} elseif ( '' !== trim( (string) $cat_intro ) ) {
			$desc = $cat_intro;
		} elseif ( $term ) {
			$desc = sprintf(
				/* translators: %s: taxonomy term name. */
				__( '%s — explore the Ricoman range.', 'ricoman' ),
				single_term_title( '', false )
			);
		}
	} elseif ( is_post_type_archive() ) {
		$obj = get_queried_object();
		if ( $obj && ! empty( $obj->description ) ) {
			$desc = $obj->description;
		} elseif ( $obj && 'product' === $obj->name ) {
			$desc = __( 'Browse Ricoman’s commercial LED lighting ranges — linear, downlights, panels, track, emergency and more. British-made luminaires, held in UK stock and made to order in Manchester.', 'ricoman' );
		} elseif ( $obj && 'project' === $obj->name ) {
			$desc = __( 'Ricoman commercial lighting projects and case studies — offices, retail, education, healthcare, hospitality and industrial schemes.', 'ricoman' );
		}
	} elseif ( is_search() ) {
		$desc = sprintf(
			/* translators: %s: search query. */
			__( 'Search results for “%s”.', 'ricoman' ),
			get_search_query()
		);
	}

	if ( '' === trim( (string) $desc ) ) {
		$desc = get_bloginfo( 'description' );
	}
	// Guarantee a non-empty meta description on every page (an empty one fails
	// the SEO audit). Final brand fallback when nothing else is available.
	if ( '' === trim( (string) $desc ) ) {
		$desc = __( 'Ricoman — British-made commercial LED lighting: linear, downlights, panels, track, emergency and bespoke luminaires for architects and specifiers.', 'ricoman' );
	}

	return wp_trim_words( wp_strip_all_tags( (string) $desc ), 32, '…' );
}

/**
 * Best available share/OG image URL: per-post featured -> site default.
 *
 * @return string
 */
function ricoman_seo_image() {
	if ( is_singular() ) {
		if ( has_post_thumbnail() ) {
			$url = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
			if ( $url ) {
				return $url;
			}
		}
		// No featured image: fall back to the first image in the content, so our
		// block-built pages (feature pages, About, etc.) still get a real social
		// card from their hero image rather than just the logo.
		$content = (string) get_post_field( 'post_content', get_queried_object_id() );
		if ( $content && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
			return $m[1];
		}
	}
	$default = ricoman_seo_opt( 'default_og_image' );
	if ( $default ) {
		return $default;
	}
	$logo = ricoman_seo_opt( 'logo' );
	return $logo ? $logo : '';
}

/**
 * The canonical URL for the current request.
 *
 * @return string
 */
function ricoman_seo_canonical() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_singular() ) {
		return (string) get_permalink( get_queried_object_id() );
	}
	if ( is_tax() || is_category() || is_tag() ) {
		$link = get_term_link( get_queried_object() );
		return is_wp_error( $link ) ? '' : (string) $link;
	}
	if ( is_post_type_archive() ) {
		return (string) get_post_type_archive_link( get_queried_object()->name );
	}
	if ( is_home() ) {
		$blog = (int) get_option( 'page_for_posts' );
		return $blog ? (string) get_permalink( $blog ) : home_url( '/' );
	}
	return '';
}

/**
 * Should the current request be hidden from search engines?
 *
 * @return bool
 */
function ricoman_seo_is_noindex() {
	// Thin / duplicate / low-value listings we don't want in the index. Author and
	// date archives mirror the same posts (single-author marketing site), so they
	// add no value and risk duplicate content — matches dropping them from the
	// sitemap. Search + 404 are never useful index targets.
	if ( is_search() || is_404() || is_author() || is_date() ) {
		return true;
	}
	if ( is_singular() && get_post_meta( get_queried_object_id(), '_ricoman_seo_noindex', true ) ) {
		return true;
	}
	return apply_filters( 'ricoman_seo_is_noindex', false );
}

/* ---------------------------------------------------------------------------
 * Keep NON-PRODUCTION hosts (e.g. staging.ricoman.com) out of search engines
 * without ever affecting the live site.
 * ------------------------------------------------------------------------- */

/**
 * True on a staging / non-production host. Gated on the host containing
 * "staging" so the LIVE site (ricoman.com) can NEVER match — its indexing is
 * left completely untouched. Adjustable via the `ricoman_is_staging` filter.
 */
function ricoman_is_staging_site() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
	$is   = ( '' !== $host && false !== strpos( $host, 'staging' ) );
	return (bool) apply_filters( 'ricoman_is_staging', $is );
}

// Universal, plugin-independent noindex header on every staging response
// (covers HTML, PDFs, feeds — everything — and works even with an SEO plugin).
add_action( 'send_headers', function () {
	if ( ricoman_is_staging_site() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
} );

// Force the theme's own robots meta to noindex on staging.
add_filter( 'ricoman_seo_is_noindex', function ( $v ) {
	return ricoman_is_staging_site() ? true : $v;
} );

// Force WordPress core's wp_robots() meta to noindex on staging.
add_filter( 'wp_robots', function ( $robots ) {
	if ( ricoman_is_staging_site() ) {
		unset( $robots['index'], $robots['follow'], $robots['max-image-preview'], $robots['max-snippet'] );
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

// Force Yoast's robots meta to noindex on staging (only if Yoast is active).
add_filter( 'wpseo_robots_array', function ( $robots ) {
	if ( ricoman_is_staging_site() && is_array( $robots ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'nofollow';
	}
	return $robots;
} );

// Most reliable of all: report the staging site as "not public" (the same switch
// as Settings -> Reading -> "Discourage search engines"). WordPress core AND Yoast
// both emit a noindex robots meta via their own native logic when this is off, so
// the on-page meta matches the header regardless of which plugin renders it. This
// is a read-time filter only (nothing is written to the database) and is strictly
// host-gated, so the live site is never affected.
add_filter( 'pre_option_blog_public', function ( $pre ) {
	return ricoman_is_staging_site() ? '0' : $pre;
} );

// ...but keep staging CRAWLABLE. blog_public=0 would otherwise make WordPress serve
// a "Disallow: /" robots.txt, which blocks search engines from fetching the page,
// seeing the noindex, and dropping any existing listing. Allow crawling so the
// noindex can actually do its job.
add_filter( 'robots_txt', function ( $output ) {
	return ricoman_is_staging_site() ? "User-agent: *\nAllow: /\n" : $output;
}, 99 );

/* ---------------------------------------------------------------------------
 * Document title
 * ------------------------------------------------------------------------- */

add_filter( 'document_title_separator', function () {
	return '·';
} );

add_filter( 'pre_get_document_title', function ( $title ) {
	if ( ricoman_seo_plugin_active() ) {
		return $title;
	}
	if ( is_singular() ) {
		$ov = get_post_meta( get_queried_object_id(), '_ricoman_seo_title', true );
		if ( $ov ) {
			return $ov;
		}
	}
	return $title;
} );

/* When an SEO plugin (Yoast) is active but produces an EMPTY meta description
 * for a page — e.g. the product/project archives, which Yoast doesn't template
 * by default — fill it with the theme's generated description so no page ships
 * without one. Only fills empties; never overrides a description Yoast set. */
add_filter( 'wpseo_metadesc', 'ricoman_seo_fill_metadesc' );
add_filter( 'wpseo_opengraph_desc', 'ricoman_seo_fill_metadesc' );
function ricoman_seo_fill_metadesc( $desc ) {
	if ( '' !== trim( (string) $desc ) ) {
		return $desc;
	}
	$d = function_exists( 'ricoman_seo_description' ) ? ricoman_seo_description() : '';
	return $d ? $d : $desc;
}

/* ---------------------------------------------------------------------------
 * <head> meta: description, canonical, robots, Open Graph, Twitter
 * ------------------------------------------------------------------------- */

function ricoman_seo_head_meta() {
	if ( ricoman_seo_plugin_active() ) {
		return;
	}

	$out = array();

	// Robots.
	if ( ricoman_seo_is_noindex() ) {
		$out[] = '<meta name="robots" content="noindex,follow">';
	} else {
		$out[] = '<meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1">';
	}

	// Description.
	$desc = ricoman_seo_description();
	if ( $desc ) {
		$out[] = '<meta name="description" content="' . esc_attr( $desc ) . '">';
	}

	// Canonical.
	$canonical = ricoman_seo_canonical();
	if ( $canonical ) {
		$out[] = '<link rel="canonical" href="' . esc_url( $canonical ) . '">';
	}

	// Open Graph.
	$type = 'website';
	if ( is_singular( array( 'post', 'news' ) ) ) {
		$type = 'article';
	} elseif ( is_singular( 'product' ) ) {
		$type = 'product';
	}
	$title = wp_get_document_title();
	$img   = ricoman_seo_image();

	$out[] = '<meta property="og:type" content="' . esc_attr( $type ) . '">';
	$out[] = '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">';
	$out[] = '<meta property="og:title" content="' . esc_attr( $title ) . '">';
	if ( $desc ) {
		$out[] = '<meta property="og:description" content="' . esc_attr( $desc ) . '">';
	}
	if ( $canonical ) {
		$out[] = '<meta property="og:url" content="' . esc_url( $canonical ) . '">';
	}
	$out[] = '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">';
	if ( $img ) {
		$out[] = '<meta property="og:image" content="' . esc_url( $img ) . '">';
	}

	// Article timestamps.
	if ( is_singular( array( 'post', 'news' ) ) ) {
		$id      = get_queried_object_id();
		$out[]   = '<meta property="article:published_time" content="' . esc_attr( get_the_date( 'c', $id ) ) . '">';
		$out[]   = '<meta property="article:modified_time" content="' . esc_attr( get_the_modified_date( 'c', $id ) ) . '">';
	}

	// Twitter.
	$out[] = '<meta name="twitter:card" content="' . ( $img ? 'summary_large_image' : 'summary' ) . '">';
	$twitter = ricoman_seo_opt( 'twitter' );
	if ( $twitter ) {
		$handle = '@' . ltrim( $twitter, '@' );
		$out[]  = '<meta name="twitter:site" content="' . esc_attr( $handle ) . '">';
	}
	$out[] = '<meta name="twitter:title" content="' . esc_attr( $title ) . '">';
	if ( $desc ) {
		$out[] = '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">';
	}
	if ( $img ) {
		$out[] = '<meta name="twitter:image" content="' . esc_url( $img ) . '">';
	}

	// Search Console verification.
	$gv = ricoman_seo_opt( 'google_verification' );
	if ( $gv ) {
		$out[] = '<meta name="google-site-verification" content="' . esc_attr( $gv ) . '">';
	}

	echo "\n<!-- Ricoman SEO -->\n" . implode( "\n", $out ) . "\n";
}
add_action( 'wp_head', 'ricoman_seo_head_meta', 1 );

/* ---------------------------------------------------------------------------
 * Breadcrumbs (trail + HTML + shortcode)
 * ------------------------------------------------------------------------- */

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

	if ( is_singular( 'product' ) || is_post_type_archive( 'product' ) || is_tax( 'product-cat' ) ) {
		$archive = get_post_type_archive_link( 'product' );
		if ( $archive ) {
			$trail[] = array(
				'label' => __( 'Products', 'ricoman' ),
				'url'   => $archive,
			);
		}
	} elseif ( is_tax( 'project-cat' ) ) {
		// Sector pages sit under the "Lighting by Sector" hub.
		$hub = get_page_by_path( 'sectors' );
		$trail[] = array(
			'label' => __( 'Sectors', 'ricoman' ),
			'url'   => $hub ? get_permalink( $hub ) : home_url( '/sectors/' ),
		);
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
		if ( is_singular( 'product' ) ) {
			$terms = get_the_terms( get_queried_object_id(), 'product-cat' );
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

/* ---------------------------------------------------------------------------
 * JSON-LD structured data
 * ------------------------------------------------------------------------- */

function ricoman_output_schema() {
	if ( ricoman_seo_plugin_active() ) {
		return;
	}

	$graph  = array();
	$org_id = home_url( '/#organization' );
	$site_id = home_url( '/#website' );

	// Organization.
	$org = array(
		'@type'       => 'Organization',
		'@id'         => $org_id,
		'name'        => get_bloginfo( 'name' ),
		'url'         => home_url( '/' ),
		'description' => get_bloginfo( 'description' ),
	);
	$logo = ricoman_seo_opt( 'logo' );
	if ( ! $logo && has_custom_logo() ) {
		$logo = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}
	if ( $logo ) {
		$org['logo'] = array(
			'@type' => 'ImageObject',
			'url'   => $logo,
		);
	}
	$sameas = array_filter( array_map( 'trim', explode( "\n", (string) ricoman_seo_opt( 'social' ) ) ) );
	if ( $sameas ) {
		$org['sameAs'] = array_values( $sameas );
	}
	$phone = ricoman_seo_opt( 'phone' );
	$email = ricoman_seo_opt( 'email' );
	if ( $phone || $email ) {
		$cp = array(
			'@type'       => 'ContactPoint',
			'contactType' => 'customer service',
		);
		if ( $phone ) {
			$cp['telephone'] = $phone;
		}
		if ( $email ) {
			$cp['email'] = $email;
		}
		$org['contactPoint'] = $cp;
	}
	$street = ricoman_seo_opt( 'street' );
	if ( $street ) {
		$org['address'] = array_filter( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'addressLocality' => ricoman_seo_opt( 'locality' ),
			'addressRegion'   => ricoman_seo_opt( 'region' ),
			'postalCode'      => ricoman_seo_opt( 'postcode' ),
			'addressCountry'  => ricoman_seo_opt( 'country', 'GB' ),
		) );
	}
	$founding = ricoman_seo_opt( 'founding' );
	if ( $founding ) {
		$org['foundingDate'] = $founding;
	}
	$graph[] = $org;

	// WebSite + search action.
	$graph[] = array(
		'@type'           => 'WebSite',
		'@id'             => $site_id,
		'url'             => home_url( '/' ),
		'name'            => get_bloginfo( 'name' ),
		'publisher'       => array( '@id' => $org_id ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => home_url( '/?s={search_term_string}' ),
			),
			'query-input' => 'required name=search_term_string',
		),
	);

	// LocalBusiness (local SEO / map results) — only when an address is set.
	if ( ricoman_seo_opt( 'street' ) ) {
		$lb = array(
			'@type'    => 'LocalBusiness',
			'@id'      => home_url( '/#localbusiness' ),
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'parentOrganization' => array( '@id' => $org_id ),
			'address'  => array_filter( array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => ricoman_seo_opt( 'street' ),
				'addressLocality' => ricoman_seo_opt( 'locality' ),
				'addressRegion'   => ricoman_seo_opt( 'region' ),
				'postalCode'      => ricoman_seo_opt( 'postcode' ),
				'addressCountry'  => ricoman_seo_opt( 'country', 'GB' ),
			) ),
		);
		if ( ricoman_seo_opt( 'phone' ) ) {
			$lb['telephone'] = ricoman_seo_opt( 'phone' );
		}
		if ( $logo ) {
			$lb['image'] = $logo;
		}
		if ( ricoman_seo_opt( 'lat' ) && ricoman_seo_opt( 'lng' ) ) {
			$lb['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => ricoman_seo_opt( 'lat' ),
				'longitude' => ricoman_seo_opt( 'lng' ),
			);
		}
		$graph[] = $lb;
	}

	// Per-context nodes.
	if ( is_singular( 'product' ) ) {
		$id    = get_queried_object_id();
		$sku   = (string) get_post_meta( $id, '_ricoman_sku', true );
		// Featured image, falling back to the derived product image (migrated
		// products often have no WP featured image — their gallery lives in ACF).
		$image = get_the_post_thumbnail_url( $id, 'large' );
		if ( ! $image && function_exists( 'ricoman_product_img' ) ) {
			$image = ricoman_product_img( $id );
		}

		$product = array(
			'@type'        => 'Product',
			'@id'          => get_permalink( $id ) . '#product',
			'name'         => get_the_title( $id ),
			'description'  => ricoman_seo_description(),
			'url'          => get_permalink( $id ),
			'brand'        => array( '@type' => 'Brand', 'name' => get_bloginfo( 'name' ) ),
			'manufacturer' => array( '@id' => $org_id ),
		);
		if ( $sku ) {
			$product['sku'] = $sku;
			$product['mpn'] = $sku;
		}
		if ( $image ) {
			$product['image'] = $image;
		}
		// Product category (schema 'category') from the real taxonomy.
		$pcats = get_the_terms( $id, 'product-cat' );
		if ( $pcats && ! is_wp_error( $pcats ) ) {
			$product['category'] = $pcats[0]->name;
		}

		$props = array();
		// Hand-entered spec meta (rarely set on the migrated catalogue) first.
		$specs = array(
			'_ricoman_wattage' => 'Wattage',
			'_ricoman_lumens'  => 'Luminous flux',
			'_ricoman_cct'     => 'Colour temperature',
			'_ricoman_cri'     => 'CRI',
			'_ricoman_ip'      => 'IP rating',
			'_ricoman_beam'    => 'Beam angle',
		);
		$have = array();
		foreach ( $specs as $key => $label ) {
			$val = (string) get_post_meta( $id, $key, true );
			if ( '' !== $val ) {
				$props[]        = array( '@type' => 'PropertyValue', 'name' => $label, 'value' => $val );
				$have[ $label ] = true;
			}
		}
		// Fall back to the precomputed catalogue metrics (lumens / watts / features)
		// derived from the migrated variant data, so schema isn't empty.
		if ( function_exists( 'ricoman_pf_metrics' ) ) {
			$mx = ricoman_pf_metrics( $id );
			if ( empty( $have['Luminous flux'] ) && ! empty( $mx['lm'] ) ) {
				$props[] = array( '@type' => 'PropertyValue', 'name' => 'Luminous flux', 'value' => (int) $mx['lm'] . ' lm' );
			}
			if ( empty( $have['Wattage'] ) && ! empty( $mx['w'] ) ) {
				$props[] = array( '@type' => 'PropertyValue', 'name' => 'Wattage', 'value' => (int) $mx['w'] . ' W' );
			}
			foreach ( (array) ( $mx['feats'] ?? array() ) as $feat ) {
				if ( is_string( $feat ) && '' !== $feat ) {
					$props[] = array( '@type' => 'PropertyValue', 'name' => 'Feature', 'value' => $feat );
				}
			}
		}
		if ( $props ) {
			$product['additionalProperty'] = $props;
		}
		$graph[] = $product;

	} elseif ( is_singular( 'project' ) ) {
		$id      = get_queried_object_id();
		$project = array(
			'@type'       => 'CreativeWork',
			'@id'         => get_permalink( $id ) . '#project',
			'name'        => get_the_title( $id ),
			'description' => ricoman_seo_description(),
			'url'         => get_permalink( $id ),
			'creator'     => array( '@id' => $org_id ),
		);
		$image = get_the_post_thumbnail_url( $id, 'large' );
		if ( $image ) {
			$project['image'] = $image;
		}
		$graph[] = $project;

	} elseif ( is_singular( array( 'post', 'news' ) ) ) {
		$id      = get_queried_object_id();
		$article = array(
			'@type'            => 'Article',
			'@id'              => get_permalink( $id ) . '#article',
			'headline'         => get_the_title( $id ),
			'description'      => ricoman_seo_description(),
			'datePublished'    => get_the_date( 'c', $id ),
			'dateModified'     => get_the_modified_date( 'c', $id ),
			'author'           => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) ),
			'publisher'        => array( '@id' => $org_id ),
			'mainEntityOfPage' => get_permalink( $id ),
		);
		$image = get_the_post_thumbnail_url( $id, 'large' );
		if ( $image ) {
			$article['image'] = $image;
		}
		$graph[] = $article;

	} elseif ( ( is_post_type_archive( array( 'product', 'project' ) ) || is_tax() || is_category() || is_tag() ) && have_posts() ) {
		$items = array();
		$pos   = 1;
		global $wp_query;
		foreach ( $wp_query->posts as $p ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'url'      => get_permalink( $p ),
				'name'     => get_the_title( $p ),
			);
		}
		if ( $items ) {
			$graph[] = array(
				'@type'           => 'CollectionPage',
				'@id'             => ricoman_seo_canonical() . '#collection',
				'name'            => wp_get_document_title(),
				'mainEntity'      => array(
					'@type'           => 'ItemList',
					'itemListElement' => $items,
				),
			);
		}
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

/* ---------------------------------------------------------------------------
 * Sitemap + robots.txt
 * ------------------------------------------------------------------------- */

// Make sure the homepage is in the core sitemap and add a Sitemap line to robots.
add_filter( 'robots_txt', function ( $output ) {
	if ( get_option( 'blog_public' ) ) {
		$sitemap = home_url( '/wp-sitemap.xml' );
		if ( false === strpos( $output, 'Sitemap:' ) ) {
			$output .= "\nSitemap: " . esc_url( $sitemap ) . "\n";
		}
	}
	return $output;
}, 20 );

// Drop the author/users sitemap: this is a single-author marketing site, so
// author archives are duplicate, low-value URLs we don't want indexed.
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
	return ( 'users' === $name ) ? false : $provider;
}, 10, 2 );

// Keep noindexed posts (our per-post "Hide from search engines" toggle) out of
// the core sitemap, so the sitemap and the robots meta tag stay consistent.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args ) {
	$exclude = array(
		'relation' => 'OR',
		array( 'key' => '_ricoman_seo_noindex', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_ricoman_seo_noindex', 'value' => '1', 'compare' => '!=' ),
	);
	$args['meta_query'] = empty( $args['meta_query'] )
		? $exclude
		: array( 'relation' => 'AND', $args['meta_query'], $exclude );
	return $args;
} );

/* ---------------------------------------------------------------------------
 * Per-post SEO fields (overrides) — "optimise over time" by hand
 * ------------------------------------------------------------------------- */

function ricoman_seo_post_types() {
	return apply_filters( 'ricoman_seo_post_types', array( 'post', 'news', 'page', 'product', 'project' ) );
}

add_action( 'add_meta_boxes', function () {
	if ( ricoman_seo_plugin_active() ) {
		return;
	}
	foreach ( ricoman_seo_post_types() as $pt ) {
		add_meta_box(
			'ricoman_seo',
			__( 'SEO (Ricoman)', 'ricoman' ),
			'ricoman_seo_metabox',
			$pt,
			'normal',
			'low'
		);
	}
} );

function ricoman_seo_metabox( $post ) {
	wp_nonce_field( 'ricoman_seo_save', 'ricoman_seo_nonce' );
	$title    = (string) get_post_meta( $post->ID, '_ricoman_seo_title', true );
	$desc     = (string) get_post_meta( $post->ID, '_ricoman_seo_desc', true );
	$noindex  = (string) get_post_meta( $post->ID, '_ricoman_seo_noindex', true );
	?>
	<style>.ricoman-seo-field{margin:0 0 14px}.ricoman-seo-field label{font-weight:600;display:block;margin-bottom:4px}.ricoman-seo-field input[type=text],.ricoman-seo-field textarea{width:100%}.ricoman-seo-count{color:#646970;font-size:12px;float:right;font-weight:400}</style>
	<div class="ricoman-seo-field">
		<label for="ricoman_seo_title"><?php esc_html_e( 'SEO title', 'ricoman' ); ?>
			<span class="ricoman-seo-count" id="ricoman_seo_title_c"></span></label>
		<input type="text" id="ricoman_seo_title" name="ricoman_seo_title" value="<?php echo esc_attr( $title ); ?>"
			placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
		<p class="description"><?php esc_html_e( 'Leave blank to use the post title. Aim for ~50–60 characters.', 'ricoman' ); ?></p>
	</div>
	<div class="ricoman-seo-field">
		<label for="ricoman_seo_desc"><?php esc_html_e( 'Meta description', 'ricoman' ); ?>
			<span class="ricoman-seo-count" id="ricoman_seo_desc_c"></span></label>
		<textarea id="ricoman_seo_desc" name="ricoman_seo_desc" rows="3"
			placeholder="<?php esc_attr_e( 'Auto-generated from the excerpt / content if left blank.', 'ricoman' ); ?>"><?php echo esc_textarea( $desc ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Aim for ~120–160 characters.', 'ricoman' ); ?></p>
	</div>
	<div class="ricoman-seo-field">
		<label for="ricoman_seo_focus"><?php esc_html_e( 'Focus keyphrase', 'ricoman' ); ?></label>
		<input type="text" id="ricoman_seo_focus" name="ricoman_seo_focus" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, '_ricoman_seo_focus', true ) ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. linear lighting', 'ricoman' ); ?>">
		<p class="description"><?php esc_html_e( 'The phrase you want this page to rank for. Used to score the page below.', 'ricoman' ); ?></p>
	</div>
	<div id="ricoman_seo_panel"></div>
	<div class="ricoman-seo-field">
		<label><input type="checkbox" name="ricoman_seo_noindex" value="1" <?php checked( $noindex, '1' ); ?>>
			<?php esc_html_e( 'Hide this from search engines (noindex)', 'ricoman' ); ?></label>
	</div>
	<script>
	(function(){
		function bind(id,cid,lo,hi){var el=document.getElementById(id),c=document.getElementById(cid);if(!el||!c)return;
			function u(){var n=el.value.length;c.textContent=n+' chars';c.style.color=(n>=lo&&n<=hi)?'#008a20':(n>hi?'#b32d2e':'#646970');}
			el.addEventListener('input',u);u();}
		bind('ricoman_seo_title','ricoman_seo_title_c',40,60);
		bind('ricoman_seo_desc','ricoman_seo_desc_c',120,160);
	})();
	</script>
	<?php
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['ricoman_seo_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_seo_nonce'] ), 'ricoman_seo_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$title = isset( $_POST['ricoman_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ricoman_seo_title'] ) ) : '';
	$desc  = isset( $_POST['ricoman_seo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ricoman_seo_desc'] ) ) : '';
	$focus = isset( $_POST['ricoman_seo_focus'] ) ? sanitize_text_field( wp_unslash( $_POST['ricoman_seo_focus'] ) ) : '';
	$noidx = ! empty( $_POST['ricoman_seo_noindex'] ) ? '1' : '';

	$title ? update_post_meta( $post_id, '_ricoman_seo_title', $title ) : delete_post_meta( $post_id, '_ricoman_seo_title' );
	$desc ? update_post_meta( $post_id, '_ricoman_seo_desc', $desc ) : delete_post_meta( $post_id, '_ricoman_seo_desc' );
	$focus ? update_post_meta( $post_id, '_ricoman_seo_focus', $focus ) : delete_post_meta( $post_id, '_ricoman_seo_focus' );
	$noidx ? update_post_meta( $post_id, '_ricoman_seo_noindex', '1' ) : delete_post_meta( $post_id, '_ricoman_seo_noindex' );
} );

/* ---------------------------------------------------------------------------
 * "Improve over time": SEO status column in admin lists
 * ------------------------------------------------------------------------- */

add_action( 'admin_init', function () {
	if ( ricoman_seo_plugin_active() ) {
		return;
	}
	foreach ( ricoman_seo_post_types() as $pt ) {
		add_filter( "manage_{$pt}_posts_columns", 'ricoman_seo_add_column' );
		add_action( "manage_{$pt}_posts_custom_column", 'ricoman_seo_render_column', 10, 2 );
	}
} );

function ricoman_seo_add_column( $cols ) {
	$cols['ricoman_seo'] = __( 'SEO', 'ricoman' );
	return $cols;
}

function ricoman_seo_render_column( $col, $post_id ) {
	if ( 'ricoman_seo' !== $col ) {
		return;
	}
	if ( get_post_meta( $post_id, '_ricoman_seo_noindex', true ) ) {
		echo '<span title="noindex" style="color:#646970">⊘ hidden</span>';
		return;
	}
	if ( ! function_exists( 'ricoman_seo_score' ) ) {
		echo '—';
		return;
	}
	$r     = ricoman_seo_score( $post_id );
	$score = (int) $r['score'];
	$fails = array();
	foreach ( $r['checks'] as $c ) {
		if ( ! $c['pass'] ) {
			$fails[] = $c['label'];
		}
	}
	$color = $score >= 80 ? '#008a20' : ( $score >= 50 ? '#dba617' : '#b32d2e' );
	echo '<span title="' . esc_attr( $fails ? implode( ', ', $fails ) : __( 'Looks good', 'ricoman' ) ) . '" style="color:' . esc_attr( $color ) . ';font-weight:700">● ' . esc_html( (string) $score ) . '<span style="font-weight:400">/100</span></span>';
}

/* ---------------------------------------------------------------------------
 * Site-wide SEO settings (Settings -> Ricoman SEO)
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Ricoman SEO', 'ricoman' ),
		__( 'Ricoman SEO', 'ricoman' ),
		'manage_options',
		'ricoman-seo',
		'ricoman_seo_settings_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'ricoman_seo_group', 'ricoman_seo', 'ricoman_seo_sanitize' );
} );

function ricoman_seo_sanitize( $input ) {
	$out  = array();
	$text = array( 'twitter', 'phone', 'street', 'locality', 'region', 'postcode', 'country', 'founding', 'google_verification', 'psi_key', 'lat', 'lng' );
	foreach ( $text as $k ) {
		if ( isset( $input[ $k ] ) ) {
			$out[ $k ] = sanitize_text_field( $input[ $k ] );
		}
	}
	if ( isset( $input['email'] ) ) {
		$out['email'] = sanitize_email( $input['email'] );
	}
	foreach ( array( 'logo', 'default_og_image' ) as $k ) {
		if ( isset( $input[ $k ] ) ) {
			$out[ $k ] = esc_url_raw( $input[ $k ] );
		}
	}
	if ( isset( $input['social'] ) ) {
		$lines = array_filter( array_map( 'trim', explode( "\n", (string) $input['social'] ) ) );
		$lines = array_map( 'esc_url_raw', $lines );
		$out['social'] = implode( "\n", $lines );
	}
	$out['allow_ai'] = empty( $input['allow_ai'] ) ? '0' : '1';
	return $out;
}

function ricoman_seo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$f = function ( $k, $d = '' ) {
		return esc_attr( ricoman_seo_opt( $k, $d ) );
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Ricoman SEO', 'ricoman' ); ?></h1>
		<?php if ( ricoman_seo_plugin_active() ) : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'A dedicated SEO plugin is active, so the theme’s built-in SEO output is paused to avoid duplicates. These settings still feed the theme where used.', 'ricoman' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'These details power your structured data (Google rich results), social sharing cards and contact schema. Fill them in once; pages optimise themselves from here.', 'ricoman' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'ricoman_seo_group' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="rs_logo"><?php esc_html_e( 'Logo URL', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_logo" name="ricoman_seo[logo]" value="<?php echo $f( 'logo' ); ?>"></td></tr>
				<tr><th scope="row"><label for="rs_og"><?php esc_html_e( 'Default share image URL', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_og" name="ricoman_seo[default_og_image]" value="<?php echo $f( 'default_og_image' ); ?>">
					<p class="description"><?php esc_html_e( 'Used when a page has no featured image.', 'ricoman' ); ?></p></td></tr>
				<tr><th scope="row"><label for="rs_social"><?php esc_html_e( 'Social profile URLs', 'ricoman' ); ?></label></th>
					<td><textarea id="rs_social" name="ricoman_seo[social]" rows="4" class="large-text" placeholder="https://www.linkedin.com/company/...&#10;https://www.instagram.com/..."><?php echo esc_textarea( ricoman_seo_opt( 'social' ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One URL per line (LinkedIn, Instagram, X, etc.). Feeds Organization sameAs.', 'ricoman' ); ?></p></td></tr>
				<tr><th scope="row"><label for="rs_tw"><?php esc_html_e( 'X / Twitter handle', 'ricoman' ); ?></label></th>
					<td><input type="text" id="rs_tw" name="ricoman_seo[twitter]" value="<?php echo $f( 'twitter' ); ?>" placeholder="ricoman"></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'Business address', 'ricoman' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="ricoman_seo[street]" value="<?php echo $f( 'street' ); ?>" placeholder="<?php esc_attr_e( 'Street address', 'ricoman' ); ?>"><br>
						<input type="text" class="regular-text" name="ricoman_seo[locality]" value="<?php echo $f( 'locality' ); ?>" placeholder="<?php esc_attr_e( 'Town / city', 'ricoman' ); ?>" style="margin-top:6px"><br>
						<input type="text" class="regular-text" name="ricoman_seo[region]" value="<?php echo $f( 'region' ); ?>" placeholder="<?php esc_attr_e( 'Region / county', 'ricoman' ); ?>" style="margin-top:6px"><br>
						<input type="text" class="regular-text" name="ricoman_seo[postcode]" value="<?php echo $f( 'postcode' ); ?>" placeholder="<?php esc_attr_e( 'Postcode', 'ricoman' ); ?>" style="margin-top:6px"><br>
						<input type="text" class="regular-text" name="ricoman_seo[country]" value="<?php echo $f( 'country', 'GB' ); ?>" placeholder="GB" style="margin-top:6px">
					</td></tr>
				<tr><th scope="row"><label for="rs_phone"><?php esc_html_e( 'Phone', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_phone" name="ricoman_seo[phone]" value="<?php echo $f( 'phone' ); ?>"></td></tr>
				<tr><th scope="row"><label for="rs_email"><?php esc_html_e( 'Email', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_email" name="ricoman_seo[email]" value="<?php echo $f( 'email' ); ?>"></td></tr>
				<tr><th scope="row"><label for="rs_found"><?php esc_html_e( 'Founded (year)', 'ricoman' ); ?></label></th>
					<td><input type="text" id="rs_found" name="ricoman_seo[founding]" value="<?php echo $f( 'founding' ); ?>" placeholder="1999"></td></tr>
				<tr><th scope="row"><label for="rs_gv"><?php esc_html_e( 'Google verification code', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_gv" name="ricoman_seo[google_verification]" value="<?php echo $f( 'google_verification' ); ?>">
					<p class="description"><?php esc_html_e( 'The content value from the Search Console “HTML tag” method.', 'ricoman' ); ?></p></td></tr>
				<tr><th scope="row"><label for="rs_geo"><?php esc_html_e( 'HQ coordinates', 'ricoman' ); ?></label></th>
					<td><input type="text" id="rs_geo" name="ricoman_seo[lat]" value="<?php echo $f( 'lat' ); ?>" placeholder="<?php esc_attr_e( 'Latitude', 'ricoman' ); ?>">
					<input type="text" name="ricoman_seo[lng]" value="<?php echo $f( 'lng' ); ?>" placeholder="<?php esc_attr_e( 'Longitude', 'ricoman' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional — adds geo coordinates to your LocalBusiness data for local/map results.', 'ricoman' ); ?></p></td></tr>
				<tr><th scope="row"><?php esc_html_e( 'AI / generative search', 'ricoman' ); ?></th>
					<td><label><input type="checkbox" name="ricoman_seo[allow_ai]" value="1" <?php checked( ricoman_seo_opt( 'allow_ai', '1' ), '1' ); ?>>
						<?php esc_html_e( 'Welcome AI crawlers (ChatGPT, Claude, Perplexity, Google AI) and publish an /llms.txt guide', 'ricoman' ); ?></label>
					<p class="description"><?php esc_html_e( 'Recommended ON for generative-engine optimisation — it lets answer engines read and cite your pages.', 'ricoman' ); ?></p></td></tr>
				<tr><th scope="row"><label for="rs_psi"><?php esc_html_e( 'PageSpeed API key', 'ricoman' ); ?></label></th>
					<td><input type="text" class="regular-text" id="rs_psi" name="ricoman_seo[psi_key]" value="<?php echo $f( 'psi_key' ); ?>">
					<p class="description"><?php esc_html_e( 'Optional — a Google PageSpeed Insights API key lets the SEO & Speed dashboard fetch live scores reliably.', 'ricoman' ); ?></p></td></tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

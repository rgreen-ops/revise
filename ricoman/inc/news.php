<?php
/**
 * News post type display — master listing + single article, rendered natively
 * from the migrated data (the old site used the `news` post type + ACF
 * "News Others Info" / "Post Tag Line").
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Display image for a news article (featured, else ACF bottom image). */
function ricoman_news_img( $pid, $size = 'large' ) {
	$img = get_the_post_thumbnail_url( $pid, $size );
	if ( ! $img && function_exists( 'ricoman_pf_imgurl' ) ) {
		$img = ricoman_pf_imgurl( get_post_meta( $pid, 'news_bottom_image', true ) );
	}
	return $img;
}

/** Short dek/excerpt for a news article. */
function ricoman_news_excerpt( $pid, $words = 24 ) {
	$ex = has_excerpt( $pid ) ? get_the_excerpt( $pid ) : '';
	if ( '' === trim( $ex ) ) {
		$ex = wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) );
	}
	if ( '' === trim( $ex ) ) {
		$ex = (string) get_post_meta( $pid, 'news_bottom_description', true );
	}
	return wp_trim_words( wp_strip_all_tags( $ex ), $words, '…' );
}

/** Standfirst/dek: manual override, else the first whole sentence(s) of the
 * body (ends cleanly on a full stop — no mid-sentence ellipsis). */
function ricoman_news_dek( $pid ) {
	$man = trim( (string) get_post_meta( $pid, '_rmn_standfirst', true ) );
	if ( '' !== $man ) {
		return $man;
	}
	$txt = trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
	if ( '' === $txt ) {
		$txt = trim( (string) get_post_meta( $pid, 'news_bottom_description', true ) );
	}
	if ( '' === $txt ) {
		return '';
	}
	$words = preg_split( '/\s+/', $txt );
	if ( count( $words ) <= 45 ) {
		return $txt;
	}
	$clip = implode( ' ', array_slice( $words, 0, 50 ) );
	// Trim back to the last sentence end so it never stops mid-thought.
	if ( preg_match( '/^(.*[.!?])(\s|$)/su', $clip, $m ) ) {
		return trim( $m[1] );
	}
	return rtrim( $clip, " ,;:\xe2\x80\x93\xe2\x80\x94" ) . '.';
}

/** Estimated read time in minutes from the article body. */
function ricoman_news_readtime( $pid ) {
	$words = str_word_count( wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
	return max( 1, (int) round( $words / 200 ) );
}

/** Topic chips are derived from each article's title + body (no manual tagging
 * needed). slug => [ label, keywords[] ]. Filterable. */
function ricoman_news_topic_map() {
	return apply_filters( 'ricoman_news_topic_map', array(
		'guides'   => array( 'Guides & how-to', array( 'how to', 'guide', 'glossary', 'what is', 'what beam', 'do i need', 'mistake', 'tips', 'explained', 'best ', 'should i', 'why ', 'how do' ) ),
		'linear'   => array( 'Linear', array( 'linear', 'flow+', 'flow plus' ) ),
		'controls' => array( 'Controls & dimming', array( 'dimming', 'dimmable', 'dali', 'casambi', 'control', 'tunable', 'scene' ) ),
		'colour'   => array( 'Colour & RGBW', array( 'rgbw', 'rgb', 'colour temp', 'cct', 'tunable white' ) ),
		'sectors'  => array( 'By sector', array( 'office', 'gym', 'retail', 'hospital', 'healthcare', 'warehouse', 'school', 'education', 'industrial', 'workplace', 'leisure', 'commercial space' ) ),
		'emergency'=> array( 'Emergency', array( 'emergency' ) ),
		'products' => array( 'Products', array( 'range', 'launch', 'new product', 'estrella', 'luminaire' ) ),
		'energy'   => array( 'Energy & sustainability', array( 'energy', 'sustainab', 'efficien', 'carbon', 'recycl' ) ),
	) );
}

/** Topic slugs matched purely from an article's title + body (keyword map). */
function ricoman_news_topics_derived( $pid ) {
	$hay = strtolower( get_the_title( $pid ) . ' ' . wp_strip_all_tags( (string) get_post_field( 'post_content', $pid ) ) );
	$out = array();
	foreach ( ricoman_news_topic_map() as $slug => $def ) {
		foreach ( $def[1] as $kw ) {
			if ( false !== strpos( $hay, $kw ) ) {
				$out[] = $slug;
				break;
			}
		}
	}
	return $out;
}

/**
 * Editable News "Topics" taxonomy (hierarchical so it gets category-style
 * checkboxes + Quick/Bulk Edit — the fastest way to tag a big back-catalogue).
 */
add_action( 'init', function () {
	register_taxonomy( 'news-cat', 'news', array(
		'labels'             => array(
			'name'          => __( 'Topics', 'ricoman' ),
			'singular_name' => __( 'Topic', 'ricoman' ),
			'menu_name'     => __( 'Topics', 'ricoman' ),
			'all_items'     => __( 'All Topics', 'ricoman' ),
			'edit_item'     => __( 'Edit Topic', 'ricoman' ),
			'add_new_item'  => __( 'Add New Topic', 'ricoman' ),
			'new_item_name' => __( 'New Topic Name', 'ricoman' ),
			'search_items'  => __( 'Search Topics', 'ricoman' ),
		),
		'public'             => true,
		'hierarchical'       => true,
		'show_admin_column'  => true,
		'show_in_quick_edit' => true,
		'show_in_rest'       => true,
		'rewrite'            => array( 'slug' => 'news-topic', 'with_front' => false ),
	) );
}, 9 );

/** Seed the Topics taxonomy once with the default topic set. */
add_action( 'init', function () {
	if ( get_option( 'ricoman_news_terms_seeded' ) || ! taxonomy_exists( 'news-cat' ) ) {
		return;
	}
	foreach ( ricoman_news_topic_map() as $slug => $def ) {
		if ( ! term_exists( $slug, 'news-cat' ) ) {
			wp_insert_term( $def[0], 'news-cat', array( 'slug' => $slug ) );
		}
	}
	update_option( 'ricoman_news_terms_seeded', 1 );
}, 12 );

/**
 * Persist an article's keyword-derived topics as real news-cat terms — but only
 * the first time, and only when it has no terms yet, so we seed the taxonomy
 * (populating Topic archives, admin columns and the listing filter) without ever
 * fighting manual tagging. The `_rm_topics_autotagged` flag makes it idempotent:
 * once tagged (here or by hand), we never re-touch it. Returns terms assigned.
 */
function ricoman_news_autotag( $pid ) {
	$pid = (int) $pid;
	if ( get_post_meta( $pid, '_rm_topics_autotagged', true ) ) {
		return 0;
	}
	$existing = wp_get_object_terms( $pid, 'news-cat', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $existing ) ) {
		return 0;
	}
	if ( ! empty( $existing ) ) {
		// Already tagged (manually) — record that and leave it alone.
		update_post_meta( $pid, '_rm_topics_autotagged', 1 );
		return 0;
	}
	$map  = ricoman_news_topic_map();
	$tids = array();
	foreach ( ricoman_news_topics_derived( $pid ) as $slug ) {
		$term = term_exists( $slug, 'news-cat' );
		if ( ! $term ) {
			$label = isset( $map[ $slug ] ) ? $map[ $slug ][0] : $slug;
			$term  = wp_insert_term( $label, 'news-cat', array( 'slug' => $slug ) );
		}
		if ( ! is_wp_error( $term ) ) {
			$tids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
		}
	}
	if ( $tids ) {
		wp_set_object_terms( $pid, $tids, 'news-cat', false );
	}
	update_post_meta( $pid, '_rm_topics_autotagged', 1 );
	return count( $tids );
}

/** Auto-tag on save (covers new + edited articles). */
add_action( 'save_post_news', function ( $pid, $post, $update ) {
	if ( wp_is_post_revision( $pid ) || wp_is_post_autosave( $pid ) || 'publish' !== $post->post_status ) {
		return;
	}
	ricoman_news_autotag( $pid );
}, 20, 3 );

/** One-time backfill across the existing catalogue, in self-rescheduling batches. */
add_action( 'ricoman_news_autotag_all', 'ricoman_news_autotag_all' );
function ricoman_news_autotag_all() {
	$ids = get_posts( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'cache_results'  => false,
		'meta_query'     => array( array( 'key' => '_rm_topics_autotagged', 'compare' => 'NOT EXISTS' ) ),
	) );
	foreach ( $ids as $pid ) {
		ricoman_news_autotag( (int) $pid );
	}
	if ( count( $ids ) >= 50 && ! wp_next_scheduled( 'ricoman_news_autotag_all' ) ) {
		wp_schedule_single_event( time() + 30, 'ricoman_news_autotag_all' );
	}
}
add_action( 'init', function () {
	if ( get_option( 'ricoman_news_autotag_kicked' ) ) {
		return;
	}
	if ( ! wp_next_scheduled( 'ricoman_news_autotag_all' ) ) {
		wp_schedule_single_event( time() + 10, 'ricoman_news_autotag_all' );
	}
	update_option( 'ricoman_news_autotag_kicked', 1 );
}, 13 );

/**
 * Topics for an article as slug => label. Prefers the assigned taxonomy terms;
 * falls back to the keyword-derived topics until an article is tagged — so the
 * listing filter always works, and gets more accurate as you tag.
 */
function ricoman_news_topic_terms( $pid ) {
	$out   = array();
	$terms = get_the_terms( $pid, 'news-cat' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			$out[ $t->slug ] = $t->name;
		}
		return $out;
	}
	$map = ricoman_news_topic_map();
	foreach ( ricoman_news_topics_derived( $pid ) as $slug ) {
		if ( isset( $map[ $slug ] ) ) {
			$out[ $slug ] = $map[ $slug ][0];
		}
	}
	return $out;
}

/** One news card (used for the grid and the featured lead). */
function ricoman_news_card( $pid, $featured = false ) {
	$img    = ricoman_news_img( $pid, $featured ? 'large' : 'medium_large' );
	$topics = ricoman_news_topic_terms( $pid );
	$tag    = (string) get_post_meta( $pid, 'news_tag_line', true );
	if ( '' === trim( $tag ) && $topics ) {
		$tag = reset( $topics ); // show the first topic as the card eyebrow.
	}
	$cls    = $featured ? 'rm-newscard rm-newscard--lead' : 'rm-newscard';
	$hay    = strtolower( get_the_title( $pid ) . ' ' . implode( ' ', $topics ) . ' ' . ricoman_news_excerpt( $pid, 30 ) );
	$tagsl  = implode( ' ', array_keys( $topics ) );
	return '<a class="' . $cls . '" data-tags="' . esc_attr( $tagsl ) . '" data-search="' . esc_attr( $hay ) . '" href="' . esc_url( get_permalink( $pid ) ) . '">'
		. '<span class="rm-newscard-img"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>'
		. '<span class="rm-newscard-body">'
		. ( $tag ? '<span class="rm-eyebrow">' . esc_html( $tag ) . '</span>' : '' )
		. '<span class="rm-newscard-t">' . esc_html( get_the_title( $pid ) ) . '</span>'
		. ( $featured ? '<span class="rm-newscard-dek">' . esc_html( ricoman_news_excerpt( $pid, 34 ) ) . '</span>' : '' )
		. '<span class="rm-newscard-meta"><span class="rm-newscard-date">' . esc_html( get_the_date( '', $pid ) ) . '</span>'
		. '<span class="rm-newscard-read">' . (int) ricoman_news_readtime( $pid ) . ' min read</span></span>'
		. '<span class="rm-newscard-go">Read article →</span>'
		. '</span></a>';
}

/** Master / listing grid — featured lead + card grid. [ricoman_news_grid count="24"] */
add_shortcode( 'ricoman_news_grid', function ( $atts ) {
	$atts = shortcode_atts( array( 'count' => 24, 'featured' => '1' ), $atts, 'ricoman_news_grid' );
	$q    = new WP_Query( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $atts['count'],
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '<div class="rm-pp-wrap"><p class="rm-config-note">News articles will appear here once published.</p></div>';
	}
	$ids = wp_list_pluck( $q->posts, 'ID' );
	wp_reset_postdata();

	// Topic chips — union of each article's topics (assigned terms, else derived).
	$tags = array();
	foreach ( $ids as $pid ) {
		foreach ( ricoman_news_topic_terms( $pid ) as $slug => $label ) {
			$tags[ $slug ] = $label;
		}
	}
	asort( $tags );

	$tools = '<div class="rm-newstools">'
		. '<div class="rm-projsearch"><svg class="rm-projsearch-ic" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
		. '<input type="search" class="rm-newsq" placeholder="Search articles…" aria-label="Search articles"></div>';
	if ( $tags ) {
		$tools .= '<div class="rm-newschips"><button type="button" class="rm-newschip on" data-tag="">All</button>';
		foreach ( $tags as $slug => $label ) {
			$tools .= '<button type="button" class="rm-newschip" data-tag="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</button>';
		}
		$tools .= '</div>';
	}
	$tools .= '</div>';

	$out  = '<div class="rm-pp-wrap rm-newswrap">' . $tools;
	if ( '1' === (string) $atts['featured'] && count( $ids ) > 0 ) {
		$lead = array_shift( $ids );
		$out .= '<div class="rm-news-lead">' . ricoman_news_card( $lead, true ) . '</div>';
	}
	if ( $ids ) {
		$out .= '<div class="rm-newsgrid">';
		foreach ( $ids as $pid ) {
			$out .= ricoman_news_card( $pid, false );
		}
		$out .= '</div>';
	}
	$out .= '<p class="rm-newsnone" hidden>No articles match your search. <button type="button" class="rm-newsreset">Clear</button></p>';
	return $out . '</div>';
} );

/**
 * The /news/ landing page was generated with a placeholder paragraph instead
 * of the article list. Swap that placeholder for the real listing (keeping the
 * page's hero + CTA), so visitors actually see the articles.
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || ! is_page() ) {
		return $content;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	if ( ! in_array( $slug, array( 'news', 'our-news', 'insights' ), true ) ) {
		return $content;
	}
	$grid = do_shortcode( '[ricoman_news_grid]' );
	if ( false !== strpos( $content, 'latest news and articles will appear' ) ) {
		return preg_replace( '#<p>\s*Our latest news and articles will appear here\.?\s*</p>#i', $grid, $content, 1 );
	}
	return $content . $grid;
}, 8 );

/** Tell Yoast (if active) to treat news posts as Article schema, not WebPage. */
add_filter( 'wpseo_schema_article_post_types', function ( $types ) {
	$types = (array) $types;
	if ( ! in_array( 'news', $types, true ) ) {
		$types[] = 'news';
	}
	return $types;
} );

/**
 * Keyword => URL map for in-article internal links. Built from the REAL product
 * categories (via get_term_link, so the URLs always resolve — no guessed slugs
 * that 404) plus curated feature-page links that are only included when the page
 * actually exists. Every category is covered automatically, with a leading-"LED"
 * alias so natural prose ("linear lighting") matches "LED Linear Lighting".
 * Editable via the filter. Cached per request.
 */
function ricoman_news_link_map() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	$map = array();

	// Real product categories — correct permalink whatever the slug structure is.
	$tax   = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => true ) );
	if ( ! is_wp_error( $terms ) && $terms ) {
		foreach ( $terms as $t ) {
			$link = get_term_link( $t );
			if ( is_wp_error( $link ) ) {
				continue;
			}
			$name = strtolower( trim( $t->name ) );
			if ( '' !== $name && ! isset( $map[ $name ] ) ) {
				$map[ $name ] = $link;
			}
			// Alias without a leading "LED " (e.g. "LED Linear Lighting" -> "linear lighting").
			$alias = strtolower( trim( preg_replace( '/^led\s+/i', '', $t->name ) ) );
			if ( '' !== $alias && $alias !== $name && ! isset( $map[ $alias ] ) ) {
				$map[ $alias ] = $link;
			}
		}
	}

	// Curated feature-page links — only added when the target page exists, so we
	// never link to a 404. Keyword => page path (slug).
	$curated = array(
		'human centric lighting' => 'human-centric-lighting',
		'tunable white'          => 'human-centric-lighting',
		'lighting design'        => 'lighting-design',
		'casambi'                => 'casambi',
		'antimicrobial'          => 'antimicrobial-protection',
		'fire safety'            => 'fire-safety',
		'fire rated'             => 'fire-safety',
		'custom lighting'        => 'customisation',
		'bespoke lighting'       => 'customisation',
		'made in britain'        => 'made-in-britain',
		'sustainability'         => 'sustainability',
		'where to buy'           => 'where-to-buy',
		'emergency lighting'     => 'fire-safety',
	);
	foreach ( $curated as $kw => $slug ) {
		if ( isset( $map[ $kw ] ) ) {
			continue;
		}
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$map[ $kw ] = get_permalink( $page );
		}
	}

	$cached = apply_filters( 'ricoman_news_link_map', $map );
	return $cached;
}

/**
 * Add a few internal links to an article body — first occurrence of each
 * curated term only, capped, skipping headings + existing links. Restrained on
 * purpose: relevant internal links help SEO + send readers to product pages,
 * but over-linking ("keyword stuffing") hurts both, so we keep it light.
 */
function ricoman_news_autolink( $html ) {
	$map = ricoman_news_link_map();
	if ( '' === trim( (string) $html ) || ! $map || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}
	$max  = (int) apply_filters( 'ricoman_news_autolink_max', 5 );
	$keys = array_keys( $map );
	usort( $keys, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );

	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="rmroot">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	$count = 0;

	foreach ( $keys as $kw ) {
		if ( $count >= $max ) {
			break;
		}
		$nodes = $xpath->query( '//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2) and not(ancestor::h3) and not(ancestor::h4) and not(ancestor::button)]' );
		foreach ( $nodes as $node ) {
			$text = $node->nodeValue;
			if ( preg_match( '/(?<![\w])' . preg_quote( $kw, '/' ) . '(?![\w])/i', $text, $m, PREG_OFFSET_CAPTURE ) ) {
				$pos     = $m[0][1];
				$matched = $m[0][0];
				$before  = substr( $text, 0, $pos );
				$after   = substr( $text, $pos + strlen( $matched ) );
				$a       = $doc->createElement( 'a' );
				$a->setAttribute( 'href', $map[ $kw ] );
				$a->setAttribute( 'class', 'rm-news-ilink' );
				$a->appendChild( $doc->createTextNode( $matched ) );
				$parent = $node->parentNode;
				$parent->insertBefore( $doc->createTextNode( $before ), $node );
				$parent->insertBefore( $a, $node );
				$parent->insertBefore( $doc->createTextNode( $after ), $node );
				$parent->removeChild( $node );
				$count++;
				break; // first occurrence of this term only.
			}
		}
	}

	$root = $doc->getElementById( 'rmroot' );
	if ( ! $root ) {
		return $html;
	}
	$out = '';
	foreach ( $root->childNodes as $c ) {
		$out .= $doc->saveHTML( $c );
	}
	return $out;
}

/** Conversion CTA band shared by article + (optionally) the listing. */
function ricoman_news_cta( $pid ) {
	$head = (string) get_post_meta( $pid, '_rmn_cta_head', true );
	$sub  = (string) get_post_meta( $pid, '_rmn_cta_sub', true );
	$btn  = (string) get_post_meta( $pid, '_rmn_cta_btn', true );
	$url  = (string) get_post_meta( $pid, '_rmn_cta_url', true );
	// Sensible conversion defaults so every article drives an enquiry.
	if ( '' === trim( $head ) ) { $head = 'Planning a lighting scheme?'; }
	if ( '' === trim( $sub ) ) { $sub = 'Send us your drawings or a finishes schedule and our in-house designers will return a fully specified scheme — usually within 3–5 days.'; }
	if ( '' === trim( $btn ) ) { $btn = 'Request a free lighting design'; }
	if ( '' === trim( $url ) ) { $url = '/lighting-design/'; }
	return '<aside class="rm-news-cta"><div class="rm-news-cta-in">'
		. '<h2 class="rm-news-cta-h">' . esc_html( $head ) . '</h2>'
		. '<p class="rm-news-cta-p">' . esc_html( $sub ) . '</p>'
		. '<a class="btn btn-solid" href="' . esc_url( $url ) . '">' . esc_html( $btn ) . '</a>'
		. ' <a class="btn btn-line-d" href="/products/">Browse the product range →</a>'
		. '<p class="rm-news-cta-alt"><a href="/contact/">Or talk to the team →</a></p>'
		. '</div></aside>';
}

/** Related products picked on the article (one product name or URL per line). */
function ricoman_news_related_products( $pid ) {
	$raw = trim( (string) get_post_meta( $pid, '_rmn_products', true ) );
	if ( '' === $raw ) {
		return '';
	}
	$cards = '';
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$url   = '';
		$name  = $line;
		if ( preg_match( '#^https?://#i', $line ) ) {
			$url = $line;
			$pp  = url_to_postid( $line );
			$name = $pp ? get_the_title( $pp ) : $line;
		} else {
			$url = function_exists( 'ricoman_find_product_url' ) ? ricoman_find_product_url( $line ) : '';
		}
		if ( '' === $url ) {
			continue;
		}
		$img   = '';
		$ppid  = url_to_postid( $url );
		if ( $ppid && function_exists( 'ricoman_product_img' ) ) {
			$img = ricoman_product_img( $ppid );
		}
		$inner = ( $img ? '<div class="rm-acard-img" style="background-image:url(' . esc_url( $img ) . ')"></div>' : '' )
			. '<div class="rm-acard-body"><h3>' . esc_html( $name ) . '</h3></div>';
		$cards .= '<a class="rm-acard" href="' . esc_url( $url ) . '">' . $inner . '</a>';
	}
	return $cards ? '<div class="rm-news-prod"><h2 class="rm-shead">Products in this article</h2><div class="rm-acards">' . $cards . '</div></div>' : '';
}

/** More articles (most recent, excluding the current one). */
function ricoman_news_related_articles( $pid, $n = 3 ) {
	$q = new WP_Query( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => $n,
		'post__not_in'   => array( $pid ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $q->have_posts() ) {
		return '';
	}
	$cards = '';
	foreach ( $q->posts as $p ) {
		$cards .= ricoman_news_card( $p->ID, false );
	}
	wp_reset_postdata();
	return '<div class="rm-news-more"><h2 class="rm-shead">More from the journal</h2><div class="rm-newsgrid">' . $cards . '</div></div>';
}

/** Single news article — editorial layout + conversion blocks. */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'news' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$pid  = get_the_ID();
	$tag  = (string) get_post_meta( $pid, 'news_tag_line', true );
	$dek  = ricoman_news_dek( $pid );

	$out  = '<div class="rm-section rm-news-head"><div class="rm-pp-wrap rm-news-headin">';
	$out .= '<div class="rm-pp-crumb">' . do_shortcode( '[ricoman_breadcrumbs]' ) . '</div>';
	if ( $tag ) {
		$out .= '<p class="rm-eyebrow">' . esc_html( $tag ) . '</p>';
	}
	$out .= '<h1 class="rm-news-title">' . esc_html( get_the_title() ) . '</h1>';
	if ( '' !== trim( $dek ) ) {
		$out .= '<p class="rm-news-standfirst">' . esc_html( $dek ) . '</p>';
	}
	$out .= '<p class="rm-news-byline"><span>' . esc_html( get_the_date() ) . '</span>'
		. '<span class="rm-news-readt">' . (int) ricoman_news_readtime( $pid ) . ' min read</span></p>';
	$out .= '</div></div>';

	$hero = ricoman_news_img( $pid, 'large' );
	if ( $hero ) {
		$out .= '<div class="rm-section rm-news-herosec"><div class="rm-pp-wrap"><figure class="rm-news-hero"><img src="' . esc_url( $hero ) . '" alt="' . esc_attr( get_the_title() ) . '"></figure></div></div>';
	}

	$out .= '<div class="rm-section rm-news-bodysec"><div class="rm-pp-wrap rm-news-single">';

	// Key takeaways box (great for SEO snippets + scannability).
	$take = trim( (string) get_post_meta( $pid, '_rmn_takeaways', true ) );
	if ( '' !== $take ) {
		$li = '';
		foreach ( preg_split( '/\r\n|\r|\n/', $take ) as $t ) {
			$t = trim( $t );
			if ( '' !== $t ) {
				$li .= '<li>' . esc_html( $t ) . '</li>';
			}
		}
		if ( $li ) {
			$out .= '<div class="rm-news-takeaways"><p class="rm-news-takeaways-h">Key takeaways</p><ul>' . $li . '</ul></div>';
		}
	}

	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		$out .= '<div class="rm-news-body">' . ricoman_news_autolink( $content ) . '</div>';
	}

	// Optional bottom heading/description + gallery from "News Others Info".
	$bh = (string) get_post_meta( $pid, 'news_bottom_heading', true );
	$bd = (string) get_post_meta( $pid, 'news_bottom_description', true );
	if ( $bh || $bd ) {
		$out .= '<div class="rm-news-extra">' . ( $bh ? '<h2 class="rm-shead">' . esc_html( $bh ) . '</h2>' : '' )
			. ( $bd ? '<p>' . esc_html( $bd ) . '</p>' : '' ) . '</div>';
	}
	if ( function_exists( 'have_rows' ) && have_rows( 'news_details_galary', $pid ) ) {
		$g = '';
		while ( have_rows( 'news_details_galary', $pid ) ) {
			the_row();
			$u = function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_sub_field( 'galary_image' ) ) : '';
			if ( $u ) {
				$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
			}
		}
		if ( $g ) {
			$out .= '<div class="rm-apage-gallery">' . $g . '</div>';
		}
	}

	$out .= ricoman_news_related_products( $pid );
	$out .= ricoman_news_cta( $pid );
	$out .= '</div></div>';

	$out .= '<div class="rm-section rm-news-moresec"><div class="rm-pp-wrap">' . ricoman_news_related_articles( $pid ) . '</div></div>';

	// Newsletter signup — turn article readers into subscribers (logged as leads).
	if ( function_exists( 'ricoman_newsletter_form' ) ) {
		$out .= '<div class="rm-section rm-news-newssec"><div class="rm-pp-wrap">'
			. ricoman_newsletter_form( array( 'source' => 'News article', 'title' => 'Get more lighting insights', 'sub' => 'Project stories, product launches and specifier know-how — straight to your inbox. No spam, unsubscribe anytime.' ) )
			. '</div></div>';
	}

	return $out;
}, 9 );

/* ----------------------------------------------------------------------- *
 * News editor: conversion + SEO fields (standfirst, takeaways, CTA band,
 * related products). Lets the team turn landing traffic into enquiries.
 * ----------------------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'ricoman_news_conv', __( 'Article content & conversion (Ricoman)', 'ricoman' ), 'ricoman_news_metabox', 'news', 'normal', 'high' );
} );

function ricoman_news_metabox( $post ) {
	wp_nonce_field( 'ricoman_news_meta', 'ricoman_news_meta_nonce' );
	$f = function ( $k ) use ( $post ) { return esc_textarea( (string) get_post_meta( $post->ID, $k, true ) ); };
	$v = function ( $k ) use ( $post ) { return esc_attr( (string) get_post_meta( $post->ID, $k, true ) ); };
	echo '<style>.rmn-fld{margin:0 0 16px}.rmn-fld label{display:block;font-weight:600;margin:0 0 4px}.rmn-fld .desc{color:#666;font-weight:400;font-size:12px}.rmn-fld input[type=text],.rmn-fld textarea{width:100%}.rmn-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}</style>';

	echo '<div class="rmn-fld"><label>Standfirst / intro <span class="desc">— the bold dek under the title (also used as the listing excerpt and meta fallback). Leave blank to auto-generate.</span></label>';
	echo '<textarea name="_rmn_standfirst" rows="2">' . $f( '_rmn_standfirst' ) . '</textarea></div>';

	echo '<div class="rmn-fld"><label>Key takeaways <span class="desc">— one per line. Shown as a scannable box near the top (good for SEO snippets).</span></label>';
	echo '<textarea name="_rmn_takeaways" rows="4">' . $f( '_rmn_takeaways' ) . '</textarea></div>';

	echo '<div class="rmn-fld"><label>Products in this article <span class="desc">— one product name or full URL per line. Renders linked product cards.</span></label>';
	echo '<textarea name="_rmn_products" rows="3">' . $f( '_rmn_products' ) . '</textarea></div>';

	echo '<hr><p><strong>' . esc_html__( 'Conversion CTA band', 'ricoman' ) . '</strong> <span class="desc">(shown at the end of the article — leave blank to use the sensible defaults)</span></p>';
	echo '<div class="rmn-grid">';
	echo '<div class="rmn-fld"><label>CTA heading</label><input type="text" name="_rmn_cta_head" value="' . $v( '_rmn_cta_head' ) . '" placeholder="Planning a lighting scheme?"></div>';
	echo '<div class="rmn-fld"><label>Button label</label><input type="text" name="_rmn_cta_btn" value="' . $v( '_rmn_cta_btn' ) . '" placeholder="Request a free lighting design"></div>';
	echo '</div>';
	echo '<div class="rmn-fld"><label>CTA sub-text</label><textarea name="_rmn_cta_sub" rows="2" placeholder="Send us your drawings…">' . $f( '_rmn_cta_sub' ) . '</textarea></div>';
	echo '<div class="rmn-fld"><label>Button URL</label><input type="text" name="_rmn_cta_url" value="' . $v( '_rmn_cta_url' ) . '" placeholder="/lighting-design/"></div>';
}

add_action( 'save_post_news', function ( $post_id ) {
	if ( ! isset( $_POST['ricoman_news_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ricoman_news_meta_nonce'] ), 'ricoman_news_meta' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$text = array( '_rmn_standfirst', '_rmn_takeaways', '_rmn_products', '_rmn_cta_sub' );
	foreach ( $text as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	$line = array( '_rmn_cta_head', '_rmn_cta_btn', '_rmn_cta_url' );
	foreach ( $line as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
} );

/* ----------------------------------------------------------------------- *
 * Admin: News Topics helper — one-click auto-tag the whole back-catalogue
 * from article content, then refine with Quick/Bulk Edit.
 * ----------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=news',
		__( 'Auto-tag Topics', 'ricoman' ),
		__( 'Auto-tag Topics', 'ricoman' ),
		'manage_categories',
		'ricoman-news-autotag',
		'ricoman_news_autotag_page'
	);
} );

function ricoman_news_autotag_page() {
	$all = (int) wp_count_posts( 'news' )->publish;
	// Count how many are already in at least one topic.
	$tagged = (int) ( new WP_Query( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array( 'taxonomy' => 'news-cat', 'operator' => 'EXISTS' ) ),
	) ) )->post_count;

	echo '<div class="wrap"><h1>' . esc_html__( 'Auto-tag News Topics', 'ricoman' ) . '</h1>';
	if ( isset( $_GET['tagged'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( 'Auto-tagged %d article(s) from their content.', 'ricoman' ), (int) $_GET['tagged'] )
			. '</p></div>';
	}
	echo '<p>' . esc_html__( 'This reads each article\'s title and body and assigns matching Topics (Guides & how-to, Linear, Controls & dimming, By sector, etc.). It only adds topics — it never removes ones you set by hand — so it\'s safe to run on the whole catalogue and then refine.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html( sprintf( __( '%1$d of %2$d published articles currently have a topic.', 'ricoman' ), $tagged, $all ) ) . '</strong></p>';
	echo '<p>' . wp_kses_post( __( 'Tip: to tag by hand, use the <strong>Topics</strong> column on the <a href="edit.php?post_type=news">News list</a> — tick boxes via <em>Quick Edit</em>, or select several articles and use <em>Bulk actions → Edit</em> to add a topic to many at once.', 'ricoman' ) ) . '</p>';
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Auto-tag every published article from its content?', 'ricoman' ) ) . '\');">';
	echo '<input type="hidden" name="action" value="ricoman_news_autotag">';
	wp_nonce_field( 'ricoman_news_autotag' );
	submit_button( __( 'Auto-tag all articles now', 'ricoman' ), 'primary' );
	echo '</form></div>';
}

add_action( 'admin_post_ricoman_news_autotag', function () {
	if ( ! current_user_can( 'manage_categories' ) || ! check_admin_referer( 'ricoman_news_autotag' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$ids = get_posts( array(
		'post_type'      => 'news',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$n = 0;
	foreach ( $ids as $pid ) {
		$slugs = ricoman_news_topics_derived( $pid );
		if ( ! $slugs ) {
			continue;
		}
		// Ensure the terms exist, then append (never wipe manual tags).
		$map = ricoman_news_topic_map();
		foreach ( $slugs as $slug ) {
			if ( ! term_exists( $slug, 'news-cat' ) && isset( $map[ $slug ] ) ) {
				wp_insert_term( $map[ $slug ][0], 'news-cat', array( 'slug' => $slug ) );
			}
		}
		wp_set_object_terms( $pid, $slugs, 'news-cat', true );
		$n++;
	}
	wp_safe_redirect( add_query_arg( 'tagged', $n, admin_url( 'edit.php?post_type=news&page=ricoman-news-autotag' ) ) );
	exit;
} );

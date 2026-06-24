<?php
/**
 * SEO Targets — the back-office register for the focus keywords, with an
 * automated on-page coverage review.
 *
 * Holds the team's target search terms (seeded with the 20 from the keyword
 * study), each mapped to the page that should rank for it, and audits coverage:
 * is the term in the page's SEO title, meta description, H1, body, URL, and does
 * the page have FAQ schema? Produces a per-term score + RAG flag and surfaces
 * GAPS (terms with no page yet) so the keyword list and the content plan live in
 * one place. Pure on-page review — no external API. (Real ranking/impression
 * data would come from a separate Google Search Console connection.)
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The starter set of focus terms (the 20 from the study). */
function ricoman_seo_targets_default() {
	return array(
		array( 'term' => 'commercial LED lighting manufacturer UK', 'intent' => 'Identity', 'priority' => 'High', 'url' => '/about/' ),
		array( 'term' => 'made in britain LED lighting', 'intent' => 'Identity', 'priority' => 'High', 'url' => '/made-in-britain/' ),
		array( 'term' => 'LED linear lighting', 'intent' => 'Commercial', 'priority' => 'High', 'url' => '/products/estrella-linear-lighting/' ),
		array( 'term' => 'UGR19 office linear lighting', 'intent' => 'Specifier', 'priority' => 'High', 'url' => '/office-lighting/' ),
		array( 'term' => 'curved linear lighting', 'intent' => 'Specifier', 'priority' => 'High', 'url' => '/flow-designer/' ),
		array( 'term' => 'suspended linear lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/suspended-linear-lighting/' ),
		array( 'term' => 'commercial LED track lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/product-category/48v-track/' ),
		array( 'term' => 'office lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/office-lighting/' ),
		array( 'term' => 'gym sports hall lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/gym-sports-hall-lighting/' ),
		array( 'term' => 'healthcare antimicrobial lighting', 'intent' => 'Sector', 'priority' => 'High', 'url' => '/antimicrobial-protection/' ),
		array( 'term' => 'school education lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/education-lighting/' ),
		array( 'term' => 'retail lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/retail-lighting/' ),
		array( 'term' => 'warehouse high bay lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/warehouse-high-bay-lighting/' ),
		array( 'term' => 'amenity lighting', 'intent' => 'Sector', 'priority' => 'Medium', 'url' => '/product-category/outdoor-exterior-lighting/' ),
		array( 'term' => 'feature lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/feature-lighting/' ),
		array( 'term' => 'emergency lighting', 'intent' => 'Compliance', 'priority' => 'Medium', 'url' => '/fire-safety/' ),
		array( 'term' => 'free lighting design service', 'intent' => 'Investigation', 'priority' => 'High', 'url' => '/lighting-design/' ),
		array( 'term' => 'photometric IES LDT files', 'intent' => 'Download', 'priority' => 'Medium', 'url' => '/downloads/' ),
		array( 'term' => 'BIM Revit lighting files', 'intent' => 'Download', 'priority' => 'Medium', 'url' => '/downloads/' ),
		array( 'term' => 'human centric lighting', 'intent' => 'Commercial', 'priority' => 'Medium', 'url' => '/human-centric-lighting/' ),
	);
}

/** The saved targets (seeded with the default set on first use). */
function ricoman_seo_targets() {
	$saved = get_option( 'ricoman_seo_targets', null );
	if ( ! is_array( $saved ) ) {
		$saved = ricoman_seo_targets_default();
		update_option( 'ricoman_seo_targets', $saved, false );
	}
	return $saved;
}

/* ----------------------------------------------------------------- matching */

function ricoman_seo_norm( $s ) {
	return trim( preg_replace( '/\s+/', ' ', preg_replace( '/[^a-z0-9 ]+/', ' ', strtolower( (string) $s ) ) ) );
}
/** Exact (normalised) phrase present? */
function ricoman_seo_has_phrase( $hay, $needle ) {
	$n = ricoman_seo_norm( $needle );
	return '' !== $n && false !== strpos( ' ' . ricoman_seo_norm( $hay ) . ' ', ' ' . $n . ' ' )
		|| ( '' !== $n && false !== strpos( ricoman_seo_norm( $hay ), $n ) );
}
/** Fraction of the term's significant words present in the text (0..1). */
function ricoman_seo_word_cov( $hay, $needle ) {
	$h     = ' ' . ricoman_seo_norm( $hay ) . ' ';
	$stop  = array( 'the', 'and', 'for', 'uk', 'led' );
	$words = array_values( array_unique( array_filter( explode( ' ', ricoman_seo_norm( $needle ) ), function ( $w ) use ( $stop ) {
		return strlen( $w ) > 2 && ! in_array( $w, $stop, true );
	} ) ) );
	if ( ! $words ) {
		return 0.0;
	}
	$hit = 0;
	foreach ( $words as $w ) {
		if ( false !== strpos( $h, ' ' . $w . ' ' ) || false !== strpos( $h, $w ) ) {
			$hit++;
		}
	}
	return $hit / count( $words );
}

/* --------------------------------------------------------------- resolution */

/** Resolve a target URL/slug to a post or term we can audit. */
function ricoman_seo_target_resolve( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url ) {
		return array( 'type' => 'gap' );
	}
	$full = ( 0 === strpos( $url, 'http' ) ) ? $url : home_url( $url );
	$pid  = url_to_postid( $full );
	if ( $pid ) {
		return array( 'type' => 'post', 'id' => (int) $pid );
	}
	$path = trim( (string) wp_parse_url( $full, PHP_URL_PATH ), '/' );
	$seg  = explode( '/', $path );
	$last = end( $seg );
	foreach ( array( 'product-cat', 'product_cat', 'category', 'project-cat' ) as $tax ) {
		if ( taxonomy_exists( $tax ) ) {
			$term = get_term_by( 'slug', $last, $tax );
			if ( $term && ! is_wp_error( $term ) ) {
				return array( 'type' => 'term', 'term' => $term );
			}
		}
	}
	return array( 'type' => 'unresolved' );
}

/** The text fields we audit a term against, for a resolved target. */
function ricoman_seo_target_fields( $resolved ) {
	$f = array( 'title' => '', 'seo_title' => '', 'meta' => '', 'body' => '', 'slug' => '', 'faq' => false, 'label' => '', 'edit' => '' );
	if ( 'post' === $resolved['type'] ) {
		$id            = $resolved['id'];
		$f['title']    = get_the_title( $id );
		$f['seo_title'] = (string) get_post_meta( $id, '_yoast_wpseo_title', true );
		if ( '' === $f['seo_title'] ) {
			$f['seo_title'] = (string) get_post_meta( $id, '_ricoman_seo_title', true );
		}
		if ( '' === $f['seo_title'] && function_exists( 'ricoman_seo_post_title' ) ) {
			$f['seo_title'] = ricoman_seo_post_title( $id );
		}
		$f['meta'] = (string) get_post_meta( $id, '_yoast_wpseo_metadesc', true );
		if ( '' === $f['meta'] ) {
			$f['meta'] = (string) get_post_meta( $id, '_ricoman_seo_desc', true );
		}
		// Body: post content + key product fields (products render from meta, not content).
		$body = (string) get_post_field( 'post_content', $id );
		foreach ( array( 'product_sort_description', 'specification', 'key_features', 'product_subname' ) as $mk ) {
			$mv = get_post_meta( $id, $mk, true );
			if ( is_string( $mv ) ) {
				$body .= ' ' . $mv;
			}
		}
		$f['body'] = wp_strip_all_tags( strip_shortcodes( $body ) );
		$f['slug'] = (string) get_post_field( 'post_name', $id );
		$f['faq']  = ( '' !== trim( (string) get_post_meta( $id, '_ricoman_faq', true ) ) ) || false !== stripos( $body, 'ricoman_faq' );
		$f['label'] = get_the_title( $id );
		$f['edit']  = get_edit_post_link( $id, '' );
	} elseif ( 'term' === $resolved['type'] ) {
		$term          = $resolved['term'];
		$f['title']    = $term->name;
		$tax           = get_option( 'wpseo_taxonomy_meta', array() );
		$f['seo_title'] = isset( $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_title'] ) ? $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_title'] : '';
		$f['meta']     = isset( $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_desc'] ) ? $tax[ $term->taxonomy ][ $term->term_id ]['wpseo_desc'] : '';
		$body          = (string) $term->description;
		if ( function_exists( 'ricoman_cat_seo' ) ) {
			$body .= ' ' . (string) ricoman_cat_seo( $term->term_id, 'intro' ) . ' ' . (string) ricoman_cat_seo( $term->term_id, 'body' );
		}
		$f['body'] = wp_strip_all_tags( strip_shortcodes( $body ) );
		$f['slug'] = $term->slug;
		$f['faq']  = false !== stripos( $body, 'ricoman_faq' );
		$f['label'] = $term->name . ' (category)';
		$f['edit']  = get_edit_term_link( $term->term_id, $term->taxonomy );
	}
	return $f;
}

/** Audit one target → score (0-100), RAG, and the list of checks. */
function ricoman_seo_target_audit( $target ) {
	$resolved = ricoman_seo_target_resolve( isset( $target['url'] ) ? $target['url'] : '' );
	$out      = array( 'resolved' => $resolved, 'score' => 0, 'rag' => 'red', 'checks' => array(), 'label' => '', 'edit' => '' );
	if ( 'post' !== $resolved['type'] && 'term' !== $resolved['type'] ) {
		$msg = ( 'gap' === $resolved['type'] ) ? 'No target page set — create one and assign it' : 'URL doesn’t resolve to a page';
		$out['checks'][] = array( $msg, false );
		$out['gap']      = true;
		return $out;
	}
	$f         = ricoman_seo_target_fields( $resolved );
	$out['label'] = $f['label'];
	$out['edit']  = $f['edit'];
	$term      = $target['term'];
	// weight => [label, text, exact-required]
	$tests = array(
		25 => array( 'SEO title', $f['seo_title'] ),
		20 => array( 'H1 / page title', $f['title'] ),
		15 => array( 'Meta description', $f['meta'] ),
		15 => array( 'Body copy', $f['body'] ),
		10 => array( 'URL slug', $f['slug'] ),
	);
	$score = 0;
	foreach ( $tests as $w => $t ) {
		list( $label, $text ) = $t;
		$pass = ricoman_seo_has_phrase( $text, $term );
		$cov  = ricoman_seo_word_cov( $text, $term );
		if ( $pass ) {
			$score += $w;
			$out['checks'][] = array( $label . ' ✓', true );
		} elseif ( $cov >= 0.6 ) {
			$score += (int) round( $w * 0.5 );
			$out['checks'][] = array( $label . ' — partial (' . round( $cov * 100 ) . '% of words)', null );
		} else {
			$out['checks'][] = array( $label . ' — missing', false );
		}
	}
	// FAQ schema bonus.
	if ( $f['faq'] ) {
		$score += 5;
		$out['checks'][] = array( 'FAQ schema present ✓', true );
	} else {
		$out['checks'][] = array( 'No FAQ schema (add [ricoman_faq])', false );
	}
	$out['score'] = min( 100, $score );
	$out['rag']   = $out['score'] >= 70 ? 'green' : ( $out['score'] >= 40 ? 'amber' : 'red' );
	// Extra signals (don't affect the score, but surface what's there).
	$out['internal_links'] = ricoman_seo_internal_links_count( $resolved );
	$out['schema']         = ricoman_seo_schema_types( $resolved, $f );
	$out['faq']            = $f['faq'];
	return $out;
}

/* ------------------------------------------------------- extra page signals */

/** How many published posts/pages link to this target (internal-link strength). */
function ricoman_seo_internal_links_count( $resolved ) {
	if ( 'post' !== $resolved['type'] && 'term' !== $resolved['type'] ) {
		return 0;
	}
	$link = ( 'post' === $resolved['type'] ) ? get_permalink( $resolved['id'] ) : get_term_link( $resolved['term'] );
	if ( is_wp_error( $link ) || ! $link ) {
		return 0;
	}
	$path = trim( (string) wp_parse_url( $link, PHP_URL_PATH ), '/' );
	if ( '' === $path ) {
		return 0;
	}
	$cache = get_transient( 'rm_seo_ilinks' );
	if ( ! is_array( $cache ) ) {
		$cache = array();
	}
	if ( isset( $cache[ $path ] ) ) {
		return (int) $cache[ $path ];
	}
	global $wpdb;
	$like  = '%' . $wpdb->esc_like( '/' . $path ) . '%';
	$count = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status='publish' AND post_type IN ('page','post','product','project','news') AND post_content LIKE %s",
		$like
	) );
	if ( 'post' === $resolved['type'] ) {
		$count = max( 0, $count - 1 ); // don't count the page linking to itself.
	}
	$cache[ $path ] = $count;
	set_transient( 'rm_seo_ilinks', $cache, 6 * HOUR_IN_SECONDS );
	return $count;
}

/** Best-effort list of structured-data types the target page emits. */
function ricoman_seo_schema_types( $resolved, $f ) {
	$types = array();
	if ( ! empty( $f['faq'] ) ) {
		$types[] = 'FAQ';
	}
	if ( 'post' === $resolved['type'] ) {
		$pt = get_post_type( $resolved['id'] );
		if ( 'product' === $pt ) {
			$types[] = 'Product';
		} elseif ( 'news' === $pt || 'post' === $pt ) {
			$types[] = 'Article';
		}
		$types[] = 'Breadcrumb';
	} elseif ( 'term' === $resolved['type'] ) {
		$types[] = 'CollectionPage';
		$types[] = 'Breadcrumb';
	}
	return array_values( array_unique( $types ) );
}

/* --------------------------------------------------- auto-suggest a page */

/** Suggest the best existing page/term for a gap/unresolved term. */
function ricoman_seo_suggest_page( $term ) {
	$best  = null;
	$bestc = 0.0;
	// Pages & key post types by title match.
	$q = new WP_Query( array(
		'post_type'      => array( 'page', 'product', 'project', 'news' ),
		'post_status'    => 'publish',
		'posts_per_page' => 60,
		's'              => $term,
		'no_found_rows'  => true,
		'fields'         => 'ids',
	) );
	foreach ( $q->posts as $pid ) {
		$cov = ricoman_seo_word_cov( get_the_title( $pid ), $term );
		if ( $cov > $bestc ) {
			$bestc = $cov;
			$best  = array( 'label' => get_the_title( $pid ), 'url' => wp_make_link_relative( get_permalink( $pid ) ) );
		}
	}
	wp_reset_postdata();
	// Product categories by name.
	foreach ( array( 'product-cat', 'product_cat', 'project-cat' ) as $tax ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => 200 ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $t ) {
			$cov = ricoman_seo_word_cov( $t->name, $term );
			if ( $cov > $bestc ) {
				$link = get_term_link( $t );
				if ( ! is_wp_error( $link ) ) {
					$bestc = $cov;
					$best  = array( 'label' => $t->name . ' (category)', 'url' => wp_make_link_relative( $link ) );
				}
			}
		}
	}
	return ( $best && $bestc >= 0.6 ) ? $best : null;
}

/* ------------------------------------------------- one-click optimisation */

/**
 * Hand-written, keyword-led SEO title + meta description per target term.
 * Keyed by the normalised term (see ricoman_seo_norm). Titles are kept ≤ ~60
 * chars and lead with the phrase; descriptions ~150–160 chars with the phrase
 * up front, a couple of USPs and a soft hook. Falls back to a generated version
 * for any term not listed here.
 */
function ricoman_seo_target_copy_library() {
	return array(
		'commercial led lighting manufacturer uk' => array(
			'Commercial LED Lighting Manufacturer UK | Ricoman',
			'UK manufacturer of commercial LED lighting, designed & made in Manchester. Free lighting design, photometric & BIM files, UK stock and a 5-year warranty.',
		),
		'made in britain led lighting' => array(
			'Made in Britain LED Lighting | Ricoman',
			'British-made commercial LED lighting, designed & manufactured in Manchester. Fast UK lead times, free scheme design and a 5-year warranty.',
		),
		'led linear lighting' => array(
			'LED Linear Lighting | UK Made | Ricoman',
			'Commercial LED linear lighting made in Britain — continuous, low-glare runs in any length. Free photometric design, UK stock and a 5-year warranty.',
		),
		'ugr19 office linear lighting' => array(
			'UGR<19 Office Linear Lighting | Ricoman',
			'Low-glare UGR<19 office linear lighting, designed & made in the UK. Free DIALux scheme design proving lux, uniformity and glare. 5-year warranty.',
		),
		'curved linear lighting' => array(
			'Curved Linear Lighting | Flow by Ricoman',
			'Seamless curved linear lighting, made to any radius in Britain. Design your own continuous, dot-free run with the Flow+ Designer — UK-made.',
		),
		'suspended linear lighting' => array(
			'Suspended Linear Lighting | UK Made | Ricoman',
			'Suspended linear lighting made to your exact run — low-glare, configurable and British-made. Free photometric design and a 5-year warranty.',
		),
		'commercial led track lighting' => array(
			'Commercial LED Track Lighting | Ricoman',
			'48V magnetic LED track lighting for retail & commercial spaces — adjustable, high-CRI and UK-supplied with free design support and a 5-year warranty.',
		),
		'office lighting' => array(
			'Office Lighting | UK Manufacturer | Ricoman',
			'Low-glare, energy-efficient office lighting designed & made in Britain. Free DIALux scheme design, UGR<19 luminaires and a 5-year warranty.',
		),
		'gym sports hall lighting' => array(
			'Gym & Sports Hall Lighting | Ricoman',
			'High-output, glare-controlled gym & sports hall lighting designed to the right lux levels. UK-made, robust and efficient — free scheme design.',
		),
		'healthcare antimicrobial lighting' => array(
			'Antimicrobial Healthcare Lighting | Ricoman',
			'Antimicrobial LED lighting for healthcare & hygiene-critical spaces — sealed, easy-clean and IP-rated. British-made with a 5-year warranty.',
		),
		'school education lighting' => array(
			'School & Education Lighting | Ricoman',
			'Low-glare, efficient school & education lighting designed to standards — UGR<19 classrooms, emergency & controls. UK-made, 5-year warranty.',
		),
		'retail lighting' => array(
			'Retail Lighting | High-CRI | Ricoman',
			'High-CRI retail lighting that makes products sell — track, accent & feature lighting. UK-supplied with free design support and a 5-year warranty.',
		),
		'warehouse high bay lighting' => array(
			'Warehouse & High Bay Lighting | Ricoman',
			'Energy-saving LED high bay & warehouse lighting — bright, even and controllable, UK-made up to 180 lm/W. Free scheme design and a 5-year warranty.',
		),
		'amenity lighting' => array(
			'Amenity & Exterior Lighting | Ricoman',
			'Durable amenity & exterior LED lighting for outdoor commercial spaces — IP-rated, efficient and UK-supplied with free design support.',
		),
		'feature lighting' => array(
			'Feature Lighting | Bespoke & UK-Made | Ricoman',
			'Bespoke feature lighting — curved runs, statement pendants and architectural detail, designed with you and made to order in Britain.',
		),
		'emergency lighting' => array(
			'Emergency Lighting | Fire-Rated | Ricoman',
			'Emergency & fire-rated lighting designed to support BS 5266 — maintained, non-maintained and exit signage. British-made with a 5-year warranty.',
		),
		'free lighting design service' => array(
			'Free Lighting Design Service | Ricoman',
			'Free lighting design service — send your drawings and our UK team returns a costed DIALux photometric scheme, usually within 3–5 working days.',
		),
		'photometric ies ldt files' => array(
			'Photometric IES, LDT & BIM Files | Ricoman',
			'Download photometric IES & LDT files for Ricoman luminaires, ready for DIALux & Relux — plus datasheets, BIM/Revit objects and installation guides.',
		),
		'bim revit lighting files' => array(
			'BIM, Revit & Photometric Files | Ricoman',
			'BIM & Revit (RFA) lighting files for Ricoman luminaires, ready for your model — plus photometric IES/LDT files, datasheets and installation guides.',
		),
		'human centric lighting' => array(
			'Human Centric Lighting | Tunable White | Ricoman',
			'Human centric, tunable-white lighting that supports focus, comfort & wellbeing — for workplaces, healthcare & education. UK-made, CRI 90+.',
		),
	);
}

/** The best SEO title for a term: hand-written if we have it, else generated. */
function ricoman_seo_target_title( $term, $fallback = '' ) {
	$lib = ricoman_seo_target_copy_library();
	$key = ricoman_seo_norm( $term );
	if ( isset( $lib[ $key ][0] ) && '' !== $lib[ $key ][0] ) {
		return $lib[ $key ][0];
	}
	return ricoman_seo_title_for_term( $term, $fallback );
}

/** The best meta description for a term: hand-written if we have it, else generated. */
function ricoman_seo_target_desc( $term, $fallback = '' ) {
	$lib = ricoman_seo_target_copy_library();
	$key = ricoman_seo_norm( $term );
	if ( isset( $lib[ $key ][1] ) && '' !== $lib[ $key ][1] ) {
		return $lib[ $key ][1];
	}
	return ricoman_seo_desc_for_term( $term, $fallback );
}

/** A keyword-led SEO title built from a term (≤ ~60 chars), brand suffixed. (Generic fallback.) */
function ricoman_seo_title_for_term( $term, $fallback = '' ) {
	$t = trim( (string) $term );
	if ( '' === $t ) {
		return $fallback;
	}
	$t     = ucwords( $t );
	$title = $t . ' | Ricoman Lighting';
	if ( strlen( $title ) > 60 ) {
		$title = $t . ' | Ricoman';
	}
	return $title;
}

/** A meta description built from a term (generic fallback). */
function ricoman_seo_desc_for_term( $term, $fallback = '' ) {
	$t = trim( (string) $term );
	if ( '' === $t ) {
		return $fallback;
	}
	return ucfirst( $t ) . ' from Ricoman — UK manufacturer of commercial LED lighting. Free lighting design, photometric & BIM files, UK stock and a 5-year warranty.';
}

/**
 * Optimise one target's page for its term. Writes the hand-written (or generated)
 * SEO title / meta / focus keyphrase.
 *
 * Modes:
 *  - default: fill empty fields only.
 *  - $force:  also upgrade values still equal to the old generic auto-fill.
 *  - $overwrite: replace the SEO title & meta with the keyword-led version even
 *    if already set (the explicit per-row "Optimise" button) — still skips a
 *    field that already equals the target value, and never edits page content.
 * Returns the number of fields written.
 */
function ricoman_seo_optimise_target( $target, $force = false, $overwrite = false ) {
	$resolved = ricoman_seo_target_resolve( isset( $target['url'] ) ? $target['url'] : '' );
	$term     = isset( $target['term'] ) ? $target['term'] : '';
	$written  = 0;
	if ( '' === trim( (string) $term ) ) {
		return 0;
	}
	$title_new = ricoman_seo_target_title( $term );
	$desc_new  = ricoman_seo_target_desc( $term );
	$title_gen = ricoman_seo_title_for_term( $term );   // what the generic optimiser wrote.
	$desc_gen  = ricoman_seo_desc_for_term( $term );
	// Writable if empty; or (overwrite) anything that isn't already the target value;
	// or (force) still equal to the old generated value. Never re-writes an identical value.
	$writable  = function ( $current, $new ) use ( $force, $overwrite, $title_gen, $desc_gen ) {
		$current = trim( (string) $current );
		if ( '' === $current ) {
			return true;
		}
		if ( $current === $new ) {
			return false; // already optimal — nothing to do.
		}
		if ( $overwrite ) {
			return true;
		}
		return $force && ( $current === $title_gen || $current === $desc_gen );
	};
	if ( 'post' === $resolved['type'] ) {
		$id = $resolved['id'];
		foreach ( array( '_yoast_wpseo_title', '_ricoman_seo_title' ) as $mk ) {
			if ( $writable( get_post_meta( $id, $mk, true ), $title_new ) ) {
				update_post_meta( $id, $mk, $title_new );
				$written++;
			}
		}
		foreach ( array( '_yoast_wpseo_metadesc', '_ricoman_seo_desc' ) as $mk ) {
			if ( $writable( get_post_meta( $id, $mk, true ), $desc_new ) ) {
				update_post_meta( $id, $mk, $desc_new );
				$written++;
			}
		}
		if ( '' === trim( (string) get_post_meta( $id, '_yoast_wpseo_focuskw', true ) ) ) {
			update_post_meta( $id, '_yoast_wpseo_focuskw', sanitize_text_field( $term ) );
			$written++;
		}
	} elseif ( 'term' === $resolved['type'] ) {
		$t   = $resolved['term'];
		$all = get_option( 'wpseo_taxonomy_meta', array() );
		if ( ! isset( $all[ $t->taxonomy ] ) ) {
			$all[ $t->taxonomy ] = array();
		}
		$meta = isset( $all[ $t->taxonomy ][ $t->term_id ] ) ? $all[ $t->taxonomy ][ $t->term_id ] : array();
		if ( $writable( isset( $meta['wpseo_title'] ) ? $meta['wpseo_title'] : '', $title_new ) ) {
			$meta['wpseo_title'] = $title_new;
			$written++;
		}
		if ( $writable( isset( $meta['wpseo_desc'] ) ? $meta['wpseo_desc'] : '', $desc_new ) ) {
			$meta['wpseo_desc'] = $desc_new;
			$written++;
		}
		if ( empty( $meta['wpseo_focuskw'] ) ) {
			$meta['wpseo_focuskw'] = sanitize_text_field( $term );
			$written++;
		}
		$all[ $t->taxonomy ][ $t->term_id ] = $meta;
		update_option( 'wpseo_taxonomy_meta', $all );
	}
	return $written;
}

add_action( 'admin_post_ricoman_seo_optimise', function () {
	$i = isset( $_GET['i'] ) ? (int) $_GET['i'] : -1;
	if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ricoman_seo_opt_' . $i ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$targets = ricoman_seo_targets();
	// Explicit click → overwrite the SEO title & meta with the keyword-led version
	// (page heading, URL and body are never touched — edit those on the page itself).
	$n       = isset( $targets[ $i ] ) ? ricoman_seo_optimise_target( $targets[ $i ], true, true ) : 0;
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'optimised' => $n ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ------------------------------------------------- create a gap landing page */

add_action( 'admin_post_ricoman_seo_create_gap', function () {
	$i = isset( $_GET['i'] ) ? (int) $_GET['i'] : -1;
	if ( ! current_user_can( 'publish_pages' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ricoman_seo_gap_' . $i ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$targets = ricoman_seo_targets();
	if ( ! isset( $targets[ $i ] ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=ricoman-seo-targets' ) );
		exit;
	}
	$term  = $targets[ $i ]['term'];
	$title = ucwords( trim( (string) $term ) );
	$intro = ricoman_seo_desc_for_term( $term );
	$content  = '<!-- wp:heading {"level":1} --><h1>' . esc_html( $title ) . '</h1><!-- /wp:heading>';
	$content .= '<!-- wp:paragraph --><p>' . esc_html( $intro ) . '</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:paragraph --><p>' . esc_html__( 'Edit this page to add your copy, images and internal links. The FAQ block below emits FAQ schema.', 'ricoman' ) . '</p><!-- /wp:paragraph -->';
	$content .= '<!-- wp:shortcode -->[ricoman_faq]<!-- /wp:shortcode -->';
	$pid = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => $title,
		'post_name'    => sanitize_title( $term ),
		'post_content' => $content,
	), true );
	if ( ! is_wp_error( $pid ) && $pid ) {
		update_post_meta( $pid, '_yoast_wpseo_title', ricoman_seo_title_for_term( $term, $title ) );
		update_post_meta( $pid, '_yoast_wpseo_metadesc', ricoman_seo_desc_for_term( $term ) );
		update_post_meta( $pid, '_yoast_wpseo_focuskw', sanitize_text_field( $term ) );
		update_post_meta( $pid, '_ricoman_seo_title', ricoman_seo_title_for_term( $term, $title ) );
		update_post_meta( $pid, '_ricoman_seo_desc', ricoman_seo_desc_for_term( $term ) );
		$targets[ $i ]['url'] = wp_make_link_relative( get_permalink( $pid ) );
		update_option( 'ricoman_seo_targets', $targets, false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'gapmade' => (int) $pid ), admin_url( 'admin.php' ) ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'gapfail' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* --------------------------------------------------- history + sparklines */

/** Snapshot every target's score (+ GSC position if connected) into history. */
function ricoman_seo_targets_snapshot() {
	$targets = ricoman_seo_targets();
	$hist    = get_option( 'ricoman_seo_history', array() );
	if ( ! is_array( $hist ) ) {
		$hist = array();
	}
	$date = gmdate( 'Y-m-d' );
	foreach ( $targets as $t ) {
		$key = ricoman_seo_norm( $t['term'] );
		if ( '' === $key ) {
			continue;
		}
		$a   = ricoman_seo_target_audit( $t );
		$pos = null;
		if ( function_exists( 'ricoman_gsc_term_data' ) ) {
			$g = ricoman_gsc_term_data( $t['term'] );
			if ( $g ) {
				$pos = $g['position'];
			}
		}
		if ( ! isset( $hist[ $key ] ) || ! is_array( $hist[ $key ] ) ) {
			$hist[ $key ] = array();
		}
		$hist[ $key ][ $date ] = array( 's' => (int) $a['score'], 'p' => $pos );
		// Keep the last 26 snapshots per term.
		if ( count( $hist[ $key ] ) > 26 ) {
			$hist[ $key ] = array_slice( $hist[ $key ], -26, 26, true );
		}
	}
	update_option( 'ricoman_seo_history', $hist, false );
	return $hist;
}
add_action( 'ricoman_seo_targets_snapshot', 'ricoman_seo_targets_snapshot' );

/** Inline SVG sparkline from a term's score history. */
function ricoman_seo_sparkline( $term ) {
	$hist = get_option( 'ricoman_seo_history', array() );
	$key  = ricoman_seo_norm( $term );
	if ( empty( $hist[ $key ] ) || ! is_array( $hist[ $key ] ) || count( $hist[ $key ] ) < 2 ) {
		return '<span style="color:#aaa;font-size:11px">—</span>';
	}
	$vals = array();
	foreach ( $hist[ $key ] as $row ) {
		$vals[] = isset( $row['s'] ) ? (int) $row['s'] : 0;
	}
	$n   = count( $vals );
	$w   = 64;
	$h   = 18;
	$max = 100;
	$pts = array();
	foreach ( $vals as $idx => $v ) {
		$x = $n > 1 ? round( $idx / ( $n - 1 ) * ( $w - 2 ) + 1, 1 ) : 1;
		$y = round( $h - 1 - ( $v / $max ) * ( $h - 2 ), 1 );
		$pts[] = $x . ',' . $y;
	}
	$dir   = end( $vals ) - reset( $vals );
	$color = $dir > 0 ? '#1a7f37' : ( $dir < 0 ? '#b32d2e' : '#888' );
	$arrow = $dir > 0 ? '▲' : ( $dir < 0 ? '▼' : '▬' );
	return '<svg width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" style="vertical-align:middle"><polyline fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="1.5" points="' . esc_attr( implode( ' ', $pts ) ) . '"/></svg> <span style="color:' . esc_attr( $color ) . ';font-size:11px">' . $arrow . '</span>';
}

/* ----------------------------------------------------- weekly email digest */

add_action( 'ricoman_seo_weekly_digest', 'ricoman_seo_send_digest' );
function ricoman_seo_send_digest() {
	// Take a fresh snapshot first so trends + the email agree.
	ricoman_seo_targets_snapshot();
	$targets = ricoman_seo_targets();
	$green = $amber = $red = 0;
	$worst = array();
	foreach ( $targets as $t ) {
		$a = ricoman_seo_target_audit( $t );
		if ( 'green' === $a['rag'] ) {
			$green++;
		} elseif ( 'amber' === $a['rag'] ) {
			$amber++;
		} else {
			$red++;
		}
		$worst[] = array( 'term' => $t['term'], 'score' => empty( $a['gap'] ) ? (int) $a['score'] : -1 );
	}
	usort( $worst, function ( $a, $b ) { return $a['score'] - $b['score']; } );
	$lines   = array();
	$lines[] = 'Ricoman SEO Targets — weekly review (' . gmdate( 'j M Y' ) . ')';
	$lines[] = '';
	$lines[] = "Coverage: {$green} well covered · {$amber} partial · {$red} gap/weak";
	$lines[] = '';
	$lines[] = 'Lowest-scoring terms to work on:';
	foreach ( array_slice( $worst, 0, 6 ) as $w ) {
		$lines[] = '  • ' . $w['term'] . ' — ' . ( $w['score'] < 0 ? 'no page yet' : $w['score'] . '%' );
	}
	if ( function_exists( 'ricoman_gsc_ready' ) && ricoman_gsc_ready() ) {
		$opp = ricoman_gsc_opportunities( $targets );
		if ( ! empty( $opp['page2'] ) ) {
			$lines[] = '';
			$lines[] = 'Page-2 quick wins (live, from Search Console):';
			foreach ( array_slice( $opp['page2'], 0, 6 ) as $o ) {
				$lines[] = '  • ' . $o['query'] . ' — pos ' . $o['position'] . ', ' . $o['impressions'] . ' impressions';
			}
		}
	}
	$lines[] = '';
	$lines[] = 'Full review: ' . admin_url( 'admin.php?page=ricoman-seo-targets' );
	$to      = apply_filters( 'ricoman_seo_digest_to', get_option( 'admin_email' ) );
	if ( $to ) {
		wp_mail( $to, 'Ricoman SEO — weekly targets review', implode( "\n", $lines ) );
	}
}

/** Schedule weekly cron events on load (idempotent). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ricoman_seo_weekly_digest' ) ) {
		wp_schedule_event( strtotime( 'next monday 8:00' ), 'weekly', 'ricoman_seo_weekly_digest' );
	}
	if ( ! wp_next_scheduled( 'ricoman_seo_targets_snapshot' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'ricoman_seo_targets_snapshot' );
	}
} );
// Ensure a 'weekly' schedule exists (WP core has none by default).
add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['weekly'] ) ) {
		$s['weekly'] = array( 'interval' => WEEK_IN_SECONDS, 'display' => __( 'Once Weekly', 'ricoman' ) );
	}
	return $s;
} );

/* -------------------------------------------------------------- CSV export */

add_action( 'admin_post_ricoman_seo_targets_export', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ricoman_seo_export' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$targets = ricoman_seo_targets();
	$gsc_on  = function_exists( 'ricoman_gsc_ready' ) && ricoman_gsc_ready();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=ricoman-seo-targets-' . gmdate( 'Y-m-d' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Term', 'Intent', 'Priority', 'Target URL', 'Coverage %', 'RAG', 'Internal links', 'Schema', 'GSC position', 'GSC clicks', 'GSC impressions', 'To fix' ) );
	foreach ( $targets as $t ) {
		$a   = ricoman_seo_target_audit( $t );
		$gap = ! empty( $a['gap'] );
		$bad = array();
		foreach ( $a['checks'] as $c ) {
			if ( false === $c[1] ) {
				$bad[] = $c[0];
			}
		}
		$g = $gsc_on ? ricoman_gsc_term_data( $t['term'] ) : null;
		fputcsv( $out, array(
			$t['term'], $t['intent'], $t['priority'], $t['url'],
			$gap ? '' : (int) $a['score'], $a['rag'],
			isset( $a['internal_links'] ) ? (int) $a['internal_links'] : 0,
			isset( $a['schema'] ) ? implode( ' ', $a['schema'] ) : '',
			$g ? $g['position'] : '', $g ? $g['clicks'] : '', $g ? $g['impressions'] : '',
			implode( ' | ', $bad ),
		) );
	}
	fclose( $out );
	exit;
} );

/* -------------------------------------------------------------- admin screen */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'SEO Targets', 'ricoman' ),
		__( 'SEO Targets', 'ricoman' ),
		'edit_posts',
		'ricoman-seo-targets',
		'ricoman_seo_targets_page'
	);
}, 29 );

/** Save handler. */
add_action( 'admin_post_ricoman_seo_targets_save', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ricoman_seo_targets' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$rows = isset( $_POST['t'] ) && is_array( $_POST['t'] ) ? wp_unslash( $_POST['t'] ) : array();
	$out  = array();
	foreach ( $rows as $r ) {
		$termv = isset( $r['term'] ) ? sanitize_text_field( $r['term'] ) : '';
		if ( '' === trim( $termv ) ) {
			continue;
		}
		$out[] = array(
			'term'     => $termv,
			'intent'   => isset( $r['intent'] ) ? sanitize_text_field( $r['intent'] ) : '',
			'priority' => isset( $r['priority'] ) ? sanitize_text_field( $r['priority'] ) : 'Medium',
			'url'      => isset( $r['url'] ) ? esc_url_raw( trim( $r['url'] ), array( 'http', 'https' ) ) : '',
		);
	}
	update_option( 'ricoman_seo_targets', $out, false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-seo-targets', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function ricoman_seo_targets_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$targets = ricoman_seo_targets();
	$audits  = array();
	$green   = 0;
	$amber   = 0;
	$red     = 0;
	foreach ( $targets as $i => $t ) {
		$a            = ricoman_seo_target_audit( $t );
		$audits[ $i ] = $a;
		if ( 'green' === $a['rag'] ) {
			$green++;
		} elseif ( 'amber' === $a['rag'] ) {
			$amber++;
		} else {
			$red++;
		}
	}
	$intents    = array( 'Identity', 'Commercial', 'Specifier', 'Sector', 'Investigation', 'Download', 'Compliance' );
	$priorities = array( 'High', 'Medium', 'Low' );
	$gsc_on     = function_exists( 'ricoman_gsc_ready' ) && ricoman_gsc_ready();
	$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_seo_targets_export' ), 'ricoman_seo_export' );
	?>
	<div class="wrap rm-seotargets">
		<h1><?php esc_html_e( 'SEO Targets', 'ricoman' ); ?>
			<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Export CSV', 'ricoman' ); ?></a>
		</h1>
		<?php
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Targets saved.', 'ricoman' ) . '</p></div>';
		}
		if ( isset( $_GET['optimised'] ) ) {
			$n = (int) $_GET['optimised'];
			echo '<div class="notice notice-success is-dismissible"><p>' . ( $n > 0
				? sprintf( esc_html__( 'Optimised — set the SEO title & meta for this term across %d field(s). (Page heading, URL and body aren’t changed — edit those on the page itself.)', 'ricoman' ), $n )
				: esc_html__( 'Already optimised — the SEO title & meta already match this term. Anything still red is the page’s heading, URL slug or body, which you edit on the page itself (not here).', 'ricoman' ) ) . '</p></div>';
		}
		if ( isset( $_GET['gapmade'] ) ) {
			$pid = (int) $_GET['gapmade'];
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Draft landing page created and linked to the term.', 'ricoman' ) . ' <a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html__( 'Edit it →', 'ricoman' ) . '</a></p></div>';
		}
		if ( isset( $_GET['gapfail'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Could not create the page.', 'ricoman' ) . '</p></div>';
		}
		?>
		<p class="description" style="max-width:860px"><?php esc_html_e( 'Your focus search terms and how well each target page covers them on-page (title, meta, H1, body, URL, FAQ schema). Optimise writes the keyword-led SEO title & meta description for a term; Suggest finds the best existing page for a gap; Create page spins up a draft. Optimise never changes a page’s heading, URL or body text — those are edited on the page itself, which is why an identity page (e.g. /about/) can still show H1/slug as “missing”. Connect Search Console (below) for live position, clicks & impressions plus a quick-win finder. Coverage re-checks each time you open this page.', 'ricoman' ); ?></p>

		<div class="rm-seo-sum">
			<span class="rm-seo-pill green"><?php echo (int) $green; ?> <?php esc_html_e( 'well covered', 'ricoman' ); ?></span>
			<span class="rm-seo-pill amber"><?php echo (int) $amber; ?> <?php esc_html_e( 'partial', 'ricoman' ); ?></span>
			<span class="rm-seo-pill red"><?php echo (int) $red; ?> <?php esc_html_e( 'gap / weak', 'ricoman' ); ?></span>
		</div>

		<?php if ( function_exists( 'ricoman_gsc_settings_panel' ) ) { ricoman_gsc_settings_panel(); } ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ricoman_seo_targets_save">
			<?php wp_nonce_field( 'ricoman_seo_targets' ); ?>
			<table class="widefat striped rm-seo-table">
				<thead><tr>
					<th style="width:20%"><?php esc_html_e( 'Target term', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'Intent', 'ricoman' ); ?></th>
					<th><?php esc_html_e( 'Priority', 'ricoman' ); ?></th>
					<th style="width:18%"><?php esc_html_e( 'Target page (URL/path)', 'ricoman' ); ?></th>
					<th style="width:8%"><?php esc_html_e( 'Coverage', 'ricoman' ); ?></th>
					<th style="width:8%"><?php esc_html_e( 'Trend', 'ricoman' ); ?></th>
					<?php if ( $gsc_on ) : ?><th style="width:9%"><?php esc_html_e( 'Live rank', 'ricoman' ); ?></th><?php endif; ?>
					<th><?php esc_html_e( 'What to fix / do', 'ricoman' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $targets as $i => $t ) :
					$a   = $audits[ $i ];
					$rag = $a['rag'];
					$gap = ! empty( $a['gap'] );
					$opt_url = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_seo_optimise&i=' . $i ), 'ricoman_seo_opt_' . $i );
					$gap_url = wp_nonce_url( admin_url( 'admin-post.php?action=ricoman_seo_create_gap&i=' . $i ), 'ricoman_seo_gap_' . $i );
					?>
					<tr>
						<td><input type="text" name="t[<?php echo (int) $i; ?>][term]" value="<?php echo esc_attr( $t['term'] ); ?>" style="width:100%"></td>
						<td><select name="t[<?php echo (int) $i; ?>][intent]"><?php foreach ( $intents as $opt ) { echo '<option' . selected( $t['intent'], $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><select name="t[<?php echo (int) $i; ?>][priority]"><?php foreach ( $priorities as $opt ) { echo '<option' . selected( $t['priority'], $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><input type="text" name="t[<?php echo (int) $i; ?>][url]" value="<?php echo esc_attr( $t['url'] ); ?>" placeholder="/page-slug/" style="width:80%">
							<?php if ( $a['edit'] ) : ?> <a href="<?php echo esc_url( $a['edit'] ); ?>" title="Edit page">✎</a><?php endif; ?>
							<?php
							if ( $gap || 'unresolved' === $a['resolved']['type'] ) {
								$sugg = ricoman_seo_suggest_page( $t['term'] );
								if ( $sugg ) {
									echo '<div class="rm-seo-sugg">' . esc_html__( 'Suggested:', 'ricoman' ) . ' <a href="#" class="rm-seo-pick" data-url="' . esc_attr( $sugg['url'] ) . '" data-i="' . (int) $i . '">' . esc_html( $sugg['label'] ) . '</a></div>';
								}
							}
							?>
						</td>
						<td>
							<span class="rm-seo-score rm-<?php echo esc_attr( $rag ); ?>"><?php echo $gap ? '—' : (int) $a['score'] . '%'; ?></span>
							<?php if ( ! $gap ) : ?>
								<div class="rm-seo-sig">
									<?php if ( ! empty( $a['internal_links'] ) ) : ?><span title="<?php esc_attr_e( 'internal links to this page', 'ricoman' ); ?>">🔗<?php echo (int) $a['internal_links']; ?></span><?php endif; ?>
									<?php if ( ! empty( $a['schema'] ) ) : ?><span title="<?php echo esc_attr( implode( ', ', $a['schema'] ) . ' schema' ); ?>">⌗<?php echo count( $a['schema'] ); ?></span><?php endif; ?>
								</div>
							<?php endif; ?>
						</td>
						<td><?php echo ricoman_seo_sparkline( $t['term'] ); // phpcs:ignore WordPress.Security.EscapeOutput — SVG built internally. ?></td>
						<?php if ( $gsc_on ) :
							$g = ricoman_gsc_term_data( $t['term'] ); ?>
							<td class="rm-seo-gsc">
								<?php if ( $g ) : ?>
									<strong title="<?php esc_attr_e( 'average position', 'ricoman' ); ?>"><?php echo esc_html( $g['position'] ); ?></strong>
									<div class="rm-seo-gsc-sub"><?php echo (int) $g['impressions']; ?> <?php esc_html_e( 'impr', 'ricoman' ); ?> · <?php echo (int) $g['clicks']; ?> <?php esc_html_e( 'clk', 'ricoman' ); ?></div>
								<?php else : ?>
									<span style="color:#aaa">—</span>
								<?php endif; ?>
							</td>
						<?php endif; ?>
						<td class="rm-seo-checks">
							<?php
							if ( $gap ) {
								echo '<strong style="color:#b32d2e">' . esc_html__( 'No page yet.', 'ricoman' ) . '</strong> ';
								echo '<a class="button button-small" href="' . esc_url( $gap_url ) . '">' . esc_html__( 'Create page', 'ricoman' ) . '</a>';
							} else {
								// Split the failures into what Optimise handles (SEO title/meta) and
								// what lives on the page itself (heading, URL, body, FAQ).
								$meta_bad    = array();
								$content_bad = array();
								foreach ( $a['checks'] as $c ) {
									if ( false !== $c[1] ) {
										continue;
									}
									if ( false !== stripos( $c[0], 'SEO title' ) || false !== stripos( $c[0], 'Meta description' ) ) {
										$meta_bad[] = $c[0];
									} else {
										$content_bad[] = $c[0];
									}
								}
								if ( ! $meta_bad && ! $content_bad ) {
									echo '<span style="color:#1a7f37">' . esc_html__( 'All on-page signals present.', 'ricoman' ) . '</span>';
								} else {
									if ( $meta_bad ) {
										echo '<div><strong>' . esc_html__( 'SEO fields:', 'ricoman' ) . '</strong> ' . esc_html( implode( ' · ', $meta_bad ) )
											. ' <a class="button button-small button-primary" href="' . esc_url( $opt_url ) . '">' . esc_html__( 'Optimise', 'ricoman' ) . '</a></div>';
									}
									if ( $content_bad ) {
										echo '<div style="margin-top:' . ( $meta_bad ? '6px' : '0' ) . '"><strong>' . esc_html__( 'On the page:', 'ricoman' ) . '</strong> ' . esc_html( implode( ' · ', $content_bad ) ) . ' ';
										if ( ! empty( $a['edit'] ) ) {
											echo '<a class="button button-small" href="' . esc_url( $a['edit'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Edit page →', 'ricoman' ) . '</a>';
										}
										echo '<div class="description" style="margin-top:3px">' . esc_html__( 'Heading, URL slug or body text — change these in the page editor (usually fine to leave on identity, category or tool pages).', 'ricoman' ) . '</div></div>';
									}
								}
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
					<tr class="rm-seo-add">
						<td><input type="text" name="t[new][term]" placeholder="<?php esc_attr_e( 'add a new term…', 'ricoman' ); ?>" style="width:100%"></td>
						<td><select name="t[new][intent]"><?php foreach ( $intents as $opt ) { echo '<option>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><select name="t[new][priority]"><?php foreach ( $priorities as $opt ) { echo '<option' . selected( 'Medium', $opt, false ) . '>' . esc_html( $opt ) . '</option>'; } ?></select></td>
						<td><input type="text" name="t[new][url]" placeholder="/page-slug/" style="width:80%"></td>
						<td>—</td><td>—</td><?php if ( $gsc_on ) : ?><td>—</td><?php endif; ?><td><?php esc_html_e( '(new row)', 'ricoman' ); ?></td>
					</tr>
				</tbody>
			</table>
			<p><?php submit_button( __( 'Save targets', 'ricoman' ), 'primary', 'submit', false ); ?>
				<span class="description" style="margin-left:10px"><?php esc_html_e( 'A weekly snapshot powers the trend sparkline + the emailed digest.', 'ricoman' ); ?></span></p>
		</form>

		<?php
		if ( $gsc_on ) {
			$opp = ricoman_gsc_opportunities( $targets );
			if ( ! empty( $opp['page2'] ) || ! empty( $opp['new'] ) ) {
				echo '<h2 style="margin-top:26px">' . esc_html__( 'Opportunities (from Search Console)', 'ricoman' ) . '</h2>';
				echo '<div class="rm-seo-opps">';
				if ( ! empty( $opp['page2'] ) ) {
					echo '<div class="rm-seo-opp"><h3>' . esc_html__( 'Page-2 quick wins', 'ricoman' ) . '</h3><p class="description">' . esc_html__( 'Targeted terms ranking 11–20 with real impressions — a small push could move them to page 1.', 'ricoman' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Query', 'ricoman' ) . '</th><th>' . esc_html__( 'Pos', 'ricoman' ) . '</th><th>' . esc_html__( 'Impr', 'ricoman' ) . '</th></tr></thead><tbody>';
					foreach ( $opp['page2'] as $o ) {
						echo '<tr><td>' . esc_html( $o['query'] ) . '</td><td>' . esc_html( $o['position'] ) . '</td><td>' . (int) $o['impressions'] . '</td></tr>';
					}
					echo '</tbody></table></div>';
				}
				if ( ! empty( $opp['new'] ) ) {
					echo '<div class="rm-seo-opp"><h3>' . esc_html__( 'New queries to consider targeting', 'ricoman' ) . '</h3><p class="description">' . esc_html__( 'High-impression searches you already appear for that aren’t in your target list yet.', 'ricoman' ) . '</p><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Query', 'ricoman' ) . '</th><th>' . esc_html__( 'Pos', 'ricoman' ) . '</th><th>' . esc_html__( 'Impr', 'ricoman' ) . '</th></tr></thead><tbody>';
					foreach ( $opp['new'] as $o ) {
						echo '<tr><td>' . esc_html( $o['query'] ) . '</td><td>' . esc_html( $o['position'] ) . '</td><td>' . (int) $o['impressions'] . '</td></tr>';
					}
					echo '</tbody></table></div>';
				}
				echo '</div>';
			}
		}
		?>

		<style>
		.rm-seo-sum{margin:10px 0 14px}
		.rm-seo-pill{display:inline-block;padding:5px 12px;border-radius:999px;color:#fff;font-weight:600;margin-right:8px;font-size:13px}
		.rm-seo-pill.green{background:#1a7f37}.rm-seo-pill.amber{background:#b8860b}.rm-seo-pill.red{background:#b32d2e}
		.rm-seo-table input[type=text],.rm-seo-table select{font-size:13px}
		.rm-seo-score{display:inline-block;min-width:42px;text-align:center;padding:3px 8px;border-radius:6px;font-weight:700;color:#fff}
		.rm-seo-score.rm-green{background:#1a7f37}.rm-seo-score.rm-amber{background:#b8860b}.rm-seo-score.rm-red{background:#b32d2e}
		.rm-seo-sig{margin-top:4px;font-size:11px;color:#666}.rm-seo-sig span{margin-right:6px}
		.rm-seo-checks{font-size:12.5px;color:#555}
		.rm-seo-sugg{font-size:12px;margin-top:4px}
		.rm-seo-gsc strong{font-size:15px}.rm-seo-gsc-sub{font-size:11px;color:#777}
		.rm-seo-add td{background:#f6f7f9}
		.rm-seo-opps{display:flex;gap:22px;flex-wrap:wrap}.rm-seo-opp{flex:1;min-width:340px}
		</style>
		<script>
		jQuery(function($){
			$('.rm-seo-pick').on('click',function(e){
				e.preventDefault();
				var i=$(this).data('i'), u=$(this).data('url');
				$('input[name="t['+i+'][url]"]').val(u).css('background','#fffbcc');
			});
		});
		</script>
	</div>
	<?php
}

/* ----------------------------------------------- one-time pages + optimise */

/**
 * One-time (on existing, already-scaffolded sites): create the sector /
 * application landing pages for the SEO targets, point any matching targets at
 * them, and fill empty SEO fields for every target page. Everything is
 * create-only / fill-empty, so it never overwrites edited content. Admin only.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_seo_pages_v1' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$pages = array(
		'office-lighting'             => array( 'Office Lighting', 'ricoman_office_lighting_blocks' ),
		'gym-sports-hall-lighting'    => array( 'Gym & Sports Hall Lighting', 'ricoman_gym_sports_lighting_blocks' ),
		'education-lighting'          => array( 'School & Education Lighting', 'ricoman_education_lighting_blocks' ),
		'retail-lighting'            => array( 'Retail Lighting', 'ricoman_retail_lighting_blocks' ),
		'warehouse-high-bay-lighting' => array( 'Warehouse & High Bay Lighting', 'ricoman_warehouse_highbay_blocks' ),
		'feature-lighting'           => array( 'Feature Lighting', 'ricoman_feature_lighting_blocks' ),
		'suspended-linear-lighting'   => array( 'Suspended Linear Lighting', 'ricoman_suspended_linear_blocks' ),
	);
	foreach ( $pages as $slug => $info ) {
		if ( get_page_by_path( $slug ) ) {
			continue; // already exists — never overwrite.
		}
		if ( function_exists( 'ricoman_make_post' ) && function_exists( $info[1] ) ) {
			ricoman_make_post( 'page', $info[0], $slug, call_user_func( $info[1] ), 'page-plain' );
		}
	}
	// Point matching stored targets at the new pages (fill-empty URL only).
	$url_for = array(
		'suspended linear lighting'    => '/suspended-linear-lighting/',
		'office lighting'              => '/office-lighting/',
		'ugr19 office linear lighting' => '/office-lighting/',
		'gym sports hall lighting'     => '/gym-sports-hall-lighting/',
		'school education lighting'    => '/education-lighting/',
		'retail lighting'              => '/retail-lighting/',
		'warehouse high bay lighting'  => '/warehouse-high-bay-lighting/',
		'feature lighting'             => '/feature-lighting/',
	);
	$targets = ricoman_seo_targets();
	$changed = false;
	foreach ( $targets as $k => $t ) {
		$norm = ricoman_seo_norm( $t['term'] );
		if ( isset( $url_for[ $norm ] ) && '' === trim( (string) $t['url'] ) ) {
			$targets[ $k ]['url'] = $url_for[ $norm ];
			$changed = true;
		}
	}
	if ( $changed ) {
		update_option( 'ricoman_seo_targets', $targets, false );
	}
	// Fill empty SEO title/meta/focus for every target's page (fill-empty, safe).
	foreach ( ricoman_seo_targets() as $t ) {
		ricoman_seo_optimise_target( $t );
	}
	// Add the new pages to the footer feature-link column (skips ones already linked).
	if ( function_exists( 'ricoman_footer_links_topup' ) ) {
		ricoman_footer_links_topup();
	}
	update_option( 'ricoman_seo_pages_v1', 1 );
} );

/**
 * One-time: upgrade the earlier generic auto-filled SEO copy to the hand-written,
 * keyword-led titles & descriptions. Force mode overwrites ONLY values still equal
 * to the old generated string — human edits are never touched.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_seo_optimised_v2' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	foreach ( ricoman_seo_targets() as $t ) {
		ricoman_seo_optimise_target( $t, true );
	}
	update_option( 'ricoman_seo_optimised_v2', 1 );
} );

/**
 * One-time: refresh the 7 sector / application pages with the richer layout
 * (full-bleed bands + extra CTAs). Only rewrites a page that hasn't been edited
 * by a human since it was created (post_modified ~ post_date), so it never
 * clobbers your edits.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'ricoman_seo_pages_v2' ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$map = array(
		'office-lighting'             => 'ricoman_office_lighting_blocks',
		'gym-sports-hall-lighting'    => 'ricoman_gym_sports_lighting_blocks',
		'education-lighting'          => 'ricoman_education_lighting_blocks',
		'retail-lighting'            => 'ricoman_retail_lighting_blocks',
		'warehouse-high-bay-lighting' => 'ricoman_warehouse_highbay_blocks',
		'feature-lighting'           => 'ricoman_feature_lighting_blocks',
		'suspended-linear-lighting'   => 'ricoman_suspended_linear_blocks',
	);
	foreach ( $map as $slug => $fn ) {
		$page = get_page_by_path( $slug );
		if ( ! $page || ! function_exists( $fn ) ) {
			continue;
		}
		// Skip if a human has edited it (modified more than ~2 min after creation).
		$created  = strtotime( $page->post_date_gmt );
		$modified = strtotime( $page->post_modified_gmt );
		if ( $created && $modified && ( $modified - $created ) > 120 ) {
			continue;
		}
		wp_update_post( array( 'ID' => $page->ID, 'post_content' => call_user_func( $fn ) ) );
	}
	update_option( 'ricoman_seo_pages_v2', 1 );
} );

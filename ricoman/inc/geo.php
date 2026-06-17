<?php
/**
 * GEO — Generative Engine Optimisation (AI search).
 *
 * Helps answer engines (ChatGPT, Claude, Perplexity, Gemini, Google AI
 * Overviews) understand, trust and cite the site:
 *  - FAQ shortcode that emits accessible Q&A + FAQPage schema (AI loves clean Q&A).
 *  - Speakable / WebPage schema marking the key passages on each page.
 *  - A generated /llms.txt site guide (the emerging "robots.txt for LLMs").
 *  - Explicit access for AI crawlers in robots.txt (toggle in SEO settings),
 *    because for generative SEO you *want* these bots to read you.
 *
 * Pairs with inc/seo.php (reuses ricoman_seo_opt / ricoman_seo_plugin_active).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Safe wrapper for the SEO option helper (in case load order changes). */
function ricoman_geo_opt( $key, $default = '' ) {
	return function_exists( 'ricoman_seo_opt' ) ? ricoman_seo_opt( $key, $default ) : $default;
}
function ricoman_geo_seo_plugin() {
	return function_exists( 'ricoman_seo_plugin_active' ) ? ricoman_seo_plugin_active() : false;
}

/* ---------------------------------------------------------------------------
 * FAQ shortcode  ->  accessible accordion + FAQPage JSON-LD
 *
 * Usage:
 *   [ricoman_faq]
 *   Q: What lead time can we expect?
 *   A: UK-made fittings ship on an average six-day lead.
 *   Q: Do you offer free lighting design?
 *   A: Yes — send drawings and we return a costed scheme in 3–5 days.
 *   [/ricoman_faq]
 * ------------------------------------------------------------------------- */
add_shortcode( 'ricoman_faq', 'ricoman_faq_shortcode' );

function ricoman_faq_shortcode( $atts, $content = '' ) {
	if ( ! $content ) {
		return '';
	}
	$lines = preg_split( '/\r\n|\r|\n/', wp_strip_all_tags( $content ) );
	$faqs  = array();
	$cur   = null;
	foreach ( $lines as $ln ) {
		$ln = trim( $ln );
		if ( '' === $ln ) {
			continue;
		}
		if ( preg_match( '/^Q[:.\)]\s*(.+)/i', $ln, $m ) ) {
			if ( $cur ) {
				$faqs[] = $cur;
			}
			$cur = array( 'q' => $m[1], 'a' => '' );
		} elseif ( preg_match( '/^A[:.\)]\s*(.+)/i', $ln, $m ) ) {
			if ( $cur ) {
				$cur['a'] .= ( $cur['a'] ? ' ' : '' ) . $m[1];
			}
		} elseif ( $cur ) {
			if ( $cur['a'] ) {
				$cur['a'] .= ' ' . $ln;
			} else {
				$cur['q'] .= ' ' . $ln;
			}
		}
	}
	if ( $cur ) {
		$faqs[] = $cur;
	}
	if ( ! $faqs ) {
		return '';
	}

	$html = ricoman_faq_styles();
	$html .= '<div class="ricoman-faq">';
	foreach ( $faqs as $f ) {
		$html .= '<details class="ricoman-faq__item"><summary>' . esc_html( $f['q'] ) . '</summary>'
			. '<div class="ricoman-faq__a">' . wpautop( esc_html( $f['a'] ) ) . '</div></details>';
	}
	$html .= '</div>';

	$entities = array();
	foreach ( $faqs as $f ) {
		if ( '' === $f['a'] ) {
			continue;
		}
		$entities[] = array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
		);
	}
	if ( $entities ) {
		$data  = array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $entities );
		$html .= '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
	}
	return $html;
}

/** Minimal FAQ styling, printed once. */
function ricoman_faq_styles() {
	static $done = false;
	if ( $done ) {
		return '';
	}
	$done = true;
	return '<style>.ricoman-faq__item{border-top:1px solid var(--wp--preset--color--line,#e3e5ea);padding:0}'
		. '.ricoman-faq__item:last-child{border-bottom:1px solid var(--wp--preset--color--line,#e3e5ea)}'
		. '.ricoman-faq summary{cursor:pointer;list-style:none;padding:1rem 0;font-weight:600}'
		. '.ricoman-faq summary::-webkit-details-marker{display:none}'
		. '.ricoman-faq summary::after{content:"+";float:right;font-weight:400}'
		. '.ricoman-faq__item[open] summary::after{content:"–"}'
		. '.ricoman-faq__a{padding:0 0 1rem}</style>';
}

/* ---------------------------------------------------------------------------
 * Speakable / WebPage schema for singular content.
 * ------------------------------------------------------------------------- */
add_action( 'wp_head', function () {
	if ( ricoman_geo_seo_plugin() || ! is_singular() ) {
		return;
	}
	$data = array(
		'@context'  => 'https://schema.org',
		'@type'     => 'WebPage',
		'@id'       => get_permalink() . '#webpage',
		'url'       => get_permalink(),
		'name'      => wp_get_document_title(),
		'isPartOf'  => array( '@id' => home_url( '/#website' ) ),
		'speakable' => array(
			'@type'       => 'SpeakableSpecification',
			'cssSelector' => array( 'h1', '.ricoman-summary', 'main p:first-of-type' ),
		),
	);
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 21 );

/* ---------------------------------------------------------------------------
 * /llms.txt — a concise, machine-readable site guide for LLMs.
 * ------------------------------------------------------------------------- */
add_action( 'template_redirect', function () {
	$path = (string) wp_parse_url( (string) ( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '' ), PHP_URL_PATH );
	if ( 'llms.txt' !== trim( $path, '/' ) ) {
		return;
	}
	nocache_headers();
	header( 'Content-Type: text/plain; charset=utf-8' );
	echo ricoman_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
} );

function ricoman_llms_excerpt( $post ) {
	$d = get_post_meta( $post->ID, '_ricoman_seo_desc', true );
	if ( ! $d ) {
		$d = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
	}
	$d = wp_strip_all_tags( (string) $d );
	return $d ? ': ' . wp_trim_words( $d, 20, '…' ) : '';
}

function ricoman_llms_txt() {
	$out  = '# ' . get_bloginfo( 'name' ) . "\n\n";
	$desc = get_bloginfo( 'description' );
	if ( $desc ) {
		$out .= '> ' . $desc . "\n\n";
	}
	$out .= 'This file helps AI assistants understand and cite ' . get_bloginfo( 'name' ) . ". \n\n";

	$pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 40 ) );
	if ( $pages ) {
		$out .= "## Key pages\n";
		foreach ( $pages as $p ) {
			$out .= '- [' . $p->post_title . '](' . get_permalink( $p ) . ')' . ricoman_llms_excerpt( $p ) . "\n";
		}
		$out .= "\n";
	}

	foreach ( array( 'product' => 'Products', 'project' => 'Projects' ) as $pt => $label ) {
		$archive = get_post_type_archive_link( $pt );
		if ( ! $archive ) {
			continue;
		}
		$out  .= '## ' . $label . "\n";
		$out  .= '- [' . $label . '](' . $archive . ")\n";
		$items = get_posts( array( 'post_type' => $pt, 'numberposts' => 60, 'post_status' => 'publish' ) );
		foreach ( $items as $it ) {
			$out .= '- [' . get_the_title( $it ) . '](' . get_permalink( $it ) . ')' . ricoman_llms_excerpt( $it ) . "\n";
		}
		$out .= "\n";
	}

	$out .= "## Contact\n";
	$phone = ricoman_geo_opt( 'phone' );
	$email = ricoman_geo_opt( 'email' );
	if ( $phone ) {
		$out .= '- Phone: ' . $phone . "\n";
	}
	if ( $email ) {
		$out .= '- Email: ' . $email . "\n";
	}
	$out .= '- Website: ' . home_url( '/' ) . "\n";

	return $out;
}

/* ---------------------------------------------------------------------------
 * Welcome AI crawlers in robots.txt (toggle in Settings → Ricoman SEO).
 * ------------------------------------------------------------------------- */
add_filter( 'robots_txt', function ( $output ) {
	$allow = ( '' !== ricoman_geo_opt( 'allow_ai', '1' ) && '0' !== ricoman_geo_opt( 'allow_ai', '1' ) );
	$bots  = array(
		'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'anthropic-ai',
		'Claude-Web', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended',
		'CCBot', 'Amazonbot', 'Bytespider', 'cohere-ai',
	);
	$out = "\n# AI / generative-search crawlers\n";
	foreach ( $bots as $b ) {
		$out .= 'User-agent: ' . $b . "\n" . ( $allow ? "Allow: /\n" : "Disallow: /\n" );
	}
	$out .= "\n# LLM site guide: " . home_url( '/llms.txt' ) . "\n";
	return $output . $out;
}, 21 );

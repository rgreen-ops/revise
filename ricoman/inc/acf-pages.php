<?php
/**
 * Generic ACF page renderer.
 *
 * The migrated content pages (About, Sustainability, Casambi, Human Centric,
 * Custom Lighting, Fire Safety, UAE Exports, etc.) store all their content in
 * bespoke ACF field groups. Rather than hand-build 20+ templates up-front, this
 * reads each page's ACF fields and renders them natively in our theme — banner,
 * sections, repeaters, galleries — so every ACF page displays its real content
 * with no Elementor. Individual high-value pages get bespoke layouts on top.
 *
 * Only runs on pages whose post_content is empty (i.e. the migrated ACF pages);
 * pages we build with blocks are untouched.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || ! is_singular( 'page' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		return $content; // has real (block/classic) content already.
	}
	// Known listing pages (were Elementor) -> native shortcodes.
	$slug = get_post_field( 'post_name', get_the_ID() );
	$map  = array(
		'our-news'  => '[ricoman_news_grid]',
		'news'      => '[ricoman_news_grid]',
		'products'  => '[ricoman_catalogue]',
		'downloads' => '[ricoman_catalogue]',
	);
	if ( isset( $map[ $slug ] ) ) {
		return do_shortcode( $map[ $slug ] );
	}
	if ( ! function_exists( 'get_field_objects' ) ) {
		return $content;
	}
	$html = ricoman_render_acf_page( get_the_ID() );
	return '' !== $html ? $html : $content;
}, 9 );

/** url helpers (shared with acf-product.php when present). */
if ( ! function_exists( 'ricoman_pf_imgurl' ) ) {
	function ricoman_pf_imgurl( $v ) {
		if ( is_numeric( $v ) ) {
			$u = wp_get_attachment_image_url( (int) $v, 'large' );
			return $u ? $u : '';
		}
		if ( is_array( $v ) ) {
			return $v['url'] ?? ( $v['sizes']['large'] ?? '' );
		}
		return is_string( $v ) ? $v : '';
	}
}

/** Build the page from its ACF field objects, in field order, grouped by tabs. */
function ricoman_render_acf_page( $pid ) {
	$fields = get_field_objects( $pid );
	if ( ! is_array( $fields ) || ! $fields ) {
		return '';
	}

	$e = 'esc_html';

	// ---- Banner / hero (look for *banner_image + *banner_title) ----
	$bimg = $btitle = $bsub = '';
	foreach ( $fields as $name => $f ) {
		$v = $f['value'] ?? '';
		if ( '' === $v ) {
			continue;
		}
		if ( '' === $bimg && preg_match( '/banner.*image|image.*banner/i', $name ) && 'image' === $f['type'] ) {
			$bimg = ricoman_pf_imgurl( $v );
		} elseif ( '' === $btitle && preg_match( '/banner.*title|banner_main_text|banner_title/i', $name ) && in_array( $f['type'], array( 'text', 'textarea' ), true ) ) {
			$btitle = $v;
		} elseif ( '' === $bsub && preg_match( '/banner.*sub|sub.*banner|banner.*description/i', $name ) && in_array( $f['type'], array( 'text', 'textarea' ), true ) ) {
			$bsub = $v;
		}
	}
	if ( '' === $btitle ) {
		$btitle = get_the_title( $pid );
	}

	$out = '';
	if ( $bimg ) {
		$out .= '<div class="wp-block-cover alignfull rm-apage-hero has-base-color has-text-color has-custom-content-position is-position-bottom-left" style="min-height:46vh">'
			. '<span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span>'
			. '<img class="wp-block-cover__image-background" alt="" src="' . esc_url( $bimg ) . '" data-object-fit="cover"/>'
			. '<div class="wp-block-cover__inner-container"><h1 class="rm-apage-htitle">' . $e( $btitle ) . '</h1>'
			. ( $bsub ? '<p class="rm-apage-hsub">' . $e( $bsub ) . '</p>' : '' ) . '</div></div>';
	} else {
		$out .= '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap"><h1 class="rm-apage-title">' . $e( $btitle ) . '</h1>'
			. ( $bsub ? '<p class="rm-cfg-desc">' . $e( $bsub ) . '</p>' : '' ) . '</div></div>';
	}

	// ---- Body: walk fields in order, render with light heuristics ----
	$body       = '';
	$pending_btn = '';
	$used_banner = array(); // skip the fields we already used for the hero.

	foreach ( $fields as $name => $f ) {
		$type  = $f['type'];
		$label = (string) ( $f['label'] ?? '' );
		$val   = $f['value'] ?? '';

		// Skip banner fields already shown + admin-only tabs handled as spacing.
		if ( preg_match( '/banner/i', $name ) && in_array( $type, array( 'image', 'text', 'textarea' ), true ) ) {
			continue;
		}
		if ( 'tab' === $type ) {
			$body .= '<hr class="rm-apage-div" aria-hidden="true">';
			continue;
		}
		if ( '' === $val || array() === $val ) {
			continue;
		}

		// Button text/link pairing.
		if ( preg_match( '/button\s*(text|title)/i', $label ) && is_string( $val ) ) {
			$pending_btn = $val;
			continue;
		}
		if ( preg_match( '/button\s*(link|url)/i', $label ) && is_string( $val ) ) {
			$lbl = $pending_btn ? $pending_btn : 'Find out more';
			$body .= '<p class="rm-apage-btn"><a class="btn btn-line-d" href="' . esc_url( $val ) . '">' . $e( $lbl ) . ' →</a></p>';
			$pending_btn = '';
			continue;
		}
		if ( preg_match( '/button\s*icon/i', $label ) ) {
			continue; // decorative.
		}

		switch ( $type ) {
			case 'text':
				if ( preg_match( '/(title|heading)/i', $label ) ) {
					$body .= '<h2 class="rm-shead">' . $e( $val ) . '</h2>';
				} elseif ( preg_match( '/(subtitle|sub title|tag\s*line)/i', $label ) ) {
					$body .= '<p class="rm-eyebrow">' . $e( $val ) . '</p>';
				} else {
					$body .= '<p>' . $e( $val ) . '</p>';
				}
				break;
			case 'textarea':
				$body .= wpautop( $e( $val ) );
				break;
			case 'wysiwyg':
				$body .= '<div class="rm-apage-wysiwyg">' . wp_kses_post( $val ) . '</div>';
				break;
			case 'image':
				$u = ricoman_pf_imgurl( $val );
				if ( $u && ! preg_match( '/(icon|logo)/i', $label ) ) {
					$body .= '<figure class="wp-block-image size-large rm-apage-img"><img src="' . esc_url( $u ) . '" alt="' . esc_attr( $label ) . '" loading="lazy"></figure>';
				}
				break;
			case 'file':
				$u = is_string( $val ) ? $val : ( is_array( $val ) ? ( $val['url'] ?? '' ) : '' );
				if ( $u ) {
					$body .= '<p class="rm-apage-btn"><a class="btn btn-line-d" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . $e( $label ) . ' ↓</a></p>';
				}
				break;
			case 'oembed':
				$body .= '<div class="rm-apage-embed">' . wp_kses_post( $val ) . '</div>';
				break;
			case 'gallery':
				if ( is_array( $val ) ) {
					$g = '';
					foreach ( $val as $im ) {
						$u = ricoman_pf_imgurl( $im );
						if ( $u ) {
							$g .= '<img src="' . esc_url( $u ) . '" alt="" loading="lazy">';
						}
					}
					if ( $g ) {
						$body .= '<div class="rm-apage-gallery">' . $g . '</div>';
					}
				}
				break;
			case 'repeater':
				$body .= ricoman_render_acf_repeater( $label, $val );
				break;
		}
	}

	$out .= '<div class="rm-section"><div class="rm-pp-wrap rm-apage-body">' . $body . '</div></div>';
	return $out;
}

/** Render an ACF repeater as a row of cards (image + title + text + link). */
function ricoman_render_acf_repeater( $label, $rows ) {
	if ( ! is_array( $rows ) || ! $rows ) {
		return '';
	}
	$cards = '';
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$img = $title = $desc = $link = $linktext = '';
		foreach ( $row as $k => $v ) {
			$lk = strtolower( (string) $k );
			if ( '' === $v || is_array( $v ) && ! isset( $v['url'] ) ) {
				if ( is_array( $v ) && isset( $v['url'] ) ) {
					// link array.
				} else {
					// fallthrough for scalars below.
				}
			}
			if ( false !== strpos( $lk, 'image' ) || false !== strpos( $lk, 'icon' ) || false !== strpos( $lk, 'logo' ) || false !== strpos( $lk, 'picture' ) ) {
				$u = ricoman_pf_imgurl( $v );
				if ( $u && '' === $img ) {
					$img = $u;
				}
			} elseif ( false !== strpos( $lk, 'title' ) || false !== strpos( $lk, 'name' ) || false !== strpos( $lk, 'heading' ) ) {
				if ( is_scalar( $v ) && '' === $title ) {
					$title = (string) $v;
				}
			} elseif ( false !== strpos( $lk, 'link' ) || false !== strpos( $lk, 'url' ) ) {
				$link = is_array( $v ) ? ( $v['url'] ?? '' ) : (string) $v;
			} elseif ( false !== strpos( $lk, 'description' ) || false !== strpos( $lk, 'content' ) || false !== strpos( $lk, 'detail' ) || false !== strpos( $lk, 'text' ) || false !== strpos( $lk, 'comment' ) || false !== strpos( $lk, 'subtitle' ) ) {
				if ( is_scalar( $v ) && '' === $desc ) {
					$desc = (string) $v;
				}
			}
		}
		if ( '' === $img && '' === $title && '' === $desc ) {
			continue;
		}
		$inner = ( $img ? '<div class="rm-acard-img" style="background-image:url(' . esc_url( $img ) . ')"></div>' : '' )
			. '<div class="rm-acard-body">'
			. ( $title ? '<h3>' . esc_html( $title ) . '</h3>' : '' )
			. ( $desc ? '<div class="rm-acard-desc">' . wp_kses_post( wpautop( $desc ) ) . '</div>' : '' )
			. '</div>';
		$cards .= $link
			? '<a class="rm-acard" href="' . esc_url( $link ) . '">' . $inner . '</a>'
			: '<div class="rm-acard">' . $inner . '</div>';
	}
	if ( '' === $cards ) {
		return '';
	}
	$head = ( $label && ! preg_match( '/^(content|body|repeater|list|section)/i', $label ) ) ? '<h2 class="rm-shead">' . esc_html( $label ) . '</h2>' : '';
	return $head . '<div class="rm-acards">' . $cards . '</div>';
}

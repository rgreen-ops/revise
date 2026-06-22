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
	$slug = get_post_field( 'post_name', get_the_ID() );
	// Downloads is rendered dynamically from the real files on the server, so it
	// always reflects what's actually downloadable — overriding any stale block
	// content left from an earlier build.
	if ( 'downloads' === $slug && function_exists( 'ricoman_downloads_page_html' ) ) {
		return ricoman_downloads_page_html();
	}
	if ( '' !== trim( wp_strip_all_tags( (string) $content ) ) ) {
		return $content; // has real (block/classic) content already.
	}
	// Known listing pages (were Elementor) -> native shortcodes.
	$map  = array(
		'our-news'   => '[ricoman_news_grid]',
		'news'       => '[ricoman_news_grid]',
		'products'   => '[ricoman_catalogue]',
		'contact'    => '[ricoman_contact]',
		'contact-us' => '[ricoman_contact]',
	);
	if ( isset( $map[ $slug ] ) ) {
		return do_shortcode( $map[ $slug ] );
	}
	if ( function_exists( 'get_field_objects' ) ) {
		$html = ricoman_render_acf_page( get_the_ID() );
		if ( '' !== $html ) {
			return $html;
		}
	}
	// Last resort: a page with neither blocks nor ACF content (e.g. the few
	// Elementor-only pages still awaiting a bespoke layout). Render a clean
	// titled header instead of a blank screen, plus breadcrumbs for context.
	$title = get_the_title( get_the_ID() );
	$crumb = shortcode_exists( 'ricoman_breadcrumbs' ) ? do_shortcode( '[ricoman_breadcrumbs]' ) : '';
	return '<div class="rm-section rm-pp-crumbwrap"><div class="rm-pp-wrap">'
		. ( $crumb ? '<div class="rm-pp-crumb">' . $crumb . '</div>' : '' )
		. '<h1 class="rm-apage-title">' . esc_html( $title ) . '</h1></div></div>';
}, 9 );

/**
 * Hide the legacy ACF "Extra Fields" meta boxes in the editor on managed static
 * pages that now use block content (so the page renders from blocks, not ACF).
 * Stops admins editing dead fields. Only removes them once the page has real
 * block content — pages still on ACF keep their boxes so they stay editable.
 */
add_action( 'add_meta_boxes', function ( $post_type, $post ) {
	if ( 'page' !== $post_type || ! ( $post instanceof WP_Post ) ) {
		return;
	}
	$map = function_exists( 'ricoman_theme_page_map' ) ? ricoman_theme_page_map() : array();
	if ( ! isset( $map[ $post->post_name ] ) || '' === trim( (string) $post->post_content ) ) {
		return; // not a managed page, or still ACF-rendered.
	}
	global $wp_meta_boxes;
	foreach ( array( 'normal', 'advanced', 'side' ) as $ctx ) {
		if ( empty( $wp_meta_boxes['page'][ $ctx ] ) ) {
			continue;
		}
		foreach ( $wp_meta_boxes['page'][ $ctx ] as $boxes ) {
			foreach ( array_keys( (array) $boxes ) as $id ) {
				if ( 0 === strpos( (string) $id, 'acf-' ) ) {
					remove_meta_box( $id, 'page', $ctx );
				}
			}
		}
	}
}, 100, 2 );

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

	// ---- Body: collect fields as typed items, then lay out (zig-zag) ----
	$items       = array();
	$pending_btn = '';

	foreach ( $fields as $name => $f ) {
		$type  = $f['type'];
		$label = (string) ( $f['label'] ?? '' );
		$val   = $f['value'] ?? '';

		if ( preg_match( '/banner/i', $name ) && in_array( $type, array( 'image', 'text', 'textarea' ), true ) ) {
			continue;
		}
		if ( 'tab' === $type ) {
			$items[] = array( 'kind' => 'divider', 'html' => '' );
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
			$lbl     = $pending_btn ? $pending_btn : 'Find out more';
			$items[] = array( 'kind' => 'body', 'html' => '<p class="rm-apage-btn"><a class="btn btn-line-d" href="' . esc_url( $val ) . '">' . $e( $lbl ) . ' →</a></p>' );
			$pending_btn = '';
			continue;
		}
		if ( preg_match( '/button\s*icon/i', $label ) ) {
			continue; // decorative.
		}

		switch ( $type ) {
			case 'text':
				if ( preg_match( '/(title|heading)/i', $label ) ) {
					$items[] = array( 'kind' => 'body', 'html' => '<h2 class="rm-shead">' . $e( $val ) . '</h2>' );
				} elseif ( preg_match( '/(subtitle|sub title|tag\s*line)/i', $label ) ) {
					$items[] = array( 'kind' => 'body', 'html' => '<p class="rm-eyebrow">' . $e( $val ) . '</p>' );
				} else {
					$items[] = array( 'kind' => 'body', 'html' => '<p>' . $e( $val ) . '</p>' );
				}
				break;
			case 'textarea':
				$items[] = array( 'kind' => 'body', 'html' => wpautop( $e( $val ) ) );
				break;
			case 'wysiwyg':
				$items[] = array( 'kind' => 'body', 'html' => '<div class="rm-apage-wysiwyg">' . wp_kses_post( $val ) . '</div>' );
				break;
			case 'image':
				$u = ricoman_pf_imgurl( $val );
				if ( $u && ! preg_match( '/(icon|logo)/i', $label ) ) {
					$items[] = array( 'kind' => 'media', 'html' => '<img src="' . esc_url( $u ) . '" alt="' . esc_attr( $label ) . '" loading="lazy">' );
				}
				break;
			case 'file':
				$u = is_string( $val ) ? $val : ( is_array( $val ) ? ( $val['url'] ?? '' ) : '' );
				if ( $u ) {
					$items[] = array( 'kind' => 'body', 'html' => '<p class="rm-apage-btn"><a class="btn btn-line-d" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . $e( $label ) . ' ↓</a></p>' );
				}
				break;
			case 'oembed':
				$items[] = array( 'kind' => 'media', 'html' => '<div class="rm-apage-embed">' . wp_kses_post( $val ) . '</div>' );
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
						$items[] = array( 'kind' => 'wide', 'html' => '<div class="rm-apage-gallery">' . $g . '</div>' );
					}
				}
				break;
			case 'repeater':
				$cards = ricoman_render_acf_repeater( $label, $val );
				if ( $cards ) {
					$items[] = array( 'kind' => 'wide', 'html' => $cards );
				}
				break;
		}
	}

	$out .= ricoman_acf_layout_items( $items );

	// Closing call-to-action band — on marketing pages, not legal/utility ones.
	$slug = get_post_field( 'post_name', $pid );
	if ( ! preg_match( '/privacy|cookie|terms|slavery|email-notice|site-?map|thank|warranty|legal|login|register|dashboard|account/i', $slug ) ) {
		$img = esc_url( get_theme_file_uri( 'assets/images/office1.webp' ) );
		$out .= '<div class="wp-block-cover alignfull has-base-color has-text-color" style="min-height:42vh">'
			. '<span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-70 has-background-dim"></span>'
			. '<img class="wp-block-cover__image-background" alt="" src="' . $img . '" data-object-fit="cover"/>'
			. '<div class="wp-block-cover__inner-container"><h2 class="wp-block-heading has-text-align-center" style="text-align:center">Let’s plan your lighting</h2>'
			. '<p class="has-text-align-center" style="text-align:center">Talk to our team or request a free lighting design for your project.</p>'
			. '<div class="wp-block-buttons is-content-justification-center" style="display:flex;justify-content:center;gap:10px">'
			. '<a class="btn btn-solid" href="' . esc_url( home_url( '/lighting-design/' ) ) . '">Request a Lighting Design</a> '
			. '<a class="btn btn-line" href="' . esc_url( home_url( '/contact/' ) ) . '">Contact us</a></div></div></div>';
	}
	return $out;
}

/**
 * Lay collected items out like a real marketing page: pair each image/embed with
 * the text that follows it into an alternating (zig-zag) row; render runs of
 * text on their own as a centred prose block; galleries / card grids span wide.
 */
function ricoman_acf_layout_items( $items ) {
	$n   = count( $items );
	$out = '';
	$zz  = 0;
	$i   = 0;
	while ( $i < $n ) {
		$it = $items[ $i ];

		if ( 'media' === $it['kind'] ) {
			// Collect the body items that belong with this media.
			$bodyhtml = '';
			$j        = $i + 1;
			while ( $j < $n && 'body' === $items[ $j ]['kind'] ) {
				$bodyhtml .= $items[ $j ]['html'];
				$j++;
			}
			if ( '' !== $bodyhtml ) {
				$rev  = ( $zz % 2 ) ? ' rev' : '';
				$out .= '<div class="rm-section"><div class="rm-pp-wrap"><div class="rm-azz' . $rev . '">'
					. '<div class="rm-azz-media">' . $it['html'] . '</div>'
					. '<div class="rm-azz-body">' . $bodyhtml . '</div></div></div></div>';
				$zz++;
				$i = $j;
			} else {
				$out .= '<div class="rm-section"><div class="rm-pp-wrap"><figure class="rm-apage-img">' . $it['html'] . '</figure></div></div>';
				$i++;
			}
			continue;
		}

		if ( 'wide' === $it['kind'] ) {
			$out .= '<div class="rm-section"><div class="rm-pp-wrap">' . $it['html'] . '</div></div>';
			$i++;
			continue;
		}

		if ( 'divider' === $it['kind'] ) {
			$i++;
			continue;
		}

		// A run of body-only items -> a centred prose block.
		$buf = '';
		while ( $i < $n && 'body' === $items[ $i ]['kind'] ) {
			$buf .= $items[ $i ]['html'];
			$i++;
		}
		if ( '' !== $buf ) {
			$out .= '<div class="rm-section"><div class="rm-pp-wrap rm-apage-body">' . $buf . '</div></div>';
		} else {
			$i++; // safety against stalls.
		}
	}
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

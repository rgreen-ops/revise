<?php
/**
 * Content Transporter — WordPress migration.
 *
 * The current ricoman.com is WordPress whose pages render client-side, so
 * scraping the front-end HTML misses the content. This tool reads the *stored*
 * content instead, two ways:
 *
 *   1. WordPress export file (WXR .xml)  — Tools → Export on the old site.
 *   2. Live WordPress via the REST API   — content.rendered is server-side, so
 *      JS rendering doesn't matter.
 *
 * For each item it creates a draft on the template you choose, sideloads images
 * into the Media Library, maps title / description / featured image / body into
 * fields + blocks, and drops anything it can't confidently place into an
 * "Unsorted imported content" box on the draft.
 *
 * Tools → Content Transporter.  Requires the DOM extension.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---- Admin menu ---- */
add_action( 'admin_menu', function () {
	// Pre-launch content migration tool — hidden by default. Re-enable with:
	//   add_filter( 'ricoman_show_setup_tools', '__return_true' );
	if ( ! apply_filters( 'ricoman_show_setup_tools', false ) ) {
		return;
	}
	add_management_page(
		__( 'Content Transporter', 'ricoman' ),
		__( 'Content Transporter', 'ricoman' ),
		'edit_pages',
		'ricoman-transporter',
		'ricoman_transporter_page'
	);
} );

/**
 * Templates offered per post type (slug => label).
 *
 * @param string $post_type Post type.
 * @return array<string,string>
 */
function ricoman_transporter_templates( $post_type ) {
	$common = array( '' => __( 'Default template', 'ricoman' ) );
	if ( 'page' === $post_type ) {
		return $common + array(
			'page-landing'  => __( 'Landing (hero + CTA)', 'ricoman' ),
			'page-wide'     => __( 'Full width', 'ricoman' ),
			'page-sidebar'  => __( 'With sidebar', 'ricoman' ),
			'page-contact'  => __( 'Contact', 'ricoman' ),
			'page-no-title' => __( 'No title', 'ricoman' ),
			'page-canvas'   => __( 'Blank canvas', 'ricoman' ),
		);
	}
	if ( 'product' === $post_type ) {
		return $common + array( 'single-product-simple' => __( 'Product, simple', 'ricoman' ) );
	}
	if ( 'project' === $post_type ) {
		return $common + array( 'single-project-simple' => __( 'Project, simple', 'ricoman' ) );
	}
	return $common;
}

/* ---- Admin page ---- */
function ricoman_transporter_page() {
	if ( ! current_user_can( 'edit_pages' ) ) {
		return;
	}

	$results = array();

	// WXR upload.
	if ( isset( $_POST['ricoman_wxr'] ) && check_admin_referer( 'ricoman_wxr' ) ) {
		$pt   = isset( $_POST['wxr_post_type'] ) ? sanitize_key( $_POST['wxr_post_type'] ) : 'page';
		$tpl  = isset( $_POST['wxr_template'] ) ? sanitize_text_field( wp_unslash( $_POST['wxr_template'] ) ) : '';
		$only = isset( $_POST['wxr_only'] ) ? sanitize_text_field( wp_unslash( $_POST['wxr_only'] ) ) : 'page';
		if ( ! empty( $_FILES['wxr_file']['tmp_name'] ) && is_uploaded_file( $_FILES['wxr_file']['tmp_name'] ) ) {
			$xml     = file_get_contents( $_FILES['wxr_file']['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			$results = ricoman_transport_wxr( $xml, $only, $pt, $tpl );
		} else {
			$results[] = new WP_Error( 'no_file', __( 'Please choose a WordPress export (.xml) file.', 'ricoman' ) );
		}
	}

	// REST pull.
	if ( isset( $_POST['ricoman_rest'] ) && check_admin_referer( 'ricoman_rest' ) ) {
		$base = isset( $_POST['rest_url'] ) ? esc_url_raw( wp_unslash( $_POST['rest_url'] ) ) : '';
		$src  = isset( $_POST['rest_type'] ) ? sanitize_key( $_POST['rest_type'] ) : 'pages';
		$pt   = isset( $_POST['rest_post_type'] ) ? sanitize_key( $_POST['rest_post_type'] ) : 'page';
		$tpl  = isset( $_POST['rest_template'] ) ? sanitize_text_field( wp_unslash( $_POST['rest_template'] ) ) : '';
		$results = ricoman_transport_rest( $base, $src, $pt, $tpl );
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Content Transporter', 'ricoman' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Migrate the existing WordPress content into the new theme. Both methods read the stored content (so the old site’s JavaScript rendering doesn’t matter). Each item becomes a draft on the template you pick; images come across automatically and anything that doesn’t fit a field is saved to an “Unsorted content” box on the draft.', 'ricoman' ); ?></p>

		<?php if ( ! class_exists( 'DOMDocument' ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The PHP DOM extension is required and isn’t available on this server.', 'ricoman' ); ?></p></div>
		<?php endif; ?>

		<?php foreach ( $results as $r ) : ?>
			<?php if ( is_wp_error( $r ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $r->get_error_message() ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: 1: title, 2: images, 3: edit link, 4: orphan note */
						esc_html__( 'Imported “%1$s” (%2$d images). %3$s %4$s', 'ricoman' ),
						esc_html( $r['title'] ),
						(int) $r['images'],
						'<a href="' . esc_url( get_edit_post_link( $r['id'] ) ) . '">' . esc_html__( 'Edit draft →', 'ricoman' ) . '</a>',
						$r['orphan'] ? '<strong>' . esc_html__( '(some content went to the Unsorted box)', 'ricoman' ) . '</strong>' : ''
					);
					?>
				</p></div>
			<?php endif; ?>
		<?php endforeach; ?>

		<div style="display:grid;grid-template-columns:1fr 1fr;gap:28px;max-width:1100px;margin-top:18px">

			<!-- WXR -->
			<div class="card" style="padding:20px">
				<h2><?php esc_html_e( '1. From a WordPress export (.xml)', 'ricoman' ); ?></h2>
				<p class="description"><?php esc_html_e( 'On the old site: Tools → Export → download the .xml, then upload it here. Most reliable.', 'ricoman' ); ?></p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'ricoman_wxr' ); ?>
					<p><input type="file" name="wxr_file" accept=".xml"></p>
					<p>
						<label><?php esc_html_e( 'Import items of type', 'ricoman' ); ?>
							<input type="text" name="wxr_only" value="page" class="regular-text" placeholder="page, post, product…"></label>
						<span class="description"><?php esc_html_e( '(the old site’s post type)', 'ricoman' ); ?></span>
					</p>
					<p>
						<label><?php esc_html_e( 'Create here as', 'ricoman' ); ?>
							<select name="wxr_post_type" onchange="ricomanRt(this.value,'wxr_template')">
								<option value="page"><?php esc_html_e( 'Page', 'ricoman' ); ?></option>
								<option value="product"><?php esc_html_e( 'Product', 'ricoman' ); ?></option>
								<option value="project"><?php esc_html_e( 'Project', 'ricoman' ); ?></option>
							</select></label>
						<select name="wxr_template" id="wxr_template"></select>
					</p>
					<?php submit_button( __( 'Import from file', 'ricoman' ), 'primary', 'ricoman_wxr' ); ?>
				</form>
			</div>

			<!-- REST -->
			<div class="card" style="padding:20px">
				<h2><?php esc_html_e( '2. From a live WordPress (REST API)', 'ricoman' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Enter the old site’s address. Reads stored content via /wp-json, so JS rendering doesn’t matter. The source must be WordPress with the REST API enabled.', 'ricoman' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'ricoman_rest' ); ?>
					<p><input type="url" name="rest_url" class="regular-text" placeholder="https://ricoman.com/back-end" required></p>
				<p class="description"><?php esc_html_e( 'If WordPress is in a sub-folder (your admin is at /back-end/wp-admin), enter that — e.g. https://ricoman.com/back-end. The tool also auto-detects the REST root.', 'ricoman' ); ?></p>
					<p>
						<label><?php esc_html_e( 'Pull', 'ricoman' ); ?>
							<input type="text" name="rest_type" value="pages" class="regular-text" placeholder="pages, posts, product…"></label>
						<span class="description"><?php esc_html_e( '(REST base on the source)', 'ricoman' ); ?></span>
					</p>
					<p>
						<label><?php esc_html_e( 'Create here as', 'ricoman' ); ?>
							<select name="rest_post_type" onchange="ricomanRt(this.value,'rest_template')">
								<option value="page"><?php esc_html_e( 'Page', 'ricoman' ); ?></option>
								<option value="product"><?php esc_html_e( 'Product', 'ricoman' ); ?></option>
								<option value="project"><?php esc_html_e( 'Project', 'ricoman' ); ?></option>
							</select></label>
						<select name="rest_template" id="rest_template"></select>
					</p>
					<?php submit_button( __( 'Pull from REST', 'ricoman' ), 'secondary', 'ricoman_rest' ); ?>
				</form>
			</div>
		</div>

		<p class="description" style="margin-top:14px"><?php esc_html_e( 'Up to 50 items per run — run again to continue. Imports are drafts, so nothing goes live until you publish.', 'ricoman' ); ?></p>

		<script>
		var RICOMAN_RT = <?php echo wp_json_encode( array(
			'page'    => ricoman_transporter_templates( 'page' ),
			'product' => ricoman_transporter_templates( 'product' ),
			'project' => ricoman_transporter_templates( 'project' ),
		) ); ?>;
		function ricomanRt( pt, target ) {
			var sel = document.getElementById( target );
			if ( ! sel ) { return; }
			sel.innerHTML = '';
			var opts = RICOMAN_RT[ pt ] || {};
			Object.keys( opts ).forEach( function ( slug ) {
				var o = document.createElement( 'option' );
				o.value = slug; o.textContent = opts[ slug ];
				sel.appendChild( o );
			} );
		}
		ricomanRt( 'page', 'wxr_template' );
		ricomanRt( 'page', 'rest_template' );
		</script>
	</div>
	<?php
}

/* ---------------------------------------------------------------------------
 * WXR (.xml) import
 * ------------------------------------------------------------------------- */
function ricoman_transport_wxr( $xml, $only_type, $post_type, $template ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return array( new WP_Error( 'no_dom', __( 'PHP DOM extension is unavailable.', 'ricoman' ) ) );
	}
	$data = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING );
	if ( ! $data || ! isset( $data->channel ) ) {
		return array( new WP_Error( 'bad_xml', __( 'That doesn’t look like a WordPress export file.', 'ricoman' ) ) );
	}
	$ns = $data->getNamespaces( true );
	if ( empty( $ns['wp'] ) ) {
		return array( new WP_Error( 'bad_wxr', __( 'Missing the WordPress export namespace.', 'ricoman' ) ) );
	}

	// First pass: map attachment post_id -> URL.
	$attach = array();
	foreach ( $data->channel->item as $item ) {
		$wp = $item->children( $ns['wp'] );
		if ( 'attachment' === (string) $wp->post_type ) {
			$attach[ (string) $wp->post_id ] = (string) $wp->attachment_url;
		}
	}

	$results = array();
	$count   = 0;
	foreach ( $data->channel->item as $item ) {
		$wp = $item->children( $ns['wp'] );
		if ( (string) $wp->post_type !== $only_type ) {
			continue;
		}
		if ( $count >= 50 ) {
			break;
		}
		$count++;

		$title   = (string) $item->title;
		$content = isset( $ns['content'] ) ? (string) $item->children( $ns['content'] )->encoded : '';
		$excerpt = isset( $ns['excerpt'] ) ? (string) $item->children( $ns['excerpt'] )->encoded : '';

		// Featured image via _thumbnail_id.
		$featured = '';
		foreach ( $wp->postmeta as $meta ) {
			if ( '_thumbnail_id' === (string) $meta->meta_key ) {
				$tid = (string) $meta->meta_value;
				if ( isset( $attach[ $tid ] ) ) {
					$featured = $attach[ $tid ];
				}
			}
		}

		$results[] = ricoman_transport_create( array(
			'post_type'  => $post_type,
			'title'      => $title,
			'html'       => $content,
			'excerpt'    => $excerpt,
			'featured'   => $featured,
			'template'   => $template,
			'source_url' => (string) $item->link,
		) );
	}

	if ( ! $results ) {
		return array( new WP_Error( 'none', sprintf( /* translators: %s: type */ __( 'No “%s” items found in that file.', 'ricoman' ), $only_type ) ) );
	}
	return $results;
}

/* ---------------------------------------------------------------------------
 * REST API import
 * ------------------------------------------------------------------------- */
function ricoman_transport_rest( $base, $rest_type, $post_type, $template ) {
	if ( ! $base || ! wp_http_validate_url( $base ) ) {
		return array( new WP_Error( 'bad_url', __( 'Enter a valid site URL.', 'ricoman' ) ) );
	}
	$base = untrailingslashit( $base );
	$ua   = array( 'timeout' => 30, 'user-agent' => 'RicomanTransporter/1.0' );

	// Site origin (scheme://host[:port]) — candidates are built from the ROOT, so a
	// pasted deep page URL (e.g. /back-end/projects/x) still resolves correctly.
	$pp     = wp_parse_url( $base );
	$origin = ( isset( $pp['scheme'] ) ? $pp['scheme'] : 'https' ) . '://' . ( isset( $pp['host'] ) ? $pp['host'] : '' ) . ( isset( $pp['port'] ) ? ':' . $pp['port'] : '' );

	// Build candidate REST roots — the source may be headless or live in a
	// sub-folder (e.g. ricoman.com/back-end), so try discovery + common paths.
	$roots = array();
	if ( false !== strpos( $base, 'wp-json' ) ) {
		$roots[] = preg_replace( '#/wp-json.*$#', '/wp-json', $base );
	}
	$home = wp_remote_get( $origin . '/', array( 'timeout' => 15, 'user-agent' => 'RicomanTransporter/1.0' ) );
	if ( ! is_wp_error( $home ) ) {
		$link = wp_remote_retrieve_header( $home, 'link' );
		$link = is_array( $link ) ? implode( ',', $link ) : (string) $link;
		if ( preg_match( '#<([^>]+)>;\s*rel="https://api\.w\.org/"#', $link, $mm ) ) {
			$roots[] = untrailingslashit( $mm[1] );
		}
	}
	// Whatever path was pasted, plus origin-root variants.
	$roots[] = $base . '/wp-json';
	$roots[] = $origin . '/wp-json';
	$roots[] = $origin . '/back-end/wp-json';
	$roots[] = $origin . '/blog/wp-json';
	$roots   = array_values( array_unique( $roots ) );

	$items = null;
	$tried = array();
	$fetch = function ( $endpoint ) use ( $ua, &$tried ) {
		$tried[] = $endpoint;
		$resp    = wp_remote_get( $endpoint, $ua );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}
		$decoded = json_decode( wp_remote_retrieve_body( $resp ), true );
		// A valid list of posts is a numerically-indexed array (not an error object).
		return ( is_array( $decoded ) && ! isset( $decoded['code'] ) ) ? $decoded : null;
	};
	foreach ( $roots as $root ) {
		$items = $fetch( $root . '/wp/v2/' . rawurlencode( $rest_type ) . '?per_page=50&_embed=1' );
		if ( null !== $items ) {
			break;
		}
	}
	// Fallback: ?rest_route= style (works even without pretty permalinks).
	if ( null === $items ) {
		foreach ( array_unique( array( $base, $origin, $origin . '/back-end' ) ) as $rr ) {
			$items = $fetch( $rr . '/?rest_route=' . rawurlencode( '/wp/v2/' . $rest_type ) . '&per_page=50&_embed=1' );
			if ( null !== $items ) {
				break;
			}
		}
	}
	if ( null === $items ) {
		return array( new WP_Error( 'json', sprintf( /* translators: %s: endpoints tried */ __( 'Could not read a REST response. If WordPress is in a sub-folder, enter that exact URL (e.g. https://ricoman.com/back-end). Tried: %s', 'ricoman' ), implode( ' · ', array_slice( $tried, 0, 5 ) ) ) ) );
	}

	$results = array();
	foreach ( $items as $it ) {
		$title    = isset( $it['title']['rendered'] ) ? $it['title']['rendered'] : '';
		$content  = isset( $it['content']['rendered'] ) ? $it['content']['rendered'] : '';
		$excerpt  = isset( $it['excerpt']['rendered'] ) ? wp_strip_all_tags( $it['excerpt']['rendered'] ) : '';
		$featured = '';
		if ( ! empty( $it['_embedded']['wp:featuredmedia'][0]['source_url'] ) ) {
			$featured = $it['_embedded']['wp:featuredmedia'][0]['source_url'];
		}
		$results[] = ricoman_transport_create( array(
			'post_type'  => $post_type,
			'title'      => $title,
			'html'       => $content,
			'excerpt'    => $excerpt,
			'featured'   => $featured,
			'template'   => $template,
			'source_url' => isset( $it['link'] ) ? $it['link'] : $base,
		) );
	}
	if ( ! $results ) {
		return array( new WP_Error( 'none', __( 'No items returned from that REST endpoint.', 'ricoman' ) ) );
	}
	return $results;
}

/* ---------------------------------------------------------------------------
 * Shared: create a draft from stored HTML
 * ------------------------------------------------------------------------- */
function ricoman_transport_create( $args ) {
	$post_type = post_type_exists( $args['post_type'] ) ? $args['post_type'] : 'page';
	$title     = wp_strip_all_tags( (string) $args['title'] );
	if ( '' === $title ) {
		$title = __( 'Imported item', 'ricoman' );
	}

	$post_id = wp_insert_post( array(
		'post_type'   => $post_type,
		'post_status' => 'draft',
		'post_title'  => $title,
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	// Parse stored HTML.
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?><body>' . $args['html'] . '</body>' );
	libxml_clear_errors();
	$body = $dom->getElementsByTagName( 'body' );
	$root = $body->length ? $body->item( 0 ) : $dom->documentElement;

	$imgcount = 0;
	$orphan   = array();
	$blocks   = ricoman_transport_blocks( $root, $args['source_url'], $post_id, $imgcount, $orphan );

	if ( ! empty( $args['featured'] ) ) {
		$att = ricoman_transport_sideload( ricoman_transport_abs( $args['featured'], $args['source_url'] ), $post_id );
		if ( $att ) {
			set_post_thumbnail( $post_id, $att );
			$imgcount++;
		}
	}

	wp_update_post( array( 'ID' => $post_id, 'post_content' => $blocks ) );
	if ( ! empty( $args['excerpt'] ) ) {
		update_post_meta( $post_id, '_ricoman_seo_desc', wp_strip_all_tags( $args['excerpt'] ) );
	}
	if ( ! empty( $args['template'] ) ) {
		update_post_meta( $post_id, '_wp_page_template', $args['template'] );
	}
	if ( ! empty( $args['source_url'] ) ) {
		update_post_meta( $post_id, '_ricoman_source_url', $args['source_url'] );
	}
	if ( $orphan ) {
		update_post_meta( $post_id, '_ricoman_orphan_html', wp_kses_post( implode( "\n", $orphan ) ) );
	}

	return array(
		'id'     => $post_id,
		'title'  => $title,
		'images' => $imgcount,
		'orphan' => ! empty( $orphan ),
	);
}

/* ---------------------------------------------------------------------------
 * HTML → blocks (shared); leftovers collected in $orphan
 * ------------------------------------------------------------------------- */
function ricoman_transport_blocks( $node, $base, $post_id, &$imgcount, &$orphan, $depth = 0 ) {
	$out = '';
	if ( ! $node || ! $node->hasChildNodes() || $depth > 6 ) {
		return $out;
	}
	foreach ( $node->childNodes as $child ) {
		if ( XML_TEXT_NODE === $child->nodeType ) {
			$t = trim( $child->textContent );
			if ( '' !== $t ) {
				$out .= '<!-- wp:paragraph --><p>' . esc_html( $t ) . '</p><!-- /wp:paragraph -->' . "\n";
			}
			continue;
		}
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}
		$tag = strtolower( $child->nodeName );

		if ( in_array( $tag, array( 'script', 'style', 'nav', 'header', 'footer', 'form', 'aside', 'noscript', 'svg', 'link', 'meta' ), true ) ) {
			continue;
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = max( 2, (int) substr( $tag, 1 ) );
				$txt   = trim( $child->textContent );
				if ( '' !== $txt ) {
					$out .= '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . esc_html( $txt ) . '</h' . $level . '><!-- /wp:heading -->' . "\n";
				}
				break;

			case 'p':
				$inner = ricoman_transport_inner( $child );
				if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) {
					$out .= '<!-- wp:paragraph --><p>' . $inner . '</p><!-- /wp:paragraph -->' . "\n";
				}
				break;

			case 'ul':
			case 'ol':
				$items = '';
				foreach ( $child->getElementsByTagName( 'li' ) as $li ) {
					$items .= '<li>' . ricoman_transport_inner( $li ) . '</li>';
				}
				if ( $items ) {
					$wraptag = ( 'ol' === $tag ) ? 'ol' : 'ul';
					$attr    = ( 'ol' === $tag ) ? ' {"ordered":true}' : '';
					$out    .= '<!-- wp:list' . $attr . ' --><' . $wraptag . ' class="wp-block-list">' . $items . '</' . $wraptag . '><!-- /wp:list -->' . "\n";
				}
				break;

			case 'blockquote':
				$q = trim( $child->textContent );
				if ( '' !== $q ) {
					$out .= '<!-- wp:quote --><blockquote class="wp-block-quote"><p>' . esc_html( $q ) . '</p></blockquote><!-- /wp:quote -->' . "\n";
				}
				break;

			case 'img':
			case 'figure':
				$img = ( 'img' === $tag ) ? $child : ( $child->getElementsByTagName( 'img' )->length ? $child->getElementsByTagName( 'img' )->item( 0 ) : null );
				if ( $img && $imgcount < 40 ) {
					$src = $img->getAttribute( 'src' );
					$alt = $img->getAttribute( 'alt' );
					if ( $src ) {
						$att = ricoman_transport_sideload( ricoman_transport_abs( $src, $base ), $post_id );
						if ( $att ) {
							$imgcount++;
							$u    = wp_get_attachment_image_url( $att, 'large' );
							$out .= '<!-- wp:image {"id":' . $att . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . esc_url( $u ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $att . '"/></figure><!-- /wp:image -->' . "\n";
						}
					}
				}
				break;

			case 'div':
			case 'section':
			case 'main':
			case 'article':
			case 'span':
				$out .= ricoman_transport_blocks( $child, $base, $post_id, $imgcount, $orphan, $depth + 1 );
				break;

			default:
				$frag = trim( ricoman_transport_outer( $child ) );
				if ( '' !== $frag && strlen( wp_strip_all_tags( $frag ) ) > 1 ) {
					$orphan[] = $frag;
				}
				break;
		}
	}
	return $out;
}

/** Inner HTML of a node, limited to safe inline markup. */
function ricoman_transport_inner( $node ) {
	$html = '';
	foreach ( $node->childNodes as $c ) {
		$html .= $node->ownerDocument->saveHTML( $c );
	}
	return wp_kses( $html, array(
		'a'      => array( 'href' => array(), 'title' => array() ),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'br'     => array(),
	) );
}

/** Outer HTML of a node (for the orphan box). */
function ricoman_transport_outer( $node ) {
	return $node->ownerDocument->saveHTML( $node );
}

/** Resolve a possibly-relative URL against a base. */
function ricoman_transport_abs( $src, $base ) {
	if ( preg_match( '#^https?://#i', $src ) ) {
		return $src;
	}
	if ( 0 === strpos( $src, '//' ) ) {
		return ( 0 === strpos( (string) $base, 'https' ) ? 'https:' : 'http:' ) . $src;
	}
	$parts = wp_parse_url( (string) $base );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $src;
	}
	$root = $parts['scheme'] . '://' . $parts['host'];
	if ( '' === $src ) {
		return '';
	}
	if ( 0 === strpos( $src, '/' ) ) {
		return $root . $src;
	}
	$path = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $parts['path'] ) : '/';
	return $root . $path . $src;
}

/** Download a remote image into the Media Library; return attachment ID or 0. */
function ricoman_transport_sideload( $url, $post_id ) {
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return 0;
	}
	if ( ! function_exists( 'media_handle_sideload' ) ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
	}
	$tmp = download_url( $url, 25 );
	if ( is_wp_error( $tmp ) ) {
		return 0;
	}
	$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	if ( ! $name || ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
		$name = 'imported-image.jpg';
	}
	$file = array( 'name' => $name, 'tmp_name' => $tmp );
	$id   = media_handle_sideload( $file, $post_id );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return 0;
	}
	return (int) $id;
}

/* ---- "Unsorted imported content" box on the edit screen ---- */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'page', 'product', 'project', 'post' ) as $pt ) {
		add_meta_box( 'ricoman_orphan', __( 'Unsorted imported content', 'ricoman' ), 'ricoman_orphan_metabox', $pt, 'normal', 'default' );
	}
} );

function ricoman_orphan_metabox( $post ) {
	$html = (string) get_post_meta( $post->ID, '_ricoman_orphan_html', true );
	$src  = (string) get_post_meta( $post->ID, '_ricoman_source_url', true );
	if ( '' === $html ) {
		echo '<p style="color:#646970;margin:0">' . esc_html__( 'Nothing here. The Content Transporter puts any content it can’t place automatically into this box, so you can drop it onto the page where it belongs.', 'ricoman' ) . '</p>';
		return;
	}
	if ( $src ) {
		echo '<p class="description">' . esc_html__( 'Imported from:', 'ricoman' ) . ' <a href="' . esc_url( $src ) . '" target="_blank" rel="noopener">' . esc_html( $src ) . '</a></p>';
	}
	echo '<p>' . esc_html__( 'Copy what you need into the editor, then tick “done” to clear this box on save.', 'ricoman' ) . '</p>';
	echo '<textarea readonly class="large-text code" rows="10" onclick="this.select()">' . esc_textarea( $html ) . '</textarea>';
	wp_nonce_field( 'ricoman_orphan_save', 'ricoman_orphan_nonce' );
	echo '<p><label><input type="checkbox" name="ricoman_orphan_clear" value="1"> ' . esc_html__( 'Done — clear this unsorted content on save', 'ricoman' ) . '</label></p>';
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['ricoman_orphan_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_orphan_nonce'] ), 'ricoman_orphan_save' ) ) {
		return;
	}
	if ( ! empty( $_POST['ricoman_orphan_clear'] ) && current_user_can( 'edit_post', $post_id ) ) {
		delete_post_meta( $post_id, '_ricoman_orphan_html' );
	}
} );

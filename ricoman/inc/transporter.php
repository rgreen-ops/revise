<?php
/**
 * Content Transporter.
 *
 * Migrate existing pages into the new theme one at a time:
 *  - Paste one or more source URLs and pick the target post type + template.
 *  - The transporter fetches each page, pulls the title, meta description, hero
 *    image and main content, sideloads every image into the Media Library, and
 *    creates a draft using the chosen template.
 *  - Content that maps cleanly becomes blocks (headings, paragraphs, lists,
 *    images, quotes). Anything it can't confidently place is dropped into an
 *    "Unsorted imported content" box on the edit screen, ready to paste wherever
 *    it fits on the page.
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
	if ( isset( $_POST['ricoman_transport'] ) && check_admin_referer( 'ricoman_transport' ) ) {
		$urls      = isset( $_POST['urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['urls'] ) ) : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : 'page';
		$template  = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

		$list = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $urls ) ) );
		foreach ( array_slice( $list, 0, 20 ) as $url ) {
			$results[] = ricoman_transport_import( esc_url_raw( $url ), $post_type, $template );
		}
	}

	$pt_default = 'page';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Content Transporter', 'ricoman' ); ?></h1>
		<p class="description"><?php esc_html_e( 'Bring existing pages into the new theme. Paste a URL (or several, one per line), choose where it should land and which template to use. Images come across automatically; anything that doesn’t fit a standard field is saved to an “Unsorted content” box on the draft.', 'ricoman' ); ?></p>

		<?php if ( ! class_exists( 'DOMDocument' ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The PHP DOM extension is required for the transporter and isn’t available on this server.', 'ricoman' ); ?></p></div>
		<?php endif; ?>

		<?php foreach ( $results as $r ) : ?>
			<?php if ( is_wp_error( $r ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $r->get_error_message() ); ?></p></div>
			<?php else : ?>
				<div class="notice notice-success"><p>
					<?php
					printf(
						/* translators: 1: page title, 2: image count, 3: edit link, 4: orphan note */
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

		<form method="post">
			<?php wp_nonce_field( 'ricoman_transport' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="rt_urls"><?php esc_html_e( 'Source URL(s)', 'ricoman' ); ?></label></th>
					<td><textarea id="rt_urls" name="urls" rows="5" class="large-text code" placeholder="https://old-site.com/about&#10;https://old-site.com/manufacturing"></textarea>
						<p class="description"><?php esc_html_e( 'One URL per line (up to 20). Each becomes its own draft.', 'ricoman' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><label for="rt_pt"><?php esc_html_e( 'Create as', 'ricoman' ); ?></label></th>
					<td>
						<select id="rt_pt" name="post_type" onchange="ricomanRtTemplates(this.value)">
							<option value="page"><?php esc_html_e( 'Page', 'ricoman' ); ?></option>
							<option value="product"><?php esc_html_e( 'Product', 'ricoman' ); ?></option>
							<option value="project"><?php esc_html_e( 'Project', 'ricoman' ); ?></option>
						</select>
						<select id="rt_tpl" name="template"></select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Transport as draft', 'ricoman' ), 'primary', 'ricoman_transport' ); ?>
		</form>

		<script>
		var RICOMAN_RT = <?php echo wp_json_encode( array(
			'page'    => ricoman_transporter_templates( 'page' ),
			'product' => ricoman_transporter_templates( 'product' ),
			'project' => ricoman_transporter_templates( 'project' ),
		) ); ?>;
		function ricomanRtTemplates( pt ) {
			var sel = document.getElementById( 'rt_tpl' );
			sel.innerHTML = '';
			var opts = RICOMAN_RT[ pt ] || {};
			Object.keys( opts ).forEach( function ( slug ) {
				var o = document.createElement( 'option' );
				o.value = slug; o.textContent = opts[ slug ];
				sel.appendChild( o );
			} );
		}
		ricomanRtTemplates( '<?php echo esc_js( $pt_default ); ?>' );
		</script>
	</div>
	<?php
}

/**
 * Import a single URL into a draft.
 *
 * @param string $url       Source URL.
 * @param string $post_type Target post type.
 * @param string $template  Template slug ('' for default).
 * @return array|WP_Error
 */
function ricoman_transport_import( $url, $post_type, $template ) {
	if ( ! class_exists( 'DOMDocument' ) ) {
		return new WP_Error( 'no_dom', __( 'PHP DOM extension is unavailable.', 'ricoman' ) );
	}
	if ( ! $url || ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'bad_url', sprintf( /* translators: %s: url */ __( 'Skipped invalid URL: %s', 'ricoman' ), $url ) );
	}

	$resp = wp_remote_get( $url, array(
		'timeout'    => 25,
		'user-agent' => 'RicomanTransporter/1.0',
	) );
	if ( is_wp_error( $resp ) ) {
		return new WP_Error( 'fetch', sprintf( /* translators: 1: url 2: error */ __( 'Could not fetch %1$s — %2$s', 'ricoman' ), $url, $resp->get_error_message() ) );
	}
	$html = wp_remote_retrieve_body( $resp );
	if ( '' === trim( (string) $html ) ) {
		return new WP_Error( 'empty', sprintf( /* translators: %s: url */ __( 'No HTML returned from %s', 'ricoman' ), $url ) );
	}

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html );
	libxml_clear_errors();
	$xpath = new DOMXPath( $dom );

	// Title.
	$title = ricoman_transport_meta( $xpath, 'og:title' );
	if ( ! $title ) {
		$h1 = $xpath->query( '//h1' );
		$title = ( $h1->length ) ? trim( $h1->item( 0 )->textContent ) : '';
	}
	if ( ! $title ) {
		$t = $dom->getElementsByTagName( 'title' );
		$title = $t->length ? trim( $t->item( 0 )->textContent ) : __( 'Imported page', 'ricoman' );
	}

	// Description + hero.
	$desc = ricoman_transport_meta( $xpath, 'og:description' );
	if ( ! $desc ) {
		$md = $xpath->query( '//meta[@name="description"]/@content' );
		$desc = $md->length ? trim( $md->item( 0 )->nodeValue ) : '';
	}
	$hero = ricoman_transport_meta( $xpath, 'og:image' );

	// Main content container: most paragraph-dense of main/article/body.
	$container = ricoman_transport_main( $xpath, $dom );

	// Create the draft first so sideloaded images attach to it.
	$post_id = wp_insert_post( array(
		'post_type'   => post_type_exists( $post_type ) ? $post_type : 'page',
		'post_status' => 'draft',
		'post_title'  => wp_strip_all_tags( $title ),
	), true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$img_count = 0;
	$orphan    = array();
	$blocks    = ricoman_transport_blocks( $container, $url, $post_id, $img_count, $orphan );

	// Featured image from the hero.
	if ( $hero ) {
		$hero_abs = ricoman_transport_abs( $hero, $url );
		$att      = ricoman_transport_sideload( $hero_abs, $post_id );
		if ( $att ) {
			set_post_thumbnail( $post_id, $att );
			$img_count++;
		}
	}

	// Update content + meta.
	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $blocks,
	) );
	if ( $desc ) {
		update_post_meta( $post_id, '_ricoman_seo_desc', wp_strip_all_tags( $desc ) );
	}
	if ( $template ) {
		update_post_meta( $post_id, '_wp_page_template', $template );
	}
	update_post_meta( $post_id, '_ricoman_source_url', $url );
	if ( $orphan ) {
		update_post_meta( $post_id, '_ricoman_orphan_html', wp_kses_post( implode( "\n", $orphan ) ) );
	}

	return array(
		'id'     => $post_id,
		'title'  => wp_strip_all_tags( $title ),
		'images' => $img_count,
		'orphan' => ! empty( $orphan ),
	);
}

/**
 * Read an og:/twitter: meta value.
 */
function ricoman_transport_meta( $xpath, $property ) {
	$q = $xpath->query( '//meta[@property="' . $property . '"]/@content | //meta[@name="' . $property . '"]/@content' );
	return $q->length ? trim( $q->item( 0 )->nodeValue ) : '';
}

/**
 * Find the most content-dense container.
 *
 * @return DOMElement
 */
function ricoman_transport_main( $xpath, $dom ) {
	$candidates = $xpath->query( '//main | //article | //*[@role="main"]' );
	$best       = null;
	$best_len   = 0;
	foreach ( $candidates as $c ) {
		$len = strlen( trim( $c->textContent ) );
		if ( $len > $best_len ) {
			$best_len = $len;
			$best     = $c;
		}
	}
	if ( $best ) {
		return $best;
	}
	$body = $dom->getElementsByTagName( 'body' );
	return $body->length ? $body->item( 0 ) : $dom->documentElement;
}

/**
 * Walk a container into Gutenberg block markup; collect leftovers in $orphan.
 *
 * @param DOMNode $node     Container.
 * @param string  $base     Base URL for resolving links/images.
 * @param int     $post_id  Draft post ID.
 * @param int     $imgcount By-ref image counter.
 * @param array   $orphan   By-ref leftover HTML.
 * @param int     $depth    Recursion guard.
 * @return string
 */
function ricoman_transport_blocks( $node, $base, $post_id, &$imgcount, &$orphan, $depth = 0 ) {
	$out = '';
	if ( ! $node || ! $node->hasChildNodes() || $depth > 6 ) {
		return $out;
	}
	foreach ( $node->childNodes as $child ) {
		if ( XML_TEXT_NODE === $child->nodeType ) {
			$t = trim( $child->textContent );
			if ( '' !== $t ) {
				$out .= "<!-- wp:paragraph --><p>" . esc_html( $t ) . "</p><!-- /wp:paragraph -->\n";
			}
			continue;
		}
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}
		$tag = strtolower( $child->nodeName );

		if ( in_array( $tag, array( 'script', 'style', 'nav', 'header', 'footer', 'form', 'aside', 'noscript', 'svg' ), true ) ) {
			continue;
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$level = (int) substr( $tag, 1 );
				$level = max( 2, $level ); // never re-emit H1 inside content.
				$txt   = trim( $child->textContent );
				if ( '' !== $txt ) {
					$out .= '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading">' . esc_html( $txt ) . '</h' . $level . '><!-- /wp:heading -->' . "\n";
				}
				break;

			case 'p':
				$inner = ricoman_transport_inner( $child );
				if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) {
					$out .= "<!-- wp:paragraph --><p>" . $inner . "</p><!-- /wp:paragraph -->\n";
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
							$u   = wp_get_attachment_image_url( $att, 'large' );
							$out .= '<!-- wp:image {"id":' . $att . ',"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="' . esc_url( $u ) . '" alt="' . esc_attr( $alt ) . '" class="wp-image-' . $att . '"/></figure><!-- /wp:image -->' . "\n";
						}
					}
				}
				break;

			case 'div':
			case 'section':
			case 'main':
			case 'article':
								// Recurse into structural wrappers.
				$out .= ricoman_transport_blocks( $child, $base, $post_id, $imgcount, $orphan, $depth + 1 );
				break;

			default:
				// Anything else we can't confidently place → orphan box.
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
		'span'   => array(),
	) );
}

/** Outer HTML of a node (for the orphan box). */
function ricoman_transport_outer( $node ) {
	return $node->ownerDocument->saveHTML( $node );
}

/** Resolve a possibly-relative URL against the source page URL. */
function ricoman_transport_abs( $src, $base ) {
	if ( preg_match( '#^https?://#i', $src ) ) {
		return $src;
	}
	if ( 0 === strpos( $src, '//' ) ) {
		return ( 0 === strpos( $base, 'https' ) ? 'https:' : 'http:' ) . $src;
	}
	$parts = wp_parse_url( $base );
	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return $src;
	}
	$root = $parts['scheme'] . '://' . $parts['host'];
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
	$name = basename( wp_parse_url( $url, PHP_URL_PATH ) );
	if ( ! $name || ! preg_match( '/\.(jpe?g|png|gif|webp|avif)$/i', $name ) ) {
		$name = 'imported-image.jpg';
	}
	$file = array( 'name' => $name, 'tmp_name' => $tmp );
	$id   = media_handle_sideload( $file, $post_id );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return 0;
	}
	return (int) $id;
}

/* ---- "Unsorted imported content" box on the edit screen ---- */
add_action( 'add_meta_boxes', function () {
	foreach ( array( 'page', 'product', 'project', 'post' ) as $pt ) {
		add_meta_box(
			'ricoman_orphan',
			__( 'Unsorted imported content', 'ricoman' ),
			'ricoman_orphan_metabox',
			$pt,
			'normal',
			'default'
		);
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

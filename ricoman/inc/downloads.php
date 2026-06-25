<?php
/**
 * Downloads & Resources page
 *
 * Manual files (catalogues, brochures, education guides, 3D models, Revit, BIM)
 * are managed via Ricoman → Downloads in the admin. Each entry has:
 *   title | type | file URL | optional thumbnail URL
 *
 * Product-specific files (datasheets, installation instructions, LDT) are
 * pulled live from product ACF fields when a product is chosen in the dropdown.
 *
 * Front-end rendered via [ricoman_downloads] shortcode.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------------------------ types */

function ricoman_dl_types() {
	return array(
		'catalogue'     => 'Catalogue',
		'brochure'      => 'Product Literature',
		'education'     => 'Education Guides',
		'3d'            => '3D Models',
		'revit'         => 'Revit Files',
		'bim'           => 'BIM Files',
		'installation'  => 'Installation Instructions',
		'ldt'           => 'LDT Files',
		'datasheet'     => 'Datasheet',
	);
}

/* ------------------------------------------------------------------ admin */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-admin',
		'Downloads',
		'Downloads',
		'edit_posts',
		'ricoman-downloads',
		'ricoman_downloads_admin_page'
	);
} );

function ricoman_downloads_admin_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}

	// Save.
	if ( isset( $_POST['rm_dl_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rm_dl_nonce'] ), 'rm_dl_save' ) ) {
		$entries = array();
		$titles  = isset( $_POST['rm_dl_title'] ) ? (array) $_POST['rm_dl_title'] : array();
		$types   = isset( $_POST['rm_dl_type'] )  ? (array) $_POST['rm_dl_type']  : array();
		$urls    = isset( $_POST['rm_dl_url'] )   ? (array) $_POST['rm_dl_url']   : array();
		$thumbs  = isset( $_POST['rm_dl_thumb'] ) ? (array) $_POST['rm_dl_thumb'] : array();
		foreach ( $titles as $i => $title ) {
			$title = sanitize_text_field( $title );
			$url   = esc_url_raw( $urls[ $i ] ?? '' );
			if ( '' === $title || '' === $url ) {
				continue;
			}
			$entries[] = array(
				'title' => $title,
				'type'  => sanitize_key( $types[ $i ] ?? 'catalogue' ),
				'url'   => $url,
				'thumb' => esc_url_raw( $thumbs[ $i ] ?? '' ),
			);
		}
		update_option( 'ricoman_downloads', $entries );
		echo '<div class="notice notice-success is-dismissible"><p>Downloads saved.</p></div>';
	}

	$entries = get_option( 'ricoman_downloads', array() );
	$types   = ricoman_dl_types();
	// Only manual types shown in admin (not datasheet/installation/ldt — those come from products).
	$manual_types = array( 'catalogue', 'brochure', 'education', '3d', 'revit', 'bim' );
	?>
	<div class="wrap">
		<h1>Downloads &amp; Resources</h1>
		<p>Manage manually uploaded files (Catalogues, Brochures, Education Guides, 3D Models, Revit &amp; BIM). Product-specific files (Datasheets, Installation Instructions, LDT) are pulled automatically from each product page.</p>
		<form method="post">
			<?php wp_nonce_field( 'rm_dl_save', 'rm_dl_nonce' ); ?>
			<table class="widefat rm-dl-admin-table" id="rm-dl-rows">
				<thead>
					<tr>
						<th>Title</th>
						<th>Type</th>
						<th>File URL</th>
						<th>Thumbnail URL <small>(optional)</small></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $i => $e ) : ?>
					<tr>
						<td><input type="text" name="rm_dl_title[]" value="<?php echo esc_attr( $e['title'] ); ?>" class="regular-text" required></td>
						<td>
							<select name="rm_dl_type[]">
								<?php foreach ( $manual_types as $k ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $e['type'], $k ); ?>><?php echo esc_html( $types[ $k ] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="url" name="rm_dl_url[]" value="<?php echo esc_attr( $e['url'] ); ?>" class="regular-text" required></td>
						<td><input type="url" name="rm_dl_thumb[]" value="<?php echo esc_attr( $e['thumb'] ?? '' ); ?>" class="regular-text"></td>
						<td><button type="button" class="button rm-dl-remove">Remove</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="rm-dl-add">+ Add file</button>
				<?php submit_button( 'Save Downloads', 'primary', 'submit', false ); ?>
			</p>
		</form>
	</div>

	<script>
	(function(){
		var types = <?php echo wp_json_encode( array_map( null, $manual_types, array_map( function($k) use ($types){ return $types[$k]; }, $manual_types ) ) ); ?>;
		var typeOpts = <?php
			$opts = '';
			foreach ( $manual_types as $k ) {
				$opts .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $types[ $k ] ) . '<\/option>';
			}
			echo wp_json_encode( $opts );
		?>;

		document.getElementById('rm-dl-add').addEventListener('click', function(){
			var tr = document.createElement('tr');
			tr.innerHTML = '<td><input type="text" name="rm_dl_title[]" class="regular-text" required></td>'
				+ '<td><select name="rm_dl_type[]">' + typeOpts + '</select></td>'
				+ '<td><input type="url" name="rm_dl_url[]" class="regular-text" required></td>'
				+ '<td><input type="url" name="rm_dl_thumb[]" class="regular-text"></td>'
				+ '<td><button type="button" class="button rm-dl-remove">Remove</button></td>';
			document.querySelector('#rm-dl-rows tbody').appendChild(tr);
		});

		document.addEventListener('click', function(e){
			if ( e.target.classList.contains('rm-dl-remove') ) {
				e.target.closest('tr').remove();
			}
		});
	})();
	</script>
	<?php
}

/* ------------------------------------------------------------------ shortcode / front-end */

add_shortcode( 'ricoman_downloads', 'ricoman_downloads_shortcode' );

function ricoman_downloads_shortcode() {
	$manual  = get_option( 'ricoman_downloads', array() );
	$types   = ricoman_dl_types();
	$products = ricoman_dl_get_products();

	ob_start();
	?>
	<div class="rm-dl-page" id="rm-dl-page">

		<aside class="rm-dl-filters" aria-label="Filter downloads">

			<div class="rm-dl-filter-group">
				<h3 class="rm-dl-filter-heading">Type</h3>
				<ul class="rm-dl-type-list">
					<?php foreach ( $types as $key => $label ) : ?>
					<li>
						<label class="rm-dl-type-label">
							<input type="checkbox" class="rm-dl-type-cb" value="<?php echo esc_attr( $key ); ?>"
								<?php echo in_array( $key, array( 'catalogue', 'brochure' ), true ) ? 'checked' : ''; ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					</li>
					<?php endforeach; ?>
				</ul>
			</div>

			<div class="rm-dl-filter-group rm-dl-filter-product" id="rm-dl-product-group" style="display:none">
				<h3 class="rm-dl-filter-heading">Product</h3>
				<select class="rm-dl-product-select" id="rm-dl-product-select">
					<option value="">— All products —</option>
					<?php foreach ( $products as $pid => $pname ) : ?>
					<option value="<?php echo esc_attr( $pid ); ?>"><?php echo esc_html( $pname ); ?></option>
					<?php endforeach; ?>
					<option value="accessories">Accessories</option>
				</select>
			</div>

		</aside>

		<div class="rm-dl-results" id="rm-dl-results">
			<?php echo ricoman_dl_render_manual( $manual, array( 'catalogue', 'brochure' ) ); ?>
		</div>

	</div>

	<script>
	window.rmDlManual = <?php echo wp_json_encode( $manual ); ?>;
	window.rmDlAjax  = <?php echo wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'rm_dl_product' ) ) ); ?>;
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Return all non-accessory products + a grouped accessories entry.
 */
function ricoman_dl_get_products() {
	$ctax = taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat';
	$args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'meta_query'     => array(
			array(
				'key'     => 'is_accessories_product',
				'compare' => 'NOT EXISTS',
			),
		),
	);
	// Exclude accessory products (flagged via meta).
	$q = new WP_Query( $args );
	$out = array();
	foreach ( $q->posts as $p ) {
		// Skip if flagged as accessory.
		$flag = get_post_meta( $p->ID, 'is_accessories_product', true );
		if ( $flag && 'no' !== strtolower( (string) $flag ) ) {
			continue;
		}
		$out[ $p->ID ] = $p->post_title;
	}
	return $out;
}

/**
 * Render a grid of manual download cards filtered by type keys.
 */
function ricoman_dl_render_manual( $entries, $type_filter = array() ) {
	$filtered = array_filter( $entries, function( $e ) use ( $type_filter ) {
		return empty( $type_filter ) || in_array( $e['type'], $type_filter, true );
	} );

	if ( empty( $filtered ) ) {
		return '<p class="rm-dl-empty">No files found for the selected filters.</p>';
	}

	$html = '<div class="rm-dl-grid">';
	foreach ( $filtered as $e ) {
		$ext   = strtoupper( pathinfo( wp_parse_url( $e['url'], PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		$thumb = ! empty( $e['thumb'] ) ? $e['thumb'] : '';
		$html .= '<a class="rm-dl-card" href="' . esc_url( $e['url'] ) . '" download rel="noopener" data-type="' . esc_attr( $e['type'] ) . '">';
		if ( $thumb ) {
			$html .= '<div class="rm-dl-card-thumb"><img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $e['title'] ) . '" loading="lazy"></div>';
		} else {
			$html .= '<div class="rm-dl-card-thumb rm-dl-card-thumb--icon"><span class="rm-dl-ext">' . esc_html( $ext ?: 'FILE' ) . '</span></div>';
		}
		$html .= '<div class="rm-dl-card-body">';
		$html .= '<span class="rm-dl-card-title">' . esc_html( $e['title'] ) . '</span>';
		$html .= '<span class="rm-dl-card-meta">' . esc_html( ricoman_dl_types()[ $e['type'] ] ?? $e['type'] ) . ( $ext ? ' · ' . $ext : '' ) . '</span>';
		$html .= '</div>';
		$html .= '<span class="rm-dl-card-dl">↓ Download</span>';
		$html .= '</a>';
	}
	$html .= '</div>';
	return $html;
}

/* ------------------------------------------------------------------ AJAX: product files */

add_action( 'wp_ajax_rm_dl_product',        'ricoman_dl_ajax_product' );
add_action( 'wp_ajax_nopriv_rm_dl_product', 'ricoman_dl_ajax_product' );

function ricoman_dl_ajax_product() {
	check_ajax_referer( 'rm_dl_product', 'nonce' );

	$types      = isset( $_POST['types'] )   ? array_map( 'sanitize_key', (array) $_POST['types'] ) : array();
	$product_id = isset( $_POST['product'] ) ? sanitize_text_field( $_POST['product'] ) : '';

	// Product-specific types.
	$product_types = array( 'datasheet', 'installation', 'ldt' );

	// If only manual types selected, return nothing from product AJAX (handled client-side).
	$needs_product = array_intersect( $types, $product_types );

	if ( empty( $needs_product ) ) {
		wp_send_json_success( array( 'html' => '' ) );
	}

	// Query products.
	if ( 'accessories' === $product_id ) {
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array( 'key' => 'is_accessories_product', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
			),
		);
	} elseif ( $product_id ) {
		$query_args = array(
			'post_type'  => 'product',
			'post_status'=> 'publish',
			'p'          => (int) $product_id,
		);
	} else {
		// All products.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
	}

	$q    = new WP_Query( $query_args );
	$html = '<div class="rm-dl-grid">';
	$found = false;

	foreach ( $q->posts as $p ) {
		$pid = $p->ID;

		if ( in_array( 'datasheet', $types, true ) ) {
			$ds = ricoman_pf_fileurl( ricoman_pf_get( $pid, 'download_family_datasheet' ) );
			if ( $ds ) {
				$html  .= ricoman_dl_product_card( $p->post_title . ' — Datasheet', 'datasheet', $ds, $pid );
				$found  = true;
			}
		}

		if ( in_array( 'ldt', $types, true ) ) {
			// LDT lives on variant-product posts linked to the parent.
			$variants = get_posts( array(
				'post_type'      => 'variant-product',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array( 'key' => 'parent_product', 'value' => $pid, 'compare' => '=' ),
					array( 'key' => 'download_led', 'compare' => 'EXISTS' ),
				),
			) );
			foreach ( $variants as $v ) {
				$ldt = ricoman_pf_fileurl( ricoman_pf_get( $v->ID, 'download_led' ) );
				if ( $ldt ) {
					$html .= ricoman_dl_product_card( $p->post_title . ' — LDT File', 'ldt', $ldt, $pid );
					$found = true;
					break;
				}
			}
		}

		if ( in_array( 'installation', $types, true ) ) {
			// Installation lives in the download_section repeater.
			$dls = ricoman_pf_get( $pid, 'download_section', array() );
			if ( is_array( $dls ) ) {
				foreach ( $dls as $row ) {
					if ( ! is_array( $row ) ) continue;
					$dtitle = '';
					$dfile  = null;
					foreach ( $row as $k => $v ) {
						if ( false !== strpos( strtolower( (string) $k ), 'title' ) ) $dtitle = $v;
						elseif ( false !== strpos( strtolower( (string) $k ), 'file' ) ) $dfile = $v;
					}
					if ( $dfile && false !== stripos( (string) $dtitle, 'install' ) ) {
						$url = ricoman_pf_fileurl( $dfile );
						if ( $url ) {
							$html .= ricoman_dl_product_card( $p->post_title . ' — ' . ( $dtitle ?: 'Installation Instructions' ), 'installation', $url, $pid );
							$found = true;
						}
					}
				}
			}
		}
	}

	if ( ! $found ) {
		$html .= '<p class="rm-dl-empty" style="grid-column:1/-1">No files found for the selected filters.</p>';
	}

	$html .= '</div>';
	wp_send_json_success( array( 'html' => $html ) );
}

function ricoman_dl_product_card( $title, $type, $url, $pid ) {
	$ext   = strtoupper( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$thumb = get_the_post_thumbnail_url( $pid, 'thumbnail' );
	$types = ricoman_dl_types();
	$html  = '<a class="rm-dl-card" href="' . esc_url( $url ) . '" download rel="noopener" data-type="' . esc_attr( $type ) . '">';
	if ( $thumb ) {
		$html .= '<div class="rm-dl-card-thumb"><img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $title ) . '" loading="lazy"></div>';
	} else {
		$html .= '<div class="rm-dl-card-thumb rm-dl-card-thumb--icon"><span class="rm-dl-ext">' . esc_html( $ext ?: 'FILE' ) . '</span></div>';
	}
	$html .= '<div class="rm-dl-card-body">';
	$html .= '<span class="rm-dl-card-title">' . esc_html( $title ) . '</span>';
	$html .= '<span class="rm-dl-card-meta">' . esc_html( $types[ $type ] ?? $type ) . ( $ext ? ' · ' . $ext : '' ) . '</span>';
	$html .= '</div>';
	$html .= '<span class="rm-dl-card-dl">↓ Download</span>';
	$html .= '</a>';
	return $html;
}

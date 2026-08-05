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

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'ricoman_page_ricoman-downloads' === $hook ) {
		wp_enqueue_media();
	}
} );

add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
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

		update_option( 'ricoman_dl_cta', array(
			'heading'    => sanitize_text_field( isset( $_POST['rm_dl_cta_heading'] )    ? $_POST['rm_dl_cta_heading']    : '' ),
			'sub'        => sanitize_text_field( isset( $_POST['rm_dl_cta_sub'] )        ? $_POST['rm_dl_cta_sub']        : '' ),
			'btn1_label' => sanitize_text_field( isset( $_POST['rm_dl_cta_btn1_label'] ) ? $_POST['rm_dl_cta_btn1_label'] : '' ),
			'btn1_url'   => esc_url_raw(         isset( $_POST['rm_dl_cta_btn1_url'] )   ? $_POST['rm_dl_cta_btn1_url']   : '' ),
			'btn2_label' => sanitize_text_field( isset( $_POST['rm_dl_cta_btn2_label'] ) ? $_POST['rm_dl_cta_btn2_label'] : '' ),
			'btn2_url'   => esc_url_raw(         isset( $_POST['rm_dl_cta_btn2_url'] )   ? $_POST['rm_dl_cta_btn2_url']   : '' ),
		) );

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
						<th>File</th>
						<th>Thumbnail <small>(optional)</small></th>
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
						<td class="rm-dl-file-cell">
							<input type="url" name="rm_dl_url[]" value="<?php echo esc_attr( $e['url'] ); ?>" class="regular-text rm-dl-url-input" required>
							<button type="button" class="button rm-dl-pick" data-target="url">&#128206; Upload / Choose</button>
						</td>
						<td class="rm-dl-file-cell">
							<input type="url" name="rm_dl_thumb[]" value="<?php echo esc_attr( $e['thumb'] ?? '' ); ?>" class="regular-text rm-dl-thumb-input">
							<button type="button" class="button rm-dl-pick" data-target="thumb">&#128247; Upload / Choose</button>
						</td>
						<td><button type="button" class="button rm-dl-remove">Remove</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="rm-dl-add">+ Add file</button>
			</p>

			<hr style="margin:30px 0">
			<h2>Bottom CTA banner</h2>
			<p>Edit the "Can't find a document?" banner shown at the bottom of the downloads page.</p>
			<?php
			$cta = get_option( 'ricoman_dl_cta', array() );
			$cta_h  = isset( $cta['heading'] )    ? $cta['heading']    : "Can't find a document?";
			$cta_s  = isset( $cta['sub'] )        ? $cta['sub']        : 'Tell us the product or project and our team will send the exact files you need.';
			$cta_b1l = isset( $cta['btn1_label'] ) ? $cta['btn1_label'] : 'Request files';
			$cta_b1u = isset( $cta['btn1_url'] )   ? $cta['btn1_url']   : '/contact/';
			$cta_b2l = isset( $cta['btn2_label'] ) ? $cta['btn2_label'] : 'Talk to the team';
			$cta_b2u = isset( $cta['btn2_url'] )   ? $cta['btn2_url']   : '/about/';
			?>
			<table class="form-table">
				<tr>
					<th>Heading</th>
					<td><input type="text" name="rm_dl_cta_heading" value="<?php echo esc_attr( $cta_h ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th>Subtext</th>
					<td><input type="text" name="rm_dl_cta_sub" value="<?php echo esc_attr( $cta_s ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th>Button 1 label</th>
					<td><input type="text" name="rm_dl_cta_btn1_label" value="<?php echo esc_attr( $cta_b1l ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th>Button 1 URL</th>
					<td><input type="text" name="rm_dl_cta_btn1_url" value="<?php echo esc_attr( $cta_b1u ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th>Button 2 label</th>
					<td><input type="text" name="rm_dl_cta_btn2_label" value="<?php echo esc_attr( $cta_b2l ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th>Button 2 URL</th>
					<td><input type="text" name="rm_dl_cta_btn2_url" value="<?php echo esc_attr( $cta_b2u ); ?>" class="regular-text"></td>
				</tr>
			</table>
			<?php submit_button( 'Save all', 'primary' ); ?>
		</form>
	</div>

	<script>
	(function(){
		var types = <?php echo wp_json_encode( array_map( null, $manual_types, array_map( function($k) use ($types){ return $types[$k]; }, $manual_types ) ) ); ?>;
		var typeOpts = <?php
			$opts_arr = array();
			foreach ( $manual_types as $k ) {
				$opts_arr[] = array( 'value' => $k, 'label' => $types[ $k ] );
			}
			echo wp_json_encode( $opts_arr );
		?>.map(function(o){ return '<option value="'+o.value+'">'+o.label+'</option>'; }).join('');

		document.getElementById('rm-dl-add').addEventListener('click', function(){
			var tr = document.createElement('tr');
			tr.innerHTML = '<td><input type="text" name="rm_dl_title[]" class="regular-text" required></td>'
				+ '<td><select name="rm_dl_type[]">' + typeOpts + '</select></td>'
				+ '<td class="rm-dl-file-cell"><input type="url" name="rm_dl_url[]" class="regular-text rm-dl-url-input" required><button type="button" class="button rm-dl-pick" data-target="url">&#128206; Upload / Choose</button></td>'
				+ '<td class="rm-dl-file-cell"><input type="url" name="rm_dl_thumb[]" class="regular-text rm-dl-thumb-input"><button type="button" class="button rm-dl-pick" data-target="thumb">&#128247; Upload / Choose</button></td>'
				+ '<td><button type="button" class="button rm-dl-remove">Remove</button></td>';
			document.querySelector('#rm-dl-rows tbody').appendChild(tr);
		});

		document.addEventListener('click', function(e){
			if ( e.target.classList.contains('rm-dl-remove') ) {
				e.target.closest('tr').remove();
			}
		});

		// WP media picker.
		document.addEventListener('click', function(e){
			if ( ! e.target.classList.contains('rm-dl-pick') ) return;
			var btn    = e.target;
			var row    = btn.closest('tr');
			var target = btn.getAttribute('data-target');
			var input  = target === 'thumb' ? row.querySelector('.rm-dl-thumb-input') : row.querySelector('.rm-dl-url-input');
			var isImg  = target === 'thumb';
			var frame  = wp.media({
				title:    isImg ? 'Choose thumbnail image' : 'Choose file',
				button:   { text: 'Use this file' },
				multiple: false,
				library:  isImg ? { type: 'image' } : {},
			});
			frame.on('select', function(){
				var att = frame.state().get('selection').first().toJSON();
				input.value = att.url;
			});
			frame.open();
		});
	})();
	</script>
	<style>
	.rm-dl-file-cell{display:flex;flex-direction:column;gap:4px}
	.rm-dl-file-cell input{margin-bottom:0!important}
	</style>
	<?php
}

/* ------------------------------------------------------------------ shortcode / front-end */

// Force the downloads page to use the full-width page-downloads block template.
add_filter( 'block_template_hierarchy', function( $hierarchy ) {
	if ( is_page( 'downloads' ) ) {
		array_unshift( $hierarchy, 'page-downloads' );
	}
	return $hierarchy;
} );

add_shortcode( 'ricoman_downloads', 'ricoman_downloads_shortcode' );

function ricoman_downloads_shortcode() {
	$manual  = get_option( 'ricoman_downloads', array() );
	$types   = ricoman_dl_types();
	$products = ricoman_dl_get_products();

	ob_start();
	?>
	<div class="rm-dl-wrap alignfull">

	<div class="rm-dl-page" id="rm-dl-page">

		<aside class="rm-dl-filters" aria-label="Filter downloads">

			<div class="rm-dl-filter-search-wrap">
				<input type="search" id="rm-dl-search" class="rm-dl-search" placeholder="Search&hellip;" autocomplete="off" aria-label="Search downloads">
			</div>

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

			<div class="rm-dl-filter-group rm-dl-filter-product" id="rm-dl-product-group">
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

	</div><!-- .rm-dl-wrap -->


	<script>
	window.rmDlManual = <?php echo wp_json_encode( $manual ); ?>;
	window.rmDlAjax  = <?php echo wp_json_encode( array( 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'rm_dl_product' ) ) ); ?>;
	</script>
	<?php
	return ob_get_clean();
}

/**
 * Return an SVG icon for a given download type.
 */
function ricoman_dl_type_icon( $type ) {
	$icons = array(
		'catalogue' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="6" width="36" height="46" rx="3" stroke="currentColor" stroke-width="2.5"/><rect x="18" y="6" width="28" height="46" rx="3" fill="white" stroke="currentColor" stroke-width="2.5"/><line x1="24" y1="20" x2="40" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="24" y1="27" x2="40" y2="27" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="24" y1="34" x2="34" y2="34" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',

		'brochure' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="8" width="44" height="48" rx="3" stroke="currentColor" stroke-width="2.5"/><rect x="10" y="8" width="44" height="20" rx="3" fill="currentColor" fill-opacity=".1" stroke="currentColor" stroke-width="2.5"/><line x1="18" y1="36" x2="46" y2="36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="18" y1="43" x2="46" y2="43" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="18" y1="50" x2="34" y2="50" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',

		'education' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M32 10L56 22L32 34L8 22L32 10Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><path d="M20 28V42C20 42 24 48 32 48C40 48 44 42 44 42V28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="56" y1="22" x2="56" y2="36" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',

		'3d' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M32 8L54 20V44L32 56L10 44V20L32 8Z" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"/><line x1="32" y1="8" x2="32" y2="56" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="10" y1="20" x2="54" y2="20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="10" y1="44" x2="54" y2="44" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="10" y1="20" x2="32" y2="32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="54" y1="20" x2="32" y2="32" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',

		'revit' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="10" y="8" width="44" height="48" rx="3" stroke="currentColor" stroke-width="2.5"/><path d="M20 20H32C35.3 20 38 22.7 38 26C38 29.3 35.3 32 32 32H20V20Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><line x1="20" y1="32" x2="38" y2="44" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="20" y1="26" x2="44" y2="26" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',

		'bim' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="24" width="48" height="28" rx="3" stroke="currentColor" stroke-width="2.5"/><path d="M16 24V16C16 14.9 16.9 14 18 14H32L48 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="24" y1="34" x2="24" y2="42" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="32" y1="34" x2="32" y2="42" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="34" x2="40" y2="42" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/><line x1="20" y1="38" x2="44" y2="38" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',

		'installation' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="6" width="40" height="52" rx="3" stroke="currentColor" stroke-width="2.5"/><line x1="20" y1="18" x2="44" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><line x1="20" y1="26" x2="44" y2="26" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="22" cy="36" r="2" fill="currentColor"/><line x1="28" y1="36" x2="44" y2="36" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="22" cy="44" r="2" fill="currentColor"/><line x1="28" y1="44" x2="44" y2="44" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',

		'ldt' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="32" cy="28" r="14" stroke="currentColor" stroke-width="2.5"/><path d="M32 14C32 14 24 20 24 28C24 36 32 42 32 42C32 42 40 36 40 28C40 20 32 14 32 14Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M18 28H46" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M20 48L32 42L44 48" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><line x1="32" y1="48" x2="32" y2="56" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/></svg>',

		'datasheet' => '<svg viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="6" width="40" height="52" rx="3" stroke="currentColor" stroke-width="2.5"/><line x1="20" y1="18" x2="44" y2="18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><rect x="20" y="24" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="34" y="24" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="20" y="36" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.8"/><rect x="34" y="36" width="10" height="8" rx="1" stroke="currentColor" stroke-width="1.8"/></svg>',
	);
	$svg = isset( $icons[ $type ] ) ? $icons[ $type ] : $icons['datasheet'];
	return '<div class="rm-dl-card-thumb rm-dl-card-thumb--icon"><span class="rm-dl-type-icon">' . $svg . '</span></div>';
}

/**
 * Return all non-accessory products + a grouped accessories entry.
 */
function ricoman_dl_get_products() {
	$q   = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		// Skip accessories — flagged via the is_accessories_product meta OR in the
		// "Accessories" product-cat term. They're reachable via the grouped
		// "Accessories" entry, not listed individually. Uses the same detection as
		// the archive + product pages, so term-tagged accessories (mounting kits,
		// clamps, diffusers, suspension sets…) no longer leak into the list.
		if ( function_exists( 'ricoman_pf_is_accessory' ) && ricoman_pf_is_accessory( $p->ID ) ) {
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
		$use_image = in_array( $e['type'], array( 'catalogue', 'brochure', 'education' ), true );
		$html .= '<a class="rm-dl-card" href="' . esc_url( $e['url'] ) . '" download rel="noopener" data-type="' . esc_attr( $e['type'] ) . '">';
		if ( $use_image && $thumb ) {
			$html .= '<div class="rm-dl-card-thumb"><img src="' . esc_url( $thumb ) . '" alt="' . esc_attr( $e['title'] ) . '" loading="lazy"></div>';
		} else {
			$html .= ricoman_dl_type_icon( $e['type'] );
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
		// All accessories (meta flag OR "Accessories" term) — the loop filters each
		// product via ricoman_pf_is_accessory() so term-tagged ones are included.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
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
		// Accessories view: only show accessory products (meta OR "Accessories" term).
		if ( 'accessories' === $product_id && function_exists( 'ricoman_pf_is_accessory' ) && ! ricoman_pf_is_accessory( $pid ) ) {
			continue;
		}

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

		// download_section repeater — per-product files (datasheet / instructions /
		// LDT / IES): exactly what the product page lists. Classify each row by its
		// title + file extension and show it under the matching type. Previously
		// only "install*" rows were read here, so datasheets and LDT/IES files
		// stored in the repeater (e.g. Mosaic's) never appeared on the Downloads page.
		$dls = ricoman_pf_get( $pid, 'download_section', array() );
		if ( is_array( $dls ) ) {
			foreach ( $dls as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$dtitle = '';
				$dfile  = null;
				foreach ( $row as $k => $v ) {
					if ( false !== strpos( strtolower( (string) $k ), 'title' ) ) {
						$dtitle = $v;
					} elseif ( false !== strpos( strtolower( (string) $k ), 'file' ) ) {
						$dfile = $v;
					}
				}
				$url = $dfile ? ricoman_pf_fileurl( $dfile ) : '';
				if ( ! $url ) {
					continue;
				}
				$dtype = ricoman_dl_classify( $dtitle, $url );
				if ( ! in_array( $dtype, $types, true ) ) {
					continue;
				}
				$html .= ricoman_dl_product_card( $p->post_title . ' — ' . ( $dtitle ? $dtitle : ucfirst( $dtype ) ), $dtype, $url, $pid );
				$found = true;
			}
		}
	}

	if ( ! $found ) {
		$html .= '<p class="rm-dl-empty" style="grid-column:1/-1">No files found for the selected filters.</p>';
	}

	$html .= '</div>';
	wp_send_json_success( array( 'html' => $html ) );
}

/**
 * Classify a download_section row into a Downloads-page type (datasheet /
 * installation / ldt) from its file extension + title, so per-product files
 * surface under the right filter — mirroring what the product page shows.
 * Defaults to datasheet (the common per-product doc). Note a "…LDT files.zip"
 * is caught by the title even though the extension is .zip.
 */
function ricoman_dl_classify( $title, $url ) {
	$ext = strtolower( (string) pathinfo( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$t   = strtolower( (string) $title );
	if ( in_array( $ext, array( 'ldt', 'ies' ), true ) || preg_match( '/\bldt\b|\bies\b|photometr/', $t ) ) {
		return 'ldt';
	}
	if ( preg_match( '/instal|instruct|fitting|guide|manual/', $t ) ) {
		return 'installation';
	}
	return 'datasheet';
}

function ricoman_dl_product_card( $title, $type, $url, $pid ) {
	$ext   = strtoupper( pathinfo( wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	$types = ricoman_dl_types();
	$html  = '<a class="rm-dl-card" href="' . esc_url( $url ) . '" download rel="noopener" data-type="' . esc_attr( $type ) . '">';
	$html .= ricoman_dl_type_icon( $type );
	$html .= '<div class="rm-dl-card-body">';
	$html .= '<span class="rm-dl-card-title">' . esc_html( $title ) . '</span>';
	$html .= '<span class="rm-dl-card-meta">' . esc_html( $types[ $type ] ?? $type ) . ( $ext ? ' · ' . $ext : '' ) . '</span>';
	$html .= '</div>';
	$html .= '<span class="rm-dl-card-dl">↓ Download</span>';
	$html .= '</a>';
	return $html;
}

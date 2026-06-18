<?php
/**
 * Product data & variant structure.
 *
 * Registers typed product meta (specs) plus a simple variant table, exposed in
 * the REST API so they can be read by the datasheet, schema and front-end
 * templates. A lightweight meta box keeps editing usable in any editor.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The product specification fields.
 *
 * @return array<string,string> Map of meta key => human label.
 */
function ricoman_product_spec_fields() {
	return array(
		'_ricoman_sku'        => __( 'SKU / Order code', 'ricoman' ),
		'_ricoman_wattage'    => __( 'Wattage (W)', 'ricoman' ),
		'_ricoman_lumens'     => __( 'Output (lm)', 'ricoman' ),
		'_ricoman_efficacy'   => __( 'Efficacy (lm/W)', 'ricoman' ),
		'_ricoman_cct'        => __( 'Colour temperature (CCT)', 'ricoman' ),
		'_ricoman_cri'        => __( 'CRI', 'ricoman' ),
		'_ricoman_beam'       => __( 'Beam angle', 'ricoman' ),
		'_ricoman_ip'         => __( 'IP rating', 'ricoman' ),
		'_ricoman_dimensions' => __( 'Dimensions', 'ricoman' ),
		'_ricoman_warranty'   => __( 'Warranty', 'ricoman' ),
	);
}

/**
 * Register product meta with the REST API.
 */
function ricoman_register_product_meta() {
	foreach ( array_keys( ricoman_product_spec_fields() ) as $key ) {
		register_post_meta(
			'product',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	// Variants stored as a newline-delimited table; parsed on read.
	register_post_meta(
		'product',
		'_ricoman_variants',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'ricoman_sanitize_variants',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	// Extra product-only content fields (Product Builder).
	$extra = array(
		'_ricoman_tagline'     => 'sanitize_text_field',
		'_ricoman_lead'        => 'sanitize_text_field',
		'_ricoman_features'    => 'sanitize_textarea_field',
		'_ricoman_finishes'    => 'sanitize_textarea_field',
		'_ricoman_datasheet'   => 'esc_url_raw',
		'_ricoman_extra_specs' => 'sanitize_textarea_field',
		'_ricoman_downloads'   => 'sanitize_textarea_field',
	);
	foreach ( $extra as $key => $sanitize ) {
		register_post_meta(
			'product',
			$key,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize,
				'auth_callback'     => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}
}
add_action( 'init', 'ricoman_register_product_meta' );

/**
 * Sanitize the variants textarea (one variant per line).
 *
 * @param string $value Raw textarea value.
 * @return string
 */
function ricoman_sanitize_variants( $value ) {
	$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
	$clean = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$clean[] = implode( ' | ', array_map( 'sanitize_text_field', array_map( 'trim', explode( '|', $line ) ) ) );
		}
	}
	return implode( "\n", $clean );
}

/**
 * Parse "Label | Value" lines into [ [label, value], ... ].
 *
 * @param string $raw Newline-delimited pairs.
 * @return array<int,array{0:string,1:string}>
 */
function ricoman_parse_pairs( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( '' === $parts[0] ) {
			continue;
		}
		$out[] = array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
	}
	return $out;
}

/** Load the WordPress media library on the product editor (for Downloads). */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'product' === get_post_type() ) {
		wp_enqueue_media();
	}
} );

/**
 * Parse the variants meta into structured rows.
 *
 * Each line: SKU | Description | Wattage | Lumens | CCT
 *
 * @param int $post_id Product ID.
 * @return array<int,array<string,string>>
 */
function ricoman_get_variants( $post_id ) {
	$raw = (string) get_post_meta( $post_id, '_ricoman_variants', true );
	if ( '' === $raw ) {
		return array();
	}
	$cols = array( 'sku', 'description', 'wattage', 'lumens', 'cct' );
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = array_map( 'trim', explode( '|', $line ) );
		$row   = array();
		foreach ( $cols as $i => $col ) {
			$row[ $col ] = isset( $parts[ $i ] ) ? $parts[ $i ] : '';
		}
		$rows[] = $row;
	}
	return $rows;
}

/* -------------------------------------------------------------------------
 * Editing UI: a simple meta box (works in classic and block editors).
 * ---------------------------------------------------------------------- */

/**
 * Add the product details meta box.
 */
function ricoman_add_product_metabox() {
	add_meta_box(
		'ricoman_product_details',
		'💡 ' . __( 'Product Builder', 'ricoman' ),
		'ricoman_render_product_metabox',
		'product',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes', 'ricoman_add_product_metabox' );

/**
 * Render the product meta box.
 *
 * @param WP_Post $post Current product.
 */
function ricoman_render_product_metabox( $post ) {
	wp_nonce_field( 'ricoman_save_product', 'ricoman_product_nonce' );
	$m = function ( $k ) use ( $post ) { return (string) get_post_meta( $post->ID, $k, true ); };
	$ready = function_exists( 'ricoman_ricobot_ready' ) && ricoman_ricobot_ready();
	?>
	<style>
		.rmpb{--b:#e3e3e1}
		.rmpb h3{margin:22px 0 10px;font-size:12px;text-transform:uppercase;letter-spacing:.12em;color:#777}
		.rmpb h3:first-child{margin-top:4px}
		.rmpb .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
		.rmpb label{display:block;font-weight:600;margin-bottom:4px;font-size:13px}
		.rmpb input,.rmpb textarea{width:100%}
		.rmpb .full{margin-top:12px}
		.rmpb .hint{color:#777;font-size:12px;margin:4px 0 0}
		.rmpb .sync{display:flex;gap:10px;align-items:center;background:#f6f7f9;border:1px solid var(--b);border-radius:8px;padding:12px;margin:4px 0 6px}
		.rmpb .sync .dashicons{color:#1d4ed8}
		#rmpb-sync-msg{font-size:12px}
		.rmpb-row{display:flex;gap:8px;align-items:center;margin-bottom:8px}
		.rmpb-row input{flex:1}
		.rmpb-del{color:#b32d2e;text-decoration:none;font-size:14px;cursor:pointer}
	</style>
	<div class="rmpb">

		<div class="sync">
			<span class="dashicons dashicons-rest-api"></span>
			<button type="button" class="button" id="rmpb-sync" <?php disabled( ! $ready ); ?>><?php esc_html_e( 'Sync specs from RICOBOT', 'ricoman' ); ?></button>
			<span id="rmpb-sync-msg" class="hint"><?php echo $ready ? esc_html__( 'Fills the spec fields below from the Order code.', 'ricoman' ) : esc_html__( 'Connect RICOBOT (Settings → RICOBOT) to enable.', 'ricoman' ); ?></span>
		</div>

		<h3><?php esc_html_e( 'Overview', 'ricoman' ); ?></h3>
		<div class="full"><label for="_ricoman_tagline"><?php esc_html_e( 'Tagline (one line under the title)', 'ricoman' ); ?></label><input type="text" id="_ricoman_tagline" name="_ricoman_tagline" value="<?php echo esc_attr( $m( '_ricoman_tagline' ) ); ?>" placeholder="Seamless curves of light, made to order"></div>
		<div class="full"><label for="_ricoman_lead"><?php esc_html_e( 'Availability / lead-time line', 'ricoman' ); ?></label><input type="text" id="_ricoman_lead" name="_ricoman_lead" value="<?php echo esc_attr( $m( '_ricoman_lead' ) ); ?>" placeholder="Made to order · ~6 day UK lead"></div>

		<h3><?php esc_html_e( 'Specifications', 'ricoman' ); ?></h3>
		<div class="grid">
		<?php
		foreach ( ricoman_product_spec_fields() as $key => $label ) {
			printf(
				'<div><label for="%1$s">%2$s</label><input type="text" id="%1$s" name="%1$s" value="%3$s" /></div>',
				esc_attr( $key ),
				esc_html( $label ),
				esc_attr( $m( $key ) )
			);
		}
		?>
		</div>

		<h3><?php esc_html_e( 'Custom spec rows', 'ricoman' ); ?></h3>
		<div class="rmpb-rep" id="rmpb-xspec">
		<?php
		$xspecs = ricoman_parse_pairs( $m( '_ricoman_extra_specs' ) );
		if ( ! $xspecs ) {
			$xspecs = array( array( '', '' ) );
		}
		foreach ( $xspecs as $r ) {
			echo '<div class="rmpb-row"><input type="text" name="rmpb_xspec_label[]" placeholder="Label (e.g. Driver)" value="' . esc_attr( $r[0] ) . '"><input type="text" name="rmpb_xspec_value[]" placeholder="Value (e.g. DALI dimmable)" value="' . esc_attr( $r[1] ) . '"><button type="button" class="button-link rmpb-del" title="Remove">✕</button></div>';
		}
		?>
		</div>
		<p><button type="button" class="button" id="rmpb-xspec-add">＋ <?php esc_html_e( 'Add spec row', 'ricoman' ); ?></button> <span class="hint"><?php esc_html_e( 'Extra rows appended to the spec table.', 'ricoman' ); ?></span></p>

		<h3><?php esc_html_e( 'Key features', 'ricoman' ); ?></h3>
		<div class="full"><textarea id="_ricoman_features" name="_ricoman_features" rows="4" placeholder="One feature per line&#10;Dot-free continuous run&#10;Bends to any radius&#10;Made to your exact length"><?php echo esc_textarea( $m( '_ricoman_features' ) ); ?></textarea><p class="hint"><?php esc_html_e( 'One per line — shown as a bullet list on the product page.', 'ricoman' ); ?></p></div>

		<h3><?php esc_html_e( 'Finishes', 'ricoman' ); ?></h3>
		<div class="full"><textarea id="_ricoman_finishes" name="_ricoman_finishes" rows="2" placeholder="Matt white, Matt black, Brushed brass, Anodised silver"><?php echo esc_textarea( $m( '_ricoman_finishes' ) ); ?></textarea><p class="hint"><?php esc_html_e( 'Comma or line separated — shown as finish chips.', 'ricoman' ); ?></p></div>

		<h3><?php esc_html_e( 'Variants', 'ricoman' ); ?></h3>
		<div class="full"><label for="_ricoman_variants"><?php esc_html_e( 'One per line: SKU | Description | Wattage | Lumens | CCT', 'ricoman' ); ?></label><textarea id="_ricoman_variants" name="_ricoman_variants" rows="5" placeholder="RM-DL-08 | 8W fixed downlight | 8W | 800lm | 3000/4000/6000K"><?php echo esc_textarea( $m( '_ricoman_variants' ) ); ?></textarea></div>

		<div class="full"><label for="_ricoman_datasheet"><?php esc_html_e( 'Datasheet URL (optional — overrides the auto PDF)', 'ricoman' ); ?></label><input type="url" id="_ricoman_datasheet" name="_ricoman_datasheet" value="<?php echo esc_attr( $m( '_ricoman_datasheet' ) ); ?>" placeholder="https://ricobot.ricoman.com/api/public/products/CODE/datasheet.pdf"></div>

		<h3><?php esc_html_e( 'Downloads', 'ricoman' ); ?></h3>
		<div class="rmpb-rep" id="rmpb-dl">
		<?php
		$downloads = ricoman_parse_pairs( $m( '_ricoman_downloads' ) );
		if ( ! $downloads ) {
			$downloads = array( array( '', '' ) );
		}
		foreach ( $downloads as $r ) {
			echo '<div class="rmpb-row"><input type="text" name="rmpb_dl_title[]" placeholder="Title (e.g. Installation guide)" value="' . esc_attr( $r[0] ) . '"><input type="text" class="rmpb-dl-url" name="rmpb_dl_url[]" placeholder="File URL" value="' . esc_attr( $r[1] ) . '"><button type="button" class="button rmpb-dl-pick">' . esc_html__( 'Choose file', 'ricoman' ) . '</button><button type="button" class="button-link rmpb-del" title="Remove">✕</button></div>';
		}
		?>
		</div>
		<p><button type="button" class="button" id="rmpb-dl-add">＋ <?php esc_html_e( 'Add download', 'ricoman' ); ?></button> <span class="hint"><?php esc_html_e( 'Datasheets, IES, BIM, guides, certificates — pick from the Media Library.', 'ricoman' ); ?></span></p>
	</div>
	<script>
	( function () {
		var btn = document.getElementById( 'rmpb-sync' );
		if ( ! btn ) { return; }
		btn.addEventListener( 'click', function () {
			var sku = ( document.getElementById( '_ricoman_sku' ) || {} ).value || '';
			var msg = document.getElementById( 'rmpb-sync-msg' );
			if ( ! sku ) { msg.textContent = 'Enter an Order code / SKU first.'; return; }
			msg.textContent = 'Fetching from RICOBOT…';
			var body = new FormData();
			body.append( 'action', 'ricoman_ricobot_sync' );
			body.append( 'nonce', '<?php echo esc_js( wp_create_nonce( 'ricoman_rb_sync' ) ); ?>' );
			body.append( 'sku', sku );
			fetch( ajaxurl, { method: 'POST', body: body } ).then( function ( r ) { return r.json(); } ).then( function ( res ) {
				if ( ! res.success ) { msg.textContent = '⚠ ' + ( res.data || 'Could not fetch.' ); return; }
				var count = 0;
				Object.keys( res.data ).forEach( function ( k ) {
					var el = document.getElementById( k );
					if ( el && res.data[ k ] ) { el.value = res.data[ k ]; count++; }
				} );
				msg.textContent = '✓ Filled ' + count + ' fields from RICOBOT. Remember to Update.';
			} ).catch( function () { msg.textContent = '⚠ Request failed.'; } );
		} );
	} )();

	/* Repeaters: add/remove rows + Media Library picker for downloads. */
	( function () {
		function addRow( repId, html ) {
			var rep = document.getElementById( repId );
			if ( ! rep ) { return; }
			var div = document.createElement( 'div' );
			div.className = 'rmpb-row';
			div.innerHTML = html;
			rep.appendChild( div );
		}
		var xa = document.getElementById( 'rmpb-xspec-add' );
		if ( xa ) {
			xa.addEventListener( 'click', function () {
				addRow( 'rmpb-xspec', '<input type="text" name="rmpb_xspec_label[]" placeholder="Label"><input type="text" name="rmpb_xspec_value[]" placeholder="Value"><button type="button" class="button-link rmpb-del" title="Remove">✕</button>' );
			} );
		}
		var da = document.getElementById( 'rmpb-dl-add' );
		if ( da ) {
			da.addEventListener( 'click', function () {
				addRow( 'rmpb-dl', '<input type="text" name="rmpb_dl_title[]" placeholder="Title"><input type="text" class="rmpb-dl-url" name="rmpb_dl_url[]" placeholder="File URL"><button type="button" class="button rmpb-dl-pick">Choose file</button><button type="button" class="button-link rmpb-del" title="Remove">✕</button>' );
			} );
		}
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'rmpb-del' ) ) {
				e.preventDefault();
				var row = e.target.closest( '.rmpb-row' );
				if ( row ) { row.remove(); }
			}
			if ( e.target.classList.contains( 'rmpb-dl-pick' ) && window.wp && wp.media ) {
				e.preventDefault();
				var row = e.target.closest( '.rmpb-row' );
				var frame = wp.media( { title: 'Select download', multiple: false } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					row.querySelector( '.rmpb-dl-url' ).value = a.url;
					var t = row.querySelector( 'input[name="rmpb_dl_title[]"]' );
					if ( t && ! t.value ) { t.value = a.title || a.filename || 'Download'; }
				} );
				frame.open();
			}
		} );
	} )();
	</script>
	<?php
}

/**
 * Save the product meta box values.
 *
 * @param int $post_id Product ID.
 */
function ricoman_save_product_meta( $post_id ) {
	if ( ! isset( $_POST['ricoman_product_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_product_nonce'] ), 'ricoman_save_product' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( array_keys( ricoman_product_spec_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
	if ( isset( $_POST['_ricoman_variants'] ) ) {
		update_post_meta( $post_id, '_ricoman_variants', ricoman_sanitize_variants( wp_unslash( $_POST['_ricoman_variants'] ) ) );
	}
	$text = array( '_ricoman_tagline' => 'sanitize_text_field', '_ricoman_lead' => 'sanitize_text_field', '_ricoman_features' => 'sanitize_textarea_field', '_ricoman_finishes' => 'sanitize_textarea_field', '_ricoman_datasheet' => 'esc_url_raw' );
	foreach ( $text as $key => $fn ) {
		if ( isset( $_POST[ $key ] ) ) {
			update_post_meta( $post_id, $key, call_user_func( $fn, wp_unslash( $_POST[ $key ] ) ) );
		}
	}

	// Custom spec rows -> "Label | Value" lines.
	if ( isset( $_POST['rmpb_xspec_label'] ) ) {
		$labels = (array) wp_unslash( $_POST['rmpb_xspec_label'] );
		$values = isset( $_POST['rmpb_xspec_value'] ) ? (array) wp_unslash( $_POST['rmpb_xspec_value'] ) : array();
		$rows   = array();
		foreach ( $labels as $i => $lab ) {
			$lab = sanitize_text_field( $lab );
			$val = isset( $values[ $i ] ) ? sanitize_text_field( $values[ $i ] ) : '';
			if ( '' !== $lab ) {
				$rows[] = $lab . ' | ' . $val;
			}
		}
		update_post_meta( $post_id, '_ricoman_extra_specs', implode( "\n", $rows ) );
	}

	// Downloads -> "Title | URL" lines.
	if ( isset( $_POST['rmpb_dl_url'] ) ) {
		$titles = isset( $_POST['rmpb_dl_title'] ) ? (array) wp_unslash( $_POST['rmpb_dl_title'] ) : array();
		$urls   = (array) wp_unslash( $_POST['rmpb_dl_url'] );
		$rows   = array();
		foreach ( $urls as $i => $url ) {
			$url   = esc_url_raw( $url );
			$title = isset( $titles[ $i ] ) ? sanitize_text_field( $titles[ $i ] ) : '';
			if ( '' !== $url ) {
				$rows[] = ( '' !== $title ? $title : 'Download' ) . ' | ' . $url;
			}
		}
		update_post_meta( $post_id, '_ricoman_downloads', implode( "\n", $rows ) );
	}
}
add_action( 'save_post_product', 'ricoman_save_product_meta' );

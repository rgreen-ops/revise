<?php
/**
 * Variant CSV import / export — the staging replacement for the old
 * "Import/Export Variable Product" tool.
 *
 * Each order-code row is a `variant-product` post linked to its parent product
 * by the `parent_product` meta. This screen lets an admin bulk-edit those rows
 * in a spreadsheet: export every variant (optionally just one product's) to CSV,
 * edit in Excel/Sheets, and import it back to create or update the rows.
 *
 * Found under: Variant Products → Import / Export (back end).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical CSV columns, in order. `id` and `parent` are structural; the rest
 * map 1:1 to variant-product meta keys (and match the datasheet spec fields).
 */
function ricoman_variant_csv_columns() {
	return array(
		'id',            // post ID — leave blank to create a new variant.
		'parent',        // parent product: slug or numeric ID.
		'title',         // post title.
		'part_code',
		'order_code',
		'product_sort_description',
		'lumens',
		'dimensions',
		'efficacy',
		'cri',
		'beam_angle',
		'ip_rating',
		'ik_rating',
		'ugr',
		'colour_finish',
		'operating_temperatures',
		'voltage_range',
		'power_factor',
		'l70_b50',
		'optics',
		'leds',
		'construction_material',
		'diffuser_type',
		'unit_weight',
		'warranty',
		'certifications',
		'download_led',        // LDT file URL.
		'product_main_image',  // image URL.
		'product_diagram',     // dimension diagram URL.
	);
}

/** Meta columns only (everything except the structural id/parent/title). */
function ricoman_variant_csv_meta_keys() {
	$cols = ricoman_variant_csv_columns();
	return array_values( array_diff( $cols, array( 'id', 'parent', 'title' ) ) );
}

/** Resolve a stored image/file meta value to a URL for export. */
function ricoman_variant_csv_url( $v ) {
	if ( is_numeric( $v ) ) {
		$u = wp_get_attachment_url( (int) $v );
		return $u ? $u : '';
	}
	if ( is_array( $v ) ) {
		return isset( $v['url'] ) ? (string) $v['url'] : '';
	}
	return (string) $v;
}

/* ------------------------------------------------------------------ admin UI */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=variant-product',
		__( 'Import / Export Variants', 'ricoman' ),
		__( 'Import / Export', 'ricoman' ),
		'edit_posts',
		'ricoman-variant-csv',
		'ricoman_variant_csv_page'
	);
} );

function ricoman_variant_csv_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );
	$notice = isset( $_GET['rm_csv'] ) ? sanitize_text_field( wp_unslash( $_GET['rm_csv'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Variant Products — Import / Export', 'ricoman' ); ?></h1>
		<?php if ( $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Bulk-edit the order-code rows (lumens, dimensions, LDT files, datasheets) in a spreadsheet. Export to CSV, edit, then import to create or update rows.', 'ricoman' ); ?></p>

		<div class="card" style="max-width:760px;padding:8px 20px 18px">
			<h2><?php esc_html_e( 'Export', 'ricoman' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ricoman_variant_export">
				<?php wp_nonce_field( 'ricoman_variant_export' ); ?>
				<p>
					<label for="rm-exp-parent"><?php esc_html_e( 'Product:', 'ricoman' ); ?></label>
					<select name="parent" id="rm-exp-parent">
						<option value=""><?php esc_html_e( 'All products', 'ricoman' ); ?></option>
						<?php foreach ( $products as $prod_id ) : ?>
							<option value="<?php echo esc_attr( $prod_id ); ?>"><?php echo esc_html( get_the_title( $prod_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Download CSV', 'ricoman' ); ?></button>
					<button type="submit" class="button" name="template" value="1"><?php esc_html_e( 'Download blank template', 'ricoman' ); ?></button>
				</p>
			</form>
		</div>

		<div class="card" style="max-width:760px;padding:8px 20px 18px;margin-top:18px">
			<h2><?php esc_html_e( 'Import', 'ricoman' ); ?></h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ricoman_variant_import">
				<?php wp_nonce_field( 'ricoman_variant_import' ); ?>
				<p><input type="file" name="csv" accept=".csv,text/csv" required></p>
				<p class="description"><?php esc_html_e( 'Rows with an "id" update that variant; blank "id" creates a new one. "parent" accepts a product slug or numeric ID. Image / file columns accept a URL.', 'ricoman' ); ?></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Upload &amp; import', 'ricoman' ); ?></button></p>
			</form>
		</div>
	</div>
	<?php
}

/* -------------------------------------------------------------------- export */

add_action( 'admin_post_ricoman_variant_export', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'ricoman_variant_export' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
	}
	$cols     = ricoman_variant_csv_columns();
	$template = ! empty( $_POST['template'] );
	$parent   = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;

	$ids = array();
	if ( ! $template ) {
		$args = array(
			'post_type'      => 'variant-product',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);
		if ( $parent ) {
			$args['meta_query'] = array( array( 'key' => 'parent_product', 'value' => (string) $parent ) );
		}
		$ids = get_posts( $args );
	}

	$slug    = $parent ? get_post_field( 'post_name', $parent ) : 'all';
	$fname   = 'ricoman-variants-' . $slug . '-' . gmdate( 'Ymd' ) . '.csv';
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $fname . '"' );
	$out = fopen( 'php://output', 'w' );
	fprintf( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads accents correctly.
	fputcsv( $out, $cols );

	$meta_keys = ricoman_variant_csv_meta_keys();
	foreach ( $ids as $vid ) {
		$parent_id = (int) get_post_meta( $vid, 'parent_product', true );
		$row = array(
			'id'     => $vid,
			'parent' => $parent_id ? get_post_field( 'post_name', $parent_id ) : '',
			'title'  => get_the_title( $vid ),
		);
		foreach ( $meta_keys as $k ) {
			$v = get_post_meta( $vid, $k, true );
			if ( in_array( $k, array( 'download_led', 'product_main_image', 'product_diagram' ), true ) ) {
				$v = ricoman_variant_csv_url( $v );
			}
			$row[ $k ] = is_scalar( $v ) ? (string) $v : '';
		}
		// Keep column order.
		$line = array();
		foreach ( $cols as $c ) {
			$line[] = isset( $row[ $c ] ) ? $row[ $c ] : '';
		}
		fputcsv( $out, $line );
	}
	fclose( $out );
	exit;
} );

/* -------------------------------------------------------------------- import */

/** Resolve a "parent" cell (slug or numeric ID) to a product post ID. */
function ricoman_variant_resolve_parent( $val ) {
	$val = trim( (string) $val );
	if ( '' === $val ) {
		return 0;
	}
	if ( ctype_digit( $val ) && 'product' === get_post_type( (int) $val ) ) {
		return (int) $val;
	}
	$p = get_page_by_path( $val, OBJECT, 'product' );
	return $p ? (int) $p->ID : 0;
}

add_action( 'admin_post_ricoman_variant_import', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'ricoman_variant_import' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'ricoman' ) );
	}
	$back = admin_url( 'edit.php?post_type=variant-product&page=ricoman-variant-csv' );

	if ( empty( $_FILES['csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['csv']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( 'rm_csv', rawurlencode( __( 'No file uploaded.', 'ricoman' ) ), $back ) );
		exit;
	}

	$fh = fopen( $_FILES['csv']['tmp_name'], 'r' );
	if ( ! $fh ) {
		wp_safe_redirect( add_query_arg( 'rm_csv', rawurlencode( __( 'Could not read file.', 'ricoman' ) ), $back ) );
		exit;
	}

	$header = fgetcsv( $fh );
	if ( $header ) {
		// Strip a UTF-8 BOM from the first header cell.
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		$header    = array_map( function ( $h ) { return strtolower( trim( (string) $h ) ); }, $header );
	}
	$allowed   = ricoman_variant_csv_columns();
	$meta_keys = ricoman_variant_csv_meta_keys();

	$created = 0;
	$updated = 0;
	$skipped = 0;
	while ( ( $data = fgetcsv( $fh ) ) !== false ) {
		if ( count( array_filter( $data, function ( $c ) { return '' !== trim( (string) $c ); } ) ) === 0 ) {
			continue; // blank line.
		}
		$rec = array();
		foreach ( $header as $i => $col ) {
			if ( in_array( $col, $allowed, true ) ) {
				$rec[ $col ] = isset( $data[ $i ] ) ? trim( (string) $data[ $i ] ) : '';
			}
		}

		$id        = ! empty( $rec['id'] ) && ctype_digit( $rec['id'] ) ? (int) $rec['id'] : 0;
		$parent_id = isset( $rec['parent'] ) ? ricoman_variant_resolve_parent( $rec['parent'] ) : 0;
		$title     = isset( $rec['title'] ) && '' !== $rec['title']
			? $rec['title']
			: ( ! empty( $rec['part_code'] ) ? $rec['part_code'] : ( ! empty( $rec['order_code'] ) ? $rec['order_code'] : 'Variant' ) );

		// An update target must be an existing variant-product.
		if ( $id && 'variant-product' !== get_post_type( $id ) ) {
			$id = 0;
		}

		if ( $id ) {
			wp_update_post( array( 'ID' => $id, 'post_title' => $title ) );
			$updated++;
		} else {
			$id = wp_insert_post( array(
				'post_type'   => 'variant-product',
				'post_status' => 'publish',
				'post_title'  => $title,
			) );
			if ( ! $id || is_wp_error( $id ) ) {
				$skipped++;
				continue;
			}
			$created++;
		}

		if ( $parent_id ) {
			update_post_meta( $id, 'parent_product', (string) $parent_id );
		}
		foreach ( $meta_keys as $k ) {
			if ( array_key_exists( $k, $rec ) ) {
				// Empty cell clears the value; leaves untouched only if column absent.
				if ( '' === $rec[ $k ] ) {
					delete_post_meta( $id, $k );
				} else {
					update_post_meta( $id, $k, $rec[ $k ] );
				}
			}
		}
	}
	fclose( $fh );

	$msg = sprintf(
		/* translators: 1: created, 2: updated, 3: skipped */
		__( 'Import complete — %1$d created, %2$d updated, %3$d skipped.', 'ricoman' ),
		$created,
		$updated,
		$skipped
	);
	wp_safe_redirect( add_query_arg( 'rm_csv', rawurlencode( $msg ), $back ) );
	exit;
} );

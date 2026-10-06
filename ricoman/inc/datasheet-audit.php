<?php
/**
 * Datasheet Audit (Ricoman → Datasheet Audit).
 *
 * Scans every product's "Family datasheet" (field download_family_datasheet) and
 * flags the ones that are NOT the current format. The up-to-date datasheets are
 * named like "<Product>-Specs-Doc.pdf"; anything else is likely an older layout
 * that needs re-uploading. Read-only report + CSV export.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is this filename the current "-Specs-Doc.pdf" datasheet format? */
function ricoman_ds_is_current( $filename ) {
	return (bool) preg_match( '/specs[-_ ]?doc/i', (string) $filename );
}

/**
 * Build the audit rows: every product that has a family datasheet.
 * Returns array of [ pid, title, cat, filename, url, current(bool) ].
 */
function ricoman_ds_audit_rows() {
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );

	$rows = array();
	foreach ( (array) $ids as $pid ) {
		$val = function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'download_family_datasheet' ) : get_post_meta( $pid, 'download_family_datasheet', true );
		if ( empty( $val ) ) {
			continue; // No family datasheet on this product.
		}
		$url = function_exists( 'ricoman_pf_fileurl' ) ? ricoman_pf_fileurl( $val ) : ( is_string( $val ) ? $val : '' );
		if ( '' === $url ) {
			continue;
		}
		$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
		$filename = rawurldecode( basename( $path ) );

		$cats = get_the_terms( $pid, taxonomy_exists( 'product-cat' ) ? 'product-cat' : 'product_cat' );
		$cat  = ( is_array( $cats ) && $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';

		$rows[] = array(
			'pid'      => (int) $pid,
			'title'    => get_the_title( $pid ),
			'cat'      => $cat,
			'filename' => $filename,
			'url'      => $url,
			'current'  => ricoman_ds_is_current( $filename ),
		);
	}
	// Old / non-matching first so they're easy to action.
	usort( $rows, function ( $a, $b ) {
		if ( $a['current'] !== $b['current'] ) {
			return $a['current'] ? 1 : -1;
		}
		return strcasecmp( $a['title'], $b['title'] );
	} );
	return $rows;
}

/** Register the admin page under the Ricoman hub. */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Datasheet Audit', 'ricoman' ),
		__( 'Datasheet Audit', 'ricoman' ),
		'manage_options',
		'ricoman-ds-audit',
		'ricoman_ds_audit_page'
	);
}, 30 );

/** Render the audit report. */
function ricoman_ds_audit_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$rows    = ricoman_ds_audit_rows();
	$old     = array_filter( $rows, function ( $r ) { return ! $r['current']; } );
	$current = count( $rows ) - count( $old );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Datasheet Audit', 'ricoman' ); ?></h1>
		<p><?php esc_html_e( 'Checks every product’s “Family datasheet” download. Current datasheets are named like “<Product>-Specs-Doc.pdf”. Anything else is flagged below as likely an older version to re-upload.', 'ricoman' ); ?></p>
		<p style="font-size:13px">
			<strong><?php echo count( $rows ); ?></strong> <?php esc_html_e( 'products with a family datasheet', 'ricoman' ); ?> &nbsp;·&nbsp;
			<span style="color:#b32d2e"><strong><?php echo count( $old ); ?></strong> <?php esc_html_e( 'need attention (old / non-standard)', 'ricoman' ); ?></span> &nbsp;·&nbsp;
			<span style="color:#1a7f37"><strong><?php echo (int) $current; ?></strong> <?php esc_html_e( 'up to date', 'ricoman' ); ?></span>
		</p>
		<p>
			<?php $nonce = wp_create_nonce( 'ricoman_ds_audit_csv' ); ?>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin-post.php?action=ricoman_ds_audit_csv&scope=old&_wpnonce=' . $nonce ) ); ?>"><?php esc_html_e( 'Download CSV (needs attention)', 'ricoman' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin-post.php?action=ricoman_ds_audit_csv&scope=all&_wpnonce=' . $nonce ) ); ?>"><?php esc_html_e( 'Download CSV (all)', 'ricoman' ); ?></a>
		</p>

		<?php if ( empty( $rows ) ) : ?>
			<div class="notice notice-info"><p><?php esc_html_e( 'No products with a family datasheet were found.', 'ricoman' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Status', 'ricoman' ); ?></th>
						<th><?php esc_html_e( 'Product', 'ricoman' ); ?></th>
						<th><?php esc_html_e( 'Category', 'ricoman' ); ?></th>
						<th><?php esc_html_e( 'Datasheet file', 'ricoman' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<tr>
							<td>
								<?php if ( $r['current'] ) : ?>
									<span style="color:#1a7f37;font-weight:600">✓ <?php esc_html_e( 'Up to date', 'ricoman' ); ?></span>
								<?php else : ?>
									<span style="color:#b32d2e;font-weight:600">⚠ <?php esc_html_e( 'Check / old', 'ricoman' ); ?></span>
								<?php endif; ?>
							</td>
							<td><strong><?php echo esc_html( $r['title'] ); ?></strong></td>
							<td><?php echo esc_html( $r['cat'] ); ?></td>
							<td><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['filename'] ); ?></a></td>
							<td>
								<?php $edit = get_edit_post_link( $r['pid'] ); ?>
								<?php if ( $edit ) : ?><a href="<?php echo esc_url( $edit ); ?>"><?php esc_html_e( 'Edit', 'ricoman' ); ?></a><?php endif; ?>
								&nbsp;<a href="<?php echo esc_url( get_permalink( $r['pid'] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'ricoman' ); ?></a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/** CSV export of the audit. */
add_action( 'admin_post_ricoman_ds_audit_csv', function () {
	if ( ! current_user_can( 'manage_options' ) || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'ricoman_ds_audit_csv' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$scope = ( isset( $_GET['scope'] ) && 'all' === $_GET['scope'] ) ? 'all' : 'old';
	$rows  = ricoman_ds_audit_rows();
	if ( 'old' === $scope ) {
		$rows = array_filter( $rows, function ( $r ) { return ! $r['current']; } );
	}

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="ricoman-datasheet-audit-' . $scope . '-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, array( 'Status', 'Product', 'Category', 'Datasheet file', 'URL', 'Edit link' ) );
	foreach ( $rows as $r ) {
		fputcsv( $out, array(
			$r['current'] ? 'Up to date' : 'Check / old',
			$r['title'],
			$r['cat'],
			$r['filename'],
			$r['url'],
			(string) get_edit_post_link( $r['pid'], 'raw' ),
		) );
	}
	fclose( $out );
	exit;
} );

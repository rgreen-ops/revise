<?php
/**
 * Dynamic PDF datasheet generation.
 *
 * Every product gets a print-optimised datasheet at /?datasheet=1 (e.g.
 * /products/slim-downlight/?datasheet=1). The page is laid out for A4 and is
 * "Save as PDF"-ready in any browser. If a PDF engine (Dompdf) is autoloaded,
 * the same markup is streamed as a real downloadable PDF instead.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the ?datasheet query var.
 *
 * @param array $vars Query vars.
 * @return array
 */
function ricoman_datasheet_query_var( $vars ) {
	$vars[] = 'datasheet';
	return $vars;
}
add_filter( 'query_vars', 'ricoman_datasheet_query_var' );

/**
 * Return the public URL of a product's datasheet.
 *
 * @param int $product_id Product ID.
 * @return string
 */
function ricoman_datasheet_url( $product_id ) {
	return add_query_arg( 'datasheet', '1', get_permalink( $product_id ) );
}

/**
 * Intercept product requests asking for the datasheet and render it.
 */
function ricoman_maybe_render_datasheet() {
	if ( ! is_singular( 'product' ) || ! get_query_var( 'datasheet' ) ) {
		return;
	}

	$post_id = get_queried_object_id();
	$html    = ricoman_datasheet_markup( $post_id );

	// If a PDF engine is available, stream a real PDF.
	if ( class_exists( '\Dompdf\Dompdf' ) ) {
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => true ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$filename = sanitize_title( get_the_title( $post_id ) ) . '-datasheet.pdf';
		$dompdf->stream( $filename, array( 'Attachment' => true ) );
		exit;
	}

	// Otherwise serve the print-ready HTML page.
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled & escaped in builder.
	exit;
}
add_action( 'template_redirect', 'ricoman_maybe_render_datasheet' );

/**
 * Build the datasheet HTML for a product.
 *
 * @param int $post_id Product ID.
 * @return string
 */
function ricoman_datasheet_markup( $post_id ) {
	$title    = get_the_title( $post_id );
	$excerpt  = wp_strip_all_tags( get_the_excerpt( $post_id ) );
	$image    = get_the_post_thumbnail_url( $post_id, 'large' );
	$specs    = ricoman_product_spec_fields();
	$variants = ricoman_get_variants( $post_id );
	$site     = get_bloginfo( 'name' );
	$auto     = ( $excerpt ) ? '' : '';

	ob_start();
	?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( $title . ' — ' . __( 'Datasheet', 'ricoman' ) ); ?></title>
<style>
	@page { size: A4; margin: 18mm; }
	* { box-sizing: border-box; }
	body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; font-size: 12px; line-height: 1.5; }
	.ds-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #d81f26; padding-bottom: 12px; }
	.ds-brand { font-size: 22px; font-weight: 800; letter-spacing: .5px; color: #d81f26; }
	.ds-brand small { display:block; color:#5b6472; font-weight:600; font-size:10px; letter-spacing:.12em; text-transform:uppercase; }
	.ds-tag { text-align: right; font-size: 10px; color: #5b6472; text-transform: uppercase; letter-spacing: .1em; padding-top: 6px; }
	h1 { font-size: 24px; margin: 18px 0 4px; }
	.ds-intro { color: #374151; margin: 0 0 14px; max-width: 60%; }
	.ds-cols { display: flex; gap: 22px; }
	.ds-main { flex: 1; }
	.ds-aside { width: 220px; }
	.ds-img { width: 100%; border: 1px solid #e3e8ef; border-radius: 8px; }
	table { width: 100%; border-collapse: collapse; margin: 8px 0 18px; }
	th, td { text-align: left; padding: 7px 10px; border-bottom: 1px solid #e3e8ef; font-size: 11.5px; }
	th { background: #f5f7fa; width: 45%; color: #0e1726; }
	.ds-section { font-size: 11px; text-transform: uppercase; letter-spacing: .1em; color: #d81f26; font-weight: 700; margin: 14px 0 4px; }
	.ds-variants th { background: #0e1726; color: #fff; width: auto; }
	.ds-foot { margin-top: 26px; border-top: 1px solid #e3e8ef; padding-top: 10px; font-size: 10px; color: #5b6472; display: flex; justify-content: space-between; }
	@media screen { body { background:#eceff4; } .ds-page { background:#fff; max-width: 800px; margin: 24px auto; padding: 40px; box-shadow: 0 8px 30px rgba(0,0,0,.12); } .ds-print { text-align:center; margin: 16px; } .ds-print button { background:#d81f26; color:#fff; border:0; padding:10px 18px; border-radius:8px; font-weight:700; cursor:pointer; } }
	@media print { .ds-print { display:none; } .ds-page { padding:0; } }
</style>
</head>
<body>
<div class="ds-print"><button onclick="window.print()"><?php esc_html_e( 'Download / Print PDF', 'ricoman' ); ?></button></div>
<div class="ds-page">
	<div class="ds-head">
		<div class="ds-brand"><?php echo esc_html( $site ); ?><small><?php esc_html_e( 'Commercial Interior Lighting', 'ricoman' ); ?></small></div>
		<div class="ds-tag"><?php esc_html_e( 'Product Datasheet', 'ricoman' ); ?><br><?php echo esc_html( gmdate( 'F Y' ) ); ?></div>
	</div>

	<h1><?php echo esc_html( $title ); ?></h1>
	<?php if ( $excerpt ) : ?><p class="ds-intro"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>

	<div class="ds-cols">
		<div class="ds-main">
			<div class="ds-section"><?php esc_html_e( 'Specifications', 'ricoman' ); ?></div>
			<table>
				<tbody>
				<?php
				$any = false;
				foreach ( $specs as $key => $label ) :
					$val = (string) get_post_meta( $post_id, $key, true );
					if ( '' === $val ) {
						continue;
					}
					$any = true;
					?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $val ); ?></td></tr>
				<?php endforeach; ?>
				<?php if ( ! $any ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Specifications to be confirmed.', 'ricoman' ); ?></td></tr>
				<?php endif; ?>
				</tbody>
			</table>

			<?php if ( ! empty( $variants ) ) : ?>
				<div class="ds-section"><?php esc_html_e( 'Variants', 'ricoman' ); ?></div>
				<table class="ds-variants">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order code', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'Description', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'Watts', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'Lumens', 'ricoman' ); ?></th>
							<th><?php esc_html_e( 'CCT', 'ricoman' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $variants as $v ) : ?>
							<tr>
								<td><?php echo esc_html( $v['sku'] ); ?></td>
								<td><?php echo esc_html( $v['description'] ); ?></td>
								<td><?php echo esc_html( $v['wattage'] ); ?></td>
								<td><?php echo esc_html( $v['lumens'] ); ?></td>
								<td><?php echo esc_html( $v['cct'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="ds-aside">
			<?php if ( $image ) : ?>
				<img class="ds-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $title ); ?>">
			<?php endif; ?>
		</div>
	</div>

	<div class="ds-foot">
		<span><?php echo esc_html( $site ); ?> · <?php esc_html_e( 'Made in Britain', 'ricoman' ); ?></span>
		<span><?php echo esc_url( get_permalink( $post_id ) ); ?></span>
	</div>
</div>
</body>
</html>
	<?php
	return (string) ob_get_clean();
}

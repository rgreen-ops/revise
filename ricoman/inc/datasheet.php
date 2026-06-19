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
 * ?datasheet=1            -> the product datasheet.
 * ?datasheet={variantID}  -> an on-the-fly datasheet for a single order code,
 *                            built from that variant's own data + the parent
 *                            product's image, dimension diagram and info.
 */
function ricoman_maybe_render_datasheet() {
	$ds = get_query_var( 'datasheet' );
	if ( ! $ds ) {
		return;
	}

	// Per-variant datasheet (generated on the fly from the line's code).
	if ( is_numeric( $ds ) && 'variant-product' === get_post_type( (int) $ds ) ) {
		$vid  = (int) $ds;
		$html = ricoman_variant_datasheet_markup( $vid );
		ricoman_datasheet_output( $html, sanitize_title( get_the_title( $vid ) ) . '-datasheet' );
		return;
	}

	if ( ! is_singular( 'product' ) ) {
		return;
	}
	$post_id = get_queried_object_id();
	ricoman_datasheet_output( ricoman_datasheet_markup( $post_id ), sanitize_title( get_the_title( $post_id ) ) . '-datasheet' );
}
add_action( 'template_redirect', 'ricoman_maybe_render_datasheet' );

/** Stream as PDF if Dompdf is available, else print-ready HTML. */
function ricoman_datasheet_output( $html, $filename ) {
	if ( class_exists( '\Dompdf\Dompdf' ) ) {
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => true ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$dompdf->stream( $filename . '.pdf', array( 'Attachment' => true ) );
		exit;
	}
	header( 'Content-Type: text/html; charset=utf-8' );
	echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit;
}

/** Datasheet URL for a single variant (order code). */
function ricoman_variant_datasheet_url( $variant_id, $parent_id ) {
	return add_query_arg( 'datasheet', (int) $variant_id, get_permalink( $parent_id ) );
}

/**
 * Build an A4 datasheet for one variant, on the fly, from that line's own ACF
 * data plus the parent product's image, dimension diagram, spec and features.
 */
function ricoman_variant_datasheet_markup( $vid ) {
	$g  = function ( $k ) use ( $vid ) { return function_exists( 'ricoman_pf_get' ) ? (string) ricoman_pf_get( $vid, $k ) : (string) get_post_meta( $vid, $k, true ); };
	$gi = function ( $k ) use ( $vid ) { return function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( get_post_meta( $vid, $k, true ) ) : ''; };

	$parent_id = (int) get_post_meta( $vid, 'parent_product', true );
	$pname     = $parent_id ? get_the_title( $parent_id ) : '';
	$code      = $g( 'part_code' ) ? $g( 'part_code' ) : $g( 'order_code' );
	$desc      = $g( 'product_sort_description' );

	// Image: variant main image, else parent gallery / thumbnail.
	$image = $gi( 'product_main_image' );
	if ( ! $image ) {
		$image = $gi( 'product_gallery_image' );
	}
	if ( ! $image && $parent_id && function_exists( 'ricoman_product_img' ) ) {
		$image = ricoman_product_img( $parent_id );
	}
	// Dimension diagram: variant diagram, else parent dimension_diagrams[0].
	$diagram = $gi( 'product_diagram' );
	if ( ! $diagram ) {
		$diagram = $gi( 'photometric_diagram' );
	}
	if ( ! $diagram && $parent_id && function_exists( 'ricoman_pf_dimension_diagrams' ) ) {
		$dd = ricoman_pf_dimension_diagrams( $parent_id );
		$diagram = $dd ? $dd[0] : '';
	}

	// Spec rows from the variant's own fields.
	$spec_map = array(
		'lumens'                 => 'Lumens',
		'dimensions'             => 'Dimensions (mm)',
		'efficacy'               => 'Efficacy',
		'cri'                    => 'CRI',
		'beam_angle'             => 'Beam angle',
		'ip_rating'              => 'IP rating',
		'ik_rating'              => 'IK rating',
		'ugr'                    => 'UGR',
		'colour_finish'          => 'Colour finish',
		'operating_temperatures' => 'Operating temp.',
		'voltage_range'          => 'Voltage range',
		'power_factor'           => 'Power factor',
		'l70_b50'                => 'L70 B50',
		'optics'                 => 'Optics',
		'leds'                   => 'LEDs',
		'construction_material'  => 'Construction',
		'diffuser_type'          => 'Diffuser',
		'unit_weight'            => 'Unit weight',
		'warranty'               => 'Warranty',
		'certifications'         => 'Certifications',
	);
	$rows = '';
	foreach ( $spec_map as $k => $label ) {
		$v = $g( $k );
		if ( '' !== trim( $v ) ) {
			$rows .= '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
		}
	}

	$features = $parent_id ? (string) get_post_meta( $parent_id, 'key_features', true ) : '';
	$spectext = $parent_id ? (string) get_post_meta( $parent_id, 'specification', true ) : '';
	$site     = get_bloginfo( 'name' );
	$permalink = $parent_id ? get_permalink( $parent_id ) : home_url( '/' );

	ob_start();
	?>
<!doctype html><html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>"><head><meta charset="utf-8">
<title><?php echo esc_html( $pname . ' ' . $code . ' — Datasheet' ); ?></title>
<style>
	@page { size: A4; margin: 16mm; }
	*{box-sizing:border-box} body{font-family:Arial,Helvetica,sans-serif;color:#16161a;margin:0;font-size:12px;line-height:1.55}
	.ds-head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #004899;padding-bottom:12px}
	.ds-brand{font-size:22px;font-weight:800;color:#004899}.ds-brand small{display:block;color:#6b7280;font-weight:600;font-size:10px;letter-spacing:.12em;text-transform:uppercase}
	.ds-tag{text-align:right;font-size:10px;color:#6b7280;text-transform:uppercase;letter-spacing:.1em;padding-top:6px}
	h1{font-size:22px;margin:16px 0 2px}.ds-code{color:#004899;font-weight:700;font-size:14px;margin:0 0 2px}.ds-sub{color:#374151;margin:0 0 14px}
	.ds-cols{display:flex;gap:22px}.ds-main{flex:1}.ds-aside{width:230px}
	.ds-img{width:100%;border:1px solid #e3e8ef;border-radius:8px;background:#f5f5f3}
	.ds-diagram{width:100%;border:1px solid #e3e8ef;border-radius:8px;margin-top:12px;background:#fff;padding:6px}
	table{width:100%;border-collapse:collapse;margin:8px 0 14px}
	th,td{text-align:left;padding:6px 9px;border-bottom:1px solid #e3e8ef;font-size:11px}
	th{background:#f5f7fa;width:42%;color:#0e1726}
	.ds-section{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#004899;font-weight:700;margin:14px 0 4px}
	.ds-feat{font-size:11px}.ds-feat ul{margin:4px 0 0;padding-left:18px}
	.ds-foot{margin-top:24px;border-top:1px solid #e3e8ef;padding-top:10px;font-size:10px;color:#6b7280;display:flex;justify-content:space-between}
	@media screen{body{background:#eceff4}.ds-page{background:#fff;max-width:800px;margin:24px auto;padding:40px;box-shadow:0 8px 30px rgba(0,0,0,.12)}.ds-print{text-align:center;margin:16px}.ds-print button{background:#004899;color:#fff;border:0;padding:10px 18px;border-radius:8px;font-weight:700;cursor:pointer}}
	@media print{.ds-print{display:none}.ds-page{padding:0}}
</style></head><body>
<div class="ds-print"><button onclick="window.print()">Download / Print PDF</button></div>
<div class="ds-page">
	<div class="ds-head"><div class="ds-brand"><?php echo esc_html( $site ); ?><small>Commercial Interior Lighting</small></div>
	<div class="ds-tag">Product Datasheet<br><?php echo esc_html( gmdate( 'F Y' ) ); ?></div></div>
	<?php if ( $code ) : ?><p class="ds-code"><?php echo esc_html( $code ); ?></p><?php endif; ?>
	<h1><?php echo esc_html( $pname ); ?></h1>
	<?php if ( $desc ) : ?><p class="ds-sub"><?php echo esc_html( wp_strip_all_tags( $desc ) ); ?></p><?php endif; ?>
	<div class="ds-cols">
		<div class="ds-main">
			<div class="ds-section">Specification</div>
			<table><tbody><?php echo $rows ? $rows : '<tr><td colspan="2">Specification to be confirmed.</td></tr>'; // phpcs:ignore ?></tbody></table>
			<?php if ( '' !== trim( wp_strip_all_tags( $features ) ) ) : ?>
				<div class="ds-section">Key features</div>
				<div class="ds-feat"><?php echo wp_kses_post( $features ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== trim( wp_strip_all_tags( $spectext ) ) ) : ?>
				<div class="ds-section">Technical detail</div>
				<div class="ds-feat"><?php echo wp_kses_post( $spectext ); ?></div>
			<?php endif; ?>
		</div>
		<div class="ds-aside">
			<?php if ( $image ) : ?><img class="ds-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $pname ); ?>"><?php endif; ?>
			<?php if ( $diagram ) : ?><img class="ds-diagram" src="<?php echo esc_url( $diagram ); ?>" alt="Dimensions"><?php endif; ?>
		</div>
	</div>
	<div class="ds-foot"><span><?php echo esc_html( $site ); ?> · Made in Britain</span><span><?php echo esc_url( $permalink ); ?></span></div>
</div></body></html>
	<?php
	return (string) ob_get_clean();
}

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

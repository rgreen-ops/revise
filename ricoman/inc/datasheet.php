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
 * Build a two-page A4 datasheet for one variant, on the fly, from that line's
 * ACF data plus the parent product's images and dimension/photometric diagrams.
 * Layout matches the Ricoman branded datasheet format.
 */
function ricoman_variant_datasheet_markup( $vid ) {
	$g = function ( $k ) use ( $vid ) {
		$v = function_exists( 'ricoman_pf_get' ) ? (string) ricoman_pf_get( $vid, $k ) : (string) get_post_meta( $vid, $k, true );
		return function_exists( 'ricoman_fix_text' ) ? ricoman_fix_text( trim( $v ) ) : trim( $v );
	};
	$gi = function ( $k ) use ( $vid ) {
		$v = get_post_meta( $vid, $k, true );
		return function_exists( 'ricoman_pf_imgurl' ) ? ricoman_pf_imgurl( $v ) : '';
	};
	$tv = function ( $tax ) use ( $vid ) {
		if ( ! taxonomy_exists( $tax ) ) {
			return '';
		}
		$terms = get_the_terms( $vid, $tax );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		$val = implode( ', ', wp_list_pluck( $terms, 'name' ) );
		return function_exists( 'ricoman_fix_text' ) ? ricoman_fix_text( $val ) : $val;
	};

	$parent_id = (int) get_post_meta( $vid, 'parent_product', true );
	$pname     = $parent_id ? get_the_title( $parent_id ) : get_the_title( $vid );
	$code      = $g( 'part_code' ) ?: $g( 'order_code' );
	$desc      = $g( 'product_sort_description' );

	// Hero image: variant → parent gallery/thumbnail.
	$image = $gi( 'product_main_image' ) ?: $gi( 'product_gallery_image' );
	if ( ! $image && $parent_id ) {
		if ( function_exists( 'ricoman_product_img' ) ) {
			$image = ricoman_product_img( $parent_id );
		}
		if ( ! $image ) {
			$v     = get_post_meta( $parent_id, 'product_main_image', true );
			$image = ( $v && function_exists( 'ricoman_pf_imgurl' ) ) ? ricoman_pf_imgurl( $v ) : '';
		}
	}

	// Dimension diagram: variant field → parent dimension_diagrams[0].
	$dim_diagram = $gi( 'product_diagram' );
	if ( ! $dim_diagram && $parent_id ) {
		if ( function_exists( 'ricoman_pf_dimension_diagrams' ) ) {
			$dd          = ricoman_pf_dimension_diagrams( $parent_id );
			$dim_diagram = $dd ? $dd[0] : '';
		}
		if ( ! $dim_diagram ) {
			$v           = get_post_meta( $parent_id, 'product_diagram', true );
			$dim_diagram = ( $v && function_exists( 'ricoman_pf_imgurl' ) ) ? ricoman_pf_imgurl( $v ) : '';
		}
	}

	// Photometric diagram: variant field → parent fallback.
	$photometric = $gi( 'photometric_diagram' );
	if ( ! $photometric && $parent_id ) {
		$v           = get_post_meta( $parent_id, 'photometric_diagram', true );
		$photometric = ( $v && function_exists( 'ricoman_pf_imgurl' ) ) ? ricoman_pf_imgurl( $v ) : '';
	}

	// Logo image from the Customizer, if set.
	$logo_id  = get_theme_mod( 'custom_logo' );
	$logo_url = $logo_id ? (string) wp_get_attachment_image_url( (int) $logo_id, 'full' ) : '';

	// Lumens resolved the same way as the configure table.
	$lumens = function_exists( 'ricoman_variant_lumens' ) ? ricoman_variant_lumens( $vid ) : $g( 'lumens' );

	// Site options for the footer.
	$rm_opts       = (array) get_option( 'ricoman_settings', array() );
	$foot_phone    = isset( $rm_opts['foot_phone'] ) ? $rm_opts['foot_phone'] : '0161 451 5913';
	$foot_email    = isset( $rm_opts['foot_email'] ) ? $rm_opts['foot_email'] : 'sales@ricoman.com';
	$site_domain   = preg_replace( '#^https?://#', '', rtrim( home_url( '/' ), '/' ) );

	// ------------------------------------------------------------------ sections
	// Each row: [ label, value, is_accent ]
	$p1_sections = array(
		'Product Data' => array(
			array( 'Part Code (s)', $code, true ),
			array( 'Applications', $g( 'applications' ) ?: $tv( 'application-area' ), true ),
			array( 'Warranty', $g( 'warranty' ), false ),
			array( 'Certification(s)', $g( 'certifications' ), false ),
		),
		'Physical Data' => array(
			array( 'Module', $g( 'module' ) ?: $tv( 'fitting-type' ), true ),
			array( 'Colour Finish', $g( 'colour_finish' ), false ),
			array( 'Body Colour', $tv( 'color' ) ?: $g( 'body_colour' ), false ),
			array( 'Dimensions (mm)', $g( 'dimensions' ), false ),
			array( 'Length', $tv( 'size' ) ?: $g( 'length' ), false ),
			array( 'Luminaire Fixing', $g( 'luminaire_fixing' ), true ),
			array( 'Construction Material', $g( 'construction_material' ), false ),
			array( 'Diffuser Type', $g( 'diffuser_type' ) ?: $tv( 'diffuser-material' ), false ),
		),
		'Electrical Data' => array(
			array( 'Wattage', $tv( 'wattage' ), false ),
			array( 'Voltage Range', $g( 'voltage_range' ), false ),
			array( 'Power Factor', $g( 'power_factor' ), false ),
			array( 'Inrush Current', $g( 'inrush_current' ), false ),
			array( 'Running Current', $g( 'running_current' ), false ),
		),
	);
	$p2_sections = array(
		'Technical Data' => array(
			array( 'Lumens (±5%)', $lumens, false ),
			array( 'Efficacy', $g( 'efficacy' ), false ),
			array( 'Operating Temperature', $g( 'operating_temperatures' ) ?: $g( 'operating_temperature' ), false ),
			array( 'Beam Angle', $g( 'beam_angle' ) ?: $tv( 'beam-angle' ), false ),
			array( 'Operating Hours', $g( 'operating_hours' ), false ),
			array( 'IP Rating', $g( 'ip_rating' ) ?: $tv( 'iprating' ), false ),
			array( 'IK Rating', $g( 'ik_rating' ), false ),
		),
		'Light Source Data' => array(
			array( 'Kelvins', $tv( 'temperature' ) ?: $g( 'colour_temperature' ), true ),
			array( 'CRI', $g( 'cri' ), false ),
			array( 'Macadam Ellipse', $g( 'macadam_ellipse' ), false ),
			array( 'L80 B50', $g( 'l80_b50' ) ?: $g( 'l80b50' ), true ),
			array( 'LEDs', $g( 'leds' ), false ),
		),
	);

	// Build section HTML (returns '' when all rows are empty).
	$render_section = function ( $heading, $rows ) {
		$trs = '';
		foreach ( $rows as $row ) {
			if ( '' === trim( (string) $row[1] ) ) {
				continue;
			}
			$cls  = $row[2] ? ' class="ds2-ac"' : '';
			$trs .= '<tr' . $cls . '><td class="ds2-lbl">' . esc_html( $row[0] ) . '</td><td>' . esc_html( $row[1] ) . '</td></tr>';
		}
		if ( '' === $trs ) {
			return '';
		}
		return '<div class="ds2-sg"><div class="ds2-sh">' . esc_html( $heading ) . '</div>'
			. '<table class="ds2-tb"><tbody>' . $trs . '</tbody></table></div>';
	};

	$p1_html = '';
	foreach ( $p1_sections as $heading => $rows ) {
		$p1_html .= $render_section( $heading, $rows );
	}
	$p2_html = '';
	foreach ( $p2_sections as $heading => $rows ) {
		$p2_html .= $render_section( $heading, $rows );
	}

	ob_start();
	?>
<!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<title><?php echo esc_html( trim( $pname . ' ' . $code ) . ' — Datasheet' ); ?></title>
<style>
/* reset */
*{box-sizing:border-box;margin:0;padding:0}
/* base */
body{font-family:Arial,Helvetica,sans-serif;font-size:10px;color:#1a1a1a;background:#ccc;line-height:1.4}
/* pages */
.ds2-wrap{max-width:820px;margin:0 auto;padding:20px 0;display:flex;flex-direction:column;gap:20px}
.ds2-page{background:#fff;width:100%;overflow:hidden}
/* hero (page 1) */
.ds2-hero{background:#ebebeb;height:220px;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.ds2-hero-img{display:flex;align-items:center;justify-content:center;width:100%;height:100%;padding:16px}
.ds2-hero-img img{max-height:190px;max-width:70%;object-fit:contain}
.ds2-logo-box{position:absolute;top:14px;right:16px;text-align:right}
.ds2-logo-txt{display:block;font-size:17px;font-weight:900;letter-spacing:2.5px;color:#1a1a1a;line-height:1}
.ds2-logo-img{max-height:34px;max-width:150px;display:block;margin-left:auto}
.ds2-logo-tag{display:block;font-size:6.5px;letter-spacing:1.2px;text-transform:uppercase;color:#555;margin-top:3px}
/* identity */
.ds2-ident{padding:13px 18px 6px}
.ds2-pname{font-size:19px;font-weight:700;line-height:1.2}
.ds2-desc{font-size:10.5px;color:#d81f26;margin-top:4px;font-weight:500}
/* body columns */
.ds2-body{display:flex;padding:6px 18px 16px;gap:0}
.ds2-lc{flex:0 0 58%;padding-right:14px}
.ds2-rc{flex:1;padding-left:14px;border-left:1px solid #e0e0e0}
/* section group */
.ds2-sg{margin-bottom:9px}
.ds2-sh{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#1a1a1a;border-bottom:1.5px solid #1a1a1a;padding-bottom:2px}
/* data table */
.ds2-tb{width:100%;border-collapse:collapse}
.ds2-tb td{padding:2.5px 4px;border-bottom:1px solid #ebebeb;font-size:9px;vertical-align:top}
.ds2-lbl{width:45%;font-weight:500;color:#1a1a1a}
.ds2-ac td{color:#d81f26!important}
/* right-column diagram area */
.ds2-diag-lbl{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;border-bottom:1.5px solid #1a1a1a;padding-bottom:2px;margin-bottom:7px}
.ds2-diag-img img{width:100%;max-width:100%;object-fit:contain;display:block}
.ds2-no-img{font-size:8.5px;color:#888;font-style:italic;padding:10px 0}
/* page 2 header strip */
.ds2-p2h{display:flex;justify-content:space-between;align-items:center;padding:9px 18px;border-bottom:2px solid #1a1a1a}
.ds2-p2h-code{font-size:9.5px;font-weight:700}
/* footer */
.ds2-hr{border:none;border-top:1px solid #d0d0d0;margin:0 18px}
.ds2-foot{display:flex;justify-content:space-between;flex-wrap:wrap;gap:3px 20px;padding:6px 18px 10px;font-size:8px;color:#666}
/* print button */
.ds2-printbtn{text-align:center;padding:14px 0}
.ds2-printbtn button{background:#d81f26;color:#fff;border:0;padding:9px 22px;font-size:13px;font-weight:700;border-radius:5px;cursor:pointer}
/* print */
@media print{
  @page{size:A4;margin:10mm}
  body{background:#fff;font-size:10px}
  .ds2-wrap{max-width:none;padding:0;gap:0}
  .ds2-printbtn{display:none}
  .ds2-page{page-break-after:always}
  .ds2-page:last-child{page-break-after:auto}
}
</style>
</head>
<body>

<div class="ds2-printbtn"><button onclick="window.print()">Print / Save as PDF</button></div>

<div class="ds2-wrap">

<!-- ===== PAGE 1 ===== -->
<div class="ds2-page">

  <!-- Hero -->
  <div class="ds2-hero">
    <div class="ds2-logo-box">
      <?php if ( $logo_url ) : ?>
        <img class="ds2-logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
      <?php else : ?>
        <span class="ds2-logo-txt">RICOMAN</span>
        <span class="ds2-logo-tag">Your Lighting. Our Passion.</span>
      <?php endif; ?>
    </div>
    <div class="ds2-hero-img">
      <?php if ( $image ) : ?>
        <img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $pname ); ?>">
      <?php endif; ?>
    </div>
  </div>

  <!-- Product identity -->
  <div class="ds2-ident">
    <div class="ds2-pname"><?php echo esc_html( $pname ); ?></div>
    <?php if ( $desc ) : ?>
      <div class="ds2-desc"><?php echo esc_html( wp_strip_all_tags( $desc ) ); ?></div>
    <?php endif; ?>
  </div>

  <!-- Specs (left) + Dimension diagram (right) -->
  <div class="ds2-body">
    <div class="ds2-lc"><?php echo $p1_html; // phpcs:ignore ?></div>
    <div class="ds2-rc">
      <div class="ds2-diag-lbl">Dimension Diagram</div>
      <div class="ds2-diag-img">
        <?php if ( $dim_diagram ) : ?>
          <img src="<?php echo esc_url( $dim_diagram ); ?>" alt="Dimension Diagram">
        <?php else : ?>
          <p class="ds2-no-img">Image not available</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <hr class="ds2-hr">
  <div class="ds2-foot">
    <span><?php echo esc_html( $site_domain ); ?></span>
    <span><?php echo esc_html( $foot_phone ); ?></span>
    <span>Product design and technical data may be subject to change.</span>
    <span><?php echo esc_html( $foot_email ); ?></span>
  </div>

</div><!-- /page 1 -->

<!-- ===== PAGE 2 ===== -->
<div class="ds2-page">

  <!-- Page 2 header strip -->
  <div class="ds2-p2h">
    <?php if ( $code ) : ?>
      <span class="ds2-p2h-code">Part Code: <?php echo esc_html( $code ); ?></span>
    <?php else : ?>
      <span></span>
    <?php endif; ?>
    <div style="text-align:right">
      <?php if ( $logo_url ) : ?>
        <img class="ds2-logo-img" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" style="max-height:26px">
      <?php else : ?>
        <span class="ds2-logo-txt" style="font-size:14px">RICOMAN</span>
        <span class="ds2-logo-tag">Your Lighting. Our Passion.</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Specs (left) + Photometric diagram (right) -->
  <div class="ds2-body" style="padding-top:14px">
    <div class="ds2-lc"><?php echo $p2_html; // phpcs:ignore ?></div>
    <div class="ds2-rc">
      <div class="ds2-diag-lbl">Photometric Diagram</div>
      <div class="ds2-diag-img">
        <?php if ( $photometric ) : ?>
          <img src="<?php echo esc_url( $photometric ); ?>" alt="Photometric Diagram">
        <?php else : ?>
          <p class="ds2-no-img">Image not available</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Footer -->
  <hr class="ds2-hr">
  <div class="ds2-foot">
    <span><?php echo esc_html( $site_domain ); ?></span>
    <span><?php echo esc_html( $foot_phone ); ?></span>
    <span>Product design and technical data may be subject to change.</span>
    <span><?php echo esc_html( $foot_email ); ?></span>
  </div>

</div><!-- /page 2 -->

</div><!-- .ds2-wrap -->
</body>
</html>
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

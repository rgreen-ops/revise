<?php
/**
 * Bulk Studio / In-situ image classifier.
 *
 * Product pages split gallery images into Studio (the product on white / a
 * cut-out — stored in `product_gallery_image`) and In-situ (the product in a
 * real space — stored in `insitu_gallery`). Sorting these by hand across every
 * product is slow, so this tool auto-classifies them: it samples each image's
 * edges and calls white/transparent borders "studio", everything else
 * "in-situ", then writes each product's two gallery fields.
 *
 * Heuristic, so it includes a dry-run preview and only writes on Apply.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Normalise an ACF gallery value (IDs / arrays / URLs) to attachment IDs. */
function ricoman_gc_ids( $val ) {
	$ids = array();
	foreach ( (array) $val as $v ) {
		if ( is_array( $v ) && isset( $v['ID'] ) ) {
			$ids[] = (int) $v['ID'];
		} elseif ( is_numeric( $v ) ) {
			$ids[] = (int) $v;
		} elseif ( is_string( $v ) && false !== strpos( $v, '://' ) ) {
			$id = attachment_url_to_postid( $v );
			if ( $id ) {
				$ids[] = (int) $id;
			}
		}
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/** Classify one image file as 'studio' (white/cut-out edges) or 'insitu'. */
function ricoman_gc_kind_from_file( $file ) {
	if ( ! $file || ! file_exists( $file ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
		return null;
	}
	$size = @getimagesize( $file );
	if ( ! $size ) {
		return null;
	}
	list( $w, $h, $type ) = $size;
	if ( $w < 4 || $h < 4 ) {
		return null;
	}
	$im = null;
	switch ( $type ) {
		case IMAGETYPE_JPEG: $im = @imagecreatefromjpeg( $file ); break;
		case IMAGETYPE_PNG:  $im = @imagecreatefrompng( $file ); break;
		case IMAGETYPE_GIF:  $im = @imagecreatefromgif( $file ); break;
		case IMAGETYPE_WEBP: $im = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $file ) : null; break;
	}
	if ( ! $im ) {
		return null;
	}
	$light = 0;
	$n     = 0;
	$steps = 16;
	for ( $i = 0; $i <= $steps; $i++ ) {
		$x = min( (int) round( $w * $i / $steps ), $w - 1 );
		$y = min( (int) round( $h * $i / $steps ), $h - 1 );
		$pts = array( array( $x, 0 ), array( $x, $h - 1 ), array( 0, $y ), array( $w - 1, $y ) );
		foreach ( $pts as $p ) {
			$rgba = @imagecolorat( $im, $p[0], $p[1] );
			if ( false === $rgba ) {
				continue;
			}
			$a = ( $rgba >> 24 ) & 0x7F;
			$r = ( $rgba >> 16 ) & 0xFF;
			$g = ( $rgba >> 8 ) & 0xFF;
			$b = $rgba & 0xFF;
			$n++;
			if ( $a > 64 ) { // transparent
				$light++;
			} elseif ( $r >= 238 && $g >= 238 && $b >= 238 ) { // near-white
				$light++;
			}
		}
	}
	imagedestroy( $im );
	if ( 0 === $n ) {
		return null;
	}
	return ( $light / $n ) >= 0.66 ? 'studio' : 'insitu';
}

/** Classify one attachment (defaults to 'studio' when undecidable — keeps it
 * in the main gallery rather than guessing it into In-situ). */
function ricoman_gc_classify( $att_id ) {
	$meta = wp_get_attachment_metadata( $att_id );
	$file = get_attached_file( $att_id );
	// Prefer a medium sub-size for speed if it's a GD-readable format.
	if ( $file && ! empty( $meta['sizes']['medium']['file'] ) ) {
		$sub = trailingslashit( dirname( $file ) ) . $meta['sizes']['medium']['file'];
		if ( file_exists( $sub ) && preg_match( '/\.(jpe?g|png|gif|webp)$/i', $sub ) ) {
			$file = $sub;
		}
	}
	$kind = ricoman_gc_kind_from_file( $file );
	return $kind ? $kind : 'studio';
}

/** Re-sort one product's images into Studio / In-situ. Dry-run unless $apply. */
function ricoman_gc_product( $pid, $apply ) {
	$studio_now = ricoman_gc_ids( function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'product_gallery_image', array() ) : get_post_meta( $pid, 'product_gallery_image', true ) );
	$insitu_now = ricoman_gc_ids( function_exists( 'ricoman_pf_get' ) ? ricoman_pf_get( $pid, 'insitu_gallery', array() ) : get_post_meta( $pid, 'insitu_gallery', true ) );
	$all = array_values( array_unique( array_merge( $studio_now, $insitu_now ) ) );
	if ( ! $all ) {
		return array( 'studio' => 0, 'insitu' => 0, 'moved' => 0 );
	}
	$studio = array();
	$insitu = array();
	foreach ( $all as $id ) {
		if ( 'insitu' === ricoman_gc_classify( (int) $id ) ) {
			$insitu[] = (int) $id;
		} else {
			$studio[] = (int) $id;
		}
	}
	$moved = count( array_diff( $insitu, $insitu_now ) ) + count( array_diff( $studio, $studio_now ) );
	if ( $apply ) {
		if ( function_exists( 'update_field' ) ) {
			update_field( 'product_gallery_image', $studio, $pid );
			update_field( 'insitu_gallery', $insitu, $pid );
		} else {
			update_post_meta( $pid, 'product_gallery_image', $studio );
			update_post_meta( $pid, 'insitu_gallery', $insitu );
		}
	}
	return array( 'studio' => count( $studio ), 'insitu' => count( $insitu ), 'moved' => $moved );
}

/* ------------------------------------------------------------------ admin */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'ricoman-hub',
		__( 'Studio / In-situ Sort', 'ricoman' ),
		__( 'Studio / In-situ Sort', 'ricoman' ),
		'manage_options',
		'ricoman-gallery-classify',
		'ricoman_gc_page'
	);
}, 34 );

function ricoman_gc_page() {
	$total = (int) wp_count_posts( 'product' )->publish;
	echo '<div class="wrap"><h1>' . esc_html__( 'Sort Studio / In-situ images', 'ricoman' ) . '</h1>';
	echo '<p>' . esc_html__( 'Automatically sorts every product\'s gallery images into Studio (product on white / cut-outs) and In-situ (real-space photos), so both tabs on the product page are populated without sorting by hand.', 'ricoman' ) . '</p>';
	echo '<p><strong>' . esc_html( sprintf( __( '%d published products', 'ricoman' ), $total ) ) . '</strong></p>';
	echo '<p>' . esc_html__( 'Preview first (no changes). It\'s a best-guess from each image\'s background, so do a quick spot-check after applying and fix any odd ones on the product.', 'ricoman' ) . '</p>';
	echo '<p><button class="button" id="rm-gc-preview">' . esc_html__( 'Preview classification', 'ricoman' ) . '</button> '
		. '<button class="button button-primary" id="rm-gc-apply">' . esc_html__( 'Apply — sort into Studio / In-situ', 'ricoman' ) . '</button> '
		. '<span id="rm-gc-out" style="margin-left:10px"></span></p>';
	$nonce = wp_create_nonce( 'rm_gc' );
	?>
	<script>
	(function(){
		var nonce=<?php echo wp_json_encode( $nonce ); ?>, ajax=<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var out=document.getElementById('rm-gc-out'), busy=false;
		function run(apply){
			if(busy)return; if(apply && !confirm('Sort all product images into Studio / In-situ now? Preview first if unsure.'))return;
			busy=true; document.getElementById('rm-gc-preview').disabled=document.getElementById('rm-gc-apply').disabled=true;
			var studio=0,insitu=0,moved=0;
			function batch(off){
				var b=new URLSearchParams({action:'rm_gc_run',nonce:nonce,apply:apply?'1':'0',offset:off});
				fetch(ajax,{method:'POST',body:b,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
					if(!j||!j.success){ out.textContent='Error — stopped.'; busy=false; document.getElementById('rm-gc-preview').disabled=document.getElementById('rm-gc-apply').disabled=false; return; }
					var d=j.data; studio+=d.studio; insitu+=d.insitu; moved+=d.moved;
					out.textContent=(apply?'Sorted ':'Would sort ')+d.done+'/'+d.total+' products — '+studio.toLocaleString()+' studio, '+insitu.toLocaleString()+' in-situ ('+moved.toLocaleString()+' moved)…';
					if(d.next!==null){ setTimeout(function(){batch(d.next);}, 200); }
					else { out.textContent=(apply?'Done. ':'Preview: ')+studio.toLocaleString()+' studio, '+insitu.toLocaleString()+' in-situ'+(apply?'':' ('+moved.toLocaleString()+' would move)')+'.'; busy=false; document.getElementById('rm-gc-preview').disabled=document.getElementById('rm-gc-apply').disabled=false; }
				}).catch(function(){ out.textContent='Network error — click to resume.'; busy=false; document.getElementById('rm-gc-preview').disabled=document.getElementById('rm-gc-apply').disabled=false; });
			}
			batch(0);
		}
		document.getElementById('rm-gc-preview').addEventListener('click',function(){ out.textContent='Checking…'; run(false); });
		document.getElementById('rm-gc-apply').addEventListener('click',function(){ out.textContent='Sorting…'; run(true); });
	})();
	</script>
	<?php
	echo '</div>';
}

add_action( 'wp_ajax_rm_gc_run', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'rm_gc', 'nonce', false ) ) {
		wp_send_json_error();
	}
	$apply  = isset( $_POST['apply'] ) && '1' === $_POST['apply'];
	$offset = max( 0, (int) ( $_POST['offset'] ?? 0 ) );
	$batch  = 8;
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $batch,
		'offset'         => $offset,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$studio = 0;
	$insitu = 0;
	$moved  = 0;
	foreach ( $ids as $pid ) {
		$r = ricoman_gc_product( (int) $pid, $apply );
		$studio += $r['studio'];
		$insitu += $r['insitu'];
		$moved  += $r['moved'];
	}
	$total = (int) wp_count_posts( 'product' )->publish;
	$next  = $offset + count( $ids );
	wp_send_json_success( array(
		'studio' => $studio,
		'insitu' => $insitu,
		'moved'  => $moved,
		'done'   => $next,
		'total'  => $total,
		'next'   => count( $ids ) < $batch ? null : $next,
	) );
} );

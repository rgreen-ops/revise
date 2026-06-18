<?php
/**
 * Structured product-page content — modelled on the existing ricoman.com ACF
 * setup so products are edited as FIELDS (not baked-in blocks) and rendered by
 * the template. Theme updates restyle products without ever touching content.
 *
 * Fields (all stored in post meta, so they survive every theme update):
 *   _ricoman_subname            Product sub-name (shown under the title)
 *   _ricoman_colour_variants    Colour variants: "Name | main image | swatch"
 *   _ricoman_paragraphs         Paragraph info: one paragraph per line
 *   _ricoman_zigzag             Zig-zag sections: "media | title | text | btn | url"
 *   _ricoman_ld_btn / _url      "Request a Lighting Design" button
 *   _ricoman_trade_btn / _url   "Apply for a Trade Account" button
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ----------------------------------------------------------- register meta */
add_action( 'init', function () {
	$keys = array( '_ricoman_subname', '_ricoman_colour_variants', '_ricoman_paragraphs', '_ricoman_zigzag', '_ricoman_ld_btn', '_ricoman_ld_url', '_ricoman_trade_btn', '_ricoman_trade_url' );
	foreach ( $keys as $k ) {
		register_post_meta( 'product', $k, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
} );

/** Parse "a | b | c" lines into rows of trimmed parts. */
function ricoman_pf_lines( $pid, $key ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) get_post_meta( $pid, $key, true ) ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$out[] = array_map( 'trim', explode( '|', $line ) );
	}
	return $out;
}

/* --------------------------------------------------------------- meta box */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'ricoman_pf', '🧩 Product page content (structured)', 'ricoman_pf_box', 'product', 'normal', 'high' );
} );

function ricoman_pf_box( $post ) {
	wp_nonce_field( 'ricoman_pf_save', 'ricoman_pf_nonce' );
	$m = function ( $k ) use ( $post ) { return (string) get_post_meta( $post->ID, $k, true ); };
	?>
	<style>
		.rpf label{display:block;font-weight:600;margin:14px 0 4px;font-size:13px}
		.rpf input[type=text],.rpf input[type=url],.rpf textarea{width:100%}
		.rpf .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
		.rpf .rep .row{display:flex;gap:8px;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap}
		.rpf .rep .row input{flex:1;min-width:120px}
		.rpf .rep .row textarea{flex:2;min-width:200px}
		.rpf .pick{white-space:nowrap}
		.rpf .del{color:#b32d2e;text-decoration:none;cursor:pointer;font-size:16px;line-height:30px}
		.rpf h4{margin:22px 0 2px;font-size:12px;text-transform:uppercase;letter-spacing:.1em;color:#777}
		.rpf .hint{color:#777;font-size:12px;margin:2px 0 0}
	</style>
	<div class="rpf">
		<label><?php esc_html_e( 'Product sub-name (shown under the title)', 'ricoman' ); ?></label>
		<input type="text" name="_ricoman_subname" value="<?php echo esc_attr( $m( '_ricoman_subname' ) ); ?>" placeholder="High Output, Low Energy Panel">

		<h4><?php esc_html_e( 'Colour variants', 'ricoman' ); ?></h4>
		<p class="hint"><?php esc_html_e( 'Each colour shows a swatch; clicking it swaps the main product image.', 'ricoman' ); ?></p>
		<div class="rep" id="rpf-cv" data-tpl="cv">
		<?php
		$cv = ricoman_pf_lines( $post->ID, '_ricoman_colour_variants' );
		if ( ! $cv ) {
			$cv = array( array( '', '', '' ) );
		}
		foreach ( $cv as $r ) {
			echo ricoman_pf_cv_row( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		</div>
		<p><button type="button" class="button rpf-add" data-rep="rpf-cv" data-tpl="cv">＋ <?php esc_html_e( 'Add colour', 'ricoman' ); ?></button></p>

		<h4><?php esc_html_e( 'Paragraph info', 'ricoman' ); ?></h4>
		<p class="hint"><?php esc_html_e( 'One key point per line (e.g. “Glare-free precision: …”).', 'ricoman' ); ?></p>
		<textarea name="_ricoman_paragraphs" rows="4"><?php echo esc_textarea( $m( '_ricoman_paragraphs' ) ); ?></textarea>

		<h4><?php esc_html_e( 'Zig-zag content sections', 'ricoman' ); ?></h4>
		<p class="hint"><?php esc_html_e( 'Alternating image/text rows: image, heading, text, optional button.', 'ricoman' ); ?></p>
		<div class="rep" id="rpf-zz" data-tpl="zz">
		<?php
		$zz = ricoman_pf_lines( $post->ID, '_ricoman_zigzag' );
		if ( ! $zz ) {
			$zz = array( array( '', '', '', '', '' ) );
		}
		foreach ( $zz as $r ) {
			echo ricoman_pf_zz_row( $r ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		?>
		</div>
		<p><button type="button" class="button rpf-add" data-rep="rpf-zz" data-tpl="zz">＋ <?php esc_html_e( 'Add section', 'ricoman' ); ?></button></p>

		<h4><?php esc_html_e( 'Call-to-action buttons', 'ricoman' ); ?></h4>
		<div class="grid2">
			<div><label><?php esc_html_e( 'Lighting Design button — title', 'ricoman' ); ?></label><input type="text" name="_ricoman_ld_btn" value="<?php echo esc_attr( $m( '_ricoman_ld_btn' ) ); ?>" placeholder="Request a Lighting Design"></div>
			<div><label><?php esc_html_e( 'Lighting Design button — link', 'ricoman' ); ?></label><input type="url" name="_ricoman_ld_url" value="<?php echo esc_attr( $m( '_ricoman_ld_url' ) ); ?>" placeholder="/lighting-design/"></div>
			<div><label><?php esc_html_e( 'Trade button — title', 'ricoman' ); ?></label><input type="text" name="_ricoman_trade_btn" value="<?php echo esc_attr( $m( '_ricoman_trade_btn' ) ); ?>" placeholder="Apply for a Trade Account"></div>
			<div><label><?php esc_html_e( 'Trade button — link', 'ricoman' ); ?></label><input type="url" name="_ricoman_trade_url" value="<?php echo esc_attr( $m( '_ricoman_trade_url' ) ); ?>" placeholder="/contact/"></div>
		</div>
	</div>
	<script>
	( function () {
		function tpl( t ) {
			if ( 'cv' === t ) { return '<div class="row"><input type="text" name="rpf_cv_name[]" placeholder="Colour (e.g. Black)"><input type="text" class="rpf-url" name="rpf_cv_img[]" placeholder="Main image URL"><button type="button" class="button pick rpf-pick">Image</button><input type="text" class="rpf-url2" name="rpf_cv_sw[]" placeholder="Swatch image/#hex"><button type="button" class="button pick rpf-pick2">Swatch</button><a class="del rpf-del">✕</a></div>'; }
			return '<div class="row"><input type="text" class="rpf-url" name="rpf_zz_media[]" placeholder="Image/video URL"><button type="button" class="button pick rpf-pick">Media</button><input type="text" name="rpf_zz_title[]" placeholder="Heading"><textarea name="rpf_zz_text[]" rows="2" placeholder="Text"></textarea><input type="text" name="rpf_zz_btn[]" placeholder="Button label"><input type="text" name="rpf_zz_url[]" placeholder="/url"><a class="del rpf-del">✕</a></div>';
		}
		document.querySelectorAll( '.rpf-add' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () {
				var rep = document.getElementById( b.dataset.rep );
				var d = document.createElement( 'div' ); d.innerHTML = tpl( b.dataset.tpl ); rep.appendChild( d.firstChild );
			} );
		} );
		document.addEventListener( 'click', function ( e ) {
			if ( e.target.classList.contains( 'rpf-del' ) ) { e.preventDefault(); var r = e.target.closest( '.row' ); if ( r ) { r.remove(); } }
			var pickCls = e.target.classList.contains( 'rpf-pick' ) ? '.rpf-url' : ( e.target.classList.contains( 'rpf-pick2' ) ? '.rpf-url2' : '' );
			if ( pickCls && window.wp && wp.media ) {
				e.preventDefault();
				var row = e.target.closest( '.row' );
				var frame = wp.media( { title: 'Select', multiple: false } );
				frame.on( 'select', function () { var a = frame.state().get( 'selection' ).first().toJSON(); var inp = row.querySelector( pickCls ); if ( inp ) { inp.value = a.url; } } );
				frame.open();
			}
		} );
	} )();
	</script>
	<?php
}

function ricoman_pf_cv_row( $r ) {
	$r += array( '', '', '' );
	return '<div class="row"><input type="text" name="rpf_cv_name[]" placeholder="Colour (e.g. Black)" value="' . esc_attr( $r[0] ) . '"><input type="text" class="rpf-url" name="rpf_cv_img[]" placeholder="Main image URL" value="' . esc_attr( $r[1] ) . '"><button type="button" class="button pick rpf-pick">Image</button><input type="text" class="rpf-url2" name="rpf_cv_sw[]" placeholder="Swatch image/#hex" value="' . esc_attr( $r[2] ) . '"><button type="button" class="button pick rpf-pick2">Swatch</button><a class="del rpf-del">✕</a></div>';
}
function ricoman_pf_zz_row( $r ) {
	$r += array( '', '', '', '', '' );
	return '<div class="row"><input type="text" class="rpf-url" name="rpf_zz_media[]" placeholder="Image/video URL" value="' . esc_attr( $r[0] ) . '"><button type="button" class="button pick rpf-pick">Media</button><input type="text" name="rpf_zz_title[]" placeholder="Heading" value="' . esc_attr( $r[1] ) . '"><textarea name="rpf_zz_text[]" rows="2" placeholder="Text">' . esc_textarea( $r[2] ) . '</textarea><input type="text" name="rpf_zz_btn[]" placeholder="Button label" value="' . esc_attr( $r[3] ) . '"><input type="text" name="rpf_zz_url[]" placeholder="/url" value="' . esc_attr( $r[4] ) . '"><a class="del rpf-del">✕</a></div>';
}

/* ----------------------------------------------------------------- save */
add_action( 'save_post_product', function ( $post_id ) {
	if ( ! isset( $_POST['ricoman_pf_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ricoman_pf_nonce'] ), 'ricoman_pf_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( '_ricoman_subname', '_ricoman_ld_btn', '_ricoman_trade_btn' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	foreach ( array( '_ricoman_ld_url', '_ricoman_trade_url' ) as $k ) {
		if ( isset( $_POST[ $k ] ) ) {
			update_post_meta( $post_id, $k, esc_url_raw( wp_unslash( $_POST[ $k ] ) ) );
		}
	}
	if ( isset( $_POST['_ricoman_paragraphs'] ) ) {
		update_post_meta( $post_id, '_ricoman_paragraphs', sanitize_textarea_field( wp_unslash( $_POST['_ricoman_paragraphs'] ) ) );
	}
	// Colour variants.
	if ( isset( $_POST['rpf_cv_name'] ) ) {
		$names = (array) wp_unslash( $_POST['rpf_cv_name'] );
		$imgs  = isset( $_POST['rpf_cv_img'] ) ? (array) wp_unslash( $_POST['rpf_cv_img'] ) : array();
		$sws   = isset( $_POST['rpf_cv_sw'] ) ? (array) wp_unslash( $_POST['rpf_cv_sw'] ) : array();
		$rows  = array();
		foreach ( $names as $i => $n ) {
			$n = sanitize_text_field( $n );
			if ( '' === $n && empty( $imgs[ $i ] ) ) {
				continue;
			}
			$rows[] = $n . ' | ' . esc_url_raw( isset( $imgs[ $i ] ) ? $imgs[ $i ] : '' ) . ' | ' . sanitize_text_field( isset( $sws[ $i ] ) ? $sws[ $i ] : '' );
		}
		update_post_meta( $post_id, '_ricoman_colour_variants', implode( "\n", $rows ) );
	}
	// Zig-zag.
	if ( isset( $_POST['rpf_zz_media'] ) ) {
		$media = (array) wp_unslash( $_POST['rpf_zz_media'] );
		$tt    = isset( $_POST['rpf_zz_title'] ) ? (array) wp_unslash( $_POST['rpf_zz_title'] ) : array();
		$tx    = isset( $_POST['rpf_zz_text'] ) ? (array) wp_unslash( $_POST['rpf_zz_text'] ) : array();
		$bt    = isset( $_POST['rpf_zz_btn'] ) ? (array) wp_unslash( $_POST['rpf_zz_btn'] ) : array();
		$bu    = isset( $_POST['rpf_zz_url'] ) ? (array) wp_unslash( $_POST['rpf_zz_url'] ) : array();
		$rows  = array();
		foreach ( $media as $i => $u ) {
			$title = isset( $tt[ $i ] ) ? sanitize_text_field( $tt[ $i ] ) : '';
			if ( '' === $u && '' === $title ) {
				continue;
			}
			$rows[] = esc_url_raw( $u ) . ' | ' . $title . ' | ' . sanitize_text_field( isset( $tx[ $i ] ) ? $tx[ $i ] : '' ) . ' | ' . sanitize_text_field( isset( $bt[ $i ] ) ? $bt[ $i ] : '' ) . ' | ' . esc_url_raw( isset( $bu[ $i ] ) ? $bu[ $i ] : '' );
		}
		update_post_meta( $post_id, '_ricoman_zigzag', implode( "\n", $rows ) );
	}
} );

/* ------------------------------------------------- front-end render shortcodes */

/** Colour variants — main image + swatches that swap it (rendered from fields). */
add_shortcode( 'ricoman_colour_variants', function () {
	$rows = ricoman_pf_lines( get_the_ID(), '_ricoman_colour_variants' );
	if ( ! $rows ) {
		return '';
	}
	$first = $rows[0] + array( '', '', '' );
	$out   = '<div class="rm-cv"><div class="rm-cv-main"><img class="rm-cv-img" src="' . esc_url( $first[1] ) . '" alt="" loading="lazy"></div><div class="rm-cv-swatches">';
	foreach ( $rows as $i => $r ) {
		$r     = $r + array( '', '', '' );
		$sw    = $r[2];
		$style = ( $sw && '#' === substr( $sw, 0, 1 ) ) ? 'background:' . esc_attr( $sw ) : ( $sw ? 'background-image:url(' . esc_url( $sw ) . ')' : '' );
		$out  .= '<button type="button" class="rm-cv-sw' . ( 0 === $i ? ' on' : '' ) . '" data-img="' . esc_url( $r[1] ) . '" style="' . $style . '" aria-label="' . esc_attr( $r[0] ) . '"><span>' . esc_html( $r[0] ) . '</span></button>';
	}
	$out .= '</div></div>';
	$out .= '<script>(function(){var w=document.currentScript.previousElementSibling,im=w.querySelector(".rm-cv-img");w.querySelectorAll(".rm-cv-sw").forEach(function(b){b.addEventListener("click",function(){if(b.dataset.img){im.src=b.dataset.img;}w.querySelectorAll(".rm-cv-sw").forEach(function(x){x.classList.remove("on");});b.classList.add("on");});});})();</script>';
	return $out;
} );

/** Paragraph info — one key point per line, as a clean list. */
add_shortcode( 'ricoman_paragraphs', function () {
	$rows = ricoman_pf_lines( get_the_ID(), '_ricoman_paragraphs' );
	if ( ! $rows ) {
		return '';
	}
	$out = '<div class="rm-paras">';
	foreach ( $rows as $r ) {
		$out .= '<p>' . esc_html( implode( ' | ', $r ) ) . '</p>';
	}
	return $out . '</div>';
} );

/** Zig-zag content sections — alternating image/text, in the site design. */
add_shortcode( 'ricoman_zigzag', function () {
	$rows = ricoman_pf_lines( get_the_ID(), '_ricoman_zigzag' );
	if ( ! $rows ) {
		return '';
	}
	$out = '<div class="rm-zz">';
	foreach ( $rows as $i => $r ) {
		$r     = $r + array( '', '', '', '', '' );
		$isvid = preg_match( '/\.(mp4|webm)(\?|$)/i', $r[0] );
		$media = $r[0] ? ( $isvid ? '<video controls playsinline src="' . esc_url( $r[0] ) . '"></video>' : '<img src="' . esc_url( $r[0] ) . '" alt="' . esc_attr( $r[1] ) . '" loading="lazy">' ) : '';
		$btn   = ( '' !== trim( $r[3] ) ) ? '<a class="btn btn-line-d" href="' . esc_url( $r[4] ? $r[4] : '#' ) . '">' . esc_html( $r[3] ) . '</a>' : '';
		$out  .= '<div class="rm-zz-row' . ( 0 === $i % 2 ? '' : ' rev' ) . '"><div class="rm-zz-media">' . $media . '</div><div class="rm-zz-body">'
			. ( $r[1] ? '<h3>' . esc_html( $r[1] ) . '</h3>' : '' )
			. ( $r[2] ? '<p>' . esc_html( $r[2] ) . '</p>' : '' )
			. $btn . '</div></div>';
	}
	return $out . '</div>';
} );

/** The two CTA buttons (Lighting Design + Trade), from fields. */
add_shortcode( 'ricoman_cta_buttons', function () {
	$pid = get_the_ID();
	$ld  = get_post_meta( $pid, '_ricoman_ld_btn', true );
	$tr  = get_post_meta( $pid, '_ricoman_trade_btn', true );
	$out = '';
	if ( '' !== trim( (string) $ld ) ) {
		$out .= '<a class="btn btn-solid" href="' . esc_url( get_post_meta( $pid, '_ricoman_ld_url', true ) ?: '/lighting-design/' ) . '">' . esc_html( $ld ) . '</a> ';
	}
	if ( '' !== trim( (string) $tr ) ) {
		$out .= '<a class="btn btn-line-d" href="' . esc_url( get_post_meta( $pid, '_ricoman_trade_url', true ) ?: '/contact/' ) . '">' . esc_html( $tr ) . '</a>';
	}
	return $out ? '<div class="rm-cta-buttons">' . $out . '</div>' : '';
} );

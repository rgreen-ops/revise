<?php
/**
 * Home hero slider — a homepage hero carousel like the old site. Each slide can
 * be an IMAGE or a VIDEO background, with its own eyebrow, heading, text and two
 * buttons. Managed from Ricoman → Home Slider; rendered with [ricoman_home_slider]
 * (or the "Home · Hero slider" pattern). Slides are seeded once from the migrated
 * `home_slider` content if present, otherwise from the theme's current hero.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Is a media URL a video? (by extension) */
function ricoman_slide_is_video( $url ) {
	$ext = strtolower( pathinfo( (string) wp_parse_url( (string) $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
	return in_array( $ext, array( 'mp4', 'webm', 'ogg', 'mov', 'm4v' ), true );
}

/** The default slide (the theme's current video hero). */
function ricoman_home_slides_default() {
	return array(
		array(
			'type'       => 'video',
			'media'      => get_theme_file_uri( 'assets/videos/hero-banner.mp4' ),
			'poster'     => get_theme_file_uri( 'assets/images/hero-poster.webp' ),
			'eyebrow'    => 'Commercial Interior Lighting · Made in Britain',
			'heading'    => 'Light that transforms how a space feels.',
			'text'       => 'We’re a British manufacturer obsessed with getting light right — designing and making commercial luminaires in Manchester for the architects, designers and specifiers who shape great spaces.',
			'btn1_label' => 'Explore Products',
			'btn1_url'   => '/products/',
			'btn2_label' => 'Free Scheme Design',
			'btn2_url'   => '/lighting-design/',
			'dim'        => 55,
		),
	);
}

/** Seed slides from the migrated `home_slider` posts, if that content exists. */
function ricoman_home_slides_from_migrated() {
	if ( ! post_type_exists( 'home_slider' ) ) {
		return array();
	}
	$posts = get_posts( array(
		'post_type'   => 'home_slider',
		'post_status' => 'publish',
		'numberposts' => 12,
		'orderby'     => 'menu_order date',
		'order'       => 'ASC',
	) );
	$slides = array();
	foreach ( $posts as $p ) {
		// Background: featured image, else any ACF image/video-ish field.
		$media = get_the_post_thumbnail_url( $p->ID, 'full' );
		if ( ! $media && function_exists( 'get_field' ) ) {
			foreach ( array( 'image', 'background', 'slide_image', 'video', 'background_image' ) as $fk ) {
				$v = get_field( $fk, $p->ID );
				if ( is_array( $v ) && ! empty( $v['url'] ) ) {
					$v = $v['url'];
				}
				if ( is_string( $v ) && '' !== $v ) {
					$media = $v;
					break;
				}
			}
		}
		$heading = $p->post_title;
		$text    = trim( wp_strip_all_tags( $p->post_content ) );
		$slides[] = array(
			'type'       => ricoman_slide_is_video( $media ) ? 'video' : 'image',
			'media'      => (string) $media,
			'poster'     => '',
			'eyebrow'    => '',
			'heading'    => $heading,
			'text'       => $text,
			'btn1_label' => '',
			'btn1_url'   => '',
			'btn2_label' => '',
			'btn2_url'   => '',
			'dim'        => 50,
		);
	}
	return $slides;
}

/** The saved slides (seeded once on first use). */
function ricoman_home_slides() {
	$s = get_option( 'ricoman_home_slides', null );
	if ( ! is_array( $s ) ) {
		$s = ricoman_home_slides_from_migrated();
		if ( ! $s ) {
			$s = ricoman_home_slides_default();
		}
		update_option( 'ricoman_home_slides', $s, false );
	}
	return $s;
}

/* ----------------------------------------------------------- front render */

/** Render the home hero slider. */
function ricoman_home_slider_render() {
	$slides = array_values( array_filter( ricoman_home_slides(), function ( $s ) {
		return ! empty( $s['heading'] ) || ! empty( $s['media'] );
	} ) );
	if ( ! $slides ) {
		return '';
	}
	$multi = count( $slides ) > 1;
	ob_start();
	?>
	<div class="rm-hslider<?php echo $multi ? ' rm-hslider--multi' : ''; ?>" data-autoplay="6000" aria-roledescription="carousel">
		<div class="rm-hslider-track">
			<?php foreach ( $slides as $i => $s ) :
				$type = isset( $s['type'] ) ? $s['type'] : ( ricoman_slide_is_video( $s['media'] ) ? 'video' : 'image' );
				$dim  = isset( $s['dim'] ) ? max( 0, min( 80, (int) $s['dim'] ) ) : 50; ?>
				<div class="rm-hslide<?php echo 0 === $i ? ' is-active' : ''; ?>" role="group" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $i + 1 ) . ' / ' . count( $slides ) ); ?>"<?php echo 0 === $i ? '' : ' aria-hidden="true"'; ?>>
					<?php if ( 'video' === $type && ! empty( $s['media'] ) ) : ?>
						<video class="rm-hslide-bg" autoplay muted loop playsinline preload="metadata"<?php echo ! empty( $s['poster'] ) ? ' poster="' . esc_url( $s['poster'] ) . '"' : ''; ?> src="<?php echo esc_url( $s['media'] ); ?>"></video>
					<?php elseif ( ! empty( $s['media'] ) ) : ?>
						<img class="rm-hslide-bg" src="<?php echo esc_url( $s['media'] ); ?>" alt="" loading="<?php echo 0 === $i ? 'eager' : 'lazy'; ?>">
					<?php endif; ?>
					<span class="rm-hslide-dim" style="opacity:<?php echo esc_attr( $dim / 100 ); ?>"></span>
					<div class="rm-hslide-inner">
						<?php if ( ! empty( $s['eyebrow'] ) ) : ?><p class="rm-eyebrow"><?php echo esc_html( $s['eyebrow'] ); ?></p><?php endif; ?>
						<h1 class="rm-hslide-h"><?php echo esc_html( $s['heading'] ); ?></h1>
						<?php if ( ! empty( $s['text'] ) ) : ?><p class="rm-hslide-text"><?php echo esc_html( $s['text'] ); ?></p><?php endif; ?>
						<div class="rm-hslide-btns">
							<?php if ( ! empty( $s['btn1_label'] ) ) : ?><a class="btn btn-line" href="<?php echo esc_url( $s['btn1_url'] ); ?>"><?php echo esc_html( $s['btn1_label'] ); ?></a><?php endif; ?>
							<?php if ( ! empty( $s['btn2_label'] ) ) : ?><a class="btn btn-line" href="<?php echo esc_url( $s['btn2_url'] ); ?>"><?php echo esc_html( $s['btn2_label'] ); ?></a><?php endif; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( $multi ) : ?>
			<button type="button" class="rm-hslider-arrow rm-hslider-prev" aria-label="<?php esc_attr_e( 'Previous slide', 'ricoman' ); ?>">&lsaquo;</button>
			<button type="button" class="rm-hslider-arrow rm-hslider-next" aria-label="<?php esc_attr_e( 'Next slide', 'ricoman' ); ?>">&rsaquo;</button>
			<div class="rm-hslider-dots">
				<?php foreach ( $slides as $i => $s ) : ?>
					<button type="button" class="rm-hsdot<?php echo 0 === $i ? ' is-active' : ''; ?>" data-i="<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d slide number */ __( 'Go to slide %d', 'ricoman' ), $i + 1 ) ); ?>"></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
	ricoman_home_slider_assets();
	return ob_get_clean();
}
add_shortcode( 'ricoman_home_slider', 'ricoman_home_slider_render' );

/** Inline CSS + JS for the slider (printed once). */
function ricoman_home_slider_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
	<style>
	.rm-hslider{position:relative;width:100%;min-height:82vh;overflow:hidden;background:#16161a}
	.rm-hslider-track{position:relative;width:100%;height:100%;min-height:82vh}
	.rm-hslide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .8s ease;display:flex;align-items:flex-end}
	.rm-hslide.is-active{opacity:1;visibility:visible;position:relative}
	.rm-hslide-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
	.rm-hslide-dim{position:absolute;inset:0;background:#16161a}
	.rm-hslide-inner{position:relative;z-index:2;max-width:1180px;margin:0 auto;width:100%;padding:0 24px 8vh;color:#fff}
	.rm-hslide .rm-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.8rem;font-weight:700;opacity:.85;margin:0 0 .6em}
	.rm-hslide-h{font-size:clamp(2.6rem,7vw,6rem);font-weight:500;line-height:.98;margin:0 0 .3em;color:#fff}
	.rm-hslide-text{font-size:1.1rem;line-height:1.55;max-width:60ch;opacity:.92;margin:0 0 1.4em}
	.rm-hslide-btns{display:flex;gap:12px;flex-wrap:wrap}
	.rm-hslide-btns .btn{display:inline-block;padding:13px 26px;border:1.5px solid rgba(255,255,255,.8);border-radius:6px;color:#fff;font-weight:600;text-decoration:none;transition:.2s}
	.rm-hslide-btns .btn:hover{background:#fff;color:#16161a}
	.rm-hslider-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:46px;height:46px;border:0;border-radius:50%;background:rgba(0,0,0,.35);color:#fff;font-size:26px;line-height:1;cursor:pointer;transition:.2s}
	.rm-hslider-arrow:hover{background:rgba(0,0,0,.6)}
	.rm-hslider-prev{left:18px}.rm-hslider-next{right:18px}
	.rm-hslider-dots{position:absolute;left:0;right:0;bottom:22px;z-index:3;display:flex;justify-content:center;gap:10px}
	.rm-hsdot{width:11px;height:11px;border-radius:50%;border:0;background:rgba(255,255,255,.45);cursor:pointer;padding:0;transition:.2s}
	.rm-hsdot.is-active{background:#fff;transform:scale(1.15)}
	@media(max-width:782px){.rm-hslider,.rm-hslider-track{min-height:68vh}.rm-hslide-inner{padding-bottom:10vh}.rm-hslider-arrow{display:none}}
	</style>
	<script>
	(function(){
		function init(s){
			var slides=[].slice.call(s.querySelectorAll('.rm-hslide'));
			if(slides.length<2)return;
			var dots=[].slice.call(s.querySelectorAll('.rm-hsdot')),cur=0,timer=null,
			    delay=parseInt(s.getAttribute('data-autoplay'),10)||6000;
			function show(n){
				n=(n+slides.length)%slides.length;
				slides[cur].classList.remove('is-active');if(dots[cur])dots[cur].classList.remove('is-active');
				slides[cur].setAttribute('aria-hidden','true');
				cur=n;
				slides[cur].classList.add('is-active');if(dots[cur])dots[cur].classList.add('is-active');
				slides[cur].removeAttribute('aria-hidden');
			}
			function next(){show(cur+1);}function prev(){show(cur-1);}
			function play(){stop();timer=setInterval(next,delay);}function stop(){if(timer)clearInterval(timer);}
			var pn=s.querySelector('.rm-hslider-next'),pp=s.querySelector('.rm-hslider-prev');
			if(pn)pn.addEventListener('click',function(){next();play();});
			if(pp)pp.addEventListener('click',function(){prev();play();});
			dots.forEach(function(d){d.addEventListener('click',function(){show(parseInt(d.getAttribute('data-i'),10));play();});});
			s.addEventListener('mouseenter',stop);s.addEventListener('mouseleave',play);
			// swipe
			var x0=null;
			s.addEventListener('touchstart',function(e){x0=e.touches[0].clientX;},{passive:true});
			s.addEventListener('touchend',function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40){dx<0?next():prev();play();}x0=null;});
			play();
		}
		function boot(){document.querySelectorAll('.rm-hslider--multi').forEach(init);}
		if(document.readyState!=='loading')boot();else document.addEventListener('DOMContentLoaded',boot);
	})();
	</script>
	<?php
}

/* --------------------------------------------------------------- admin UI */

add_action( 'admin_menu', function () {
	add_submenu_page( 'ricoman-hub', __( 'Home Slider', 'ricoman' ), __( 'Home Slider', 'ricoman' ), 'edit_theme_options', 'ricoman-home-slider', 'ricoman_home_slider_admin' );
}, 12 );

add_action( 'admin_post_ricoman_home_slider_save', function () {
	if ( ! current_user_can( 'edit_theme_options' ) || ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'ricoman_home_slider' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'ricoman' ) );
	}
	$rows = isset( $_POST['s'] ) && is_array( $_POST['s'] ) ? wp_unslash( $_POST['s'] ) : array();
	$out  = array();
	foreach ( $rows as $r ) {
		$media   = isset( $r['media'] ) ? esc_url_raw( trim( $r['media'] ) ) : '';
		$heading = isset( $r['heading'] ) ? sanitize_text_field( $r['heading'] ) : '';
		if ( '' === $media && '' === trim( $heading ) ) {
			continue;
		}
		$out[] = array(
			'type'       => ricoman_slide_is_video( $media ) ? 'video' : 'image',
			'media'      => $media,
			'poster'     => isset( $r['poster'] ) ? esc_url_raw( trim( $r['poster'] ) ) : '',
			'eyebrow'    => isset( $r['eyebrow'] ) ? sanitize_text_field( $r['eyebrow'] ) : '',
			'heading'    => $heading,
			'text'       => isset( $r['text'] ) ? sanitize_textarea_field( $r['text'] ) : '',
			'btn1_label' => isset( $r['btn1_label'] ) ? sanitize_text_field( $r['btn1_label'] ) : '',
			'btn1_url'   => isset( $r['btn1_url'] ) ? esc_url_raw( trim( $r['btn1_url'] ), array( 'http', 'https', 'mailto', 'tel' ) ) : '',
			'btn2_label' => isset( $r['btn2_label'] ) ? sanitize_text_field( $r['btn2_label'] ) : '',
			'btn2_url'   => isset( $r['btn2_url'] ) ? esc_url_raw( trim( $r['btn2_url'] ), array( 'http', 'https', 'mailto', 'tel' ) ) : '',
			'dim'        => isset( $r['dim'] ) ? max( 0, min( 80, (int) $r['dim'] ) ) : 50,
		);
	}
	update_option( 'ricoman_home_slides', $out, false );
	if ( function_exists( 'ricoman_pagecache_flush' ) ) {
		ricoman_pagecache_flush();
	}
	wp_safe_redirect( add_query_arg( array( 'page' => 'ricoman-home-slider', 'saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function ricoman_home_slider_admin() {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}
	wp_enqueue_media();
	$slides = ricoman_home_slides();
	$home   = get_page_by_path( 'home' );
	$on_home = false;
	if ( $home ) {
		$on_home = false !== strpos( (string) $home->post_content, 'ricoman_home_slider' );
	}
	?>
	<div class="wrap rm-hsl-admin">
		<h1><?php esc_html_e( 'Home Slider', 'ricoman' ); ?></h1>
		<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Slider saved.', 'ricoman' ); ?></p></div><?php endif; ?>
		<p class="description" style="max-width:760px"><?php esc_html_e( 'Build your homepage hero carousel. Each slide can be an image or a video background, with its own heading, text and buttons. Reorder with the arrows; drop a slide by clearing its heading and media.', 'ricoman' ); ?></p>

		<?php if ( ! $on_home ) : ?>
			<div class="notice notice-warning"><p><?php
				printf(
					/* translators: %s shortcode */
					esc_html__( 'To show this on the homepage, add the %s block/shortcode at the very top of the Home page (replacing the current hero). Edit the Home page, add a Shortcode block and paste it.', 'ricoman' ),
					'<code>[ricoman_home_slider]</code>'
				);
				?> <a href="<?php echo esc_url( $home ? get_edit_post_link( $home->ID ) : admin_url( 'edit.php?post_type=page' ) ); ?>"><?php esc_html_e( 'Edit Home page →', 'ricoman' ); ?></a></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ricoman_home_slider_save">
			<?php wp_nonce_field( 'ricoman_home_slider' ); ?>
			<div id="rm-hsl-list">
				<?php foreach ( $slides as $i => $s ) {
					ricoman_home_slider_row( $i, $s );
				} ?>
			</div>
			<p><button type="button" class="button" id="rm-hsl-add">＋ <?php esc_html_e( 'Add slide', 'ricoman' ); ?></button></p>
			<?php submit_button( __( 'Save slider', 'ricoman' ) ); ?>
		</form>

		<script type="text/template" id="rm-hsl-tpl"><?php ricoman_home_slider_row( '__i__', array() ); ?></script>
		<style>
		.rm-hsl-slide{border:1px solid #dcdce0;border-radius:8px;background:#fff;padding:14px 16px;margin:0 0 14px;display:flex;gap:18px;flex-wrap:wrap}
		.rm-hsl-media{flex:0 0 230px}
		.rm-hsl-fields{flex:1;min-width:300px;display:grid;gap:8px}
		.rm-hsl-prev{width:100%;height:128px;background:#f0f1f4 center/cover no-repeat;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888;font-size:12px;overflow:hidden}
		.rm-hsl-prev video,.rm-hsl-prev img{width:100%;height:100%;object-fit:cover}
		.rm-hsl-fields label{font-size:12px;font-weight:600;color:#555;display:block}
		.rm-hsl-fields input[type=text],.rm-hsl-fields textarea{width:100%}
		.rm-hsl-2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
		.rm-hsl-head{display:flex;justify-content:space-between;align-items:center;width:100%;margin:-4px 0 4px}
		.rm-hsl-move button,.rm-hsl-del{cursor:pointer}
		</style>
		<script>
		jQuery(function($){
			var idx=$('#rm-hsl-list .rm-hsl-slide').length;
			$('#rm-hsl-add').on('click',function(){
				var html=$('#rm-hsl-tpl').html().replace(/__i__/g,idx++);
				$('#rm-hsl-list').append(html);
			});
			$('#rm-hsl-list').on('click','.rm-hsl-del',function(){ $(this).closest('.rm-hsl-slide').remove(); });
			$('#rm-hsl-list').on('click','.rm-hsl-up',function(){ var r=$(this).closest('.rm-hsl-slide'),p=r.prev('.rm-hsl-slide'); if(p.length)r.insertBefore(p); });
			$('#rm-hsl-list').on('click','.rm-hsl-dn',function(){ var r=$(this).closest('.rm-hsl-slide'),n=r.next('.rm-hsl-slide'); if(n.length)r.insertAfter(n); });
			$('#rm-hsl-list').on('click','.rm-hsl-pick',function(e){
				e.preventDefault();
				var $row=$(this).closest('.rm-hsl-slide'),target=$(this).data('target');
				var frame=wp.media({title:'Choose image or video',library:{type:['image','video']},multiple:false});
				frame.on('select',function(){
					var a=frame.state().get('selection').first().toJSON();
					$row.find('input[data-f="'+target+'"]').val(a.url);
					if(target==='media'){
						var prev=$row.find('.rm-hsl-prev');
						if((a.mime||'').indexOf('video')===0){ prev.html('<video src="'+a.url+'" muted></video>'); }
						else { prev.html('<img src="'+(a.url)+'">'); }
					}
				});
				frame.open();
			});
		});
		</script>
	</div>
	<?php
}

/** One slide row (also used as the JS template with __i__). */
function ricoman_home_slider_row( $i, $s ) {
	$g = function ( $k, $d = '' ) use ( $s ) {
		return isset( $s[ $k ] ) ? $s[ $k ] : $d;
	};
	$media = $g( 'media' );
	$prev  = '';
	if ( $media ) {
		$prev = ricoman_slide_is_video( $media )
			? '<video src="' . esc_url( $media ) . '" muted></video>'
			: '<img src="' . esc_url( $media ) . '">';
	}
	?>
	<div class="rm-hsl-slide">
		<div class="rm-hsl-head">
			<strong><?php esc_html_e( 'Slide', 'ricoman' ); ?></strong>
			<span class="rm-hsl-move">
				<button type="button" class="button-link rm-hsl-up" title="<?php esc_attr_e( 'Move up', 'ricoman' ); ?>">▲</button>
				<button type="button" class="button-link rm-hsl-dn" title="<?php esc_attr_e( 'Move down', 'ricoman' ); ?>">▼</button>
				<a class="rm-hsl-del" style="color:#b32d2e;text-decoration:none" title="<?php esc_attr_e( 'Remove', 'ricoman' ); ?>">✕</a>
			</span>
		</div>
		<div class="rm-hsl-media">
			<div class="rm-hsl-prev"><?php echo $prev ? $prev : esc_html__( 'No media', 'ricoman' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<p><button type="button" class="button rm-hsl-pick" data-target="media"><?php esc_html_e( 'Choose image / video', 'ricoman' ); ?></button></p>
			<input type="text" data-f="media" name="s[<?php echo esc_attr( $i ); ?>][media]" value="<?php echo esc_attr( $media ); ?>" placeholder="<?php esc_attr_e( 'media URL', 'ricoman' ); ?>">
			<label style="margin-top:6px"><?php esc_html_e( 'Darkness over media', 'ricoman' ); ?>
				<input type="range" min="0" max="80" name="s[<?php echo esc_attr( $i ); ?>][dim]" value="<?php echo esc_attr( $g( 'dim', 50 ) ); ?>" style="width:100%">
			</label>
			<p><button type="button" class="button-link rm-hsl-pick" data-target="poster"><?php esc_html_e( 'Video poster image (optional)', 'ricoman' ); ?></button></p>
			<input type="text" data-f="poster" name="s[<?php echo esc_attr( $i ); ?>][poster]" value="<?php echo esc_attr( $g( 'poster' ) ); ?>" placeholder="<?php esc_attr_e( 'poster URL (video only)', 'ricoman' ); ?>">
		</div>
		<div class="rm-hsl-fields">
			<label><?php esc_html_e( 'Eyebrow (small label)', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][eyebrow]" value="<?php echo esc_attr( $g( 'eyebrow' ) ); ?>"></label>
			<label><?php esc_html_e( 'Heading', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][heading]" value="<?php echo esc_attr( $g( 'heading' ) ); ?>"></label>
			<label><?php esc_html_e( 'Text', 'ricoman' ); ?><textarea name="s[<?php echo esc_attr( $i ); ?>][text]" rows="2"><?php echo esc_textarea( $g( 'text' ) ); ?></textarea></label>
			<div class="rm-hsl-2">
				<label><?php esc_html_e( 'Button 1 label', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][btn1_label]" value="<?php echo esc_attr( $g( 'btn1_label' ) ); ?>"></label>
				<label><?php esc_html_e( 'Button 1 link', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][btn1_url]" value="<?php echo esc_attr( $g( 'btn1_url' ) ); ?>" placeholder="/products/"></label>
				<label><?php esc_html_e( 'Button 2 label', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][btn2_label]" value="<?php echo esc_attr( $g( 'btn2_label' ) ); ?>"></label>
				<label><?php esc_html_e( 'Button 2 link', 'ricoman' ); ?><input type="text" name="s[<?php echo esc_attr( $i ); ?>][btn2_url]" value="<?php echo esc_attr( $g( 'btn2_url' ) ); ?>" placeholder="/lighting-design/"></label>
			</div>
		</div>
	</div>
	<?php
}

/** Register a "Home · Hero slider" pattern so it can be dropped onto the homepage. */
add_action( 'init', function () {
	if ( function_exists( 'register_block_pattern' ) ) {
		register_block_pattern( 'ricoman/home-hero-slider', array(
			'title'      => __( 'Home · Hero slider', 'ricoman' ),
			'categories' => array( 'ricoman-page' ),
			'content'    => '<!-- wp:shortcode -->[ricoman_home_slider]<!-- /wp:shortcode -->',
		) );
	}
}, 13 );

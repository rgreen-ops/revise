<?php
/**
 * Home hero slider — a homepage hero carousel built from editable Cover blocks.
 *
 * Drop the "Home · Hero slider" pattern onto the Home page (＋ → Patterns →
 * Ricoman — Page). Each slide is an ordinary **Cover block** whose background can
 * be an image OR a video (swap it in the editor toolbar), with its own eyebrow,
 * heading, text and buttons. Duplicate a Cover to add a slide; delete one to
 * remove it. In the editor the slides stack normally so they're easy to edit; on
 * the front end this script turns the stack into a carousel (dots, arrows,
 * autoplay with pause-on-hover, swipe).
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ------------------------------------------------- front-end styles + script */

add_action( 'wp_enqueue_scripts', 'ricoman_home_slider_assets' );
function ricoman_home_slider_assets() {
	$css = '.rm-hslider{position:relative;overflow:hidden;background:#16161a}'
		. '.rm-hslider>.wp-block-cover{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .8s ease}'
		. '.rm-hslider>.wp-block-cover.is-active{position:relative;opacity:1;visibility:visible}'
		. '.rm-hslider .wp-block-cover__inner-container{max-width:1180px;margin-left:auto;margin-right:auto;width:100%;padding:0 24px}'
		. '.rm-hslider .rm-eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:.8rem;font-weight:700;opacity:.9;margin:0 0 .6em}'
		. '.rm-hslider .rm-hslide-text{font-size:1.1rem;line-height:1.55;max-width:60ch;opacity:.94;margin:0 0 1.4em}'
		. '.rm-hslider .is-style-outline-light .wp-block-button__link{border:1.5px solid rgba(255,255,255,.8);color:#fff;background:transparent}'
		. '.rm-hslider .is-style-outline-light .wp-block-button__link:hover{background:#fff;color:#16161a}'
		. '.rm-hslider-arrow{position:absolute;top:50%;transform:translateY(-50%);z-index:4;width:46px;height:46px;border:0;border-radius:50%;background:rgba(0,0,0,.35);color:#fff;font-size:26px;line-height:1;cursor:pointer;transition:.2s}'
		. '.rm-hslider-arrow:hover{background:rgba(0,0,0,.6)}.rm-hslider-prev{left:18px}.rm-hslider-next{right:18px}'
		. '.rm-hslider-dots{position:absolute;left:0;right:0;bottom:22px;z-index:4;display:flex;justify-content:center;gap:10px}'
		. '.rm-hsdot{width:11px;height:11px;border-radius:50%;border:0;background:rgba(255,255,255,.45);cursor:pointer;padding:0;transition:.2s}'
		. '.rm-hsdot.is-active{background:#fff;transform:scale(1.15)}'
		. '@media(max-width:782px){.rm-hslider-arrow{display:none}}';
	$js = '(function(){'
		. 'function build(s){'
		. 'var k=[].slice.call(s.children).filter(function(c){return c.matches&&c.matches(".wp-block-cover");});'
		. 'if(k.length<2)return;'
		. 'k.forEach(function(x,i){if(i===0){x.classList.add("is-active");}else{x.setAttribute("aria-hidden","true");}});'
		. 'function mk(c,h,l){var b=document.createElement("button");b.type="button";b.className=c;b.setAttribute("aria-label",l);b.innerHTML=h;return b;}'
		. 'var prev=mk("rm-hslider-arrow rm-hslider-prev","&lsaquo;","Previous slide");'
		. 'var next=mk("rm-hslider-arrow rm-hslider-next","&rsaquo;","Next slide");'
		. 'var dots=document.createElement("div");dots.className="rm-hslider-dots";'
		. 'k.forEach(function(x,i){var d=mk("rm-hsdot"+(i===0?" is-active":""),"","Go to slide "+(i+1));d.setAttribute("data-i",i);dots.appendChild(d);});'
		. 's.appendChild(prev);s.appendChild(next);s.appendChild(dots);'
		. 'var de=[].slice.call(dots.children),cur=0,t=null,delay=parseInt(s.getAttribute("data-autoplay"),10)||6000;'
		. 'function show(n){n=(n+k.length)%k.length;k[cur].classList.remove("is-active");k[cur].setAttribute("aria-hidden","true");de[cur].classList.remove("is-active");cur=n;k[cur].classList.add("is-active");k[cur].removeAttribute("aria-hidden");de[cur].classList.add("is-active");}'
		. 'function nx(){show(cur+1);}function pv(){show(cur-1);}'
		. 'function play(){stop();t=setInterval(nx,delay);}function stop(){if(t){clearInterval(t);}}'
		. 'next.onclick=function(){nx();play();};prev.onclick=function(){pv();play();};'
		. 'de.forEach(function(d){d.onclick=function(){show(parseInt(d.getAttribute("data-i"),10));play();};});'
		. 's.addEventListener("mouseenter",stop);s.addEventListener("mouseleave",play);'
		. 'var x0=null;s.addEventListener("touchstart",function(e){x0=e.touches[0].clientX;},{passive:true});'
		. 's.addEventListener("touchend",function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40){dx<0?nx():pv();play();}x0=null;});'
		. 'play();}'
		. 'function boot(){[].slice.call(document.querySelectorAll(".rm-hslider")).forEach(build);}'
		. 'if(document.readyState!=="loading"){boot();}else{document.addEventListener("DOMContentLoaded",boot);}'
		. '})();';
	wp_register_style( 'ricoman-hslider', false );
	wp_enqueue_style( 'ricoman-hslider' );
	wp_add_inline_style( 'ricoman-hslider', $css );
	wp_register_script( 'ricoman-hslider', false, array(), null, true );
	wp_enqueue_script( 'ricoman-hslider' );
	wp_add_inline_script( 'ricoman-hslider', $js );
}

/* ------------------------------------------------------------------- pattern */

/** Register a "Home · Hero slider" pattern (editable Cover-block slides). */
add_action( 'init', function () {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	// Each slide is an editable Cover block (background = image OR video, swappable
	// in the editor). Duplicate a Cover to add a slide; delete one to remove it.
	$slide = function ( $img, $eyebrow, $heading, $text, $b1l, $b1u, $b2l, $b2u ) {
		$btns = '<!-- wp:buttons --><div class="wp-block-buttons">'
			. '<!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="' . $b1u . '">' . $b1l . '</a></div><!-- /wp:button -->'
			. ( $b2l ? '<!-- wp:button {"className":"is-style-outline-light"} --><div class="wp-block-button is-style-outline-light"><a class="wp-block-button__link wp-element-button" href="' . $b2u . '">' . $b2l . '</a></div><!-- /wp:button -->' : '' )
			. '</div><!-- /wp:buttons -->';
		return '<!-- wp:cover {"url":"' . esc_url( $img ) . '","dimRatio":50,"overlayColor":"ink","minHeight":80,"minHeightUnit":"vh","contentPosition":"bottom left","align":"full"} -->'
			. '<div class="wp-block-cover alignfull has-custom-content-position is-position-bottom-left" style="min-height:80vh">'
			. '<span aria-hidden="true" class="wp-block-cover__background has-ink-background-color has-background-dim-50 has-background-dim"></span>'
			. '<img class="wp-block-cover__image-background" alt="" src="' . esc_url( $img ) . '" data-object-fit="cover"/>'
			. '<div class="wp-block-cover__inner-container">'
			. '<!-- wp:paragraph {"className":"rm-eyebrow","textColor":"base"} --><p class="rm-eyebrow has-base-color has-text-color">' . $eyebrow . '</p><!-- /wp:paragraph -->'
			. '<!-- wp:heading {"level":1,"className":"rm-hslide-h","textColor":"base","style":{"typography":{"fontWeight":"500","fontSize":"clamp(2.6rem,7vw,6rem)","lineHeight":"0.98"}}} --><h1 class="wp-block-heading rm-hslide-h has-base-color has-text-color" style="font-size:clamp(2.6rem,7vw,6rem);font-weight:500;line-height:0.98">' . $heading . '</h1><!-- /wp:heading -->'
			. '<!-- wp:paragraph {"className":"rm-hslide-text","textColor":"base"} --><p class="rm-hslide-text has-base-color has-text-color">' . $text . '</p><!-- /wp:paragraph -->'
			. $btns
			. '</div></div><!-- /wp:cover -->';
	};
	$u       = function ( $f ) { return get_theme_file_uri( 'assets/images/' . $f ); };
	$content = '<!-- wp:group {"align":"full","className":"rm-hslider rm-hslider--multi"} --><div class="wp-block-group alignfull rm-hslider rm-hslider--multi" data-autoplay="6000">'
		. $slide( $u( 'warm-int.webp' ), 'Commercial Interior Lighting · Made in Britain', 'Light that transforms how a space feels.', 'We&rsquo;re a British manufacturer obsessed with getting light right &mdash; designing and making commercial luminaires in Manchester.', 'Explore Products', '/products/', 'Lighting Design', '/lighting-design/' )
		. $slide( $u( 'rico-betfred-flow.webp' ), 'Bespoke &amp; Curved Linear', 'Transforming spaces with lighting that inspires.', 'Seamless curved runs, statement features and made-to-order luminaires &mdash; designed with you and built in Britain.', 'See our work', '/projects/', '', '' )
		. $slide( $u( 'warehouse.webp' ), 'UK Stock &middot; ~6-Day Lead', 'Lighting that performs, and endures.', 'High-efficacy, long-life luminaires backed by in-house design, fast UK lead times and a 5-year warranty.', 'Browse products', '/products/', 'Talk to the team', '/contact/' )
		. '</div><!-- /wp:group -->';
	register_block_pattern( 'ricoman/home-hero-slider', array(
		'title'      => __( 'Home · Hero slider', 'ricoman' ),
		'categories' => array( 'ricoman-page' ),
		'content'    => $content,
	) );
}, 13 );

<?php
/**
 * Title: Projects listing
 * Slug: ricoman/projects-archive
 * Categories: ricoman, ricoman-pages
 * Description: Projects listing — hero, sector filter, case-study mosaic, CTA.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="phero">
  <div class="bg" style="background-image:url(<?php echo $img( 'office1.jpg' ); ?>)"></div><div class="scrim"></div>
  <div class="inner"><div class="wrap">
    <span class="kick lt">Selected Work</span>
    <h1>Light that performs in the real world.</h1>
    <p class="lede">From workplace fit-outs to flagship retail, our luminaires are specified, delivered and installed across the UK.</p>
  </div></div>
</section>

<div class="filter"><div class="wrap">
  <span class="chip on">All sectors</span><span class="chip">Workplace</span><span class="chip">Retail</span><span class="chip">Hospitality</span><span class="chip">Healthcare</span><span class="chip">Education</span><span class="chip">Leisure</span>
</div></div>

<section class="sec tight"><div class="wrap">
  <div class="mosaic">
    <a class="pj big" href="/projects/acoustic-ceiling/"><span class="tagp">Case study</span><img src="<?php echo $img( 'rico-acoustic-corridor.jpg' ); ?>" alt="Acoustic linear ceiling"><div class="ov"><small>Commercial Office · Manchester</small><h3>Acoustic linear ceiling</h3></div></a>
    <a class="pj wide" href="/projects/flagship-store/"><img src="<?php echo $img( 'retail.jpg' ); ?>" alt="Flagship store"><div class="ov"><small>Retail · Manchester</small><h3>Flagship Store</h3></div></a>
    <a class="pj" href="/projects/breakout-lounge/"><span class="tagp">Case study</span><img src="<?php echo $img( 'rico-breakout-lounge.jpg' ); ?>" alt="Breakout lounge"><div class="ov"><small>Workplace · Amenity</small><h3>Breakout lounge</h3></div></a>
    <a class="pj" href="/projects/boutique-hotel/"><img src="<?php echo $img( 'office2.jpg' ); ?>" alt="Boutique hotel"><div class="ov"><small>Hospitality</small><h3>Boutique Hotel</h3></div></a>
    <a class="pj" href="/projects/betfred-hq/"><img src="<?php echo $img( 'rico-betfred7.webp' ); ?>" alt="Betfred HQ"><div class="ov"><small>Workplace · Warrington</small><h3>Betfred HQ</h3></div></a>
    <a class="pj" href="/projects/kingsgate/"><img src="<?php echo $img( 'rico-kingsgate.png' ); ?>" alt="Kingsgate"><div class="ov"><small>Retail · London</small><h3>Kingsgate</h3></div></a>
    <a class="pj wide" href="/projects/estrella-canteen/"><img src="<?php echo $img( 'estrella-canteen.jpg' ); ?>" alt="Estrella canteen"><div class="ov"><small>Hospitality · Staff dining</small><h3>Estrella Canteen</h3></div></a>
    <a class="pj" href="/projects/campus-library/"><img src="<?php echo $img( 'office5.jpg' ); ?>" alt="Campus library"><div class="ov"><small>Education</small><h3>Campus Library</h3></div></a>
  </div>
</div></section>

<section class="cta"><div class="bg" style="background-image:url(<?php echo $img( 'office6.jpg' ); ?>)"></div><div class="scrim"></div><div class="inner"><div class="wrap col">
  <h2>Have a project on the board?</h2>
  <p>Send us your drawings or a finishes schedule and our in-house lighting designers will return a fully specified, costed scheme — usually within 3–5 days.</p>
  <div class="acts"><a class="btn btn-line" href="/lighting-design/">Start a Project</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="/about/">Talk to the design team →</a></div>
</div></div></section>
<!-- /wp:html -->

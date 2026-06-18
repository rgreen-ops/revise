<?php
/**
 * Title: Page — Manufacturing
 * Slug: ricoman/page-manufacturing
 * Categories: ricoman, ricoman-pages
 * Description: Made-in-Britain manufacturing story — stats, vertical integration, process, capabilities.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="phero">
  <div class="bg" style="background-image:url(<?php echo $img( 'workshop.webp' ); ?>)"></div><div class="scrim"></div>
  <div class="inner"><div class="wrap">
    <span class="kick lt">Made in Britain</span>
    <h1>Designed &amp; manufactured in Manchester.</h1>
    <p class="lede">We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail in-house.</p>
  </div></div>
</section>

<section class="statband"><div class="wrap">
  <div class="s"><div class="big">15,000<span style="font-size:.4em">ft²</span></div><small>Production area, Manchester HQ</small></div>
  <div class="s"><div class="big">2,000<span style="font-size:.4em">+</span></div><small>Components stocked, ready to build</small></div>
  <div class="s"><div class="big">~6 <span style="font-size:.4em">days</span></div><small>Average UK-made lead time</small></div>
  <div class="s"><div class="big">98<span style="font-size:.4em">%</span></div><small>On-time-in-full target</small></div>
</div></section>

<section class="sec statement"><div class="wrap col"><p>Vertical integration, end to end. <span>We make to order — not to a catalogue.</span></p></div></section>

<section class="split light">
  <div class="img" style="background-image:url(<?php echo $img( 'rico-making.webp' ); ?>)"></div>
  <div class="body">
    <span class="kick"><span class="n">01</span>In-house, end to end</span>
    <h2>One roof, full control</h2>
    <p>Design, electronics, assembly, finishing and testing all happen under one roof in Manchester — nothing outsourced to a supply chain we can't see.</p>
    <p>That means shorter lead times, full traceability on every batch, and the ability to make a fitting to your exact geometry.</p>
    <a class="lnk" href="/products/">See what we make →</a>
  </div>
</section>

<section class="sec"><div class="wrap">
  <div class="shead"><div><span class="kick"><span class="n">02</span>From component to luminaire</span><h2>How a Ricoman fitting is made</h2></div></div>
  <div class="steps">
    <div class="step"><span class="no">01</span><h3>Design &amp; tooling</h3><p>CAD, photometric modelling and tooling, in-house.</p></div>
    <div class="step"><span class="no">02</span><h3>Assembly</h3><p>Boards, optics and housings hand-built to order.</p></div>
    <div class="step"><span class="no">03</span><h3>Finishing</h3><p>Powder-coat &amp; bespoke finishes to your spec.</p></div>
    <div class="step"><span class="no">04</span><h3>Test &amp; despatch</h3><p>Batch burn-in, then delivered UK-wide.</p></div>
  </div>
</div></section>

<section class="split dark">
  <div class="body">
    <span class="kick lt"><span class="n">03</span>Bespoke as standard</span>
    <h2>If you can draw it, we can make it</h2>
    <p>Curved linear runs, custom lengths, special CCTs, brand-matched finishes — bespoke isn't a bolt-on, it's how the factory is built to work.</p>
    <a class="btn btn-line" href="/lighting-design/">Talk to our designers →</a>
  </div>
  <div class="img" style="background-image:url(<?php echo $img( 'warehouse.webp' ); ?>)"></div>
</section>

<section class="cta"><div class="bg" style="background-image:url(<?php echo $img( 'workshop.webp' ); ?>)"></div><div class="scrim"></div><div class="inner"><div class="wrap col">
  <h2>Want to see it for yourself?</h2>
  <p>Book a visit to the Manchester facility, or send us a project and let our team spec it end to end — designed, made and delivered in Britain.</p>
  <div class="acts"><a class="btn btn-line" href="/about/">Book a visit</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="/lighting-design/">Start a project →</a></div>
</div></div></section>
<!-- /wp:html -->

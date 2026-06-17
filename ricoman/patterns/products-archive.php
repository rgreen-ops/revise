<?php
/**
 * Title: Products listing
 * Slug: ricoman/products-archive
 * Categories: ricoman, ricoman-pages
 * Description: Products range listing — hero, filter rail, category groups, applications, CTA.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="phero">
  <div class="bg" style="background-image:url(<?php echo $img( 'arch-line.jpg' ); ?>)"></div><div class="scrim"></div>
  <div class="inner"><div class="wrap">
    <span class="kick lt">Our Lighting Range</span>
    <h1>Commercial luminaires, made to specify.</h1>
    <p class="lede">Over 500 interior fittings across linear, downlights, pendants, track and modular ranges — held in UK stock and made to order in Manchester.</p>
  </div></div>
</section>

<div class="filter"><div class="wrap">
  <span class="chip on">All ranges</span><span class="chip">Linear</span><span class="chip">Downlights</span><span class="chip">Pendants</span><span class="chip">Track &amp; Spot</span><span class="chip">Modular Recessed</span><span class="chip">Biophilic</span><span class="chip">Acoustic</span>
  <span class="chip" style="margin-left:auto;border-color:var(--blue);color:var(--blue)">＋ Request a price</span>
</div></div>

<section class="sec"><div class="wrap">
  <div class="catrow"><h2>Linear Lighting</h2><span class="ct">Continuous runs &amp; profile systems</span></div>
  <div class="pgrid">
    <a class="pc" href="/products/"><div class="ph"><span class="badge blue">Featured</span><img src="<?php echo $img( 'arch-line.jpg' ); ?>" alt="Flow+"></div><div class="body"><small>Linear · Made to order</small><h3>Flow+</h3><div class="spec"><span>Up to 180 lm/W</span><span>CRI 90+</span><span>Bendable</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'ceiling.jpg' ); ?>" alt="Edge 35"></div><div class="body"><small>Linear · Recessed</small><h3>Edge 35</h3><div class="spec"><span>Trimless</span><span>CCT switch</span><span>IP20</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'office5.jpg' ); ?>" alt="Line Pro"></div><div class="body"><small>Linear · Suspended</small><h3>Line Pro</h3><div class="spec"><span>Up-/down-light</span><span>DALI</span><span>1.5m / 2.4m</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'rico-acoustic-corridor.jpg' ); ?>" alt="Astrawave"></div><div class="body"><small>Acoustic · Linear</small><h3>Astrawave</h3><div class="spec"><span>Sound-absorbing</span><span>UGR&lt;19</span><span>Bespoke</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
  </div>

  <div class="catrow"><h2>Downlights &amp; Pendants</h2><span class="ct">Fire-rated, decorative &amp; configurable</span></div>
  <div class="pgrid">
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'ceiling.jpg' ); ?>" alt="Halo FR"></div><div class="body"><small>Downlight · Fire-rated</small><h3>Halo FR</h3><div class="spec"><span>90 min</span><span>IP65</span><span>Tri-CCT</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><span class="badge blue">Configurable</span><img src="<?php echo $img( 'estrella-lounge.webp' ); ?>" alt="Estrella"></div><div class="body"><small>Pendant · Configurable</small><h3>Estrella</h3><div class="spec"><span>Build to spec</span><span>Finishes</span><span>CRI 90+</span></div><div class="foot"><span class="price">Configure →</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'pendant.jpg' ); ?>" alt="Halo Ring"></div><div class="body"><small>Pendant · Architectural</small><h3>Halo Ring</h3><div class="spec"><span>Ø600–1200</span><span>Up/down</span><span>Bespoke</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
    <a class="pc" href="/products/"><div class="ph"><img src="<?php echo $img( 'rico-z62.webp' ); ?>" alt="Z62"></div><div class="body"><small>Pendant · Decorative</small><h3>Z62</h3><div class="spec"><span>Warm 2700K</span><span>Dimmable</span><span>Brass / Black</span></div><div class="foot"><span class="price">Request a price</span><span class="ar">→</span></div></div></a>
  </div>
</div></section>

<section class="sec apps"><div class="wrap">
  <div class="shead"><div><span class="kick">Shop by application</span><h2>Find the right light for the space</h2></div><a class="lnk" href="/projects/">See it in projects →</a></div>
  <div class="appgrid">
    <a class="app" href="/projects/"><img src="<?php echo $img( 'office1.jpg' ); ?>" alt="Workplace"><div class="ov"><h3>Workplace</h3><small>Offices · UGR&lt;19</small></div></a>
    <a class="app" href="/projects/"><img src="<?php echo $img( 'retail.jpg' ); ?>" alt="Retail"><div class="ov"><h3>Retail</h3><small>Accent · High CRI</small></div></a>
    <a class="app" href="/projects/"><img src="<?php echo $img( 'office2.jpg' ); ?>" alt="Hospitality"><div class="ov"><h3>Hospitality</h3><small>Warm · Dimmable</small></div></a>
  </div>
</div></section>

<section class="cta"><div class="bg" style="background-image:url(<?php echo $img( 'office1.jpg' ); ?>)"></div><div class="scrim"></div><div class="inner"><div class="wrap col">
  <h2>Can't find the exact fitting?</h2>
  <p>Send us a finishes schedule or a drawing and our in-house team will spec the range, beam and finish — and return a costed list, usually within 3–5 days.</p>
  <div class="acts"><a class="btn btn-line" href="/lighting-design/">Free Scheme Design</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="/about/">Talk to the team →</a></div>
</div></div></section>
<!-- /wp:html -->

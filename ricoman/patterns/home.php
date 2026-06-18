<?php
/**
 * Title: Home
 * Slug: ricoman/home
 * Categories: ricoman, ricoman-pages
 * Description: The full Ricoman homepage — hero, facts, ranges, featured, projects, manufacturing, audience, news and CTA.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="hero">
  <div class="bg" style="background-image:url(<?php echo $img( 'warm-int.jpg' ); ?>)"></div><div class="scrim"></div>
  <div class="inner">
    <div class="top"><div class="wrap"><span class="kick lt"><span class="n">01</span>Commercial Interior Lighting</span><span class="kick lt">Made in Britain</span></div></div>
    <div class="btm"><div class="wrap">
      <h1>Lighting that transforms commercial interiors.</h1>
      <div class="row">
        <p class="lede">Designed and manufactured in Manchester for architects, interior designers, design &amp; build teams and electrical contractors — on spec, on time, on budget.</p>
        <div class="acts"><a class="btn btn-line" href="/products/">Explore Products</a><a class="btn btn-line" href="/lighting-design/">Free Scheme Design ↓</a></div>
      </div>
    </div></div>
  </div>
</section>

<section class="facts"><div class="wrap">
  <div class="f"><small>Lead Time</small><b>UK-made, ~6-day average</b></div>
  <div class="f"><small>Last Year</small><b>535+ schemes designed</b></div>
  <div class="f"><small>In Stock</small><b>2,000+ components</b></div>
  <div class="f"><small>Partners</small><b>Strong local network</b></div>
</div></section>

<section class="sec statement"><div class="wrap col">
  <p>We design and deliver UK-made commercial lighting. <span>Bringing spaces to life — on spec, on time, and on budget.</span></p>
</div></section>

<section class="sec" style="padding-top:0"><div class="wrap">
  <div class="shead">
    <div><span class="kick"><span class="n">02</span>Our Lighting Range</span><h2>A luminaire for every commercial interior</h2></div>
    <a class="lnk" href="/products/">All products →</a>
  </div>
  <div class="range">
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'arch-line.jpg' ); ?>" alt="Linear lighting"></div><div class="meta"><h3>Linear Lighting</h3><span class="ar">→</span></div><div class="sub">Continuous runs &amp; profile systems</div></a>
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'ceiling.jpg' ); ?>" alt="Downlights"></div><div class="meta"><h3>Downlights</h3><span class="ar">→</span></div><div class="sub">Fire-rated, switchable CCT</div></a>
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'pendant.jpg' ); ?>" alt="Pendants"></div><div class="meta"><h3>Pendants</h3><span class="ar">→</span></div><div class="sub">Architectural &amp; decorative</div></a>
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'retail.jpg' ); ?>" alt="Track &amp; spotlights"></div><div class="meta"><h3>Track &amp; Spotlights</h3><span class="ar">→</span></div><div class="sub">Retail &amp; gallery accent</div></a>
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'warm-int.jpg' ); ?>" alt="Biophilic lighting"></div><div class="meta"><h3>Biophilic Lighting</h3><span class="ar">→</span></div><div class="sub">Human-centric, tunable</div></a>
    <a class="rcard" href="/products/"><div class="ph"><img src="<?php echo $img( 'office3.jpg' ); ?>" alt="Modular recessed"></div><div class="meta"><h3>Modular Recessed</h3><span class="ar">→</span></div><div class="sub">Offices, schools, healthcare</div></a>
  </div>
</div></section>

<section class="feat">
  <div class="body">
    <span class="kick lt"><span class="n">03</span>Featured · Linear</span>
    <h2>Flow+ — seamless curves of light</h2>
    <p>A flexible linear system that bends to any architectural line, continuous and dot-free. Made to order in Manchester, to your exact geometry.</p>
    <div class="acts"><a class="btn btn-line" href="/products/flow-plus/">View Flow+ →</a><a class="btn btn-line" href="/flow-designer/">Design your run</a></div>
  </div>
  <div class="img" style="background-image:url(<?php echo $img( 'ceiling.jpg' ); ?>)"></div>
</section>

<section class="sec projects"><div class="wrap">
  <div class="shead">
    <div><span class="kick"><span class="n">04</span>Selected Work</span><h2>Lighting that performs in the real world</h2></div>
    <a class="lnk" href="/projects/">All projects →</a>
  </div>
  <div class="grid">
    <a class="pj big" href="/projects/allianz-hq/"><img src="<?php echo $img( 'office1.jpg' ); ?>" alt="Workplace"><div class="ov"><small>Commercial Office · Leeds</small><h3>Allianz HQ Fit-out</h3></div></a>
    <a class="pj wide" href="/projects/flagship-store/"><img src="<?php echo $img( 'retail.jpg' ); ?>" alt="Retail"><div class="ov"><small>Retail · Manchester</small><h3>Flagship Store</h3></div></a>
    <a class="pj" href="/projects/studio-hq/"><img src="<?php echo $img( 'office6.jpg' ); ?>" alt="Workplace"><div class="ov"><small>Workplace</small><h3>Studio HQ</h3></div></a>
    <a class="pj" href="/projects/boutique-hotel/"><img src="<?php echo $img( 'office2.jpg' ); ?>" alt="Hospitality"><div class="ov"><small>Hospitality</small><h3>Boutique Hotel</h3></div></a>
  </div>
</div></section>

<section class="dark"><div class="britain">
  <div class="body">
    <span class="kick lt"><span class="n">05</span>Made in Britain</span>
    <h2>Designed &amp; manufactured in Manchester</h2>
    <p>We design, assemble, test and finish our luminaires in our own UK facility — controlling quality, lead times and every bespoke detail. With 2,000+ components stocked and ready to build, we make to order on an average six-day lead.</p>
    <p>Vertical integration and strong relationships with trusted local partners mean honest lead times, full traceability and the ability to make to order rather than to a catalogue.</p>
    <a class="btn btn-line" style="margin-top:.4em" href="/manufacturing/">Inside our manufacturing →</a>
  </div>
  <div class="img" style="background-image:url(<?php echo $img( 'workshop.jpg' ); ?>)"></div>
</div></section>

<section class="sec"><div class="wrap">
  <div class="shead"><div><span class="kick"><span class="n">06</span>Who We Work With</span><h2>A partner for the whole project team</h2></div></div>
  <div class="aud">
    <div class="acard"><span class="sn">01</span><h3>Architects</h3><p>Clean photometrics, full data sheets and BIM-ready files for precise specification.</p></div>
    <div class="acard"><span class="sn">02</span><h3>Interior Designers</h3><p>Warm, high-CRI light and decorative ranges that flatter materials and finishes.</p></div>
    <div class="acard"><span class="sn">03</span><h3>Design &amp; Build</h3><p>Value-engineered alternatives, budget certainty and stock to keep programmes moving.</p></div>
    <div class="acard"><span class="sn">04</span><h3>Electrical Contractors</h3><p>Fast quotes, reliable lead times and easy-install fittings that wire up first time.</p></div>
  </div>
</div></section>

<section class="cta"><div class="bg" style="background-image:url(<?php echo $img( 'office1.jpg' ); ?>)"></div><div class="scrim"></div><div class="inner"><div class="wrap col">
  <h2>Have a project on the board?</h2>
  <p>Send us your drawings or a finishes schedule and our in-house lighting designers will return a fully specified, costed scheme — usually within 3–5 days.</p>
  <div class="acts"><a class="btn btn-line" href="/lighting-design/">Start a Project</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="/about/">Talk to the design team →</a></div>
</div></div></section>
<!-- /wp:html -->

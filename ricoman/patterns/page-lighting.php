<?php
/**
 * Title: Page — Lighting Design
 * Slug: ricoman/page-lighting
 * Categories: ricoman, ricoman-pages
 * Description: Free scheme design service — process, what you receive, CTA.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="phero">
  <div class="bg" style="background-image:url(<?php echo $img( 'rico-office-render.webp' ); ?>)"></div><div class="scrim"></div>
  <div class="inner"><div class="wrap">
    <span class="kick lt">Free Scheme Design</span>
    <h1>Your scheme, fully designed — at no cost.</h1>
    <p class="lede">Send us a drawing or a finishes schedule and our in-house lighting designers return a fully specified, photometric-backed and costed scheme. Usually within 3–5 days.</p>
  </div></div>
</section>

<section class="sec statement"><div class="wrap col"><p>We don't just sell luminaires. <span>We design the light, prove it works, and cost it — before you commit a penny.</span></p></div></section>

<section class="sec" style="padding-top:0"><div class="wrap">
  <div class="shead"><div><span class="kick"><span class="n">01</span>How it works</span><h2>Four steps from drawing to delivered scheme</h2></div></div>
  <div class="steps">
    <div class="step"><span class="no">01</span><h3>Send your drawings</h3><p>A plan, RCP or sketch and a finishes schedule. PDF, DWG or photos.</p></div>
    <div class="step"><span class="no">02</span><h3>We design the light</h3><p>A DIALux photometric study — lux, uniformity, UGR and energy.</p></div>
    <div class="step"><span class="no">03</span><h3>Costed scheme in 3–5 days</h3><p>A specified luminaire schedule, layout and itemised costing.</p></div>
    <div class="step"><span class="no">04</span><h3>Made &amp; delivered</h3><p>Approved, made to order in Manchester, delivered to programme.</p></div>
  </div>
</div></section>

<section class="split dark">
  <div class="body">
    <span class="kick lt"><span class="n">02</span>What you receive</span>
    <h2>A scheme you can specify with confidence</h2>
    <ul class="ul">
      <li><b>DIALux photometric study</b> — lux, uniformity &amp; UGR proven on plan</li>
      <li><b>Luminaire schedule</b> — every fitting, finish, beam and quantity</li>
      <li><b>Reflected ceiling layout</b> — positions ready for the contractor</li>
      <li><b>Itemised costing</b> — with value-engineered options</li>
      <li><b>Data sheets &amp; BIM files</b> — ready for your spec pack</li>
    </ul>
  </div>
  <div class="img" style="background-image:url(<?php echo $img( 'office5.webp' ); ?>)"></div>
</section>

<section class="cta"><div class="bg" style="background-image:url(<?php echo $img( 'office1.webp' ); ?>)"></div><div class="scrim"></div><div class="inner"><div class="wrap col">
  <h2>Start your scheme</h2>
  <p>Tell us about the project and share your drawings — one of our lighting designers will be in touch, with your costed scheme to follow.</p>
  <div class="acts"><a class="btn btn-line" href="/about/">Talk to the team</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="/projects/">See the results →</a></div>
</div></div></section>
<!-- /wp:html -->

<?php
/**
 * Title: Page — About & Contact
 * Slug: ricoman/page-about
 * Categories: ricoman, ricoman-pages
 * Description: Company story, stats, values and contact details.
 *
 * @package Ricoman
 */
$img = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
?>
<!-- wp:html -->
<section class="phero">
  <div class="bg" style="background-image:url(<?php echo $img( 'rico-office.webp' ); ?>)"></div><div class="scrim"></div>
  <div class="inner"><div class="wrap">
    <span class="kick lt">About Ricoman</span>
    <h1>British lighting, made with intent.</h1>
    <p class="lede">We're a Manchester manufacturer of commercial interior LED lighting — designing, making and delivering schemes for the people who build great spaces.</p>
  </div></div>
</section>

<section class="sec"><div class="wrap">
  <div class="shead"><div><span class="kick"><span class="n">01</span>Who we are</span><h2>Lighting that works on spec, on time, on budget</h2></div></div>
  <p style="max-width:60ch;font-size:1.12rem">Ricoman designs and manufactures commercial interior LED lighting from our own facility in Manchester. Because we're the manufacturer — not a reseller — we control quality, lead times and bespoke detail in-house: free scheme design, 2,000+ components stocked ready to build, an average six-day UK-made lead, strong local partnerships and a 5-year warranty.</p>
</div></section>

<section class="statband soft"><div class="wrap">
  <div class="s"><div class="big" data-nocount>1999</div><small>Our journey began</small></div>
  <div class="s"><div class="big">535</div><small>Lighting design projects in 2025</small></div>
  <div class="s"><div class="big">2,000<span style="font-size:.4em">+</span></div><small>Components stocked, ready to build</small></div>
  <div class="s"><div class="big">20<span style="font-size:.4em">+</span></div><small>Countries supplied</small></div>
</div></section>

<section class="sec"><div class="wrap">
  <div class="shead"><div><span class="kick"><span class="n">02</span>What we stand for</span><h2>The way we like to work</h2></div></div>
  <div class="vals">
    <div class="vcard"><span class="sn">↳ 01</span><h3>Curiosity</h3><p>We constantly question the status quo, asking how lighting can be smarter, greener and more human. Innovation begins with asking the right questions.</p></div>
    <div class="vcard"><span class="sn">↳ 02</span><h3>Growth</h3><p>We grow through learning, sustainable practice and a relentless drive to improve — investing in in-house manufacturing for precision, flexibility and quality.</p></div>
    <div class="vcard"><span class="sn">↳ 03</span><h3>Community</h3><p>We invest in the people and places around us, from our Manchester roots to the partners on every project. Long-term relationships are at the heart of what we do.</p></div>
  </div>
</div></section>

<section class="sec contact"><div class="wrap">
  <div class="cwrap">
    <div>
      <span class="kick lt"><span class="n">03</span>Get in touch</span>
      <h2 style="margin-top:1em">Talk to the team</h2>
      <div class="cblock" style="margin-top:1.4em"><h4>Visit / Post</h4><p>Metroplex Business Park</p><p>520 Broadway, M50 2UE</p><p>Manchester, United Kingdom</p></div>
      <div class="cblock"><h4>Call</h4><p><a href="tel:01614515913">0161 451 5913</a></p></div>
      <div class="cblock"><h4>Email</h4><p><a href="mailto:sales@ricoman.com">sales@ricoman.com</a></p></div>
      <div class="cblock"><h4>Hours</h4><p>Mon–Thu 8:30–17:00 · Fri 8:30–16:00</p></div>
    </div>
    <div style="align-self:center">
      <img src="<?php echo $img( 'rico-office.webp' ); ?>" alt="Ricoman Manchester" style="width:100%;border:1px solid rgba(255,255,255,.14)">
    </div>
  </div>
</div></section>
<!-- /wp:html -->

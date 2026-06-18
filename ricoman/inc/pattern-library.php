<?php
/**
 * Ricoman pattern library.
 *
 * A large set of ready-made, on-brand section patterns the team can drop into
 * any page from the block inserter (＋ → Patterns → "Ricoman — Library").
 * Heroes, stats, features, galleries, video, logos, testimonials, team, steps,
 * FAQ, pricing, timeline, CTAs and more — all using the theme design system.
 *
 * @package Ricoman
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {

	if ( function_exists( 'register_block_pattern_category' ) ) {
		register_block_pattern_category( 'ricoman-library', array( 'label' => __( 'Ricoman — Library', 'ricoman' ) ) );
	}

	$u   = function ( $f ) { return esc_url( get_theme_file_uri( 'assets/images/' . $f ) ); };
	$lib = array();

	/* ---------------- Heroes ---------------- */
	$lib['hero-image'] = array( 'Hero — image', '
<section class="phero"><div class="bg" style="background-image:url(' . $u( 'warm-int.jpg' ) . ')"></div><div class="scrim"></div>
<div class="inner"><div class="wrap"><span class="kick lt">Eyebrow label</span><h1>A bold headline for this page.</h1><p class="lede">One supporting sentence that sets up the section beneath.</p>
<div class="acts" style="margin-top:1.4em"><a class="btn btn-line" href="#">Primary action</a><a class="btn btn-line" href="#">Secondary</a></div></div></div></section>' );

	$lib['hero-split'] = array( 'Hero — split', '
<section class="split light"><div class="body"><span class="kick">Eyebrow</span><h2>Split hero with text and image.</h2><p>A short intro paragraph explaining the value in a sentence or two.</p><a class="btn btn-line-d" href="#" style="margin-top:.6em">Call to action</a></div><div class="img" style="background-image:url(' . $u( 'office5.jpg' ) . ')"></div></section>' );

	$lib['hero-centered'] = array( 'Hero — centered', '
<section class="sec" style="text-align:center;padding-block:clamp(80px,12vw,160px)"><div class="wrap col"><span class="kick" style="display:block;margin-bottom:1.4em">Eyebrow</span><h1 style="font-size:clamp(2.4rem,5vw,4.4rem);font-weight:500">A centered, type-led hero.</h1><p style="max-width:50ch;margin:1.2em auto 0;color:var(--muted);font-size:1.15rem">Clean and minimal — ideal for campaign and landing pages.</p><div class="acts" style="justify-content:center;margin-top:2em"><a class="btn btn-solid" href="#">Get started</a></div></div></section>' );

	$lib['hero-video'] = array( 'Hero — video', '
<section class="phero"><div class="bg" style="background-image:url(' . $u( 'office1.jpg' ) . ')"></div><div class="scrim"></div>
<div class="inner"><div class="wrap"><span class="kick lt">Watch</span><h1>See it in action.</h1><div class="rm-play" style="position:static;justify-content:flex-start;margin-top:1.2em"><span>▶</span></div></div></div></section>' );

	/* ---------------- Stats ---------------- */
	$lib['stats-band'] = array( 'Stats — band', '
<section class="statband"><div class="wrap">
<div class="s"><div class="big">500+</div><small>Projects delivered</small></div>
<div class="s"><div class="big">2,000+</div><small>Components in stock</small></div>
<div class="s"><div class="big">~6 days</div><small>Average lead time</small></div>
<div class="s"><div class="big">98%</div><small>On-time in full</small></div>
</div></section>' );

	$lib['stats-soft'] = array( 'Stats — soft band', '
<section class="statband soft"><div class="wrap">
<div class="s"><div class="big">1999</div><small>Founded</small></div>
<div class="s"><div class="big">20+</div><small>Countries supplied</small></div>
<div class="s"><div class="big">5 yr</div><small>Warranty</small></div>
<div class="s"><div class="big">100%</div><small>UK manufactured</small></div>
</div></section>' );

	$lib['big-statement'] = array( 'Big statement', '
<section class="sec statement"><div class="wrap col"><p>A big editorial statement. <span>Use the muted span for a secondary clause.</span></p></div></section>' );

	/* ---------------- Features ---------------- */
	$lib['feature-left'] = array( 'Feature — image left', '
<section class="split light"><div class="img" style="background-image:url(' . $u( 'rico-making.webp' ) . ')"></div><div class="body"><span class="kick"><span class="n">01</span>Eyebrow</span><h2>Feature heading goes here.</h2><p>Two or three lines describing the feature, the benefit, and why it matters to the reader.</p><a class="lnk" href="#">Learn more →</a></div></section>' );

	$lib['feature-right-dark'] = array( 'Feature — image right (dark)', '
<section class="split dark"><div class="body"><span class="kick lt"><span class="n">02</span>Eyebrow</span><h2>A dark feature section.</h2><p>Dark sections add rhythm and contrast between light content blocks.</p><a class="btn btn-line" href="#" style="margin-top:.4em">Call to action</a></div><div class="img" style="background-image:url(' . $u( 'workshop.jpg' ) . ')"></div></section>' );

	$lib['feature-list-dark'] = array( 'Feature — checklist (dark)', '
<section class="split dark"><div class="body"><span class="kick lt">What you get</span><h2>Everything included.</h2><ul class="ul"><li><b>Point one</b> — short supporting detail</li><li><b>Point two</b> — short supporting detail</li><li><b>Point three</b> — short supporting detail</li><li><b>Point four</b> — short supporting detail</li></ul></div><div class="img" style="background-image:url(' . $u( 'office5.jpg' ) . ')"></div></section>' );

	$lib['overlap-card'] = array( 'Image with overlap card', '
<section><div class="phero" style="min-height:46vh"><div class="bg" style="background-image:url(' . $u( 'retail.jpg' ) . ')"></div><div class="scrim"></div></div><div class="wrap"><div class="rm-overlap" style="max-width:760px;margin-inline:auto;box-shadow:0 30px 80px -50px rgba(0,0,0,.5)"><span class="kick">Eyebrow</span><h2 style="margin:.4em 0">A card that overlaps the image above.</h2><p style="color:var(--muted)">A nice way to bridge a hero image into content.</p></div></div></section>' );

	/* ---------------- Media ---------------- */
	$lib['logos'] = array( 'Logo strip', '
<section class="sec tight"><div class="wrap"><p class="kick" style="text-align:center;margin-bottom:2.4em">Trusted by teams across the UK</p><div class="rm-logos"><span>ALLIANZ</span><span>BETFRED</span><span>KINGSGATE</span><span>NHS</span><span>SAVILLS</span><span>JLL</span></div></div></section>' );

	$lib['gallery-3'] = array( 'Gallery — grid', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">Gallery</span><h2>A few highlights</h2></div></div><div class="rm-gal"><img src="' . $u( 'office1.jpg' ) . '" alt=""><img src="' . $u( 'office2.jpg' ) . '" alt=""><img src="' . $u( 'retail.jpg' ) . '" alt=""><img src="' . $u( 'office5.jpg' ) . '" alt=""><img src="' . $u( 'office6.jpg' ) . '" alt=""><img src="' . $u( 'pendant.jpg' ) . '" alt=""></div></div></section>' );

	$lib['gallery-mosaic'] = array( 'Gallery — mosaic', '
<section class="sec tight"><div class="wrap"><div class="mosaic"><a class="pj big" href="#"><img src="' . $u( 'office1.jpg' ) . '" alt=""><div class="ov"><h3>Feature</h3></div></a><a class="pj wide" href="#"><img src="' . $u( 'retail.jpg' ) . '" alt=""><div class="ov"><h3>Wide</h3></div></a><a class="pj" href="#"><img src="' . $u( 'office2.jpg' ) . '" alt=""><div class="ov"><h3>Tile</h3></div></a><a class="pj" href="#"><img src="' . $u( 'office6.jpg' ) . '" alt=""><div class="ov"><h3>Tile</h3></div></a></div></div></section>' );

	$lib['video-embed'] = array( 'Video — feature', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">Watch</span><h2>A short film about the work</h2></div></div><div class="rm-videowrap"><img src="' . $u( 'workshop.jpg' ) . '" alt=""><div class="rm-play"><span>▶</span></div></div></div></section>' );

	$lib['image-full'] = array( 'Full-bleed image band', '
<section><div style="height:60vh;background:center/cover no-repeat url(' . $u( 'arch-line.jpg' ) . ')"></div></section>' );

	/* ---------------- Social proof ---------------- */
	$lib['quote-light'] = array( 'Quote — light', '
<section class="sec quote"><div class="wrap"><p>“A genuinely useful quote from a happy client, kept short and punchy.”</p><div class="by">— Name Surname, Role, Company</div></div></section>' );

	$lib['quote-dark'] = array( 'Quote — dark', '
<section class="sec quote dark"><div class="wrap"><p>“A dark testimonial section for visual contrast and emphasis.”</p><div class="by">— Name Surname, Role, Company</div></div></section>' );

	$lib['team-grid'] = array( 'Team grid', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">The team</span><h2>People behind the work</h2></div></div><div class="rm-team"><div class="m"><img src="' . $u( 'office3.jpg' ) . '" alt=""><h4>Full Name</h4><small>Job title</small></div><div class="m"><img src="' . $u( 'office6.jpg' ) . '" alt=""><h4>Full Name</h4><small>Job title</small></div><div class="m"><img src="' . $u( 'office2.jpg' ) . '" alt=""><h4>Full Name</h4><small>Job title</small></div><div class="m"><img src="' . $u( 'office1.jpg' ) . '" alt=""><h4>Full Name</h4><small>Job title</small></div></div></div></section>' );

	/* ---------------- Process / info ---------------- */
	$lib['steps-4'] = array( 'Process — 4 steps', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">How it works</span><h2>Four simple steps</h2></div></div><div class="steps"><div class="step"><span class="no">01</span><h3>Step one</h3><p>Short description of this step.</p></div><div class="step"><span class="no">02</span><h3>Step two</h3><p>Short description of this step.</p></div><div class="step"><span class="no">03</span><h3>Step three</h3><p>Short description of this step.</p></div><div class="step"><span class="no">04</span><h3>Step four</h3><p>Short description of this step.</p></div></div></div></section>' );

	$lib['icon-cards'] = array( 'Feature cards — 3', '
<section class="sec"><div class="wrap"><div class="rm-iconcards"><div><div class="ic">◆</div><h3>Feature one</h3><p>A sentence about this feature and its benefit.</p></div><div><div class="ic">◆</div><h3>Feature two</h3><p>A sentence about this feature and its benefit.</p></div><div><div class="ic">◆</div><h3>Feature three</h3><p>A sentence about this feature and its benefit.</p></div></div></div></section>' );

	$lib['audience'] = array( 'Audience — 4 columns', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">Who it\'s for</span><h2>A partner for everyone</h2></div></div><div class="aud"><div class="acard"><span class="sn">01</span><h3>Group one</h3><p>Why this audience benefits.</p></div><div class="acard"><span class="sn">02</span><h3>Group two</h3><p>Why this audience benefits.</p></div><div class="acard"><span class="sn">03</span><h3>Group three</h3><p>Why this audience benefits.</p></div><div class="acard"><span class="sn">04</span><h3>Group four</h3><p>Why this audience benefits.</p></div></div></div></section>' );

	$lib['values-3'] = array( 'Values — 3 columns', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">What we stand for</span><h2>Our values</h2></div></div><div class="vals"><div class="vcard"><span class="sn">↳ 01</span><h3>Value one</h3><p>A short paragraph about this value.</p></div><div class="vcard"><span class="sn">↳ 02</span><h3>Value two</h3><p>A short paragraph about this value.</p></div><div class="vcard"><span class="sn">↳ 03</span><h3>Value three</h3><p>A short paragraph about this value.</p></div></div></div></section>' );

	$lib['faq'] = array( 'FAQ — accordion', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">FAQ</span><h2>Common questions</h2></div></div><div class="rm-faq"><details open><summary>First question?</summary><p>A clear, concise answer to the question.</p></details><details><summary>Second question?</summary><p>A clear, concise answer to the question.</p></details><details><summary>Third question?</summary><p>A clear, concise answer to the question.</p></details><details><summary>Fourth question?</summary><p>A clear, concise answer to the question.</p></details></div></div></section>' );

	$lib['timeline'] = array( 'Timeline', '
<section class="sec"><div class="wrap col"><div class="shead"><div><span class="kick">Our story</span><h2>How we got here</h2></div></div><div class="rm-timeline"><div class="ev"><div class="yr">1999</div><h3>The beginning</h3><p>What happened this year.</p></div><div class="ev"><div class="yr">2010</div><h3>A milestone</h3><p>What happened this year.</p></div><div class="ev"><div class="yr">2020</div><h3>Growth</h3><p>What happened this year.</p></div><div class="ev"><div class="yr">Today</div><h3>Where we are now</h3><p>What\'s happening now.</p></div></div></div></section>' );

	$lib['pricing-3'] = array( 'Pricing — 3 plans', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">Options</span><h2>Choose what fits</h2></div></div><div class="rm-plans"><div class="rm-plan"><h3>Starter</h3><div class="pr">£—</div><ul><li>Feature included</li><li>Feature included</li><li>Feature included</li></ul><a class="btn btn-line-d" href="#">Choose</a></div><div class="rm-plan feat"><h3>Recommended</h3><div class="pr">£—</div><ul><li>Everything in Starter</li><li>Feature included</li><li>Feature included</li></ul><a class="btn btn-line" href="#">Choose</a></div><div class="rm-plan"><h3>Premium</h3><div class="pr">£—</div><ul><li>Everything in Recommended</li><li>Feature included</li><li>Feature included</li></ul><a class="btn btn-line-d" href="#">Choose</a></div></div></div></section>' );

	/* ---------------- CTAs / utility ---------------- */
	$lib['cta-band'] = array( 'CTA — image band', '
<section class="cta"><div class="bg" style="background-image:url(' . $u( 'office1.jpg' ) . ')"></div><div class="scrim"></div><div class="inner"><div class="wrap col"><h2>A strong closing call to action.</h2><p>One sentence of supporting copy to nudge the click.</p><div class="acts"><a class="btn btn-line" href="#">Primary</a><a class="btn btn-solid" style="background:#fff;color:var(--ink)" href="#">Secondary →</a></div></div></div></section>' );

	$lib['cta-simple'] = array( 'CTA — simple', '
<section class="sec" style="text-align:center"><div class="wrap col"><h2>Ready to get started?</h2><p style="color:var(--muted);max-width:46ch;margin:1em auto 1.6em">A short line of supporting copy.</p><a class="btn btn-solid" href="#">Get in touch</a></div></section>' );

	$lib['banner'] = array( 'Announcement banner', '
<div class="rm-banner">New: something worth announcing. <a href="#">Find out more →</a></div>' );

	$lib['contact-split'] = array( 'Contact — split (dark)', '
<section class="sec contact"><div class="wrap"><div class="cwrap"><div><span class="kick lt">Get in touch</span><h2 style="margin-top:.6em">Talk to the team</h2><div class="cblock" style="margin-top:1.4em"><h4>Visit</h4><p>Your address here</p></div><div class="cblock"><h4>Call</h4><p>0000 000 0000</p></div><div class="cblock"><h4>Email</h4><p>hello@example.com</p></div></div><div style="align-self:center"><img src="' . $u( 'rico-office.jpg' ) . '" alt="" style="width:100%"></div></div></div></section>' );

	$lib['two-col-text'] = array( 'Two-column text', '
<section class="sec"><div class="wrap"><div class="shead"><div><span class="kick">Overview</span><h2>A section with two text columns</h2></div></div><div style="display:grid;grid-template-columns:1fr 1fr;gap:clamp(28px,4vw,64px)"><p style="font-size:1.05rem">First column of body text. Useful for longer-form content where a single column would be too wide to read comfortably.</p><p style="font-size:1.05rem">Second column continues the thought. Keep paragraphs short and scannable for the best reading experience.</p></div></div></section>' );

	// Register everything.
	foreach ( $lib as $slug => $data ) {
		register_block_pattern(
			'ricoman/' . $slug,
			array(
				'title'      => 'Ricoman · ' . $data[0],
				'categories' => array( 'ricoman-library', 'ricoman' ),
				'content'    => '<!-- wp:html -->' . $data[1] . '<!-- /wp:html -->',
			)
		);
	}
}, 11 );

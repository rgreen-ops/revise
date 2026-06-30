/* Sticky header: turns solid black once the user scrolls past the hero. */
(function () {
  var hdr = document.querySelector('header.site') || document.querySelector('.ricoman-site-header');
  if (hdr) {
    function onScroll() { hdr.classList.toggle('rm-stuck', (window.scrollY || window.pageYOffset) > 40); }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }
})();

/* Ricoman previews — subtle motion layer (progressive enhancement).
   - Gentle reveal-on-scroll for sections/cards (slight fade + rise, light stagger)
   - Count-up for leading numeric stats (projects, components, sq ft, %)
   Respects prefers-reduced-motion. Degrades to fully static if it can't run. */
(function () {
  "use strict";
  try {
    var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var supportsIO = "IntersectionObserver" in window;

    /* ---------- reveal on scroll ---------- */
    var revealSel = [
      ".shead", ".rcard", ".pc", ".pj", ".acard", ".vcard", ".step", ".cap",
      ".fstep", ".dcard", ".ncard", ".statband .s", ".cs-meta > div", ".app",
      ".feat .body", ".britain .body", ".split .body", ".intro .copy",
      ".intro .facts", ".metastrip .m", ".gal2 img", ".three .dcard"
    ].join(",");

    var els = [].slice.call(document.querySelectorAll(revealSel));
    els.forEach(function (el) {
      el.classList.add("reveal");
      // light per-group stagger
      var sibs = el.parentNode ? [].slice.call(el.parentNode.children).filter(function (n) { return n.classList && n.classList.contains("reveal"); }) : [];
      var idx = sibs.indexOf(el);
      if (idx > 0) el.style.transitionDelay = Math.min(idx, 6) * 0.06 + "s";
    });

    if (reduce || !supportsIO) {
      els.forEach(function (el) { el.classList.add("in"); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { e.target.classList.add("in"); io.unobserve(e.target); }
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -8% 0px" });
      els.forEach(function (el) { io.observe(el); });
    }

    /* ---------- count-up for leading numbers ---------- */
    function animateNumber(el) {
      var html = el.innerHTML;
      var m = html.match(/\d[\d,]*/);
      if (!m) return;
      // only animate when the number leads (ignore mid-string numbers like "~6-day")
      var pre = html.slice(0, m.index);
      if (pre.replace(/\s/g, "") !== "") return;
      var target = parseInt(m[0].replace(/,/g, ""), 10);
      if (isNaN(target)) return;
      var post = html.slice(m.index + m[0].length);
      var dur = 1100, start = null;
      function fmt(n) { return n.toLocaleString("en-GB"); }
      function frame(ts) {
        if (!start) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var eased = 1 - Math.pow(1 - p, 3);
        el.innerHTML = pre + fmt(Math.round(eased * target)) + post;
        if (p < 1) requestAnimationFrame(frame);
      }
      el.innerHTML = pre + "0" + post;
      requestAnimationFrame(frame);
    }

    var nums = [].slice.call(document.querySelectorAll(".statband .big, .facts b, .cs-meta b"))
      .filter(function (el) { return !el.hasAttribute("data-nocount"); });

    if (!reduce && supportsIO) {
      var io2 = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) { animateNumber(e.target); io2.unobserve(e.target); }
        });
      }, { threshold: 0.4 });
      nums.forEach(function (el) { io2.observe(el); });
    }
  } catch (err) { /* stay static on any error */ }
})();

/* Lazy-load the deferred hero background video (poster shows first, then the
   compressed video swaps in after the page has loaded — keeps it off the
   critical path). */
window.addEventListener('load', function () {
  try {
    // Non-background videos (e.g. the brand-film player) shouldn't pull bytes on
    // load — show their poster and only fetch when the visitor hits play.
    document.querySelectorAll('video:not(.wp-block-cover__video-background)').forEach(function (v) {
      if (!v.hasAttribute('autoplay')) { v.preload = 'none'; }
    });
    // On phones/tablets and data-saver/slow connections, keep the still poster
    // and never download the ~1.9MB video — big mobile speed + payload win.
    var small = window.matchMedia && window.matchMedia('(max-width: 1024px)').matches;
    var c = navigator.connection || {};
    var slow = c.saveData === true || /(^|-)2g$/.test(c.effectiveType || '');
    if (small || slow) { return; }
    document.querySelectorAll('video.wp-block-cover__video-background[data-src]').forEach(function (v) {
      v.src = v.getAttribute('data-src');
      v.removeAttribute('data-src');
      try { v.load(); } catch (e) {}
      var p = v.play();
      if (p && p.catch) { p.catch(function () {}); }
    });
  } catch (e) { /* leave poster in place on any error */ }
});

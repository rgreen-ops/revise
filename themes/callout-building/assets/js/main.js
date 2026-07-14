/* ==========================================================================
   Callout — theme JavaScript (vanilla, no dependencies)

   Everything is progressive enhancement: each block bails out silently if
   its markup isn't on the current page, so this one file is safe to load
   on every template.
   ========================================================================== */
(function () {
  "use strict";

  /* Mobile navigation ----------------------------------------------------- */
  var navToggle = document.querySelector("[data-nav-toggle]");
  var navPanel = document.querySelector("[data-nav-panel]");
  if (navToggle && navPanel) {
    navToggle.addEventListener("click", function () {
      var open = navPanel.classList.toggle("hidden") === false;
      navToggle.setAttribute("aria-expanded", String(open));
      navToggle.querySelector("[data-icon-open]").classList.toggle("hidden", open);
      navToggle.querySelector("[data-icon-close]").classList.toggle("hidden", !open);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && !navPanel.classList.contains("hidden")) {
        navToggle.click();
        navToggle.focus();
      }
    });
  }

  /* Sticky header shadow --------------------------------------------------- */
  var header = document.querySelector("[data-header]");
  if (header) {
    var onScroll = function () {
      header.classList.toggle("shadow-md", window.scrollY > 8);
    };
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
  }

  /* Scroll reveal ----------------------------------------------------------
     Elements with .reveal fade in when they enter the viewport.
     The CSS disables the effect entirely under prefers-reduced-motion. */
  var revealEls = document.querySelectorAll(".reveal");
  if (revealEls.length && "IntersectionObserver" in window) {
    var io = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            io.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.12 }
    );
    revealEls.forEach(function (el) { io.observe(el); });
  } else {
    revealEls.forEach(function (el) { el.classList.add("is-visible"); });
  }

  /* Tabs (product page) ----------------------------------------------------
     Markup: [data-tabs] wraps buttons [role=tab] and panels [role=tabpanel];
     each tab's aria-controls names its panel id. Arrow keys move focus. */
  document.querySelectorAll("[data-tabs]").forEach(function (root) {
    var tabs = Array.prototype.slice.call(root.querySelectorAll("[role=tab]"));
    var panels = Array.prototype.slice.call(root.querySelectorAll("[role=tabpanel]"));

    function select(tab) {
      tabs.forEach(function (t) {
        var active = t === tab;
        t.setAttribute("aria-selected", String(active));
        t.setAttribute("tabindex", active ? "0" : "-1");
        t.classList.toggle("border-accent", active);
        t.classList.toggle("text-primary", active);
        t.classList.toggle("border-transparent", !active);
        t.classList.toggle("text-ink-soft", !active);
      });
      panels.forEach(function (p) {
        p.classList.toggle("hidden", p.id !== tab.getAttribute("aria-controls"));
      });
    }

    tabs.forEach(function (tab, i) {
      tab.addEventListener("click", function () { select(tab); });
      tab.addEventListener("keydown", function (e) {
        var next = e.key === "ArrowRight" ? i + 1 : e.key === "ArrowLeft" ? i - 1 : null;
        if (next === null) return;
        e.preventDefault();
        var target = tabs[(next + tabs.length) % tabs.length];
        target.focus();
        select(target);
      });
    });
  });

  /* Accordion (FAQs) -------------------------------------------------------
     Uses native <details>; this just closes siblings so one stays open. */
  document.querySelectorAll("[data-accordion]").forEach(function (group) {
    group.querySelectorAll("details").forEach(function (d) {
      d.addEventListener("toggle", function () {
        if (!d.open) return;
        group.querySelectorAll("details[open]").forEach(function (other) {
          if (other !== d) other.open = false;
        });
      });
    });
  });

  /* Product grid filter (demo) ---------------------------------------------
     Buttons with [data-filter="category"] show/hide cards carrying
     [data-category]. Purely client-side — swap for your real catalogue. */
  var filterBar = document.querySelector("[data-filter-bar]");
  if (filterBar) {
    var cards = document.querySelectorAll("[data-category]");
    var countEl = document.querySelector("[data-filter-count]");
    filterBar.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-filter]");
      if (!btn) return;
      var value = btn.getAttribute("data-filter");
      filterBar.querySelectorAll("[data-filter]").forEach(function (b) {
        var active = b === btn;
        b.setAttribute("aria-pressed", String(active));
        b.classList.toggle("bg-primary", active);
        b.classList.toggle("text-white", active);
        b.classList.toggle("bg-board", !active);
        b.classList.toggle("text-ink-soft", !active);
      });
      var shown = 0;
      cards.forEach(function (card) {
        var show = value === "all" || card.getAttribute("data-category") === value;
        card.classList.toggle("hidden", !show);
        if (show) shown++;
      });
      if (countEl) countEl.textContent = shown + " products";
    });
  }

  /* Product gallery ----------------------------------------------------------
     Thumbnails [data-gallery-thumb] swap the main image [data-gallery-main]. */
  var galleryMain = document.querySelector("[data-gallery-main]");
  if (galleryMain) {
    document.querySelectorAll("[data-gallery-thumb]").forEach(function (thumb) {
      thumb.addEventListener("click", function () {
        var img = thumb.querySelector("img");
        galleryMain.src = img.src;
        galleryMain.alt = img.alt;
        document.querySelectorAll("[data-gallery-thumb]").forEach(function (t) {
          t.classList.toggle("border-accent", t === thumb);
          t.classList.toggle("border-line", t !== thumb);
          t.setAttribute("aria-pressed", String(t === thumb));
        });
      });
    });
  }

  /* Forms (demo handler) ----------------------------------------------------
     Static templates have no backend. This validates required fields and
     shows the success message so the flow can be demonstrated. Point the
     form at your own endpoint (Formspree, Netlify Forms, your CRM) and
     delete this block to go live. */
  document.querySelectorAll("[data-demo-form]").forEach(function (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      var success = form.querySelector("[data-form-success]");
      if (success) {
        success.classList.remove("hidden");
        success.focus();
      }
      form.reset();
    });
  });

  /* Footer year ------------------------------------------------------------ */
  document.querySelectorAll("[data-year]").forEach(function (el) {
    el.textContent = new Date().getFullYear();
  });
})();

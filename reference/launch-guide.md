# Go‑Live checklist — staging → ricoman.com (same Plesk server)

This is the **one‑time launch**. After it, you never do this again — ongoing
updates use **Ricoman → 🚀 Push to Live**. Take it slowly; every step is safe and
reversible. Don't worry about the old site's size — you're copying the **clean
staging site over live**, so the old bloat is replaced, not carried over.

Rough time: 30–45 minutes. Do it at a quiet time (early morning).

---

## A. The day before — make space + a safety net

1. **Free disk space (clear the old backups).**
   Plesk → *Tools & Settings → Backup Manager* (and the subscription's Backup
   Manager). Delete the **old/failed backups** (the ~248 GB). This is the storage
   that broke backups — clearing it gives the copy room to run.

2. **Confirm staging is signed off.** Click around staging one last time — home,
   a category, a product, news, a project, contact. If it looks right there, it'll
   look right live.

---

## B. Launch morning — back up live first (your undo button)

3. **Take a FULL backup of the LIVE site.**
   Plesk → the **ricoman.com** subscription → *Backup Manager → Back Up* →
   include *Files and databases* → run it. Wait for it to finish.
   👉 This is your rollback. If anything looks wrong after launch, you restore this
   and you're exactly back to the old site. Nothing is irreversible.

---

## C. Copy staging → live (Plesk WordPress Toolkit)

4. Plesk → **WordPress** (the WordPress Toolkit).

5. Find the **staging** install (staging.ricoman.com). Use **"Copy Data"**
   (sometimes under the **⋮ / Clone or Copy** menu).
   - **Source:** staging.ricoman.com
   - **Target:** ricoman.com (the live install)
   - Copy **Files + Database** (everything).
   - Leave "replace URLs" / "search‑replace" **on** — the Toolkit swaps
     `staging.ricoman.com` → `ricoman.com` for you (this is the bit that's risky by
     hand; the Toolkit does it safely, including inside serialized data).
   - Confirm and let it run.

   *(Exact wording varies by Plesk version — if you only see "Clone", clone staging
   then point the live domain at the clone; tell me what you see and I'll steer.)*

---

## D. Finish on live (a few clicks, no Plesk)

6. Log into **ricoman.com/wp-admin**.

7. **Settings → Reading →** UNTICK **"Discourage search engines…"**, Save.
   (Staging had this ON so Google ignored it; live MUST be findable.)

8. **Ricoman → 🚀 Go Live →** click **"Run launch finalisation"**.
   This one button: clears caches, refreshes permalinks/links, re‑seeds the SEO
   copy, and runs the pre‑flight checks. Green ticks = good.

9. **Spot‑check live:** homepage, a category (filters work), a product (gallery +
   configure table), news, a project, contact form. Click the main menu links.

10. **Redirects:** Ricoman → *Links & Redirects* — make sure old URLs point to the
    new ones (the 404 watch will catch any stragglers in the first days).

---

## E. After launch

- Live now collects real leads/accounts. **Never copy staging over live again** —
  that would wipe them. From here, all updates go via **🚀 Push to Live** (code) or
  by editing live content directly with Draft → Preview → Publish.
- Keep an eye on **Ricoman → Leads** and the 404 log for the first few days.

## If something looks wrong

- Don't panic — you have the backup from step 3.
- Small visual glitch → tell Claude, it's usually a cache; click "Run launch
  finalisation" again.
- Something seriously broken → Plesk → ricoman.com → *Backup Manager → Restore*
  the step‑3 backup. You're back to the old site; we regroup and retry.

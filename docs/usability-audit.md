# MCU-NEX Usability Audit

Captured live from `http://127.0.0.1:8000` on 2026-10-10 (fresh database, so every
empty state below is the state a real first-run user sees). Pages audited: login
(`/`), super-admin home dashboard, account management, curriculums, create schedule.

Findings are grouped by severity. "Page family" = the 12 unique screens that are
each cloned across 5 role folders (admin, super-admin, viewing,
viewing-with-approval, viewing-program-head).

---

## P0 — Broken or misleading

1. **"Forgot password" is a dead end.** The modal walks the user through email →
   6-digit code → new password, then answers *"Email reset is not wired yet"* via
   `alert()`. Users who genuinely forgot their password hit a multi-step form that
   can never succeed. Either wire it or replace the flow with one honest message.
2. **Error/notice messages use `alert()`** in the forgot-password flow, while the
   login form itself uses a proper inline `role="alert"` element. Native dialogs
   feel broken in a web app and block the page.
3. **Create-schedule page has zero hover states and zero media queries**
   (measured: 0 `:hover`, 0 `@media` in 26 KB). Buttons give no pointer feedback
   and the page cannot adapt to smaller screens at all.
4. **Account-management has 1 media query for a 27 KB page** — the main user
   table (Name/Email/Role/Status/Actions) does not adapt; on narrow screens the
   page overflows horizontally.

## P1 — Consistency & trust

5. **The notification bell is an emoji** (`&#128276;` 🔔) next to hand-built
   CSS UI — it renders differently on every OS and looks unfinished.
6. **Two different brand headers.** The login card says "MCU-NEX / Registrar
   Allocation System"; the app topbar says "MCU-NEX / Registrar Room Allocation".
   First impressions disagree about what the product is called.
7. **Role chip, avatar, and user menu are custom per page** (hand-rolled
   `chip`, `avatar` initials, `tooltip` markup) — fine on dashboards, but each
   page family reimplements them slightly differently.
8. **Inconsistent interaction affordances across page families:** curriculums has
   33 hover rules and 8 media queries (best of the audited set); create-schedule
   has none. Users learn hover/behavior per page and get surprised on the next.

## P2 — Structure that inflates every future change

9. **Page = one giant file.** home-dashboard.blade.php is 47 KB / 1,351 lines:
   inline `<style>` block + markup + inline `<script>`. Same for all 12 families.
10. **Role variants are near-identical clones:** admin vs super-admin home
    dashboard differ by **5 lines out of 1,351** (role chip text + two help
    sentences). Any fix must be applied up to 5× or it silently diverges.
11. **12 `<script>` tags per page** loaded sequentially from `public/js/`
    (globals with load-order dependencies: `api-client` → `session-boot` →
    stores → page script). No bundling, no caching strategy, no defer except
    `notifications.js`.
12. **Relative asset URLs everywhere** (`../Attri/mculogo.jpg`, `../../css/…`)
    — they work only because each page sits at a known depth; moving a page
    breaks its assets silently.

## P3 — Performance & polish

13. **Google Fonts CDN** (`fonts.googleapis.com` DM Sans) — extra DNS/TLS round
    trip, blocked in some networks/offline dev; Vite already self-hosts
    Instrument Sans as the app font (unused by these pages).
14. **Inline styles are duplicated per page:** `:root` color tokens
    (`--purple: #5c267e`, `--gold: #f9b937`) copy-pasted into every page's
    `<style>` — changing the brand color means 79 edits.
15. **Dashboard ships `card`-classed markup only 3×** but drags in the full
    store/JS chain (curriculum, sections, org, room-types…) regardless of use.

## What's already good (keep it)

- **A11y baseline exists**: `aria-*` attributes on menus/tooltips/dialogs
  (30 on the dashboard), `role="dialog"` + `aria-modal` on modals, real
  `<label for>` on login/account forms, no `<img>` missing `alt` in audited pages.
- **Login page is focused**: one card, two fields, proper `autocomplete`
  attributes, inline `role="alert"` error region, disabled-state on submit.
- **Brand palette is distinctive** (MCU purple `#5c267e` + gold `#f9b937`) —
  worth keeping as the token base.
- **DM Sans** is a solid, legible type choice.

## Redesign direction (summary)

1. One design-token layer (colors, type, spacing, radius, shadow) — kill the 79
   copy-pasted `:root` blocks.
2. Shared shell: topbar + tabs + sidebar as one Blade component driven by role
   config; single source for nav, chip, avatar, menus.
3. Component library: button/card/table/field/modal/toast/empty-state with
   hover/focus/disabled states defined once, responsive by default.
4. Replace `alert()` flows with inline feedback; decide the forgot-password fate.
5. Bundle JS through Vite; drop CDN fonts for self-hosted DM Sans.
6. Migrate page families one at a time (dashboards first — they're 5 clones of
   1 file, so consolidation pays off immediately).

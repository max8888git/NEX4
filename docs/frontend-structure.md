# Frontend structure

How the role pages, scripts and styles are organised (October 2026 refactor).

## Pages

URLs are unchanged: `/{role-folder}/{page}.html`, e.g. `/admin/home-dashboard.html`.
`App\Http\Controllers\PageController` resolves each URL in this order:

1. `resources/views/pages/{role-folder}/{page}.blade.php`: a page that is
   genuinely different for one role (only a few remain, see below).
2. `resources/views/roles/{group}/{page}.blade.php`: the shared page for the
   role's group, rendered with `$role` set to the URL's role folder.

| Role folder | Group |
| --- | --- |
| `admin`, `super-admin` | `staff` |
| `viewing`, `viewing-with-approval`, `viewing-program-head` | `viewer` |

Where roles differ inside a shared page, the view switches on `$role`:

```blade
@endverbatim
@if ($role === 'super-admin')
@verbatim
        <span class="chip">Super Admin</span>
@endverbatim
@else
@verbatim
        <span class="chip">Admin</span>
@endverbatim
@endif
@verbatim
```

Pages are still mostly one big `@verbatim` block (HTML, inline `<style>` and
`<script>`), so Blade only runs in the gaps between `@endverbatim` and `@verbatim`.

Pages still kept per role (the variants differ too much to merge usefully):
VPAA's `viewer`, `viewer-requests` and `viewer-rooms`.

Redesigned pages use the shared building blocks in
`resources/css/legacy/ui.css` (buttons, status chips, tabs, fields, dialogs,
empty states) plus a small page stylesheet: Requests (`staff-requests.css/.js`)
and Account management (`staff-accounts.css/.js`, one page for both staff
roles; the server decides which accounts and roles each may manage).

## Navigation

Every app page uses one shared sidebar instead of its own copy:

- Links per role folder: `config/navigation.php` (groups, icons, which pages
  highlight each item).
- Markup: `<x-app-nav :role="$role" />` in place of the old `<nav class="nav-bar">`,
  and `<x-app-nav-button />` (the "Menu" button) in place of the hamburger.
- Behaviour: `resources/js/legacy/app-nav.js`. At 1200px and wider it's a
  fixed sidebar; below that it's a drawer (backdrop, Esc or backdrop click to
  close, focus kept inside, page scroll locked). It also shows the signed-in
  user and the pending-requests badge.
- Styles: `resources/css/legacy/app-nav.css`.

## Profile menu and My account

- `<x-profile-menu :role="$role" />` (topbar) and `resources/js/legacy/profile.js`
  show the signed-in user's name, role, email and photo everywhere.
- My account (`/{role}/account.html`, one page for all roles) lets everyone set
  a profile photo and change their own password; the University Registrar can
  also edit their own name and email (via `users.php`). Text size is a
  per-computer preference.
- Server side (small additions):
  - `POST api/auth.php` actions `change_password` (current password required,
    min 5 characters, logged in the Transaction log), `set_photo` (data URL of
    a JPG/PNG/WebP up to 512 KB / 1024px; the browser crops to 256px) and
    `remove_photo`.
  - Photos are files in `storage/app/private/avatars/{user_id}.{ext}`
    (`App\Support\ProfilePhoto`), served to signed-in users only at
    `GET api/avatar/u-{id}`; the session user includes `photoUrl`.
  - Tests: `tests/Feature/OwnAccountTest.php` (uses a separate
    `mcu_nex_test` MySQL database).

## Scripts and styles

- `resources/js/legacy/*.js` and `resources/css/legacy/*.css` (formerly
  `public/js`, `public/css`, `public/admin/js`) are Vite entries, built to
  hashed files in `public/build`. Run `npm run build` (or `npm run dev`).
- Pages load them with `<x-legacy-js name="api-client" />` and
  `<x-legacy-css name="schedule-page" />`.
- They're still **classic scripts**, not ES modules: each one attaches a global
  to `window` (`McuApi`, `OrgStore`, …), and the inline page scripts after them
  use those globals right away. Don't switch them to `@vite`/`type="module"`
  until those inline scripts are moved into modules too, because modules run
  after the page has parsed. `vite.config.js` keeps each file's `'use strict'`.
- `api-client.js` derives the API base (`/api/`) from the page URL (everything
  before the role folder), so it no longer depends on its own file name.
- `resources/css/legacy/shell-responsive.css` is loaded last on every app page
  and holds the phone-width fixes for the shared topbar and tab row.
- DM Sans is self-hosted through `@fonts` (configured in `vite.config.js`).
- Static images stay in `public/Attri/`.

## Create schedule

- `schedule-page.js` is the timetable and auto-scheduler (shared with Final
  report and Faculty loading). `schedule-colleges.js` is the Colleges panel on
  Create schedule only: approval steps and per-college actions (see
  `docs/per-college-approval.md`). They talk through document events:
  `mcu:schedule-loaded` (page → panel: which term/tab is showing),
  `mcu:schedule-reload` (panel → page: reload the timetable after an action)
  and `mcu:colleges-changed` (panel → page: locks changed, redraw).
  `window.McuScheduleColleges.isLocked(collegeId)` hides Edit for locked
  colleges.

## Trying pages without touching real data

`bash scripts/preview-db.sh` copies `mcu_nex` to `mcu_nex_preview` and sets
every account there to the test password in that script. The `preview-copy`
entry in `.claude/launch.json` (`scripts/preview-serve.cmd`) serves the site
on http://localhost:8001 against that copy. Run the script again to start
over from the real data.

## Removed: Faculty loading (2026-10-13)

Not part of the project's scope. Its pages, scripts, styles, `legacy-api/faculty-loading.php`,
`FacultyLoadingController` and the `FacultyLoading` model were moved to
`_archive/faculty-loading-removed/`; the menu links, the `api/faculty-loading.php` route and the
DomainCatalog include are gone. Old `…/faculty-loading.html` links redirect to the dashboard.
The database tables (`faculty`, `faculty_loadings`, …) are left as they are.

## Curriculums (registrars), merged 2026-10-13

One page, `roles/staff/curriculums/curriculums` (`curriculum-library.js`): every curriculum,
read-only, picked from a list grouped by college and program (search, Active / Archived / All),
with Download CSV / PDF / Print, Upload CSV (`curriculum-upload.js`), and Archive / Restore / Delete
(archived only). The old filter page (`curriculums/curriculum-filters`) and Data management →
Curriculums (`data-management/add-curriculum`) redirect here; the old pages are in
`_archive/curriculum-pages-before-merge/`. "From the colleges" (`curriculums/review`) is the
review tab next to it.

# Per-college timetable approval (server side)

For backend review. Colleges move through approval one at a time (or several
at once): Records Officer → University Registrar → VPAA → published.

## Files

- `legacy-api/schedule-colleges.php` (new): everything below.
- `legacy-api/schedule.php`: loads the new file; `GET ?colleges=1` returns the
  status overview; per-college actions are dispatched before the existing
  ones; after the existing whole-campus approval actions the per-college rows
  are rebuilt from the term-wide state.
- `legacy-api/schedule-workspaces.php`: Generate keeps locked colleges; edits
  to a locked college's classes are refused.
- `legacy-api/bootstrap.php`: `ensure_notifications_schema` skips its
  `CREATE TABLE IF NOT EXISTS` when the table exists. In MySQL that statement
  silently commits the open transaction, so every action that sent a
  notification ended its own transaction early (the change saved, then the
  request failed with "There is no active transaction"). This affected the
  existing approval buttons too.
- `tests/Feature/CollegeApprovalTest.php`: the whole flow on a copy of the
  database (`mcu_nex_sched_test`).

Backups of the three changed legacy files are in `_archive/baseline-2026-10-11/`.

## Data

New table `term_college_approvals` (created on first use), one row per
term and college: `status` (draft, pending_super_admin, pending_vpaa,
published), that college's copy at each step (`sa_json`, `vpaa_json`,
`published_json`), `is_locked`, unlock reason/time, and the last send-back
note.

The existing `term_schedule_approvals` columns and `schedule_copies` slots
stay the source for every page that reads them: after each per-college
action they are rebuilt as "all colleges at that step combined"
(`term_from_college_rows`). Reports, the viewer pages and Rooms available
need no changes.

## Actions (POST api/schedule.php)

All take `schoolYear`, `termCode` and `colleges: ["c-2", ...]`. Roles are
checked against the signed-in user, not the request's `actor`.

Statuses: `draft` (Records Officer working on it) → `pending_super_admin`
(sent, waiting for the University Registrar) → `with_registrar` (the
Registrar approved it or built it, and is finishing it) → `pending_vpaa` →
`published` (locked). `with_registrar` was added to the `status` enum; the
column is altered automatically on the first request.

| Action | Who | Effect |
| --- | --- | --- |
| `college_submit_to_registrar` | Records Officer | Their working timetable's copy of each college (complete, not locked, not already with the Registrar) → `pending_super_admin` |
| `college_retract` | Records Officer / University Registrar | Records Officer: `pending_super_admin` → `draft`. Registrar: `pending_vpaa` → `with_registrar` |
| `college_registrar_decide` `accept: true` | University Registrar | `pending_super_admin` → `with_registrar`; the Records Officer's copy replaces the Registrar's own version of those colleges in their working timetable, to check or edit |
| `college_registrar_decide` `accept: true, keepMine: true` | University Registrar | `pending_super_admin` → `with_registrar`, keeping the Registrar's own version of those colleges (they must have one); the Records Officer is told |
| `college_registrar_decide` `accept: false` (`note` required) | University Registrar | `pending_super_admin` or `with_registrar` → `draft`, back to the Records Officer |
| `college_send_to_vpaa` | University Registrar | From their working timetable (`draft` they built, or `with_registrar`) → `pending_vpaa`. Also works while `pending_super_admin`: the Registrar's own version goes instead and the Records Officer's request is closed (they're told). If the Registrar changed the Records Officer's version, the Records Officer is told |
| `college_vpaa_decide` (`accept`, `note`) | VPAA | Approve → `published`, locked. Send back (note required) → `with_registrar` |
| `college_unlock` (`note` required) | University Registrar | `published` → `with_registrar`, unlocked; logged. The published copy stays live until a corrected one is approved |
| `college_relock` | University Registrar | Lock an unlocked college again without changes |

Before the VPAA publishes, each college is checked for room double-bookings
against the other colleges' published timetables. If the clash is with a
college whose new version is also waiting, the message says to approve them
together.

## Lock

`apply_college_locks_to_generate`: a full Generate becomes "schedule the rest"
with the locked colleges' published classes as the already-placed classes,
so their rooms and times are blocked and they aren't moved.
`assert_edit_target_not_locked` / `assert_locked_colleges_unchanged`: manual
edits to a locked college's classes are refused.

## Known issue (not changed)

The existing schedule actions (`submit_to_vpaa`, `vpaa_decide`, …) trust the
`actor` field sent by the page instead of the signed-in user's role, so any
request could act as the VPAA. The new per-college actions check the session;
the old ones should too.

## Room rules (legacy-api/room-rules.php)

Data management → Facilities already stores which colleges may use a room
(owner college or "shared by" colleges), a floor (`floor_college_access`) or
a whole building (`exclusive_college_id`). The most specific setting wins.
The auto-scheduler now only places a class in rooms its college may use
(`placement_room_choices`, `assign_rooms_to_groups`, `group_from_members`).
Rooms sent to the page carry `colleges` (empty = any), so editing by hand
warns and asks "Save anyway" — the override.

The editable working timetable now always gets today's room list
(`apply_workspace_to_payload`), not the one saved inside a copy, so room
types and rules are current.

## Compare and clashes (front end)

On For approval, "Compare with my version" (schedule-colleges.js) loads the
submitted copy and the Registrar's working timetable, lists the classes whose
day, time or room differ, and the room or student clashes the Records
Officer's version would cause with the Registrar's other colleges. Choices:
Use the Records Officer's (approve), Keep mine (`keepMine`), Send back. When
using the Records Officer's version with clashes, the Registrar picks who
moves; the Working timetable tab then unschedules those classes and places
them again in free rooms (schedule-page.js `resolveApprovalClashes`, using
the existing `unschedule_class` and `add_meeting` actions), re-checking until
nothing clashes. No new server action for the moving.

## Dean / Program Chair dashboard (2026-10-13)

- `GET api/schedule.php?college_final=1[&term=ID]` → `college_final_for_viewer()` (schedule-colleges.php). Read-only. The college and programs come from the session. Returns the college's approved copy (`published_json`) for the current offering, or a chosen term that has one, plus its status. A Program Chair gets only their programs' classes. Each class is tagged `programId`, matched by name as well, because a saved copy can point at an older program row.
- When the VPAA approves colleges, each college's Dean and Program Chairs get "Your college's timetable is approved", which links to their dashboard (`notification_href_for_role` page `college-timetable`).
- Front end: `resources/js/legacy/college-timetable.js` + `css/legacy/college-timetable.css`, shown on `roles/viewer/dashboard` for Dean and Program Chair. It replaces the old hardcoded CAS department cards. There's a filter by program, search, and Download CSV / PDF (`window.McuTimetablePdf` in `resources/js/curriculum-pdf.js`) / Print.

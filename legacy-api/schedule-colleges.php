<?php
declare(strict_types=1);

/*
 * Per-college timetable approval.
 *
 * Colleges move through the approval steps one at a time (or several at
 * once): Records Officer -> University Registrar -> VPAA -> published. Each
 * term/college pair has a row in term_college_approvals with its status and
 * its own copy at each step (only that college's classes).
 *
 * The term-wide columns in term_schedule_approvals (pending_sa_json,
 * pending_vpaa_json, published_json, status) and the matching
 * schedule_copies slots are rebuilt from these rows after every per-college
 * action, so Reports, the viewer pages and Rooms available keep working.
 * After the older whole-campus actions run, the per-college rows are rebuilt
 * from the term-wide state instead (college_rows_from_term).
 *
 * Published colleges are locked: Generate keeps their published classes and
 * schedules everyone else around them, and edits to their classes are refused.
 * The University Registrar can unlock a published college to fix a mistake
 * (reason required, logged); its published copy stays live until a corrected
 * one is approved.
 *
 * Unlike the older actions, these check the signed-in user's role on the
 * server instead of trusting the "actor" sent by the page.
 */

// with_registrar: the University Registrar approved it (or built it) and is finishing it before sending it to the VPAA
const COLLEGE_STATUSES = ['draft', 'pending_super_admin', 'with_registrar', 'pending_vpaa', 'published'];

function ensure_college_approval_schema(PDO $pdo): void
{
  // CREATE TABLE commits any open transaction in MySQL, so only run it when the
  // table is missing (run_schedule_request also calls this before its transaction)
  if (schema_table_exists($pdo, 'term_college_approvals')) {
    // older tables don't have the with_registrar step yet (ALTER also commits, so same rule)
    $type = (string) ($pdo->query("SHOW COLUMNS FROM term_college_approvals LIKE 'status'")->fetch()['Type'] ?? '');
    if ($type !== '' && strpos($type, 'with_registrar') === false && !$pdo->inTransaction()) {
      $pdo->exec("ALTER TABLE term_college_approvals MODIFY status
        ENUM('draft','pending_super_admin','with_registrar','pending_vpaa','published') NOT NULL DEFAULT 'draft'");
    }
    return;
  }
  $pdo->exec(
    "CREATE TABLE IF NOT EXISTS term_college_approvals (
      term_id INT NOT NULL,
      college_id INT NOT NULL,
      status ENUM('draft','pending_super_admin','with_registrar','pending_vpaa','published') NOT NULL DEFAULT 'draft',
      sa_json LONGTEXT NULL,
      sa_at DATETIME NULL,
      vpaa_json LONGTEXT NULL,
      vpaa_at DATETIME NULL,
      published_json LONGTEXT NULL,
      published_at DATETIME NULL,
      is_locked TINYINT(1) NOT NULL DEFAULT 0,
      unlock_note TEXT NULL,
      unlocked_at DATETIME NULL,
      last_reject_from ENUM('super_admin','vpaa') NULL,
      last_reject_note TEXT NULL,
      last_reject_at DATETIME NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (term_id, college_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
  );
}

// ----- helpers -----

/** Signed-in user's role from the session (not from the request body). */
function college_session_role(PDO $pdo): string
{
  $user = session_db_user($pdo);
  if (!$user) {
    throw new RuntimeException('Log in again, then try that again.');
  }
  return (string) ($user['role'] ?? '');
}

function college_require_role(PDO $pdo, array $roles, string $message): string
{
  $role = college_session_role($pdo);
  if (!in_array($role, $roles, true)) {
    throw new RuntimeException($message);
  }
  return $role;
}

/** Active colleges, id => name. */
function college_names(PDO $pdo): array
{
  $out = [];
  foreach ($pdo->query('SELECT college_id, college_name FROM colleges WHERE is_active = 1 ORDER BY college_name')->fetchAll() as $r) {
    $out[(int) $r['college_id']] = (string) $r['college_name'];
  }
  return $out;
}

function class_college_id(array $class): int
{
  return (int) (parse_id((string) ($class['collegeId'] ?? ''), 'c') ?: 0);
}

/** Only the given college's classes from a timetable snapshot. */
function snapshot_for_college(?array $snap, int $collegeId): ?array
{
  if (!$snap || !is_array($snap['classes'] ?? null)) {
    return null;
  }
  $classes = array_values(array_filter($snap['classes'], static function ($c) use ($collegeId) {
    return is_array($c) && class_college_id($c) === $collegeId;
  }));
  if (!$classes) {
    return null;
  }
  $out = $snap;
  $out['classes'] = $classes;
  $out['summary'] = schedule_summary($classes);
  $out['fingerprint'] = schedule_fingerprint($classes);
  $out['collegeId'] = public_id('c', $collegeId);
  return $out;
}

/** Combine college copies into one term-wide snapshot (null when there are none). */
function merge_college_snapshots(array $snaps): ?array
{
  $snaps = array_values(array_filter($snaps, 'is_array'));
  if (!$snaps) {
    return null;
  }
  $classes = [];
  $rooms = [];
  foreach ($snaps as $s) {
    foreach ($s['classes'] ?? [] as $c) {
      $classes[] = $c;
    }
    foreach ($s['rooms'] ?? [] as $r) {
      if (is_array($r) && isset($r['id'])) {
        $rooms[(string) $r['id']] = $r;
      }
    }
  }
  $out = $snaps[0];
  unset($out['collegeId']);
  $out['classes'] = $classes;
  $out['rooms'] = array_values($rooms);
  $out['summary'] = schedule_summary($classes);
  $out['fingerprint'] = schedule_fingerprint($classes);
  $out['colleges'] = array_values(array_filter(array_map(static fn ($s) => $s['collegeId'] ?? null, $snaps)));
  return $out;
}

function decode_snapshot($json): ?array
{
  if (!is_string($json) || trim($json) === '') {
    return null;
  }
  $d = json_decode($json, true);
  return is_array($d) ? $d : null;
}

function encode_snapshot(?array $snap): ?string
{
  return $snap ? json_encode($snap, JSON_UNESCAPED_UNICODE) : null;
}

function college_rows(PDO $pdo, int $termId): array
{
  $stmt = $pdo->prepare('SELECT * FROM term_college_approvals WHERE term_id = ?');
  $stmt->execute([$termId]);
  $out = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $out[(int) $r['college_id']] = $r;
  }
  return $out;
}

function college_row(PDO $pdo, int $termId, int $collegeId): array
{
  $rows = college_rows($pdo, $termId);
  if (isset($rows[$collegeId])) {
    return $rows[$collegeId];
  }
  $pdo->prepare('INSERT INTO term_college_approvals (term_id, college_id) VALUES (?, ?)')->execute([$termId, $collegeId]);
  return college_rows($pdo, $termId)[$collegeId];
}

function save_college_row(PDO $pdo, int $termId, int $collegeId, array $fields): void
{
  college_row($pdo, $termId, $collegeId);
  $sets = [];
  $vals = [];
  foreach ($fields as $k => $v) {
    $sets[] = $k . ' = ' . ($v === 'NOW()' ? 'NOW()' : '?');
    if ($v !== 'NOW()') {
      $vals[] = $v;
    }
  }
  $vals[] = $termId;
  $vals[] = $collegeId;
  $pdo->prepare('UPDATE term_college_approvals SET ' . implode(', ', $sets) . ' WHERE term_id = ? AND college_id = ?')->execute($vals);
}

/** Colleges named in the request ("colleges": ["c-2", ...]); all must be active. */
function requested_college_ids(PDO $pdo, array $body): array
{
  $names = college_names($pdo);
  $ids = [];
  foreach ((array) ($body['colleges'] ?? []) as $raw) {
    $id = (int) (parse_id((string) $raw, 'c') ?: 0);
    if (!$id || !isset($names[$id])) {
      throw new RuntimeException('Pick colleges from the list.');
    }
    $ids[$id] = $id;
  }
  if (!$ids) {
    throw new RuntimeException('Choose at least one college.');
  }
  return array_values($ids);
}

function college_label_list(PDO $pdo, array $ids): string
{
  $names = college_names($pdo);
  $labels = array_map(static fn ($id) => $names[$id] ?? ('College ' . $id), $ids);
  if (count($labels) <= 2) {
    return implode(' and ', $labels);
  }
  return implode(', ', array_slice($labels, 0, -1)) . ', and ' . end($labels);
}

/** Meetings of $a that use the same room at an overlapping time as meetings of $b. */
function find_room_clashes(array $a, array $b): array
{
  $busy = [];
  foreach ($b as $c) {
    foreach ((array) ($c['meetings'] ?? []) as $m) {
      if (!empty($m['roomId']) && !empty($m['day'])) {
        $busy[$m['roomId'] . '|' . $m['day']][] = [(string) $m['start'], (string) $m['end'], (string) ($c['courseCode'] ?? '')];
      }
    }
  }
  $out = [];
  foreach ($a as $c) {
    foreach ((array) ($c['meetings'] ?? []) as $m) {
      foreach ($busy[($m['roomId'] ?? '') . '|' . ($m['day'] ?? '')] ?? [] as [$s, $e, $other]) {
        if ((string) $m['start'] < $e && (string) $m['end'] > $s) {
          $out[] = ($c['courseCode'] ?? '') . ' and ' . $other . ' in ' . ($m['roomCode'] ?? 'the same room') . ' on ' . $m['day'];
        }
      }
    }
  }
  return array_values(array_unique($out));
}

// ----- keeping the term-wide copies in step -----

/** Rebuild the term-wide columns and copy slots from the per-college rows. */
function term_from_college_rows(PDO $pdo, int $termId): void
{
  $rows = college_rows($pdo, $termId);
  $sa = [];
  $vpaa = [];
  $pub = [];
  foreach ($rows as $r) {
    if ($r['status'] === 'pending_super_admin' && ($s = decode_snapshot($r['sa_json']))) {
      $sa[] = $s;
    }
    if ($r['status'] === 'pending_vpaa' && ($s = decode_snapshot($r['vpaa_json']))) {
      $vpaa[] = $s;
    }
    if ($s = decode_snapshot($r['published_json'])) {
      $pub[] = $s;
    }
  }
  $saSnap = merge_college_snapshots($sa);
  $vpaaSnap = merge_college_snapshots($vpaa);
  $pubSnap = merge_college_snapshots($pub);
  $status = $saSnap ? 'pending_super_admin' : ($vpaaSnap ? 'pending_vpaa' : ($pubSnap ? 'published' : 'draft'));

  approval_row($pdo, $termId);
  $pdo->prepare(
    'UPDATE term_schedule_approvals
     SET status = ?,
         pending_sa_json = ?, pending_sa_at = IF(? IS NULL, NULL, COALESCE(pending_sa_at, NOW())),
         pending_vpaa_json = ?, pending_vpaa_at = IF(? IS NULL, NULL, COALESCE(pending_vpaa_at, NOW())),
         published_json = ?, published_at = IF(? IS NULL, published_at, COALESCE(published_at, NOW()))
     WHERE term_id = ?'
  )->execute([
    $status,
    encode_snapshot($saSnap), encode_snapshot($saSnap),
    encode_snapshot($vpaaSnap), encode_snapshot($vpaaSnap),
    encode_snapshot($pubSnap), encode_snapshot($pubSnap),
    $termId,
  ]);
  foreach (['admin_submitted' => $saSnap, 'vpaa_pending' => $vpaaSnap, 'vpaa_final' => $pubSnap] as $slot => $snap) {
    if ($snap) {
      upsert_workspace_copy($pdo, $termId, $slot, $snap);
    } else {
      clear_workspace_copy($pdo, $termId, $slot);
    }
  }
}

/**
 * After an older whole-campus action, rebuild the per-college rows from the
 * term-wide state so both views agree. Keeps each college's lock and unlock note.
 */
function college_rows_from_term(PDO $pdo, int $termId): void
{
  ensure_college_approval_schema($pdo);
  $row = approval_row($pdo, $termId);
  $sa = decode_snapshot($row['pending_sa_json'] ?? null);
  $vpaa = decode_snapshot($row['pending_vpaa_json'] ?? null);
  $pub = decode_snapshot($row['published_json'] ?? null);
  $existing = college_rows($pdo, $termId);
  foreach (array_keys(college_names($pdo)) as $cid) {
    $saC = snapshot_for_college($sa, $cid);
    $vpaaC = snapshot_for_college($vpaa, $cid);
    $pubC = snapshot_for_college($pub, $cid);
    if (!$saC && !$vpaaC && !$pubC && !isset($existing[$cid])) {
      continue;
    }
    $prev = $existing[$cid] ?? [];
    $status = $saC ? 'pending_super_admin' : ($vpaaC ? 'pending_vpaa' : ($pubC ? 'published' : 'draft'));
    if ($status === 'draft' && ($prev['status'] ?? '') === 'with_registrar') {
      $status = 'with_registrar';
    }
    $fields = [
      'status' => $status,
      'sa_json' => encode_snapshot($saC),
      'vpaa_json' => encode_snapshot($vpaaC),
      'published_json' => encode_snapshot($pubC),
      'last_reject_from' => $row['last_reject_from'] ?? null,
      'last_reject_note' => $row['last_reject_note'] ?? null,
      'last_reject_at' => $row['last_reject_at'] ?? null,
    ];
    if ($saC) {
      $fields['sa_at'] = $row['pending_sa_at'] ?? null;
    }
    if ($vpaaC) {
      $fields['vpaa_at'] = $row['pending_vpaa_at'] ?? null;
    }
    if ($pubC) {
      $fields['published_at'] = $row['published_at'] ?? null;
      // newly published through the old flow: lock it; otherwise keep the current lock
      $fields['is_locked'] = ($prev['status'] ?? '') === 'published' || !$prev ? (int) ($prev['is_locked'] ?? 1) : 1;
    }
    save_college_row($pdo, $termId, $cid, $fields);
  }
}

/** Set up the per-college rows the first time a term is looked at. */
function ensure_college_rows(PDO $pdo, int $termId): void
{
  ensure_college_approval_schema($pdo);
  if (!college_rows($pdo, $termId)) {
    college_rows_from_term($pdo, $termId);
  }
}

/** Published colleges whose classes Generate must keep (college id => published classes). */
function locked_college_classes(PDO $pdo, int $termId): array
{
  ensure_college_rows($pdo, $termId);
  $out = [];
  foreach (college_rows($pdo, $termId) as $cid => $r) {
    if ($r['status'] === 'published' && (int) $r['is_locked'] === 1 && ($snap = decode_snapshot($r['published_json']))) {
      $out[$cid] = $snap['classes'] ?? [];
    }
  }
  return $out;
}

/**
 * Generate: keep locked colleges' published classes and schedule everyone else
 * around them (they become the "already placed" seed of Schedule remaining).
 */
function apply_college_locks_to_generate(PDO $pdo, int $termId, array &$body): void
{
  $locked = locked_college_classes($pdo, $termId);
  if (!$locked) {
    return;
  }
  $keep = [];
  foreach ($locked as $classes) {
    foreach ($classes as $c) {
      $keep[] = $c;
    }
  }
  $seed = [];
  if (!empty($body['onlyUnscheduled']) && is_array($body['seedPrivateClasses'] ?? null)) {
    foreach ($body['seedPrivateClasses'] as $c) {
      if (is_array($c) && !isset($locked[class_college_id($c)])) {
        $seed[] = $c;
      }
    }
  }
  $body['onlyUnscheduled'] = true;
  $body['seedPrivateClasses'] = array_merge($keep, $seed);
}

/**
 * Refuse an edit aimed at a locked college's class before any other checks,
 * so the message says why (the request names a meeting, class, or section).
 */
function assert_edit_target_not_locked(PDO $pdo, int $termId, array $body, array $classes): void
{
  $locked = locked_college_classes($pdo, $termId);
  if (!$locked) {
    return;
  }
  $meetingId = (string) ($body['meetingId'] ?? '');
  $classId = (string) ($body['offeringId'] ?? $body['classId'] ?? '');
  $sectionId = (string) ($body['sectionId'] ?? '');
  foreach ($classes as $c) {
    if (!is_array($c) || !isset($locked[class_college_id($c)])) {
      continue;
    }
    $hit = ($classId !== '' && (string) ($c['id'] ?? '') === $classId)
      || ($sectionId !== '' && (string) ($c['sectionId'] ?? '') === $sectionId);
    if (!$hit && $meetingId !== '') {
      foreach ((array) ($c['meetings'] ?? []) as $m) {
        if ((string) ($m['id'] ?? '') === $meetingId) {
          $hit = true;
          break;
        }
      }
    }
    if ($hit) {
      throw new RuntimeException(college_label_list($pdo, [class_college_id($c)]) . ' is published and locked. The University Registrar can unlock it to fix a mistake.');
    }
  }
}

/** Refuse an edit that changed a locked college's classes. */
function assert_locked_colleges_unchanged(PDO $pdo, int $termId, array $before, array $after): void
{
  $locked = locked_college_classes($pdo, $termId);
  if (!$locked) {
    return;
  }
  $pick = static function (array $classes) use ($locked): array {
    $out = [];
    foreach ($classes as $c) {
      if (is_array($c) && isset($locked[class_college_id($c)])) {
        $out[(string) ($c['id'] ?? '')] = json_encode($c['meetings'] ?? []);
      }
    }
    ksort($out);
    return $out;
  };
  $a = $pick($before);
  $b = $pick($after);
  if ($a === $b) {
    return;
  }
  $changed = [];
  foreach ($after as $c) {
    $id = (string) ($c['id'] ?? '');
    if (is_array($c) && isset($locked[class_college_id($c)]) && ($a[$id] ?? null) !== ($b[$id] ?? null)) {
      $changed[class_college_id($c)] = true;
    }
  }
  foreach ($before as $c) {
    $id = (string) ($c['id'] ?? '');
    if (is_array($c) && isset($locked[class_college_id($c)]) && !array_key_exists($id, $b)) {
      $changed[class_college_id($c)] = true;
    }
  }
  $names = college_label_list($pdo, array_keys($changed) ?: array_keys($locked));
  throw new RuntimeException($names . ' is published and locked. The University Registrar can unlock it to fix a mistake.');
}

// ----- status for the page -----

function college_status_overview(PDO $pdo, array $query): array
{
  ensure_college_approval_schema($pdo);
  $role = college_session_role($pdo);
  [$year, $sem, $termId] = resolve_term_for_year_sem($pdo, ['schoolYear' => $query['year'] ?? '', 'termCode' => $query['sem'] ?? '1'], false);
  ensure_college_rows($pdo, $termId);
  $rows = college_rows($pdo, $termId);
  $mine = in_array($role, ['admin', 'super_admin'], true) ? load_workspace_copy($pdo, $termId, private_slot_for_actor($role)) : null;

  $out = [];
  foreach (college_names($pdo) as $cid => $name) {
    $r = $rows[$cid] ?? [];
    $status = (string) ($r['status'] ?? 'draft');
    $work = snapshot_for_college($mine, $cid);
    $workClasses = $work ? count($work['classes']) : 0;
    $sent = in_array($status, ['pending_super_admin', 'with_registrar'], true) ? snapshot_for_college(decode_snapshot($r['sa_json'] ?? null), $cid) : null;
    $workUnscheduled = $work ? snapshot_unscheduled_count($work) : 0;
    $count = static fn ($json) => ($s = decode_snapshot($json)) ? count($s['classes'] ?? []) : 0;
    $out[] = [
      'id' => public_id('c', $cid),
      'name' => $name,
      'status' => $status,
      'locked' => $status === 'published' && (int) ($r['is_locked'] ?? 0) === 1,
      'hasPublished' => $count($r['published_json'] ?? null) > 0,
      'publishedClasses' => $count($r['published_json'] ?? null),
      'publishedAt' => $r['published_at'] ?? null,
      'pendingClasses' => $status === 'pending_super_admin' ? $count($r['sa_json'] ?? null) : ($status === 'pending_vpaa' ? $count($r['vpaa_json'] ?? null) : 0),
      'sentAt' => $status === 'pending_super_admin' ? ($r['sa_at'] ?? null) : ($status === 'pending_vpaa' ? ($r['vpaa_at'] ?? null) : null),
      'workspaceClasses' => $workClasses,
      'workspaceUnscheduled' => $workUnscheduled,
      // can this college be sent from your own schedule right now?
      'readyToSend' => $workClasses > 0 && $workUnscheduled === 0
        && ($status === 'draft' || ($role === 'super_admin' && in_array($status, ['with_registrar', 'pending_super_admin'], true))),
      'fromRecordsOfficer' => $status === 'with_registrar' && decode_snapshot($r['sa_json'] ?? null) !== null,
      // the Registrar's working timetable differs from what the Records Officer sent
      'editedFromSent' => $role === 'super_admin' && $sent && $work
        && schedule_fingerprint($work['classes']) !== schedule_fingerprint($sent['classes']),
      'lastRejectFrom' => $r['last_reject_from'] ?? null,
      'lastRejectNote' => $r['last_reject_note'] ?? null,
      'lastRejectAt' => $r['last_reject_at'] ?? null,
      'unlockNote' => $r['unlock_note'] ?? null,
      'unlockedAt' => $r['unlocked_at'] ?? null,
    ];
  }
  return ['term' => ['schoolYear' => $year, 'termCode' => $sem, 'label' => term_offering_label($year, $sem)], 'role' => $role, 'colleges' => $out];
}

/**
 * The approved (VPAA-published) timetable of the signed-in Dean's or Program Chair's
 * college, for their dashboard. A Program Chair gets only their own programs.
 * Read-only; the college and programs come from the session, never the request.
 */
function college_final_for_viewer(PDO $pdo, array $query): array
{
  ensure_college_approval_schema($pdo);
  $me = curriculum_review_user($pdo);
  if (!$me || !in_array($me['role'], ['dean', 'program_chair'], true) || $me['college_id'] <= 0) {
    throw new RuntimeException('Only a Dean or a Program Chair can open this.');
  }
  $programs = curriculum_programs_for($pdo, $me);
  $collegeName = (string) ($me['college_name'] ?? '');

  // terms where this college has an approved copy, newest first
  $stmt = $pdo->prepare(
    'SELECT a.term_id, t.term_code, sy.label, t.is_active
     FROM term_college_approvals a
     JOIN terms t ON t.term_id = a.term_id
     JOIN school_years sy ON sy.school_year_id = t.school_year_id
     WHERE a.college_id = ? AND a.published_json IS NOT NULL
     ORDER BY sy.label DESC, t.term_code DESC'
  );
  $stmt->execute([$me['college_id']]);
  $terms = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
    $terms[(int) $t['term_id']] = ['id' => (int) $t['term_id'], 'label' => term_offering_label((string) $t['label'], (string) $t['term_code'])];
  }
  $current = current_offering_term($pdo);
  $termId = (int) ($query['term'] ?? 0);
  if (!$termId || (!isset($terms[$termId]) && (!$current || $termId !== $current['termId']))) {
    $termId = $current ? $current['termId'] : (int) (array_key_first($terms) ?? 0);
  }
  if ($current && !isset($terms[$current['termId']])) {
    $terms = [$current['termId'] => ['id' => $current['termId'], 'label' => term_offering_label($current['schoolYear'], $current['termCode'])]] + $terms;
  }

  $row = $termId ? college_row($pdo, $termId, $me['college_id']) : [];
  $snap = snapshot_for_college(decode_snapshot($row['published_json'] ?? null), $me['college_id']);
  $classes = $snap ? $snap['classes'] : [];
  // tag each class with the viewer's program; matched by name too, since a saved copy can
  // point at an older program row (e.g. a program re-created under another department)
  $nameOf = [];
  $stmt = $pdo->prepare('SELECT program_id, program_name FROM programs WHERE college_id = ?');
  $stmt->execute([$me['college_id']]);
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $nameOf[(int) $p['program_id']] = strtolower(trim((string) $p['program_name']));
  }
  $mineByName = [];
  foreach ($programs as $pid => $p) {
    $mineByName[strtolower(trim((string) $p['program_name']))] = public_id('d', (int) $pid);
  }
  foreach ($classes as &$c) {
    $pid = (int) (parse_id((string) ($c['departmentId'] ?? ''), 'd') ?: 0);
    $c['programId'] = isset($programs[$pid]) ? public_id('d', $pid) : ($mineByName[$nameOf[$pid] ?? ''] ?? null);
  }
  unset($c);
  if ($me['role'] === 'program_chair') {
    $classes = array_values(array_filter($classes, static fn ($c) => $c['programId'] !== null));
  }
  return [
    'role' => $me['role'],
    'collegeName' => $collegeName,
    'term' => $termId && isset($terms[$termId]) ? $terms[$termId] : null,
    'terms' => array_values($terms),
    'status' => (string) ($row['status'] ?? 'draft'),
    'publishedAt' => $snap ? ($row['published_at'] ?? null) : null,
    'programs' => array_values(array_map(static fn ($p) => ['id' => public_id('d', (int) $p['program_id']), 'name' => (string) $p['program_name']], $programs)),
    'classes' => $classes,
  ];
}

// ----- actions -----

/** Snapshot of the actor's own schedule, split by college; every chosen college must be complete. */
function college_copies_from_workspace(PDO $pdo, int $termId, string $year, string $sem, string $role, array $ids, string $action): array
{
  $snap = stamp_copy_origin(snapshot_from_workspace_source($pdo, $year, $sem, $termId, 'mine', $role), 'mine', $role, $action);
  $names = college_names($pdo);
  $copies = [];
  foreach ($ids as $cid) {
    $c = snapshot_for_college($snap, $cid);
    if (!$c) {
      throw new RuntimeException($names[$cid] . ' has no classes in your schedule yet. Generate the schedule first.');
    }
    $n = snapshot_unscheduled_count($c);
    if ($n > 0) {
      throw new RuntimeException($names[$cid] . ' still has ' . $n . ($n === 1 ? ' unscheduled class' : ' unscheduled classes') . '. Place every class before sending it.');
    }
    $copies[$cid] = $c;
  }
  return $copies;
}

/** Replace these colleges' classes in the Registrar's working timetable with the given copies. */
function college_put_in_working(PDO $pdo, int $termId, array $copies): void
{
  $slot = private_slot_for_actor('super_admin');
  $mine = load_workspace_copy($pdo, $termId, $slot) ?: empty_workspace_snapshot();
  $keep = array_values(array_filter($mine['classes'] ?? [], static fn ($c) => is_array($c) && !isset($copies[class_college_id($c)])));
  $merged = merge_college_snapshots(array_merge([array_merge($mine, ['classes' => $keep])], array_values($copies)));
  unset($merged['colleges']);
  upsert_workspace_copy($pdo, $termId, $slot, $merged);
}

function college_assert_can_send(array $row, string $name, string $role = 'super_admin'): void
{
  $status = (string) ($row['status'] ?? 'draft');
  if ($status === 'pending_super_admin') {
    throw new RuntimeException($name . ' is already waiting for the University Registrar.');
  }
  if ($status === 'with_registrar' && $role === 'admin') {
    throw new RuntimeException($name . ' is with the University Registrar now. They can send it back to you if it needs changes.');
  }
  if ($status === 'pending_vpaa') {
    throw new RuntimeException($name . ' is already waiting for the VPAA.');
  }
  if ($status === 'published' && (int) ($row['is_locked'] ?? 0) === 1) {
    throw new RuntimeException($name . ' is published and locked. Unlock it first to send a corrected version.');
  }
}

function college_action(PDO $pdo, array $body, string $action): array
{
  ensure_college_approval_schema($pdo);
  [$year, $sem, $termId] = resolve_term_for_year_sem($pdo, $body, false);
  ensure_college_rows($pdo, $termId);
  $ids = requested_college_ids($pdo, $body);
  $names = college_names($pdo);
  $rows = college_rows($pdo, $termId);
  $note = trim((string) ($body['note'] ?? ''));
  $term = term_offering_label($year, $sem);
  $list = college_label_list($pdo, $ids);
  $row = static fn ($cid) => $rows[$cid] ?? ['status' => 'draft'];

  switch ($action) {
    case 'college_submit_to_registrar': {
      $role = college_require_role($pdo, ['admin'], 'Only the Records Officer sends colleges to the University Registrar.');
      foreach ($ids as $cid) {
        college_assert_can_send($row($cid), $names[$cid], 'admin');
      }
      foreach (college_copies_from_workspace($pdo, $termId, $year, $sem, $role, $ids, 'submit') as $cid => $copy) {
        save_college_row($pdo, $termId, $cid, [
          'status' => 'pending_super_admin', 'sa_json' => encode_snapshot($copy), 'sa_at' => 'NOW()',
          'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null,
        ]);
      }
      notify_roles($pdo, ['super_admin'], 'timetable_send', 'Records Officer sent timetables for review',
        'Records Officer sent ' . $list . ' (' . $term . ') for your review.', 'create-schedule.html?ws=submitted', 'Timetable');
      record_transaction($pdo, 'Submit', 'schedule', 'Sent ' . $list . ' (' . $term . ') to the University Registrar.');
      break;
    }
    case 'college_retract': {
      $role = college_require_role($pdo, ['admin', 'super_admin'], 'Only the Records Officer or University Registrar can take back a timetable.');
      $from = $role === 'admin' ? 'pending_super_admin' : 'pending_vpaa';
      foreach ($ids as $cid) {
        if (($row($cid)['status'] ?? '') !== $from) {
          throw new RuntimeException($names[$cid] . ($role === 'admin' ? ' isn’t waiting for the University Registrar.' : ' isn’t waiting for the VPAA.'));
        }
      }
      foreach ($ids as $cid) {
        // back to draft so it can be fixed and sent again; a published copy stays live
        save_college_row($pdo, $termId, $cid, $from === 'pending_super_admin'
          ? ['status' => 'draft', 'is_locked' => 0, 'sa_json' => null, 'sa_at' => null]
          : ['status' => 'with_registrar', 'is_locked' => 0, 'vpaa_json' => null, 'vpaa_at' => null]);
      }
      notify_roles($pdo, [$role === 'admin' ? 'super_admin' : 'vpaa'], 'timetable_return', 'A timetable was taken back',
        ($role === 'admin' ? 'Records Officer' : 'University Registrar') . ' took back ' . $list . ' (' . $term . ').',
        $role === 'admin' ? 'create-schedule.html?ws=submitted' : 'final-report.html?ws=awaiting', 'Timetable');
      record_transaction($pdo, 'Retract', 'schedule', 'Took back ' . $list . ' (' . $term . ').');
      break;
    }
    case 'college_registrar_decide': {
      college_require_role($pdo, ['super_admin'], 'Only the University Registrar can approve or send back these colleges.');
      $accept = !empty($body['accept']);
      if (!$accept && $note === '') {
        throw new RuntimeException('Say what the Records Officer should fix before sending it back.');
      }
      foreach ($ids as $cid) {
        $st = (string) ($row($cid)['status'] ?? '');
        if ($accept ? $st !== 'pending_super_admin' : !in_array($st, ['pending_super_admin', 'with_registrar'], true)) {
          throw new RuntimeException($names[$cid] . ($accept ? ' isn’t waiting for your approval.' : ' isn’t with you to send back.'));
        }
      }
      if ($accept && !empty($body['keepMine'])) {
        // approve, but finish with the Registrar's own version of these colleges
        $mine = load_workspace_copy($pdo, $termId, private_slot_for_actor('super_admin'));
        foreach ($ids as $cid) {
          if (!snapshot_for_college($mine, $cid)) {
            throw new RuntimeException($names[$cid] . ' isn’t in your Working timetable, so there is no version of yours to keep. Use the Records Officer’s instead.');
          }
        }
        foreach ($ids as $cid) {
          save_college_row($pdo, $termId, $cid, ['status' => 'with_registrar',
            'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null]);
        }
        notify_roles($pdo, ['admin'], 'timetable_send', 'University Registrar approved your timetables',
          'University Registrar approved ' . $list . ' (' . $term . ') and will use their own version of the timetable.', 'create-schedule.html?ws=submitted', 'Timetable');
        record_transaction($pdo, 'Approve', 'schedule', 'Approved ' . $list . ' (' . $term . '), keeping the University Registrar’s version.');
      } elseif ($accept) {
        // the Records Officer's copy replaces the Registrar's own version of these colleges
        $copies = [];
        foreach ($ids as $cid) {
          $sent = snapshot_for_college(decode_snapshot($row($cid)['sa_json'] ?? null), $cid);
          if (!$sent) {
            throw new RuntimeException($names[$cid] . ' has no submitted timetable to approve.');
          }
          $copies[$cid] = $sent;
        }
        college_put_in_working($pdo, $termId, $copies);
        foreach ($ids as $cid) {
          save_college_row($pdo, $termId, $cid, ['status' => 'with_registrar',
            'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null]);
        }
        notify_roles($pdo, ['admin'], 'timetable_send', 'University Registrar approved your timetables',
          'University Registrar approved ' . $list . ' (' . $term . '). They’ll send it to the VPAA.', 'create-schedule.html?ws=submitted', 'Timetable');
        record_transaction($pdo, 'Approve', 'schedule', 'Approved ' . $list . ' (' . $term . ') from the Records Officer.');
      } else {
        foreach ($ids as $cid) {
          save_college_row($pdo, $termId, $cid, ['status' => 'draft', 'is_locked' => 0, 'sa_json' => null, 'sa_at' => null,
            'last_reject_from' => 'super_admin', 'last_reject_note' => $note, 'last_reject_at' => 'NOW()']);
        }
        notify_roles($pdo, ['admin'], 'timetable_return', 'University Registrar sent timetables back',
          'University Registrar sent ' . $list . ' (' . $term . ') back. ' . notify_note_snippet($note), 'create-schedule.html?ws=mine', 'Timetable');
        record_transaction($pdo, 'Reject', 'schedule', 'Sent ' . $list . ' (' . $term . ') back to the Records Officer. Note: ' . mb_substr($note, 0, 180));
      }
      break;
    }
    case 'college_send_to_vpaa': {
      $role = college_require_role($pdo, ['super_admin'], 'Only the University Registrar sends colleges to the VPAA.');
      $replaced = []; // the Records Officer's request is closed: the Registrar sends their own version instead
      foreach ($ids as $cid) {
        if (($row($cid)['status'] ?? '') === 'pending_super_admin') {
          $replaced[] = $cid;
          continue;
        }
        college_assert_can_send($row($cid), $names[$cid]);
      }
      $edited = [];
      foreach (college_copies_from_workspace($pdo, $termId, $year, $sem, $role, $ids, 'submit_to_vpaa') as $cid => $copy) {
        $sent = snapshot_for_college(decode_snapshot($row($cid)['sa_json'] ?? null), $cid);
        if (!in_array($cid, $replaced, true) && $sent && schedule_fingerprint($sent['classes']) !== schedule_fingerprint($copy['classes'])) {
          $edited[] = $cid; // the Records Officer's version, changed by the Registrar
        }
        save_college_row($pdo, $termId, $cid, [
          'status' => 'pending_vpaa', 'vpaa_json' => encode_snapshot($copy), 'vpaa_at' => 'NOW()', 'sa_json' => null, 'sa_at' => null,
          'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null,
        ]);
      }
      notify_roles($pdo, ['vpaa'], 'timetable_send', 'Timetables are waiting for your approval',
        'University Registrar sent ' . $list . ' (' . $term . ') for your approval.', 'final-report.html?ws=awaiting', 'Timetable');
      if ($replaced) {
        notify_roles($pdo, ['admin'], 'timetable_send', 'University Registrar sent their own version',
          'University Registrar sent their own version of ' . college_label_list($pdo, $replaced) . ' (' . $term . ') to the VPAA, so your request for it is closed.', 'final-report.html?ws=awaiting', 'Timetable');
      }
      if ($edited) {
        notify_roles($pdo, ['admin'], 'timetable_send', 'University Registrar edited your timetables',
          'University Registrar made changes to ' . college_label_list($pdo, $edited) . ' (' . $term . ') and sent them to the VPAA.', 'final-report.html?ws=awaiting', 'Timetable');
      }
      record_transaction($pdo, 'Submit', 'schedule', 'Sent ' . $list . ' (' . $term . ') to the VPAA.');
      break;
    }
    case 'college_vpaa_decide': {
      college_require_role($pdo, ['vpaa'], 'Only the VPAA can approve or send back these colleges.');
      $accept = !empty($body['accept']);
      if (!$accept && $note === '') {
        throw new RuntimeException('Say what the University Registrar should fix before sending it back.');
      }
      foreach ($ids as $cid) {
        if (($row($cid)['status'] ?? '') !== 'pending_vpaa') {
          throw new RuntimeException($names[$cid] . ' isn’t waiting for your approval.');
        }
      }
      if ($accept) {
        // no room double-booked against colleges that are already published (or approved together)
        $accepted = [];
        foreach ($ids as $cid) {
          $classes = decode_snapshot($row($cid)['vpaa_json'])['classes'] ?? [];
          $clash = find_room_clashes($classes, $accepted);
          $withPending = [];
          foreach ($rows as $oid => $r) {
            if (in_array($oid, $ids, true) || !($s = decode_snapshot($r['published_json']))) {
              continue;
            }
            $c = find_room_clashes($classes, $s['classes'] ?? []);
            if ($c) {
              $clash = array_merge($clash, $c);
              if (($r['status'] ?? '') === 'pending_vpaa') {
                $withPending[] = $names[$oid] ?? '';
              }
            }
          }
          if ($clash) {
            $hint = $withPending
              ? ' These rooms are free in the new ' . implode(' and ', $withPending) . ' timetable that is also waiting, so approve them together.'
              : ' Send it back so the rooms can be changed.';
            throw new RuntimeException($names[$cid] . ' uses rooms that are still booked in the published timetable: '
              . implode('; ', array_slice($clash, 0, 3)) . (count($clash) > 3 ? ' and ' . (count($clash) - 3) . ' more' : '') . '.' . $hint);
          }
          $accepted = array_merge($accepted, $classes);
        }
      }
      foreach ($ids as $cid) {
        $r = $row($cid);
        save_college_row($pdo, $termId, $cid, $accept
          ? ['status' => 'published', 'published_json' => $r['vpaa_json'], 'published_at' => 'NOW()', 'vpaa_json' => null, 'vpaa_at' => null,
             'is_locked' => 1, 'unlock_note' => null, 'unlocked_at' => null,
             'last_reject_from' => null, 'last_reject_note' => null, 'last_reject_at' => null]
          : ['status' => 'with_registrar', 'vpaa_json' => null, 'vpaa_at' => null, 'is_locked' => 0,
             'last_reject_from' => 'vpaa', 'last_reject_note' => $note, 'last_reject_at' => 'NOW()']);
      }
      if ($accept) {
        notify_roles($pdo, ['super_admin', 'admin'], 'timetable_published', 'VPAA published timetables',
          'The VPAA approved and published ' . $list . ' (' . $term . ').', 'final-report', 'Timetable');
        foreach ($ids as $cid) {
          notify_roles($pdo, ['dean', 'program_chair'], 'timetable_published', 'Your college’s timetable is approved',
            'The VPAA approved the ' . ($names[$cid] ?? 'college') . ' timetable for ' . $term . '. Check it and download a copy from your dashboard.',
            'college-timetable', 'Timetable', $cid);
        }
        record_transaction($pdo, 'Publish', 'schedule', 'Published ' . $list . ' (' . $term . ').');
      } else {
        notify_roles($pdo, ['super_admin'], 'timetable_return', 'VPAA sent timetables back',
          'The VPAA sent ' . $list . ' (' . $term . ') back. ' . notify_note_snippet($note), 'create-schedule.html?ws=submitted', 'Timetable');
        record_transaction($pdo, 'Reject', 'schedule', 'Sent ' . $list . ' (' . $term . ') back to the University Registrar. Note: ' . mb_substr($note, 0, 180));
      }
      break;
    }
    case 'college_unlock': {
      college_require_role($pdo, ['super_admin'], 'Only the University Registrar can unlock a published college.');
      if ($note === '') {
        throw new RuntimeException('Say what needs fixing. The reason is kept in the Transaction log.');
      }
      foreach ($ids as $cid) {
        $r = $row($cid);
        if (($r['status'] ?? '') !== 'published' || (int) ($r['is_locked'] ?? 0) !== 1) {
          throw new RuntimeException($names[$cid] . ' isn’t a locked, published college.');
        }
      }
      foreach ($ids as $cid) {
        // the published copy stays live until a corrected one is approved
        save_college_row($pdo, $termId, $cid, ['status' => 'with_registrar', 'is_locked' => 0, 'unlock_note' => $note, 'unlocked_at' => 'NOW()']);
      }
      notify_roles($pdo, ['vpaa', 'admin'], 'timetable_return', 'A published timetable was unlocked for corrections',
        'University Registrar unlocked ' . $list . ' (' . $term . ') to fix a mistake. ' . notify_note_snippet($note), 'final-report', 'Timetable');
      record_transaction($pdo, 'Unlock', 'schedule', 'Unlocked ' . $list . ' (' . $term . ') for corrections. Reason: ' . mb_substr($note, 0, 180));
      break;
    }
    case 'college_relock': {
      college_require_role($pdo, ['super_admin'], 'Only the University Registrar can lock a college again.');
      foreach ($ids as $cid) {
        $r = $row($cid);
        if (!in_array($r['status'] ?? '', ['draft', 'with_registrar'], true) || decode_snapshot($r['published_json'] ?? null) === null) {
          throw new RuntimeException($names[$cid] . ' has no unlocked published timetable to lock.');
        }
      }
      foreach ($ids as $cid) {
        save_college_row($pdo, $termId, $cid, ['status' => 'published', 'is_locked' => 1]);
      }
      record_transaction($pdo, 'Lock', 'schedule', 'Locked ' . $list . ' (' . $term . ') again without changes.');
      break;
    }
    default:
      throw new RuntimeException('Unknown college action.');
  }

  term_from_college_rows($pdo, $termId);
  return college_status_overview($pdo, ['year' => $year, 'sem' => $sem]);
}

const COLLEGE_ACTIONS = [
  'college_submit_to_registrar',
  'college_retract',
  'college_registrar_decide',
  'college_send_to_vpaa',
  'college_vpaa_decide',
  'college_unlock',
  'college_relock',
];

/** Older whole-campus approval actions; the per-college rows are rebuilt after them. */
const TERM_WIDE_APPROVAL_ACTIONS = [
  'ready_for_super_admin',
  'submit_to_super_admin',
  'retract_admin_submission',
  'approve_admin_submission',
  'reject_admin_submission',
  'submit_to_vpaa',
  'retract_vpaa_pending',
  'super_admin_decide',
  'super_admin_send_live',
  'vpaa_decide',
  'archive_published_offering',
];

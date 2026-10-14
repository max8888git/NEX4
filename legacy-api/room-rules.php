<?php
/**
 * Which colleges may use each room, from what Data management → Facilities
 * already stores. The most specific setting wins:
 *   1. the room (owner college, or "shared by" colleges)
 *   2. the room's floor (floor_college_access)
 *   3. the building ("whole building for one college")
 * No setting means any college may use it.
 *
 * The auto-scheduler only places a class in a room its college may use.
 * Editing by hand can still pick any room (the page asks to confirm).
 */

/** room_id => [college_id, ...]; rooms open to every college are left out. */
function room_allowed_colleges(PDO $pdo): array
{
  static $byDb = null; // per connection
  $byDb ??= new WeakMap();
  $db = $pdo;
  if (isset($byDb[$db])) {
    return $byDb[$db];
  }
  $cache = [];
  if (!schema_table_exists($pdo, 'rooms')) {
    return $byDb[$db] = $cache;
  }

  $extras = [];
  if (schema_table_exists($pdo, 'facilities_ui_state')) {
    $raw = $pdo->query('SELECT extras FROM facilities_ui_state WHERE state_id = 1')->fetchColumn();
    $decoded = $raw ? json_decode((string) $raw, true) : null;
    $extras = is_array($decoded) ? $decoded : [];
  }
  $allocation = is_array($extras['roomAllocation'] ?? null) ? $extras['roomAllocation'] : [];

  $byFloor = [];
  if (schema_table_exists($pdo, 'floor_college_access')) {
    foreach ($pdo->query('SELECT floor_id, college_id FROM floor_college_access') as $row) {
      $byFloor[(int) $row['floor_id']][] = (int) $row['college_id'];
    }
  }

  $sql = 'SELECT r.room_id, r.room_code, r.floor_id, r.owner_college_id, b.building_code, b.exclusive_college_id
          FROM rooms r
          LEFT JOIN floors f ON f.floor_id = r.floor_id
          LEFT JOIN buildings b ON b.building_id = f.building_id';
  foreach ($pdo->query($sql) as $row) {
    $rid = (int) $row['room_id'];
    $key = (string) $row['building_code'] . '::' . (string) $row['room_code'];
    $alloc = is_array($allocation[$key] ?? null) ? $allocation[$key] : [];
    $colleges = [];
    if (!empty($alloc['multiCollege']) && is_array($alloc['colleges'] ?? null)) {
      foreach ($alloc['colleges'] as $c) {
        $id = (int) (parse_id((string) $c, 'c') ?: 0);
        if ($id) {
          $colleges[] = $id;
        }
      }
    }
    if (!$colleges && $row['owner_college_id']) {
      $colleges = [(int) $row['owner_college_id']];
    }
    if (!$colleges && !empty($byFloor[(int) $row['floor_id']])) {
      $colleges = $byFloor[(int) $row['floor_id']];
    }
    if (!$colleges && $row['exclusive_college_id']) {
      $colleges = [(int) $row['exclusive_college_id']];
    }
    if ($colleges) {
      $cache[$rid] = array_values(array_unique($colleges));
    }
  }
  return $byDb[$db] = $cache;
}

/** Can every college in this group use the room? */
function room_allows_colleges(PDO $pdo, int $roomId, array $collegeIds): bool
{
  $allowed = room_allowed_colleges($pdo)[$roomId] ?? null;
  if ($allowed === null) {
    return true;
  }
  foreach ($collegeIds as $c) {
    if (!in_array((int) $c, $allowed, true)) {
      return false;
    }
  }
  return true;
}

/** College ids of the sections in a placement group. */
function group_college_ids(array $g): array
{
  $ids = [];
  foreach ($g['members'] ?? [] as $m) {
    if (!empty($m['collegeId'])) {
      $ids[(int) $m['collegeId']] = true;
    }
  }
  return array_keys($ids);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function run_org_request(): void
{
$pdo = db();
ensure_department_program_split($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  json_ok(['org' => org_document($pdo)]);
}

if ($method === 'PUT') {
  $body = json_input();
  try {
    $pdo->beginTransaction();
    $beforeOrg = org_audit_state($pdo);
    sync_org($pdo, $body);
    foreach (org_audit_changes($beforeOrg, org_audit_state($pdo)) as $change) {
      record_transaction($pdo, $change['action'], $change['area'], $change['summary']);
    }
    $pdo->commit();
    json_ok(['org' => org_document($pdo)]);
  } catch (Throwable $e) {
    registrar_reraise($e);
    registrar_log_unexpected($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

if ($method === 'POST') {
  $body = json_input();
  $action = (string) ($body['action'] ?? $_GET['action'] ?? '');
  try {
    $pdo->beginTransaction();
    if ($action === 'addCollege') {
      $college = add_college($pdo, (string) ($body['name'] ?? ''), (string) ($body['code'] ?? ''), !empty($body['useDepartments']));
      record_transaction($pdo, 'Add', 'college', 'Added college ' . $college['name'] . '.');
      $pdo->commit();
      json_ok(['college' => $college, 'org' => org_document($pdo)]);
    }
    if ($action === 'addDepartment') {
      $dept = add_department($pdo, (string) ($body['collegeId'] ?? ''), (string) ($body['name'] ?? ''));
      record_transaction($pdo, 'Add', 'department', 'Added department ' . $dept['name'] . '.');
      $pdo->commit();
      json_ok(['department' => $dept, 'org' => org_document($pdo)]);
    }
    if ($action === 'addProgram') {
      $program = add_program(
        $pdo,
        (string) ($body['departmentId'] ?? ''),
        (string) ($body['name'] ?? '')
      );
      record_transaction($pdo, 'Add', 'program', 'Added program ' . (string) ($program['name'] ?? 'a program') . '.');
      $pdo->commit();
      json_ok(['program' => $program, 'org' => org_document($pdo)]);
    }
    $pdo->rollBack();
    json_error('Unknown org action');
  } catch (Throwable $e) {
    registrar_reraise($e);
    registrar_log_unexpected($e);
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    json_error($e->getMessage(), 400);
  }
}

json_error('Unsupported method', 405);
}

if (!defined('REGISTRAR_LIBRARY')) {
  run_org_request();
}

function college_direct_program(array $programs, int $collegeId): ?array
{
  $fallback = null;
  foreach ($programs as $p) {
    if ((int) $p['college_id'] !== $collegeId || (int) $p['is_active'] !== 1) {
      continue;
    }
    $row = [
      'id' => public_id('d', (int) $p['program_id']),
      'name' => (string) $p['program_name'],
    ];
    if ((int) $p['represents_college'] === 1) {
      return $row;
    }
    if ((int) ($p['department_id'] ?? 0) < 1) {
      $fallback = $row;
    }
  }
  return $fallback;
}

function org_document(PDO $pdo): array
{
  $colleges = $pdo->query(
    'SELECT college_id, college_code, college_name, use_departments, archived_label, is_active
     FROM colleges
     ORDER BY college_name'
  )->fetchAll();

  $departments = $pdo->query(
    'SELECT department_id, college_id, department_name, archived_label, is_active
     FROM departments
     ORDER BY department_name'
  )->fetchAll();
  $programs = $pdo->query(
    'SELECT program_id, college_id, department_id, program_code, program_name, represents_college, archived_label, is_active
     FROM programs
     ORDER BY program_name'
  )->fetchAll();

  $programsByDept = [];
  foreach ($programs as $p) {
    $deptId = (int) ($p['department_id'] ?? 0);
    if ($deptId < 1) {
      continue;
    }
    $programsByDept[$deptId][] = $p;
  }
  $deptsByCollege = [];
  foreach ($departments as $d) {
    $deptsByCollege[(int) $d['college_id']][] = $d;
  }

  $active = [];
  $archivedColleges = [];
  $archivedDeptOnly = [];
  $collegeIndex = [];

  foreach ($colleges as $c) {
    $cid = (int) $c['college_id'];
    $useDept = (int) $c['use_departments'] === 1;
    $depts = [];
    if (!$useDept) {
      $directProgram = college_direct_program($programs, $cid);
    } else {
      $directProgram = null;
    foreach ($deptsByCollege[$cid] ?? [] as $d) {
      $did = (int) $d['department_id'];
      $plist = [];
      foreach ($programsByDept[$did] ?? [] as $p) {
        if ((int) $p['is_active'] !== 1) {
          continue;
        }
        $plist[] = [
          'id' => public_id('d', (int) $p['program_id']),
          'name' => $p['program_name'],
        ];
      }
      if ((int) $d['is_active'] === 1) {
        $depts[] = [
          'id' => public_id('dept', $did),
          'name' => $d['department_name'],
          'programs' => $plist,
        ];
      } elseif ((int) $c['is_active'] === 1) {
        $archivedDeptOnly[] = [
          'id' => public_id('dept', $did),
          'deptName' => $d['department_name'],
          'programs' => $plist,
          'parentCode' => $c['college_code'],
          'parentName' => $c['college_name'],
          'archivedLabel' => $d['archived_label'] ?: '',
        ];
      }
    }
    }

    $row = [
      'id' => public_id('c', $cid),
      'name' => $c['college_name'],
      'code' => $c['college_code'],
      'useDepartments' => $useDept,
      'departments' => $depts,
      'program' => $directProgram,
    ];
    $collegeIndex[$cid] = $row;
    if ((int) $c['is_active'] === 1) {
      $active[] = $row;
    } else {
      $row['archivedLabel'] = $c['archived_label'] ?: '';
      $archivedColleges[] = $row;
    }
  }

  return [
    'active' => $active,
    'archivedColleges' => $archivedColleges,
    'archivedDeptOnly' => $archivedDeptOnly,
  ];
}

function add_college(PDO $pdo, string $name, string $code, bool $useDepartments): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the college.');
  }
  $stmt = $pdo->prepare('SELECT college_name FROM colleges WHERE college_name = ? LIMIT 1');
  $stmt->execute([$name]);
  if ($stmt->fetch()) {
    throw new RuntimeException('A college called "' . $name . '" already exists.');
  }
  $code = strtoupper(trim($code));
  if ($code === '') {
    $code = code_from_name($name);
  }
  $code = unique_code($pdo, 'colleges', 'college_code', $code, null, 'college_id');
  $ins = $pdo->prepare(
    'INSERT INTO colleges (college_code, college_name, use_departments, is_active) VALUES (?, ?, ?, 1)'
  );
  $ins->execute([$code, $name, $useDepartments ? 1 : 0]);
  $id = (int) $pdo->lastInsertId();
  if (!$useDepartments) {
    ensure_sole_program($pdo, $id, $name, $code);
  }
  return [
    'id' => public_id('c', $id),
    'name' => $name,
    'code' => $code,
    'useDepartments' => $useDepartments,
    'departments' => [],
  ];
}

function add_department(PDO $pdo, string $collegeId, string $name): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the department.');
  }
  $cid = parse_id($collegeId, 'c');
  if (!$cid) {
    throw new RuntimeException('Pick a college first.');
  }
  $col = $pdo->prepare('SELECT college_id, college_code, college_name FROM colleges WHERE college_id = ?');
  $col->execute([$cid]);
  $college = $col->fetch();
  if (!$college) {
    throw new RuntimeException('Pick a college first.');
  }
  $dup = $pdo->prepare(
    'SELECT department_name FROM departments WHERE college_id = ? AND department_name = ? AND is_active = 1 LIMIT 1'
  );
  $dup->execute([$cid, $name]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this college.');
  }
  $pdo->prepare('UPDATE colleges SET use_departments = 1 WHERE college_id = ?')->execute([$cid]);
  $deptCode = unique_code(
    $pdo,
    'departments',
    'department_code',
    $college['college_code'] . '-' . code_from_name($name, 6),
    null,
    'department_id'
  );
  $insDept = $pdo->prepare(
    'INSERT INTO departments (college_id, department_code, department_name, is_active) VALUES (?, ?, ?, 1)'
  );
  $insDept->execute([$cid, $deptCode, $name]);
  $deptId = (int) $pdo->lastInsertId();
  $program = insert_program($pdo, $cid, $deptId, $name, (string) $college['college_code']);
  return [
    'id' => public_id('dept', $deptId),
    'name' => $name,
    'programs' => [$program],
  ];
}

function add_program(PDO $pdo, string $departmentId, string $name): array
{
  $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
  if ($name === '') {
    throw new RuntimeException('Enter a name for the program.');
  }
  $did = parse_id($departmentId, 'dept');
  if (!$did) {
    throw new RuntimeException('Pick a department first.');
  }
  $dept = $pdo->prepare(
    'SELECT d.department_id, d.college_id, c.college_code
     FROM departments d
     JOIN colleges c ON c.college_id = d.college_id
     WHERE d.department_id = ? AND d.is_active = 1'
  );
  $dept->execute([$did]);
  $row = $dept->fetch();
  if (!$row) {
    throw new RuntimeException('Pick a department first.');
  }
  $dup = $pdo->prepare(
    'SELECT program_id FROM programs WHERE department_id = ? AND program_name = ? AND is_active = 1 LIMIT 1'
  );
  $dup->execute([$did, $name]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this department.');
  }
  return insert_program($pdo, (int) $row['college_id'], $did, $name, (string) $row['college_code']);
}

function insert_program(PDO $pdo, int $collegeId, int $departmentId, string $name, string $collegeCode): array
{
  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    $collegeCode . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, department_id, program_code, program_name, represents_college, normal_years, is_active)
     VALUES (?, ?, ?, ?, 0, 4, 1)'
  );
  $ins->execute([$collegeId, $departmentId, $code, $name]);
  return [
    'id' => public_id('d', (int) $pdo->lastInsertId()),
    'name' => $name,
  ];
}

function direct_program_id(PDO $pdo, int $collegeId): int
{
  $stmt = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND represents_college = 1 AND is_active = 1
     LIMIT 1'
  );
  $stmt->execute([$collegeId]);
  $id = (int) $stmt->fetchColumn();
  if ($id > 0) {
    return $id;
  }
  $fallback = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND department_id IS NULL AND is_active = 1
     ORDER BY program_id
     LIMIT 1'
  );
  $fallback->execute([$collegeId]);
  return (int) $fallback->fetchColumn();
}

function upsert_direct_program(PDO $pdo, int $collegeId, array $program, string $collegeCode): int
{
  $name = trim((string) ($program['name'] ?? ''));
  if ($name === '') {
    throw new RuntimeException('Enter a program name.');
  }
  $id = parse_id((string) ($program['id'] ?? ''), 'd');
  if (!$id) {
    $id = direct_program_id($pdo, $collegeId);
  }
  $dup = $pdo->prepare(
    'SELECT program_id FROM programs
     WHERE college_id = ? AND program_name = ? AND is_active = 1 AND program_id <> ?
     LIMIT 1'
  );
  $dup->execute([$collegeId, $name, $id ?: 0]);
  if ($dup->fetch()) {
    throw new RuntimeException('"' . $name . '" is already under this college.');
  }
  if ($id) {
    $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      $pdo->prepare(
        'UPDATE programs
         SET college_id = ?, department_id = NULL, program_name = ?, represents_college = 1, archived_label = NULL, is_active = 1
         WHERE program_id = ?'
      )->execute([$collegeId, $name, $id]);
      return $id;
    }
  }
  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    ($collegeCode !== '' ? $collegeCode : 'P') . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, department_id, program_code, program_name, represents_college, normal_years, is_active)
     VALUES (?, NULL, ?, ?, 1, 4, 1)'
  );
  $ins->execute([$collegeId, $code, $name]);
  return (int) $pdo->lastInsertId();
}

function ensure_sole_program(PDO $pdo, int $collegeId, string $collegeName, string $collegeCode): int
{
  return upsert_direct_program($pdo, $collegeId, ['name' => $collegeName], $collegeCode);
}

function insert_department(PDO $pdo, int $collegeId, string $name, string $collegeCode): int
{
  $code = unique_code(
    $pdo,
    'departments',
    'department_code',
    $collegeCode . '-' . code_from_name($name, 6),
    null,
    'department_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO departments (college_id, department_code, department_name, is_active) VALUES (?, ?, ?, 1)'
  );
  $ins->execute([$collegeId, $code, $name]);
  return (int) $pdo->lastInsertId();
}

function sync_org(PDO $pdo, array $doc): void
{
  $active = is_array($doc['active'] ?? null) ? $doc['active'] : [];
  $archived = is_array($doc['archivedColleges'] ?? null) ? $doc['archivedColleges'] : [];
  $archivedDepts = is_array($doc['archivedDeptOnly'] ?? null) ? $doc['archivedDeptOnly'] : [];

  $seenColleges = [];
  $seenPrograms = [];
  $seenDepartments = [];

  foreach ($active as $c) {
    $cid = upsert_college($pdo, $c, true);
    $seenColleges[$cid] = true;
    $useDept = !empty($c['useDepartments']);
    $depts = is_array($c['departments'] ?? null) ? $c['departments'] : [];
    if ($useDept) {
      foreach ($depts as $d) {
        [$deptId, $programIds] = upsert_department_with_programs($pdo, $cid, $d, $c, true);
        $seenDepartments[$deptId] = true;
        foreach ($programIds as $pid) {
          $seenPrograms[$pid] = true;
        }
      }
      if (!$depts) {
        $keep = direct_program_id($pdo, $cid);
        if ($keep) {
          $seenPrograms[$keep] = true;
        }
      }
    } else {
      $program = is_array($c['program'] ?? null) ? $c['program'] : null;
      $pname = trim((string) ($program['name'] ?? ''));
      if ($pname !== '') {
        $seenPrograms[upsert_direct_program($pdo, $cid, $program, (string) ($c['code'] ?? ''))] = true;
      } elseif (!array_key_exists('program', $c)) {
        $keep = direct_program_id($pdo, $cid);
        if ($keep) {
          $seenPrograms[$keep] = true;
        }
      }
    }
  }

  foreach ($archived as $c) {
    $cid = upsert_college($pdo, $c, false);
    $seenColleges[$cid] = true;
    $depts = is_array($c['departments'] ?? null) ? $c['departments'] : [];
    foreach ($depts as $d) {
      [$deptId, $programIds] = upsert_department_with_programs($pdo, $cid, $d, $c, false);
      $seenDepartments[$deptId] = true;
      foreach ($programIds as $pid) {
        $seenPrograms[$pid] = true;
      }
    }
  }

  foreach ($archivedDepts as $d) {
    $deptId = parse_id((string) ($d['id'] ?? ''), 'dept');
    if (!$deptId) {
      $deptId = parse_id((string) ($d['id'] ?? ''), 'd');
      if ($deptId) {
        $pdo->prepare('UPDATE programs SET is_active = 0, archived_label = ? WHERE program_id = ?')
          ->execute([(string) ($d['archivedLabel'] ?? ''), $deptId]);
        $seenPrograms[$deptId] = true;
      }
      continue;
    }
    $label = (string) ($d['archivedLabel'] ?? '');
    $pdo->prepare('UPDATE departments SET is_active = 0, archived_label = ? WHERE department_id = ?')
      ->execute([$label, $deptId]);
    $pdo->prepare('UPDATE programs SET is_active = 0, archived_label = ? WHERE department_id = ?')
      ->execute([$label, $deptId]);
    $seenDepartments[$deptId] = true;
    foreach ($pdo->query('SELECT program_id FROM programs WHERE department_id = ' . (int) $deptId)->fetchAll() as $row) {
      $seenPrograms[(int) $row['program_id']] = true;
    }
  }

  foreach ($pdo->query('SELECT college_id FROM colleges')->fetchAll() as $row) {
    $cid = (int) $row['college_id'];
    if (!isset($seenColleges[$cid])) {
      $pdo->prepare('UPDATE colleges SET is_active = 0 WHERE college_id = ?')->execute([$cid]);
    }
  }
  foreach ($pdo->query('SELECT program_id FROM programs')->fetchAll() as $row) {
    $pid = (int) $row['program_id'];
    if (!isset($seenPrograms[$pid])) {
      $pdo->prepare('UPDATE programs SET is_active = 0 WHERE program_id = ?')->execute([$pid]);
    }
  }
  foreach ($pdo->query('SELECT department_id FROM departments')->fetchAll() as $row) {
    $did = (int) $row['department_id'];
    if (!isset($seenDepartments[$did])) {
      $pdo->prepare('UPDATE departments SET is_active = 0 WHERE department_id = ?')->execute([$did]);
    }
  }
}

function upsert_department_with_programs(PDO $pdo, int $collegeId, array $d, array $college, bool $active): array
{
  $name = trim((string) ($d['name'] ?? ''));
  if ($name === '') {
    throw new RuntimeException('Enter a name for the department.');
  }
  $label = (string) ($d['archivedLabel'] ?? $college['archivedLabel'] ?? '');
  $deptId = parse_id((string) ($d['id'] ?? ''), 'dept');
  $collegeCode = (string) ($college['code'] ?? 'P');
  $known = false;
  if ($deptId) {
    $exists = $pdo->prepare('SELECT department_id FROM departments WHERE department_id = ?');
    $exists->execute([$deptId]);
    $known = (bool) $exists->fetch();
  }
  if (!$known) {
    $byName = $pdo->prepare(
      'SELECT department_id FROM departments WHERE college_id = ? AND department_name = ? ORDER BY is_active DESC, department_id LIMIT 1'
    );
    $byName->execute([$collegeId, $name]);
    $deptId = (int) $byName->fetchColumn();
    $known = $deptId > 0;
  }
  if ($known) {
    $pdo->prepare(
      'UPDATE departments SET college_id = ?, department_name = ?, archived_label = ?, is_active = ? WHERE department_id = ?'
    )->execute([$collegeId, $name, $active ? null : $label, $active ? 1 : 0, $deptId]);
  } else {
    $deptId = insert_department($pdo, $collegeId, $name, $collegeCode);
  }

  $programs = is_array($d['programs'] ?? null) ? $d['programs'] : [];
  if (!$programs) {
    $existingPrograms = $pdo->prepare(
      'SELECT program_id, program_name FROM programs WHERE department_id = ? ORDER BY program_name'
    );
    $existingPrograms->execute([$deptId]);
    foreach ($existingPrograms->fetchAll() as $existingProgram) {
      $programs[] = [
        'id' => public_id('d', (int) $existingProgram['program_id']),
        'name' => (string) $existingProgram['program_name'],
      ];
    }
  }
  if (!$programs) {
    $programs = [['id' => '', 'name' => $name]];
  }
  $ids = [];
  foreach ($programs as $p) {
    if (!is_array($p)) {
      continue;
    }
    $pname = trim((string) ($p['name'] ?? ''));
    if ($pname === '') {
      continue;
    }
    $pid = parse_id((string) ($p['id'] ?? ''), 'd');
    $programKnown = false;
    if ($pid) {
      $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
      $exists->execute([$pid]);
      $programKnown = (bool) $exists->fetch();
    }
    if (!$programKnown) {
      $byName = $pdo->prepare(
        'SELECT program_id FROM programs WHERE college_id = ? AND program_name = ? ORDER BY is_active DESC, program_id LIMIT 1'
      );
      $byName->execute([$collegeId, $pname]);
      $pid = (int) $byName->fetchColumn();
      $programKnown = $pid > 0;
    }
    if ($programKnown) {
      $pdo->prepare(
        'UPDATE programs SET college_id = ?, department_id = ?, program_name = ?, represents_college = 0, archived_label = ?, is_active = ?
         WHERE program_id = ?'
      )->execute([$collegeId, $deptId, $pname, $active ? null : $label, $active ? 1 : 0, $pid]);
      $ids[] = $pid;
      continue;
    }
    $created = insert_program($pdo, $collegeId, $deptId, $pname, $collegeCode);
    $ids[] = (int) parse_id($created['id'], 'd');
  }
  if (!$ids) {
    $created = insert_program($pdo, $collegeId, $deptId, $name, $collegeCode);
    $ids[] = (int) parse_id($created['id'], 'd');
  }
  return [$deptId, $ids];
}

function upsert_college(PDO $pdo, array $c, bool $active): int
{
  $name = trim((string) ($c['name'] ?? ''));
  $code = strtoupper(trim((string) ($c['code'] ?? '')));
  $useDept = !empty($c['useDepartments']);
  $label = (string) ($c['archivedLabel'] ?? '');
  $id = parse_id((string) ($c['id'] ?? ''), 'c');

  if ($id) {
    $exists = $pdo->prepare('SELECT college_id FROM colleges WHERE college_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      if ($code === '') {
        $code = code_from_name($name);
      }
      $code = unique_code($pdo, 'colleges', 'college_code', $code, $id, 'college_id');
      $pdo->prepare(
        'UPDATE colleges SET college_code = ?, college_name = ?, use_departments = ?, archived_label = ?, is_active = ?
         WHERE college_id = ?'
      )->execute([$code, $name, $useDept ? 1 : 0, $active ? null : $label, $active ? 1 : 0, $id]);
      return $id;
    }
  }

  if ($code === '') {
    $code = code_from_name($name);
  }
  $code = unique_code($pdo, 'colleges', 'college_code', $code, null, 'college_id');
  $ins = $pdo->prepare(
    'INSERT INTO colleges (college_code, college_name, use_departments, archived_label, is_active)
     VALUES (?, ?, ?, ?, ?)'
  );
  $ins->execute([$code, $name, $useDept ? 1 : 0, $active ? null : $label, $active ? 1 : 0]);
  return (int) $pdo->lastInsertId();
}

function upsert_program(PDO $pdo, int $collegeId, array $d, array $college, bool $represents, bool $active): int
{
  $name = trim((string) ($d['name'] ?? ''));
  $id = parse_id((string) ($d['id'] ?? ''), 'd');
  $label = (string) ($d['archivedLabel'] ?? $college['archivedLabel'] ?? '');

  if ($id) {
    $exists = $pdo->prepare('SELECT program_id FROM programs WHERE program_id = ?');
    $exists->execute([$id]);
    if ($exists->fetch()) {
      $pdo->prepare(
        'UPDATE programs SET college_id = ?, program_name = ?, represents_college = ?, archived_label = ?, is_active = ?
         WHERE program_id = ?'
      )->execute([$collegeId, $name, $represents ? 1 : 0, $active ? null : $label, $active ? 1 : 0, $id]);
      return $id;
    }
  }

  $code = unique_code(
    $pdo,
    'programs',
    'program_code',
    ((string) ($college['code'] ?? 'P')) . '-' . code_from_name($name, 6),
    null,
    'program_id'
  );
  $ins = $pdo->prepare(
    'INSERT INTO programs (college_id, program_code, program_name, represents_college, normal_years, archived_label, is_active)
     VALUES (?, ?, ?, ?, 4, ?, ?)'
  );
  $ins->execute([$collegeId, $code, $name, $represents ? 1 : 0, $active ? null : $label, $active ? 1 : 0]);
  return (int) $pdo->lastInsertId();
}

function org_audit_state(PDO $pdo): array
{
  $colleges = [];
  foreach ($pdo->query('SELECT college_id, college_code, college_name, use_departments, is_active FROM colleges') as $row) {
    $colleges[(int) $row['college_id']] = [
      'code' => (string) $row['college_code'],
      'name' => (string) $row['college_name'],
      'useDepartments' => (int) $row['use_departments'] === 1,
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  $departments = [];
  foreach ($pdo->query('SELECT department_id, college_id, department_name, is_active FROM departments') as $row) {
    $departments[(int) $row['department_id']] = [
      'collegeId' => (int) $row['college_id'],
      'name' => (string) $row['department_name'],
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  $programs = [];
  foreach ($pdo->query('SELECT program_id, college_id, department_id, program_name, represents_college, is_active FROM programs') as $row) {
    $programs[(int) $row['program_id']] = [
      'collegeId' => (int) $row['college_id'],
      'departmentId' => (int) ($row['department_id'] ?? 0),
      'name' => (string) $row['program_name'],
      'represents' => (int) $row['represents_college'] === 1,
      'active' => (int) $row['is_active'] === 1,
    ];
  }
  return ['colleges' => $colleges, 'departments' => $departments, 'programs' => $programs];
}

function org_college_name(array $state, int $collegeId): string
{
  $row = $state['colleges'][$collegeId] ?? null;
  if (!$row) {
    return 'a college';
  }
  $name = trim((string) $row['name']);
  return $name !== '' ? $name : 'a college';
}

function org_department_name(array $state, int $departmentId): string
{
  $row = $state['departments'][$departmentId] ?? null;
  if (!$row) {
    return 'a department';
  }
  $name = trim((string) $row['name']);
  return $name !== '' ? $name : 'a department';
}

function org_audit_changes(array $before, array $after): array
{
  $out = [];
  $collegeArchived = [];
  $newColleges = [];
  foreach ($after['colleges'] as $id => $college) {
    $name = trim((string) $college['name']) !== '' ? (string) $college['name'] : 'a college';
    if (!isset($before['colleges'][$id])) {
      if ($college['active']) {
        $out[] = ['action' => 'Add', 'area' => 'college', 'summary' => 'Added college ' . $name . '.'];
        $newColleges[$id] = $name;
      }
      continue;
    }
    $was = $before['colleges'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    if ($was['active'] && !$college['active']) {
      $out[] = ['action' => 'Archive', 'area' => 'college', 'summary' => 'Archived college ' . $wasName . '.'];
      $collegeArchived[$id] = true;
      continue;
    }
    if (!$was['active'] && $college['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'college', 'summary' => 'Restored college ' . $name . '.'];
    }
    if (!$college['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ((string) $was['code'] !== (string) $college['code']) {
      $parts[] = 'code ' . $was['code'] . ' to ' . $college['code'];
    }
    if ((bool) $was['useDepartments'] !== (bool) $college['useDepartments']) {
      $parts[] = $college['useDepartments'] ? 'now uses departments' : 'no longer uses departments';
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'college', 'summary' => 'Edited college ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }

  $departmentArchived = [];
  $newDepartments = [];
  foreach ($after['departments'] as $id => $dept) {
    $name = trim((string) $dept['name']) !== '' ? (string) $dept['name'] : 'a department';
    $collegeName = org_college_name($after, (int) $dept['collegeId']);
    if (!isset($before['departments'][$id])) {
      if ($dept['active']) {
        $out[] = ['action' => 'Add', 'area' => 'department', 'summary' => 'Added department ' . $name . ' under ' . $collegeName . '.'];
        $newDepartments[$id] = $name;
      }
      continue;
    }
    $was = $before['departments'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    $wasCollege = org_college_name($before, (int) $was['collegeId']);
    if ($was['active'] && !$dept['active']) {
      if (empty($collegeArchived[(int) $was['collegeId']]) && empty($collegeArchived[(int) $dept['collegeId']])) {
        $out[] = ['action' => 'Archive', 'area' => 'department', 'summary' => 'Archived department ' . $wasName . ' under ' . $wasCollege . '.'];
      }
      $departmentArchived[$id] = true;
      continue;
    }
    if (!$was['active'] && $dept['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'department', 'summary' => 'Restored department ' . $name . ' under ' . $collegeName . '.'];
    }
    if (!$dept['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ((int) $was['collegeId'] !== (int) $dept['collegeId']) {
      $parts[] = 'moved from ' . $wasCollege . ' to ' . $collegeName;
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'department', 'summary' => 'Edited department ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }

  foreach ($after['programs'] as $id => $program) {
    $name = trim((string) $program['name']) !== '' ? (string) $program['name'] : 'a program';
    $collegeName = org_college_name($after, (int) $program['collegeId']);
    $deptName = (int) $program['departmentId'] > 0 ? org_department_name($after, (int) $program['departmentId']) : '';
    $place = $deptName !== '' ? $deptName . ', ' . $collegeName : $collegeName;
    if (!isset($before['programs'][$id])) {
      if (!$program['active']) {
        continue;
      }
      if ($program['represents'] && isset($newColleges[(int) $program['collegeId']]) && strcasecmp($name, $newColleges[(int) $program['collegeId']]) === 0) {
        continue;
      }
      if (!$program['represents'] && isset($newDepartments[(int) $program['departmentId']]) && strcasecmp($name, $newDepartments[(int) $program['departmentId']]) === 0) {
        continue;
      }
      $out[] = ['action' => 'Add', 'area' => 'program', 'summary' => 'Added program ' . $name . ' under ' . $place . '.'];
      continue;
    }
    $was = $before['programs'][$id];
    $wasName = trim((string) $was['name']) !== '' ? (string) $was['name'] : $name;
    $wasCollege = org_college_name($before, (int) $was['collegeId']);
    $wasDept = (int) $was['departmentId'] > 0 ? org_department_name($before, (int) $was['departmentId']) : '';
    $wasPlace = $wasDept !== '' ? $wasDept . ', ' . $wasCollege : $wasCollege;
    if ($was['active'] && !$program['active']) {
      $parentArchived = !empty($collegeArchived[(int) $was['collegeId']]) || !empty($collegeArchived[(int) $program['collegeId']]);
      $deptArchived = !empty($departmentArchived[(int) $was['departmentId']]) || !empty($departmentArchived[(int) $program['departmentId']]);
      if (!$parentArchived && !$deptArchived) {
        $out[] = ['action' => 'Archive', 'area' => 'program', 'summary' => 'Archived program ' . $wasName . ' under ' . $wasPlace . '.'];
      }
      continue;
    }
    if (!$was['active'] && $program['active']) {
      $out[] = ['action' => 'Unarchive', 'area' => 'program', 'summary' => 'Restored program ' . $name . ' under ' . $place . '.'];
    }
    if (!$program['active']) {
      continue;
    }
    $parts = [];
    if ($wasName !== $name) {
      $parts[] = 'name ' . $wasName . ' to ' . $name;
    }
    if ($wasPlace !== $place) {
      $parts[] = 'moved from ' . $wasPlace . ' to ' . $place;
    }
    if ($parts) {
      $out[] = ['action' => 'Edit', 'area' => 'program', 'summary' => 'Edited program ' . $wasName . ': ' . implode('; ', $parts) . '.'];
    }
  }
  return $out;
}

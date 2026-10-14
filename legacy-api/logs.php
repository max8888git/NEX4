<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pdo = db();
$me = session_user_payload($pdo);
if (!$me) {
  json_error('Log in to view the transaction log.', 401);
}
if (!in_array((string) $me['role'], ['admin', 'super_admin'], true)) {
  json_error('Only the Records Officer or University Registrar can view the transaction log.', 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') {
  json_error('Unsupported method', 405);
}

$rows = $pdo->query(
  'SELECT log_id, created_at, actor_name, actor_role, action, area, summary
   FROM transaction_logs
   ORDER BY created_at DESC, log_id DESC
   LIMIT 500'
)->fetchAll();

$logs = [];
foreach ($rows as $row) {
  $role = (string) $row['actor_role'];
  $logs[] = [
    'id' => (int) $row['log_id'],
    'createdAt' => (string) $row['created_at'],
    'actorName' => (string) $row['actor_name'],
    'actorRole' => $role,
    'actorRoleLabel' => account_role_label($role),
    'action' => (string) $row['action'],
    'area' => (string) $row['area'],
    'summary' => (string) $row['summary'],
  ];
}

json_ok(['logs' => $logs]);

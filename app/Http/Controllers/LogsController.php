<?php

namespace App\Http\Controllers;

use App\Models\TransactionLog;
use App\Support\RegistrarSession;

class LogsController extends Controller
{
    public function handle()
    {
        $me = RegistrarSession::user();
        if (! $me) {
            return response()->json(['ok' => false, 'error' => 'Log in to view the transaction log.'], 401);
        }
        if (! in_array((string) $me['role'], ['admin', 'super_admin'], true)) {
            return response()->json(['ok' => false, 'error' => 'Only the Records Officer or University Registrar can view the transaction log.'], 403);
        }
        $rows = TransactionLog::query()
            ->orderByDesc('created_at')
            ->orderByDesc('log_id')
            ->limit(500)
            ->get(['log_id', 'created_at', 'actor_name', 'actor_role', 'action', 'area', 'summary']);

        $logs = [];
        foreach ($rows as $row) {
            $role = (string) $row->actor_role;
            $logs[] = [
                'id' => (int) $row->log_id,
                'createdAt' => (string) $row->created_at,
                'actorName' => (string) $row->actor_name,
                'actorRole' => $role,
                'actorRoleLabel' => RegistrarSession::roleLabel($role),
                'action' => (string) $row->action,
                'area' => (string) $row->area,
                'summary' => (string) $row->summary,
            ];
        }

        return response()->json(['ok' => true, 'logs' => $logs]);
    }
}
